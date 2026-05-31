<?php

require_once __DIR__ . '/../services/EmailNotificationService.php';

$limit = isset($argv[1]) ? max(1, min((int) $argv[1], 100)) : 25;
$result = (new EmailNotificationService())->retryPending($limit);
echo 'Email outbox processed: sent=' . $result['sent'] . ', failed=' . $result['failed'] . "\n";
