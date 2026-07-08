<?php
$titulo_pagina = 'SecureNode — Histórico';
$pagina_atual  = 'historico';
require 'includes/auth.php';

// so_ativos = true: sensores binários onde só interessam as deteções (valor=1),
$disponiveis = [
    'temperatura' => ['nome' => 'Temperatura',     'unidade' => '°C', 'dispositivo' => 'Raspberry Pi', 'so_ativos' => false],
    'humidade'    => ['nome' => 'Humidade',         'unidade' => '%',  'dispositivo' => 'Raspberry Pi', 'so_ativos' => false],
    'som'         => ['nome' => 'Som',              'unidade' => '',   'dispositivo' => 'ESP32',        'so_ativos' => true],
    'fogo'        => ['nome' => 'Fogo',             'unidade' => '',   'dispositivo' => 'ESP32',        'so_ativos' => true],
    'pir'         => ['nome' => 'Movimento (PIR)',  'unidade' => '',   'dispositivo' => 'Raspberry Pi', 'so_ativos' => true],
    'choque'      => ['nome' => 'Choque',           'unidade' => '',   'dispositivo' => 'Raspberry Pi', 'so_ativos' => true],
    'buzzer'      => ['nome' => 'Buzzer',           'unidade' => '',   'dispositivo' => 'Raspberry Pi', 'so_ativos' => false],
    'rele'        => ['nome' => 'Relé',             'unidade' => '',   'dispositivo' => 'ESP32',        'so_ativos' => false],
];

$sensor_sel = isset($_GET['sensor']) && array_key_exists($_GET['sensor'], $disponiveis)
    ? $_GET['sensor']
    : 'temperatura';

$info = $disponiveis[$sensor_sel];

$caminho_log = "api/files/{$sensor_sel}/log.txt";
$linhas = [];
if (file_exists($caminho_log)) {
    $todas = file($caminho_log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($info['so_ativos']) {
        $todas = array_values(array_filter($todas, fn($l) => trim(explode(';', $l)[1] ?? '') === '1'));
    }
    $linhas = array_reverse(array_slice($todas, -50));
}
?>
<?php require 'includes/head.php'; ?>

<?php require 'includes/navbar.php'; ?>

<div class="container-fluid px-4 py-3">

    <h1 class="visually-hidden">SecureNode — Histórico de Sensores e Atuadores</h1>

    <h2 class="section-title mb-3">Histórico de Sensores e Atuadores</h2>

    <div class="card card-tabela mb-4">
        <div class="card-body-custom">
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($disponiveis as $chave => $dados): ?>
                    <a href="historico.php?sensor=<?= $chave ?>"
                       class="btn btn-sensor-sel <?= ($chave === $sensor_sel) ? 'ativo' : '' ?>">
                        <?= $dados['nome'] ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card card-tabela">
        <div class="card-header-custom tabela-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                Histórico — <?= htmlspecialchars($info['nome']) ?>
                <span class="badge-dispositivo <?= $info['dispositivo'] === 'ESP32' ? 'esp32' : '' ?> ms-2">
                    <?= $info['dispositivo'] ?>
                </span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted" style="font-size:0.8rem;">Últimas 50 entradas</span>
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                <form method="post" action="limpar_log.php" style="display:inline;"
                      onsubmit="return confirm('Apagar todo o histórico de <?= htmlspecialchars($info['nome']) ?>?')">
                    <input type="hidden" name="sensor" value="<?= htmlspecialchars($sensor_sel) ?>">
                    <button type="submit" class="btn btn-sm btn-danger" style="font-size:0.75rem;">
                        Limpar Histórico
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body-custom p-0">
            <?php if (empty($linhas)): ?>
                <div class="p-4 text-center text-muted">Sem dados disponíveis para este sensor.</div>
            <?php else: ?>
                <table class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Data e Hora</th>
                            <th>Valor</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($linhas as $i => $linha):
                            $partes = explode(';', $linha);
                            if (count($partes) < 2) continue;
                            $hora   = htmlspecialchars(trim($partes[0]));
                            $valor  = htmlspecialchars(trim($partes[1]));
                            $unidade = $info['unidade'];

                            if ($sensor_sel === 'temperatura') {
                                $badge = floatval($valor) > 35
                                    ? '<span class="badge bg-danger">Elevada</span>'
                                    : '<span class="badge bg-success">Normal</span>';
                            } elseif ($info['so_ativos']) {
                                $badge = '<span class="badge bg-warning text-dark">Ativo</span>';
                            } elseif (in_array($sensor_sel, ['buzzer', 'rele'])) {
                                $badge = $valor == '1'
                                    ? '<span class="badge bg-warning text-dark">Ativo</span>'
                                    : '<span class="badge bg-secondary">Inativo</span>';
                            } else {
                                $badge = '<span class="badge bg-primary">—</span>';
                            }
                        ?>
                        <tr>
                            <td class="text-muted"><?= $i + 1 ?></td>
                            <td><?= $hora ?></td>
                            <td><strong><?= $valor ?><?= $unidade ?></strong></td>
                            <td><?= $badge ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require 'includes/footer.php'; ?>
