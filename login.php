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
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $_SESSION['login_attempts'] ??= [];
        $_SESSION['login_attempts'] = array_values(array_filter(
            $_SESSION['login_attempts'],
            fn ($time) => $time > time() - 900
        ));

        if (count($_SESSION['login_attempts']) >= 8) {
            $error = 'Muitas tentativas. Aguarde alguns minutos e tente novamente.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $_SESSION['login_attempts'][] = time();
            $error = 'Dados inválidos.';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);

                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['user_name'] = (string)$user['name'];

                unset($_SESSION['login_attempts']);
                unset($_SESSION['game']);
                unset($_SESSION['table_mode_chosen']);
                unset($_SESSION['table_mode']);

                header('Location: game.php');
                exit;
            }

            $_SESSION['login_attempts'][] = time();
            $error = 'Dados inválidos.';
        }
    }
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Poker Simulator</title><link rel="stylesheet" href="css/style.css"><link rel="icon" type="image/png" href="icon.png"> </head><body class="auth-page"><div class="auth-card"><div class="brand-mark">♠</div><h1>Poker Simulator</h1><?php if($error):?><p class="error"><?=e($error)?></p><?php endif;?><form method="POST" onsubmit="this.classList.add('loading');this.querySelector('button').textContent='Entrando...';this.querySelector('button').disabled=true;"><?=csrf_field()?><input type="email" name="email" placeholder="E-mail" required maxlength="150"><input type="password" name="password" placeholder="Senha" required maxlength="255"><button>Entrar</button></form><a href="register.php">Criar conta</a></div></body></html>
