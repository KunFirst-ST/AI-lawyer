<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../services/SocialAuthService.php';

SocialAuthService::connectedProvidersForUser(0);
echo "social_accounts-ready\n";
