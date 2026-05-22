<?php

require_once __DIR__ . '/../config/database.php';

final class NotificationService
{
    public function create(int $userId, string $title, string $message, string $type = 'system'): void
    {
        $stmt = db()->prepare('INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $title, $message, $type]);
    }

    public function markRead(int $notificationId, int $userId): void
    {
        $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
        $stmt->execute([$notificationId, $userId]);
    }
}
