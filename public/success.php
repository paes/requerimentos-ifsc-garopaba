<?php
/**
 * Pagina de sucesso exibida para o aluno logo apos a conclusao da abertura de um requerimento.
 *
 * @author Prof. Eduardo Gomes
 */
require_once '../config/config.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <script>document.documentElement.setAttribute('data-theme', localStorage.getItem('req_theme') || 'default')</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitação Enviada - IFSC</title>
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/themes.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">

    <header class="theme-header text-white shadow-lg">
        <div class="container mx-auto px-6 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <img src="<?= BASE_URL ?>/assets/img/logob.png" alt="IFSC Logo" class="h-10 brightness-0 invert opacity-90">
                <div class="hidden md:block border-l border-white/30 pl-4">
                    <h1 class="text-lg font-bold tracking-tight">Instituto Federal de Santa Catarina</h1>
                    <p class="text-white/80 text-xs font-medium uppercase tracking-wider">Sistema de Requerimentos</p>
                </div>
            </div>
            <div class="theme-switcher">
                <button class="theme-btn" data-t="default" title="Esmeralda">💎</button>
                <button class="theme-btn" data-t="ifsc"    title="IFSC">🍃</button>
                <button class="theme-btn" data-t="noturno" title="Noturno">🌙</button>
            </div>
        </div>
    </header>

    <main class="flex-grow flex items-center justify-center px-4 py-12">
        <div class="bg-white p-10 rounded-2xl shadow-xl text-center max-w-md w-full border border-gray-100">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-6">
                <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Solicitação Enviada!</h2>
            <p class="text-gray-500 mb-8">Seu requerimento foi registrado com sucesso. Você receberá um e-mail de confirmação em breve.</p>

            <div class="bg-gray-50 border border-gray-200 rounded-xl p-5 mb-8">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Número do Protocolo</p>
                <p class="text-3xl font-mono font-bold text-green-800"><?= htmlspecialchars($_GET['protocol'] ?? 'ERRO') ?></p>
                <p class="text-xs text-gray-400 mt-2">Guarde este número para consultar o andamento do seu pedido.</p>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="<?= BASE_URL ?>/check_status.php"
                   class="px-5 py-2.5 bg-[#1CBB9B] text-white rounded-xl font-semibold hover:bg-[#169C80] transition-all text-sm">
                    Consultar Status
                </a>
                <a href="<?= BASE_URL ?>/index.php"
                   class="px-5 py-2.5 border border-gray-200 text-gray-600 rounded-xl font-semibold hover:bg-gray-50 transition-all text-sm">
                    Voltar ao Início
                </a>
            </div>
        </div>
    </main>

    <footer class="bg-white border-t border-gray-200 py-6 mt-auto">
        <div class="container mx-auto px-4 text-center">
            <p class="text-xs text-gray-400">&copy; <?= date('Y') ?> Instituto Federal de Santa Catarina — Câmpus Garopaba</p>
        </div>
    </footer>

</body>
</html>
