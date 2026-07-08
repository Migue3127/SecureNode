<?php
// Inicia a sessão para gerir autenticação
session_start();

// Se já está autenticado, redireciona para o dashboard
if (isset($_SESSION['autenticado']) && $_SESSION['autenticado'] === true) {
    header('Location: dashboard.php');
    exit;
}

$erro = '';

// Processa o formulário de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['username']) && isset($_POST['password'])) {

        $username_input = trim($_POST['username']);
        $password_input = $_POST['password'];

        // Lê o ficheiro de utilizadores (formato: email|hash|role)
        $linhas = file(__DIR__ . '/config/utilizadores.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $autenticado = false;

        foreach ($linhas as $linha) {
            $partes = explode('|', $linha);
            if (count($partes) === 3) {
                list($email, $hash, $role) = $partes;
                // Verifica username e password com hash
                if ($email === $username_input && password_verify($password_input, trim($hash))) {
                    $_SESSION['autenticado'] = true;
                    $_SESSION['username'] = $email;
                    $_SESSION['role'] = trim($role);
                    $autenticado = true;
                    header('Location: dashboard.php');
                    exit;
                }
            }
        }

        if (!$autenticado) {
            $erro = 'Credenciais inválidas. Tente novamente.';
        }
    }
}
?>
<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SecureNode — Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-body">

<div class="login-wrapper">
    <div class="login-card">

        <!-- Logo / Título -->
        <div class="login-header">
            <div class="login-icon">🛡️</div>
            <h1 class="login-title">SecureNode</h1>
            <p class="login-subtitle">Sistema de Vigilância Inteligente</p>
        </div>

        <!-- Mensagem de erro -->
        <?php if (!empty($erro)): ?>
            <div class="alert alert-danger alert-sm" role="alert">
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <!-- Formulário de login -->
        <form method="post">
            <div class="mb-3">
                <label for="username" class="form-label label-custom">Email</label>
                <input type="email"
                       class="form-control input-custom"
                       id="username"
                       name="username"
                       placeholder="utilizador@vigilancia.pt"
                       required
                       autocomplete="email">
            </div>
            <div class="mb-4">
                <label for="password" class="form-label label-custom">Password</label>
                <input type="password"
                       class="form-control input-custom"
                       id="password"
                       name="password"
                       placeholder="••••••••"
                       required
                       autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-login w-100">
                Entrar no Sistema
            </button>
        </form>

        <p class="login-footer-text">
            Acesso restrito a utilizadores autorizados
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
