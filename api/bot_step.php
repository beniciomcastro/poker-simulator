<?php

session_start();

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/PokerGame.php';

api_boot();

if (!isset($_SESSION['user_id'], $_SESSION['game'])) {
    api_out([
        'ok' => false,
        'error' => 'Sessão expirada. Faça login novamente.'
    ]);
}

$_SESSION['game'] = PokerGame::botStep($_SESSION['game']);

save_finished_hand_once($pdo);

api_out([
    'ok' => true,
    'game' => $_SESSION['game']
]);