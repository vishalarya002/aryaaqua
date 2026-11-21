<?php
declare(strict_types=1);
require_once __DIR__ . '/constants.php';

function db(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    // These defaults match a fresh local XAMPP installation.
    $host = getenv('ARYAAQUA_DB_HOST') ?: '127.0.0.1';
    $port = getenv('ARYAAQUA_DB_PORT') ?: '3306';
    $name = getenv('ARYAAQUA_DB_NAME') ?: 'aryaaqua_db';
    $user = getenv('ARYAAQUA_DB_USER') ?: 'root';
    $password = getenv('ARYAAQUA_DB_PASSWORD') ?: '';
    $connection = new PDO(
        "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $connection->exec("SET time_zone = '+05:30'");
    return $connection;
}
