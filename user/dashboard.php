<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('user');

redirect(url('/user/ai-chat.php'));
