<?php
// controlar.php — Controlo de atuadores via dashboard (autenticado por sessão)
date_default_timezone_set('Europe/Lisbon');
session_start();

if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    http_response_code(403);
    echo 'Erro 403: sessão inválida';
    exit;
}

// Timeout de sessão: 1 hora sem atividade
if (isset($_SESSION['ultimo_acesso']) && time() - $_SESSION['ultimo_acesso'] > 3600) {
    session_unset(); session_destroy();
    http_response_code(403);
    echo 'Erro 403: sessão expirada';
    exit;
}
$_SESSION['ultimo_acesso'] = time();

// Apenas admin e operador podem controlar atuadores
$role = $_SESSION['role'] ?? 'visitante';
if (!in_array($role, ['admin', 'operador'])) {
    http_response_code(403);
    echo 'Erro 403: sem permissão';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Erro 405: método não permitido';
    exit;
}

// Apenas atuadores podem ser controlados pelo dashboard
$permitidos = ['buzzer', 'rele'];

$nome  = trim($_POST['nome']  ?? '');
$valor = trim($_POST['valor'] ?? '');

if (!in_array($nome, $permitidos) || !in_array($valor, ['0', '1'])) {
    http_response_code(400);
    echo 'Erro 400: parâmetros inválidos';
    exit;
}

// Escreve diretamente nos ficheiros
$dir = __DIR__ . '/api/files/' . $nome . '/';
if (!is_dir($dir)) mkdir($dir, 0755, true);

$hora = date('Y-m-d H:i:s');
file_put_contents($dir . 'valor.txt', $valor);
file_put_contents($dir . 'hora.txt',  $hora);
file_put_contents($dir . 'log.txt',   $hora . ';' . $valor . PHP_EOL, FILE_APPEND);

http_response_code(200);
echo 'OK: ' . $nome . ' = ' . $valor . ' @ ' . $hora;
?>
