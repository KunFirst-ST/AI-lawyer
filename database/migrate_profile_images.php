<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

ensureUserProfileImageColumn();

$columns = db_driver() === 'sqlite'
    ? array_column(db()->query('PRAGMA table_info(users)')->fetchAll(), 'name')
    : array_column(db()->query('SHOW COLUMNS FROM users')->fetchAll(), 'Field');

if (!in_array('profile_image', $columns, true)) {
    throw new RuntimeException('Missing users column: profile_image');
}

echo "profile-images-ready\n";
