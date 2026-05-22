<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$checks = [
    'app' => true,
    'database' => false,
    'uploads_writable' => is_writable(dirname(__DIR__) . '/uploads'),
    'sessions_writable' => is_writable(dirname(__DIR__) . '/storage/sessions'),
    'ai_configured' => false,
];

try {
    db()->query('SELECT 1');
    $checks['database'] = true;
} catch (Throwable) {
    $checks['database'] = false;
}

$aiConfig = require __DIR__ . '/../config/ai.php';
$checks['ai_configured'] = !empty($aiConfig['api_key']);

$ok = $checks['app'] && $checks['database'] && $checks['uploads_writable'] && $checks['sessions_writable'];
jsonResponse($ok, $ok ? 'ระบบพร้อมใช้งาน' : 'ระบบยังมีบางส่วนที่ต้องตั้งค่า', [
    'checks' => $checks,
    'timestamp' => date(DATE_ATOM),
], [], $ok ? 200 : 503);
