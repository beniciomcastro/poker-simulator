<?php
session_start();
require_once 'helpers.php';
require_once '../config/database.php';
require_once '../classes/PokerGame.php';
api_boot();
if (!isset($_SESSION['user_id'])) api_out(['ok'=>false, 'error'=>'Faça login para jogar.']);
$stmt = $pdo->prepare('SELECT name, chips FROM users WHERE id=?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
if (!$user) api_out(['ok'=>false, 'error'=>'Usuário não encontrado.']);
$_SESSION['user_name'] = $user['name'];
$_SESSION['game'] = PokerGame::start($user['name'], (int)$user['chips']);
api_out(['ok'=>true,'game'=>$_SESSION['game']]);
