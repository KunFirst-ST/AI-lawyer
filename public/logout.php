<?php
require_once __DIR__ . '/../includes/auth.php';
logoutUser();
redirect(url('/public/index.php'));
