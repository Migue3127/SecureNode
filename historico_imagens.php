<?php
// Mostra todas as imagens capturadas pelos sensores (até MAX_HISTORICO=20).
// Ao clicar numa imagem abre o lightbox com o gatilho e timestamp.

$titulo_pagina = 'SecureNode — Histórico de Imagens';
$pagina_atual  = 'imagens';
require 'includes/auth.php';

$indice  = 'api/images/hist/indice.txt';
$imagens = [];
if (file_exists($indice)) {
    foreach (array_reverse(file($indice, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) as $linha) {
        $partes = explode('|', $linha);
        if (count($partes) === 2)
            $imagens[] = ['timestamp' => trim($partes[0]), 'ficheiro' => trim($partes[1])];
    }
}
?>
<?php require 'includes/head.php'; ?>

<?php require 'includes/navbar.php'; ?>

<div class="container-fluid px-4 py-3">

    <h1 class="visually-hidden">SecureNode — Histórico de Imagens da Webcam</h1>

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="section-title mb-0">Histórico de Imagens da Webcam</h2>
        <?php if (($_SESSION['role'] ?? '') === 'admin' && !empty($imagens)): ?>
        <form method="post" action="limpar_imagens.php" style="display:inline;"
              onsubmit="return confirm('Apagar todas as imagens?')">
            <button type="submit" class="btn btn-sm btn-danger" style="font-size:0.75rem;">
                Limpar Imagens
            </button>
        </form>
        <?php endif; ?>
    </div>

    <?php if (empty($imagens)): ?>
        <div class="card card-tabela p-4 text-center">
            <p class="text-muted mb-0">Sem imagens registadas. Aguarda capturas da câmara de vigilância.</p>
        </div>
    <?php else: ?>
        <p class="text-muted mb-3" style="font-size:0.82rem;">
            Últimas <?= count($imagens) ?> capturas — da mais recente para a mais antiga.
        </p>
        <div class="row g-3">
            <?php foreach ($imagens as $i => $img): ?>
            <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                <div class="card card-sensor h-100">
                    <?php
                        $f = $img['ficheiro'];
                        if (strpos($f, 'pir') !== false)
                            $gatilho = '<span class="badge bg-warning text-dark">PIR</span>';
                        elseif (strpos($f, 'choque') !== false)
                            $gatilho = '<span class="badge bg-danger">Choque</span>';
                        elseif (strpos($f, 'fogo') !== false)
                            $gatilho = '<span class="badge bg-danger">Fogo</span>';
                        elseif (strpos($f, 'som') !== false)
                            $gatilho = '<span class="badge bg-info text-dark">Som</span>';
                        else
                            $gatilho = '';
                    ?>
                    <div class="card-header-custom webcam-header d-flex justify-content-between align-items-center">
                        <span>#<?= count($imagens) - $i ?> <?= $gatilho ?></span>
                        <?php if ($i === 0): ?>
                            <span class="badge bg-success" style="font-size:0.65rem;">Recente</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body-custom p-1 text-center">
                        <img src="api/images/hist/<?= htmlspecialchars($img['ficheiro']) ?>"
                             alt="Captura <?= htmlspecialchars($img['timestamp']) ?>"
                             class="img-webcam img-lightbox-trigger"
                             style="cursor:zoom-in;"
                             data-timestamp="<?= htmlspecialchars($img['timestamp']) ?>"
                             data-ficheiro="<?= htmlspecialchars($img['ficheiro']) ?>"
                             data-num="<?= count($imagens) - $i ?>"
                             data-gatilho="<?= strpos($img['ficheiro'],'pir')!==false ? 'PIR — Movimento' : (strpos($img['ficheiro'],'choque')!==false ? 'Choque/Vibração' : (strpos($img['ficheiro'],'fogo')!==false ? 'Fogo' : (strpos($img['ficheiro'],'som')!==false ? 'Som' : 'Manual'))) ?>"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
                        <div style="display:none;color:var(--cor-texto-muted);font-size:0.75rem;padding:1rem;">
                            Imagem não disponível
                        </div>
                    </div>
                    <div class="card-footer-custom" style="flex-direction:column;align-items:flex-start;gap:0.1rem;">
                        <span><?= htmlspecialchars(str_replace('_', ' ', $img['timestamp'])) ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<!-- Lightbox -->
<div id="lightbox" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.88);z-index:9999;align-items:center;justify-content:center;flex-direction:column;gap:1rem;cursor:zoom-out;">
    <img id="lb-img" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBTAA7" alt="" style="max-width:90vw;max-height:75vh;border-radius:8px;box-shadow:0 0 40px rgba(0,0,0,0.8);">
    <div style="background:var(--cor-painel);border:1px solid var(--cor-borda);border-radius:8px;padding:0.75rem 1.5rem;text-align:center;min-width:260px;">
        <div id="lb-num"  style="font-size:0.75rem;color:var(--cor-texto-muted);margin-bottom:0.25rem;"></div>
        <div id="lb-gatilho" style="font-size:1rem;font-weight:600;color:var(--cor-texto);margin-bottom:0.25rem;"></div>
        <div id="lb-ts"   style="font-size:0.85rem;color:var(--cor-texto-muted);"></div>
    </div>
    <div style="font-size:0.75rem;color:#6b7280;">Clica em qualquer lugar ou prime ESC para fechar</div>
</div>

<script>
const lb        = document.getElementById('lightbox');
const lbImg     = document.getElementById('lb-img');
const lbNum     = document.getElementById('lb-num');
const lbGatilho = document.getElementById('lb-gatilho');
const lbTs      = document.getElementById('lb-ts');

document.querySelectorAll('.img-lightbox-trigger').forEach(img => {
    img.addEventListener('click', function() {
        lbImg.src        = this.src;
        lbNum.textContent     = 'Captura #' + this.dataset.num;
        lbGatilho.textContent = this.dataset.gatilho;
        lbTs.textContent      = this.dataset.timestamp.replace(/_/g, ' ');
        lb.style.display = 'flex';
    });
});

lb.addEventListener('click', () => lb.style.display = 'none');
document.addEventListener('keydown', e => { if (e.key === 'Escape') lb.style.display = 'none'; });
</script>

<?php require 'includes/footer.php'; ?>
