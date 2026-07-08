<?php
require 'includes/auth.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    die('Acesso negado.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: historico.php');
    exit;
}

$permitidos = ['temperatura', 'humidade', 'som', 'fogo', 'buzzer', 'rele', 'pir', 'choque'];
$sensor = isset($_POST['sensor']) && in_array($_POST['sensor'], $permitidos) ? $_POST['sensor'] : null;

if ($sensor) {
    $log = "api/files/{$sensor}/log.txt";
    if (file_exists($log)) file_put_contents($log, '');
}

header('Location: historico.php?sensor=' . urlencode($sensor ?? 'temperatura'));
exit;
