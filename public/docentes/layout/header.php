<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($user) && class_exists('Auth')) {
    $user = Auth::user();
}
?>
<!DOCTYPE html>
<html lang="pt-BR"><head>
    <script>document.documentElement.setAttribute('data-theme', localStorage.getItem('req_theme') || 'default')</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' - IFSC Docentes' : 'Portal Docente - IFSC' ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/themes.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/favicon.ico" type="image/x-icon">
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
</head>

<body class="bg-[#F2F4F8] flex h-screen overflow-hidden">

    <header class="fixed top-0 left-0 right-0 py-1 theme-header shadow-md z-50 flex items-center justify-between px-6 transition-all duration-300 print:hidden">
        <div class="flex items-center w-64">
            <button id="mobile-menu-btn" class="md:hidden mr-4 text-white hover:text-white/80 focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            <a href="dashboard.php" class="flex items-center">
                <img src="<?= BASE_URL ?>/assets/img/logob.png" alt="IFSC" class="h-14 brightness-0 invert">
                <div class="h-8 w-px bg-white/30 mx-4"></div>
                <span class="text-white font-light text-xl tracking-tight">Portal Docente</span>
            </a>
        </div>

        <div class="flex items-center gap-4">
            <div class="theme-switcher">
                <button class="theme-btn" data-t="default" title="Esmeralda">💎</button>
                <button class="theme-btn" data-t="ifsc"    title="IFSC">🍃</button>
                <button class="theme-btn" data-t="noturno" title="Noturno">🌙</button>
            </div>
            <div class="flex items-center gap-3 text-white">
                <div class="text-right hidden sm:block">
                    <p class="text-sm font-bold leading-tight"><?= htmlspecialchars($teacherName ?? $user['user_name'] ?? 'Docente') ?></p>
                    <p class="text-xs opacity-80">Portal Docente</p>
                </div>
                <div class="h-9 w-9 rounded-full bg-white/20 flex items-center justify-center text-white font-bold border border-white/30">
                    <?= strtoupper(substr($teacherName ?? $user['user_name'] ?? 'D', 0, 1)) ?>
                </div>
            </div>
        </div>
    </header>

    <div class="flex pt-16 h-screen w-full relative">
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const menuBtn = document.getElementById('mobile-menu-btn');
                const sidebar = document.querySelector('aside');
                if (menuBtn && sidebar) {
                    menuBtn.addEventListener('click', function () {
                        sidebar.classList.toggle('hidden');
                        sidebar.classList.toggle('absolute');
                        sidebar.classList.toggle('inset-y-0');
                        sidebar.classList.toggle('left-0');
                        sidebar.classList.toggle('z-40');
                        sidebar.classList.toggle('w-64');
                        if (sidebar.classList.contains('absolute')) {
                            sidebar.style.height = 'calc(100vh - 4rem)';
                            sidebar.style.top = '0';
                        } else {
                            sidebar.style.height = '';
                            sidebar.style.top = '';
                        }
                    });
                }
            });
        </script>
