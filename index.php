<?php
session_start();
header('Location: ' . (isset($_SESSION['user_id']) ? 'game.php' : 'login.php'));
exit;
