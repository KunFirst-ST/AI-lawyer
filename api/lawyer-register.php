<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/LawyerRegistrationService.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Method not allowed', [], [], 405);
    }
    if (currentUser()) {
        jsonResponse(false, 'กรุณาออกจากระบบก่อนสมัครบัญชีทนายใหม่', [
            'redirect' => dashboardPathForRole(currentUser()['role']),
        ], [], 409);
    }

    verify_csrf();
    rateLimit('lawyer_register', (int) app_config('registration_rate_limit', 10), 300);

    $result = (new LawyerRegistrationService())->register($_POST, $_FILES);
    loginUser($result['user']);

    jsonResponse(true, 'ส่งใบสมัครทนายสำเร็จ กรุณารอแอดมินตรวจสอบ', [
        'lawyer_id' => $result['lawyer_id'],
        'redirect' => url('/lawyer/dashboard.php'),
    ], [], 201);
} catch (InvalidArgumentException $exception) {
    jsonResponse(false, $exception->getMessage(), [], ['validation' => true], 422);
} catch (Throwable $exception) {
    jsonResponse(false, 'สมัครทนายไม่สำเร็จ', [], ['detail' => $exception->getMessage()], 500);
}
