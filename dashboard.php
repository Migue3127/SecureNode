<?php
// Página principal — lê os valores atuais de todos os sensores e atuadores dos ficheiros .txt

$titulo_pagina = 'SecureNode — Dashboard';
$pagina_atual  = 'dashboard';
require 'includes/auth.php';
require 'config/config.php';

$role = $_SESSION['role'] ?? 'visitante';
$pode_controlar = in_array($role, ['admin', 'operador']);

// Lê um ficheiro de texto; devolve o conteúdo ou um valor por omissão se não existir
function lerFicheiro($caminho, $default = '—') {
    return file_exists($caminho) ? trim(file_get_contents($caminho)) : $default;
}

$temp_valor  = lerFicheiro('api/files/temperatura/valor.txt', '0');
$temp_hora   = lerFicheiro('api/files/temperatura/hora.txt',  '—');
$hum_valor   = lerFicheiro('api/files/humidade/valor.txt',    '0');
$hum_hora    = lerFicheiro('api/files/humidade/hora.txt',     '—');
$som_valor   = lerFicheiro('api/files/som/valor.txt',   '0');
$som_hora    = lerFicheiro('api/files/som/hora.txt',    '—');
$fogo_valor  = lerFicheiro('api/files/fogo/valor.txt',  '0');
$fogo_hora   = lerFicheiro('api/files/fogo/hora.txt',   '—');
$pir_valor    = lerFicheiro('api/files/pir/valor.txt',    '0');
$pir_hora     = lerFicheiro('api/files/pir/hora.txt',     '—');
$choque_valor = lerFicheiro('api/files/choque/valor.txt', '0');
$choque_hora  = lerFicheiro('api/files/choque/hora.txt',  '—');
$buzzer_valor = lerFicheiro('api/files/buzzer/valor.txt', '0');
$buzzer_hora  = lerFicheiro('api/files/buzzer/hora.txt',  '—');
$rele_valor   = lerFicheiro('api/files/rele/valor.txt',   '0');
$rele_hora    = lerFicheiro('api/files/rele/hora.txt',    '—');

// Lê as últimas N imagens do índice para mostrar as capturas recentes no carregamento inicial
function lerUltimasImagens($n = 3) {
    $indice = 'api/images/hist/indice.txt';
    if (!file_exists($indice)) return [];
    $linhas = file($indice, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $resultado = [];
    foreach (array_reverse(array_slice($linhas, -$n)) as $linha) {
        $partes = explode('|', $linha);
        if (count($partes) === 2)
            $resultado[] = ['timestamp' => trim($partes[0]), 'ficheiro' => trim($partes[1])];
    }
    return $resultado;
}
$ultimas_imagens = lerUltimasImagens(3);

// Lê o log de um sensor e extrai labels (HH:MM) e valores
function lerLog($sensor, $limite = 20) {
    $caminho = "api/files/{$sensor}/log.txt";
    if (!file_exists($caminho)) return ['labels' => [], 'valores' => []];
    $linhas = array_slice(file($caminho, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), -$limite);
    $labels = []; $valores = [];
    foreach ($linhas as $linha) {
        $partes = explode(';', $linha);
        if (count($partes) < 2) continue;
        $labels[]  = substr(trim($partes[0]), 11, 5);  // extrai HH:MM do timestamp
        $valores[] = floatval(trim($partes[1]));
    }
    return ['labels' => $labels, 'valores' => $valores];
}
$grafico_temp = lerLog('temperatura');
$grafico_hum  = lerLog('humidade');


function jsArray($arr, $numerico = false) {
    if ($numerico)
        return '[' . implode(',', array_map('floatval', $arr)) . ']';
    $itens = array_map(fn($v) => '"' . addslashes((string)$v) . '"', $arr);
    return '[' . implode(',', $itens) . ']';
}

$alerta_ativo   = ($pir_valor == '1' || $choque_valor == '1' || $fogo_valor == '1' || $som_valor == '1');
$estado_sistema = $alerta_ativo ? 'ALERTA' : 'SEGURO';

// Helper para gerar o badge do gatilho de uma captura pelo nome do ficheiro
function badgeGatilho($ficheiro) {
    if (strpos($ficheiro, 'pir')    !== false) return '<span class="badge bg-warning text-dark" style="font-size:0.6rem;">PIR</span>';
    if (strpos($ficheiro, 'choque') !== false) return '<span class="badge bg-danger" style="font-size:0.6rem;">Choque</span>';
    if (strpos($ficheiro, 'fogo')   !== false) return '<span class="badge bg-danger" style="font-size:0.6rem;">Fogo</span>';
    if (strpos($ficheiro, 'som')    !== false) return '<span class="badge bg-info text-dark" style="font-size:0.6rem;">Som</span>';
    return '';
}
?>
<?php require 'includes/head.php'; ?>

<?php require 'includes/navbar.php'; ?>

<div class="container-fluid px-4 py-3">

    <h1 class="visually-hidden">SecureNode — Dashboard de Vigilância</h1>

    <!-- Estado geral do sistema -->
    <div class="sistema-status <?= $alerta_ativo ? 'status-alerta' : 'status-seguro' ?> mb-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <span class="status-icon"><?= $alerta_ativo ? '!' : '' ?></span>
                <span class="status-label">Estado do Sistema:</span>
                <span class="status-valor"><?= $estado_sistema ?></span>
            </div>
            <div class="status-info">
                <span>Utilizador: <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></span>
                <span class="mx-2">|</span>
                <span>Última atualização: <strong id="relogio"><?= date('H:i:s') ?></strong></span>
                <span class="mx-2">|</span>
                <span class="badge-auto-refresh">Auto-refresh ativo</span>
            </div>
        </div>
    </div>

    <!-- SENSORES -->
    <h2 class="section-title">Sensores</h2>
    <div class="row g-3 mb-4">

        <!-- Temperatura (RPi) -->
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card card-sensor h-100" data-sensor="temperatura">
                <div class="card-header-custom sensor-header">Temperatura</div>
                <div class="card-body-custom text-center">
                    <div class="valor-grande <?= (floatval($temp_valor) > 35) ? 'valor-perigo' : 'valor-normal' ?>">
                        <?= htmlspecialchars($temp_valor) ?>°C
                    </div>
                    <div class="badge-dispositivo">Raspberry Pi</div>
                </div>
                <div class="card-footer-custom">
                    <?= htmlspecialchars($temp_hora) ?>
                    <a href="historico.php?sensor=temperatura" class="link-hist">Histórico</a>
                </div>
            </div>
        </div>

        <!-- Humidade (RPi) -->
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card card-sensor h-100" data-sensor="humidade">
                <div class="card-header-custom sensor-header">Humidade</div>
                <div class="card-body-custom text-center">
                    <div class="valor-grande valor-normal">
                        <?= htmlspecialchars($hum_valor) ?>%
                    </div>
                    <div class="badge-dispositivo">Raspberry Pi</div>
                </div>
                <div class="card-footer-custom">
                    <?= htmlspecialchars($hum_hora) ?>
                    <a href="historico.php?sensor=humidade" class="link-hist">Histórico</a>
                </div>
            </div>
        </div>

        <!-- Som (ESP32) -->
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card card-sensor h-100" data-sensor="som">
                <div class="card-header-custom sensor-header">Som</div>
                <div class="card-body-custom text-center">
                    <div class="valor-grande <?= ($som_valor == '1') ? 'valor-perigo' : 'valor-normal' ?>">
                        <?= ($som_valor == '1') ? 'DETETADO' : 'Silêncio' ?>
                    </div>
                    <div class="badge-dispositivo esp32">ESP32</div>
                </div>
                <div class="card-footer-custom">
                    <?= htmlspecialchars($som_hora) ?>
                    <a href="historico.php?sensor=som" class="link-hist">Histórico</a>
                </div>
            </div>
        </div>

        <!-- PIR Movimento (RPi) -->
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card card-sensor h-100 <?= ($pir_valor == '1') ? 'card-alerta' : '' ?>" data-sensor="pir">
                <div class="card-header-custom sensor-header">Movimento (PIR)</div>
                <div class="card-body-custom text-center">
                    <div class="valor-grande <?= ($pir_valor == '1') ? 'valor-perigo' : 'valor-normal' ?>">
                        <?= ($pir_valor == '1') ? 'DETETADO' : 'Sem mov.' ?>
                    </div>
                    <div class="badge-dispositivo">Raspberry Pi</div>
                </div>
                <div class="card-footer-custom">
                    <?= htmlspecialchars($pir_hora) ?>
                    <a href="historico.php?sensor=pir" class="link-hist">Histórico</a>
                </div>
            </div>
        </div>

        <!-- Choque (RPi) -->
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card card-sensor h-100 <?= ($choque_valor == '1') ? 'card-alerta' : '' ?>" data-sensor="choque">
                <div class="card-header-custom sensor-header">Choque</div>
                <div class="card-body-custom text-center">
                    <div class="valor-grande <?= ($choque_valor == '1') ? 'valor-perigo' : 'valor-normal' ?>">
                        <?= ($choque_valor == '1') ? 'DETETADO' : 'Normal' ?>
                    </div>
                    <div class="badge-dispositivo">Raspberry Pi</div>
                </div>
                <div class="card-footer-custom">
                    <?= htmlspecialchars($choque_hora) ?>
                    <a href="historico.php?sensor=choque" class="link-hist">Histórico</a>
                </div>
            </div>
        </div>

        <!-- Fogo (ESP32) -->
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card card-sensor h-100 <?= ($fogo_valor == '1') ? 'card-alerta' : '' ?>" data-sensor="fogo">
                <div class="card-header-custom sensor-header">Fogo</div>
                <div class="card-body-custom text-center">
                    <div class="valor-grande <?= ($fogo_valor == '1') ? 'valor-perigo' : 'valor-normal' ?>">
                        <?= ($fogo_valor == '1') ? 'DETETADO' : 'Sem fogo' ?>
                    </div>
                    <div class="badge-dispositivo esp32">ESP32</div>
                </div>
                <div class="card-footer-custom">
                    <?= htmlspecialchars($fogo_hora) ?>
                    <a href="historico.php?sensor=fogo" class="link-hist">Histórico</a>
                </div>
            </div>
        </div>

    </div>

    <!-- ATUADORES -->
    <h2 class="section-title">Atuadores</h2>
    <div class="row g-3 mb-4">

        <!-- Buzzer (RPi) -->
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card card-atuador h-100" data-sensor="buzzer">
                <div class="card-header-custom atuador-header">Buzzer (Alarme)</div>
                <div class="card-body-custom text-center">
                    <div class="estado-atuador <?= ($buzzer_valor == '1') ? 'estado-on' : 'estado-off' ?>">
                        <?= ($buzzer_valor == '1') ? '● LIGADO' : '○ DESLIGADO' ?>
                    </div>
                    <div class="badge-dispositivo">Raspberry Pi</div>
                    <?php if ($pode_controlar): ?>
                    <button id="btn-buzzer" class="btn btn-controlo mt-2 <?= ($buzzer_valor == '1') ? 'btn-desligar' : 'btn-ligar' ?>"
                            data-alvo="<?= ($buzzer_valor == '1') ? '0' : '1' ?>"
                            onclick="controlarAtuador('buzzer', this.dataset.alvo)">
                        <?= ($buzzer_valor == '1') ? 'Desligar Alarme' : 'Ligar Alarme' ?>
                    </button>
                    <?php else: ?>
                    <span class="badge bg-secondary mt-2" style="font-size:0.7rem;">Sem permissão</span>
                    <?php endif; ?>
                </div>
                <div class="card-footer-custom">
                    <?= htmlspecialchars($buzzer_hora) ?>
                    <a href="historico.php?sensor=buzzer" class="link-hist">Histórico</a>
                </div>
            </div>
        </div>

        <!-- Relé (ESP32) -->
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card card-atuador h-100" data-sensor="rele">
                <div class="card-header-custom atuador-header">Relé (Sirene)</div>
                <div class="card-body-custom text-center">
                    <div class="estado-atuador <?= ($rele_valor == '1') ? 'estado-on' : 'estado-off' ?>">
                        <?= ($rele_valor == '1') ? '● LIGADO' : '○ DESLIGADO' ?>
                    </div>
                    <div class="badge-dispositivo esp32">ESP32</div>
                    <?php if ($pode_controlar): ?>
                    <button id="btn-rele" class="btn btn-controlo mt-2 <?= ($rele_valor == '1') ? 'btn-desligar' : 'btn-ligar' ?>"
                            data-alvo="<?= ($rele_valor == '1') ? '0' : '1' ?>"
                            onclick="controlarAtuador('rele', this.dataset.alvo)">
                        <?= ($rele_valor == '1') ? 'Desligar Sirene' : 'Ligar Sirene' ?>
                    </button>
                    <?php else: ?>
                    <span class="badge bg-secondary mt-2" style="font-size:0.7rem;">Sem permissão</span>
                    <?php endif; ?>
                </div>
                <div class="card-footer-custom">
                    <?= htmlspecialchars($rele_hora) ?>
                    <a href="historico.php?sensor=rele" class="link-hist">Histórico</a>
                </div>
            </div>
        </div>

    </div>

    <!-- GRÁFICOS -->
    <h2 class="section-title">Gráficos</h2>
    <div class="row g-3 mb-4">

        <div class="col-12 col-md-6">
            <div class="card card-tabela">
                <div class="card-header-custom tabela-header">Temperatura — Últimas leituras</div>
                <div class="card-body-custom">
                    <canvas id="graficoTemp" height="120"></canvas>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6">
            <div class="card card-tabela">
                <div class="card-header-custom tabela-header">Humidade — Últimas leituras</div>
                <div class="card-body-custom">
                    <canvas id="graficoHum" height="120"></canvas>
                </div>
            </div>
        </div>

    </div>

    <!-- WEBCAM -->
    <h2 class="section-title">Câmara de Vigilância</h2>
    <div class="row g-3 mb-4">

        <div class="col-12 col-md-7 col-lg-6">
            <div class="card card-webcam h-100">
                <div class="card-header-custom webcam-header">Câmara ao Vivo</div>
                <div class="card-body-custom text-center p-2">
                    <img id="img-stream"
                         src="<?= CAMERA_STREAM_URL ?>"
                         alt="Câmara ao vivo"
                         class="img-webcam"
                         onerror="this.onerror=null; this.alt='Câmara offline — Python não iniciado'">
                </div>
                <div class="card-footer-custom">
                    <span>Stream ao vivo (porta 8080)</span>
                    <a href="historico_imagens.php" class="link-hist">Histórico Imagens</a>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-5 col-lg-6">
            <div class="card card-tabela h-100">
                <div class="card-header-custom tabela-header">Capturas Recentes</div>
                <div class="card-body-custom">
                    <!-- Renderizado inicialmente pelo PHP; o JS atualiza a cada 5s -->
                    <div id="fotos-recentes">
                    <?php if (empty($ultimas_imagens)): ?>
                        <p class="text-muted text-center mt-3" style="font-size:0.82rem;">Sem capturas ainda.</p>
                    <?php else: ?>
                    <div class="row g-2">
                        <?php foreach ($ultimas_imagens as $img): ?>
                        <div class="col-12 col-sm-4">
                            <div style="border:1px solid var(--cor-borda);border-radius:6px;overflow:hidden;">
                                <img src="api/images/hist/<?= htmlspecialchars($img['ficheiro']) ?>"
                                     alt="Captura"
                                     class="img-webcam"
                                     onerror="this.parentElement.style.display='none'">
                                <div style="padding:0.3rem 0.5rem;font-size:0.68rem;color:var(--cor-texto-muted);background:rgba(0,0,0,0.2);">
                                    <?= badgeGatilho($img['ficheiro']) ?>
                                    <?= htmlspecialchars(str_replace('_', ' ', $img['timestamp'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div><!-- /container -->

<div id="notificacao" class="notificacao" style="display:none;"></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Configuração base partilhada pelos dois gráficos
const cfgBase = (label, labels, dados, cor) => ({
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: label,
            data: dados,
            borderColor: cor,
            backgroundColor: cor.replace(')', ', 0.1)').replace('rgb', 'rgba'),
            borderWidth: 2,
            pointRadius: 3,
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { labels: { color: '#8b949e', font: { size: 11 } } } },
        scales: {
            x: { ticks: { color: '#8b949e', font: { size: 10 } }, grid: { color: '#21262d' } },
            y: { ticks: { color: '#8b949e', font: { size: 10 } }, grid: { color: '#21262d' } }
        }
    }
});

// Dados iniciais gerados pelo PHP na carga da página
const labelsTemp = <?= jsArray($grafico_temp['labels']) ?>;
const dadosTemp  = <?= jsArray($grafico_temp['valores'], true) ?>;
const labelsHum  = <?= jsArray($grafico_hum['labels']) ?>;
const dadosHum   = <?= jsArray($grafico_hum['valores'], true) ?>;

const chartTemp = new Chart(document.getElementById('graficoTemp'), cfgBase('°C', labelsTemp, dadosTemp, 'rgb(248,81,73)'));
const chartHum  = new Chart(document.getElementById('graficoHum'),  cfgBase('%',  labelsHum,  dadosHum,  'rgb(88,166,255)'));

// Relógio do dashboard — atualiza só o texto, não faz pedidos à API
function atualizarRelogio() {
    const agora = new Date();
    const h = String(agora.getHours()).padStart(2,'0');
    const m = String(agora.getMinutes()).padStart(2,'0');
    const s = String(agora.getSeconds()).padStart(2,'0');
    const el = document.getElementById('relogio');
    if (el) el.textContent = `${h}:${m}:${s}`;
}
setInterval(atualizarRelogio, 1000);

// Envia comando ao atuador via controlar.php e aguarda 1s para refrescar
function controlarAtuador(nome, valor) {
    const dados = new FormData();
    dados.append('nome', nome);
    dados.append('valor', valor);
    fetch('controlar.php', { method: 'POST', body: dados })
    .then(response => {
        if (response.ok) {
            mostrarNotificacao(`${nome} atualizado para ${valor == '1' ? 'LIGADO' : 'DESLIGADO'}`, 'sucesso');
            setTimeout(() => atualizarDashboard(), 1000);
        } else {
            mostrarNotificacao(`Erro ao atualizar ${nome}`, 'erro');
        }
    })
    .catch(() => mostrarNotificacao('Erro de ligação', 'erro'));
}

// Atualiza todos os valores dos sensores e atuadores (a cada 2s)
function atualizarDashboard() {
    fetch('api/api.php?nome=temperatura').then(r => r.text()).then(v => {
        const el = document.querySelector('[data-sensor="temperatura"] .valor-grande');
        if (el) { el.textContent = v + '°C'; el.className = 'valor-grande ' + (parseFloat(v) > 35 ? 'valor-perigo' : 'valor-normal'); }
    }).catch(() => {});

    fetch('api/api.php?nome=humidade').then(r => r.text()).then(v => {
        const el = document.querySelector('[data-sensor="humidade"] .valor-grande');
        if (el) el.textContent = v + '%';
    }).catch(() => {});

    // Sensores binários: o mesmo padrão para todos — atualiza texto, cor e borda do card
    const sensoresBinarios = {
        som:    ['DETETADO', 'Silêncio'],
        fogo:   ['DETETADO', 'Sem fogo'],
        pir:    ['DETETADO', 'Sem mov.'],
        choque: ['DETETADO', 'Normal'],
    };
    const valoresBinarios = {};
    const promessasBinarios = Object.entries(sensoresBinarios).map(([sensor, labels]) =>
        fetch(`api/api.php?nome=${sensor}`).then(r => r.text()).then(v => {
            const alerta = v.trim() == '1';
            valoresBinarios[sensor] = v.trim();
            const el = document.querySelector(`[data-sensor="${sensor}"] .valor-grande`);
            if (el) { el.textContent = alerta ? labels[0] : labels[1]; el.className = 'valor-grande ' + (alerta ? 'valor-perigo' : 'valor-normal'); }
            const card = document.querySelector(`[data-sensor="${sensor}"]`);
            if (card) card.classList.toggle('card-alerta', alerta);
        }).catch(() => {})
    );
    // Só atualiza o estado global após receber resposta de todos os sensores
    Promise.all(promessasBinarios).then(() => atualizarEstadoSistema(valoresBinarios));

    const atuadores = {
        buzzer: { label: ['Ligar Alarme',  'Desligar Alarme'] },
        rele:   { label: ['Ligar Sirene',  'Desligar Sirene'] }
    };
    ['buzzer', 'rele'].forEach(sensor => {
        fetch(`api/api.php?nome=${sensor}`).then(r => r.text()).then(v => {
            const el = document.querySelector(`[data-sensor="${sensor}"] .estado-atuador`);
            if (el) { el.textContent = v == '1' ? '● LIGADO' : '○ DESLIGADO'; el.className = 'estado-atuador ' + (v == '1' ? 'estado-on' : 'estado-off'); }
            const btn = document.getElementById(`btn-${sensor}`);
            if (btn) {
                btn.dataset.alvo = v == '1' ? '0' : '1';
                btn.textContent  = v == '1' ? atuadores[sensor].label[1] : atuadores[sensor].label[0];
                btn.className    = `btn btn-controlo mt-2 ${v == '1' ? 'btn-desligar' : 'btn-ligar'}`;
            }
        }).catch(() => {});
    });
}

// Estado global (SEGURO/ALERTA) calculado após ter todos os valores binários
function atualizarEstadoSistema(valores) {
    const alerta = Object.values(valores).some(v => v === '1');
    const el    = document.querySelector('.sistema-status');
    const icone = document.querySelector('.status-icon');
    const valor = document.querySelector('.status-valor');
    if (el)    el.className     = 'sistema-status ' + (alerta ? 'status-alerta' : 'status-seguro') + ' mb-4';
    if (icone) icone.textContent = alerta ? '!' : '';
    if (valor) valor.textContent = alerta ? 'ALERTA' : 'SEGURO';
}

// Capturas recentes — a cada 5s busca as 3 últimas do índice via API
function atualizarFotosRecentes() {
    fetch('api/api.php?fotos=3').then(r => r.text()).then(texto => {
        const imagens = texto.trim().split('\n').filter(l => l.includes('|')).map(l => {
            const p = l.split('|');
            return { timestamp: p[0].trim(), ficheiro: p[1].trim() };
        });
        const el = document.getElementById('fotos-recentes');
        if (!el) return;
        if (!imagens.length) {
            el.innerHTML = '<p class="text-muted text-center mt-3" style="font-size:0.82rem;">Sem capturas ainda.</p>';
            return;
        }
        let html = '<div class="row g-2">';
        imagens.forEach(img => {
            const gatilho = img.ficheiro.includes('pir')
                ? '<span class="badge bg-warning text-dark" style="font-size:0.6rem;">PIR</span>'
                : img.ficheiro.includes('choque')
                ? '<span class="badge bg-danger" style="font-size:0.6rem;">Choque</span>'
                : img.ficheiro.includes('fogo')
                ? '<span class="badge bg-danger" style="font-size:0.6rem;">Fogo</span>'
                : img.ficheiro.includes('som')
                ? '<span class="badge bg-info text-dark" style="font-size:0.6rem;">Som</span>'
                : '';
            const ts = img.timestamp.replace(/_/g, ' ');
            html += `<div class="col-12 col-sm-4">
                <div style="border:1px solid var(--cor-borda);border-radius:6px;overflow:hidden;">
                    <img src="api/images/hist/${img.ficheiro}" alt="Captura" class="img-webcam"
                         onerror="this.parentElement.style.display='none'">
                    <div style="padding:0.3rem 0.5rem;font-size:0.68rem;color:var(--cor-texto-muted);background:rgba(0,0,0,0.2);">
                        ${gatilho} ${ts}
                    </div>
                </div>
            </div>`;
        });
        html += '</div>';
        el.innerHTML = html;
    }).catch(() => {});
}

// Gráficos — atualiza a cada 15s com as últimas 20 leituras do log
// O log.txt tem formato "YYYY-MM-DD HH:MM:SS;valor" — extrai HH:MM para o eixo X
function atualizarGraficos() {
    fetch('api/api.php?log=temperatura&limite=20').then(r => r.text()).then(texto => {
        const linhas = texto.trim().split('\n').filter(l => l.includes(';'));
        chartTemp.data.labels           = linhas.map(l => l.split(';')[0].trim().substring(11, 16));
        chartTemp.data.datasets[0].data = linhas.map(l => parseFloat(l.split(';')[1].trim()));
        chartTemp.update();
    }).catch(() => {});
    fetch('api/api.php?log=humidade&limite=20').then(r => r.text()).then(texto => {
        const linhas = texto.trim().split('\n').filter(l => l.includes(';'));
        chartHum.data.labels           = linhas.map(l => l.split(';')[0].trim().substring(11, 16));
        chartHum.data.datasets[0].data = linhas.map(l => parseFloat(l.split(';')[1].trim()));
        chartHum.update();
    }).catch(() => {});
}

setInterval(atualizarDashboard, 2000);
setInterval(atualizarGraficos, 15000);
setInterval(atualizarFotosRecentes, 5000);

function mostrarNotificacao(msg, tipo) {
    const el = document.getElementById('notificacao');
    el.textContent = msg;
    el.className = `notificacao notificacao-${tipo}`;
    el.style.display = 'block';
    setTimeout(() => { el.style.display = 'none'; }, 3000);
}
</script>
<?php require 'includes/footer.php'; ?>
