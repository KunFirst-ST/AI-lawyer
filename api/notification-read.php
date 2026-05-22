<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/NotificationService.php';

try {
    requireLogin();
    verify_csrf();

    $user = currentUser();
    $notificationId = (int) ($_POST['notification_id'] ?? 0);
    (new NotificationService())->markRead($notificationId, (int) $user['id']);

    jsonResponse(true, 'อ่านแจ้งเตือนแล้ว');
} catch (Throwable $exception) {
    jsonResponse(false, 'เกิดข้อผิดพลาด', [], ['detail' => $exception->getMessage()], 500);
}
