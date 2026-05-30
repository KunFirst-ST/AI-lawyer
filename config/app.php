<?php

require_once __DIR__ . '/env.php';

return [
    'app_name' => 'AI Lawyer Matching Platform',
    'base_url' => envValue('APP_URL', ''),
    'env' => envValue('APP_ENV', 'local'),
    'debug' => envBool('APP_DEBUG', false),
    'timezone' => 'Asia/Bangkok',
    'commission_percent' => 20,
    'registration_enabled' => envBool('REGISTRATION_ENABLED', true),
    'registration_rate_limit' => (int) envValue('REGISTRATION_RATE_LIMIT', 10),
    'password_min_length' => max(8, (int) envValue('PASSWORD_MIN_LENGTH', 8)),
    'auto_login_after_register' => envBool('AUTO_LOGIN_AFTER_REGISTER', false),
    'require_terms_acceptance' => envBool('REQUIRE_TERMS_ACCEPTANCE', true),
    'lawyer_registration_enabled' => envBool('LAWYER_REGISTRATION_ENABLED', true),
    'social_login' => [
        'google' => [
            'enabled' => envBool('GOOGLE_LOGIN_ENABLED', false),
            'client_id' => envValue('GOOGLE_CLIENT_ID', ''),
            'client_secret' => envValue('GOOGLE_CLIENT_SECRET', ''),
        ],
        'facebook' => [
            'enabled' => envBool('FACEBOOK_LOGIN_ENABLED', false),
            'client_id' => envValue('FACEBOOK_CLIENT_ID', ''),
            'client_secret' => envValue('FACEBOOK_CLIENT_SECRET', ''),
            'graph_version' => envValue('FACEBOOK_GRAPH_VERSION', 'v24.0'),
        ],
    ],
    'upload_max_bytes' => 15 * 1024 * 1024,
    'allowed_upload_mimes' => [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'audio/mpeg',
        'audio/mp4',
        'audio/ogg',
        'audio/wav',
        'audio/x-wav',
        'audio/webm',
        'video/webm',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ],
    'bank' => [
        'account_name' => 'AI Lawyer Matching Platform Co., Ltd.',
        'account_number' => '000-0-00000-0',
        'bank_name' => 'ธนาคารตัวอย่าง',
        'promptpay_id' => '0999999999',
    ],
];
