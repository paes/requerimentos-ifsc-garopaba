<?php
/**
 * Componente de layout (Sidebar) contendo o menu de navegacao lateral da area administrativa.
 *
 * @author Prof. Eduardo Gomes
 */
// Determina a pagina ativa para destaque
$currentPage = basename($_SERVER['PHP_SELF']);

// Garante que $isSysAdmin esteja definido
if (!isset($isSysAdmin)) {
    if (isset($conn) && isset($user)) {
        $stmt = $conn->prepare("SELECT is_sysadmin FROM roles WHERE id = :id");
        $roleId = $user['user_role'] ?? $user['role_id'];
        $stmt->execute([':id' => $roleId]);
        $isSysAdmin = $stmt->fetchColumn();
    } else {
        $isSysAdmin = false;
    }
}
?>
<!-- Sidebar -->
<aside class="w-64 bg-white shadow-xl z-40 flex flex-col h-full border-r border-gray-100 hidden md:flex">

    <!-- User Info / Mini Profile in Sidebar (Optional, but good for filling space) -->
    <div class="p-6 border-b border-gray-50">
        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Navegação</p>
    </div>

    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
        <a href="dashboard.php"
            class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-all duration-200 group <?= $currentPage == 'dashboard.php' ? 'bg-brand-DEFAULT/10 text-brand-DEFAULT' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?>">
            <svg class="w-5 h-5 mr-3 <?= $currentPage == 'dashboard.php' ? 'text-brand-DEFAULT' : 'text-gray-400 group-hover:text-gray-500' ?>"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z">
                </path>
            </svg>
            Dashboard
        </a>
        <a href="my_history.php"
            class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-all duration-200 group <?= $currentPage == 'my_history.php' ? 'bg-brand-DEFAULT/10 text-brand-DEFAULT' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?>">
            <svg class="w-5 h-5 mr-3 <?= $currentPage == 'my_history.php' ? 'text-brand-DEFAULT' : 'text-gray-400 group-hover:text-gray-500' ?>"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Meu Histórico
        </a>
        <a href="profile.php"
            class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-all duration-200 group <?= $currentPage == 'profile.php' ? 'bg-brand-DEFAULT/10 text-brand-DEFAULT' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?>">
            <svg class="w-5 h-5 mr-3 <?= $currentPage == 'profile.php' ? 'text-brand-DEFAULT' : 'text-gray-400 group-hover:text-gray-500' ?>"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
            </svg>
            Meu Perfil
        </a>

        <?php if ($isSysAdmin): ?>
            <div class="mt-8 mb-2 px-4">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Gerenciamento</p>
            </div>

            <a href="all_requests.php"
                class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-all duration-200 group <?= $currentPage == 'all_requests.php' ? 'bg-brand-DEFAULT/10 text-brand-DEFAULT' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?>">
                <svg class="w-5 h-5 mr-3 <?= $currentPage == 'all_requests.php' ? 'text-brand-DEFAULT' : 'text-gray-400 group-hover:text-gray-500' ?>"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10">
                    </path>
                </svg>
                Todas as Requisições
            </a>

            <a href="courses.php"
                class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-all duration-200 group <?= $currentPage == 'courses.php' ? 'bg-brand-DEFAULT/10 text-brand-DEFAULT' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?>">
                <svg class="w-5 h-5 mr-3 <?= $currentPage == 'courses.php' ? 'text-brand-DEFAULT' : 'text-gray-400 group-hover:text-gray-500' ?>"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253">
                    </path>
                </svg>
                Cursos
            </a>
            <a href="roles.php"
                class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-all duration-200 group <?= $currentPage == 'roles.php' ? 'bg-brand-DEFAULT/10 text-brand-DEFAULT' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?>">
                <svg class="w-5 h-5 mr-3 <?= $currentPage == 'roles.php' ? 'text-brand-DEFAULT' : 'text-gray-400 group-hover:text-gray-500' ?>"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                    </path>
                </svg>
                Funções
            </a>
            <a href="users.php"
                class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-all duration-200 group <?= $currentPage == 'users.php' ? 'bg-brand-DEFAULT/10 text-brand-DEFAULT' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?>">
                <svg class="w-5 h-5 mr-3 <?= $currentPage == 'users.php' ? 'text-brand-DEFAULT' : 'text-gray-400 group-hover:text-gray-500' ?>"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                    </path>
                </svg>
                Usuários
            </a>
            <a href="workflows.php"
                class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-all duration-200 group <?= $currentPage == 'workflows.php' ? 'bg-brand-DEFAULT/10 text-brand-DEFAULT' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?>">
                <svg class="w-5 h-5 mr-3 <?= $currentPage == 'workflows.php' ? 'text-brand-DEFAULT' : 'text-gray-400 group-hover:text-gray-500' ?>"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Workflows
            </a>
            <a href="request_types.php"
                class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-all duration-200 group <?= $currentPage == 'request_types.php' ? 'bg-brand-DEFAULT/10 text-brand-DEFAULT' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?>">
                <svg class="w-5 h-5 mr-3 <?= $currentPage == 'request_types.php' ? 'text-brand-DEFAULT' : 'text-gray-400 group-hover:text-gray-500' ?>"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                    </path>
                </svg>
                Tipos de Requisição
            </a>
            <a href="email_config.php"
                class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-all duration-200 group <?= $currentPage == 'email_config.php' ? 'bg-brand-DEFAULT/10 text-brand-DEFAULT' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?>">
                <svg class="w-5 h-5 mr-3 <?= $currentPage == 'email_config.php' ? 'text-brand-DEFAULT' : 'text-gray-400 group-hover:text-gray-500' ?>"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                    </path>
                </svg>
                Configuração de Email
            </a>
        <?php endif; ?>

        <?php if (isset($user) && (($user['user_role'] ?? $user['role_id']) == 2 || $isSysAdmin)): ?>
            <div class="mt-8 mb-2 px-4">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Relatórios</p>
            </div>
            <a href="absence_report.php"
                class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-all duration-200 group <?= $currentPage == 'absence_report.php' ? 'bg-brand-DEFAULT/10 text-brand-DEFAULT' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?>">
                <svg class="w-5 h-5 mr-3 <?= $currentPage == 'absence_report.php' ? 'text-brand-DEFAULT' : 'text-gray-400 group-hover:text-gray-500' ?>"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                    </path>
                </svg>
                Justificativas de Faltas
            </a>
            <a href="absence_report_teacher.php"
                class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-all duration-200 group <?= $currentPage == 'absence_report_teacher.php' ? 'bg-brand-DEFAULT/10 text-brand-DEFAULT' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?>">
                <svg class="w-5 h-5 mr-3 <?= $currentPage == 'absence_report_teacher.php' ? 'text-brand-DEFAULT' : 'text-gray-400 group-hover:text-gray-500' ?>"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                    </path>
                </svg>
                Justificativas de Faltas (Professor)
            </a>
            <a href="schedule_report.php"
                class="flex items-center px-4 py-3 rounded-lg text-sm font-medium transition-all duration-200 group <?= $currentPage == 'schedule_report.php' ? 'bg-brand-DEFAULT/10 text-brand-DEFAULT' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?>">
                <svg class="w-5 h-5 mr-3 <?= $currentPage == 'schedule_report.php' ? 'text-brand-DEFAULT' : 'text-gray-400 group-hover:text-gray-500' ?>"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Horários Diferenciados
            </a>
        <?php endif; ?>
    </nav>

    <div class="p-4 border-t border-gray-100">
        <a href="logout.php"
            class="flex items-center px-4 py-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors text-sm font-medium">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                </path>
            </svg>
            Sair do Sistema
        </a>
    </div>
</aside>