<?php

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('AILAWYERSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    $sessionPath = dirname(__DIR__) . '/storage/sessions';
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0755, true);
    }
    if (is_dir($sessionPath) && is_writable($sessionPath)) {
        session_save_path($sessionPath);
    }
    session_start();
}

date_default_timezone_set('Asia/Bangkok');

function app_config(?string $key = null, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/app.php';
    }

    return $key === null ? $config : ($config[$key] ?? $default);
}

$logPath = dirname(__DIR__) . '/storage/logs';
if (!is_dir($logPath)) {
    mkdir($logPath, 0755, true);
}
if (app_config('debug', false)) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', $logPath . '/app.log');
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path): string
{
    $baseUrl = rtrim((string) app_config('base_url', ''), '/');
    return $baseUrl . '/' . ltrim($path, '/');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_messages(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token = null): void
{
    $token = $token ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if (!hash_equals($_SESSION['csrf_token'] ?? '', (string) $token)) {
        http_response_code(419);
        exit('Invalid CSRF token');
    }
}

function jsonResponse(bool $success, string $message = '', array $data = [], array $errors = [], int $status = 200): never
{
    if (!$success && !app_config('debug', false)) {
        unset($errors['detail'], $errors['exception'], $errors['trace']);
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'errors' => $errors,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function uploadFile(array $file, string $folder): ?string
{
    $allowedFolders = ['slips', 'lawyer_documents', 'case_documents', 'profile_images', 'message_media'];
    $folder = trim($folder, '/');
    if (!in_array($folder, $allowedFolders, true)) {
        throw new RuntimeException('โฟลเดอร์อัปโหลดไม่ถูกต้อง');
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('อัปโหลดไฟล์ไม่สำเร็จ');
    }

    $maxBytes = (int) app_config('upload_max_bytes', 5 * 1024 * 1024);
    if (($file['size'] ?? 0) > $maxBytes) {
        throw new RuntimeException('ไฟล์มีขนาดใหญ่เกินกำหนด');
    }

    $tmpName = $file['tmp_name'] ?? '';
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpName);
    if (!in_array($mime, app_config('allowed_upload_mimes', []), true)) {
        throw new RuntimeException('ชนิดไฟล์ไม่รองรับ');
    }

    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $safeExtension = preg_replace('/[^a-z0-9]/', '', $extension) ?: 'bin';
    $relativeDir = 'uploads/' . $folder;
    $targetDir = dirname(__DIR__) . '/' . $relativeDir;
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์อัปโหลดได้');
    }

    $filename = bin2hex(random_bytes(20)) . '.' . $safeExtension;
    $target = $targetDir . '/' . $filename;
    if (!move_uploaded_file($tmpName, $target)) {
        throw new RuntimeException('ไม่สามารถบันทึกไฟล์ได้');
    }

    return $relativeDir . '/' . $filename;
}

function ensureMessageMediaColumns(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $columns = db()->query('SHOW COLUMNS FROM messages')->fetchAll();
        $existing = array_column($columns, 'Field');
        $alters = [];
        if (!in_array('message_type', $existing, true)) {
            $alters[] = 'ADD COLUMN message_type VARCHAR(20) DEFAULT "text" AFTER file_path';
        }
        if (!in_array('call_type', $existing, true)) {
            $alters[] = 'ADD COLUMN call_type VARCHAR(20) NULL AFTER message_type';
        }
        if (!in_array('call_url', $existing, true)) {
            $alters[] = 'ADD COLUMN call_url VARCHAR(255) NULL AFTER call_type';
        }
        if ($alters) {
            db()->exec('ALTER TABLE messages ' . implode(', ', $alters));
        }
    } catch (Throwable) {
        // Pages still work with the base messages table; API validation reports insert errors if migration is unavailable.
    }
}

function uploadMimeKind(?string $path): string
{
    if (!$path) {
        return 'file';
    }

    $absolutePath = dirname(__DIR__) . '/' . ltrim($path, '/');
    if (!is_file($absolutePath)) {
        return 'file';
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($absolutePath) ?: '';
    if (str_starts_with($mime, 'image/')) {
        return 'image';
    }
    if (str_starts_with($mime, 'audio/') || $mime === 'video/webm') {
        return 'audio';
    }

    return 'file';
}

function rateLimit(string $key, int $limit, int $seconds): void
{
    $now = time();
    $_SESSION['rate_limits'] ??= [];
    $_SESSION['rate_limits'][$key] = array_values(array_filter(
        $_SESSION['rate_limits'][$key] ?? [],
        fn (int $timestamp): bool => $timestamp > ($now - $seconds)
    ));

    if (count($_SESSION['rate_limits'][$key]) >= $limit) {
        jsonResponse(false, 'ระบบได้รับคำขอถี่เกินไป กรุณารอสักครู่แล้วลองใหม่', [], ['rate_limit' => 'too_many_requests'], 429);
    }

    $_SESSION['rate_limits'][$key][] = $now;
}

function formatMoney(float|int|string|null $amount): string
{
    return number_format((float) $amount, 2) . ' บาท';
}

function formatDateThai(?string $date): string
{
    if (!$date) {
        return '-';
    }

    $timestamp = strtotime($date);
    if (!$timestamp) {
        return '-';
    }

    return date('d/m/', $timestamp) . (date('Y', $timestamp) + 543);
}

function setting(string $key, ?string $default = null): ?string
{
    require_once __DIR__ . '/../config/database.php';
    try {
        $stmt = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string) $value;
    } catch (Throwable) {
        return $default;
    }
}

function upsertSetting(string $key, string $value): void
{
    require_once __DIR__ . '/../config/database.php';
    $stmt = db()->prepare(
        'INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}

function legalCategoryName(?string $slug): string
{
    $map = [
        'criminal' => 'กฎหมายอาญา',
        'civil' => 'กฎหมายแพ่ง',
        'family' => 'กฎหมายครอบครัว',
        'labor' => 'กฎหมายแรงงาน',
        'business' => 'กฎหมายธุรกิจ',
        'land' => 'กฎหมายที่ดิน',
        'inheritance' => 'กฎหมายมรดก',
        'tax' => 'กฎหมายภาษี',
        'consumer' => 'กฎหมายผู้บริโภค',
        'intellectual_property' => 'ทรัพย์สินทางปัญญา',
        'immigration' => 'ตรวจคนเข้าเมือง',
        'bankruptcy' => 'ล้มละลาย',
        'contract' => 'สัญญา',
    ];

    return $map[$slug ?? ''] ?? ($slug ?: '-');
}

function levelLabel(?string $level): string
{
    return [
        'low' => 'ต่ำ',
        'medium' => 'ปานกลาง',
        'high' => 'สูง',
        'critical' => 'วิกฤต',
    ][$level ?? ''] ?? '-';
}
