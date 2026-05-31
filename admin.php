<?php

require_once __DIR__ . '/includes/auth.php';

$user = currentUser();
if ($user && $user['role'] === 'admin') {
    redirect(url('/admin/dashboard.php'));
}

redirect(url('/admin/login.php'));
