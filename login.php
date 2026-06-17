<?php
session_start();
require_once 'config/database.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        unset($_SESSION['game']);
        header('Location: game.php'); exit;
    }
    $error = 'Dados inválidos.';
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Poker Simulator</title><link rel="stylesheet" href="css/style.css"><link rel="icon" type="image/png" href="icon.png"> </head><body class="auth-page"><div class="auth-card"><div class="brand-mark">♠</div><h1>Poker Simulator</h1><?php if($error):?><p class="error"><?=htmlspecialchars($error)?></p><?php endif;?><form method="POST" onsubmit="this.classList.add('loading');this.querySelector('button').textContent='Entrando...';this.querySelector('button').disabled=true;"><input type="email" name="email" placeholder="E-mail" required><input type="password" name="password" placeholder="Senha" required><button>Entrar</button></form><a href="register.php">Criar conta</a></div></body></html>
