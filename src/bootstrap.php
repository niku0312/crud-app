<?php

declare(strict_types=1);

require_once __DIR__ . '/notes_repository.php';

/**
 * Loads key=value pairs from a .env file into the current process.
 */
function load_env(string $envPath): void
{
    if (!is_readable($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key !== '') {
            putenv(sprintf('%s=%s', $key, $value));
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

$envFile = dirname(__DIR__) . '/.env';
load_env($envFile);

/**
 * Returns a shared PDO connection using environment credentials.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $dbName = getenv('DB_NAME') ?: 'notes_app';
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbName);

    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}
