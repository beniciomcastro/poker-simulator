<?php

session_start();

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/PokerGame.php';

api_boot();

if (!isset($_SESSION['user_id'])) {
    api_out(['ok' => false, 'error' => 'Faça login para jogar.']);
}

$mode = $_POST['mode'] ?? 'casual';

if (!in_array($mode, ['casual', 'legendary'], true)) {
    $mode = 'casual';
}

$stmt = $pdo->prepare('SELECT name, chips FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    api_out(['ok' => false, 'error' => 'Usuário não encontrado.']);
}

$_SESSION['table_mode_chosen'] = true;
$_SESSION['table_mode'] = $mode;
$_SESSION['user_name'] = $user['name'];
$_SESSION['game'] = PokerGame::start($user['name'], (int)$user['chips'], $mode);

api_out([
    'ok' => true,
    'game' => $_SESSION['game']
]);