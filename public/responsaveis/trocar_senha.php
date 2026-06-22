<?php
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../src/Csrf.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['guardian_id'])) {
    header('Location: login.php');
    exit;
}

$db   = new Database();
$conn = $db->getConnection();
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $current  = $_POST['current_password'] ?? '';
    $new      = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    $stmt = $conn->prepare("SELECT password FROM guardians WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $_SESSION['guardian_id']]);
    $hash = $stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        $error = 'Senha atual incorreta.';
    } elseif (strlen($new) < 8) {
        $error = 'A nova senha deve ter ao menos 8 caracteres.';
    } elseif ($new !== $confirm) {
        $error = 'As senhas não coincidem.';
    } elseif ($current === $new) {
        $error = 'A nova senha deve ser diferente da senha atual.';
    } else {
        $newHash = password_hash($new, PASSWORD_BCRYPT);
        $upd = $conn->prepare("UPDATE guardians SET password = :pw, must_change_password = 0 WHERE id = :id");
        $upd->execute([':pw' => $newHash, ':id' => $_SESSION['guardian_id']]);
        $_SESSION['guardian_must_change_pw'] = false;
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <script>document.documentElement.setAttribute('data-theme', localStorage.getItem('req_theme') || 'default')</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trocar Senha — Portal de Responsáveis</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/themes.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/favicon.ico">
</head>
<body class="bg-[#F2F4F8] flex items-center justify-center min-h-screen">
<div class="bg-white p-10 rounded-2xl shadow-2xl w-full max-w-sm">
    <div class="text-center mb-6">
        <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="IFSC Logo" class="h-10 mx-auto mb-3">
        <h2 class="text-xl font-bold text-gray-800">Criar nova senha</h2>
        <?php if (!empty($_SESSION['guardian_must_change_pw'])): ?>
        <p class="text-xs text-amber-600 mt-1 font-medium">Você precisa definir uma senha pessoal antes de continuar.</p>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-4 text-sm">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
        <?= Csrf::field() ?>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Senha atual (temporária)</label>
            <input type="password" name="current_password" required autocomplete="current-password"
                class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-brand-DEFAULT outline-none text-sm">
            <p class="text-xs text-gray-400 mt-1">Os 5 primeiros dígitos do seu CPF.</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nova senha</label>
            <input type="password" name="new_password" required minlength="8" autocomplete="new-password"
                class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-brand-DEFAULT outline-none text-sm">
            <p class="text-xs text-gray-400 mt-1">Mínimo de 8 caracteres.</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar nova senha</label>
            <input type="password" name="confirm_password" required autocomplete="new-password"
                class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-brand-DEFAULT outline-none text-sm">
        </div>
        <button type="submit"
            class="w-full bg-brand-DEFAULT text-white py-3.5 rounded-lg hover:bg-brand-dark transition-all font-bold mt-2">
            Definir senha e entrar
        </button>
    </form>
</div>
</body>
</html>
