<?php

require_once __DIR__ . '/config/security.php';
secure_session_start();
security_headers();

require_once __DIR__ . '/config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_require_page()) {
        $error = 'Sessão inválida. Atualize a página e tente novamente.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $error = 'Informe um nome válido.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            $error = 'Informe um e-mail válido.';
        } elseif (strlen($password) < 6 || strlen($password) > 255) {
            $error = 'A senha deve ter pelo menos 6 caracteres.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            try {
                $stmt = $pdo->prepare('INSERT INTO users (name,email,password) VALUES (?,?,?)');
                $stmt->execute([$name, $email, $hash]);

                header('Location: login.php');
                exit;
            } catch (PDOException $e) {
                $error = 'E-mail já cadastrado.';
            }
        }
    }
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Criar conta</title><link rel="stylesheet" href="css/style.css"><link rel="icon" type="image/png" href="icon.png"> </head><body class="auth-page"><div class="auth-card"><div class="brand-mark">♣</div><h1>Criar conta</h1><?php if($error):?><p class="error"><?=e($error)?></p><?php endif;?><form method="POST"><?=csrf_field()?><input name="name" placeholder="Nome" required maxlength="100"><input type="email" name="email" placeholder="E-mail" required maxlength="150"><input type="password" name="password" placeholder="Senha" required minlength="6" maxlength="255"><button>Cadastrar</button></form><a href="login.php">Já tenho conta</a></div></body></html>
