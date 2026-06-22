<?php
require_once 'guard.php';

if (empty($_SESSION['guardian_pending_request'])) {
    header('Location: novo_requerimento.php');
    exit;
}

$pending     = $_SESSION['guardian_pending_request'];
$expiresIn   = max(0, $pending['expires'] - time());
$maskedEmail = preg_replace('/(?<=.{2}).(?=[^@]*@)/', '*', $guardianEmail);
$error       = $_GET['error'] ?? '';
$reenviado   = isset($_GET['reenviado']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <script>document.documentElement.setAttribute('data-theme', localStorage.getItem('req_theme') || 'default')</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar Código — IFSC Garopaba</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/themes.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/favicon.ico">
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
</head>
<body class="bg-[#F2F4F8] min-h-screen flex items-center justify-center py-10">

<div class="max-w-md w-full mx-auto px-4">

    <div class="text-center mb-6">
        <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="IFSC" class="h-10 mx-auto mb-4">
        <h1 class="text-xl font-bold text-gray-800">Confirmar envio do requerimento</h1>
        <p class="text-sm text-gray-500 mt-1">
            Enviamos um código de 6 dígitos para<br>
            <strong><?= htmlspecialchars($maskedEmail) ?></strong>
        </p>
    </div>

    <?php if ($error === 'invalido'): ?>
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm font-medium px-4 py-3 rounded-lg">
        Código inválido. Verifique e tente novamente.
    </div>
    <?php elseif ($error === 'expirado'): ?>
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm font-medium px-4 py-3 rounded-lg">
        Código expirado. Clique em "Reenviar código" para receber um novo.
    </div>
    <?php elseif ($error === 'limite'): ?>
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm font-medium px-4 py-3 rounded-lg">
        Muitas tentativas incorretas. Clique em "Reenviar código" para obter um novo código.
    </div>
    <?php elseif ($reenviado): ?>
    <div class="mb-4 bg-green-50 border border-green-200 text-green-700 text-sm font-medium px-4 py-3 rounded-lg">
        Novo código enviado para seu e-mail.
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form method="POST" action="submit_requerimento.php?action=confirmar">
            <?= Csrf::field() ?>
            <div class="mb-5">
                <label class="block text-sm font-medium text-gray-700 mb-2">Código de confirmação</label>
                <input type="text" name="otp_code" required maxlength="6" inputmode="numeric" autofocus
                    autocomplete="one-time-code"
                    class="w-full text-center text-3xl font-mono tracking-[0.5em] bg-gray-50 border border-gray-200 rounded-lg px-4 py-4 focus:ring-2 focus:ring-brand-DEFAULT focus:border-transparent outline-none"
                    placeholder="000000">
                <p class="text-xs text-gray-400 mt-2 text-center">
                    Tempo restante: <span id="countdown"></span>
                </p>
            </div>
            <button type="submit"
                class="w-full py-3 rounded-xl font-bold text-white transition-colors"
                style="background:#1CBB9B;" onmouseover="this.style.background='#169C80'" onmouseout="this.style.background='#1CBB9B'">
                Confirmar e Enviar Requerimento
            </button>
        </form>

        <div class="mt-4 text-center">
            <form method="POST" action="submit_requerimento.php?action=reenviar_otp">
                <?= Csrf::field() ?>
                <button type="submit" class="text-sm text-brand-DEFAULT hover:underline font-medium">
                    Reenviar código
                </button>
            </form>
        </div>
        <div class="mt-2 text-center">
            <a href="novo_requerimento.php" class="text-sm text-gray-400 hover:text-gray-600">
                ← Cancelar e voltar ao formulário
            </a>
        </div>
    </div>

</div>

<script>
let seconds = <?= $expiresIn ?>;
const el = document.getElementById('countdown');
function tick() {
    if (seconds <= 0) {
        el.textContent = 'Expirado';
        el.classList.add('text-red-500', 'font-bold');
        return;
    }
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    el.textContent = m > 0 ? m + 'm ' + String(s).padStart(2, '0') + 's' : s + 's';
    seconds--;
    setTimeout(tick, 1000);
}
tick();
</script>
</body>
</html>
