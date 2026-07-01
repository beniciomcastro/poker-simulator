<?php

require_once __DIR__ . '/helpers.php';
secure_session_start();
api_boot();
api_require_csrf();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/PokerGame.php';

api_require_game();

$action = $_POST['action'] ?? 'check';
$allowed = ['check','call','fold','raise'];

if (!in_array($action, $allowed, true)) {
    $action = 'check';
}

$raise = max(20, (int)($_POST['raise'] ?? 20));
$raise = min($raise, 1000000);

$game = $_SESSION['game'];
$human = $game['players'][0] ?? null;

if ($human && $action === 'raise') {
    $toCall = max(0, (int)($game['currentBet'] ?? 0) - (int)($human['bet'] ?? 0));
    $availableRaise = max(0, (int)($human['chips'] ?? 0) - $toCall);

    if ($raise > $availableRaise) {
        api_out(['ok'=>false, 'error'=>'Você não tem fichas suficientes para essa aposta.']);
    }
}

$_SESSION['game'] = PokerGame::humanAction($game, $action, $raise);

save_finished_hand_once($pdo);

api_out(['ok'=>true,'game'=>$_SESSION['game']]);
