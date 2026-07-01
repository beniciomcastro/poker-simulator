<?php

require_once __DIR__ . '/../config/security.php';

function api_boot(): void
{
    security_headers(true);
}

function api_out(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function api_require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        api_out([
            'ok' => false,
            'error' => 'Sessão expirada. Faça login novamente.'
        ]);
    }
}

function api_require_game(): void
{
    api_require_login();

    if (empty($_SESSION['game'])) {
        api_out([
            'ok' => false,
            'error' => 'Nenhum jogo ativo.'
        ]);
    }
}

function api_require_csrf(): void
{
    csrf_require_json();
}

function save_finished_hand_once(PDO $pdo): void
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['game']) || empty($_SESSION['game']['finished'])) {
        return;
    }

    if (!empty($_SESSION['game']['handSaved'])) {
        return;
    }

    $chips = max(0, (int)($_SESSION['game']['players'][0]['chips'] ?? 0));
    $pot = max(0, (int)($_SESSION['game']['pot'] ?? 0));

    $winner = mb_substr((string)($_SESSION['game']['winner'] ?? ''), 0, 100);
    $result = mb_substr((string)($_SESSION['game']['handResult'] ?? ''), 0, 255);

    $pdo->prepare('UPDATE users SET chips=? WHERE id=?')->execute([
        $chips,
        (int)$_SESSION['user_id']
    ]);

    $pdo->prepare('INSERT INTO hands (user_id,winner_name,pot,result_text) VALUES (?,?,?,?)')
        ->execute([
            (int)$_SESSION['user_id'],
            $winner,
            $pot,
            $result
        ]);

    $_SESSION['game']['handSaved'] = true;
}
