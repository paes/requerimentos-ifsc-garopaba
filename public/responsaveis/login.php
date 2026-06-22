<?php
require_once '../../config/database.php';
require_once '../../config/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Já logado → redireciona
if (!empty($_SESSION['guardian_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    // Rate limiting simples por sessão
    $_SESSION['guardian_login_attempts'] = ($_SESSION['guardian_login_attempts'] ?? 0) + 1;
    if ($_SESSION['guardian_login_attempts'] > 10) {
        $error = 'Muitas tentativas. Feche o navegador e tente novamente mais tarde.';
    } else {
        $db   = new Database();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("SELECT * FROM guardians WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $guardian = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($guardian && $guardian['active'] && password_verify($password, $guardian['password'])) {
            // Login bem-sucedido — zera contador
            unset($_SESSION['guardian_login_attempts']);

            // Previne fixação de sessão: novo ID ao autenticar
            session_regenerate_id(true);

            $_SESSION['guardian_id']              = $guardian['id'];
            $_SESSION['guardian_name']            = $guardian['name'];
            $_SESSION['guardian_email']           = $guardian['email'];
            $_SESSION['guardian_must_change_pw']  = (bool)$guardian['must_change_password'];

            if ($guardian['must_change_password']) {
                header('Location: trocar_senha.php');
            } else {
                header('Location: dashboard.php');
            }
            exit;
        } else {
            $error = 'E-mail ou senha incorretos, ou acesso ainda não liberado.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <script>document.documentElement.setAttribute('data-theme', localStorage.getItem('req_theme') || 'default')</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal de Responsáveis — IFSC Garopaba</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/themes.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/favicon.ico" type="image/x-icon">
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
</head>
<body class="bg-[#F2F4F8] flex items-center justify-center min-h-screen">
<div class="bg-white p-10 rounded-2xl shadow-2xl w-full max-w-sm">
    <div class="text-center mb-8">
        <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="IFSC Logo" class="h-12 mx-auto mb-4">
        <h2 class="text-2xl font-bold text-gray-800 tracking-tight">Portal de Responsáveis</h2>
        <p class="text-sm text-gray-500 mt-1">IFSC Câmpus Garopaba</p>
    </div>

    <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-5 text-sm font-medium">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-5">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
            <input type="email" name="email" required autocomplete="username"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-brand-DEFAULT focus:border-transparent outline-none text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Senha</label>
            <input type="password" name="password" required autocomplete="current-password"
                class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-brand-DEFAULT focus:border-transparent outline-none text-sm">
        </div>
        <button type="submit" id="btn-submit"
            class="w-full bg-brand-DEFAULT text-white py-3.5 rounded-lg hover:bg-brand-dark transition-all font-bold shadow-md">
            Entrar
        </button>
    </form>

    <div class="mt-6 text-center space-y-2 text-sm">
        <p class="text-gray-500">Ainda não tem acesso?
            <a href="cadastro.php" class="text-brand-DEFAULT hover:underline font-medium">Solicitar cadastro</a>
        </p>
        <a href="<?= BASE_URL ?>/index.php" class="block text-gray-400 hover:text-gray-600 transition-colors">
            Voltar ao site
        </a>
    </div>

    <div class="mt-6 flex justify-center">
        <div class="theme-switcher">
            <button class="theme-btn text-gray-400" data-t="default" title="Esmeralda">💎</button>
            <button class="theme-btn text-gray-400" data-t="ifsc" title="IFSC">🍃</button>
            <button class="theme-btn text-gray-400" data-t="noturno" title="Noturno">🌙</button>
        </div>
    </div>
</div>
<script>
document.querySelector('form').addEventListener('submit', function () {
    const btn = document.getElementById('btn-submit');
    btn.disabled = true;
    btn.textContent = 'Verificando...';
});
</script>
</body>
</html>
