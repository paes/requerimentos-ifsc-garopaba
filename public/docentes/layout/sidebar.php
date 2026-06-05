<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="w-64 bg-white shadow-xl z-40 flex flex-col h-full border-r border-gray-100 hidden md:flex">

    <div class="p-6 border-b border-gray-50">
        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Navegação</p>
    </div>

    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
        <a href="dashboard.php"
            class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-all duration-200 group <?= $currentPage == 'dashboard.php' ? 'bg-brand-DEFAULT/10 text-brand-DEFAULT' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?>">
            <svg class="w-5 h-5 mr-3 <?= $currentPage == 'dashboard.php' ? 'text-brand-DEFAULT' : 'text-gray-400 group-hover:text-gray-500' ?>"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
            Minhas Solicitações
        </a>
        <a href="novo.php"
            class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-all duration-200 group <?= $currentPage == 'novo.php' ? 'bg-brand-DEFAULT/10 text-brand-DEFAULT' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?>">
            <svg class="w-5 h-5 mr-3 <?= $currentPage == 'novo.php' ? 'text-brand-DEFAULT' : 'text-gray-400 group-hover:text-gray-500' ?>"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 4v16m8-8H4"></path>
            </svg>
            Nova Solicitação
        </a>
    </nav>

    <div class="p-4 border-t border-gray-100">
        <a href="logout.php"
            class="flex items-center px-4 py-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors text-sm font-medium">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
            </svg>
            Sair
        </a>
    </div>
</aside>
