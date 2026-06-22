<?php
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once 'guard.php';

$db   = new Database();
$conn = $db->getConnection();

// Alunos vinculados verificados
$stmtStudents = $conn->prepare("SELECT id, student_name, student_matricula FROM guardian_students WHERE guardian_id = :gid AND verified = 1 ORDER BY student_name ASC");
$stmtStudents->execute([':gid' => $guardianId]);
$linkedStudents = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);

// Cursos e níveis
$courses = $conn->query("SELECT id, name, level FROM courses WHERE active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$levels  = $conn->query("SELECT DISTINCT level FROM courses WHERE active = 1 ORDER BY level")->fetchAll(PDO::FETCH_COLUMN);

// Tipos de requerimento
$requestTypes  = $conn->query("SELECT id, name, information, attention, featured FROM request_types WHERE active = 1 ORDER BY featured DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
$featuredTypes = array_filter($requestTypes, fn($t) => !empty($t['featured']));
$otherTypes    = array_filter($requestTypes, fn($t) =>  empty($t['featured']));

// Disciplinas e docentes
$subjects     = $conn->query("SELECT id, course_id, name, period FROM subjects WHERE active=1 ORDER BY course_id, period, name")->fetchAll(PDO::FETCH_ASSOC);
$teachers     = $conn->query("SELECT id, name FROM teachers WHERE active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$subjectsJson = json_encode($subjects, JSON_UNESCAPED_UNICODE);
$teachersJson = json_encode($teachers, JSON_UNESCAPED_UNICODE);

$logoPath   = __DIR__ . '/../assets/img/logo.png';
$logoBase64 = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <script>document.documentElement.setAttribute('data-theme', localStorage.getItem('req_theme') || 'default')</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Requerimento — Portal de Responsáveis</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/themes.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/favicon.ico">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" defer></script>
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
    <style>
        .ms-item { transition: background .15s, border-color .15s, color .15s; }
        .ms-item:not(.ms-selected):hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
        .ms-item.ms-selected { background: var(--primary); border-color: var(--primary); color: #fff; font-weight: 500; }
        .ms-item.ms-selected:hover { background: var(--primary-dark); }
    </style>
</head>
<body class="bg-[#F2F4F8] min-h-screen">

<!-- Barra superior -->
<header class="bg-white border-b border-gray-100 shadow-sm sticky top-0 z-10">
    <div class="max-w-4xl mx-auto px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="dashboard.php" class="text-gray-400 hover:text-gray-600 transition-colors mr-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="IFSC" class="h-8">
            <div>
                <p class="text-xs text-gray-400 leading-tight">Portal de Responsáveis</p>
                <p class="text-sm font-semibold text-gray-700 leading-tight"><?= htmlspecialchars($guardianName) ?></p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div class="theme-switcher">
                <button class="theme-btn text-gray-400 text-xs" data-t="default" title="Esmeralda">💎</button>
                <button class="theme-btn text-gray-400 text-xs" data-t="ifsc"    title="IFSC">🍃</button>
                <button class="theme-btn text-gray-400 text-xs" data-t="noturno" title="Noturno">🌙</button>
            </div>
            <a href="logout.php" class="text-sm text-red-400 hover:text-red-600 font-medium transition-colors">Sair</a>
        </div>
    </div>
</header>

<main class="max-w-4xl mx-auto px-4 py-8">

<?php if (empty($linkedStudents)): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center">
    <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
    </svg>
    <h2 class="text-lg font-bold text-gray-700 mb-2">Nenhum aluno verificado</h2>
    <p class="text-sm text-gray-500 mb-4">Para protocolar requerimentos, é necessário ter ao menos um aluno vinculado e verificado pela Coordenação Pedagógica.</p>
    <a href="dashboard.php" class="text-sm text-brand-DEFAULT hover:underline font-medium">← Voltar ao início</a>
</div>
<?php else: ?>

<!-- Progress Bar -->
<div class="mb-8 px-2">
    <div class="relative flex justify-between items-center">
        <div class="absolute top-1/2 left-0 w-full h-0.5 bg-gray-200 -translate-y-1/2 -z-10"></div>
        <div id="progress-line" class="absolute top-1/2 left-0 w-0 h-0.5 bg-[#1CBB9B] -translate-y-1/2 -z-10 transition-all duration-500"></div>
        <?php foreach ([1=>'Aluno', 2=>'Solicitação', 3=>'Detalhes', 4=>'Anexos'] as $n => $label): ?>
        <div class="relative flex flex-col items-center">
            <div id="step-dot-<?= $n ?>"
                class="w-10 h-10 rounded-full <?= $n===1 ? 'bg-[#1CBB9B] text-white' : 'bg-white border-2 border-gray-200 text-gray-400' ?> flex items-center justify-center font-bold shadow-sm transition-all duration-300">
                <?= $n ?>
            </div>
            <span class="absolute top-10 mt-2 left-1/2 -translate-x-1/2 w-max text-[10px] md:text-xs font-bold <?= $n===1 ? 'text-[#1CBB9B]' : 'text-gray-400' ?> uppercase tracking-wider">
                <?= $label ?>
            </span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-8 mt-8">
<form id="requestForm" action="submit_requerimento.php?action=solicitar_otp" method="POST" enctype="multipart/form-data" novalidate>
    <?= Csrf::field() ?>

    <!-- ETAPA 1: ALUNO -->
    <div id="form-step-1" class="space-y-6 animate-fade-in">
        <h3 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
            <span class="bg-[#1CBB9B] text-white rounded-full w-8 h-8 flex items-center justify-center text-sm mr-3">1</span>
            Identificação do Aluno
        </h3>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Aluno <span class="text-red-500">*</span></label>
            <select id="student_select" required
                class="w-full bg-gray-50 border border-gray-200 rounded-md px-4 py-2.5 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none appearance-none">
                <option value="">Selecione o aluno...</option>
                <?php foreach ($linkedStudents as $s): ?>
                <option value="<?= $s['id'] ?>"
                    data-name="<?= htmlspecialchars($s['student_name']) ?>"
                    data-matricula="<?= htmlspecialchars($s['student_matricula']) ?>">
                    <?= htmlspecialchars($s['student_name']) ?> — Matrícula: <?= htmlspecialchars($s['student_matricula']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="student_name" id="student_name">
            <input type="hidden" name="student_id"   id="student_id">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nível <span class="text-red-500">*</span></label>
                <div class="relative">
                    <select id="level_select" required
                        class="w-full bg-gray-50 border border-gray-200 rounded-md pl-4 pr-10 py-2.5 focus:ring-2 focus:ring-[#1CBB9B] outline-none appearance-none">
                        <option value="">Selecione...</option>
                        <?php foreach ($levels as $level): ?>
                        <option value="<?= $level ?>"><?= ucfirst($level) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Curso <span class="text-red-500">*</span></label>
                <div class="relative">
                    <select name="course_id" id="course_select" required disabled
                        class="w-full bg-gray-50 border border-gray-200 rounded-md pl-4 pr-10 py-2.5 focus:ring-2 focus:ring-[#1CBB9B] outline-none appearance-none disabled:opacity-60">
                        <option value="">Selecione o nível primeiro...</option>
                        <?php foreach ($courses as $course): ?>
                        <option value="<?= $course['id'] ?>" data-level="<?= $course['level'] ?>" style="display:none;">
                            <?= htmlspecialchars($course['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Turma / Ano</label>
                <input type="text" name="class_info"
                    class="w-full bg-gray-50 border border-gray-200 rounded-md px-4 py-2.5 focus:ring-2 focus:ring-[#1CBB9B] outline-none"
                    placeholder="Ex: 2024, 2025">
            </div>
        </div>

        <div class="pt-4 border-t border-gray-100 flex justify-end">
            <button type="button" onclick="goToStep(2)"
                class="bg-[#1CBB9B] text-white px-8 py-3 rounded-md font-bold hover:bg-[#169C80] transition-all shadow-sm flex items-center">
                Próximo Passo
                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
    </div>

    <!-- ETAPA 2: TIPO DE REQUERIMENTO -->
    <div id="form-step-2" class="hidden space-y-6 animate-fade-in">
        <h3 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
            <span class="bg-[#1CBB9B] text-white rounded-full w-8 h-8 flex items-center justify-center text-sm mr-3">2</span>
            Tipo de Requerimento
        </h3>

        <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </span>
            <input type="text" id="type_search" placeholder="Comece a digitar para encontrar..."
                class="w-full bg-white border border-gray-200 rounded-xl pl-10 pr-4 py-3 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none shadow-sm text-sm">
        </div>

        <?php if (!empty($featuredTypes)): ?>
        <div id="section-featured">
            <p class="text-xs font-bold text-[#1CBB9B] uppercase tracking-wider mb-2">⭐ Mais utilizadas</p>
            <div id="types_grid_featured" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                <?php foreach ($featuredTypes as $type): ?>
                <div class="type-card cursor-pointer group border border-[#1CBB9B]/30 bg-[#1CBB9B]/5 rounded-xl p-3 hover:shadow-md hover:-translate-y-0.5 transition-all flex items-center min-h-[70px] relative overflow-hidden"
                    data-id="<?= $type['id'] ?>" data-name="<?= strtolower(htmlspecialchars($type['name'])) ?>">
                    <div class="absolute left-0 top-0 w-1 h-full bg-[#1CBB9B] opacity-0 group-[.selected]:opacity-100 transition-opacity"></div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs sm:text-sm font-semibold text-gray-700 group-hover:text-[#1CBB9B] group-[.selected]:text-[#1CBB9B] transition-colors leading-tight line-clamp-2">
                            <?= htmlspecialchars($type['name']) ?>
                        </p>
                    </div>
                    <div class="hidden group-[.selected]:block ml-2 text-[#1CBB9B]">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div id="section-other">
            <?php if (!empty($featuredTypes)): ?>
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Outras solicitações</p>
            <?php endif; ?>
            <div id="types_grid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                <?php foreach ($otherTypes as $type): ?>
                <div class="type-card cursor-pointer group border rounded-xl p-3 hover:shadow-md hover:-translate-y-0.5 transition-all flex items-center min-h-[70px] relative overflow-hidden"
                    data-id="<?= $type['id'] ?>" data-name="<?= strtolower(htmlspecialchars($type['name'])) ?>">
                    <div class="absolute left-0 top-0 w-1 h-full bg-[#1CBB9B] opacity-0 group-[.selected]:opacity-100 transition-opacity"></div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs sm:text-sm font-semibold text-gray-700 group-hover:text-[#1CBB9B] group-[.selected]:text-[#1CBB9B] transition-colors leading-tight line-clamp-2">
                            <?= htmlspecialchars($type['name']) ?>
                        </p>
                    </div>
                    <div class="hidden group-[.selected]:block ml-2 text-[#1CBB9B]">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="type_error_message" class="hidden text-red-500 text-xs font-bold flex items-center mt-2">
            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            Por favor, selecione uma opção antes de prosseguir.
        </div>

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

        <div id="tipo-info" class="hidden">
            <div class="bg-blue-50 border border-blue-100 rounded-md p-5 shadow-sm">
                <div id="info_content" class="text-[#293087] prose prose-sm prose-blue max-w-none leading-relaxed mb-4"></div>
                <div id="attention_box" class="hidden bg-red-50 border border-red-100 rounded-md p-4 mt-4">
                    <div class="text-red-800 text-sm leading-relaxed">
                        <strong class="font-bold block mb-2">ATENÇÃO:</strong>
                        <div id="attention_content" class="prose prose-sm prose-red max-w-none"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="pt-4 border-t border-gray-100 flex justify-between items-center">
            <button type="button" onclick="goToStep(1)" class="text-gray-500 font-bold hover:text-gray-700 transition-all flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Voltar
            </button>
            <button type="button" onclick="goToStep(3)" class="bg-[#1CBB9B] text-white px-8 py-3 rounded-md font-bold hover:bg-[#169C80] transition-all shadow-sm flex items-center">
                Próximo Passo
                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
    </div>

    <!-- ETAPA 3: DETALHES -->
    <div id="form-step-3" class="hidden space-y-6 animate-fade-in">
        <h3 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
            <span class="bg-[#1CBB9B] text-white rounded-full w-8 h-8 flex items-center justify-center text-sm mr-3">3</span>
            <span id="step3-title">Detalhes da Solicitação</span>
        </h3>

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
                    placeholder="Nome da UC não listada"
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
                        <option>Trabalho</option><option>Saúde</option><option>Viagem</option>
                        <option>Mudança de endereço</option><option>Outro</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Tipo 5: Cancelamento de Curso -->
        <div id="fields-type-5" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Motivo do Cancelamento <span class="text-red-500">*</span></label>
                <select name="cancel_reason" class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                    <option value="">Selecione...</option>
                    <option>Curso não atendeu expectativas</option><option>Horário</option>
                    <option>Mudança de endereço</option><option>Dificuldade de transporte</option>
                    <option>Mudança de Curso ou Instituição</option><option>Dificuldades financeiras</option>
                    <option>Situação de saúde</option><option>Outro</option>
                </select>
            </div>
        </div>

        <!-- Tipo 6: Transferência -->
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
                <label class="block text-sm font-medium text-gray-700 mb-1">Motivo principal <span class="text-red-500">*</span></label>
                <select name="transfer_reason" required class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                    <option value="">Selecione...</option>
                    <option>Mudança de endereço/cidade</option><option>Curso não disponível no IFSC Garopaba</option>
                    <option>Dificuldades de transporte</option><option>Preferência por outra instituição</option><option>Outro</option>
                </select>
            </div>
        </div>

        <!-- Tipo 7: Matrícula em UC Isolada -->
        <div id="fields-type-7" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome(s) da(s) UC(s) <span class="text-red-500">*</span></label>
                <textarea name="uc_isolated_names" rows="3" class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none resize-none" placeholder="Informe o(s) nome(s) da(s) UC(s)"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Curso que oferece a(s) UC(s) <span class="text-red-500">*</span></label>
                <input type="text" name="uc_isolated_course" class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
            </div>
        </div>

        <!-- Tipo 8: Diplomas / Histórico -->
        <div id="fields-type-8" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Documento <span class="text-red-500">*</span></label>
                <div class="space-y-2">
                    <label class="flex items-center cursor-pointer"><input type="radio" name="doc_type" value="Diploma de Curso Técnico ou Superior" class="h-4 w-4 text-[#1CBB9B] border-gray-300"><span class="ml-2 text-sm text-gray-700">Diploma de Curso Técnico ou Superior</span></label>
                    <label class="flex items-center cursor-pointer"><input type="radio" name="doc_type" value="Certificado FIC" class="h-4 w-4 text-[#1CBB9B] border-gray-300"><span class="ml-2 text-sm text-gray-700">Certificado FIC</span></label>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome do Curso <span class="text-red-500">*</span></label>
                    <input type="text" name="diploma_course_name" class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none" placeholder="Nome do curso concluído">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ano de Conclusão <span class="text-red-500">*</span></label>
                    <input type="number" name="graduation_year" min="2000" max="2030" class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none" placeholder="Ex: 2023">
                </div>
            </div>
        </div>

        <!-- Tipo 9: Validação / Dispensa de UC -->
        <div id="fields-type-9" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 space-y-3 text-sm">
                <p class="font-bold text-blue-900">Modalidades de Validação de UC (RDP — Art. 68–73)</p>
                <div class="space-y-2 text-blue-800 leading-snug">
                    <p><strong>RE — Reconhecimento de Estudos:</strong> Cursou esta UC em outra instituição. Apresente histórico escolar e programa da disciplina.</p>
                    <p><strong>RS — Reconhecimento de Saberes:</strong> Possui experiência prática comprovável. Apresente documentação (CTPS, declaração de empresa, certificados, etc.).</p>
                    <p><strong>EAE — Extraordinário Aproveitamento:</strong> Demonstra domínio do conteúdo e solicita avaliação especial.</p>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Validação <span class="text-red-500">*</span></label>
                <div class="space-y-2">
                    <label class="flex items-center cursor-pointer"><input type="radio" name="validation_type" value="RE" id="val_re" class="h-4 w-4 text-[#1CBB9B] border-gray-300"><span class="ml-2 text-sm text-gray-700">Reconhecimento de Estudos (RE)</span></label>
                    <label class="flex items-center cursor-pointer"><input type="radio" name="validation_type" value="RS" id="val_rs" class="h-4 w-4 text-[#1CBB9B] border-gray-300"><span class="ml-2 text-sm text-gray-700">Reconhecimento de Saberes (RS)</span></label>
                    <label class="flex items-center cursor-pointer"><input type="radio" name="validation_type" value="EAE" id="val_eae" class="h-4 w-4 text-[#1CBB9B] border-gray-300"><span class="ml-2 text-sm text-gray-700">Extraordinário Aproveitamento (EAE)</span></label>
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

        <!-- Tipo 10: Retorno de Trancamento -->
        <div id="fields-type-10" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Semestre/período pretendido para retorno <span class="text-red-500">*</span></label>
                <input type="text" name="return_period" required class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none" placeholder="Ex: 2026.1 — 1º Semestre de 2026">
            </div>
        </div>

        <!-- Tipo 11: Reingresso -->
        <div id="fields-type-11" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Último período cursado <span class="text-red-500">*</span></label>
                    <input type="text" name="last_period" required class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none" placeholder="Ex: 2022.2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Motivo do afastamento <span class="text-red-500">*</span></label>
                    <select name="reinstatement_reason" required class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                        <option value="">Selecione...</option>
                        <option>Trabalho</option><option>Saúde</option><option>Mudança de endereço</option>
                        <option>Dificuldades financeiras</option><option>Trancamento expirado</option><option>Outro</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Tipo 12: Matrícula Especial em UC -->
        <div id="fields-type-12" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome(s) da(s) UC(s) <span class="text-red-500">*</span></label>
                <textarea name="uc_special_names" rows="3" class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none resize-none" placeholder="Informe os nomes das UCs de interesse"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Curso que oferece a(s) UC(s) <span class="text-red-500">*</span></label>
                <input type="text" name="uc_special_course" class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none" placeholder="Nome do curso">
            </div>
        </div>

        <!-- Tipo 13: Apoio Educacional Especializado -->
        <div id="fields-type-13" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de necessidade específica <span class="text-red-500">*</span></label>
                <select name="support_type" required class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                    <option value="">Selecione...</option>
                    <option>Deficiência física</option><option>Deficiência visual</option><option>Deficiência auditiva</option>
                    <option>Deficiência intelectual</option><option>Transtorno do Espectro Autista (TEA)</option>
                    <option>Transtorno de aprendizagem (dislexia, TDAH, etc.)</option>
                    <option>Condição de saúde / tratamento médico contínuo</option><option>Outra necessidade específica</option>
                </select>
            </div>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="has_documentation" value="1" class="h-4 w-4 text-[#1CBB9B] border-gray-300 rounded">
                <span class="text-sm text-gray-700">Possuo laudo médico/psicológico ou documentação comprobatória</span>
            </label>
        </div>

        <!-- Tipo 14: Ajuste de Matrícula -->
        <div id="fields-type-14" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Descreva as UCs que deseja incluir e/ou cancelar <span class="text-red-500">*</span></label>
                <textarea name="uc_changes" rows="4" class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none resize-none" placeholder="Ex: Incluir: Banco de Dados I&#10;Cancelar: Cálculo II"></textarea>
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
        </div>

        <!-- Tipo 16: Extraordinário Aproveitamento de Estudos -->
        <div id="fields-type-16" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-800">
                <strong>EAE:</strong> Você demonstra domínio do conteúdo da UC e solicita uma avaliação especial designada pelo professor responsável.
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Unidade(s) Curricular(es) para EAE <span class="text-red-500">*</span></label>
                <div id="ms-subjects-type-16" class="ms-widget" data-required="1"></div>
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
                <input type="text" name="missing_prereq" required class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none" placeholder="Nome da UC pré-requisito">
            </div>
        </div>

        <!-- Tipo 18: Colação de Grau -->
        <div id="fields-type-18" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Situação ENADE <span class="text-red-500">*</span></label>
                <div class="space-y-2">
                    <label class="flex items-center cursor-pointer"><input type="radio" name="enade_status" value="Realizei o ENADE" class="h-4 w-4 text-[#1CBB9B] border-gray-300"><span class="ml-2 text-sm text-gray-700">Realizei o ENADE</span></label>
                    <label class="flex items-center cursor-pointer"><input type="radio" name="enade_status" value="Fui dispensado(a)" class="h-4 w-4 text-[#1CBB9B] border-gray-300"><span class="ml-2 text-sm text-gray-700">Fui dispensado(a)</span></label>
                </div>
            </div>
            <div class="bg-[#1CBB9B]/5 p-4 rounded-xl border border-[#1CBB9B]/20">
                <label class="flex items-start cursor-pointer">
                    <input type="checkbox" name="colacao_declaration" id="colacao_declaration" class="h-5 w-5 mt-0.5 text-[#1CBB9B] focus:ring-[#1CBB9B] border-gray-300 rounded">
                    <span class="ml-3 text-sm text-gray-700">Declaro estar ciente de que a colação de grau está sujeita à análise e aprovação da coordenação do curso. <span class="text-red-500">*</span></span>
                </label>
            </div>
        </div>

        <!-- Tipo 19: Outro -->
        <div id="fields-type-19" class="hidden mb-6 p-4 bg-blue-50 rounded-xl border border-blue-100 text-sm text-blue-800">
            <strong>Requerimento avulso:</strong> Descreva detalhadamente sua solicitação no campo de Justificativa abaixo.
        </div>

        <!-- Tipo 20: Horário Diferenciado -->
        <div id="fields-type-20" class="hidden space-y-6 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-4">Tipo de Solicitação de Horário <span class="text-red-500">*</span></label>
                <div class="flex flex-wrap gap-6">
                    <label class="flex items-center cursor-pointer"><input type="radio" name="schedule_type" value="Chegada tardia" class="w-5 h-5 text-[#1CBB9B] border-gray-300"><span class="ml-2 text-sm text-gray-700">Chegada tardia</span></label>
                    <label class="flex items-center cursor-pointer"><input type="radio" name="schedule_type" value="Saída antecipada" class="w-5 h-5 text-[#1CBB9B] border-gray-300"><span class="ml-2 text-sm text-gray-700">Saída antecipada</span></label>
                    <label class="flex items-center cursor-pointer"><input type="radio" name="schedule_type" value="Entrada e Saída" class="w-5 h-5 text-[#1CBB9B] border-gray-300"><span class="ml-2 text-sm text-gray-700">Entrada e Saída</span></label>
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
            <p class="text-[11px] text-[#293087] bg-blue-50/50 p-3 rounded border border-blue-100 italic">* Pelo menos um horário deve ser preenchido.</p>
            <div class="bg-[#1CBB9B]/5 p-4 rounded-xl border border-[#1CBB9B]/20">
                <label class="flex items-start cursor-pointer">
                    <input type="checkbox" id="declaration_accepted" name="declaration_accepted" class="h-5 w-5 text-[#1CBB9B] focus:ring-[#1CBB9B] border-gray-300 rounded">
                    <div class="ml-3 text-sm">
                        <p class="font-medium text-gray-800">Declaração</p>
                        <p class="text-gray-600 leading-relaxed text-xs">Declaro que as informações fornecidas são verdadeiras e estou ciente de que a aprovação está sujeita à análise da coordenação do curso.</p>
                    </div>
                </label>
            </div>
        </div>

        <!-- Tipo 21: Assistência Estudantil -->
        <div id="fields-type-21" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de assistência solicitada <span class="text-red-500">*</span></label>
                <div class="space-y-2">
                    <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="assistance_type[]" value="Auxílio-transporte" class="h-4 w-4 text-[#1CBB9B] border-gray-300 rounded"><span class="text-sm text-gray-700">Auxílio-transporte</span></label>
                    <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="assistance_type[]" value="Auxílio-alimentação" class="h-4 w-4 text-[#1CBB9B] border-gray-300 rounded"><span class="text-sm text-gray-700">Auxílio-alimentação</span></label>
                    <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="assistance_type[]" value="Auxílio-moradia" class="h-4 w-4 text-[#1CBB9B] border-gray-300 rounded"><span class="text-sm text-gray-700">Auxílio-moradia</span></label>
                    <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="assistance_type[]" value="Equipamentos e materiais pedagógicos" class="h-4 w-4 text-[#1CBB9B] border-gray-300 rounded"><span class="text-sm text-gray-700">Equipamentos e materiais pedagógicos</span></label>
                    <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="assistance_type[]" value="Outro" class="h-4 w-4 text-[#1CBB9B] border-gray-300 rounded"><span class="text-sm text-gray-700">Outro</span></label>
                </div>
                <p id="assistance-error" class="hidden text-xs text-red-600 font-medium mt-1">Selecione ao menos um tipo de assistência.</p>
            </div>
        </div>

        <!-- Tipo 22: Carteira LiberaIFSC -->
        <div id="fields-type-22" class="hidden space-y-4 mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
            <div class="bg-blue-50 border border-blue-200 rounded-xl overflow-hidden text-sm">
                <div class="px-4 py-3 border-b border-blue-200 flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-700 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="font-bold text-blue-900">Leia antes de solicitar a Carteirinha LiberaIFSC</p>
                </div>
                <div class="px-4 py-3 max-h-64 overflow-y-auto space-y-3 text-blue-900 leading-relaxed">
                    <p>Trata-se de uma iniciativa do IFSC Câmpus Garopaba que visa reforçar o senso de responsabilidade das famílias e dos nossos estudantes.</p>
                    <p>A autorização permite que o/a estudante saia do câmpus nas seguintes situações:</p>
                    <ol class="list-decimal list-inside space-y-1 text-blue-800">
                        <li>Realização de trabalhos acadêmicos fora de horário de aula;</li>
                        <li>Troca de horário de aulas;</li>
                        <li>Quando ocorre ausência de professor/a;</li>
                        <li>Para atendimento extraclasse com docente, monitoria, pendência, atividade esportiva e projetos;</li>
                        <li>Finalização antecipada de avaliações.</li>
                    </ol>
                    <p class="text-blue-700 text-xs border-t border-blue-200 pt-2">Para cancelar a autorização é necessário comparecer à Coordenadoria Pedagógica do câmpus.</p>
                </div>
                <div class="px-4 py-3 bg-blue-100 border-t border-blue-200">
                    <label class="block text-xs font-semibold text-blue-900 mb-1">Para confirmar que leu e compreendeu o texto acima, digite <span class="font-mono bg-white px-1.5 py-0.5 rounded border border-blue-300">CIENTE</span> no campo abaixo: <span class="text-red-500">*</span></label>
                    <input type="text" id="libera_ciente" autocomplete="off" placeholder="Digite CIENTE"
                        class="w-full bg-white border border-blue-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none font-mono tracking-widest uppercase">
                    <p id="libera_ciente_error" class="hidden text-xs text-red-600 font-medium mt-1">Digite CIENTE para confirmar a leitura.</p>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome completo do/a responsável legal <span class="text-red-500">*</span></label>
                <input type="text" name="guardian_legal_name" id="libera_guardian_name" required
                    value="<?= htmlspecialchars($guardianName) ?>"
                    class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 space-y-3">
                <p class="text-sm font-semibold text-gray-800">Termo de Autorização</p>
                <p class="text-sm text-gray-600">Gere o termo abaixo, assine digitalmente via Gov.br e faça o upload do PDF assinado abaixo.</p>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" onclick="generateSaidaAntecipadaPDF()"
                        class="inline-flex items-center gap-2 text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition-all shadow-sm hover:opacity-90"
                        style="background:#1351B4;">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Gerar Termo (PDF)
                    </button>
                    <a href="https://assinador.iti.br" target="_blank" rel="noopener noreferrer"
                        class="inline-flex items-center gap-1.5 text-sm font-semibold px-4 py-2.5 rounded-lg border transition-all hover:opacity-80"
                        style="color:#1351B4; border-color:#1351B4;">
                        Assinar no Gov.br ↗
                    </a>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Termo assinado <span class="text-red-500">*</span>
                    <span class="text-xs text-gray-400 font-normal ml-1">(PDF assinado via Gov.br, max 5 MB)</span>
                </label>
                <div id="type22-upload-container"
                    class="flex justify-center px-6 pt-5 pb-5 border-2 border-dashed border-gray-300 rounded-lg hover:border-[#1CBB9B] hover:bg-[#1CBB9B]/5 transition-all cursor-pointer group relative bg-white">
                    <input id="type22-file-upload" type="file" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" accept=".pdf,.jpg,.jpeg,.png,.heic,.webp">
                    <div class="space-y-1 text-center pointer-events-none">
                        <div class="mx-auto h-10 w-10 text-gray-400 group-hover:text-[#1CBB9B] transition-colors">
                            <svg stroke="currentColor" fill="none" viewBox="0 0 48 48"><path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <div class="text-sm text-gray-600"><span class="font-medium text-[#1CBB9B]">Enviar termo assinado</span> ou arraste aqui</div>
                        <p class="text-xs text-gray-500">PDF assinado via Gov.br · JPG · PNG · Máx. 5 MB</p>
                    </div>
                </div>
                <div id="type22-file-list" class="mt-2 space-y-2"></div>
                <div id="type22-temp-files-container"></div>
                <p id="type22-upload-error" class="hidden text-xs text-red-600 font-medium mt-1">Envie o termo assinado antes de prosseguir.</p>
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
                <textarea name="uc_cancel_reason" rows="3" class="w-full bg-white border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none resize-none" placeholder="Descreva o motivo do cancelamento"></textarea>
            </div>
        </div>

        <!-- Justificativa (sempre visível) -->
        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Justificativa / Observações adicionais <span class="text-red-500">*</span></label>
            <textarea name="description" id="description" required rows="5"
                class="w-full bg-gray-50 border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none resize-none"
                placeholder="Descreva a solicitação com o máximo de detalhes possível..."></textarea>
        </div>

        <div class="pt-4 border-t border-gray-100 flex justify-between items-center">
            <button type="button" onclick="goToStep(2)" class="text-gray-500 font-bold hover:text-gray-700 transition-all flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Voltar
            </button>
            <button type="button" id="step3-next-btn" onclick="goToStep(4)" class="bg-[#1CBB9B] text-white px-8 py-3 rounded-md font-bold hover:bg-[#169C80] transition-all shadow-sm flex items-center">
                Próximo Passo
                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
            <button type="button" id="step3-submit-btn" class="hidden bg-[#1CBB9B] text-white px-8 py-3 rounded-md font-bold hover:bg-[#169C80] transition-all shadow-sm flex items-center">
                <span id="step3-btn-text">Solicitar Confirmação →</span>
                <svg id="step3-btn-spinner" class="hidden animate-spin ml-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </button>
        </div>
    </div>

    <!-- ETAPA 4: ANEXOS -->
    <div id="form-step-4" class="hidden space-y-6 animate-fade-in">
        <h3 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
            <span class="bg-[#1CBB9B] text-white rounded-full w-8 h-8 flex items-center justify-center text-sm mr-3">4</span>
            Anexos
        </h3>
        <p class="text-sm text-gray-500 -mt-2 ml-11">Comprovantes, laudos ou outros documentos relevantes <span class="text-gray-400">(opcional)</span></p>

        <div id="upload-container"
            class="flex justify-center px-6 pt-8 pb-8 border-2 border-gray-300 border-dashed rounded-md hover:border-[#1CBB9B] hover:bg-[#1CBB9B]/5 transition-all cursor-pointer group relative">
            <input id="file-upload" type="file" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" multiple accept=".pdf,image/*">
            <div class="space-y-2 text-center pointer-events-none">
                <div class="mx-auto h-12 w-12 text-gray-400 group-hover:text-[#1CBB9B] transition-colors">
                    <svg stroke="currentColor" fill="none" viewBox="0 0 48 48"><path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <div class="text-sm text-gray-600"><span class="font-medium text-[#1CBB9B]">Adicionar arquivos</span> ou arraste e solte</div>
                <p class="text-xs text-gray-500">PNG, JPG, PDF até 5MB</p>
            </div>
        </div>
        <div id="file-list" class="mt-4 space-y-3"></div>
        <div id="temp-files-container"></div>

        <div class="pt-4 border-t border-gray-100 flex justify-between items-center">
            <button type="button" onclick="goToStep(3)" class="text-gray-500 font-bold hover:text-gray-700 transition-all flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Voltar
            </button>
            <button type="submit" id="submit_btn"
                class="text-white px-10 py-3 rounded-md font-bold transition-all shadow-sm flex items-center"
                style="background:#1CBB9B;" onmouseover="this.style.background='#169C80'" onmouseout="this.style.background='#1CBB9B'">
                <span id="btn_text">Solicitar Confirmação →</span>
                <svg id="btn_spinner" class="hidden animate-spin ml-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </button>
        </div>
    </div>

</form>
</div>

<?php endif; ?>
</main>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
const ALL_SUBJECTS = <?= $subjectsJson ?>;
const ALL_TEACHERS = <?= $teachersJson ?>;
const LOGO_DATA    = '<?= $logoBase64 ?>';
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    const form             = document.getElementById('requestForm');
    if (!form) return;

    const steps       = [1,2,3,4].map(n => document.getElementById('form-step-' + n));
    const progressLine = document.getElementById('progress-line');
    const dots        = [1,2,3,4].map(n => document.getElementById('step-dot-' + n));

    // --- Seleção de aluno ---
    const studentSelect = document.getElementById('student_select');
    if (studentSelect) {
        studentSelect.addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            document.getElementById('student_name').value = opt.dataset.name     || '';
            document.getElementById('student_id').value   = opt.dataset.matricula || '';
        });
    }

    // --- Nível e Curso ---
    const levelSelect   = document.getElementById('level_select');
    const courseSelect  = document.getElementById('course_select');
    const courseOptions = Array.from(courseSelect.options);

    levelSelect.addEventListener('change', function () {
        const sel = this.value;
        courseSelect.value = '';
        if (!sel) {
            courseSelect.disabled = true;
            courseSelect.options[0].text = 'Selecione o nível primeiro...';
            return;
        }
        courseSelect.disabled = false;
        courseSelect.options[0].text = 'Selecione seu curso...';
        courseOptions.forEach(opt => {
            if (opt.value === '') return;
            opt.style.display = (opt.dataset.level === sel) ? 'block' : 'none';
        });
    });

    // --- Multi-step navigation ---
    const requestTypeSelect = document.getElementById('request_type_select');
    const typeSearch        = document.getElementById('type_search');
    const typeCards         = document.querySelectorAll('.type-card');

    window.goToStep = function (stepNumber) {
        const currentIdx = steps.findIndex(s => !s.classList.contains('hidden'));

        if (stepNumber > currentIdx + 1) {
            let valid = true;
            const currentStep = steps[currentIdx];

            const visibleRequired = Array.from(currentStep.querySelectorAll('input[required], select[required], textarea[required]')).filter(el => {
                if (el.type === 'hidden') return false;
                let p = el;
                while (p && p !== currentStep) { if (p.classList.contains('hidden')) return false; p = p.parentElement; }
                return true;
            });
            visibleRequired.forEach(el => { if (!el.checkValidity()) { el.reportValidity(); valid = false; } });

            if (valid && (currentIdx + 1) === 2 && !requestTypeSelect.value) {
                document.getElementById('type_error_message').classList.remove('hidden');
                valid = false;
            }

            if (valid && (currentIdx + 1) === 3) {
                const typeId = requestTypeSelect.value;
                const typeDiv = document.getElementById('fields-type-' + typeId);
                if (typeDiv && !typeDiv.classList.contains('hidden')) {
                    typeDiv.querySelectorAll('.ms-widget[data-required]').forEach(widget => {
                        if (!valid) return;
                        if (widget.querySelectorAll('.ms-inputs input[type="hidden"]').length === 0) {
                            const wrapperEl = widget.querySelector('.ms-wrapper');
                            if (wrapperEl) wrapperEl.classList.add('border-red-400');
                            widget.querySelector('.ms-error')?.classList.remove('hidden');
                            valid = false;
                        }
                    });
                    if (valid) {
                        const radioNames = new Set();
                        typeDiv.querySelectorAll('input[type="radio"]').forEach(r => radioNames.add(r.name));
                        radioNames.forEach(name => {
                            if (!typeDiv.querySelector('input[name="' + name + '"]:checked')) {
                                alert('Selecione uma das opções obrigatórias.');
                                valid = false;
                            }
                        });
                    }
                    if (valid && typeId === '18') {
                        const d = document.getElementById('colacao_declaration');
                        if (d && !d.checked) { alert('Você deve marcar a declaração obrigatória.'); valid = false; }
                    }
                    if (valid && typeId === '20') {
                        const times = ['arrival_time_1','arrival_time_2','departure_time_1','departure_time_2'].map(id => document.getElementById(id)?.value || '');
                        if (times.every(t => !t)) { alert('Por favor, preencha pelo menos um horário.'); valid = false; }
                    }
                    if (valid && typeId === '21') {
                        const checked = typeDiv.querySelectorAll('input[name="assistance_type[]"]:checked');
                        if (checked.length === 0) {
                            document.getElementById('assistance-error')?.classList.remove('hidden');
                            valid = false;
                        } else { document.getElementById('assistance-error')?.classList.add('hidden'); }
                    }
                }
                const desc = document.getElementById('description');
                if (valid && desc && !desc.value.trim()) { desc.reportValidity(); valid = false; }
            }

            if (!valid) return;
        }

        if (stepNumber === 3) {
            renderTypeFields(requestTypeSelect.value);
            const typeName = requestTypeSelect.options[requestTypeSelect.selectedIndex]?.dataset?.name || 'Detalhes da Solicitação';
            document.getElementById('step3-title').textContent = requestTypeSelect.value === '22' ? 'Detalhes e Anexos' : 'Detalhes: ' + typeName;
        }

        steps.forEach((s, i) => s.classList.toggle('hidden', (i + 1) !== stepNumber));

        const widths = { 1:'0%', 2:'33%', 3:'66%', 4:'100%' };
        progressLine.style.width = widths[stepNumber] || '0%';

        dots.forEach((dot, i) => {
            const label = dot.nextElementSibling;
            if ((i + 1) <= stepNumber) {
                dot.classList.add('bg-[#1CBB9B]','text-white','border-[#1CBB9B]');
                dot.classList.remove('bg-white','text-gray-400','border-gray-200');
                label?.classList.add('text-[#1CBB9B]');
                label?.classList.remove('text-gray-400');
            } else {
                dot.classList.remove('bg-[#1CBB9B]','text-white','border-[#1CBB9B]');
                dot.classList.add('bg-white','text-gray-400','border-gray-200');
                label?.classList.remove('text-[#1CBB9B]');
                label?.classList.add('text-gray-400');
            }
        });

        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    // --- buildMultiSelect ---
    function buildMultiSelect(containerId, items, fieldName) {
        const container = document.getElementById(containerId);
        if (!container) return;
        container.innerHTML =
            '<input type="text" class="ms-search w-full border border-gray-200 rounded-md px-3 py-2 text-sm mb-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none bg-white" placeholder="Buscar...">' +
            '<div class="ms-wrapper max-h-72 overflow-y-auto border border-gray-200 rounded-md bg-gray-50/50 p-2 space-y-5"></div>' +
            '<p class="ms-count text-xs text-gray-400 mt-1.5 text-right">Nenhuma selecionada</p>' +
            '<div class="ms-inputs"></div>' +
            '<p class="ms-error hidden text-xs text-red-600 font-medium mt-1">Selecione ao menos uma opção acima.</p>';
        const searchInput = container.querySelector('.ms-search');
        const wrapperEl   = container.querySelector('.ms-wrapper');
        const countEl     = container.querySelector('.ms-count');
        const inputsEl    = container.querySelector('.ms-inputs');
        let selectedCount = 0;
        const normalize = s => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
        const groups = {}, noGroup = [];
        items.forEach(item => { item.period ? (groups[item.period] = groups[item.period] || []).push(item) : noGroup.push(item); });
        const sortedPeriods = Object.keys(groups).sort((a, b) => (parseInt(a) || 99) - (parseInt(b) || 99));
        function makeSection(label) {
            const section = document.createElement('div'); section.className = 'ms-group';
            if (label) { const hdr = document.createElement('div'); hdr.className = 'text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 px-1 pb-1.5 border-b-2 border-gray-300'; hdr.textContent = label; section.appendChild(hdr); }
            const grid = document.createElement('div'); grid.className = 'grid grid-cols-2 md:grid-cols-3 gap-1.5';
            section.appendChild(grid); wrapperEl.appendChild(section); return grid;
        }
        function addPill(item, grid) {
            const btn = document.createElement('button'); btn.type = 'button';
            btn.className = 'ms-item w-full text-left px-2.5 py-2 rounded-md text-xs border border-gray-200 text-gray-700 flex items-center justify-between gap-2 leading-snug cursor-pointer';
            btn.dataset.value = item.id; btn.dataset.label = item.name; btn.dataset.norm = normalize(item.name); btn.dataset.period = item.period || '';
            const nameSpan = document.createElement('span'); nameSpan.textContent = item.name;
            const check = document.createElement('span'); check.className = 'ms-check flex-shrink-0 font-bold'; check.style.opacity = '0'; check.textContent = '✓';
            btn.appendChild(nameSpan); btn.appendChild(check);
            btn.addEventListener('click', () => toggleItem(btn)); grid.appendChild(btn);
        }
        sortedPeriods.forEach(p => { const g = makeSection(p); groups[p].forEach(i => addPill(i, g)); });
        if (noGroup.length) { const g = makeSection(sortedPeriods.length ? 'Outras' : ''); noGroup.forEach(i => addPill(i, g)); }
        function toggleItem(btn) {
            const value = btn.dataset.value;
            if (btn.classList.contains('ms-selected')) {
                btn.classList.remove('ms-selected'); btn.querySelector('.ms-check').style.opacity = '0';
                inputsEl.querySelector('input[data-val="' + value + '"]')?.remove(); selectedCount--;
            } else {
                btn.classList.add('ms-selected'); btn.querySelector('.ms-check').style.opacity = '1';
                wrapperEl.classList.remove('border-red-400'); container.querySelector('.ms-error')?.classList.add('hidden');
                const hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = fieldName; hidden.value = value; hidden.dataset.val = value;
                inputsEl.appendChild(hidden); selectedCount++;
            }
            countEl.textContent = selectedCount === 0 ? 'Nenhuma selecionada' : selectedCount === 1 ? '1 selecionada' : selectedCount + ' selecionadas';
        }
        searchInput.addEventListener('input', () => {
            const q = normalize(searchInput.value);
            wrapperEl.querySelectorAll('.ms-item').forEach(btn => btn.classList.toggle('hidden', q !== '' && !btn.dataset.norm.includes(q)));
            wrapperEl.querySelectorAll('.ms-group').forEach(grp => { const pills = grp.querySelectorAll('.ms-item'); grp.classList.toggle('hidden', pills.length > 0 && [...pills].every(p => p.classList.contains('hidden'))); });
        });
    }

    // --- renderTypeFields ---
    function renderTypeFields(typeId) {
        document.querySelectorAll('[id^="fields-type-"]').forEach(d => d.classList.add('hidden'));
        if (!typeId) return;
        const target = document.getElementById('fields-type-' + typeId);
        if (!target) return;
        target.classList.remove('hidden');
        const courseId = courseSelect.value;
        const filtered = courseId ? ALL_SUBJECTS.filter(s => String(s.course_id) === String(courseId)) : ALL_SUBJECTS;
        if (typeId === '25') buildMultiSelect('ms-subjects-type-25', filtered, 'selected_subjects[]');
        if (typeId === '2')  { buildMultiSelect('ms-subjects-type-2', filtered, 'selected_subjects[]'); buildMultiSelect('ms-teachers-type-2', ALL_TEACHERS, 'selected_teachers[]'); }
        if (['3','15','16','17'].includes(typeId)) buildMultiSelect('ms-subjects-type-' + typeId, filtered, 'selected_subjects[]');
        const placeholders = {
            '1':'Ex: O aluno esteve ausente nos dias indicados por [motivo].','2':'Ex: A avaliação ocorreu em [data]. Esteve ausente porque [motivo].','3':'Ex: Impossibilitado de frequentar as aulas no período por [motivo].','4':'Informações adicionais sobre o motivo do trancamento.','5':'Informações adicionais sobre o motivo do cancelamento.','6':'Complemento sobre os motivos da transferência.','7':'Informe o motivo pelo qual deseja cursar a UC de forma isolada.','8':'Observação ou instrução especial para expedição do documento.','9':'Detalhe adicional que ajude na análise da solicitação de validação.','10':'Confirmo a intenção de retornar no período indicado.','11':'Descreva os motivos do afastamento.','12':'Motivo da matrícula especial e informações adicionais.','13':'Descreva as dificuldades específicas e o apoio necessário.','14':'Motivo dos ajustes solicitados.','15':'Motivo pelo qual necessita de planos de estudo.','16':'Descreva o domínio do conteúdo e por que acredita ter condições para EAE.','17':'Descreva por que acredita ter condições de cursar sem o pré-requisito.','18':'Informações adicionais sobre a situação de colação de grau.','19':'Descreva detalhadamente a solicitação.','20':'Informe o motivo do horário diferenciado.','21':'Descreva a situação socioeconômica e os motivos da solicitação.','22':'Informações adicionais sobre a carteirinha de saída antecipada.','25':'Informe o motivo pelo qual deseja cancelar a matrícula nas UCs.'
        };
        const desc = document.getElementById('description');
        if (desc) desc.placeholder = placeholders[typeId] || 'Descreva com o máximo de detalhes possível...';
        const nextBtn    = document.getElementById('step3-next-btn');
        const submitBtn3 = document.getElementById('step3-submit-btn');
        if (typeId === '22') { nextBtn?.classList.add('hidden'); submitBtn3?.classList.remove('hidden'); }
        else                 { nextBtn?.classList.remove('hidden'); submitBtn3?.classList.add('hidden'); }
    }

    // --- Type cards ---
    typeSearch.addEventListener('input', function () {
        const term = this.value.toLowerCase().trim();
        if (term.length > 0) { typeCards.forEach(c => c.classList.remove('selected')); requestTypeSelect.value = ''; }
        typeCards.forEach(c => c.classList.toggle('hidden', !c.dataset.name.includes(term)));
        ['section-featured','section-other'].forEach(sid => {
            const sec = document.getElementById(sid);
            if (!sec) return;
            sec.classList.toggle('hidden', sec.querySelectorAll('.type-card:not(.hidden)').length === 0);
        });
    });

    typeCards.forEach(card => {
        card.addEventListener('click', function () {
            typeCards.forEach(c => c.classList.remove('selected','error-ring','border-red-400'));
            document.getElementById('type_error_message').classList.add('hidden');
            document.getElementById('types_grid')?.classList.remove('border-red-200','bg-red-50/10');
            this.classList.add('selected');
            requestTypeSelect.value = this.dataset.id;
            requestTypeSelect.dispatchEvent(new Event('change'));
        });
    });

    requestTypeSelect.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        const info = opt.dataset.info || '', attention = opt.dataset.attention || '';
        const infoContainer = document.getElementById('tipo-info');
        if (info || attention) {
            infoContainer.classList.remove('hidden');
            document.getElementById('info_content').innerHTML = info;
            document.getElementById('info_content').classList.toggle('hidden', !info);
            const ab = document.getElementById('attention_box');
            if (attention) { document.getElementById('attention_content').innerHTML = attention; ab.classList.remove('hidden'); }
            else ab.classList.add('hidden');
        } else { infoContainer.classList.add('hidden'); }
    });

    // --- Checkbox "UC não listada" tipo 2 ---
    const ucChk2 = document.getElementById('uc_other_check_2');
    const ucInp2 = document.getElementById('uc_other_name_2');
    if (ucChk2 && ucInp2) ucChk2.addEventListener('change', () => ucInp2.classList.toggle('hidden', !ucChk2.checked));

    // --- Checkbox "professor não listado" tipo 2 ---
    const chk2 = document.getElementById('teacher_other_check_2');
    const inp2 = document.getElementById('teacher_other_name_2');
    if (chk2 && inp2) chk2.addEventListener('change', () => inp2.classList.toggle('hidden', !chk2.checked));

    // --- Radios tipo 9 ---
    ['val_re','val_rs','val_eae'].forEach(rid => {
        document.getElementById(rid)?.addEventListener('change', () => {
            ['uc_re_block','uc_rs_block','uc_eae_block'].forEach(id => document.getElementById(id)?.classList.add('hidden'));
            const map = { val_re:'uc_re_block', val_rs:'uc_rs_block', val_eae:'uc_eae_block' };
            document.getElementById(map[rid])?.classList.remove('hidden');
        });
    });

    // --- Upload do termo tipo 22 ---
    document.getElementById('type22-file-upload')?.addEventListener('change', function () {
        const list = document.getElementById('type22-file-list');
        const tmp  = document.getElementById('type22-temp-files-container');
        [...this.files].forEach(file => { const id = 'file-' + Math.random().toString(36).substr(2,9); uploadFileAsync(file, list, tmp, id); });
        this.value = '';
        document.getElementById('type22-upload-error')?.classList.add('hidden');
    });

    // --- step3-submit-btn (tipo 22) ---
    document.getElementById('step3-submit-btn')?.addEventListener('click', function () {
        const step3 = document.getElementById('form-step-3');
        let valid = true;
        step3.querySelectorAll('input[required], select[required], textarea[required]').forEach(el => {
            if (!el.checkValidity() && !el.closest('.hidden')) { el.reportValidity(); valid = false; }
        });
        if (!valid) return;
        const cienteEl = document.getElementById('libera_ciente');
        if (cienteEl && cienteEl.value.trim().toUpperCase() !== 'CIENTE') {
            document.getElementById('libera_ciente_error')?.classList.remove('hidden');
            cienteEl.focus(); return;
        } else { document.getElementById('libera_ciente_error')?.classList.add('hidden'); }
        if (!document.getElementById('libera_declaration')?.checked) { alert('Marque a declaração de ciência para prosseguir.'); return; }
        if (document.querySelectorAll('#type22-temp-files-container input[type="hidden"]').length === 0) {
            document.getElementById('type22-upload-error')?.classList.remove('hidden');
            document.getElementById('type22-upload-container')?.scrollIntoView({ behavior:'smooth', block:'start' }); return;
        } else { document.getElementById('type22-upload-error')?.classList.add('hidden'); }
        if (document.querySelectorAll('.upload-progress-container:not(.hidden)').length > 0) { alert('Aguarde o término dos uploads.'); return; }
        document.getElementById('step3-btn-text').textContent = 'Enviando...';
        document.getElementById('step3-btn-spinner')?.classList.remove('hidden');
        this.disabled = true;
        form.submit();
    });

    // --- Upload geral ---
    const fileUpload       = document.getElementById('file-upload');
    const fileList         = document.getElementById('file-list');
    const tempFilesContainer = document.getElementById('temp-files-container');

    fileUpload?.addEventListener('change', function (e) {
        Array.from(e.target.files).forEach(async file => {
            if (file.size > 5 * 1024 * 1024) { alert('Arquivo "' + file.name + '" muito grande (máx 5MB).'); return; }
            let toUpload = file;
            if (file.type.startsWith('image/')) { try { toUpload = await resizeImage(file); } catch(e) { console.error(e); } }
            uploadFileAsync(toUpload, fileList, tempFilesContainer);
        });
        setTimeout(() => { fileUpload.value = ''; }, 100);
    });

    form.addEventListener('submit', function (e) {
        function isVisible(el) { let p = el; while (p && p !== form) { if (p.classList?.contains('hidden')) return false; p = p.parentElement; } return true; }
        const bad = Array.from(form.querySelectorAll('input[required], select[required], textarea[required]')).find(el => el.type !== 'hidden' && !el.disabled && isVisible(el) && !el.checkValidity());
        if (bad) { e.preventDefault(); bad.reportValidity(); return; }
        if (document.querySelectorAll('.upload-progress-container:not(.hidden)').length > 0) { e.preventDefault(); alert('Aguarde o término dos uploads.'); return; }
        const submitBtn = document.getElementById('submit_btn');
        submitBtn.disabled = true;
        document.getElementById('btn_text').textContent = 'Enviando...';
        document.getElementById('btn_spinner')?.classList.remove('hidden');
    });

    function resizeImage(file) {
        return new Promise((resolve, reject) => {
            const maxW = 1600, maxH = 1600;
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = e => {
                const img = new Image(); img.src = e.target.result;
                img.onload = () => {
                    let w = img.width, h = img.height;
                    if (w <= maxW && h <= maxH) { resolve(file); return; }
                    if (w > h) { if (w > maxW) { h *= maxW/w; w = maxW; } } else { if (h > maxH) { w *= maxH/h; h = maxH; } }
                    const canvas = document.createElement('canvas'); canvas.width = w; canvas.height = h;
                    canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                    canvas.toBlob(blob => resolve(new File([blob], file.name, { type:file.type, lastModified:Date.now() })), file.type, 0.8);
                };
                img.onerror = reject;
            };
            reader.onerror = reject;
        });
    }

    function uploadFileAsync(file, targetList, targetContainer) {
        targetList      = targetList      || fileList;
        targetContainer = targetContainer || tempFilesContainer;
        const fileId = 'file-' + Math.random().toString(36).substr(2, 9);
        const fileItem = document.createElement('div');
        fileItem.id = fileId;
        fileItem.className = 'bg-white border border-gray-200 rounded-lg p-3 shadow-sm flex flex-col space-y-2';
        fileItem.innerHTML = `
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3 overflow-hidden">
                    <svg class="h-5 w-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                    <span class="text-sm font-medium text-gray-700 truncate">${file.name}</span>
                    <span class="text-xs text-gray-400">(${(file.size/1024).toFixed(1)} KB)</span>
                </div>
                <button type="button" class="remove-file text-gray-400 hover:text-red-500 transition-colors">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div class="upload-progress-container h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                <div class="upload-progress h-full bg-[#1CBB9B] transition-all duration-300" style="width:0%"></div>
            </div>
            <div class="upload-status text-[10px] font-bold text-gray-400 uppercase tracking-wider">Iniciando upload...</div>
        `;
        targetList.appendChild(fileItem);
        const progressBar = fileItem.querySelector('.upload-progress');
        const statusText  = fileItem.querySelector('.upload-status');
        const removeBtn   = fileItem.querySelector('.remove-file');
        const formData = new FormData(); formData.append('file', file);
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '<?= BASE_URL ?>/upload_temp.php', true);
        xhr.upload.onprogress = e => { if (e.lengthComputable) { const pct = (e.loaded/e.total)*100; progressBar.style.width = pct+'%'; statusText.textContent = 'Enviando: '+Math.round(pct)+'%'; } };
        xhr.onload = function () {
            if (xhr.status === 200) {
                const r = JSON.parse(xhr.responseText);
                if (r.success) {
                    statusText.textContent = 'Upload Concluído'; statusText.classList.replace('text-gray-400','text-green-600');
                    fileItem.querySelector('.upload-progress-container').classList.add('hidden');
                    const hi = document.createElement('input'); hi.type='hidden'; hi.name='temp_files[]'; hi.value=r.temp_filename; hi.dataset.fileId=fileId;
                    targetContainer.appendChild(hi);
                } else { handleUploadError(fileItem, r.message); }
            } else { handleUploadError(fileItem, 'Erro de conexão.'); }
        };
        xhr.onerror = () => handleUploadError(fileItem, 'Erro ao enviar arquivo.');
        xhr.send(formData);
        removeBtn.addEventListener('click', () => { xhr.abort(); fileItem.remove(); targetContainer.querySelector('input[data-file-id="'+fileId+'"]')?.remove(); });
    }

    function handleUploadError(fileItem, message) {
        const s = fileItem.querySelector('.upload-status');
        s.textContent = 'ERRO: ' + message; s.classList.replace('text-gray-400','text-red-500');
        fileItem.querySelector('.upload-progress')?.classList.replace('bg-[#1CBB9B]','bg-red-500');
    }

});

window.generateSaidaAntecipadaPDF = function () {
    if (!window.jspdf) { alert('Aguarde o carregamento da biblioteca de PDF.'); return; }
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ unit:'mm', format:'a4' });
    const pageW = 210, mL = 20, mR = 20, cW = pageW - mL - mR;
    if (LOGO_DATA) { const lh = 13, lw = lh * (1422/393); doc.addImage(LOGO_DATA,'PNG',mL,14,lw,lh); }
    doc.setFont('helvetica','normal'); doc.setFontSize(9); doc.setTextColor(90,90,90);
    doc.text('Instituto Federal de Santa Catarina', pageW-mR, 18, {align:'right'});
    doc.text('Câmpus Garopaba', pageW-mR, 23.5, {align:'right'});
    doc.setDrawColor(28,187,155); doc.setLineWidth(0.6); doc.line(mL,33,pageW-mR,33);
    doc.setFont('helvetica','bold'); doc.setFontSize(13); doc.setTextColor(25,25,25);
    doc.text('TERMO DE AUTORIZAÇÃO DE SAÍDA ANTECIPADA', pageW/2, 45, {align:'center'});
    doc.setDrawColor(210,210,210); doc.setLineWidth(0.3); doc.line(mL,51,pageW-mR,51);
    const studentName  = document.getElementById('student_name')?.value || '________________________';
    const courseEl     = document.getElementById('course_select');
    const courseName   = courseEl?.options[courseEl.selectedIndex]?.text || '________________________';
    const guardianName = document.getElementById('libera_guardian_name')?.value || '________________________';
    let y = 62; const lh2 = 6.5;
    doc.setFont('helvetica','normal'); doc.setFontSize(11); doc.setTextColor(35,35,35);
    const para1 = `Autorizo a saída antecipada do/a estudante ${studentName}, matriculado/a no ${courseName}, menor de idade pelo qual sou responsável legal, das dependências do IFSC Câmpus Garopaba, nas seguintes situações:`;
    const para1Lines = doc.splitTextToSize(para1, cW); doc.text(para1Lines, mL, y); y += para1Lines.length * lh2 + 4;
    const sits = ['Realização de trabalhos acadêmicos fora de horário de aula;','Troca de horário de aulas;','Ausência de professores/as;','Para atendimento extraclasse com docente, monitoria, pendência,\n   atividade esportiva e projetos;','Finalização antecipada de avaliações.'];
    doc.setFontSize(10.5);
    sits.forEach((s,i) => { const lines = doc.splitTextToSize(`${i+1}. ${s}`, cW-6); doc.text(lines, mL+6, y); y += lines.length*6+1.5; });
    y += 3;
    doc.setFontSize(11); doc.setFont('helvetica','bold'); doc.text('Estou ciente de que:', mL, y); y += lh2;
    doc.setFont('helvetica','normal'); doc.setFontSize(10.5);
    ['Sou responsável por orientar o/a estudante a não usar desta autorização para ausentar-se de atividades acadêmicas programadas;','Para cancelar esta autorização é necessário comparecer à Coordenadoria Pedagógica do Câmpus.'].forEach((txt,i) => {
        const lines = doc.splitTextToSize(`${i+1}) ${txt}`, cW-6); doc.text(lines,mL+6,y); y += lines.length*6+2;
    });
    y += 10;
    doc.setFillColor(243,253,249); doc.setDrawColor(28,187,155); doc.setLineWidth(0.4); doc.roundedRect(mL,y-4,cW,22,2.5,2.5,'FD');
    const lX = mL+6, vX = mL+52;
    doc.setFont('helvetica','bold'); doc.setFontSize(10.5); doc.setTextColor(35,35,35);
    doc.text('Responsável:', lX, y+4); doc.setFont('helvetica','normal'); doc.text(guardianName, vX, y+4);
    doc.setFont('helvetica','bold'); doc.text('Estudante:', lX, y+13); doc.setFont('helvetica','normal'); doc.text(studentName, vX, y+13);
    y += 28;
    doc.setFont('helvetica','normal'); doc.setFontSize(10); doc.setTextColor(90,90,90);
    doc.text('_____________________', mL, y); y += 5.5;
    doc.setFontSize(9); doc.setTextColor(70,70,70); doc.text('Assinatura feita no gov.br', mL, y); y += 5;
    doc.setTextColor(19,81,180); doc.textWithLink('Clique aqui para assinar', mL, y, {url:'https://assinador.iti.br'});
    doc.setDrawColor(28,187,155); doc.setLineWidth(0.3); doc.line(mL,282,pageW-mR,282);
    doc.setFontSize(8); doc.setTextColor(140,140,140);
    doc.text('Assine digitalmente via Gov.br em assinador.iti.br', pageW/2, 287, {align:'center'});
    doc.text('Instituto Federal de Santa Catarina – Câmpus Garopaba', pageW/2, 292, {align:'center'});
    doc.save('termo-autorizacao-saida-antecipada.pdf');
};
</script>
</body>
</html>
