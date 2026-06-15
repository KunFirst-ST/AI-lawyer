<?php

require_once __DIR__ . '/env.php';

return [
    'payment_verification_enabled' => envBool('N8N_PAYMENT_VERIFICATION_ENABLED', false),
    'payment_webhook_url' => trim((string) envValue('N8N_PAYMENT_WEBHOOK_URL', '')),
    'payment_webhook_secret' => (string) envValue('N8N_PAYMENT_WEBHOOK_SECRET', ''),
    'payment_callback_secret' => (string) envValue('N8N_PAYMENT_CALLBACK_SECRET', envValue('N8N_PAYMENT_WEBHOOK_SECRET', '')),
    'timeout' => max(3, (int) envValue('N8N_TIMEOUT', 15)),
];
