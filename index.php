<?php

require_once __DIR__ . '/config/security.php';
secure_session_start();
security_headers();

header('Location: ' . (isset($_SESSION['user_id']) ? 'game.php' : 'login.php'));
exit;
