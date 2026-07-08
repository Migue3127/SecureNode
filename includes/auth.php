<?php
// Incluído no topo de todas as páginas protegidas (dashboard, histórico, imagens).
// Redireciona para index.php se não há sessão válida ou se expirou por inatividade.

date_default_timezone_set('Europe/Lisbon');
session_start();
if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    header('Location: index.php');
    exit;
}
// 1 hora sem atividade = sessão expirada (evita sessões "esquecidas" abertas em PCs partilhados)
if (isset($_SESSION['ultimo_acesso']) && time() - $_SESSION['ultimo_acesso'] > 3600) {
    session_unset(); session_destroy();
    header('Location: index.php');
    exit;
}
$_SESSION['ultimo_acesso'] = time();
