<?php
session_start();
require_once 'helpers.php';
require_once '../config/database.php';
require_once '../classes/PokerGame.php';
api_boot();
if (!isset($_SESSION['user_id'], $_SESSION['game'])) api_out(['ok'=>false, 'error'=>'Sessão expirada. Faça login novamente.']);
$action = $_POST['action'] ?? 'check';
$allowed = ['check','call','fold','raise'];
if (!in_array($action, $allowed, true)) $action = 'check';
$raise = max(20, (int)($_POST['raise'] ?? 20));
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
