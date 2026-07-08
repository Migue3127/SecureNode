<?php
// Autenticação de pedidos da API (sensores e upload de imagens).
// As chaves estão em config/api_keys.txt — formato: chave|descrição (uma por linha).
// Chamada em api.php (POST) e upload.php.

date_default_timezone_set('Europe/Lisbon');
function validarApiKey() {
    $chave = isset($_SERVER['HTTP_X_API_KEY']) ? trim($_SERVER['HTTP_X_API_KEY']) : '';
    if (empty($chave)) {
        http_response_code(401);
        echo 'Erro 401: autenticação necessária (X-API-Key em falta)';
        exit;
    }
    $ficheiro = __DIR__ . '/../config/api_keys.txt';
    if (!file_exists($ficheiro)) {
        http_response_code(500);
        echo 'Erro 500: ficheiro de chaves não encontrado';
        exit;
    }
    foreach (file($ficheiro, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
        $partes = explode('|', $linha);
        if (count($partes) >= 1 && trim($partes[0]) === $chave) return;
    }
    http_response_code(403);
    echo 'Erro 403: chave de API inválida';
    exit;
}
