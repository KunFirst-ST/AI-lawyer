<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/message-helpers.php';
require_once __DIR__ . '/../services/NotificationService.php';
require_once __DIR__ . '/../services/ConversationService.php';

try {
    requireLogin();
    verify_csrf();
    rateLimit('message_send', 30, 60);

    $user = currentUser();
    $receiverId = (int) ($_POST['receiver_id'] ?? 0);
    $message = trim((string) ($_POST['message'] ?? ''));
    $caseIdValue = isset($_POST['case_id']) && $_POST['case_id'] !== '' ? (int) $_POST['case_id'] : 0;
    $bookingIdValue = isset($_POST['booking_id']) && $_POST['booking_id'] !== '' ? (int) $_POST['booking_id'] : 0;
    $caseId = $caseIdValue > 0 ? $caseIdValue : null;
    $bookingId = $bookingIdValue > 0 ? $bookingIdValue : null;
    $requestedType = (string) ($_POST['message_type'] ?? 'text');
    $callType = (string) ($_POST['call_type'] ?? 'audio');
    $filePath = null;
    $messageType = 'text';
    $callUrl = null;
    $callRoom = null;

    if (!in_array($requestedType, ['text', 'call'], true)) {
        jsonResponse(false, 'ชนิดแชตไม่ถูกต้อง', [], ['message_type' => 'invalid'], 422);
    }

    if ($receiverId <= 0 || ($message === '' && empty($_FILES['message_file']) && $requestedType !== 'call')) {
        jsonResponse(false, 'กรุณาพิมพ์แชตหรือแนบไฟล์', [], [], 422);
    }

    (new ConversationService())->assertCanTalk((int) $user['id'], $receiverId, $caseId, $bookingId);

    if ($requestedType === 'call') {
        if (!in_array($callType, ['audio', 'video'], true)) {
            jsonResponse(false, 'ประเภทการโทรไม่ถูกต้อง', [], ['call_type' => 'invalid'], 422);
        }

        $messageType = 'call';
        $callRoom = 'room_' . bin2hex(random_bytes(12));
        $callUrl = '/public/call.php?room=' . rawurlencode($callRoom) . '&type=' . rawurlencode($callType);
        if ($message === '') {
            $message = $callType === 'video' ? 'เริ่มวิดีโอคอล' : 'เริ่มโทรเสียง';
        }
    } elseif (!empty($_FILES['message_file'])) {
        $tmpName = $_FILES['message_file']['tmp_name'] ?? '';
        $mime = is_file($tmpName) ? ((new finfo(FILEINFO_MIME_TYPE))->file($tmpName) ?: '') : '';
        $messageType = str_starts_with($mime, 'image/')
            ? 'image'
            : ((str_starts_with($mime, 'audio/') || $mime === 'video/webm') ? 'audio' : 'file');
        $filePath = uploadFile($_FILES['message_file'], 'message_media');
    }

    ensureMessageMediaColumns();
    $stmt = db()->prepare('INSERT INTO messages (case_id, booking_id, sender_id, receiver_id, message, file_path, message_type, call_type, call_url, call_room) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$caseId, $bookingId, (int) $user['id'], $receiverId, $message, $filePath, $messageType, $messageType === 'call' ? $callType : null, $callUrl, $callRoom]);
    $messageId = (int) db()->lastInsertId();

    $sentStmt = db()->prepare(
        'SELECT m.*, su.name AS sender_name, ru.name AS receiver_name
         FROM messages m
         JOIN users su ON su.id = m.sender_id
         JOIN users ru ON ru.id = m.receiver_id
         WHERE m.id = ?
         LIMIT 1'
    );
    $sentStmt->execute([$messageId]);
    $sentMessage = $sentStmt->fetch();

    (new NotificationService())->create(
        $receiverId,
        'มีแชตใหม่',
        $user['name'] . ($messageType === 'call' ? ' ชวนคุณเข้าห้องโทร' : ' ส่งแชตถึงคุณ' . ($filePath ? ' พร้อมไฟล์แนบ' : '')),
        'message'
    );

    jsonResponse(true, $messageType === 'call' ? 'สร้างห้องสนทนาแล้ว' : 'ส่งในแชตแล้ว', [
        'message' => $sentMessage ? serializeConversationMessage($sentMessage, (int) $user['id']) : null,
        'message_type' => $messageType,
        'call_url' => $callUrl ? url($callUrl) : null,
    ]);
} catch (DomainException $exception) {
    jsonResponse(false, $exception->getMessage(), [], ['receiver_id' => 'forbidden'], 403);
} catch (Throwable $exception) {
    jsonResponse(false, 'เกิดข้อผิดพลาดในการส่งแชต', [], ['detail' => $exception->getMessage()], 500);
}
