<?php

require_once __DIR__ . '/helpers.php';
secure_session_start();
api_boot();
api_require_csrf();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/PokerGame.php';

api_require_game();

if (!empty($_SESSION['game']['gameOver'])) {
    api_out(['ok'=>true,'game'=>$_SESSION['game']]);
}

$_SESSION['game'] = PokerGame::nextHand($_SESSION['game']);

api_out(['ok'=>true,'game'=>$_SESSION['game']]);
