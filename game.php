<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Poker Simulator</title>
<link rel="stylesheet" href="css/style.css">
<link rel="icon" type="image/png" href="icon.png"> 
</head>
<body class="game-page">
<div class="app">
    <div class="visual-message" id="visualMessage"></div>
    <div class="table-intro hidden" id="tableIntro"><div class="intro-card"><h2></h2><div class="intro-players"></div><p></p></div></div>
    <main class="table-shell">
        <section class="poker-table" id="table">
            <div class="felt-pattern"></div>
            <div class="pot" id="pot"><div class="chips"><span></span><span></span><span></span></div><b>0</b></div>
            <div class="board" id="board"></div>
            <div class="stage" id="stage"></div>
            <div class="seat seat-0 human"><div class="avatar">VOCÊ</div><div class="info"><b></b><span></span><small></small></div><div class="cards"></div><div class="bet"></div></div>
            <div class="seat seat-1"><div class="avatar">L</div><div class="info"><b></b><span></span><small></small></div><div class="cards"></div><div class="bet"></div></div>
            <div class="seat seat-2"><div class="avatar">M</div><div class="info"><b></b><span></span><small></small></div><div class="cards"></div><div class="bet"></div></div>
            <div class="seat seat-3"><div class="avatar">N</div><div class="info"><b></b><span></span><small></small></div><div class="cards"></div><div class="bet"></div></div>
        </section>
    </main>
    <section class="controls" id="controls">
        <button type="button" data-action="check">Check</button>
        <button type="button" data-action="call">Pagar</button>
        <button type="button" data-action="fold" class="danger">Fold</button>
        <label><input type="number" id="raiseValue" min="20" step="10" value="20"></label>
        <button type="button" data-action="raise" class="gold">Aumentar</button>
    </section>
    <section class="end-panel hidden" id="endPanel"><h2></h2><div></div></section>
    <button class="exit" onclick="location.href='logout.php'">Sair</button>
</div>
<script src="js/game.js"></script>
</body>
</html>
