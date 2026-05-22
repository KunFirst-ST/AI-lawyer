<?php

require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user = currentUser();
$path = null;
$originalName = null;

if (isset($_GET['payment_id'])) {
    $paymentId = (int) $_GET['payment_id'];
    $stmt = db()->prepare(
        'SELECT p.slip_image, b.user_id
         FROM payments p
         JOIN bookings b ON b.id = p.booking_id
         WHERE p.id = ?
         LIMIT 1'
    );
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch();
    if (!$payment || empty($payment['slip_image'])) {
        http_response_code(404);
        exit('File not found');
    }
    if (!isAdmin() && (int) $payment['user_id'] !== (int) $user['id']) {
        http_response_code(403);
        exit('Forbidden');
    }
    $path = $payment['slip_image'];
    $originalName = basename($path);
} elseif (isset($_GET['message_id'])) {
    $messageId = (int) $_GET['message_id'];
    $stmt = db()->prepare('SELECT * FROM messages WHERE id = ? LIMIT 1');
    $stmt->execute([$messageId]);
    $message = $stmt->fetch();
    if (!$message || empty($message['file_path'])) {
        http_response_code(404);
        exit('File not found');
    }
    if (!isAdmin() && (int) $message['sender_id'] !== (int) $user['id'] && (int) $message['receiver_id'] !== (int) $user['id']) {
        http_response_code(403);
        exit('Forbidden');
    }
    $path = $message['file_path'];
    $originalName = basename($path);
} else {
    $documentId = (int) ($_GET['document_id'] ?? 0);
    $stmt = db()->prepare(
        'SELECT d.*, l.user_id AS lawyer_user_id
         FROM documents d
         LEFT JOIN lawyers l ON l.id = d.lawyer_id
         WHERE d.id = ?
         LIMIT 1'
    );
    $stmt->execute([$documentId]);
    $document = $stmt->fetch();
    if (!$document) {
        http_response_code(404);
        exit('File not found');
    }

    $canAccess = isAdmin()
        || (int) $document['user_id'] === (int) $user['id']
        || ((int) ($document['lawyer_user_id'] ?? 0) === (int) $user['id']);

    if (!$canAccess && !empty($document['case_id']) && isLawyer()) {
        $accessStmt = db()->prepare(
            'SELECT COUNT(*)
             FROM case_matches cm
             JOIN lawyers l ON l.id = cm.lawyer_id
             WHERE cm.case_id = ? AND l.user_id = ?'
        );
        $accessStmt->execute([(int) $document['case_id'], (int) $user['id']]);
        $canAccess = (int) $accessStmt->fetchColumn() > 0;
    }

    if (!$canAccess) {
        http_response_code(403);
        exit('Forbidden');
    }

    $path = $document['file_path'];
    $originalName = $document['original_name'] ?: basename($path);
}

$uploadsRoot = realpath(dirname(__DIR__) . '/uploads');
$absolutePath = realpath(dirname(__DIR__) . '/' . ltrim((string) $path, '/'));
if (!$uploadsRoot || !$absolutePath || !str_starts_with($absolutePath, $uploadsRoot) || !is_file($absolutePath)) {
    http_response_code(404);
    exit('File not found');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($absolutePath) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($absolutePath));
header('Content-Disposition: inline; filename="' . rawurlencode((string) $originalName) . '"');
header('X-Content-Type-Options: nosniff');
readfile($absolutePath);
exit;
