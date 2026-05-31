<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

ensureMessageMediaColumns();

$columns = db_driver() === 'sqlite'
    ? array_column(db()->query('PRAGMA table_info(messages)')->fetchAll(), 'name')
    : array_column(db()->query('SHOW COLUMNS FROM messages')->fetchAll(), 'Field');

foreach (['message_type', 'call_type', 'call_url', 'call_room'] as $column) {
    if (!in_array($column, $columns, true)) {
        throw new RuntimeException('Missing messages column: ' . $column);
    }
}

echo "message-media-ready\n";
