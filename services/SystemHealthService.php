<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/ActivityService.php';

final class SystemHealthService
{
    private bool $includePrivateDetails = false;

    public function summary(bool $includePrivateDetails = false): array
    {
        $this->includePrivateDetails = $includePrivateDetails;
        $checks = [];
        $checks[] = $this->check('app', 'เริ่มระบบแอปพลิเคชัน', 'required', true, 'ระบบ PHP หลักพร้อมทำงาน');
        $checks[] = $this->databaseCheck();
        $checks[] = $this->writableCheck('uploads_writable', 'พื้นที่เก็บไฟล์อัปโหลด', dirname(__DIR__) . '/uploads', 'required');
        $checks[] = $this->writableCheck('sessions_writable', 'พื้นที่เก็บเซสชันผู้ใช้', dirname(__DIR__) . '/storage/sessions', 'required');
        $checks[] = $this->writableCheck('logs_writable', 'พื้นที่เก็บบันทึกระบบ', dirname(__DIR__) . '/storage/logs', 'required');
        $checks[] = $this->rateLimitStorageCheck();
        $checks[] = $this->debugCheck();
        $checks[] = $this->httpsCheck();
        $checks[] = $this->aiCheck();
        $checks[] = $this->mailCheck();
        $checks[] = $this->n8nPaymentCheck();
        $checks[] = $this->googleLoginCheck();
        $checks[] = $this->turnCheck();
        $checks = array_merge($checks, $this->extensionChecks());

        $requiredOk = true;
        foreach ($checks as $check) {
            if (($check['severity'] ?? '') === 'required' && ($check['status'] ?? '') === 'error') {
                $requiredOk = false;
            }
        }

        $visibleChecks = $includePrivateDetails
            ? $checks
            : array_values(array_filter($checks, static fn (array $check): bool => ($check['severity'] ?? '') === 'required'));

        $summary = [
            'ok' => $requiredOk,
            'score' => $this->score($visibleChecks),
            'checks' => $visibleChecks,
            'environment' => app_config('env', 'local'),
            'server_time' => date(DATE_ATOM),
        ];

        if ($includePrivateDetails) {
            $summary['metrics'] = $this->metrics();
            $summary['php_version'] = PHP_VERSION;
        }

        return $summary;
    }

    public function recentAuditLogs(int $limit = 12): array
    {
        try {
            return (new ActivityService())->recentAuditLogs($limit);
        } catch (Throwable) {
            return [];
        }
    }

    private function databaseCheck(): array
    {
        try {
            db()->query('SELECT 1');
            return $this->check('database', 'การเชื่อมต่อฐานข้อมูล', 'required', true, 'ฐานข้อมูลตอบสนองตามปกติ');
        } catch (Throwable $exception) {
            return $this->check('database', 'การเชื่อมต่อฐานข้อมูล', 'required', false, 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้');
        }
    }

    private function writableCheck(string $key, string $label, string $path, string $severity): array
    {
        $message = $this->includePrivateDetails ? $path : 'ตรวจสิทธิ์เขียนไฟล์เรียบร้อย';
        return $this->check($key, $label, $severity, is_dir($path) && is_writable($path), $message);
    }

    private function rateLimitStorageCheck(): array
    {
        $path = dirname(__DIR__) . '/storage/rate_limits';
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }

        $message = $this->includePrivateDetails ? $path : 'ตรวจพื้นที่จำกัดการใช้งานเรียบร้อย';
        return $this->check('rate_limits_writable', 'พื้นที่เก็บข้อมูลป้องกันการใช้งานถี่เกินไป', 'required', is_dir($path) && is_writable($path), $message);
    }

    private function debugCheck(): array
    {
        $production = app_config('env', 'local') === 'production';
        $debug = (bool) app_config('debug', false);
        return [
            'key' => 'debug_mode',
            'label' => 'โหมดดีบัก',
            'severity' => $production ? 'required' : 'info',
            'status' => $production && $debug ? 'error' : 'ok',
            'message' => $debug ? 'เปิดโหมดแสดงข้อผิดพลาดอยู่ ควรปิดบนระบบจริง' : 'ปิดโหมดแสดงข้อผิดพลาดแล้ว',
        ];
    }

    private function httpsCheck(): array
    {
        $production = app_config('env', 'local') === 'production';
        $https = request_is_https();
        return [
            'key' => 'https',
            'label' => 'การใช้งานผ่าน HTTPS',
            'severity' => $production ? 'required' : 'info',
            'status' => (!$production || $https) ? 'ok' : 'error',
            'message' => $https ? 'ผู้ใช้กำลังเข้าเว็บผ่าน HTTPS' : 'คำขอนี้ยังไม่ได้เข้าเว็บผ่าน HTTPS',
        ];
    }

    private function aiCheck(): array
    {
        $config = require __DIR__ . '/../config/ai.php';
        $configured = !empty($config['api_key']);
        return [
            'key' => 'ai_configured',
            'label' => 'บริการ AI',
            'severity' => 'optional',
            'status' => $configured ? 'ok' : 'warn',
            'message' => $configured ? 'ตั้งค่าบริการ AI แล้ว' : 'ยังไม่ได้ตั้งค่า AI ระบบจะใช้คำตอบสำรอง',
        ];
    }

    private function mailCheck(): array
    {
        $config = require __DIR__ . '/../config/mail.php';
        $enabled = !empty($config['enabled']);
        $configured = $enabled
            && !empty($config['host'])
            && !empty($config['username'])
            && !empty($config['password'])
            && filter_var($config['from_address'], FILTER_VALIDATE_EMAIL) !== false;

        return [
            'key' => 'mail_configured',
            'label' => 'แจ้งเตือนผ่าน Gmail',
            'severity' => 'optional',
            'status' => $configured ? 'ok' : 'warn',
            'message' => $configured ? 'ระบบส่งอีเมลพร้อมใช้งาน' : ($enabled ? 'เปิด SMTP แล้ว แต่ข้อมูลยังไม่ครบ' : 'ยังไม่ได้เปิดระบบส่งอีเมล'),
        ];
    }

    private function googleLoginCheck(): array
    {
        $config = app_config('social_login', [])['google'] ?? [];
        $configured = !empty($config['enabled']) && !empty($config['client_id']) && !empty($config['client_secret']);
        return [
            'key' => 'google_login_configured',
            'label' => 'เข้าสู่ระบบด้วย Google',
            'severity' => 'optional',
            'status' => $configured ? 'ok' : 'warn',
            'message' => $configured ? 'ตั้งค่า Google Login แล้ว' : 'ยังไม่ได้เปิดใช้ Google Login หรือข้อมูลยังไม่ครบ',
        ];
    }

    private function n8nPaymentCheck(): array
    {
        $config = require __DIR__ . '/../config/n8n.php';
        $configured = !empty($config['payment_verification_enabled'])
            && !empty($config['payment_webhook_url'])
            && !empty($config['payment_callback_secret'])
            && function_exists('curl_init');

        return [
            'key' => 'n8n_payment_verification',
            'label' => 'ตรวจสลิปอัตโนมัติผ่าน n8n',
            'severity' => 'optional',
            'status' => $configured ? 'ok' : 'warn',
            'message' => $configured ? 'n8n พร้อมรับสลิปและส่งผลตรวจกลับ' : 'ยังไม่ได้เปิด n8n สำหรับตรวจสลิปอัตโนมัติ',
        ];
    }

    private function turnCheck(): array
    {
        $config = app_config('webrtc', []);
        $configured = !empty($config['turn_url']) && !empty($config['turn_username']) && !empty($config['turn_credential']);
        return [
            'key' => 'turn_configured',
            'label' => 'ตัวช่วยเชื่อมต่อการโทร',
            'severity' => 'optional',
            'status' => $configured ? 'ok' : 'warn',
            'message' => $configured ? 'ตั้งค่าเซิร์ฟเวอร์ช่วยเชื่อมต่อการโทรแล้ว' : 'ยังไม่ได้ตั้งค่าเซิร์ฟเวอร์ช่วยเชื่อมต่อ การโทรบางเครือข่ายอาจใช้งานไม่ได้',
        ];
    }

    private function extensionChecks(): array
    {
        $extensions = ['pdo', 'fileinfo', 'curl', 'mbstring'];
        if (envValue('DB_CONNECTION', 'mysql') === 'mysql') {
            $extensions[] = 'pdo_mysql';
        }

        $checks = [];
        foreach (array_unique($extensions) as $extension) {
            $checks[] = [
                'key' => 'php_ext_' . $extension,
                'label' => 'ส่วนขยาย PHP: ' . $extension,
                'severity' => in_array($extension, ['pdo', 'fileinfo'], true) ? 'required' : 'optional',
                'status' => extension_loaded($extension) ? 'ok' : 'warn',
                'message' => extension_loaded($extension) ? 'พร้อมใช้งาน' : 'ยังไม่พร้อมใช้งาน',
            ];
        }

        return $checks;
    }

    private function metrics(): array
    {
        return [
            'disk_free' => $this->formatBytes((int) @disk_free_space(dirname(__DIR__))),
            'disk_total' => $this->formatBytes((int) @disk_total_space(dirname(__DIR__))),
            'uploads_size' => $this->directorySizeSummary(dirname(__DIR__) . '/uploads'),
            'audit_events_60m' => $this->recentAuditTotal(),
            'failed_logins_60m' => $this->recentAuditAction('auth.login_failed'),
            'failed_email_notifications' => $this->safeCount('SELECT COUNT(*) FROM email_notifications WHERE status = "failed"'),
            'queued_email_notifications' => $this->safeCount('SELECT COUNT(*) FROM email_notifications WHERE status = "queued"'),
            'pending_lawyer_reviews' => $this->safeCount('SELECT COUNT(*) FROM lawyers WHERE status = "pending"'),
            'pending_payments' => $this->safeCount('SELECT COUNT(*) FROM payments WHERE status = "pending" AND slip_image IS NOT NULL'),
            'requested_matches' => $this->safeCount('SELECT COUNT(*) FROM cases WHERE match_status = "requested_by_user"'),
        ];
    }

    private function score(array $checks): int
    {
        if (!$checks) {
            return 0;
        }

        $score = 0;
        foreach ($checks as $check) {
            $score += match ($check['status'] ?? 'error') {
                'ok' => 100,
                'warn' => 55,
                default => 0,
            };
        }

        return (int) round($score / count($checks));
    }

    private function recentAuditTotal(): int
    {
        try {
            return array_sum((new ActivityService())->auditSummary(60));
        } catch (Throwable) {
            return 0;
        }
    }

    private function recentAuditAction(string $action): int
    {
        try {
            return (new ActivityService())->countRecentAction($action, 60);
        } catch (Throwable) {
            return 0;
        }
    }

    private function safeCount(string $sql): int
    {
        try {
            return (int) db()->query($sql)->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private function directorySizeSummary(string $path): string
    {
        if (!is_dir($path)) {
            return '0 B';
        }

        $bytes = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $bytes += (int) $file->getSize();
            }
        }

        return $this->formatBytes($bytes);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = max(0, $bytes);
        $unit = 0;
        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, $unit === 0 ? 0 : 2) . ' ' . $units[$unit];
    }

    private function check(string $key, string $label, string $severity, bool $ok, string $message): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'severity' => $severity,
            'status' => $ok ? 'ok' : 'error',
            'message' => $message,
        ];
    }
}
