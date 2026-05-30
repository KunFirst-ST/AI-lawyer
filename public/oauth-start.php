<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/SocialAuthService.php';

if (currentUser()) {
    redirect(dashboardPathForRole(currentUser()['role']));
}

try {
    $provider = (string) ($_GET['provider'] ?? '');
    redirect((new SocialAuthService())->authorizationUrl($provider));
} catch (Throwable $exception) {
    flash('warning', $exception->getMessage());
    redirect(url('/user/login.php'));
}
