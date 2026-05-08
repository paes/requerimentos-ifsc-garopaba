<?php
/**
 * Pagina de login para a area administrativa do sistema.
 *
 * @author Prof. Eduardo Gomes
 */
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../src/Auth.php';
require_once '../../src/Helpers.php';

require_once '../../src/Auth.php';

// session_start() e chamado no Auth.php, entao confiamos nisso.
// Mas para ser explicito e seguro:
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'error') {
    $error = 'Erro na autenticação. Verifique suas credenciais.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();

    $email = $_POST['email'];
    $password = $_POST['password'];

    // Verificacoes Anti-Bot (Cloudflare Turnstile)
    if (ENABLE_TURNSTILE) {
        $cf_secret = TURNSTILE_SECRET_KEY;
        $cf_response = $_POST['cf-turnstile-response'] ?? '';

        $verification = Helpers::verifyTurnstile($cf_response, $cf_secret, $_SERVER['REMOTE_ADDR']);

        if (!$verification['success']) {
            $error = 'Falha na verificação de segurança. Tente novamente.';
        } elseif (Auth::login($email, $password, $conn)) {
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Usuário ou senha incorretos.';
        }
    } else {
        if (Auth::login($email, $password, $conn)) {
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Usuário ou senha incorretos.';
        }
    }
}


?>
<!DOCTYPE html>
<html lang="pt-BR"><head>
    <script>document.documentElement.setAttribute('data-theme', localStorage.getItem('req_theme') || 'default')</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrativo - IFSC</title>
    <?php if (ENABLE_TURNSTILE): ?>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/themes.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/favicon.ico" type="image/x-icon">
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-bg { background: #F2F4F8; }
    </style>
</head>

<body class="gradient-bg flex items-center justify-center h-screen">
    <div class="bg-white p-10 rounded-lg shadow-2xl w-full max-w-sm transform transition-all hover:scale-[1.01]">
        <div class="text-center mb-8">
            <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="IFSC Logo" class="h-12 mx-auto mb-4">
            <h2 class="text-2xl font-bold text-gray-800 tracking-tight">Acesso Administrativo</h2>
            <p class="text-sm text-gray-500 mt-1">Entre com suas credenciais</p>
        </div>

        <?php if (isset($message)): ?>
            <div
                class="bg-green-50 border border-green-200 text-green-600 px-4 py-3 rounded-md mb-6 text-sm font-medium text-center">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div
                class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-md mb-6 text-sm font-medium text-center">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= BASE_URL ?>/admin/index.php" class="space-y-5">
            <div class="group">
                <label class="block text-sm font-medium text-gray-700 mb-2">Usuário</label>
                <input type="text" name="email" required placeholder="Seu usuário sem o @ifsc.edu.br"
                    class="w-full bg-gray-50 border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-brand-DEFAULT focus:border-transparent transition-all outline-none">
            </div>
            <div class="group">
                <label class="block text-sm font-medium text-gray-700 mb-2">Senha</label>
                <input type="password" name="password" required
                    class="w-full bg-gray-50 border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-brand-DEFAULT focus:border-transparent transition-all outline-none">
            </div>


            <!-- Turnstile Widget -->
            <?php if (ENABLE_TURNSTILE): ?>
                <div class="cf-turnstile" data-sitekey="<?= TURNSTILE_SITE_KEY ?>"></div>
            <?php endif; ?>

            <button type="submit" id="submit_btn"
                class="w-full bg-[#1CBB9B] text-white py-3.5 rounded-md hover:bg-[#169C80] transition-all font-bold shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 mt-2">Entrar</button>
            <p class="mt-4 text-xs text-center text-gray-500">
                Protegido pelo Cloudflare Turnstile.
            </p>
        </form>

        <div class="mt-8 text-center">
            <a href="<?= BASE_URL ?>/index.php" class="text-sm text-gray-400 hover:text-brand-DEFAULT transition-colors">Voltar para
                o site</a>
        </div>
        <div class="mt-4 flex justify-center">
            <div class="theme-switcher">
                <button class="theme-btn text-gray-400" data-t="default" title="Esmeralda">💎</button>
                <button class="theme-btn text-gray-400" data-t="ifsc"    title="IFSC">🍃</button>
                <button class="theme-btn text-gray-400" data-t="noturno" title="Noturno">🌙</button>
            </div>
        </div>
    </div>

    <script>
        document.querySelector('form').addEventListener('submit', function (e) {
            var btn = document.getElementById('submit_btn');
            btn.disabled = true;
            btn.textContent = 'Verificando...';
        });
    </script>
</body>

</html>