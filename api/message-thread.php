<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/message-helpers.php';
require_once __DIR__ . '/../services/ConversationService.php';

try {
    requireLogin();
    rateLimit('message_thread', 120, 60);

    $user = currentUser();
    $currentUserId = (int) $user['id'];
    $peerId = (int) ($_GET['peer_id'] ?? 0);

    if ($peerId <= 0) {
        jsonResponse(false, 'กรุณาเลือกคู่สนทนา', [], ['peer_id' => 'required'], 422);
    }

    $conversation = new ConversationService();
    $conversation->assertCanTalk($currentUserId, $peerId);
    $conversation->markThreadRead($currentUserId, $peerId);

    ensureMessageMediaColumns();
    $threadStmt = db()->prepare(
        'SELECT m.*, su.name AS sender_name, ru.name AS receiver_name
         FROM messages m
         JOIN users su ON su.id = m.sender_id
         JOIN users ru ON ru.id = m.receiver_id
         WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
         ORDER BY m.created_at ASC
         LIMIT 200'
    );
    $threadStmt->execute([$currentUserId, $peerId, $peerId, $currentUserId]);
    $messages = array_map(
        fn (array $message): array => serializeConversationMessage($message, $currentUserId),
        $threadStmt->fetchAll()
    );

    jsonResponse(true, 'โหลดแชตสำเร็จ', [
        'messages' => $messages,
        'last_message_id' => $messages ? (int) end($messages)['id'] : 0,
    ]);
} catch (DomainException $exception) {
    jsonResponse(false, $exception->getMessage(), [], ['peer_id' => 'forbidden'], 403);
} catch (Throwable $exception) {
    jsonResponse(false, 'เกิดข้อผิดพลาดในการโหลดแชต', [], ['detail' => $exception->getMessage()], 500);
}
