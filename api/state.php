<?php

require_once __DIR__ . '/helpers.php';
secure_session_start();
api_boot();

if (empty($_SESSION['user_id'])) {
    api_out([
        'ok' => false,
        'error' => 'Sessão expirada. Faça login novamente.'
    ]);
}

if (empty($_SESSION['table_mode_chosen'])) {
    api_out([
        'ok' => true,
        'game' => null
    ]);
}

api_out([
    'ok' => true,
    'game' => $_SESSION['game'] ?? null
]);
