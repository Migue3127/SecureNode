<?php
// API do Sistema de Vigilância Inteligente — SecureNode
// Suporta GET (leitura) e POST (escrita) para sensores e atuadores

header('Content-Type: text/plain; charset=utf-8');

// Diretório base dos ficheiros de dados
define('BASE_DIR', __DIR__ . '/files/');

// --- Autenticação por API Key ---
// Pedidos POST exigem o header X-API-Key com uma chave válida
// Pedidos GET do dashboard (sem chave) são permitidos para leitura
require __DIR__ . '/../includes/api_auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarApiKey();
}

// Sensores e atuadores permitidos
$permitidos = ['temperatura', 'humidade', 'som', 'fogo', 'buzzer', 'rele', 'pir', 'choque'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Escrita de dados (sensor ou atuador envia valor) ---

    if (!isset($_POST['nome']) || !isset($_POST['valor'])) {
        http_response_code(400);
        echo 'Erro 400: faltam campos obrigatórios (nome, valor)';
        exit;
    }

    $nome  = trim($_POST['nome']);
    $valor = trim($_POST['valor']);

    // Usa sempre a hora do servidor
    $hora = date('Y-m-d H:i:s');

    // Valida se o nome é permitido (evita path traversal)
    if (!in_array($nome, $permitidos)) {
        http_response_code(400);
        echo 'Erro 400: sensor/atuador desconhecido';
        exit;
    }

    // Valida que o valor é numérico (0/1 para binários, decimal para temperatura)
    if (!is_numeric($valor)) {
        http_response_code(400);
        echo 'Erro 400: valor inválido';
        exit;
    }

    $dir = BASE_DIR . $nome . '/';

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($dir . 'valor.txt', $valor);
    file_put_contents($dir . 'hora.txt',  $hora);
    file_put_contents($dir . 'log.txt', $hora . ';' . $valor . PHP_EOL, FILE_APPEND);

    http_response_code(200);
    echo 'OK: ' . $nome . ' = ' . $valor . ' @ ' . $hora;

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // --- Fotos recentes ---
    if (isset($_GET['fotos'])) {
        $n = min(10, max(1, intval($_GET['fotos'])));
        $indice = __DIR__ . '/images/hist/indice.txt';
        $result = [];
        if (file_exists($indice)) {
            $linhas = array_slice(file($indice, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), -$n);
            foreach (array_reverse($linhas) as $linha) {
                $partes = explode('|', $linha);
                if (count($partes) === 2)
                    $result[] = ['timestamp' => trim($partes[0]), 'ficheiro' => trim($partes[1])];
            }
        }
        header('Content-Type: text/plain; charset=utf-8');
        foreach ($result as $item)
            echo $item['timestamp'] . '|' . $item['ficheiro'] . "\n";
        exit;
    }

    // --- Últimas N entradas do log de um sensor ---
    if (isset($_GET['log'])) {
        $sensor_log = trim($_GET['log']);
        if (!in_array($sensor_log, $permitidos)) { http_response_code(400); echo 'Erro 400'; exit; }
        $limite_log = min(50, max(1, intval($_GET['limite'] ?? 20)));
        $ficheiro_log = BASE_DIR . $sensor_log . '/log.txt';
        if (file_exists($ficheiro_log)) {
            $linhas_log = array_slice(file($ficheiro_log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), -$limite_log);
            echo implode("\n", $linhas_log);
        }
        exit;
    }

    // --- Leitura de dados ---
    if (!isset($_GET['nome'])) {
        http_response_code(400);
        echo 'Erro 400: falta o parâmetro nome';
        exit;
    }

    $nome = trim($_GET['nome']);

    if (!in_array($nome, $permitidos)) {
        http_response_code(400);
        echo 'Erro 400: sensor/atuador desconhecido';
        exit;
    }

    $ficheiro = BASE_DIR . $nome . '/valor.txt';

    if (!file_exists($ficheiro)) {
        http_response_code(404);
        echo 'Erro 404: sem dados para ' . $nome;
        exit;
    }

    http_response_code(200);
    echo trim(file_get_contents($ficheiro));

} else {
    // Método HTTP não permitido
    http_response_code(405);
    echo 'Erro 405: método não permitido';
}
?>
