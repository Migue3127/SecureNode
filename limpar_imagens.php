<?php
require 'includes/auth.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    die('Acesso negado.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: historico_imagens.php');
    exit;
}

$images_dir = __DIR__ . '/api/images/';
$hist_dir   = $images_dir . 'hist/';
$indice     = $hist_dir . 'indice.txt';

if (is_dir($hist_dir)) {
    foreach (glob($hist_dir . '*.jpg') ?: [] as $f) unlink($f);
    foreach (glob($hist_dir . '*.png') ?: [] as $f) unlink($f);
    file_put_contents($indice, '');
}

foreach (['webcam.jpg', 'webcam.png'] as $f) {
    if (file_exists($images_dir . $f)) unlink($images_dir . $f);
}

header('Location: historico_imagens.php');
exit;
