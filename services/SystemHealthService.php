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
        $checks[] = $this->check('app', 'Application boot', 'required', true, 'Core PHP bootstrap is available.');
        $checks[] = $this->databaseCheck();
        $checks[] = $this->writableCheck('uploads_writable', 'Uploads writable', dirname(__DIR__) . '/uploads', 'required');
        $checks[] = $this->writableCheck('sessions_writable', 'Sessions writable', dirname(__DIR__) . '/storage/sessions', 'required');
        $checks[] = $this->writableCheck('logs_writable', 'Logs writable', dirname(__DIR__) . '/storage/logs', 'required');
        $checks[] = $this->rateLimitStorageCheck();
        $checks[] = $this->debugCheck();
        $checks[] = $this->httpsCheck();
        $checks[] = $this->aiCheck();
        $checks[] = $this->mailCheck();
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
            return $this->check('database', 'Database connection', 'required', true, 'Database responds to SELECT 1.');
        } catch (Throwable $exception) {
            return $this->check('database', 'Database connection', 'required', false, 'Database is not reachable.');
        }
    }

    private function writableCheck(string $key, string $label, string $path, string $severity): array
    {
        $message = $this->includePrivateDetails ? $path : ($label . ' check completed.');
        return $this->check($key, $label, $severity, is_dir($path) && is_writable($path), $message);
    }

    private function rateLimitStorageCheck(): array
    {
        $path = dirname(__DIR__) . '/storage/rate_limits';
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }

        $message = $this->includePrivateDetails ? $path : 'Rate limit storage check completed.';
        return $this->check('rate_limits_writable', 'Rate limit storage writable', 'required', is_dir($path) && is_writable($path), $message);
    }

    private function debugCheck(): array
    {
        $production = app_config('env', 'local') === 'production';
        $debug = (bool) app_config('debug', false);
        return [
            'key' => 'debug_mode',
            'label' => 'Debug mode',
            'severity' => $production ? 'required' : 'info',
            'status' => $production && $debug ? 'error' : 'ok',
            'message' => $debug ? 'APP_DEBUG is enabled.' : 'APP_DEBUG is disabled.',
        ];
    }

    private function httpsCheck(): array
    {
        $production = app_config('env', 'local') === 'production';
        $https = request_is_https();
        return [
            'key' => 'https',
            'label' => 'HTTPS request',
            'severity' => $production ? 'required' : 'info',
            'status' => (!$production || $https) ? 'ok' : 'error',
            'message' => $https ? 'Current request is HTTPS.' : 'Current request is not HTTPS.',
        ];
    }

    private function aiCheck(): array
    {
        $config = require __DIR__ . '/../config/ai.php';
        $configured = !empty($config['api_key']);
        return [
            'key' => 'ai_configured',
            'label' => 'AI provider configured',
            'severity' => 'optional',
            'status' => $configured ? 'ok' : 'warn',
            'message' => $configured ? 'OpenAI API key is configured.' : 'OpenAI API key is missing; rule-based fallback will answer.',
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
            'label' => 'Gmail notifications',
            'severity' => 'optional',
            'status' => $configured ? 'ok' : 'warn',
            'message' => $configured ? 'SMTP notifications are ready.' : ($enabled ? 'SMTP is enabled but incomplete.' : 'SMTP notifications are disabled.'),
        ];
    }

    private function googleLoginCheck(): array
    {
        $config = app_config('social_login', [])['google'] ?? [];
        $configured = !empty($config['enabled']) && !empty($config['client_id']) && !empty($config['client_secret']);
        return [
            'key' => 'google_login_configured',
            'label' => 'Google login',
            'severity' => 'optional',
            'status' => $configured ? 'ok' : 'warn',
            'message' => $configured ? 'Google OAuth is configured.' : 'Google OAuth is disabled or incomplete.',
        ];
    }

    private function turnCheck(): array
    {
        $config = app_config('webrtc', []);
        $configured = !empty($config['turn_url']) && !empty($config['turn_username']) && !empty($config['turn_credential']);
        return [
            'key' => 'turn_configured',
            'label' => 'TURN relay for calls',
            'severity' => 'optional',
            'status' => $configured ? 'ok' : 'warn',
            'message' => $configured ? 'TURN relay is configured.' : 'TURN relay is missing; calls may fail across restrictive networks.',
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
                'label' => 'PHP extension: ' . $extension,
                'severity' => in_array($extension, ['pdo', 'fileinfo'], true) ? 'required' : 'optional',
                'status' => extension_loaded($extension) ? 'ok' : 'warn',
                'message' => extension_loaded($extension) ? 'Loaded.' : 'Not loaded.',
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
