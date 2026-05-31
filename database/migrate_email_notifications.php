<?php

require_once __DIR__ . '/../services/EmailNotificationService.php';

(new EmailNotificationService())->ensureSchema();
echo "Email notification outbox schema is ready.\n";
