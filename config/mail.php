<?php

require_once __DIR__ . '/env.php';

$username = trim((string) envValue('MAIL_USERNAME', ''));
$fromAddress = trim((string) envValue('MAIL_FROM_ADDRESS', $username));
$notifyTypes = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) envValue('MAIL_NOTIFY_TYPES', 'account,booking,payment,match,lawyer_status,contact,message'))
)));

return [
    'enabled' => envBool('MAIL_ENABLED', false),
    'host' => trim((string) envValue('MAIL_HOST', 'smtp.gmail.com')),
    'port' => (int) envValue('MAIL_PORT', 587),
    'encryption' => strtolower(trim((string) envValue('MAIL_ENCRYPTION', 'tls'))),
    'username' => $username,
    'password' => (string) envValue('MAIL_PASSWORD', ''),
    'from_address' => $fromAddress,
    'from_name' => trim((string) envValue('MAIL_FROM_NAME', 'ทนายคู่ดี')),
    'timeout' => max(3, (int) envValue('MAIL_TIMEOUT', 10)),
    'send_immediately' => envBool('MAIL_SEND_IMMEDIATELY', true),
    'notify_types' => $notifyTypes,
    'message_cooldown_seconds' => max(0, (int) envValue('MAIL_MESSAGE_COOLDOWN_SECONDS', 300)),
];
