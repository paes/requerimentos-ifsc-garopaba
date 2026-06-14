<?php
require_once '../../config/database.php';
require_once '../../config/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$protocol = htmlspecialchars($_GET['p'] ?? '');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro Enviado — IFSC Garopaba</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/themes.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-[#F2F4F8] min-h-screen flex items-center justify-center">
<div class="max-w-md w-full mx-auto px-4">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <h1 class="text-xl font-bold text-gray-800 mb-2">Cadastro enviado!</h1>
        <?php if ($protocol): ?>
        <p class="text-sm text-gray-500 mb-4">Protocolo: <strong class="text-gray-800"><?= $protocol ?></strong></p>
        <?php endif; ?>
        <p class="text-sm text-gray-600 mb-6">
            Seu cadastro foi recebido e será analisado pela <strong>Coordenação Pedagógica</strong>.
            Você receberá um e-mail com o resultado. O prazo habitual é de até <strong>3 dias úteis</strong>.
        </p>
        <a href="<?= BASE_URL ?>/index.php"
            class="inline-block text-sm text-brand-DEFAULT hover:underline font-medium">
            Voltar ao site
        </a>
    </div>
</div>
</body>
</html>
