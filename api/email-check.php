<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/MemberRegistrationService.php';

try {
    $email = strtolower(trim($_GET['email'] ?? ''));
    $valid = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    $available = $valid ? (new MemberRegistrationService())->emailAvailable($email) : false;

    jsonResponse(true, $available ? 'ใช้อีเมลนี้ได้' : 'อีเมลนี้ไม่พร้อมใช้งาน', [
        'valid' => $valid,
        'available' => $available,
    ]);
} catch (Throwable $exception) {
    jsonResponse(false, 'ไม่สามารถตรวจสอบอีเมลได้', [], ['detail' => $exception->getMessage()], 500);
}
