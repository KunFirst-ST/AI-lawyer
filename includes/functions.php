<?php

function request_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $isHttps = request_is_https();
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

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: microphone=(self), camera=(self)');
    header('X-Permitted-Cross-Domain-Policies: none');
    if (request_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

send_security_headers();

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

function request_wants_json(): bool
{
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

    return str_starts_with($path, '/api/')
        || str_contains($accept, 'application/json')
        || str_contains($contentType, 'application/json');
}

function verify_csrf(?string $token = null): void
{
    $token = $token ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
    $requestToken = (string) $token;
    if ($sessionToken === '' || $requestToken === '' || !hash_equals($sessionToken, $requestToken)) {
        http_response_code(419);
        if (request_wants_json()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'เซสชันหมดอายุ กรุณารีเฟรชหน้าแล้วลองใหม่',
                'data' => [],
                'errors' => ['csrf' => 'invalid'],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

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

function uploadFile(array $file, string $folder, ?array $allowedMimes = null): ?string
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
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('ไฟล์อัปโหลดไม่ถูกต้อง');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpName);
    $mimeExtensions = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/avif' => 'avif',
        'image/bmp' => 'bmp',
        'image/x-bmp' => 'bmp',
        'image/x-ms-bmp' => 'bmp',
        'image/tiff' => 'tif',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
        'image/heic-sequence' => 'heic',
        'image/heif-sequence' => 'heif',
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
        'audio/mpeg' => 'mp3',
        'audio/mp4' => 'm4a',
        'audio/ogg' => 'ogg',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/webm' => 'webm',
        'video/webm' => 'webm',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];
    $allowedMimes = $allowedMimes ?? app_config('allowed_upload_mimes', []);
    if (!in_array($mime, $allowedMimes, true) || !isset($mimeExtensions[$mime])) {
        throw new RuntimeException('ชนิดไฟล์ไม่รองรับ');
    }

    $safeExtension = $mimeExtensions[$mime];
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

function ensureUserProfileImageColumn(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        if (db_driver() === 'sqlite') {
            $columns = db()->query('PRAGMA table_info(users)')->fetchAll();
            $existing = array_column($columns, 'name');
            if (!in_array('profile_image', $existing, true)) {
                db()->exec('ALTER TABLE users ADD COLUMN profile_image TEXT NULL');
            }
            return;
        }

        $columns = db()->query('SHOW COLUMNS FROM users')->fetchAll();
        $existing = array_column($columns, 'Field');
        if (!in_array('profile_image', $existing, true)) {
            db()->exec('ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) NULL AFTER phone');
        }
    } catch (Throwable) {
        // Profile image upload is optional; pages can still render with icon avatars.
    }
}

function profileImageUrl(?string $path): ?string
{
    $path = str_replace('\\', '/', trim((string) $path));
    if ($path === '' || !str_starts_with($path, 'uploads/profile_images/')) {
        return null;
    }

    return url('/' . ltrim($path, '/'));
}

function deleteUploadedFile(?string $path): void
{
    $path = str_replace('\\', '/', trim((string) $path));
    if ($path === '' || !str_starts_with($path, 'uploads/')) {
        return;
    }

    $absolute = dirname(__DIR__) . '/' . ltrim($path, '/');
    if (is_file($absolute)) {
        @unlink($absolute);
    }
}

function uploadProfileImage(array $file): ?string
{
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
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('ไฟล์อัปโหลดไม่ถูกต้อง');
    }

    $mime = strtolower((string) (new finfo(FILEINFO_MIME_TYPE))->file($tmpName));
    $extension = profileImageExtension($mime, (string) ($file['name'] ?? ''), $tmpName);
    if ($extension === null) {
        throw new RuntimeException('กรุณาอัปโหลดไฟล์รูปภาพเท่านั้น');
    }

    if ($extension === 'svg' && !safeSvgUpload($tmpName)) {
        throw new RuntimeException('ไฟล์ SVG นี้ไม่ปลอดภัย กรุณาใช้ไฟล์รูปภาพอื่น');
    }

    $relativeDir = 'uploads/profile_images';
    $targetDir = dirname(__DIR__) . '/' . $relativeDir;
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์อัปโหลดได้');
    }

    $filename = bin2hex(random_bytes(20)) . '.' . $extension;
    $target = $targetDir . '/' . $filename;
    if (!move_uploaded_file($tmpName, $target)) {
        throw new RuntimeException('ไม่สามารถบันทึกไฟล์ได้');
    }

    return $relativeDir . '/' . $filename;
}

function profileImageExtension(string $mime, string $originalName, string $tmpName): ?string
{
    $mime = strtolower(trim(strtok($mime, ';') ?: $mime));
    $mimeMap = [
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png' => 'png',
        'image/x-png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/avif' => 'avif',
        'image/bmp' => 'bmp',
        'image/x-bmp' => 'bmp',
        'image/x-ms-bmp' => 'bmp',
        'image/tiff' => 'tif',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
        'image/heic-sequence' => 'heic',
        'image/heif-sequence' => 'heif',
        'image/svg+xml' => 'svg',
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
    ];

    if (isset($mimeMap[$mime])) {
        return $mimeMap[$mime];
    }

    $imageInfo = @getimagesize($tmpName);
    if (is_array($imageInfo) && isset($imageInfo[2])) {
        $detected = image_type_to_extension((int) $imageInfo[2], false);
        return $detected === 'jpeg' ? 'jpg' : strtolower((string) $detected);
    }

    if (str_starts_with($mime, 'image/')) {
        $subtype = preg_replace('/[^a-z0-9]+/', '', substr($mime, 6));
        return $subtype ? substr($subtype, 0, 12) : 'img';
    }

    $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
    $knownImageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'bmp', 'dib', 'tif', 'tiff', 'heic', 'heif', 'ico', 'svg'];
    if (in_array($extension, $knownImageExtensions, true) && in_array($mime, ['application/octet-stream', 'binary/octet-stream', ''], true)) {
        return $extension === 'jpeg' ? 'jpg' : ($extension === 'tiff' ? 'tif' : $extension);
    }

    return null;
}

function safeSvgUpload(string $tmpName): bool
{
    $contents = (string) file_get_contents($tmpName, false, null, 0, 1024 * 1024);
    $lower = strtolower($contents);

    return str_contains($lower, '<svg')
        && !preg_match('/<\s*script\b|on[a-z]+\s*=|javascript\s*:|<\s*foreignobject\b/i', $contents);
}

function avatarHtml(?string $path, string $icon = 'person', string $class = 'profile-avatar', string $alt = 'Profile image'): string
{
    $icon = preg_replace('/[^a-z0-9-]/i', '', $icon) ?: 'person';
    $imageUrl = profileImageUrl($path);
    $classes = trim($class . ($imageUrl ? ' has-image' : ''));

    if ($imageUrl) {
        return '<span class="' . e($classes) . '"><img src="' . e($imageUrl) . '" alt="' . e($alt) . '"></span>';
    }

    return '<span class="' . e($classes) . '"><i class="bi bi-' . e($icon) . '"></i></span>';
}

function ensureMessageMediaColumns(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        if (db_driver() === 'sqlite') {
            $columns = db()->query('PRAGMA table_info(messages)')->fetchAll();
            $existing = array_column($columns, 'name');
            $defs = [
                'message_type' => 'ADD COLUMN message_type VARCHAR(20) DEFAULT "text"',
                'call_type' => 'ADD COLUMN call_type VARCHAR(20) NULL',
                'call_url' => 'ADD COLUMN call_url VARCHAR(255) NULL',
                'call_room' => 'ADD COLUMN call_room VARCHAR(80) NULL',
            ];
            foreach ($defs as $column => $alter) {
                if (!in_array($column, $existing, true)) {
                    db()->exec('ALTER TABLE messages ' . $alter);
                }
            }
            return;
        }

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
        if (!in_array('call_room', $existing, true)) {
            $alters[] = 'ADD COLUMN call_room VARCHAR(80) NULL AFTER call_url';
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
    if ($limit <= 0 || $seconds <= 0) {
        return;
    }

    $identity = !empty($_SESSION['user_id'])
        ? 'user:' . (int) $_SESSION['user_id']
        : 'session:' . (session_id() ?: 'anonymous');
    $ipLimit = max($limit * 5, $limit + 20);
    $allowed = consumeRateLimit($key . '|ip:' . clientIp(), $ipLimit, $seconds)
        && consumeRateLimit($key . '|' . $identity, $limit, $seconds);

    if (!$allowed) {
        $message = 'ระบบได้รับคำขอถี่เกินไป กรุณารอสักครู่แล้วลองใหม่';
        if (request_wants_json()) {
            jsonResponse(false, $message, [], ['rate_limit' => 'too_many_requests'], 429);
        }

        http_response_code(429);
        flash('danger', $message);
        redirect((string) ($_SERVER['REQUEST_URI'] ?? url('/public/login.php')));
    }
}

function clientIp(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
        $value = $_SERVER[$key] ?? '';
        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return $value;
        }
    }

    $forwardedFor = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    foreach (array_map('trim', explode(',', $forwardedFor)) as $candidate) {
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }

    return 'unknown';
}

function consumeRateLimit(string $bucket, int $limit, int $seconds): bool
{
    $directory = dirname(__DIR__) . '/storage/rate_limits';
    if (!is_dir($directory)) {
        @mkdir($directory, 0755, true);
    }

    if (!is_dir($directory) || !is_writable($directory)) {
        return consumeSessionRateLimit($bucket, $limit, $seconds);
    }

    if (random_int(1, 100) === 1) {
        cleanupRateLimitFiles($directory);
    }

    $now = time();
    $file = $directory . '/' . hash('sha256', $bucket) . '.json';
    $handle = @fopen($file, 'c+');
    if (!$handle) {
        return consumeSessionRateLimit($bucket, $limit, $seconds);
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            return consumeSessionRateLimit($bucket, $limit, $seconds);
        }

        $raw = stream_get_contents($handle);
        $timestamps = json_decode((string) $raw, true);
        if (!is_array($timestamps)) {
            $timestamps = [];
        }

        $timestamps = array_values(array_filter(
            array_map('intval', $timestamps),
            fn (int $timestamp): bool => $timestamp > ($now - $seconds)
        ));

        if (count($timestamps) >= $limit) {
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($timestamps));
            fflush($handle);
            return false;
        }

        $timestamps[] = $now;
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($timestamps));
        fflush($handle);

        return true;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function consumeSessionRateLimit(string $bucket, int $limit, int $seconds): bool
{
    $now = time();
    $_SESSION['rate_limits'] ??= [];
    $_SESSION['rate_limits'][$bucket] = array_values(array_filter(
        $_SESSION['rate_limits'][$bucket] ?? [],
        fn (int $timestamp): bool => $timestamp > ($now - $seconds)
    ));

    if (count($_SESSION['rate_limits'][$bucket]) >= $limit) {
        return false;
    }

    $_SESSION['rate_limits'][$bucket][] = $now;
    return true;
}

function cleanupRateLimitFiles(string $directory): void
{
    $threshold = time() - 86400;
    foreach (glob($directory . '/*.json') ?: [] as $file) {
        if (is_file($file) && (int) @filemtime($file) < $threshold) {
            @unlink($file);
        }
    }
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

function bookingStatusLabel(?string $status): string
{
    return [
        'pending' => 'รอดำเนินการ',
        'confirmed' => 'ยืนยันนัดแล้ว',
        'completed' => 'เสร็จสิ้น',
        'cancelled' => 'ยกเลิก',
    ][$status ?? ''] ?? ($status ?: '-');
}

function lawyerResponseStatusLabel(?string $status): string
{
    return [
        'pending' => 'รอทนายตอบรับ',
        'accepted' => 'ทนายรับงานแล้ว',
        'rejected' => 'ทนายปฏิเสธ',
    ][$status ?? ''] ?? ($status ?: '-');
}

function paymentStatusLabel(?string $status, bool $hasSlip = false, ?string $lawyerResponseStatus = null, ?string $bookingStatus = null): string
{
    if ($bookingStatus === 'cancelled' || $lawyerResponseStatus === 'rejected') {
        return 'ยกเลิก';
    }

    if ($status === 'pending' && !$hasSlip && $lawyerResponseStatus !== 'accepted') {
        return 'ยังไม่เปิดชำระ';
    }

    if ($status === 'pending' && !$hasSlip) {
        return 'รอชำระ';
    }

    if ($status === 'pending' && $hasSlip) {
        return 'รอตรวจสลิป';
    }

    return [
        'approved' => 'ชำระแล้ว',
        'rejected' => 'สลิปไม่ผ่าน',
        'refunded' => 'คืนเงินแล้ว',
    ][$status ?? ''] ?? ($status ?: '-');
}

function paymentWorkflowState(array $booking): array
{
    $bookingStatus = (string) ($booking['booking_status'] ?? $booking['status'] ?? '');
    $lawyerStatus = (string) ($booking['lawyer_response_status'] ?? 'pending');
    $paymentStatus = (string) (
        $booking['payment_status']
        ?? (array_key_exists('booking_status', $booking) ? ($booking['status'] ?? 'pending') : 'pending')
    );
    $hasSlip = !empty($booking['slip_image']);

    $stage = 'waiting_lawyer';
    $title = 'รอทนายตอบรับ';
    $description = 'ระบบส่งคำขอนัดหมายให้ทนายแล้ว เมื่อทนายรับงานจึงค่อยชำระเงิน';
    $tone = 'warning';
    $icon = 'hourglass-split';

    if ($bookingStatus === 'cancelled' || $lawyerStatus === 'rejected') {
        $stage = 'cancelled';
        $title = $lawyerStatus === 'rejected' ? 'ทนายไม่สะดวกรับงาน' : 'ยกเลิกนัดหมาย';
        $description = $lawyerStatus === 'rejected'
            ? 'รายการนี้ปิดแล้ว คุณสามารถเลือกทนายคนอื่นจากผลจับคู่ได้'
            : 'รายการนี้ถูกยกเลิกแล้ว';
        $tone = 'danger';
        $icon = 'x-circle';
    } elseif ($bookingStatus === 'completed') {
        $stage = 'completed';
        $title = 'ปรึกษาเสร็จสิ้น';
        $description = 'การให้คำปรึกษารายการนี้เสร็จแล้ว สามารถให้รีวิวทนายได้';
        $tone = 'success';
        $icon = 'check2-circle';
    } elseif ($paymentStatus === 'approved' || $bookingStatus === 'confirmed') {
        $stage = 'confirmed';
        $title = 'ยืนยันนัดแล้ว';
        $description = 'แอดมินตรวจสลิปเรียบร้อย นัดหมายนี้พร้อมเข้ารับคำปรึกษา';
        $tone = 'success';
        $icon = 'calendar-check';
    } elseif ($lawyerStatus === 'accepted' && $paymentStatus === 'rejected') {
        $stage = 'slip_rejected';
        $title = 'สลิปไม่ผ่าน';
        $description = 'กรุณาตรวจยอด วันเวลา หรือรูปสลิป แล้วอัปโหลดใหม่';
        $tone = 'danger';
        $icon = 'exclamation-triangle';
    } elseif ($lawyerStatus === 'accepted' && $hasSlip && $paymentStatus === 'pending') {
        $stage = 'waiting_admin';
        $title = 'รอแอดมินตรวจสลิป';
        $description = 'ได้รับสลิปแล้ว แอดมินกำลังตรวจสอบเพื่อยืนยันนัดหมาย';
        $tone = 'info';
        $icon = 'receipt-cutoff';
    } elseif ($lawyerStatus === 'accepted') {
        $stage = 'ready_to_pay';
        $title = 'พร้อมชำระเงิน';
        $description = 'ทนายรับงานแล้ว กรุณาโอนเงินและอัปโหลดสลิปเพื่อยืนยันนัดหมาย';
        $tone = 'primary';
        $icon = 'credit-card';
    }

    $stepStates = [
        'lawyer' => 'pending',
        'payment' => 'pending',
        'admin' => 'pending',
        'confirm' => 'pending',
    ];

    if ($stage === 'cancelled') {
        $stepStates['lawyer'] = $lawyerStatus === 'rejected' ? 'danger' : 'pending';
    } elseif (in_array($stage, ['confirmed', 'completed'], true)) {
        $stepStates = [
            'lawyer' => 'done',
            'payment' => 'done',
            'admin' => 'done',
            'confirm' => 'done',
        ];
    } elseif ($stage === 'waiting_lawyer') {
        $stepStates['lawyer'] = 'active';
    } elseif ($stage === 'ready_to_pay') {
        $stepStates['lawyer'] = 'done';
        $stepStates['payment'] = 'active';
    } elseif ($stage === 'waiting_admin') {
        $stepStates['lawyer'] = 'done';
        $stepStates['payment'] = 'done';
        $stepStates['admin'] = 'active';
    } elseif ($stage === 'slip_rejected') {
        $stepStates['lawyer'] = 'done';
        $stepStates['payment'] = 'danger';
        $stepStates['admin'] = 'danger';
    }

    $steps = [
        ['key' => 'lawyer', 'label' => 'ทนายรับงาน', 'hint' => 'รอทนายยืนยันเวลานัด', 'icon' => 'person-check'],
        ['key' => 'payment', 'label' => 'ชำระเงิน', 'hint' => 'โอนเงินและส่งสลิป', 'icon' => 'wallet2'],
        ['key' => 'admin', 'label' => 'ตรวจสลิป', 'hint' => 'แอดมินตรวจยอดชำระ', 'icon' => 'shield-check'],
        ['key' => 'confirm', 'label' => 'ยืนยันนัด', 'hint' => 'พร้อมคุยกับทนาย', 'icon' => 'calendar2-check'],
    ];

    foreach ($steps as &$step) {
        $step['state'] = $stepStates[$step['key']] ?? 'pending';
    }
    unset($step);

    $canUpload = $bookingStatus === 'pending'
        && $lawyerStatus === 'accepted'
        && (
            $paymentStatus === 'rejected'
            || ($paymentStatus === 'pending' && !$hasSlip)
        );

    $canCancel = $bookingStatus === 'pending'
        && $paymentStatus !== 'approved'
        && (!$hasSlip || $paymentStatus === 'rejected');

    return [
        'stage' => $stage,
        'title' => $title,
        'description' => $description,
        'tone' => $tone,
        'icon' => $icon,
        'can_upload' => $canUpload,
        'can_cancel' => $canCancel,
        'has_slip' => $hasSlip,
        'steps' => $steps,
    ];
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
    if (db_driver() === 'sqlite') {
        $stmt = db()->prepare(
            'INSERT INTO app_settings (setting_key, setting_value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)
             ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([$key, $value]);
        return;
    }

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

function caseStatusLabel(?string $status): string
{
    return [
        'ai_consulting' => 'กำลังปรึกษา AI',
        'waiting_match' => 'รอจับคู่ทนาย',
        'matched' => 'พบทนายที่เหมาะสม',
        'booked' => 'จองทนายแล้ว',
        'in_progress' => 'อยู่ระหว่างให้คำปรึกษา',
        'closed' => 'ปิดเคสแล้ว',
        'pending' => 'รอดำเนินการ',
        'confirmed' => 'ยืนยันแล้ว',
        'cancelled' => 'ยกเลิก',
    ][$status ?? ''] ?? ($status ?: '-');
}

function matchStatusLabel(?string $status): string
{
    return [
        'not_asked' => 'ยังไม่ได้ขอจับคู่',
        'asked' => 'รอผู้ใช้ยืนยัน',
        'requested_by_user' => 'ขอให้ระบบช่วยจับคู่',
        'declined_by_user' => 'ยังไม่ต้องการทนาย',
        'waiting_match' => 'กำลังค้นหาทนาย',
        'matched' => 'มีทนายแนะนำแล้ว',
    ][$status ?? ''] ?? ($status ?: '-');
}

function consultationTypeLabel(?string $type): string
{
    return [
        'chat' => 'แชตออนไลน์',
        'phone' => 'โทรศัพท์',
        'video' => 'วิดีโอคอล',
        'onsite' => 'พบที่สำนักงาน',
    ][$type ?? ''] ?? ($type ?: '-');
}

function lawyerStatusLabel(?string $status): string
{
    return [
        'pending' => 'รอตรวจสอบ',
        'approved' => 'อนุมัติแล้ว',
        'rejected' => 'ไม่ผ่านการตรวจ',
        'suspended' => 'ระงับชั่วคราว',
    ][$status ?? ''] ?? ($status ?: '-');
}

function accountRoleLabel(?string $role): string
{
    return [
        'user' => 'ผู้ใช้',
        'lawyer' => 'ทนาย',
        'admin' => 'แอดมิน',
    ][$role ?? ''] ?? ($role ?: '-');
}

function accountStatusLabel(?string $status): string
{
    return [
        'active' => 'ใช้งานอยู่',
        'inactive' => 'ปิดใช้งาน',
        'banned' => 'ถูกระงับ',
    ][$status ?? ''] ?? ($status ?: '-');
}

function commissionStatusLabel(?string $status): string
{
    return [
        'pending' => 'รอปิดรอบ',
        'available' => 'พร้อมเบิก',
        'paid' => 'จ่ายแล้ว',
        'cancelled' => 'ยกเลิก',
    ][$status ?? ''] ?? ($status ?: '-');
}

function emailStatusLabel(?string $status): string
{
    return [
        'queued' => 'รอส่ง',
        'sent' => 'ส่งแล้ว',
        'failed' => 'ส่งไม่สำเร็จ',
    ][$status ?? ''] ?? ($status ?: '-');
}

function emailNotificationTypeLabel(?string $type): string
{
    return [
        'account' => 'บัญชีสมาชิก',
        'booking' => 'การจอง',
        'payment' => 'การชำระเงิน',
        'match' => 'การจับคู่ทนาย',
        'lawyer_status' => 'สถานะทนาย',
        'contact' => 'ข้อความติดต่อ',
        'message' => 'ข้อความแชต',
        'member_registered' => 'สมาชิกใหม่',
        'lawyer_registered' => 'ทนายสมัครใหม่',
        'booking_created' => 'มีการจองใหม่',
        'booking_accepted' => 'ทนายรับงาน',
        'booking_rejected' => 'ทนายปฏิเสธงาน',
        'payment_uploaded' => 'มีสลิปใหม่',
        'payment_approved' => 'อนุมัติชำระเงิน',
        'payment_rejected' => 'สลิปไม่ผ่าน',
        'contact_message' => 'ข้อความติดต่อ',
        'test' => 'ทดสอบระบบ',
    ][$type ?? ''] ?? ($type ?: '-');
}

function appEnvironmentLabel(?string $environment): string
{
    return [
        'production' => 'ระบบจริง',
        'staging' => 'ระบบทดสอบก่อนใช้งานจริง',
        'local' => 'เครื่องพัฒนา',
        'development' => 'เครื่องพัฒนา',
    ][$environment ?? ''] ?? ($environment ?: '-');
}

function systemHealthStatusLabel(?string $status): string
{
    return [
        'ok' => 'พร้อม',
        'warn' => 'ควรตรวจสอบ',
        'error' => 'ต้องแก้ไข',
    ][$status ?? ''] ?? ($status ?: '-');
}

function systemMetricLabel(?string $metric): string
{
    return [
        'disk_free' => 'พื้นที่ว่างบนเซิร์ฟเวอร์',
        'disk_total' => 'พื้นที่ทั้งหมดบนเซิร์ฟเวอร์',
        'uploads_size' => 'ขนาดไฟล์อัปโหลด',
        'audit_events_60m' => 'เหตุการณ์ความปลอดภัย 60 นาที',
        'failed_logins_60m' => 'เข้าสู่ระบบไม่สำเร็จ 60 นาที',
        'failed_email_notifications' => 'อีเมลส่งไม่สำเร็จ',
        'queued_email_notifications' => 'อีเมลรอส่ง',
        'pending_lawyer_reviews' => 'ทนายรอตรวจสอบ',
        'pending_payments' => 'สลิปรอตรวจ',
        'requested_matches' => 'เคสขอจับคู่ทนาย',
    ][$metric ?? ''] ?? str_replace('_', ' ', (string) $metric);
}

function auditActionLabel(?string $action): string
{
    return [
        'auth.login_success' => 'เข้าสู่ระบบสำเร็จ',
        'auth.login_failed' => 'เข้าสู่ระบบไม่สำเร็จ',
        'auth.logout' => 'ออกจากระบบ',
        'case.create' => 'สร้างเคส',
        'case.match' => 'ระบบจับคู่ทนาย',
        'case.request_lawyer' => 'ขอให้หาทนาย',
        'case.decline_lawyer' => 'ยังไม่ต้องการทนาย',
        'booking.create' => 'จองปรึกษา',
        'booking.accept' => 'ทนายรับงาน',
        'booking.reject' => 'ทนายปฏิเสธงาน',
        'booking.complete' => 'ปิดงานปรึกษา',
        'booking.cancel' => 'ยกเลิกการจอง',
        'payment.upload_slip' => 'อัปโหลดสลิป',
        'payment.n8n_dispatched' => 'ส่งสลิปให้ n8n',
        'payment.n8n_manual_review' => 'n8n ขอให้ตรวจเพิ่ม',
        'payment.approve' => 'อนุมัติสลิป',
        'payment.reject' => 'ปฏิเสธสลิป',
        'lawyer.approved' => 'อนุมัติทนาย',
        'lawyer.rejected' => 'ปฏิเสธทนาย',
        'lawyer.suspended' => 'ระงับทนาย',
    ][$action ?? ''] ?? ($action ?: '-');
}

function auditEntityLabel(?string $entity): string
{
    return [
        'auth' => 'บัญชี',
        'case' => 'เคส',
        'booking' => 'การจอง',
        'payment' => 'การชำระเงิน',
        'lawyer' => 'ทนาย',
        'user' => 'ผู้ใช้',
    ][$entity ?? ''] ?? ($entity ?: '-');
}
