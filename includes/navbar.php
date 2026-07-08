<nav class="navbar navbar-expand-sm navbar-dark navbar-custom">
    <div class="container-fluid">
        <a class="navbar-brand brand-custom" href="dashboard.php">🛡️ SecureNode</a>
        <div class="navbar-nav ms-auto d-flex flex-row align-items-center gap-3">
            <a class="nav-link nav-link-custom <?= ($pagina_atual ?? '') === 'dashboard' ? 'active' : '' ?>" href="dashboard.php">Dashboard</a>
            <a class="nav-link nav-link-custom <?= ($pagina_atual ?? '') === 'historico' ? 'active' : '' ?>" href="historico.php">Histórico</a>
            <a class="nav-link nav-link-custom <?= ($pagina_atual ?? '') === 'imagens'   ? 'active' : '' ?>" href="historico_imagens.php">Imagens</a>
            <?php
            $perms = [
                'admin'     => ['Acesso total ao sistema', 'Ver dashboard, histórico e imagens', 'Controlar atuadores', 'Limpar histórico e imagens'],
                'operador'  => ['Acesso operacional', 'Ver dashboard, histórico e imagens', 'Controlar atuadores'],
                'visitante' => ['Acesso de leitura', 'Ver dashboard, histórico e imagens', 'Sem controlo de atuadores'],
            ];
            $role_atual  = $_SESSION['role'];
            $lista_perms = $perms[$role_atual] ?? [];
            ?>
            <div class="nav-user" style="position:relative;display:inline-block;">
                <?= htmlspecialchars($_SESSION['username']) ?>
                <span id="badge-role-btn" class="badge-role" style="cursor:pointer;" onclick="togglePermsBox(event)">
                    <?= htmlspecialchars($role_atual) ?>
                </span>
                <div id="perms-box" style="display:none;position:absolute;top:calc(100% + 10px);right:0;width:220px;background:var(--cor-painel);border:1px solid var(--cor-borda);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,0.5);z-index:9999;padding:0.75rem 1rem;">
                    <div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.08em;color:var(--cor-texto-muted);margin-bottom:0.5rem;">Permissões — <?= htmlspecialchars($role_atual) ?></div>
                    <?php foreach ($lista_perms as $i => $p): ?>
                    <div style="font-size:0.8rem;color:<?= $i === 0 ? 'var(--cor-texto)' : 'var(--cor-texto-muted)' ?>;font-weight:<?= $i === 0 ? '600' : '400' ?>;padding:0.2rem 0;">
                        <?= $i === 0 ? '' : '— ' ?><?= htmlspecialchars($p) ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <a class="btn btn-logout" href="logout.php">Sair</a>
        </div>
    </div>
</nav>
