<?php

require_once __DIR__ . '/env.php';

final class Database
{
    private static ?PDO $pdo = null;
    private static string $driver = 'mysql';

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $connection = strtolower((string) envValue('DB_CONNECTION', 'mysql'));
        if ($connection === 'sqlite') {
            self::$driver = 'sqlite';
            $database = (string) envValue('DB_DATABASE', 'storage/database.sqlite');
            if ($database !== ':memory:' && !preg_match('/^(?:[A-Za-z]:)?[\/\\\\]/', $database)) {
                $database = dirname(__DIR__) . '/' . ltrim($database, '/\\');
            }

            if ($database !== ':memory:') {
                $directory = dirname($database);
                if (!is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }
            }

            self::$pdo = new PDO('sqlite:' . $database, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            self::$pdo->exec('PRAGMA foreign_keys = ON');
            self::initializeSqlite(self::$pdo);

            return self::$pdo;
        }

        self::$driver = 'mysql';
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

    public static function driver(): string
    {
        if (!self::$pdo instanceof PDO) {
            self::connection();
        }

        return self::$driver;
    }

    private static function initializeSqlite(PDO $pdo): void
    {
        static $initialized = false;
        if ($initialized) {
            return;
        }
        $initialized = true;

        $schema = dirname(__DIR__) . '/database/sqlite_schema.sql';
        if (!is_file($schema)) {
            throw new RuntimeException('SQLite schema file is missing');
        }

        $pdo->exec((string) file_get_contents($schema));
    }
}

function db(): PDO
{
    return Database::connection();
}

function db_driver(): string
{
    return Database::driver();
}
