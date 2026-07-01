<?php

require_once __DIR__ . '/security.php';

security_headers(str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/'));

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3307';
$dbname = getenv('DB_NAME') ?: 'poker_game';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';

if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbname)) {
    http_response_code(500);
    die('Configuração de banco inválida.');
}

try {
    $pdoServer = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $pdoServer->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        chips INT NOT NULL DEFAULT 1000,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS hands (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        winner_name VARCHAR(100),
        pot INT NOT NULL DEFAULT 0,
        result_text VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id),
        CONSTRAINT fk_hands_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    http_response_code(500);

    $isApi = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/');

    if ($isApi) {
        echo json_encode([
            'ok' => false,
            'error' => 'Erro interno ao conectar com o banco.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    die('Erro interno ao conectar com o banco.');
}
