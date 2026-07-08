<?php
// logout.php — Termina a sessão do utilizador e redireciona para o login
session_start();
session_unset();    // Remove todas as variáveis de sessão
session_destroy();  // Destrói a sessão
header('Location: index.php');
exit;
?>
