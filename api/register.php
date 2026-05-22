<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/MemberRegistrationService.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Method not allowed', [], [], 405);
    }

    if (currentUser()) {
        jsonResponse(false, 'คุณเข้าสู่ระบบอยู่แล้ว', [
            'redirect' => dashboardPathForRole(currentUser()['role']),
        ], [], 409);
    }

    verify_csrf();
    rateLimit('member_register', (int) app_config('registration_rate_limit', 10), 300);

    $user = (new MemberRegistrationService())->register($_POST);
    $redirect = url('/user/login.php?registered=1');
    if (app_config('auto_login_after_register', false)) {
        loginUser($user);
        $redirect = dashboardPathForRole($user['role']);
    }

    jsonResponse(true, 'สมัครสมาชิกสำเร็จ', [
        'user' => [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ],
        'redirect' => $redirect,
    ], [], 201);
} catch (InvalidArgumentException $exception) {
    jsonResponse(false, $exception->getMessage(), [], ['validation' => true], 422);
} catch (Throwable $exception) {
    jsonResponse(false, 'เกิดข้อผิดพลาดในการสมัครสมาชิก', [], ['detail' => $exception->getMessage()], 500);
}
