<?php

require_once __DIR__ . '/helpers.php';
secure_session_start();
api_boot();
api_require_csrf();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/PokerGame.php';

api_require_login();

$mode = $_POST['mode'] ?? 'casual';

if (!in_array($mode, ['casual', 'legendary'], true)) {
    $mode = 'casual';
}

$stmt = $pdo->prepare('SELECT name, chips FROM users WHERE id = ? LIMIT 1');
$stmt->execute([(int)$_SESSION['user_id']]);
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
