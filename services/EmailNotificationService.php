<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/MailService.php';

final class EmailNotificationService
{
    private array $config;
    private MailService $mailer;

    public function __construct(?array $config = null, ?MailService $mailer = null)
    {
        $this->config = $config ?? require __DIR__ . '/../config/mail.php';
        $this->mailer = $mailer ?? new MailService($this->config);
    }

    public function ensureSchema(): void
    {
        if (db_driver() === 'sqlite') {
            db()->exec(
                'CREATE TABLE IF NOT EXISTS email_notifications (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    notification_id INTEGER NULL,
                    user_id INTEGER NULL,
                    recipient_email TEXT NOT NULL,
                    recipient_name TEXT,
                    subject TEXT NOT NULL,
                    html_body TEXT NOT NULL,
                    text_body TEXT NOT NULL,
                    notification_type TEXT DEFAULT "system",
                    status TEXT DEFAULT "queued",
                    attempts INTEGER DEFAULT 0,
                    last_error TEXT,
                    sent_at TEXT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (notification_id) REFERENCES notifications(id),
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )'
            );
            db()->exec('CREATE INDEX IF NOT EXISTS idx_email_notifications_status_created ON email_notifications(status, created_at)');
            db()->exec('CREATE INDEX IF NOT EXISTS idx_email_notifications_user_type_created ON email_notifications(user_id, notification_type, created_at)');
            return;
        }

        db()->exec(
            'CREATE TABLE IF NOT EXISTS email_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                notification_id INT NULL,
                user_id INT NULL,
                recipient_email VARCHAR(255) NOT NULL,
                recipient_name VARCHAR(255),
                subject VARCHAR(255) NOT NULL,
                html_body LONGTEXT NOT NULL,
                text_body LONGTEXT NOT NULL,
                notification_type VARCHAR(100) DEFAULT "system",
                status VARCHAR(20) DEFAULT "queued",
                attempts INT DEFAULT 0,
                last_error TEXT,
                sent_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (notification_id) REFERENCES notifications(id),
                FOREIGN KEY (user_id) REFERENCES users(id),
                KEY idx_email_notifications_status_created (status, created_at),
                KEY idx_email_notifications_user_type_created (user_id, notification_type, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function queueForNotification(int $notificationId, int $userId, string $title, string $message, string $type): ?int
    {
        if (!$this->shouldEmail($type)) {
            return null;
        }
        $this->ensureSchema();

        $stmt = db()->prepare('SELECT name, email FROM users WHERE id = ? AND status = "active" LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user || !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        if ($type === 'message' && $this->hasRecentMessageEmail($userId)) {
            return null;
        }

        [$html, $text] = $this->notificationContent($title, $message);
        $insert = db()->prepare(
            'INSERT INTO email_notifications
             (notification_id, user_id, recipient_email, recipient_name, subject, html_body, text_body, notification_type)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([$notificationId, $userId, $user['email'], $user['name'], $title, $html, $text, $type]);
        $emailId = (int) db()->lastInsertId();

        if (!empty($this->config['send_immediately'])) {
            $this->sendOne($emailId);
        }

        return $emailId;
    }

    public function sendTest(string $email, string $name = ''): void
    {
        $this->mailer->send(
            $email,
            $name,
            'ทดสอบแจ้งเตือนจากทนายคู่ดี',
            '<div style="font-family:Arial,sans-serif;max-width:640px;margin:auto;padding:24px"><h2 style="color:#0f766e">ทนายคู่ดี</h2><p>ระบบส่งอีเมลพร้อมใช้งานแล้ว</p><p>จากนี้คุณจะได้รับอีเมลแจ้งเตือนสำคัญของแพลตฟอร์มได้ตามปกติ</p></div>',
            "ทนายคู่ดี\n\nระบบส่งอีเมลพร้อมใช้งานแล้ว\nจากนี้คุณจะได้รับอีเมลแจ้งเตือนสำคัญของแพลตฟอร์มได้ตามปกติ"
        );
    }

    public function retryPending(int $limit = 25): array
    {
        $this->ensureSchema();
        $stmt = db()->prepare(
            'SELECT id FROM email_notifications
             WHERE status IN ("queued", "failed") AND attempts < 5
             ORDER BY created_at ASC
             LIMIT ' . max(1, min($limit, 100))
        );
        $stmt->execute();

        $result = ['sent' => 0, 'failed' => 0];
        foreach ($stmt->fetchAll() as $row) {
            if ($this->sendOne((int) $row['id'])) {
                $result['sent']++;
            } else {
                $result['failed']++;
            }
        }
        return $result;
    }

    public function summary(): array
    {
        $this->ensureSchema();
        $rows = db()->query('SELECT status, COUNT(*) AS total FROM email_notifications GROUP BY status')->fetchAll();
        $summary = ['queued' => 0, 'sent' => 0, 'failed' => 0];
        foreach ($rows as $row) {
            $summary[$row['status']] = (int) $row['total'];
        }
        return $summary;
    }

    public function recent(int $limit = 50): array
    {
        $this->ensureSchema();
        return db()->query(
            'SELECT * FROM email_notifications ORDER BY created_at DESC LIMIT ' . max(1, min($limit, 100))
        )->fetchAll();
    }

    public function status(): array
    {
        return [
            'enabled' => !empty($this->config['enabled']),
            'configured' => $this->mailer->isConfigured(),
            'host' => (string) $this->config['host'],
            'port' => (int) $this->config['port'],
            'from_address' => (string) $this->config['from_address'],
            'notify_types' => $this->config['notify_types'],
        ];
    }

    private function sendOne(int $emailId): bool
    {
        $stmt = db()->prepare('SELECT * FROM email_notifications WHERE id = ? LIMIT 1');
        $stmt->execute([$emailId]);
        $email = $stmt->fetch();
        if (!$email || $email['status'] === 'sent') {
            return true;
        }

        try {
            $this->mailer->send(
                $email['recipient_email'],
                $email['recipient_name'] ?? '',
                $email['subject'],
                $email['html_body'],
                $email['text_body']
            );
            $update = db()->prepare(
                'UPDATE email_notifications
                 SET status = "sent", attempts = attempts + 1, last_error = NULL, sent_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?'
            );
            $update->execute([$emailId]);
            return true;
        } catch (Throwable $exception) {
            $update = db()->prepare(
                'UPDATE email_notifications
                 SET status = "failed", attempts = attempts + 1, last_error = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?'
            );
            $update->execute([mb_substr($exception->getMessage(), 0, 1000), $emailId]);
            error_log('Email notification #' . $emailId . ' failed: ' . $exception->getMessage());
            return false;
        }
    }

    private function shouldEmail(string $type): bool
    {
        return !empty($this->config['enabled']) && in_array($type, $this->config['notify_types'], true);
    }

    private function hasRecentMessageEmail(int $userId): bool
    {
        $seconds = (int) $this->config['message_cooldown_seconds'];
        if ($seconds <= 0) {
            return false;
        }
        $sinceExpression = db_driver() === 'sqlite'
            ? 'datetime("now", "-' . $seconds . ' seconds")'
            : 'DATE_SUB(NOW(), INTERVAL ' . $seconds . ' SECOND)';
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM email_notifications
             WHERE user_id = ? AND notification_type = "message" AND status IN ("queued", "sent", "failed")
               AND created_at >= ' . $sinceExpression
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function notificationContent(string $title, string $message): array
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        $notificationsUrl = url('/public/notifications.php');
        $safeUrl = htmlspecialchars($notificationsUrl, ENT_QUOTES, 'UTF-8');
        $html = '<div style="font-family:Arial,sans-serif;max-width:640px;margin:auto;padding:24px;color:#102a43">'
            . '<div style="font-size:13px;color:#0f766e;font-weight:700">ทนายคู่ดี</div>'
            . '<h2 style="margin:12px 0">' . $safeTitle . '</h2>'
            . '<div style="line-height:1.7">' . $safeMessage . '</div>'
            . '<p style="margin-top:24px"><a href="' . $safeUrl . '" style="display:inline-block;background:#0f766e;color:#fff;text-decoration:none;padding:11px 18px;border-radius:6px">เปิดหน้าการแจ้งเตือน</a></p>'
            . '<p style="font-size:12px;color:#64748b;margin-top:28px">อีเมลนี้ส่งโดยระบบแจ้งเตือนของทนายคู่ดี</p>'
            . '</div>';
        $text = "ทนายคู่ดี\n\n" . $title . "\n\n" . $message . "\n\nเปิดหน้าการแจ้งเตือน: " . $notificationsUrl;
        return [$html, $text];
    }
}
