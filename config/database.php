<?php

require_once __DIR__ . '/env.php';

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = envValue('DB_HOST', '127.0.0.1');
        $port = envValue('DB_PORT', '3306');
        $database = envValue('DB_DATABASE', 'ai_lawyer_platform');
        $username = envValue('DB_USERNAME', 'root');
        $password = envValue('DB_PASSWORD', '');

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        self::$pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$pdo;
    }
}

function db(): PDO
{
    return Database::connection();
}
