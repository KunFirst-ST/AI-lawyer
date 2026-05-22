<?php
require_once __DIR__ . '/../includes/auth.php';

if (currentUser()) {
    redirect(dashboardPathForRole(currentUser()['role']));
}

redirect(url('/public/portals.php'));
