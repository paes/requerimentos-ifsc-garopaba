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
$typesQuery = "SELECT id, name, information, attention FROM request_types WHERE active = 1 ORDER BY CASE WHEN id = 19 THEN 1 ELSE 0 END, name ASC";
$typesStmt = $conn->prepare($typesQuery);
$typesStmt->execute();
$requestTypes = $typesStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IFSC - Sistema de Requerimentos</title>
    <?php if (ENABLE_TURNSTILE): ?>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/favicon.ico" type="image/x-icon">

</head>

<body class="gradient-bg min-h-screen text-gray-800">

    <!-- Header -->
    <header class="bg-[#1CBB9B] text-white shadow-lg">
        <div class="container mx-auto px-6 py-2 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <img src="<?= BASE_URL ?>/assets/img/logob.png" alt="IFSC Logo"
                    class="h-14 brightness-0 invert opacity-90 hover:opacity-100 transition-opacity">
                <div class="hidden md:block border-l border-white/30 pl-4">
                    <h1 class="text-lg font-bold tracking-tight">Instituto Federal de Santa Catarina</h1>
                    <p class="text-white/80 text-xs font-medium uppercase tracking-wider">Sistema de Requerimentos</p>
                </div>
            </div>
            <div class="flex items-center space-x-6">
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
                    <span
                        class="absolute top-10 mt-2 left-1/2 -translate-x-1/2 w-max text-[10px] md:text-xs font-bold text-[#1CBB9B] uppercase tracking-wider">Identificação</span>
                </div>
                <div class="relative flex flex-col items-center">
                    <div id="step-dot-2"
                        class="w-10 h-10 rounded-full bg-white border-2 border-gray-200 text-gray-400 flex items-center justify-center font-bold shadow-sm transition-all duration-300">
                        2</div>
                    <span
                        class="absolute top-10 mt-2 left-1/2 -translate-x-1/2 w-max text-[10px] md:text-xs font-bold text-gray-400 uppercase tracking-wider">Solicitação</span>
                </div>
                <div class="relative flex flex-col items-center">
                    <div id="step-dot-3"
                        class="w-10 h-10 rounded-full bg-white border-2 border-gray-200 text-gray-400 flex items-center justify-center font-bold shadow-sm transition-all duration-300">
                        3</div>
                    <span
                        class="absolute top-10 mt-2 left-1/2 -translate-x-1/2 w-max text-[10px] md:text-xs font-bold text-gray-400 uppercase tracking-wider">Anexos</span>
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
                                        class="block text-sm font-medium text-gray-700 mb-2 group-focus-within:text-[#1CBB9B] transition-colors">Turma,
                                        Módulo ou Ano</label>
                                    <input type="text" name="class_info"
                                        class="w-full bg-gray-50 border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent transition-all outline-none"
                                        placeholder="Ex: 1ª Fase, 2º Ano, Módulo 3">
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

                <!-- ETAPA 2: DETALHES DA SOLICITAÇÃO -->
                <div id="form-step-2" class="hidden space-y-8 animate-fade-in">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-800 mb-6 flex items-center">
                            <span
                                class="bg-[#1CBB9B] text-white rounded-full w-8 h-8 flex items-center justify-center text-sm mr-3">2</span>
                            Detalhes da Solicitação
                        </h3>
                        <div class="space-y-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-4">Escolha o Tipo de
                                    Requerimento <span class="text-red-500">*</span></label>

                                <div class="space-y-5">
                                    <!-- Search Box -->
                                    <div class="relative">
                                        <span
                                            class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                            </svg>
                                        </span>
                                        <input type="text" id="type_search"
                                            placeholder="Comece a digitar para encontrar..."
                                            class="w-full bg-white border border-gray-200 rounded-xl pl-10 pr-4 py-3 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent transition-all outline-none shadow-sm text-sm">
                                    </div>

                                    <!-- Grid Container -->
                                    <div id="types_grid"
                                        class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 p-1 pr-2 border border-transparent rounded-xl transition-all">
                                        <?php foreach ($requestTypes as $type): ?>
                                            <div class="type-card cursor-pointer group border rounded-xl p-3 hover:shadow-md hover:-translate-y-0.5 transition-all flex items-center min-h-[70px] relative overflow-hidden"
                                                data-id="<?= $type['id'] ?>"
                                                data-name="<?= strtolower(htmlspecialchars($type['name'])) ?>">
                                                <div
                                                    class="absolute left-0 top-0 w-1 h-full bg-[#1CBB9B] opacity-0 group-[.selected]:opacity-100 transition-opacity">
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p
                                                        class="text-xs sm:text-sm font-semibold text-gray-700 group-hover:text-[#1CBB9B] group-[.selected]:text-[#1CBB9B] transition-colors leading-tight line-clamp-2">
                                                        <?= htmlspecialchars($type['name']) ?>
                                                    </p>
                                                </div>
                                                <div class="hidden group-[.selected]:block ml-2 text-[#1CBB9B]">
                                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd"
                                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                            clip-rule="evenodd"></path>
                                                    </svg>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <div id="type_error_message"
                                        class="hidden text-red-500 text-xs font-bold animate-fade-in flex items-center mt-2">
                                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                                clip-rule="evenodd"></path>
                                        </svg>
                                        Por favor, selecione uma opção antes de prosseguir.
                                    </div>

                                    <!-- Original Select (Hidden but updated by cards) -->
                                    <select name="request_type_id" id="request_type_select" required class="hidden">
                                        <option value="">Selecione...</option>
                                        <?php foreach ($requestTypes as $type): ?>
                                            <option value="<?= $type['id'] ?>"
                                                data-info="<?= htmlspecialchars($type['information']) ?>"
                                                data-attention="<?= htmlspecialchars($type['attention']) ?>">
                                                <?= htmlspecialchars($type['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div id="type_info_container" class="hidden animate-fade-in-down">
                                <div class="bg-blue-50 border border-blue-100 rounded-md p-6 shadow-sm">
                                    <div id="info_content"
                                        class="text-[#293087] prose prose-sm prose-blue max-w-none leading-relaxed mb-4">
                                    </div>
                                    <div id="attention_box"
                                        class="hidden bg-red-50 border border-red-100 rounded-md p-4 mt-6">
                                        <div class="text-red-800 text-sm leading-relaxed">
                                            <strong class="font-bold block mb-2">ATENÇÃO:</strong>
                                            <div id="attention_content" class="prose prose-sm prose-red max-w-none">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="justification_fields" class="hidden space-y-6 animate-fade-in-down">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="group">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Data Início</label>
                                        <input type="date" name="start_date" id="start_date"
                                            class="w-full bg-gray-50 border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent transition-all outline-none">
                                    </div>
                                    <div class="group">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Data Final</label>
                                        <input type="date" name="end_date" id="end_date"
                                            class="w-full bg-gray-50 border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent transition-all outline-none">
                                    </div>
                                </div>
                                <div class="group">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Unidades
                                        Curriculares</label>
                                    <input type="text" name="subjects" id="subjects"
                                        class="w-full bg-gray-50 border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent transition-all outline-none"
                                        placeholder="Ex: Matemática, Programação...">
                                </div>
                            </div>

                            <div id="schedule_fields"
                                class="hidden space-y-6 animate-fade-in-down bg-gray-50/50 p-6 rounded-xl border border-gray-100">
                                <div class="group">
                                    <label class="block text-sm font-bold text-gray-700 mb-4">Tipo de Solicitação de
                                        Horário <span class="text-red-500">*</span></label>
                                    <div class="flex flex-wrap gap-6">
                                        <label class="flex items-center cursor-pointer group">
                                            <input type="radio" name="schedule_type" value="Chegada tardia"
                                                class="w-5 h-5 text-[#1CBB9B] focus:ring-[#1CBB9B] border-gray-300">
                                            <span
                                                class="ml-2 text-sm text-gray-700 group-hover:text-gray-900 transition-colors">Chegada
                                                tardia</span>
                                        </label>
                                        <label class="flex items-center cursor-pointer group">
                                            <input type="radio" name="schedule_type" value="Saída antecipada"
                                                class="w-5 h-5 text-[#1CBB9B] focus:ring-[#1CBB9B] border-gray-300">
                                            <span
                                                class="ml-2 text-sm text-gray-700 group-hover:text-gray-900 transition-colors">Saída
                                                antecipada</span>
                                        </label>
                                        <label class="flex items-center cursor-pointer group">
                                            <input type="radio" name="schedule_type" value="Entrada e Saída"
                                                class="w-5 h-5 text-[#1CBB9B] focus:ring-[#1CBB9B] border-gray-300">
                                            <span
                                                class="ml-2 text-sm text-gray-700 group-hover:text-gray-900 transition-colors">Entrada
                                                e Saída</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="space-y-4">
                                        <div class="group">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Horário
                                                Chegada</label>
                                            <input type="time" name="arrival_time_1" id="arrival_time_1"
                                                class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent transition-all outline-none">
                                        </div>
                                        <div class="group">
                                            <label class="block text-sm font-medium text-gray-600 mb-2">Horário Chegada
                                                2 <span
                                                    class="text-[10px] text-gray-400 font-normal">(Integrados)</span></label>
                                            <input type="time" name="arrival_time_2" id="arrival_time_2"
                                                class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent transition-all outline-none">
                                        </div>
                                    </div>
                                    <div class="space-y-4">
                                        <div class="group">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Horário
                                                Saída</label>
                                            <input type="time" name="departure_time_1" id="departure_time_1"
                                                class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent transition-all outline-none">
                                        </div>
                                        <div class="group">
                                            <label class="block text-sm font-medium text-gray-600 mb-2">Horário Saída 2
                                                <span
                                                    class="text-[10px] text-gray-400 font-normal">(Integrados)</span></label>
                                            <input type="time" name="departure_time_2" id="departure_time_2"
                                                class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent transition-all outline-none">
                                        </div>
                                    </div>
                                </div>

                                <p
                                    class="text-[11px] text-[#293087] bg-blue-50/50 p-3 rounded border border-blue-100 italic">
                                    * Pelo menos um horário deve ser preenchido de acordo com sua necessidade.
                                </p>

                                <div class="bg-[#1CBB9B]/5 p-4 rounded-xl border border-[#1CBB9B]/20">
                                    <label class="flex items-start cursor-pointer group">
                                        <div class="flex items-center h-5">
                                            <input type="checkbox" id="declaration_accepted" name="declaration_accepted"
                                                class="h-5 w-5 text-[#1CBB9B] focus:ring-[#1CBB9B] border-gray-300 rounded transition-all">
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <p class="font-medium text-gray-800">Declaração</p>
                                            <p class="text-gray-600 leading-relaxed text-xs">Declaro que as informações
                                                fornecidas são verdadeiras e estou ciente de que a aprovação está
                                                sujeita à análise da coordenação do curso. Comprometo-me também a
                                                cumprir os horários aqui solicitados.</p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div class="group">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Descrição Detalhada <span
                                        class="text-red-500">*</span></label>
                                <textarea name="description" id="description" required rows="5"
                                    class="w-full bg-gray-50 border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent transition-all outline-none resize-none"
                                    placeholder="Descreva sua solicitação com o máximo de detalhes possível..."></textarea>
                            </div>
                        </div>

                        <div class="pt-6 border-t border-gray-100 flex justify-between items-center">
                            <button type="button" onclick="goToStep(1)"
                                class="text-gray-500 font-bold hover:text-gray-700 transition-all flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 19l-7-7 7-7"></path>
                                </svg>
                                Voltar
                            </button>
                            <button type="button" onclick="goToStep(3)"
                                class="bg-[#1CBB9B] text-white px-10 py-3 rounded-md font-bold hover:bg-[#169C80] transition-all shadow-md hover:shadow-lg flex items-center">
                                Próximo Passo
                                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ETAPA 3: ANEXOS E ENVIO -->
                <div id="form-step-3" class="hidden space-y-8 animate-fade-in">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-800 mb-6 flex items-center">
                            <span
                                class="bg-[#1CBB9B] text-white rounded-full w-8 h-8 flex items-center justify-center text-sm mr-3">3</span>
                            Anexos <span class="text-sm font-normal text-gray-500 ml-2">(Adicione seus
                                comprovantes)</span>
                        </h3>
                        <div id="upload-container"
                            class="mt-1 flex justify-center px-6 pt-8 pb-8 border-2 border-gray-300 border-dashed rounded-md hover:border-[#1CBB9B] hover:bg-[#1CBB9B]/5 transition-all cursor-pointer group relative">
                            <input id="file-upload" type="file"
                                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" multiple
                                accept=".pdf,image/*" style="opacity: 0; position: absolute; inset: 0;">
                            <div class="space-y-2 text-center pointer-events-none">
                                <div
                                    class="mx-auto h-12 w-12 text-gray-400 group-hover:text-[#1CBB9B] transition-colors">
                                    <svg stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                        <path
                                            d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </div>
                                <div class="text-sm text-gray-600"><span class="font-medium text-[#1CBB9B]">Adicionar
                                        arquivos</span> ou arraste e solte</div>
                                <p class="text-xs text-gray-500">PNG, JPG, PDF até 5MB</p>
                            </div>
                        </div>

                        <!-- Lista de Arquivos com Progresso -->
                        <div id="file-list" class="mt-4 space-y-3"></div>

                        <!-- Inputs ocultos para os nomes temporários -->
                        <div id="temp-files-container"></div>
                    </div>

                    <!-- Turnstile -->
                    <?php if (ENABLE_TURNSTILE): ?>
                        <div class="flex justify-center py-4 bg-gray-50 rounded-xl border border-gray-100">
                            <div class="cf-turnstile" data-sitekey="<?= TURNSTILE_SITE_KEY ?>"></div>
                        </div>
                    <?php endif; ?>

                    <div class="pt-6 border-t border-gray-100 flex justify-between items-center">
                        <button type="button" onclick="goToStep(2)"
                            class="text-gray-500 font-bold hover:text-gray-700 transition-all flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Voltar
                        </button>
                        <button type="submit" id="submit_btn"
                            class="bg-[#1CBB9B] text-white px-10 py-4 rounded-md font-bold hover:bg-[#169C80] transition-all shadow-lg hover:shadow-xl flex items-center transform hover:-translate-y-0.5">
                            <span id="btn_text">Enviar Requerimento</span>
                            <svg id="btn_spinner" class="hidden animate-spin ml-3 h-5 w-5 text-white"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
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
            <p class="text-sm text-gray-500">&copy; <?= date('Y') ?> Instituto Federal de Santa Catarina - Câmpus
                Garopaba | Desenvolvido pelo Prof. Eduardo Gomes (Câmpus Canoinhas) e gentilmente cedido ao Câmpus Garopaba</p>
        </div>
    </footer>

    <script src="<?= BASE_URL ?>/assets/js/main.js"></script>
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
                document.getElementById('form-step-3')
            ];
            const progressLine = document.getElementById('progress-line');
            const dots = [
                document.getElementById('step-dot-1'),
                document.getElementById('step-dot-2'),
                document.getElementById('step-dot-3')
            ];

            window.goToStep = function (stepNumber) {
                // Validacao antes de avancar
                const currentStepIndex = steps.findIndex(s => !s.classList.contains('hidden'));
                if (stepNumber > (currentStepIndex + 1)) {
                    const currentStep = steps[currentStepIndex];
                    const allInputs = currentStep.querySelectorAll('input[required], select[required], textarea[required]');
                    const inputs = Array.from(allInputs).filter(input => input.type !== 'hidden' && !input.classList.contains('hidden'));
                    let valid = true;
                    inputs.forEach(input => {
                        if (!input.checkValidity()) {
                            input.reportValidity();
                            valid = false;
                        }
                    });

                    // Validacao para cartoes de tipo de requerimento (Etapa 2)
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

                    // Validacao customizada para ID 20 (Etapa 2)
                    if (valid && (currentStepIndex + 1) === 2 && requestTypeSelect.value === '20') {
                        const times = [
                            document.getElementById('arrival_time_1').value,
                            document.getElementById('arrival_time_2').value,
                            document.getElementById('departure_time_1').value,
                            document.getElementById('departure_time_2').value
                        ];
                        if (times.every(t => !t)) {
                            alert('Por favor, preencha pelo menos um horário.');
                            valid = false;
                        }
                    }

                    if (!valid) return;
                }

                // Atualiza Visibility
                steps.forEach((s, i) => {
                    s.classList.toggle('hidden', (i + 1) !== stepNumber);
                });

                // Atualiza Progress UI
                const progressWidth = stepNumber === 1 ? '0%' : (stepNumber === 2 ? '50%' : '100%');
                progressLine.style.width = progressWidth;

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

                // Desmarca a opcao atual se o usuario comecar a digitar
                if (term.length > 0) {
                    typeCards.forEach(c => {
                        c.classList.remove('selected', 'error-ring', 'border-red-400');
                    });
                    requestTypeSelect.value = "";
                    requestTypeSelect.dispatchEvent(new Event('change'));
                }

                typeCards.forEach(card => {
                    const name = card.dataset.name;
                    if (name.includes(term)) {
                        card.classList.remove('hidden');
                    } else {
                        card.classList.add('hidden');
                    }
                });
            });

            typeCards.forEach(card => {
                card.addEventListener('click', function () {
                    typeCards.forEach(c => {
                        c.classList.remove('selected', 'error-ring', 'border-red-400');
                    });
                    document.getElementById('type_error_message').classList.add('hidden');
                    document.getElementById('types_grid').classList.remove('border-red-200', 'bg-red-50/10');
                    this.classList.add('selected');
                    requestTypeSelect.value = this.dataset.id;
                    requestTypeSelect.dispatchEvent(new Event('change'));
                });
            });

            // Logica dos Campos de Justificativa e Horario
            const justificationFields = document.getElementById('justification_fields');
            const scheduleFields = document.getElementById('schedule_fields');
            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');
            const subjects = document.getElementById('subjects');
            const declarationAccepted = document.getElementById('declaration_accepted');

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
                    if (info) {
                        infoContent.innerHTML = info;
                        infoContent.classList.remove('hidden');
                    } else {
                        infoContent.classList.add('hidden');
                    }
                    if (attention) {
                        attentionContent.innerHTML = attention;
                        attentionBox.classList.remove('hidden');
                    } else {
                        attentionBox.classList.add('hidden');
                    }
                } else {
                    infoContainer.classList.add('hidden');
                }

                // Reseta campos especificos
                justificationFields.classList.add('hidden');
                scheduleFields.classList.add('hidden');
                startDate.required = false;
                endDate.required = false;
                subjects.required = false;
                declarationAccepted.required = false;

                // Remove required dos radios de tipo de horario
                document.querySelectorAll('input[name="schedule_type"]').forEach(r => r.required = false);

                if (this.value === '1') {
                    justificationFields.classList.remove('hidden');
                    startDate.required = true;
                    endDate.required = true;
                    subjects.required = true;
                } else if (this.value === '20') {
                    scheduleFields.classList.remove('hidden');
                    document.querySelectorAll('input[name="schedule_type"]').forEach(r => r.required = true);
                    declarationAccepted.required = true;
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

                    submitBtn.disabled = true;
                    btnText.textContent = 'Enviando...';
                    btnSpinner.classList.remove('hidden');
                }
            });

            // Nova Logica Assincrona de Anexos
            const fileUpload = document.getElementById('file-upload');
            const fileList = document.getElementById('file-list');
            const tempFilesContainer = document.getElementById('temp-files-container');

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
                    uploadFileAsync(fileToUpload);
                });

                // Limpa o input apos um curto atraso para garantir referencias estaveis
                setTimeout(() => {
                    fileUpload.value = '';
                }, 100);
            });

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

            function uploadFileAsync(file) {
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
                fileList.appendChild(fileItem);

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
                            tempFilesContainer.appendChild(hiddenInput);
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
                    const hidden = tempFilesContainer.querySelector(`input[data-file-id="${fileId}"]`);
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
    </script>
</body>

</html>