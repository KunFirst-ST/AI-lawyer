<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/SocialAuthService.php';

if (currentUser()) {
    redirect(dashboardPathForRole(currentUser()['role']));
}

try {
    $service = new SocialAuthService();
    $provider = (string) ($_GET['provider'] ?? '');
    if ($provider === '') {
        $provider = (string) $service->providerFromState((string) ($_GET['state'] ?? ''));
    }

    $user = $service->handleCallback($provider, $_GET);
    loginUser($user);
    flash('success', 'เข้าสู่ระบบสำเร็จ');
    redirect(dashboardPathForRole((string) $user['role']));
} catch (Throwable $exception) {
    flash('danger', $exception->getMessage());
    redirect(url('/user/login.php'));
}
