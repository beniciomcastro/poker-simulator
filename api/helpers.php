<?php
function api_boot(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}

function api_out(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function save_finished_hand_once(PDO $pdo): void
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['game']) || empty($_SESSION['game']['finished'])) return;
    if (!empty($_SESSION['game']['handSaved'])) return;

    $chips = (int)($_SESSION['game']['players'][0]['chips'] ?? 0);
    $pdo->prepare('UPDATE users SET chips=? WHERE id=?')->execute([$chips, $_SESSION['user_id']]);
    $pdo->prepare('INSERT INTO hands (user_id,winner_name,pot,result_text) VALUES (?,?,?,?)')
        ->execute([
            $_SESSION['user_id'],
            $_SESSION['game']['winner'] ?? '',
            (int)($_SESSION['game']['pot'] ?? 0),
            $_SESSION['game']['handResult'] ?? ''
        ]);
    $_SESSION['game']['handSaved'] = true;
}
