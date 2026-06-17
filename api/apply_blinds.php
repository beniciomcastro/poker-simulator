<?php
session_start();
require_once 'helpers.php';
require_once '../config/database.php';
require_once '../classes/PokerGame.php';
api_boot();
if (!isset($_SESSION['user_id'], $_SESSION['game'])) api_out(['ok'=>false, 'error'=>'Sessão expirada. Faça login novamente.']);
$_SESSION['game'] = PokerGame::applyPendingBlinds($_SESSION['game']);
api_out(['ok'=>true,'game'=>$_SESSION['game']]);
