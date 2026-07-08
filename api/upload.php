<?php
// upload.php — Recebe imagem da webcam (Raspberry Pi) e guarda no servidor
// Validação de tamanho (≤ 1000 kB) e tipo (.jpg ou .png)
// Guarda histórico das últimas 20 imagens 

define('IMAGES_DIR', __DIR__ . '/images/');
define('MAX_HISTORICO', 20);

if (!is_dir(IMAGES_DIR))            mkdir(IMAGES_DIR,             0755, true);
if (!is_dir(IMAGES_DIR . 'hist/')) mkdir(IMAGES_DIR . 'hist/',   0755, true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Erro 405: método não permitido';
    exit;
}

require __DIR__ . '/../includes/api_auth.php';
validarApiKey();

if (!isset($_FILES['imagem'])) {
    http_response_code(400);
    echo 'Erro 400: imagem não encontrada no pedido';
    exit;
}

$ficheiro = $_FILES['imagem'];

// Validação do tamanho (máximo 1000 kB = 1.000.000 bytes)
if ($ficheiro['size'] > 1000000) {
    http_response_code(413);
    echo 'Erro 413: imagem demasiado grande (máximo 1000 kB)';
    exit;
}

// Valida que é uma imagem real com getimagesize()
$info_img = @getimagesize($ficheiro['tmp_name']);
if ($info_img === false) {
    http_response_code(415);
    echo 'Erro 415: ficheiro não é uma imagem válida';
    exit;
}

$tipos_permitidos = [IMAGETYPE_JPEG, IMAGETYPE_PNG];
if (!in_array($info_img[2], $tipos_permitidos)) {
    http_response_code(415);
    echo 'Erro 415: tipo não permitido (apenas .jpg ou .png)';
    exit;
}

$extensao = ($info_img[2] === IMAGETYPE_PNG) ? '.png' : '.jpg';
$timestamp = date('Y-m-d_H-i-s');

// Guarda como webcam.jpg (imagem atual no dashboard)
$destino_atual = IMAGES_DIR . 'webcam' . $extensao;
if (!move_uploaded_file($ficheiro['tmp_name'], $destino_atual)) {
    http_response_code(500);
    echo 'Erro 500: falha ao guardar a imagem';
    exit;
}

// Usa o nome original do ficheiro
// para que historico_imagens.php possa mostrar o badge de gatilho correto
$nome_base = preg_replace('/[^a-zA-Z0-9_\-]/', '', pathinfo($ficheiro['name'], PATHINFO_FILENAME));
$nome_hist = (!empty($nome_base) ? $nome_base : $timestamp) . $extensao;
if (!copy($destino_atual, IMAGES_DIR . 'hist/' . $nome_hist)) {
    http_response_code(500);
    echo 'Erro 500: falha ao guardar imagem no histórico';
    exit;
}

// Regista no índice do histórico (formato: timestamp|nome_ficheiro)
$indice = IMAGES_DIR . 'hist/indice.txt';
file_put_contents($indice, $timestamp . '|' . $nome_hist . PHP_EOL, FILE_APPEND);

// Mantém apenas as últimas MAX_HISTORICO entradas (apaga ficheiros antigos)
$linhas = file($indice, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (count($linhas) > MAX_HISTORICO) {
    $apagar = array_slice($linhas, 0, count($linhas) - MAX_HISTORICO);
    foreach ($apagar as $linha) {
        $partes = explode('|', $linha);
        if (count($partes) === 2) {
            $antigo = IMAGES_DIR . 'hist/' . trim($partes[1]);
            if (file_exists($antigo)) unlink($antigo);
        }
    }
    $novas = array_slice($linhas, -MAX_HISTORICO);
    file_put_contents($indice, implode(PHP_EOL, $novas) . PHP_EOL);
}

http_response_code(200);
echo 'OK: imagem recebida e guardada (' . round($ficheiro['size'] / 1024, 1) . ' kB)';
?>
