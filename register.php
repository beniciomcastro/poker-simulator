<?php
session_start();
require_once 'config/database.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $hash = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
    try {
        $stmt = $pdo->prepare('INSERT INTO users (name,email,password) VALUES (?,?,?)');
        $stmt->execute([$name,$email,$hash]);
        header('Location: login.php'); exit;
    } catch (PDOException $e) { $error = 'E-mail já cadastrado.'; }
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Criar conta</title><link rel="stylesheet" href="css/style.css"><link rel="icon" type="image/png" href="icon.png"> </head><body class="auth-page"><div class="auth-card"><div class="brand-mark">♣</div><h1>Criar conta</h1><?php if($error):?><p class="error"><?=htmlspecialchars($error)?></p><?php endif;?><form method="POST"><input name="name" placeholder="Nome" required><input type="email" name="email" placeholder="E-mail" required><input type="password" name="password" placeholder="Senha" required><button>Cadastrar</button></form><a href="login.php">Já tenho conta</a></div></body></html>
