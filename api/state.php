<?php
session_start();
require_once 'helpers.php';
api_boot();
api_out(['ok'=>true, 'game'=>$_SESSION['game'] ?? null]);
