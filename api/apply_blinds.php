<?php

require_once __DIR__ . '/helpers.php';
secure_session_start();
api_boot();
api_require_csrf();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/PokerGame.php';

api_require_game();

$_SESSION['game'] = PokerGame::applyPendingBlinds($_SESSION['game']);

api_out(['ok'=>true,'game'=>$_SESSION['game']]);
