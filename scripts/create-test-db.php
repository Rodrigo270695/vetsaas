<?php

declare(strict_types=1);

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '5432';
$user = getenv('DB_USERNAME') ?: 'postgres';
$pass = getenv('DB_PASSWORD') ?: 'admin';
$db = getenv('DB_DATABASE') ?: 'vetsaas_test';

try {
    $pdo = new PDO("pgsql:host={$host};port={$port}", $user, $pass);
    $pdo->exec('CREATE DATABASE "'.$db.'"');
    echo "Database {$db} created.\n";
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'already exists') || str_contains($e->getMessage(), 'ya existe')) {
        echo "Database {$db} already exists.\n";
        exit(0);
    }

    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}
