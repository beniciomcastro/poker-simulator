<?php
session_start();
require_once 'helpers.php';
require_once '../config/database.php';
require_once '../classes/PokerGame.php';
api_boot();
if (!isset($_SESSION['user_id'], $_SESSION['game'])) api_out(['ok'=>false, 'error'=>'Sessão expirada. Faça login novamente.']);
if (!empty($_SESSION['game']['gameOver'])) api_out(['ok'=>true,'game'=>$_SESSION['game']]);
$_SESSION['game'] = PokerGame::nextHand($_SESSION['game']);
api_out(['ok'=>true,'game'=>$_SESSION['game']]);
