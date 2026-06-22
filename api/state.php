<?php

session_start();

require_once __DIR__ . '/helpers.php';

api_boot();

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