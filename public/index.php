<?php
/**
 * Pagina inicial do sistema (publica) onde os alunos preenchem o formulario para abrir novos requerimentos.
 *
 * @author Prof. Eduardo Gomes
 */
session_start();
require_once '../config/database.php';
require_once '../config/config.php';

$db = new Database();
$conn = $db->getConnection();

// Busca Cursos
$coursesQuery = "SELECT id, name, level FROM courses WHERE active = 1 ORDER BY name";
$coursesStmt = $conn->prepare($coursesQuery);
$coursesStmt->execute();
$courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);

// Busca Niveis
$levelsQuery = "SELECT DISTINCT level FROM courses WHERE active = 1 ORDER BY level";
$levelsStmt = $conn->prepare($levelsQuery);
$levelsStmt->execute();
$levels = $levelsStmt->fetchAll(PDO::FETCH_COLUMN);

// Busca Tipos de Requerimento
$typesQuery = "SELECT id, name, information, attention, featured FROM request_types WHERE active = 1 ORDER BY featured DESC, name ASC";
$typesStmt = $conn->prepare($typesQuery);
$typesStmt->execute();
$requestTypes = $typesStmt->fetchAll(PDO::FETCH_ASSOC);
$featuredTypes = array_filter($requestTypes, fn($t) => !empty($t['featured']));
$otherTypes    = array_filter($requestTypes, fn($t) =>  empty($t['featured']));

// Busca Disciplinas e Docentes
$subjects = $conn->query("SELECT id, course_id, name, period FROM subjects WHERE active=1 ORDER BY course_id, period, name")->fetchAll(PDO::FETCH_ASSOC);
$teachers = $conn->query("SELECT id, name FROM teachers WHERE active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$subjectsJson = json_encode($subjects, JSON_UNESCAPED_UNICODE);
$teachersJson = json_encode($teachers, JSON_UNESCAPED_UNICODE);

// Logo em base64 para geracao de PDF no cliente
$logoPath = __DIR__ . '/assets/img/logo.png';
$logoBase64 = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : '';
?>
<!DOCTYPE html>
<html lang="pt-BR"><head>
    <script>document.documentElement.setAttribute('data-theme', localStorage.getItem('req_theme') || 'default')</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IFSC - Sistema de Requerimentos</title>
    <?php if (ENABLE_TURNSTILE): ?>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/themes.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/favicon.ico" type="image/x-icon">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" defer></script>
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
    <style>
        .ms-item { transition: background .15s, border-color .15s, color .15s; }
        .ms-item:not(.ms-selected):hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
        .ms-item.ms-selected { background: var(--primary); border-color: var(--primary); color: #fff; font-weight: 500; }
        .ms-item.ms-selected:hover { background: var(--primary-dark); }
    </style>
</head>

<body class="gradient-bg min-h-screen text-gray-800">

    <!-- Header -->
    <header class="theme-header text-white shadow-lg">
        <div class="container mx-auto px-6 py-2 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <img src="<?= BASE_URL ?>/assets/img/logob.png" alt="IFSC Logo"
                    class="h-14 brightness-0 invert opacity-90 hover:opacity-100 transition-opacity">
                <div class="hidden md:block border-l border-white/30 pl-4">
                    <h1 class="text-lg font-bold tracking-tight">Instituto Federal de Santa Catarina</h1>
                    <p class="text-white/80 text-xs font-medium uppercase tracking-wider">Sistema de Requerimentos</p>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <div class="theme-switcher">
                    <button class="theme-btn" data-t="default" title="Esmeralda">💎</button>
                    <button class="theme-btn" data-t="ifsc"    title="IFSC">🍃</button>
                    <button class="theme-btn" data-t="noturno" title="Noturno">🌙</button>
                </div>
                <a href="<?= BASE_URL ?>/check_status.php"
                    class="text-sm font-medium text-white/90 hover:text-white transition-colors">Consultar Protocolo</a>
                <a href="<?= BASE_URL ?>/admin/index.php"
                    class="px-4 py-2 bg-white/20 hover:bg-white/30 rounded-full text-sm font-medium transition-all backdrop-blur-sm">Área
                    Administrativa</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-12 max-w-4xl">

        <div class="text-center mb-10">
            <h2 class="text-4xl font-extrabold text-gray-900 mb-2 tracking-tight">Faça seu Requerimento</h2>
            <p class="text-lg text-gray-600">Preencha o formulário abaixo para iniciar sua solicitação acadêmica.</p>
        </div>

        <!-- Progress Bar -->
        <div class="max-w-4xl mx-auto mb-8 px-4">
            <div class="relative flex justify-between items-center">
                <div class="absolute top-1/2 left-0 w-full h-0.5 bg-gray-200 -translate-y-1/2 -z-10"></div>
                <div id="progress-line"
                    class="absolute top-1/2 left-0 w-0 h-0.5 bg-[#1CBB9B] -translate-y-1/2 -z-10 transition-all duration-500">
                </div>

                <div class="relative flex flex-col items-center">
                    <div id="step-dot-1"
                        class="w-10 h-10 rounded-full bg-[#1CBB9B] text-white flex items-center justify-center font-bold shadow-lg transition-all duration-300">
                        1</div>
                    <span class="absolute top-10 mt-2 left-1/2 -translate-x-1/2 w-max text-[10px] md:text-xs font-bold text-[#1CBB9B] uppercase tracking-wider">Identificação</span>
                </div>
                <div class="relative flex flex-col items-center">
                    <div id="step-dot-2"
                        class="w-10 h-10 rounded-full bg-white border-2 border-gray-200 text-gray-400 flex items-center justify-center font-bold shadow-sm transition-all duration-300">
                        2</div>
                    <span class="absolute top-10 mt-2 left-1/2 -translate-x-1/2 w-max text-[10px] md:text-xs font-bold text-gray-400 uppercase tracking-wider">Solicitação</span>
                </div>
                <div class="relative flex flex-col items-center">
                    <div id="step-dot-3"
                        class="w-10 h-10 rounded-full bg-white border-2 border-gray-200 text-gray-400 flex items-center justify-center font-bold shadow-sm transition-all duration-300">
                        3</div>
                    <span class="absolute top-10 mt-2 left-1/2 -translate-x-1/2 w-max text-[10px] md:text-xs font-bold text-gray-400 uppercase tracking-wider">Detalhes</span>
                </div>
                <div class="relative flex flex-col items-center">
                    <div id="step-dot-4"
                        class="w-10 h-10 rounded-full bg-white border-2 border-gray-200 text-gray-400 flex items-center justify-center font-bold shadow-sm transition-all duration-300">
                        4</div>
                    <span class="absolute top-10 mt-2 left-1/2 -translate-x-1/2 w-max text-[10px] md:text-xs font-bold text-gray-400 uppercase tracking-wider">Anexos</span>
                </div>
            </div>
        </div>

        <div class="glass rounded-2xl shadow-xl p-8 md:p-10 border border-white/50">
            <form id="requestForm" action="<?= BASE_URL ?>/submit_request.php" method="POST"
                enctype="multipart/form-data" class="space-y-8">

                <!-- ETAPA 1: DADOS PESSOAIS -->
                <div id="form-step-1" class="space-y-8 animate-fade-in">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-800 mb-6 flex items-center">
                            <span
                                class="bg-[#1CBB9B] text-white rounded-full w-8 h-8 flex items-center justify-center text-sm mr-3">1</span>
                            Dados Pessoais
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="group">
                                <label
                                    class="block text-sm font-medium text-gray-700 mb-2 group-focus-within:text-[#1CBB9B] transition-colors">Nome
                                    Completo <span class="text-red-500">*</span></label>
                                <input type="text" name="student_name" id="student_name" required
                                    class="w-full bg-gray-50 border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent transition-all outline-none"
                                    placeholder="Seu nome completo">
                            </div>
                            <div class="group">
                                <label
                                    class="block text-sm font-medium text-gray-700 mb-2 group-focus-within:text-[#1CBB9B] transition-colors">E-mail
                                    Institucional <span class="text-red-500">*</span></label>
                                <input type="email" name="student_email" id="student_email" required
                                    class="w-full bg-gray-50 border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent transition-all outline-none"
                                    placeholder="seu.email@aluno.ifsc.edu.br">
                            </div>
                            <div class="group md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label
                                        class="block text-sm font-medium text-gray-700 mb-2 group-focus-within:text-[#1CBB9B] transition-colors">Telefone
                                        / WhatsApp
                                        <span class="text-red-500">*</span></label>
                                    <input type="tel" name="student_phone" id="student_phone" required
                                        class="w-full bg-gray-50 border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent transition-all outline-none"
                                        placeholder="(DD) 9XXXX-XXXX">
                                </div>
                                <div>
                                    <label
                                        class="block text-sm font-medium text-gray-700 mb-2 group-focus-within:text-[#1CBB9B] transition-colors">Matrícula
                                        <span class="text-red-500">*</span></label>
                                    <input type="text" name="student_id" id="student_id" required
                                        class="w-full bg-gray-50 border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent transition-all outline-none"
                                        placeholder="Número da matrícula">
                                </div>
                                <div>
                                    <label
                                        class="block text-sm font-medium text-gray-700 mb-2 group-focus-within:text-[#1CBB9B] transition-colors">Nível
                                        <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <select id="level_select" required
                                            class="w-full bg-gray-50 border border-gray-200 rounded-md pl-4 pr-10 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent transition-all outline-none appearance-none bg-none">
                                            <option value="">Selecione o nível...</option>
                                            <?php foreach ($levels as $level): ?>
                                                <option value="<?= $level ?>"><?= ucfirst($level) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div
                                            class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-500">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label
                                        class="block text-sm font-medium text-gray-700 mb-2 group-focus-within:text-[#1CBB9B] transition-colors">Curso
                                        <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <select name="course_id" id="course_select" required
                                            class="w-full bg-gray-50 border border-gray-200 rounded-md pl-4 pr-10 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent transition-all outline-none appearance-none bg-none"
                                            disabled>
                                            <option value="">Selecione o nível primeiro...</option>
                                            <?php foreach ($courses as $course): ?>
                                                <option value="<?= $course['id'] ?>" data-level="<?= $course['level'] ?>"
                                                    style="display:none;"><?= htmlspecialchars($course['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div
                                            class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-500">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label
                                        class="block text-sm font-medium text-gray-700 mb-2 group-focus-within:text-[#1CBB9B] transition-colors">Turma</label>
                                    <input type="text" name="class_info"
                                        class="w-full bg-gray-50 border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent transition-all outline-none"
                                        placeholder="Ex: <?= (date('Y')-2) . ', ' . (date('Y')-1) . ', ' . date('Y') ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Menor de Idade -->
                    <div class="bg-blue-50/50 rounded-md p-6 border border-blue-100">
                        <div class="flex items-center">
                            <input type="checkbox" id="is_adult" name="is_adult"
                                class="h-5 w-5 text-[#1CBB9B] focus:ring-[#1CBB9B] border-gray-300 rounded transition-all">
                            <label for="is_adult" class="ml-3 block text-sm font-medium text-gray-700 select-none">
                                Declaro que sou maior de 18 anos
                            </label>
                        </div>

                        <div id="guardian_fields"
                            class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6 animate-fade-in-down">
                            <div class="group">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nome do Responsável <span
                                        class="text-red-500">*</span></label>
                                <input type="text" name="guardian_name" id="guardian_name"
                                    class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent transition-all outline-none">
                            </div>
                            <div class="group">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Telefone do Responsável
                                    <span class="text-red-500">*</span></label>
                                <input type="text" name="guardian_phone" id="guardian_phone"
                                    class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent transition-all outline-none">
                            </div>
                        </div>
                    </div>

                    <div class="pt-6 border-t border-gray-100 flex justify-end">
                        <button type="button" onclick="goToStep(2)"
                            class="bg-[#1CBB9B] text-white px-10 py-3 rounded-md font-bold hover:bg-[#169C80] transition-all shadow-md hover:shadow-lg flex items-center">
                            Próximo Passo
                            <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7">
                                </path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- ETAPA 2: SELEÇÃO DO TIPO -->
                <div id="form-step-2" class="hidden space-y-8 animate-fade-in">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-800 mb-6 flex items-center">
                            <span class="bg-[#1CBB9B] text-white rounded-full w-8 h-8 flex items-center justify-center text-sm mr-3">2</span>
                            Tipo de Requerimento
                        </h3>
                        <div class="space-y-6">
                            <!-- Search Box -->
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </span>
                                <input type="text" id="type_search" placeholder="Comece a digitar para encontrar..."
                                    class="w-full bg-white border border-gray-200 rounded-xl pl-10 pr-4 py-3 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent transition-all outline-none shadow-sm text-sm">
                            </div>

                            <?php if (!empty($featuredTypes)): ?>
                            <!-- Mais utilizadas -->
                            <div id="section-featured">
                                <p class="text-xs font-bold text-[#1CBB9B] uppercase tracking-wider mb-2">⭐ Mais utilizadas</p>
                                <div id="types_grid_featured" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 p-1 border border-transparent rounded-xl transition-all">
                                    <?php foreach ($featuredTypes as $type): ?>
                                        <div class="type-card cursor-pointer group border border-[#1CBB9B]/30 bg-[#1CBB9B]/5 rounded-xl p-3 hover:shadow-md hover:-translate-y-0.5 transition-all flex items-center min-h-[70px] relative overflow-hidden"
                                            data-id="<?= $type['id'] ?>"
                                            data-name="<?= strtolower(htmlspecialchars($type['name'])) ?>">
                                            <div class="absolute left-0 top-0 w-1 h-full bg-[#1CBB9B] opacity-0 group-[.selected]:opacity-100 transition-opacity"></div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs sm:text-sm font-semibold text-gray-700 group-hover:text-[#1CBB9B] group-[.selected]:text-[#1CBB9B] transition-colors leading-tight line-clamp-2">
                                                    <?= htmlspecialchars($type['name']) ?>
                                                </p>
                                            </div>
                                            <div class="hidden group-[.selected]:block ml-2 text-[#1CBB9B]">
                                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                </svg>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Outras solicitações -->
                            <div id="section-other">
                                <?php if (!empty($featuredTypes)): ?>
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Outras solicitações</p>
                                <?php endif; ?>
                                <div id="types_grid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 p-1 border border-transparent rounded-xl transition-all">
                                    <?php foreach ($otherTypes as $type): ?>
                                        <div class="type-card cursor-pointer group border rounded-xl p-3 hover:shadow-md hover:-translate-y-0.5 transition-all flex items-center min-h-[70px] relative overflow-hidden"
                                            data-id="<?= $type['id'] ?>"
                                            data-name="<?= strtolower(htmlspecialchars($type['name'])) ?>">
                                            <div class="absolute left-0 top-0 w-1 h-full bg-[#1CBB9B] opacity-0 group-[.selected]:opacity-100 transition-opacity"></div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs sm:text-sm font-semibold text-gray-700 group-hover:text-[#1CBB9B] group-[.selected]:text-[#1CBB9B] transition-colors leading-tight line-clamp-2">
                                                    <?= htmlspecialchars($type['name']) ?>
                                                </p>
                                            </div>
                                            <div class="hidden group-[.selected]:block ml-2 text-[#1CBB9B]">
                                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                </svg>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div id="type_error_message" class="hidden text-red-500 text-xs font-bold animate-fade-in flex items-center mt-2">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                </svg>
                                Por favor, selecione uma opção antes de prosseguir.
                            </div>

                            <!-- Select oculto atualizado pelos cards -->
                            <select name="request_type_id" id="request_type_select" required class="hidden">
                                <option value="">Selecione...</option>
                                <?php foreach ($requestTypes as $type): ?>
                                    <option value="<?= $type['id'] ?>"
                                        data-info="<?= htmlspecialchars($type['information']) ?>"
                                        data-attention="<?= htmlspecialchars($type['attention']) ?>"
                                        data-name="<?= htmlspecialchars($type['name']) ?>">
                                        <?= htmlspecialchars($type['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <div id="type_info_container" class="hidden animate-fade-in-down">
                                <div class="bg-blue-50 border border-blue-100 rounded-md p-6 shadow-sm">
                                    <div id="info_content" class="text-[#293087] prose prose-sm prose-blue max-w-none leading-relaxed mb-4"></div>
                                    <div id="attention_box" class="hidden bg-red-50 border border-red-100 rounded-md p-4 mt-6">
                                        <div class="text-red-800 text-sm leading-relaxed">
                                            <strong class="font-bold block mb-2">ATENÇÃO:</strong>
                                            <div id="attention_content" class="prose prose-sm prose-red max-w-none"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-6 border-t border-gray-100 flex justify-between items-center">
                            <button type="button" onclick="goToStep(1)" class="text-gray-500 font-bold hover:text-gray-700 transition-all flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                </svg>
                                Voltar
                            </button>
                            <button type="button" onclick="goToStep(3)" class="bg-[#1CBB9B] text-white px-10 py-3 rounded-md font-bold hover:bg-[#169C80] transition-all shadow-md hover:shadow-lg flex items-center">
                                Próximo Passo
                                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ETAPA 3: DETALHES DA SOLICITAÇÃO -->
                <div id="form-step-3" class="hidden space-y-8 animate-fade-in">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-800 mb-6 flex items-center">
                            <span class="bg-[#1CBB9B] text-white rounded-full w-8 h-8 flex items-center justify-center text-sm mr-3">3</span>
                            <span id="step3-title">Detalhes da Solicitação</span>
                        </h3>

                        <!-- Campos específicos por tipo (todos hidden por padrão) -->

                        <!-- Tipo 1: Justificativa de Falta -->
                        <div id="fields-type-1" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Data de Início <span class="text-red-500">*</span></label>
                                    <input type="date" name="start_date" id="start_date" class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Data Final <span class="text-red-500">*</span></label>
                                    <input type="date" name="end_date" id="end_date" class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                                </div>
                            </div>
                        </div>

                        <!-- Tipo 2: Segunda Chamada -->
                        <div id="fields-type-2" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Unidades Curriculares <span class="text-red-500">*</span></label>
                                <div id="ms-subjects-type-2" class="ms-widget" data-required="1"></div>
                            </div>
                            <div>
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" id="uc_other_check_2" class="h-4 w-4 text-[#1CBB9B] border-gray-300 rounded">
                                    <span class="ml-2 text-sm text-gray-700">Unidade Curricular não aparece na lista</span>
                                </label>
                                <input type="text" name="uc_other_name" id="uc_other_name_2"
                                    placeholder="Nome da Unidade Curricular não listada"
                                    class="hidden mt-2 w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Docente(s) responsável(is) <span class="text-red-500">*</span></label>
                                <div id="ms-teachers-type-2" class="ms-widget" data-required="1"></div>
                            </div>
                            <div>
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" id="teacher_other_check_2" class="h-4 w-4 text-[#1CBB9B] border-gray-300 rounded">
                                    <span class="ml-2 text-sm text-gray-700">Professor não listado acima</span>
                                </label>
                                <input type="text" name="teacher_other_name" id="teacher_other_name_2"
                                    placeholder="Nome do professor não listado"
                                    class="hidden mt-2 w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                            </div>
                            <div>
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" name="also_justify_absence" class="h-4 w-4 text-[#1CBB9B] border-gray-300 rounded">
                                    <span class="ml-2 text-sm text-gray-700">Quero também justificar a ausência</span>
                                </label>
                            </div>
                        </div>

                        <!-- Tipo 4: Trancamento -->
                        <div id="fields-type-4" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Semestres a trancar <span class="text-red-500">*</span></label>
                                    <input type="number" name="semesters_to_lock" min="1" max="4"
                                        class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none"
                                        placeholder="1 a 4 semestres">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Motivo do Trancamento <span class="text-red-500">*</span></label>
                                    <select name="lock_reason" class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                                        <option value="">Selecione o motivo...</option>
                                        <option>Trabalho</option>
                                        <option>Saúde</option>
                                        <option>Viagem</option>
                                        <option>Mudança de endereço</option>
                                        <option>Outro</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Tipo 5: Cancelamento de Curso -->
                        <div id="fields-type-5" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Motivo do Cancelamento <span class="text-red-500">*</span></label>
                                <select name="cancel_reason" class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                                    <option value="">Selecione o motivo...</option>
                                    <option>Curso não atendeu expectativas</option>
                                    <option>Horário</option>
                                    <option>Mudança de endereço</option>
                                    <option>Dificuldade de transporte</option>
                                    <option>Mudança de Curso ou Instituição</option>
                                    <option>Dificuldades financeiras</option>
                                    <option>Situação de saúde</option>
                                    <option>Outro</option>
                                </select>
                            </div>
                        </div>

                        <!-- Tipo 7: Matrícula em UC Isolada -->
                        <div id="fields-type-7" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nome(s) da(s) UC(s) <span class="text-red-500">*</span></label>
                                <textarea name="uc_isolated_names" rows="3"
                                    class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none resize-none"
                                    placeholder="Informe o(s) nome(s) da(s) UC(s) que deseja cursar"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Curso que oferece a(s) UC(s) <span class="text-red-500">*</span></label>
                                <input type="text" name="uc_isolated_course"
                                    class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none"
                                    placeholder="Nome do curso que oferece a(s) UC(s)">
                            </div>
                        </div>

                        <!-- Tipo 8: Diplomas / Histórico -->
                        <div id="fields-type-8" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Documento <span class="text-red-500">*</span></label>
                                <div class="space-y-2">
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" name="doc_type" value="Diploma de Curso Técnico ou Superior" class="h-4 w-4 text-[#1CBB9B] border-gray-300">
                                        <span class="ml-2 text-sm text-gray-700">Diploma de Curso Técnico ou Superior</span>
                                    </label>
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" name="doc_type" value="Certificado FIC" class="h-4 w-4 text-[#1CBB9B] border-gray-300">
                                        <span class="ml-2 text-sm text-gray-700">Certificado FIC</span>
                                    </label>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome do Curso <span class="text-red-500">*</span></label>
                                    <input type="text" name="diploma_course_name"
                                        class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none"
                                        placeholder="Nome do curso concluído">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Ano de Conclusão <span class="text-red-500">*</span></label>
                                    <input type="number" name="graduation_year" min="2000" max="2030"
                                        class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none"
                                        placeholder="Ex: 2023">
                                </div>
                            </div>
                        </div>

                        <!-- Tipo 9: Validação / Dispensa de UC -->
                        <div id="fields-type-9" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
                            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 space-y-3 text-sm">
                                <p class="font-bold text-blue-900 flex items-center gap-2">
                                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                    </svg>
                                    Modalidades de Validação de UC (RDP — Art. 68–73)
                                </p>
                                <div class="space-y-2 text-blue-800 leading-snug">
                                    <p><strong>RE — Reconhecimento de Estudos:</strong> Você cursou esta UC (ou equivalente) em outra instituição de ensino superior ou técnica. Apresente histórico escolar e programa da disciplina cursada.</p>
                                    <p><strong>RS — Reconhecimento de Saberes:</strong> Você possui experiência prática/profissional comprovável na área da UC. Apresente documentação (CTPS, declaração de empresa, certificados, portfólio, etc.).</p>
                                    <p><strong>EAE — Extraordinário Aproveitamento:</strong> Você demonstra domínio do conteúdo e solicita uma avaliação especial designada pelo professor responsável pela UC.</p>
                                </div>
                                <p class="text-xs text-blue-700 border-t border-blue-200 pt-2">Sua solicitação será encaminhada à Coordenadoria de Curso, responsável pela análise. Para RE e RS, a documentação comprobatória é obrigatória. Para EAE, a Coordenadoria designará o professor que aplicará a avaliação especial.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Validação <span class="text-red-500">*</span></label>
                                <div class="space-y-2">
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" name="validation_type" value="RE" id="val_re" class="h-4 w-4 text-[#1CBB9B] border-gray-300">
                                        <span class="ml-2 text-sm text-gray-700">Reconhecimento de Estudos (RE)</span>
                                    </label>
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" name="validation_type" value="RS" id="val_rs" class="h-4 w-4 text-[#1CBB9B] border-gray-300">
                                        <span class="ml-2 text-sm text-gray-700">Reconhecimento de Saberes (RS)</span>
                                    </label>
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" name="validation_type" value="EAE" id="val_eae" class="h-4 w-4 text-[#1CBB9B] border-gray-300">
                                        <span class="ml-2 text-sm text-gray-700">Extraordinário Aproveitamento (EAE)</span>
                                    </label>
                                </div>
                            </div>
                            <div id="uc_re_block" class="hidden">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Informe a(s) UC(s) e experiência para RE <span class="text-red-500">*</span></label>
                                <textarea name="uc_re_detail" rows="3" class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none resize-none"></textarea>
                            </div>
                            <div id="uc_rs_block" class="hidden">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Informe a(s) UC(s) e experiência para RS <span class="text-red-500">*</span></label>
                                <textarea name="uc_rs_detail" rows="3" class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none resize-none"></textarea>
                            </div>
                            <div id="uc_eae_block" class="hidden">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Informe a(s) UC(s) e experiência para EAE <span class="text-red-500">*</span></label>
                                <textarea name="uc_eae_detail" rows="3" class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none resize-none"></textarea>
                            </div>
                        </div>

                        <!-- Tipo 12: Matrícula Especial em UC -->
                        <div id="fields-type-12" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nome(s) da(s) UC(s) <span class="text-red-500">*</span></label>
                                <textarea name="uc_special_names" rows="3"
                                    class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none resize-none"
                                    placeholder="Informe o(s) nome(s) da(s) UC(s) de interesse"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Curso que oferece a(s) UC(s) <span class="text-red-500">*</span></label>
                                <input type="text" name="uc_special_course"
                                    class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none"
                                    placeholder="Nome do curso">
                            </div>
                        </div>

                        <!-- Tipo 14: Ajuste de Matrícula -->
                        <div id="fields-type-14" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Descreva as UCs que deseja incluir e/ou cancelar <span class="text-red-500">*</span></label>
                                <textarea name="uc_changes" rows="4"
                                    class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none resize-none"
                                    placeholder="Ex: Incluir: Banco de Dados I&#10;Cancelar: Cálculo II"></textarea>
                            </div>
                        </div>

                        <!-- Tipo 18: Colação de Grau -->
                        <div id="fields-type-18" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Situação ENADE <span class="text-red-500">*</span></label>
                                <div class="space-y-2">
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" name="enade_status" value="Realizei o ENADE" class="h-4 w-4 text-[#1CBB9B] border-gray-300">
                                        <span class="ml-2 text-sm text-gray-700">Realizei o ENADE</span>
                                    </label>
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" name="enade_status" value="Fui dispensado(a)" class="h-4 w-4 text-[#1CBB9B] border-gray-300">
                                        <span class="ml-2 text-sm text-gray-700">Fui dispensado(a)</span>
                                    </label>
                                </div>
                            </div>
                            <div class="bg-[#1CBB9B]/5 p-4 rounded-xl border border-[#1CBB9B]/20">
                                <label class="flex items-start cursor-pointer">
                                    <input type="checkbox" name="colacao_declaration" id="colacao_declaration" class="h-5 w-5 mt-0.5 text-[#1CBB9B] focus:ring-[#1CBB9B] border-gray-300 rounded">
                                    <span class="ml-3 text-sm text-gray-700">Declaro estar ciente de que a colação de grau está sujeita à análise e aprovação da coordenação do curso. <span class="text-red-500">*</span></span>
                                </label>
                            </div>
                        </div>

                        <!-- Tipo 20: Horário Diferenciado -->
                        <div id="fields-type-20" class="hidden space-y-6 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-4">Tipo de Solicitação de Horário <span class="text-red-500">*</span></label>
                                <div class="flex flex-wrap gap-6">
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" name="schedule_type" value="Chegada tardia" class="w-5 h-5 text-[#1CBB9B] focus:ring-[#1CBB9B] border-gray-300">
                                        <span class="ml-2 text-sm text-gray-700">Chegada tardia</span>
                                    </label>
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" name="schedule_type" value="Saída antecipada" class="w-5 h-5 text-[#1CBB9B] focus:ring-[#1CBB9B] border-gray-300">
                                        <span class="ml-2 text-sm text-gray-700">Saída antecipada</span>
                                    </label>
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" name="schedule_type" value="Entrada e Saída" class="w-5 h-5 text-[#1CBB9B] focus:ring-[#1CBB9B] border-gray-300">
                                        <span class="ml-2 text-sm text-gray-700">Entrada e Saída</span>
                                    </label>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Horário Chegada</label>
                                        <input type="time" name="arrival_time_1" id="arrival_time_1" class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-2">Horário Chegada 2 <span class="text-[10px] text-gray-400 font-normal">(Integrados)</span></label>
                                        <input type="time" name="arrival_time_2" id="arrival_time_2" class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Horário Saída</label>
                                        <input type="time" name="departure_time_1" id="departure_time_1" class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-2">Horário Saída 2 <span class="text-[10px] text-gray-400 font-normal">(Integrados)</span></label>
                                        <input type="time" name="departure_time_2" id="departure_time_2" class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                                    </div>
                                </div>
                            </div>
                            <p class="text-[11px] text-[#293087] bg-blue-50/50 p-3 rounded border border-blue-100 italic">
                                * Pelo menos um horário deve ser preenchido de acordo com sua necessidade.
                            </p>
                            <div class="bg-[#1CBB9B]/5 p-4 rounded-xl border border-[#1CBB9B]/20">
                                <label class="flex items-start cursor-pointer">
                                    <input type="checkbox" id="declaration_accepted" name="declaration_accepted" class="h-5 w-5 text-[#1CBB9B] focus:ring-[#1CBB9B] border-gray-300 rounded">
                                    <div class="ml-3 text-sm">
                                        <p class="font-medium text-gray-800">Declaração</p>
                                        <p class="text-gray-600 leading-relaxed text-xs">Declaro que as informações fornecidas são verdadeiras e estou ciente de que a aprovação está sujeita à análise da coordenação do curso. Comprometo-me também a cumprir os horários aqui solicitados.</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Tipo 22: Carteira de Autorização de Saída Antecipada (LiberaIFSC) -->
                        <div id="fields-type-22" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
                            <div class="bg-blue-50 border border-blue-200 rounded-xl overflow-hidden text-sm">
                                <div class="px-4 py-3 border-b border-blue-200 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-blue-700 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <p class="font-bold text-blue-900">Leia antes de solicitar a Carteirinha LiberaIFSC</p>
                                </div>
                                <div class="px-4 py-3 max-h-64 overflow-y-auto space-y-3 text-blue-900 leading-relaxed">
                                    <p>Trata-se de uma iniciativa do IFSC Câmpus Garopaba que visa reforçar o senso de responsabilidade das famílias e dos nossos estudantes. O instituto também ganha, pois direciona recursos humanos para o que é mais importante: pensar e agir com mais empenho nos processos pedagógicos, visando melhorar a educação da nossa juventude.</p>
                                    <p>Atualmente, cada vez que um/a estudante menor de idade precisa sair do Instituto por qualquer motivo, deve solicitar autorização de seus responsáveis; eles, então, comunicam a uma equipe do IFSC que, por sua vez, emite um aviso de autorização de saída antecipada. Isso ocorre inúmeras vezes, todos os dias.</p>
                                    <p>Nossa proposta consiste em emitir uma única autorização de saída antecipada, com validade indeterminada, para permitir que o/a estudante saia do câmpus sempre que necessário — claro, com autorização do/a responsável. Cabe a você pensar se o/a jovem sob sua responsabilidade é capaz de cumprir um acordo desse tipo. Apostamos que sim: afinal, aos 16 anos muitos já exercem o direito de votar, influenciando o futuro do país.</p>
                                    <p>Ainda assim, recomendamos uma conversa franca com o/a jovem para tentar garantir que ele/a tenha condições de se comprometer com este projeto. Se necessário, a autorização poderá ser cancelada posteriormente, mediante comparecimento ao câmpus e justificativa.</p>
                                    <div>
                                        <p class="font-semibold mb-1.5">A autorização é válida apenas nas seguintes situações:</p>
                                        <ol class="list-decimal list-inside space-y-1 text-blue-800">
                                            <li>Realização de trabalhos acadêmicos fora de horário de aula (são os momentos em que eles/as não têm aula e precisam comparecer ao câmpus para estudos e, depois, querem sair);</li>
                                            <li>Troca de horário de aulas (quando docentes precisam trocar de horário durante a semana, para evitar ausências; ou quando alunos/as fazem pendência em alguma unidade curricular);</li>
                                            <li>Quando, eventualmente, ocorre ausência de professor/a;</li>
                                            <li>Para atendimento extraclasse com docente, monitoria, pendência, atividade esportiva e projetos;</li>
                                            <li>Finalização antecipada de avaliações (quando o/a estudante termina antecipadamente uma avaliação e é dispensado/a da sala de aula).</li>
                                        </ol>
                                    </div>
                                    <p class="text-blue-800 font-medium">Para todas as demais situações, continua valendo o sistema vigente: é preciso entrar em contato com a Coordenadoria Pedagógica do câmpus pelo número <strong>48 99400-9198</strong>.</p>
                                    <p class="text-blue-700 text-xs border-t border-blue-200 pt-2">Para cancelar a autorização é necessário comparecer à Coordenadoria Pedagógica do câmpus.</p>
                                </div>
                                <div class="px-4 py-3 bg-blue-100 border-t border-blue-200">
                                    <label class="block text-xs font-semibold text-blue-900 mb-1">Para confirmar que leu e compreendeu o texto acima, digite <span class="font-mono bg-white px-1.5 py-0.5 rounded border border-blue-300">CIENTE</span> no campo abaixo: <span class="text-red-500">*</span></label>
                                    <input type="text" id="libera_ciente" autocomplete="off"
                                        placeholder="Digite CIENTE"
                                        class="w-full bg-white border border-blue-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none font-mono tracking-widest uppercase">
                                    <p id="libera_ciente_error" class="hidden text-xs text-red-600 font-medium mt-1">Digite CIENTE para confirmar a leitura.</p>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nome completo do/a responsável legal <span class="text-red-500">*</span></label>
                                <input type="text" name="guardian_legal_name" id="libera_guardian_name" required
                                    class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none"
                                    placeholder="Nome completo do/a responsável que assinará o termo">
                            </div>
                            <div class="bg-white border border-gray-200 rounded-xl p-4 space-y-3">
                                <p class="text-sm font-semibold text-gray-800">Termo de Autorização</p>
                                <p class="text-sm text-gray-600">Gere o termo abaixo, peça ao/à responsável assinar digitalmente via Gov.br e faça o upload do PDF assinado em Anexos (próxima etapa).</p>
                                <div class="flex flex-wrap items-center gap-3">
                                    <button type="button" onclick="generateSaidaAntecipadaPDF()"
                                        class="inline-flex items-center gap-2 text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition-all shadow-sm hover:opacity-90"
                                        style="background:#1351B4;">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                        Gerar Termo (PDF)
                                    </button>
                                    <a href="https://assinador.iti.br" target="_blank" rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1.5 text-sm font-semibold px-4 py-2.5 rounded-lg border transition-all hover:opacity-80"
                                        style="color:#1351B4; border-color:#1351B4;">
                                        Assinar no Gov.br ↗
                                    </a>
                                </div>
                                <p class="text-xs text-gray-400">O/A responsável abre o PDF gerado em <strong>assinador.iti.br</strong> usando a conta Gov.br para assinar digitalmente.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Termo assinado <span class="text-red-500">*</span>
                                    <span class="text-xs text-gray-400 font-normal ml-1">(PDF assinado via Gov.br, max 5 MB)</span>
                                </label>
                                <div id="type22-upload-container"
                                    class="flex justify-center px-6 pt-5 pb-5 border-2 border-dashed border-gray-300 rounded-lg hover:border-[#1CBB9B] hover:bg-[#1CBB9B]/5 transition-all cursor-pointer group relative bg-white">
                                    <input id="type22-file-upload" type="file"
                                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                        accept=".pdf,.jpg,.jpeg,.png,.heic,.webp">
                                    <div class="space-y-1 text-center pointer-events-none">
                                        <div class="mx-auto h-10 w-10 text-gray-400 group-hover:text-[#1CBB9B] transition-colors">
                                            <svg stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </div>
                                        <div class="text-sm text-gray-600">
                                            <span class="font-medium text-[#1CBB9B]">Enviar termo assinado</span> ou arraste aqui
                                        </div>
                                        <p class="text-xs text-gray-500">PDF assinado via Gov.br · JPG · PNG · Máx. 5 MB</p>
                                    </div>
                                </div>
                                <div id="type22-file-list" class="mt-2 space-y-2"></div>
                                <div id="type22-temp-files-container"></div>
                                <p id="type22-upload-error" class="hidden text-xs text-red-600 font-medium mt-1">
                                    Envie o termo assinado antes de prosseguir.
                                </p>
                            </div>
                            <div class="bg-[#1CBB9B]/5 p-4 rounded-xl border border-[#1CBB9B]/20">
                                <label class="flex items-start cursor-pointer">
                                    <input type="checkbox" id="libera_declaration" name="libera_declaration" class="h-5 w-5 mt-0.5 text-[#1CBB9B] focus:ring-[#1CBB9B] border-gray-300 rounded">
                                    <span class="ml-3 text-sm text-gray-700">Estou ciente de que esta autorização é válida apenas nas situações listadas acima, e que o/a estudante <strong>não deve utilizá-la para faltar às atividades acadêmicas programadas</strong>. Comprometo-me a orientar o/a estudante quanto ao uso correto. <span class="text-red-500">*</span></span>
                                </label>
                            </div>
                        </div>

                        <!-- Tipo 25: Cancelamento de Matrícula em UC -->
                        <div id="fields-type-25" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Unidades Curriculares a cancelar <span class="text-red-500">*</span></label>
                                <div id="ms-subjects-type-25" class="ms-widget" data-required="1"></div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Motivo do cancelamento <span class="text-red-500">*</span></label>
                                <textarea name="uc_cancel_reason" rows="3"
                                    class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none resize-none"
                                    placeholder="Descreva o motivo do cancelamento"></textarea>
                            </div>
                        </div>

                        <!-- Tipo 16: Extraordinário Aproveitamento de Estudos -->
                        <div id="fields-type-16" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-800">
                                <strong>EAE — Extraordinário Aproveitamento de Estudos:</strong> Você demonstra domínio do conteúdo da UC e solicita uma avaliação especial designada pelo professor responsável. O processo é aprovado pelo Colegiado do Curso após análise do requerimento.
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Unidade(s) Curricular(es) para as quais solicita EAE <span class="text-red-500">*</span></label>
                                <div id="ms-subjects-type-16" class="ms-widget" data-required="1"></div>
                            </div>
                        </div>

                        <!-- Tipo 19: Outro -->
                        <div id="fields-type-19" class="hidden mb-6 p-4 bg-blue-50 rounded-xl border border-blue-100 text-sm text-blue-800">
                            <strong>Requerimento avulso:</strong> Descreva detalhadamente sua solicitação no campo de Justificativa abaixo. Inclua todas as informações que possam ajudar a coordenação a analisar o pedido.
                        </div>

                        <!-- Tipo 3: Trabalhos Domiciliares -->
                        <div id="fields-type-3" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Início da ausência <span class="text-red-500">*</span></label>
                                    <input type="date" name="td_start_date" required class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Fim da ausência <span class="text-red-500">*</span></label>
                                    <input type="date" name="td_end_date" required class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">UCs para as quais solicita trabalhos <span class="text-red-500">*</span></label>
                                <div id="ms-subjects-type-3" class="ms-widget" data-required="1"></div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de documento comprobatório <span class="text-red-500">*</span></label>
                                <select name="td_doc_type" required class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                                    <option value="">Selecione...</option>
                                    <option>Atestado médico</option>
                                    <option>Declaração de internação hospitalar</option>
                                    <option>Declaração médica de acompanhamento de familiar</option>
                                    <option>Outro</option>
                                </select>
                            </div>
                        </div>

                        <!-- Tipo 6: Transferência para outra instituição -->
                        <div id="fields-type-6" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Instituição de destino <span class="text-red-500">*</span></label>
                                <input type="text" name="transfer_institution" required class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none" placeholder="Nome completo da instituição">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Curso de destino <span class="text-red-500">*</span></label>
                                <input type="text" name="transfer_course" required class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none" placeholder="Nome do curso na instituição de destino">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Motivo principal da transferência <span class="text-red-500">*</span></label>
                                <select name="transfer_reason" required class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                                    <option value="">Selecione...</option>
                                    <option>Mudança de endereço/cidade</option>
                                    <option>Curso não disponível no IFSC Garopaba</option>
                                    <option>Dificuldades de transporte</option>
                                    <option>Preferência por outra instituição</option>
                                    <option>Outro</option>
                                </select>
                            </div>
                        </div>

                        <!-- Tipo 10: Retorno de Trancamento -->
                        <div id="fields-type-10" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Semestre/período pretendido para retorno <span class="text-red-500">*</span></label>
                                <input type="text" name="return_period" required class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none" placeholder="Ex: 2026.1 — 1º Semestre de 2026">
                            </div>
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-sm text-yellow-800">
                                <strong>Atenção:</strong> O retorno está sujeito à disponibilidade de vagas e análise da coordenação. Verifique se o seu período de trancamento ainda não expirou.
                            </div>
                        </div>

                        <!-- Tipo 11: Reingresso -->
                        <div id="fields-type-11" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Último período cursado <span class="text-red-500">*</span></label>
                                    <input type="text" name="last_period" required class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none" placeholder="Ex: 2022.2 — 3º Ano 2022">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Motivo do afastamento <span class="text-red-500">*</span></label>
                                    <select name="reinstatement_reason" required class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                                        <option value="">Selecione...</option>
                                        <option>Trabalho</option>
                                        <option>Saúde</option>
                                        <option>Mudança de endereço</option>
                                        <option>Dificuldades financeiras</option>
                                        <option>Trancamento expirado</option>
                                        <option>Outro</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Tipo 13: Apoio Educacional Especializado -->
                        <div id="fields-type-13" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de necessidade específica <span class="text-red-500">*</span></label>
                                <select name="support_type" required class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                                    <option value="">Selecione o tipo de necessidade...</option>
                                    <option>Deficiência física</option>
                                    <option>Deficiência visual</option>
                                    <option>Deficiência auditiva</option>
                                    <option>Deficiência intelectual</option>
                                    <option>Transtorno do Espectro Autista (TEA)</option>
                                    <option>Transtorno de aprendizagem (dislexia, TDAH, etc.)</option>
                                    <option>Condição de saúde / tratamento médico contínuo</option>
                                    <option>Outra necessidade específica</option>
                                </select>
                            </div>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="has_documentation" value="1" class="h-4 w-4 text-[#1CBB9B] border-gray-300 rounded">
                                <span class="text-sm text-gray-700">Possuo laudo médico/psicológico ou documentação comprobatória</span>
                            </label>
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-800">
                                <strong>Informação:</strong> Sua solicitação será encaminhada ao NAPNE (Núcleo de Atendimento às Pessoas com Necessidades Específicas). Documentos podem ser solicitados posteriormente.
                            </div>
                        </div>

                        <!-- Tipo 15: Planos de Estudo -->
                        <div id="fields-type-15" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Data de Início <span class="text-red-500">*</span></label>
                                    <input type="date" name="pe_start_date" required class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Data de Término <span class="text-red-500">*</span></label>
                                    <input type="date" name="pe_end_date" required class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Unidades Curriculares envolvidas <span class="text-red-500">*</span></label>
                                <div id="ms-subjects-type-15" class="ms-widget" data-required="1"></div>
                            </div>
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-sm text-yellow-800">
                                <strong>Atenção:</strong> O plano de estudos é aprovado individualmente por cada docente. Após o envio, aguarde o contato da coordenação pedagógica.
                            </div>
                        </div>

                        <!-- Tipo 17: Quebra de Pré-requisitos -->
                        <div id="fields-type-17" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">UC que deseja cursar sem o pré-requisito <span class="text-red-500">*</span></label>
                                <div id="ms-subjects-type-17" class="ms-widget" data-required="1"></div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Pré-requisito não cumprido <span class="text-red-500">*</span></label>
                                <input type="text" name="missing_prereq" required class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none" placeholder="Nome da UC pré-requisito que não cursou">
                            </div>
                        </div>

                        <!-- Tipo 21: Assistência Estudantil -->
                        <div id="fields-type-21" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de assistência solicitada <span class="text-red-500">*</span></label>
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="assistance_type[]" value="Auxílio-transporte" class="h-4 w-4 text-[#1CBB9B] border-gray-300 rounded">
                                        <span class="text-sm text-gray-700">Auxílio-transporte</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="assistance_type[]" value="Auxílio-alimentação" class="h-4 w-4 text-[#1CBB9B] border-gray-300 rounded">
                                        <span class="text-sm text-gray-700">Auxílio-alimentação</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="assistance_type[]" value="Auxílio-moradia" class="h-4 w-4 text-[#1CBB9B] border-gray-300 rounded">
                                        <span class="text-sm text-gray-700">Auxílio-moradia</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="assistance_type[]" value="Equipamentos e materiais pedagógicos" class="h-4 w-4 text-[#1CBB9B] border-gray-300 rounded">
                                        <span class="text-sm text-gray-700">Equipamentos e materiais pedagógicos</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="assistance_type[]" value="Outro" class="h-4 w-4 text-[#1CBB9B] border-gray-300 rounded">
                                        <span class="text-sm text-gray-700">Outro</span>
                                    </label>
                                </div>
                                <p id="assistance-error" class="hidden text-xs text-red-600 font-medium mt-1">Selecione ao menos um tipo de assistência.</p>
                            </div>
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-800">
                                <strong>Informação:</strong> A assistência estudantil é gerenciada pela equipe de assistência social do câmpus. Documentos comprobatórios de situação socioeconômica podem ser solicitados após análise.
                            </div>
                        </div>

                        <!-- Justificativa (sempre visível no step 3) -->
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Justificativa / Observações adicionais <span class="text-red-500">*</span></label>
                            <textarea name="description" id="description" required rows="5"
                                class="w-full bg-gray-50 border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent transition-all outline-none resize-none"
                                placeholder="Descreva sua solicitação com o máximo de detalhes possível..."></textarea>
                        </div>

                        <div class="pt-6 border-t border-gray-100 flex justify-between items-center">
                            <button type="button" onclick="goToStep(2)" class="text-gray-500 font-bold hover:text-gray-700 transition-all flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                </svg>
                                Voltar
                            </button>
                            <button type="button" id="step3-next-btn" onclick="goToStep(4)" class="bg-[#1CBB9B] text-white px-10 py-3 rounded-md font-bold hover:bg-[#169C80] transition-all shadow-md hover:shadow-lg flex items-center">
                                Próximo Passo
                                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                            <button type="button" id="step3-submit-btn"
                                class="hidden bg-[#1CBB9B] text-white px-10 py-3 rounded-md font-bold hover:bg-[#169C80] transition-all shadow-md hover:shadow-lg flex items-center">
                                <span id="step3-btn-text">Enviar Requerimento</span>
                                <svg id="step3-btn-spinner" class="hidden animate-spin ml-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ETAPA 4: ANEXOS E ENVIO -->
                <div id="form-step-4" class="hidden space-y-6 animate-fade-in">

                    <!-- Ciência do Responsável (apenas para menores de idade) -->
                    <div id="guardian-section" class="hidden">
                        <div class="rounded-xl overflow-hidden bg-white" style="border:1px solid rgba(19,81,180,0.20);">

                            <!-- Cabeçalho sutil gov.br -->
                            <div class="bg-white px-5 py-4 border-b" style="border-color:rgba(19,81,180,0.15);">
                                <div class="flex items-center justify-between flex-wrap gap-2">
                                    <img src="<?= BASE_URL ?>/assets/img/govbr.webp" alt="Gov.br" class="h-7 w-auto">
                                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full" style="background:#eef2fb;color:#1351B4;">Assinatura Digital Exigida</span>
                                </div>
                                <p class="text-sm text-gray-600 mt-2">Declaração de Ciência do Responsável</p>
                            </div>

                            <!-- Corpo -->
                            <div class="bg-white p-5 space-y-4">
                                <p class="text-sm text-gray-600">Como você é <strong class="text-gray-800">menor de idade</strong>, é obrigatório anexar a declaração de ciência do responsável legal, assinada digitalmente via Gov.br.</p>

                                <!-- Modelo de texto -->
                                <div class="rounded-lg p-3" style="background:#f0f4ff; border:1px solid rgba(19,81,180,0.2);">
                                    <p class="text-xs font-bold uppercase tracking-wider mb-1.5" style="color:#1351B4;">Texto sugerido para a declaração:</p>
                                    <p class="text-sm text-gray-600 italic leading-relaxed">
                                        "Eu, <strong>[nome completo do responsável]</strong>, declaro estar ciente do requerimento de <strong>[tipo de solicitação]</strong> apresentado por <strong>[nome do estudante]</strong> ao IFSC Câmpus Garopaba em <strong>[data]</strong>."
                                    </p>
                                </div>

                                <!-- Instrução com link clicável -->
                                <div class="flex items-start gap-2 text-xs rounded-lg px-3 py-2.5" style="background:#eef2fb; color:#1351B4;">
                                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span>
                                        Assine digitalmente em
                                        <a href="https://assinador.iti.br" target="_blank" rel="noopener noreferrer"
                                            class="font-bold underline underline-offset-2 hover:opacity-75 transition-opacity" style="color:#1351B4;">
                                            assinador.iti.br ↗
                                        </a>
                                        usando a conta Gov.br do responsável e envie o PDF assinado abaixo.
                                    </span>
                                </div>

                                <!-- Fallback: sem acesso ao Gov.br -->
                                <div class="flex items-start gap-3 rounded-lg px-4 py-3" style="background:#fffbeb; border:1px solid #f59e0b;">
                                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" style="color:#d97706;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                    </svg>
                                    <div class="text-sm" style="color:#78350f;">
                                        <strong class="block mb-0.5">Não tem acesso ao Gov.br?</strong>
                                        Escreva a declaração <strong>de próprio punho</strong>, assine e fotografe o documento <strong>junto ao seu RG, CNH ou outro documento oficial com foto</strong>. Envie a imagem no campo abaixo.
                                    </div>
                                </div>

                                <!-- Botão gerar PDF + dica -->
                                <div class="flex flex-wrap items-center gap-3">
                                    <button type="button" onclick="generateGuardianPDF()"
                                        class="inline-flex items-center gap-2 text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition-all shadow-sm hover:shadow-md hover:opacity-90"
                                        style="background:#1351B4;">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                        Gerar Declaração (PDF)
                                    </button>
                                    <span class="text-xs text-gray-500 leading-tight">Baixe, peça ao responsável assinar via Gov.br e envie abaixo</span>
                                </div>

                                <!-- Upload zone -->
                                <div id="guardian-upload-container"
                                    class="flex justify-center px-6 pt-6 pb-6 border-2 border-dashed rounded-lg transition-all cursor-pointer group relative bg-white"
                                    style="border-color:rgba(19,81,180,0.3);">
                                    <input id="guardian-file-upload" type="file"
                                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                        accept=".pdf,.jpg,.jpeg,.png,.heic,.webp">
                                    <div class="space-y-2 text-center pointer-events-none">
                                        <div class="mx-auto h-10 w-10 transition-colors" style="color:rgba(19,81,180,0.4);">
                                            <svg stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </div>
                                        <div class="text-sm text-gray-600"><span class="font-medium" style="color:#1351B4;">Clique para anexar</span> ou arraste o arquivo</div>
                                        <p class="text-xs text-gray-400">PDF assinado (Gov.br) ou foto da declaração · JPG · PNG · PDF · Máx. 5MB</p>
                                    </div>
                                </div>
                                <div id="guardian-file-list" class="space-y-2"></div>
                                <div id="guardian-temp-files-container"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Outros documentos (sempre visível) -->
                    <div>
                        <h3 class="text-xl font-semibold text-gray-800 mb-1 flex items-center">
                            <span class="bg-[#1CBB9B] text-white rounded-full w-8 h-8 flex items-center justify-center text-sm mr-3">4</span>
                            Anexos
                        </h3>
                        <p id="docs-hint" class="text-sm text-gray-500 mb-4 ml-11">Comprovantes, laudos ou outros documentos relevantes <span class="text-gray-400">(opcional)</span></p>
                        <div id="upload-container"
                            class="flex justify-center px-6 pt-8 pb-8 border-2 border-gray-300 border-dashed rounded-md hover:border-[#1CBB9B] hover:bg-[#1CBB9B]/5 transition-all cursor-pointer group relative">
                            <input id="file-upload" type="file"
                                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" multiple
                                accept=".pdf,image/*" style="opacity: 0; position: absolute; inset: 0;">
                            <div class="space-y-2 text-center pointer-events-none">
                                <div class="mx-auto h-12 w-12 text-gray-400 group-hover:text-[#1CBB9B] transition-colors">
                                    <svg stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                                <div class="text-sm text-gray-600"><span class="font-medium text-[#1CBB9B]">Adicionar arquivos</span> ou arraste e solte</div>
                                <p class="text-xs text-gray-500">PNG, JPG, PDF até 5MB</p>
                            </div>
                        </div>
                        <div id="file-list" class="mt-4 space-y-3"></div>
                        <div id="temp-files-container"></div>
                    </div>

                    <!-- Turnstile -->
                    <?php if (ENABLE_TURNSTILE): ?>
                        <div class="flex justify-center py-4 bg-gray-50 rounded-xl border border-gray-100">
                            <div class="cf-turnstile" data-sitekey="<?= TURNSTILE_SITE_KEY ?>"></div>
                        </div>
                    <?php endif; ?>

                    <div class="pt-6 border-t border-gray-100 flex justify-between items-center">
                        <button type="button" onclick="goToStep(3)"
                            class="text-gray-500 font-bold hover:text-gray-700 transition-all flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Voltar
                        </button>
                        <button type="submit" id="submit_btn"
                            class="bg-[#1CBB9B] text-white px-10 py-4 rounded-md font-bold hover:bg-[#169C80] transition-all shadow-lg hover:shadow-xl flex items-center transform hover:-translate-y-0.5">
                            <span id="btn_text">Enviar Requerimento</span>
                            <svg id="btn_spinner" class="hidden animate-spin ml-3 h-5 w-5 text-white"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <p class="mt-4 text-xs text-center text-gray-500">
                    Protegido pelo Cloudflare Turnstile.
                </p>
        </div>
        </form>
        </div>
    </main>

    <footer class="bg-white border-t border-gray-200 mt-12 py-8">
        <div class="container mx-auto px-4 text-center">
            <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="IFSC Logo"
                class="h-8 mx-auto mb-4 opacity-50 grayscale hover:grayscale-0 transition-all">
            <p class="text-sm text-gray-500">&copy; <?= date('Y') ?> Instituto Federal de Santa Catarina - Câmpus Garopaba | Desenvolvido pelo Prof. Eduardo Gomes (Câmpus Canoinhas), gentilmente cedido ao Câmpus Garopaba &middot; Customizado e expandido pelo Prof. Thiago Paes</p>
        </div>
    </footer>

    <script src="<?= BASE_URL ?>/assets/js/main.js"></script>
    <script>
        const ALL_SUBJECTS = <?= $subjectsJson ?>;
        const ALL_TEACHERS = <?= $teachersJson ?>;
        const LOGO_DATA = '<?= $logoBase64 ?>';
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Mascara e Validacao de Telefone
            const phoneInput = document.getElementById('student_phone');
            if (phoneInput) {
                phoneInput.addEventListener('input', function (e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length > 11) value = value.slice(0, 11);

                    if (value.length > 2) {
                        value = '(' + value.substring(0, 2) + ') ' + value.substring(2);
                    }
                    if (value.length > 8) {
                        // Aplica logica do traco baseada no tamanho
                        if (value.length > 13) { // (XX) XXXXX-XXXX
                            value = value.substring(0, 10) + '-' + value.substring(10);
                        } else { // (XX) XXXX-XXXX or typing
                            value = value.substring(0, 9) + '-' + value.substring(9);
                        }
                    }
                    e.target.value = value;

                    // Reseta validade customizada enquanto digita
                    this.setCustomValidity('');
                });

                phoneInput.addEventListener('blur', function (e) {
                    const value = e.target.value.replace(/\D/g, '');
                    if (value.length > 0 && value.length < 10) {
                        this.setCustomValidity('Telefone inválido. Formato esperado: (XX) XXXX-XXXX ou (XX) XXXXX-XXXX');
                        this.reportValidity();
                    } else {
                        this.setCustomValidity('');
                    }
                });
            }

            // Logica de Multiplas Etapas
            const form = document.getElementById('requestForm');
            const steps = [
                document.getElementById('form-step-1'),
                document.getElementById('form-step-2'),
                document.getElementById('form-step-3'),
                document.getElementById('form-step-4')
            ];
            const progressLine = document.getElementById('progress-line');
            const dots = [
                document.getElementById('step-dot-1'),
                document.getElementById('step-dot-2'),
                document.getElementById('step-dot-3'),
                document.getElementById('step-dot-4')
            ];

            window.goToStep = function (stepNumber) {
                const currentStepIndex = steps.findIndex(s => !s.classList.contains('hidden'));

                if (stepNumber > (currentStepIndex + 1)) {
                    const currentStep = steps[currentStepIndex];
                    let valid = true;

                    // Validacao generica de campos required visiveis
                    const allInputs = currentStep.querySelectorAll('input[required], select[required], textarea[required]');
                    const inputs = Array.from(allInputs).filter(input => {
                        if (input.type === 'hidden') return false;
                        let el = input;
                        while (el && el !== currentStep) {
                            if (el.classList.contains('hidden')) return false;
                            el = el.parentElement;
                        }
                        return true;
                    });
                    inputs.forEach(input => {
                        if (!input.checkValidity()) {
                            input.reportValidity();
                            valid = false;
                        }
                    });

                    // Validacao Step 2: tipo de requerimento selecionado
                    if (valid && (currentStepIndex + 1) === 2 && !requestTypeSelect.value) {
                        document.getElementById('type_error_message').classList.remove('hidden');
                        document.getElementById('types_grid').classList.add('border-red-200', 'bg-red-50/10');
                        typeCards.forEach(card => {
                            if (!card.classList.contains('hidden')) {
                                card.classList.add('error-ring', 'border-red-400');
                            }
                        });
                        typeSearch.focus();
                        valid = false;
                    }

                    // Validacao Step 3: campos especificos por tipo
                    if (valid && (currentStepIndex + 1) === 3) {
                        const typeId = requestTypeSelect.value;

                        // multi-selects: ao menos 1 selecionado
                        const typeDiv = document.getElementById('fields-type-' + typeId);
                        if (typeDiv && !typeDiv.classList.contains('hidden')) {
                            const multiWidgets = typeDiv.querySelectorAll('.ms-widget[data-required]');
                            multiWidgets.forEach(widget => {
                                if (!valid) return;
                                if (widget.querySelectorAll('.ms-inputs input[type="hidden"]').length === 0) {
                                    const wrapperEl = widget.querySelector('.ms-wrapper');
                                    const errorEl   = widget.querySelector('.ms-error');
                                    if (wrapperEl) wrapperEl.classList.add('border-red-400');
                                    if (errorEl) errorEl.classList.remove('hidden');
                                    widget.closest('[id^="fields-type-"]')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                                    valid = false;
                                }
                            });

                            // radio groups
                            if (valid) {
                                const radioNames = new Set();
                                typeDiv.querySelectorAll('input[type="radio"]').forEach(r => radioNames.add(r.name));
                                radioNames.forEach(name => {
                                    const checked = typeDiv.querySelector('input[name="' + name + '"]:checked');
                                    if (!checked) {
                                        alert('Selecione uma das opções obrigatórias.');
                                        valid = false;
                                    }
                                });
                            }

                            // checkbox obrigatorio (colacao)
                            if (valid && typeId === '18') {
                                const decl = document.getElementById('colacao_declaration');
                                if (decl && !decl.checked) {
                                    alert('Você deve marcar a declaração obrigatória.');
                                    valid = false;
                                }
                            }

                            // checkbox obrigatorio (carteirinha LiberaIFSC)
                            if (valid && typeId === '22') {
                                const decl = document.getElementById('libera_declaration');
                                if (decl && !decl.checked) {
                                    alert('Você deve marcar a declaração de ciência para prosseguir.');
                                    valid = false;
                                }
                            }

                            // horario diferenciado: ao menos 1 horario
                            if (valid && typeId === '20') {
                                const times = ['arrival_time_1','arrival_time_2','departure_time_1','departure_time_2']
                                    .map(id => document.getElementById(id)?.value || '');
                                if (times.every(t => !t)) {
                                    alert('Por favor, preencha pelo menos um horário.');
                                    valid = false;
                                }
                            }

                            // assistencia estudantil: ao menos 1 tipo
                            if (valid && typeId === '21') {
                                const checked = typeDiv.querySelectorAll('input[name="assistance_type[]"]:checked');
                                const errEl = document.getElementById('assistance-error');
                                if (checked.length === 0) {
                                    if (errEl) errEl.classList.remove('hidden');
                                    typeDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
                                    valid = false;
                                } else {
                                    if (errEl) errEl.classList.add('hidden');
                                }
                            }
                        }

                        // description obrigatoria
                        const desc = document.getElementById('description');
                        if (valid && desc && !desc.value.trim()) {
                            desc.reportValidity();
                            valid = false;
                        }
                    }

                    if (!valid) return;
                }

                // Ao entrar no step 3, renderiza campos do tipo
                if (stepNumber === 3) {
                    renderTypeFields(requestTypeSelect.value);
                    // Atualiza titulo dinamico
                    const typeName = requestTypeSelect.options[requestTypeSelect.selectedIndex]?.dataset?.name || 'Detalhes da Solicitação';
                    document.getElementById('step3-title').textContent = requestTypeSelect.value === '22' ? 'Detalhes e Anexos' : 'Detalhes: ' + typeName;
                }

                // Ao entrar no step 4, mostra/oculta secao do responsavel
                if (stepNumber === 4) {
                    const isAdult = document.getElementById('is_adult');
                    const guardianSection = document.getElementById('guardian-section');
                    if (guardianSection) guardianSection.classList.toggle('hidden', isAdult.checked);
                }

                // Atualiza Visibility
                steps.forEach((s, i) => {
                    s.classList.toggle('hidden', (i + 1) !== stepNumber);
                });

                // Atualiza Progress UI (0% → 33% → 66% → 100%)
                const progressWidths = { 1: '0%', 2: '33%', 3: '66%', 4: '100%' };
                progressLine.style.width = progressWidths[stepNumber] || '0%';

                dots.forEach((dot, i) => {
                    const text = dot.nextElementSibling;
                    if ((i + 1) <= stepNumber) {
                        dot.classList.add('bg-[#1CBB9B]', 'text-white');
                        dot.classList.remove('bg-white', 'text-gray-400', 'border-gray-200');
                        dot.classList.add('border-[#1CBB9B]');
                        text.classList.add('text-[#1CBB9B]');
                        text.classList.remove('text-gray-400');
                    } else {
                        dot.classList.remove('bg-[#1CBB9B]', 'text-white', 'border-[#1CBB9B]');
                        dot.classList.add('bg-white', 'text-gray-400', 'border-gray-200');
                        text.classList.remove('text-[#1CBB9B]');
                        text.classList.add('text-gray-400');
                    }
                });

                window.scrollTo({ top: 0, behavior: 'smooth' });
            };

            // Widget de multi-selecao: grid de pills clicaveis com toggle de estado
            function buildMultiSelect(containerId, items, fieldName) {
                const container = document.getElementById(containerId);
                if (!container) return;

                container.innerHTML =
                    '<input type="text" class="ms-search w-full border border-gray-200 rounded-md px-3 py-2 text-sm mb-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none bg-white" placeholder="Buscar...">' +
                    '<div class="ms-wrapper max-h-72 overflow-y-auto border border-gray-200 rounded-md bg-gray-50/50 p-2 space-y-5"></div>' +
                    '<p class="ms-count text-xs text-gray-400 mt-1.5 text-right">Nenhuma selecionada</p>' +
                    '<div class="ms-inputs"></div>' +
                    '<p class="ms-hint text-xs text-gray-400 mt-1.5">Use o campo acima para filtrar. Clique nas opções para selecioná-las.</p>' +
                    '<p class="ms-error hidden text-xs text-red-600 font-medium mt-1">Selecione ao menos uma opção acima.</p>';

                const searchInput = container.querySelector('.ms-search');
                const wrapperEl  = container.querySelector('.ms-wrapper');
                const countEl    = container.querySelector('.ms-count');
                const inputsEl   = container.querySelector('.ms-inputs');
                let selectedCount = 0;
                const normalize = s => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

                // Group items by period
                const groups = {};
                const noGroup = [];
                items.forEach(item => {
                    if (item.period) {
                        (groups[item.period] = groups[item.period] || []).push(item);
                    } else {
                        noGroup.push(item);
                    }
                });
                const sortedPeriods = Object.keys(groups).sort((a, b) => (parseInt(a) || 99) - (parseInt(b) || 99));

                // Create a section div per group; returns the inner grid element
                function makeSection(label) {
                    const section = document.createElement('div');
                    section.className = 'ms-group';

                    if (label) {
                        const hdr = document.createElement('div');
                        hdr.className = 'text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 px-1 pb-1.5 border-b-2 border-gray-300';
                        hdr.textContent = label;
                        section.appendChild(hdr);
                    }

                    const grid = document.createElement('div');
                    grid.className = 'grid grid-cols-2 md:grid-cols-3 gap-1.5';
                    section.appendChild(grid);
                    wrapperEl.appendChild(section);
                    return grid;
                }

                function addPill(item, targetGrid) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'ms-item w-full text-left px-2.5 py-2 rounded-md text-xs border border-gray-200 text-gray-700 flex items-center justify-between gap-2 leading-snug cursor-pointer';
                    btn.dataset.value = item.id;
                    btn.dataset.label = item.name;
                    btn.dataset.norm  = normalize(item.name);
                    btn.dataset.period = item.period || '';

                    const nameSpan = document.createElement('span');
                    nameSpan.textContent = item.name;

                    const check = document.createElement('span');
                    check.className = 'ms-check flex-shrink-0 font-bold';
                    check.style.opacity = '0';
                    check.textContent = '✓';

                    btn.appendChild(nameSpan);
                    btn.appendChild(check);
                    btn.addEventListener('click', () => toggleItem(btn));
                    targetGrid.appendChild(btn);
                }

                sortedPeriods.forEach(p => {
                    const grid = makeSection(p);
                    groups[p].forEach(item => addPill(item, grid));
                });
                if (noGroup.length) {
                    const grid = makeSection(sortedPeriods.length ? 'Outras' : '');
                    noGroup.forEach(item => addPill(item, grid));
                }

                function toggleItem(btn) {
                    const value = btn.dataset.value;
                    if (btn.classList.contains('ms-selected')) {
                        btn.classList.remove('ms-selected');
                        btn.querySelector('.ms-check').style.opacity = '0';
                        const inp = inputsEl.querySelector('input[data-val="' + value + '"]');
                        if (inp) inp.remove();
                        selectedCount--;
                    } else {
                        btn.classList.add('ms-selected');
                        btn.querySelector('.ms-check').style.opacity = '1';
                        wrapperEl.classList.remove('border-red-400');
                        container.querySelector('.ms-error')?.classList.add('hidden');
                        const hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = fieldName;
                        hidden.value = value;
                        hidden.dataset.val = value;
                        inputsEl.appendChild(hidden);
                        selectedCount++;
                    }
                    countEl.textContent = selectedCount === 0 ? 'Nenhuma selecionada'
                        : selectedCount === 1 ? '1 selecionada'
                        : selectedCount + ' selecionadas';
                }

                searchInput.addEventListener('input', () => {
                    const q = normalize(searchInput.value);
                    wrapperEl.querySelectorAll('.ms-item').forEach(btn => {
                        btn.classList.toggle('hidden', q !== '' && !btn.dataset.norm.includes(q));
                    });
                    // Hide entire group section when all its pills are hidden
                    wrapperEl.querySelectorAll('.ms-group').forEach(grp => {
                        const pills = grp.querySelectorAll('.ms-item');
                        const allHidden = pills.length > 0 && [...pills].every(p => p.classList.contains('hidden'));
                        grp.classList.toggle('hidden', allHidden);
                    });
                });
            }

            // Mostra/oculta campos especificos por tipo e constroi widgets
            function renderTypeFields(typeId) {
                const typeDivs = document.querySelectorAll('[id^="fields-type-"]');
                typeDivs.forEach(d => d.classList.add('hidden'));
                if (!typeId) return;

                const target = document.getElementById('fields-type-' + typeId);
                if (!target) return;
                target.classList.remove('hidden');

                const courseId = document.getElementById('course_select').value;
                const filteredSubjects = courseId
                    ? ALL_SUBJECTS.filter(s => String(s.course_id) === String(courseId))
                    : ALL_SUBJECTS;

                if (typeId === '25') {
                    buildMultiSelect('ms-subjects-type-25', filteredSubjects, 'selected_subjects[]');
                }
                if (typeId === '2') {
                    buildMultiSelect('ms-subjects-type-2', filteredSubjects, 'selected_subjects[]');
                    buildMultiSelect('ms-teachers-type-2', ALL_TEACHERS, 'selected_teachers[]');
                }
                if (['3', '15', '16', '17'].includes(typeId)) {
                    buildMultiSelect('ms-subjects-type-' + typeId, filteredSubjects, 'selected_subjects[]');
                }

                const descPlaceholders = {
                    '1':  'Ex: Estive ausente nos dias indicados por [motivo]. Se houver atestado médico, mencione o período coberto.',
                    '2':  'Ex: A avaliação ocorreu em [data] às [horário]. Estive ausente porque [motivo]. Se houver atestado médico, informe o período de afastamento coberto pelo documento.',
                    '3':  'Ex: Estive impossibilitado(a) de frequentar as aulas no período indicado por [motivo]. Solicito atividades domiciliares para as UCs selecionadas.',
                    '4':  'Ex: Informações adicionais sobre o motivo do trancamento que julgar relevantes.',
                    '5':  'Ex: Informações adicionais sobre o motivo do cancelamento que julgar relevantes.',
                    '6':  'Ex: Complemento sobre os motivos da transferência, como dificuldades específicas ou preferências.',
                    '7':  'Ex: Informe o motivo pelo qual deseja cursar a(s) UC(s) de forma isolada e qualquer informação adicional relevante.',
                    '8':  'Ex: Informe se há alguma observação ou instrução especial para expedição do documento solicitado.',
                    '9':  'Ex: Informe qualquer detalhe adicional que ajude na análise da sua solicitação de validação.',
                    '10': 'Ex: Estou ciente das condições de retorno e confirmo minha intenção de retomar o curso no período indicado.',
                    '11': 'Ex: Descreva brevemente os motivos do afastamento e sua situação atual.',
                    '12': 'Ex: Informe o motivo pelo qual deseja a matrícula especial e qualquer informação adicional relevante.',
                    '13': 'Ex: Descreva as dificuldades específicas que enfrenta e o tipo de apoio que acredita necessitar.',
                    '14': 'Ex: Informe o motivo dos ajustes solicitados e qualquer circunstância relevante para a análise.',
                    '15': 'Ex: Descreva o motivo pelo qual necessita de planos de estudo e qualquer informação relevante ao período.',
                    '17': 'Ex: Descreva por que acredita ter condições de cursar a UC sem o pré-requisito (conhecimentos prévios, experiência, etc.).',
                    '18': 'Ex: Informe qualquer informação adicional relevante sobre sua situação de colação de grau.',
                    '20': 'Ex: Informe o motivo pelo qual necessita do horário diferenciado e qualquer circunstância relevante.',
                    '21': 'Ex: Descreva brevemente sua situação socioeconômica e os motivos pelos quais solicita a assistência estudantil.',
                    '16': 'Ex: Descreva brevemente seu domínio do conteúdo da UC e por que acredita ter condições de ser avaliado(a) de forma extraordinária.',
                    '19': 'Descreva detalhadamente sua solicitação, incluindo todas as informações necessárias para que a coordenação possa analisar o pedido.',
                    '22': 'Ex: Informações adicionais ou observações sobre a solicitação da carteirinha de saída antecipada.',
                    '25': 'Ex: Informe o motivo pelo qual deseja cancelar a matrícula nas UCs selecionadas.',
                };
                const desc = document.getElementById('description');
                if (desc) desc.placeholder = descPlaceholders[typeId] || 'Descreva sua solicitação com o máximo de detalhes possível...';

                const nextBtn    = document.getElementById('step3-next-btn');
                const submitBtn3 = document.getElementById('step3-submit-btn');
                if (typeId === '22') {
                    nextBtn?.classList.add('hidden');
                    submitBtn3?.classList.remove('hidden');
                    const guardianNameEl  = document.getElementById('guardian_name');
                    const guardianPhoneEl = document.getElementById('guardian_phone');
                    if (guardianNameEl)  guardianNameEl.required  = false;
                    if (guardianPhoneEl) guardianPhoneEl.required = false;
                } else {
                    nextBtn?.classList.remove('hidden');
                    submitBtn3?.classList.add('hidden');
                }
            }

            // Checkbox "professor nao listado"
            const chk2 = document.getElementById('teacher_other_check_2');
            const inp2 = document.getElementById('teacher_other_name_2');
            if (chk2 && inp2) {
                chk2.addEventListener('change', () => inp2.classList.toggle('hidden', !chk2.checked));
            }

            // Checkbox "UC nao listada" (type 2)
            const ucChk2 = document.getElementById('uc_other_check_2');
            const ucInp2 = document.getElementById('uc_other_name_2');
            if (ucChk2 && ucInp2) {
                ucChk2.addEventListener('change', () => ucInp2.classList.toggle('hidden', !ucChk2.checked));
            }

            // Upload do termo assinado (type 22)
            const type22FileUpload = document.getElementById('type22-file-upload');
            if (type22FileUpload) {
                type22FileUpload.addEventListener('change', function () {
                    const list = document.getElementById('type22-file-list');
                    const tmp  = document.getElementById('type22-temp-files-container');
                    [...this.files].forEach(file => {
                        const fileId = 'file-' + Math.random().toString(36).substr(2, 9);
                        uploadFileAsync(file, list, tmp, fileId);
                    });
                    this.value = '';
                    document.getElementById('type22-upload-error')?.classList.add('hidden');
                });
            }

            // Botao de envio direto do step 3 (type 22)
            document.getElementById('step3-submit-btn')?.addEventListener('click', function () {
                const step3 = document.getElementById('form-step-3');
                let valid = true;
                step3.querySelectorAll('input[required], select[required], textarea[required]').forEach(el => {
                    if (!el.checkValidity() && !el.closest('.hidden')) {
                        el.reportValidity();
                        valid = false;
                    }
                });
                if (!valid) return;

                const cienteEl = document.getElementById('libera_ciente');
                const cienteErr = document.getElementById('libera_ciente_error');
                if (cienteEl && cienteEl.value.trim().toUpperCase() !== 'CIENTE') {
                    cienteErr?.classList.remove('hidden');
                    cienteEl.focus();
                    return;
                } else {
                    cienteErr?.classList.add('hidden');
                }

                const decl = document.getElementById('libera_declaration');
                if (decl && !decl.checked) {
                    alert('Marque a declaração de ciência para prosseguir.');
                    return;
                }

                const uploadedFiles = document.querySelectorAll('#type22-temp-files-container input[type="hidden"]');
                const errUpload = document.getElementById('type22-upload-error');
                if (uploadedFiles.length === 0) {
                    errUpload?.classList.remove('hidden');
                    document.getElementById('type22-upload-container')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    return;
                } else {
                    errUpload?.classList.add('hidden');
                }

                const pending = document.querySelectorAll('.upload-progress-container:not(.hidden)');
                if (pending.length > 0) {
                    alert('Aguarde o término dos uploads em andamento.');
                    return;
                }

                document.getElementById('step3-btn-text').textContent = 'Enviando...';
                document.getElementById('step3-btn-spinner')?.classList.remove('hidden');
                this.disabled = true;
                form.submit();
            });

            // Radios de validacao tipo 9
            ['val_re','val_rs','val_eae'].forEach(rid => {
                const radio = document.getElementById(rid);
                if (!radio) return;
                radio.addEventListener('change', () => {
                    document.getElementById('uc_re_block').classList.add('hidden');
                    document.getElementById('uc_rs_block').classList.add('hidden');
                    document.getElementById('uc_eae_block').classList.add('hidden');
                    const map = { val_re: 'uc_re_block', val_rs: 'uc_rs_block', val_eae: 'uc_eae_block' };
                    document.getElementById(map[rid]).classList.remove('hidden');
                });
            });

            // Logica do Responsavel
            const isAdultCheck = document.getElementById('is_adult');
            const guardianFields = document.getElementById('guardian_fields');
            const guardianName = document.getElementById('guardian_name');
            const guardianPhone = document.getElementById('guardian_phone');

            function toggleGuardian() {
                if (isAdultCheck.checked) {
                    guardianFields.classList.add('hidden');
                    guardianName.required = false;
                    guardianPhone.required = false;
                } else {
                    guardianFields.classList.remove('hidden');
                    guardianName.required = true;
                    guardianPhone.required = true;
                }
            }
            isAdultCheck.addEventListener('change', toggleGuardian);
            toggleGuardian();

            // Logica de Nivel e Curso
            const levelSelect = document.getElementById('level_select');
            const courseSelect = document.getElementById('course_select');
            const courseOptions = Array.from(courseSelect.options);

            levelSelect.addEventListener('change', function () {
                const selectedLevel = this.value;
                courseSelect.value = "";
                if (!selectedLevel) {
                    courseSelect.disabled = true;
                    courseSelect.options[0].text = "Selecione o nível primeiro...";
                    return;
                }
                courseSelect.disabled = false;
                courseSelect.options[0].text = "Selecione seu curso...";
                courseOptions.forEach(option => {
                    if (option.value === "") return;
                    option.style.display = (option.dataset.level === selectedLevel) ? 'block' : 'none';
                });
            });

            // Logica dos Cartoes de Selecao de Tipo
            const typeSearch = document.getElementById('type_search');
            const typeCards = document.querySelectorAll('.type-card');
            const requestTypeSelect = document.getElementById('request_type_select');

            typeSearch.addEventListener('input', function () {
                const term = this.value.toLowerCase().trim();
                if (term.length > 0) {
                    typeCards.forEach(c => c.classList.remove('selected', 'error-ring', 'border-red-400'));
                    requestTypeSelect.value = '';
                    requestTypeSelect.dispatchEvent(new Event('change'));
                }
                typeCards.forEach(card => {
                    const name = card.dataset.name || '';
                    card.classList.toggle('hidden', !name.includes(term));
                });
                // Oculta secao se todos os cards estiverem hidden
                ['section-featured','section-other'].forEach(sid => {
                    const sec = document.getElementById(sid);
                    if (!sec) return;
                    const visible = sec.querySelectorAll('.type-card:not(.hidden)');
                    sec.classList.toggle('hidden', visible.length === 0);
                });
            });

            typeCards.forEach(card => {
                card.addEventListener('click', function () {
                    typeCards.forEach(c => c.classList.remove('selected', 'error-ring', 'border-red-400'));
                    document.getElementById('type_error_message').classList.add('hidden');
                    document.getElementById('types_grid')?.classList.remove('border-red-200', 'bg-red-50/10');
                    this.classList.add('selected');
                    requestTypeSelect.value = this.dataset.id;
                    requestTypeSelect.dispatchEvent(new Event('change'));
                });
            });

            requestTypeSelect.addEventListener('change', function () {
                const selectedOption = this.options[this.selectedIndex];
                const info = selectedOption.dataset.info || '';
                const attention = selectedOption.dataset.attention || '';
                const infoContainer = document.getElementById('type_info_container');
                const infoContent = document.getElementById('info_content');
                const attentionBox = document.getElementById('attention_box');
                const attentionContent = document.getElementById('attention_content');

                if (info || attention) {
                    infoContainer.classList.remove('hidden');
                    infoContent.innerHTML = info;
                    infoContent.classList.toggle('hidden', !info);
                    if (attention) {
                        attentionContent.innerHTML = attention;
                        attentionBox.classList.remove('hidden');
                    } else {
                        attentionBox.classList.add('hidden');
                    }
                } else {
                    infoContainer.classList.add('hidden');
                }
            });

            // Estado de Carregamento da Submissao do Formulario
            const submitBtn = document.getElementById('submit_btn');
            const btnText = document.getElementById('btn_text');
            const btnSpinner = document.getElementById('btn_spinner');

            form.addEventListener('submit', function (e) {
                if (form.checkValidity()) {
                    // Verifica se algum upload ainda esta em andamento
                    const pendingUploads = document.querySelectorAll('.upload-progress-container:not(.hidden)');
                    if (pendingUploads.length > 0) {
                        e.preventDefault();
                        alert('Aguarde o término dos uploads em andamento.');
                        return;
                    }

                    // Valida declaracao do responsavel para menores
                    const isAdultEl = document.getElementById('is_adult');
                    if (!isAdultEl.checked) {
                        const guardianInputs = document.querySelectorAll('#guardian-temp-files-container input[type="hidden"]');
                        if (guardianInputs.length === 0) {
                            e.preventDefault();
                            alert('Por favor, anexe a Declaração de Ciência do Responsável assinada via Gov.br.');
                            document.getElementById('guardian-section').scrollIntoView({ behavior: 'smooth' });
                            return;
                        }
                    }

                    submitBtn.disabled = true;
                    btnText.textContent = 'Enviando...';
                    btnSpinner.classList.remove('hidden');
                }
            });

            // Nova Logica Assincrona de Anexos
            const fileUpload = document.getElementById('file-upload');
            const fileList = document.getElementById('file-list');
            const tempFilesContainer = document.getElementById('temp-files-container');
            const guardianFileUpload = document.getElementById('guardian-file-upload');
            const guardianFileList = document.getElementById('guardian-file-list');
            const guardianTempContainer = document.getElementById('guardian-temp-files-container');

            fileUpload.addEventListener('change', function (e) {
                const files = Array.from(e.target.files);
                if (files.length === 0) return;

                files.forEach(async (file) => {
                    // Validacao client-side: Maximo 5MB
                    const maxSize = 5 * 1024 * 1024; // 5MB
                    if (file.size > maxSize) {
                        alert(`O arquivo "${file.name}" é muito grande (Máximo 5MB). Se for imagem, redimensione-a.`);
                        return; // Skip this file
                    }

                    let fileToUpload = file;
                    if (file.type.startsWith('image/')) {
                        try {
                            fileToUpload = await resizeImage(file);
                        } catch (err) {
                            console.error("Erro ao redimensionar imagem:", err);
                        }
                    }
                    uploadFileAsync(fileToUpload, fileList, tempFilesContainer);
                });

                // Limpa o input apos um curto atraso para garantir referencias estaveis
                setTimeout(() => {
                    fileUpload.value = '';
                }, 100);
            });

            if (guardianFileUpload) {
                guardianFileUpload.addEventListener('change', function (e) {
                    const files = Array.from(e.target.files);
                    if (files.length === 0) return;
                    files.forEach(file => {
                        if (file.size > 5 * 1024 * 1024) {
                            alert('Arquivo muito grande. Máximo 5MB.');
                            return;
                        }
                        uploadFileAsync(file, guardianFileList, guardianTempContainer);
                    });
                    setTimeout(() => { guardianFileUpload.value = ''; }, 100);
                });
            }

            function resizeImage(file) {
                return new Promise((resolve, reject) => {
                    const maxWidth = 1600;
                    const maxHeight = 1600;
                    const reader = new FileReader();
                    reader.readAsDataURL(file);
                    reader.onload = (event) => {
                        const img = new Image();
                        img.src = event.target.result;
                        img.onload = () => {
                            let width = img.width;
                            let height = img.height;
                            if (width <= maxWidth && height <= maxHeight) {
                                resolve(file);
                                return;
                            }
                            if (width > height) {
                                if (width > maxWidth) {
                                    height *= maxWidth / width;
                                    width = maxWidth;
                                }
                            } else {
                                if (height > maxHeight) {
                                    width *= maxHeight / height;
                                    height = maxHeight;
                                }
                            }
                            const canvas = document.createElement('canvas');
                            canvas.width = width;
                            canvas.height = height;
                            const ctx = canvas.getContext('2d');
                            ctx.drawImage(img, 0, 0, width, height);
                            canvas.toBlob((blob) => {
                                const newFile = new File([blob], file.name, {
                                    type: file.type,
                                    lastModified: Date.now()
                                });
                                resolve(newFile);
                            }, file.type, 0.8);
                        };
                        img.onerror = reject;
                    };
                    reader.onerror = reject;
                });
            }

            function uploadFileAsync(file, targetList, targetContainer) {
                targetList = targetList || fileList;
                targetContainer = targetContainer || tempFilesContainer;
                const fileId = 'file-' + Math.random().toString(36).substr(2, 9);

                // Cria elemento da UI
                const fileItem = document.createElement('div');
                fileItem.id = fileId;
                fileItem.className = 'bg-white border border-gray-200 rounded-lg p-3 shadow-sm animate-fade-in flex flex-col space-y-2';
                fileItem.innerHTML = `
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3 overflow-hidden">
                            <svg class="h-5 w-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                            <span class="text-sm font-medium text-gray-700 truncate">${file.name}</span>
                            <span class="text-xs text-gray-400">(${(file.size / 1024).toFixed(1)} KB)</span>
                        </div>
                        <button type="button" class="remove-file text-gray-400 hover:text-red-500 transition-colors">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l18 18"></path></svg>
                        </button>
                    </div>
                    <div class="upload-progress-container h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                        <div class="upload-progress h-full bg-[#1CBB9B] transition-all duration-300" style="width: 0%"></div>
                    </div>
                    <div class="upload-status text-[10px] font-bold text-gray-400 uppercase tracking-wider">Iniciando upload...</div>
                `;
                targetList.appendChild(fileItem);

                const progressBar = fileItem.querySelector('.upload-progress');
                const statusText = fileItem.querySelector('.upload-status');
                const removeBtn = fileItem.querySelector('.remove-file');

                const formData = new FormData();
                formData.append('file', file);

                const xhr = new XMLHttpRequest();
                xhr.open('POST', '<?= BASE_URL ?>/upload_temp.php', true);

                xhr.upload.onprogress = function (e) {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        progressBar.style.width = percentComplete + '%';
                        statusText.textContent = `Enviando: ${Math.round(percentComplete)}%`;
                    }
                };

                xhr.onload = function () {
                    if (xhr.status === 200) {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            statusText.textContent = 'Upload Concluído';
                            statusText.classList.remove('text-gray-400');
                            statusText.classList.add('text-green-600');
                            fileItem.querySelector('.upload-progress-container').classList.add('hidden');

                            // Adiciona input oculto
                            const hiddenInput = document.createElement('input');
                            hiddenInput.type = 'hidden';
                            hiddenInput.name = 'temp_files[]';
                            hiddenInput.value = response.temp_filename;
                            hiddenInput.dataset.fileId = fileId;
                            targetContainer.appendChild(hiddenInput);
                        } else {
                            handleUploadError(fileItem, response.message);
                        }
                    } else {
                        handleUploadError(fileItem, 'Erro de conexão com o servidor.');
                    }
                };

                xhr.onerror = function () {
                    handleUploadError(fileItem, 'Erro ao enviar arquivo.');
                };

                xhr.send(formData);

                removeBtn.addEventListener('click', function () {
                    xhr.abort();
                    fileItem.remove();
                    const hidden = targetContainer.querySelector(`input[data-file-id="${fileId}"]`);
                    if (hidden) hidden.remove();
                });
            }

            function handleUploadError(fileItem, message) {
                const statusText = fileItem.querySelector('.upload-status');
                const progressBar = fileItem.querySelector('.upload-progress');
                statusText.textContent = 'ERRO: ' + message;
                statusText.classList.remove('text-gray-400');
                statusText.classList.add('text-red-500');
                progressBar.classList.remove('bg-[#1CBB9B]');
                progressBar.classList.add('bg-red-500');
            }
        });

        // Geracao de PDF da Declaracao de Ciencia do Responsavel
        window.generateGuardianPDF = function () {
            if (!window.jspdf) {
                alert('Aguarde o carregamento da biblioteca de PDF e tente novamente.');
                return;
            }
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({ unit: 'mm', format: 'a4' });

            const pageW = 210;
            const mL = 20, mR = 20;
            const cW = pageW - mL - mR;

            // --- Logo ---
            if (LOGO_DATA) {
                const logoH = 13;
                const logoW = logoH * (1422 / 393);
                doc.addImage(LOGO_DATA, 'PNG', mL, 14, logoW, logoH);
            }

            // --- Cabecalho direito ---
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(9);
            doc.setTextColor(90, 90, 90);
            doc.text('Instituto Federal de Santa Catarina', pageW - mR, 18, { align: 'right' });
            doc.text('Câmpus Garopaba', pageW - mR, 23.5, { align: 'right' });

            // --- Linha divisoria verde ---
            doc.setDrawColor(28, 187, 155);
            doc.setLineWidth(0.6);
            doc.line(mL, 33, pageW - mR, 33);

            // --- Titulo ---
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(13);
            doc.setTextColor(25, 25, 25);
            doc.text('DECLARAÇÃO DE CIÊNCIA DO RESPONSÁVEL', pageW / 2, 45, { align: 'center' });

            // --- Linha divisoria cinza ---
            doc.setDrawColor(210, 210, 210);
            doc.setLineWidth(0.3);
            doc.line(mL, 51, pageW - mR, 51);

            // --- Dados do formulario ---
            const studentName  = document.getElementById('student_name')?.value  || '________________________';
            const studentId    = document.getElementById('student_id')?.value    || '________________________';
            const courseEl     = document.getElementById('course_select');
            const courseName   = courseEl?.options[courseEl.selectedIndex]?.text || '________________________';
            const guardianEl   = document.getElementById('guardian_name');
            const guardianName = guardianEl?.value || '________________________';
            const typeEl       = document.getElementById('request_type_select');
            const typeName     = typeEl?.options[typeEl.selectedIndex]?.dataset?.name || 'requerimento';

            // --- Texto principal ---
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(11);
            doc.setTextColor(35, 35, 35);

            let y = 64;
            const lh = 6.5;

            const intro = `Eu, ${guardianName}, declaro estar ciente do requerimento de "${typeName}", apresentado ao Instituto Federal de Santa Catarina – Câmpus Garopaba por:`;
            const introLines = doc.splitTextToSize(intro, cW);
            doc.text(introLines, mL, y);
            y += introLines.length * lh + 6;

            // --- Caixa com dados do aluno ---
            doc.setFillColor(243, 253, 249);
            doc.setDrawColor(28, 187, 155);
            doc.setLineWidth(0.4);
            doc.roundedRect(mL, y - 4, cW, 31, 2.5, 2.5, 'FD');

            const labelX = mL + 6;
            const valueX = mL + 62;

            doc.setFont('helvetica', 'bold');
            doc.setFontSize(10.5);
            doc.text('Nome do(a) Estudante:', labelX, y + 4.5);
            doc.setFont('helvetica', 'normal');
            doc.text(studentName, valueX, y + 4.5);

            doc.setFont('helvetica', 'bold');
            doc.text('Matrícula:', labelX, y + 13);
            doc.setFont('helvetica', 'normal');
            doc.text(studentId, valueX, y + 13);

            doc.setFont('helvetica', 'bold');
            doc.text('Curso:', labelX, y + 21.5);
            doc.setFont('helvetica', 'normal');
            const courseLines = doc.splitTextToSize(courseName, cW - 62 - 6);
            doc.text(courseLines, valueX, y + 21.5);

            y += 38;

            // --- Linha de data ---
            const months = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
            const now = new Date();
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(11);
            doc.setTextColor(35, 35, 35);
            doc.text(`Garopaba, ${now.getDate()} de ${months[now.getMonth()]} de ${now.getFullYear()}.`, mL, y);
            y += 24;

            // --- Linha de assinatura ---
            doc.setDrawColor(70, 70, 70);
            doc.setLineWidth(0.4);
            doc.line(mL, y, mL + 90, y);
            y += 5.5;
            doc.setFontSize(10.5);
            doc.setTextColor(35, 35, 35);
            doc.text(guardianName, mL, y);
            y += 5;
            doc.setFontSize(9);
            doc.setTextColor(110, 110, 110);
            doc.text('Responsável Legal', mL, y);

            // --- Rodape ---
            doc.setDrawColor(28, 187, 155);
            doc.setLineWidth(0.3);
            doc.line(mL, 282, pageW - mR, 282);
            doc.setFontSize(8);
            doc.setTextColor(19, 81, 180);
            const signText1 = 'Assine em assinador.iti.br';
            doc.textWithLink(signText1, (pageW - doc.getTextWidth(signText1)) / 2, 287, { url: 'https://assinador.iti.br' });
            doc.setTextColor(140, 140, 140);
            doc.text('Instituto Federal de Santa Catarina – Câmpus Garopaba', pageW / 2, 292, { align: 'center' });

            doc.save('declaracao-ciencia-responsavel.pdf');
        };

        window.generateSaidaAntecipadaPDF = function () {
            if (!window.jspdf) {
                alert('Aguarde o carregamento da biblioteca de PDF e tente novamente.');
                return;
            }
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({ unit: 'mm', format: 'a4' });

            const pageW = 210;
            const mL = 20, mR = 20;
            const cW = pageW - mL - mR;

            // --- Logo ---
            if (LOGO_DATA) {
                const logoH = 13;
                const logoW = logoH * (1422 / 393);
                doc.addImage(LOGO_DATA, 'PNG', mL, 14, logoW, logoH);
            }

            // --- Cabecalho direito ---
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(9);
            doc.setTextColor(90, 90, 90);
            doc.text('Instituto Federal de Santa Catarina', pageW - mR, 18, { align: 'right' });
            doc.text('Câmpus Garopaba', pageW - mR, 23.5, { align: 'right' });

            // --- Linha divisoria verde ---
            doc.setDrawColor(28, 187, 155);
            doc.setLineWidth(0.6);
            doc.line(mL, 33, pageW - mR, 33);

            // --- Titulo ---
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(13);
            doc.setTextColor(25, 25, 25);
            doc.text('TERMO DE AUTORIZAÇÃO DE SAÍDA ANTECIPADA', pageW / 2, 45, { align: 'center' });

            // --- Linha divisoria cinza ---
            doc.setDrawColor(210, 210, 210);
            doc.setLineWidth(0.3);
            doc.line(mL, 51, pageW - mR, 51);

            // --- Dados do formulario ---
            const studentName  = document.getElementById('student_name')?.value  || '________________________';
            const courseEl     = document.getElementById('course_select');
            const courseName   = courseEl?.options[courseEl.selectedIndex]?.text || '________________________';
            const guardianName = document.getElementById('libera_guardian_name')?.value || '________________________';

            let y = 62;
            const lh = 6.5;

            doc.setFont('helvetica', 'normal');
            doc.setFontSize(11);
            doc.setTextColor(35, 35, 35);

            // --- Texto de autorização ---
            const para1 = `Autorizo a saída antecipada do/a estudante ${studentName}, matriculado/a no ${courseName}, menor de idade pelo qual sou responsável legal, das dependências do IFSC Câmpus Garopaba, nas seguintes situações:`;
            const para1Lines = doc.splitTextToSize(para1, cW);
            doc.text(para1Lines, mL, y);
            y += para1Lines.length * lh + 4;

            // --- Lista de situações ---
            const situacoes = [
                'Realização de trabalhos acadêmicos fora de horário de aula;',
                'Troca de horário de aulas;',
                'Ausência de professores/as;',
                'Para atendimento extraclasse com docente, monitoria, pendência,\n   atividade esportiva e projetos;',
                'Finalização antecipada de avaliações.',
            ];
            doc.setFontSize(10.5);
            situacoes.forEach((sit, i) => {
                const lines = doc.splitTextToSize(`${i + 1}. ${sit}`, cW - 6);
                doc.text(lines, mL + 6, y);
                y += lines.length * 6 + 1.5;
            });
            y += 3;

            // --- Estou ciente ---
            doc.setFontSize(11);
            doc.setFont('helvetica', 'bold');
            doc.text('Estou ciente de que:', mL, y);
            y += lh;
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(10.5);

            const ciencias = [
                'Sou responsável por orientar o/a estudante a não usar desta autorização para ausentar-se de atividades acadêmicas programadas, inclusive aulas;',
                'Para cancelar esta autorização é necessário comparecer à Coordenadoria Pedagógica do Câmpus.',
            ];
            ciencias.forEach((txt, i) => {
                const lines = doc.splitTextToSize(`${i + 1}) ${txt}`, cW - 6);
                doc.text(lines, mL + 6, y);
                y += lines.length * 6 + 2;
            });
            y += 10;

            // --- Dados do responsável ---
            doc.setFillColor(243, 253, 249);
            doc.setDrawColor(28, 187, 155);
            doc.setLineWidth(0.4);
            doc.roundedRect(mL, y - 4, cW, 22, 2.5, 2.5, 'FD');
            const labelX = mL + 6;
            const valueX = mL + 52;
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(10.5);
            doc.setTextColor(35, 35, 35);
            doc.text('Responsável:', labelX, y + 4);
            doc.setFont('helvetica', 'normal');
            doc.text(guardianName, valueX, y + 4);
            doc.setFont('helvetica', 'bold');
            doc.text('Estudante:', labelX, y + 13);
            doc.setFont('helvetica', 'normal');
            doc.text(studentName, valueX, y + 13);
            y += 28;

            // --- Linha de assinatura ---
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(10);
            doc.setTextColor(90, 90, 90);
            doc.text('_____________________', mL, y);
            y += 5.5;
            doc.setFontSize(9);
            doc.setTextColor(70, 70, 70);
            doc.text('Assinatura feita no gov.br', mL, y);
            y += 5;
            // Link clicável para o assinador
            doc.setTextColor(19, 81, 180);
            doc.textWithLink('Clique aqui para assinar', mL, y, { url: 'https://assinador.iti.br' });

            // --- Rodape ---
            doc.setDrawColor(28, 187, 155);
            doc.setLineWidth(0.3);
            doc.line(mL, 282, pageW - mR, 282);
            doc.setFontSize(8);
            doc.setTextColor(140, 140, 140);
            doc.text('Assine digitalmente via Gov.br em assinador.iti.br', pageW / 2, 287, { align: 'center' });
            doc.text('Instituto Federal de Santa Catarina – Câmpus Garopaba', pageW / 2, 292, { align: 'center' });

            doc.save('termo-autorizacao-saida-antecipada.pdf');
        };
    </script>
</body>

</html>