<?php
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../src/Auth.php';

Auth::check();
$user = Auth::user();

$db   = new Database();
$conn = $db->getConnection();

$stmtRole = $conn->prepare("SELECT is_sysadmin FROM roles WHERE id = :id");
$stmtRole->execute([':id' => $user['user_role']]);
$isSysAdmin = (bool)$stmtRole->fetchColumn();

$allowedRoles = [6, 14]; // DEPE, Assessoria DEPE
if (!$isSysAdmin && !in_array((int)$user['user_role'], $allowedRoles)) {
    header('Location: dashboard.php');
    exit;
}

$error   = '';
$success = '';

$currentMonth    = (int)date('n');
$currentYear     = date('Y');
$defaultSemester = $currentYear . '.' . ($currentMonth >= 2 && $currentMonth <= 7 ? '1' : '2');

// Campus roles (fixed list — not per-course)
$campusRoles = [
    'Direção do Câmpus',
    'Chefe do DEPE',
    'Assessoria DEPE',
    'Coordenadoria Pedagógica',
    'Secretaria Acadêmica',
    'Coordenadoria de Extensão',
    'Coordenadoria de Pesquisa e Inovação',
    'NEAD',
    'NAE',
    'Biblioteca',
    'CTIC',
];

function abbrevTeacherName(string $full): string {
    $words = explode(' ', mb_convert_case(trim($full), MB_CASE_TITLE, 'UTF-8'));
    if (count($words) <= 2) return implode(' ', $words);
    return $words[0] . ' ' . end($words);
}

// --- Remover coordenador ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_coord_id'])) {
    $conn->prepare("DELETE FROM course_coordinators WHERE id = :id")
         ->execute([':id' => (int)$_POST['delete_coord_id']]);
    $success = 'Coordenação removida.';
}

// --- Salvar coordenações de curso (DnD) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_dnd'])) {
    $semester = trim($_POST['semester'] ?? '');
    if (!preg_match('/^\d{4}\.[12]$/', $semester)) {
        $error = 'Semestre inválido.';
    } else {
        $conn->beginTransaction();
        try {
            $conn->prepare("DELETE FROM course_coordinators WHERE semester = :s AND course_id IS NOT NULL")
                 ->execute([':s' => $semester]);

            $stmtIns = $conn->prepare("
                INSERT INTO course_coordinators (semester, teacher_id, teacher_name, course_id, role_name)
                VALUES (:sem, :tid, :tname, :cid, :role)
            ");
            $stmtTN = $conn->prepare("SELECT name FROM teachers WHERE id = :id");
            $stmtCN = $conn->prepare("SELECT name FROM courses WHERE id = :id");

            foreach ($_POST['assignments'] ?? [] as $courseId => $teacherId) {
                $courseId  = (int)$courseId;
                $teacherId = (int)$teacherId;
                if (!$courseId || !$teacherId) continue;

                $stmtTN->execute([':id' => $teacherId]);
                $tName = $stmtTN->fetchColumn();
                if (!$tName) continue;

                $stmtCN->execute([':id' => $courseId]);
                $cName = $stmtCN->fetchColumn() ?: '';

                $stmtIns->execute([
                    ':sem'   => $semester,
                    ':tid'   => $teacherId,
                    ':tname' => $tName,
                    ':cid'   => $courseId,
                    ':role'  => 'Coordenação de ' . $cName,
                ]);
            }
            $conn->commit();
            $success = 'Coordenações de cursos salvas para ' . $semester . '.';
        } catch (Exception $e) {
            $conn->rollBack();
            $error = 'Erro ao salvar coordenações de curso: ' . $e->getMessage();
        }
    }
}

// --- Salvar outras coordenações do câmpus ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_campus'])) {
    $semester = trim($_POST['semester'] ?? '');
    if (!preg_match('/^\d{4}\.[12]$/', $semester)) {
        $error = 'Semestre inválido.';
    } else {
        $conn->beginTransaction();
        try {
            // Remove campus entries for this semester
            $placeholders = implode(',', array_fill(0, count($campusRoles), '?'));
            $conn->prepare("DELETE FROM course_coordinators WHERE semester = ? AND course_id IS NULL AND role_name IN ($placeholders)")
                 ->execute(array_merge([$semester], $campusRoles));

            $stmtIns = $conn->prepare("
                INSERT INTO course_coordinators (semester, teacher_id, teacher_name, course_id, role_name)
                VALUES (:sem, :tid, :tname, NULL, :role)
            ");
            $stmtTN = $conn->prepare("SELECT name FROM teachers WHERE id = :id AND active = 1");
            foreach ($_POST['campus'] ?? [] as $role => $teacherName) {
                $teacherName = trim($teacherName);
                $role        = htmlspecialchars_decode($role);
                if (!$teacherName || !in_array($role, $campusRoles)) continue;

                // Se teacher_id foi enviado, usa o nome canônico da tabela teachers
                $tid = (int)($_POST['campus_tid'][$role] ?? 0) ?: null;
                if ($tid) {
                    $stmtTN->execute([':id' => $tid]);
                    $canonical = $stmtTN->fetchColumn();
                    if ($canonical) $teacherName = $canonical;
                }

                $stmtIns->execute([':sem' => $semester, ':tid' => $tid, ':tname' => $teacherName, ':role' => $role]);
            }
            $conn->commit();
            $success = 'Outras coordenações salvas para ' . $semester . '.';
        } catch (Exception $e) {
            $conn->rollBack();
            $error = 'Erro ao salvar outras coordenações: ' . $e->getMessage();
        }
    }
}

// --- Adicionar manualmente ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_coord'])) {
    $semester    = trim($_POST['semester'] ?? '');
    $teacherId   = (int)($_POST['teacher_id'] ?? 0) ?: null;
    $teacherName = trim($_POST['teacher_name'] ?? '');
    $courseId    = (int)($_POST['course_id'] ?? 0) ?: null;
    $roleName    = trim($_POST['role_name'] ?? '');

    if (!preg_match('/^\d{4}\.[12]$/', $semester) || !$teacherName || !$roleName) {
        $error = 'Preencha todos os campos obrigatórios.';
    } else {
        if ($teacherId) {
            $stmtT = $conn->prepare("SELECT name FROM teachers WHERE id = :id");
            $stmtT->execute([':id' => $teacherId]);
            $tName = $stmtT->fetchColumn();
            if ($tName) $teacherName = $tName;
        }
        $conn->prepare("
            INSERT INTO course_coordinators (semester, teacher_id, teacher_name, course_id, role_name)
            VALUES (:sem, :tid, :tname, :cid, :role)
        ")->execute([
            ':sem'   => $semester,
            ':tid'   => $teacherId,
            ':tname' => $teacherName,
            ':cid'   => $courseId,
            ':role'  => $roleName,
        ]);
        $success = 'Coordenador adicionado com sucesso.';
    }
}

// --- Dados ---

$stmtSems = $conn->prepare("
    SELECT DISTINCT semester FROM (
        SELECT semester FROM schedule_slots
        UNION SELECT semester FROM course_coordinators
    ) s ORDER BY semester DESC
");
$stmtSems->execute();
$semesters = $stmtSems->fetchAll(PDO::FETCH_COLUMN);

$viewSemester = $_GET['semester'] ?? ($_POST['semester'] ?? ($semesters[0] ?? $defaultSemester));

$stmtCoords = $conn->prepare("
    SELECT cc.id, cc.teacher_id, cc.teacher_name, cc.role_name, cc.course_id, c.name AS course_name
    FROM course_coordinators cc
    LEFT JOIN courses c ON c.id = cc.course_id
    WHERE cc.semester = :sem
    ORDER BY cc.role_name, cc.teacher_name
");
$stmtCoords->execute([':sem' => $viewSemester]);
$coords = $stmtCoords->fetchAll(PDO::FETCH_ASSOC);

$coordByCourse = [];
$coordByRole   = [];
foreach ($coords as $c) {
    if ($c['course_id']) {
        $coordByCourse[(int)$c['course_id']] = $c;
    } elseif ($c['role_name'] && in_array($c['role_name'], $campusRoles)) {
        $coordByRole[$c['role_name']] = ['name' => $c['teacher_name'], 'tid' => $c['teacher_id'] ?? ''];
    }
}

$assignedTeacherIds = array_column(array_filter($coords, fn($c) => $c['teacher_id']), 'teacher_id', 'teacher_id');

$stmtTeachers = $conn->prepare("SELECT id, name FROM teachers WHERE active = 1 ORDER BY name");
$stmtTeachers->execute();
$teachers = $stmtTeachers->fetchAll(PDO::FETCH_ASSOC);

$stmtCourses = $conn->prepare("SELECT id, name, abbreviation FROM courses WHERE active = 1 ORDER BY name");
$stmtCourses->execute();
$courses = $stmtCourses->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Coordenadores por Semestre';
require_once 'layout/header.php';
require_once 'layout/sidebar.php';
?>

<main class="flex-1 overflow-y-auto p-6 bg-[#F2F4F8] dark:bg-gray-900">
<div class="max-w-7xl mx-auto">

    <div class="mb-5">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Coordenadores por Semestre</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Coordenações de curso e do câmpus por semestre letivo</p>
    </div>

    <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 text-sm"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Seletor de semestre -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 px-4 py-3 mb-5 flex items-center gap-3">
        <form method="GET" class="flex items-center gap-2">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Semestre:</label>
            <select name="semester" onchange="this.form.submit()"
                class="border border-gray-200 dark:border-gray-600 rounded-lg px-3 py-1.5 text-sm bg-gray-50 dark:bg-gray-700 dark:text-gray-100 focus:ring-2 focus:ring-brand-DEFAULT outline-none">
                <?php if (empty($semesters)): ?>
                    <option value="<?= htmlspecialchars($defaultSemester) ?>"><?= htmlspecialchars($defaultSemester) ?></option>
                <?php else: ?>
                    <?php foreach ($semesters as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= $viewSemester === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </form>
    </div>

    <!-- ══════════════════════════════════════════════
         COORDENAÇÕES DE CURSO — DnD grid compacto
    ══════════════════════════════════════════════ -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 mb-5">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-gray-900 dark:text-gray-100 text-sm">Coordenações de Curso — <?= htmlspecialchars($viewSemester) ?></h2>
                <p class="text-xs text-gray-400 mt-0.5">Arraste um docente para a coordenação desejada</p>
            </div>
            <form method="POST" id="dnd-form">
                <?= Csrf::field() ?>
                <input type="hidden" name="save_dnd" value="1">
                <input type="hidden" name="semester" value="<?= htmlspecialchars($viewSemester) ?>">
                <!-- hidden inputs for assignments injected by JS -->
                <div id="assignment-inputs"></div>
                <button type="submit"
                    class="px-4 py-1.5 bg-brand-DEFAULT text-white text-xs font-semibold rounded-lg hover:bg-brand-dark transition-colors">
                    Salvar Coordenações
                </button>
            </form>
        </div>

        <div class="p-5">
            <!-- Filtro de docentes -->
            <input type="text" id="teacher-filter"
                placeholder="Filtrar docentes..."
                class="w-full border border-gray-200 dark:border-gray-600 rounded-lg px-3 py-1.5 text-sm mb-3 focus:ring-2 focus:ring-brand-DEFAULT outline-none bg-gray-50 dark:bg-gray-700 dark:text-gray-100">

            <!-- Grid: docentes (esquerda) + cursos (direita) -->
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-5">

                <!-- Docentes: pills compactos -->
                <div class="lg:col-span-2">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Docentes</p>
                    <div id="teacher-list" class="flex flex-wrap gap-2 content-start">
                        <?php foreach ($teachers as $t):
                            $abbrev = abbrevTeacherName($t['name']);
                            $isAssigned = isset($assignedTeacherIds[$t['id']]);
                        ?>
                        <div class="teacher-card inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border
                                    cursor-grab select-none transition-all text-xs font-semibold shadow-sm
                                    <?= $isAssigned
                                        ? 'border-amber-300 bg-amber-50 text-amber-700 hover:border-amber-400 hover:shadow'
                                        : 'border-gray-200 bg-white text-gray-600 hover:border-brand-DEFAULT hover:bg-brand-DEFAULT/5 hover:text-brand-DEFAULT hover:shadow' ?>"
                            draggable="true"
                            data-teacher-id="<?= $t['id'] ?>"
                            data-teacher-name="<?= htmlspecialchars($t['name']) ?>"
                            data-teacher-abbrev="<?= htmlspecialchars($abbrev) ?>"
                            title="<?= htmlspecialchars($t['name']) ?>">
                            <?= htmlspecialchars($abbrev) ?>
                            <?php if ($isAssigned): ?>
                            <span class="assigned-badge w-2 h-2 rounded-full bg-amber-400 flex-shrink-0"></span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Cursos: grid de drop zones -->
                <div class="lg:col-span-3">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Cursos</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3">
                        <?php foreach ($courses as $c):
                            $currentCoord     = $coordByCourse[(int)$c['id']] ?? null;
                            $coordTeacherName = $currentCoord['teacher_name'] ?? '';
                            $coordTeacherId   = $currentCoord['teacher_id'] ?? '';
                            $coordAbbrev      = $coordTeacherName ? abbrevTeacherName($coordTeacherName) : '';
                            $courseLabel      = $c['abbreviation'] ?: preg_replace('/^(Técnico em|CST em|PROEJA em|FIC em|Pós-graduação em)\s*/i', '', $c['name']);
                        ?>
                        <div class="course-slot rounded-xl border-2 border-dashed border-gray-200 bg-gray-50
                                    hover:border-brand-DEFAULT/30 hover:bg-brand-DEFAULT/5 transition-all p-3"
                            data-course-id="<?= $c['id'] ?>"
                            data-course-name="<?= htmlspecialchars($c['name']) ?>"
                            ondragover="handleDragOver(event, this)"
                            ondragleave="handleDragLeave(event, this)"
                            ondrop="handleDrop(event, this)">
                            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide leading-tight mb-2 truncate"
                               title="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($courseLabel) ?></p>
                            <div id="assigned-<?= $c['id'] ?>" class="mb-2 <?= $coordTeacherName ? '' : 'hidden' ?>">
                                <?php if ($coordTeacherName): ?>
                                <span class="inline-flex items-center gap-1 text-xs bg-brand-DEFAULT/10 text-brand-DEFAULT
                                             rounded-full pl-2 pr-0.5 py-0.5 font-medium max-w-full min-w-0"
                                    data-teacher-id="<?= htmlspecialchars($coordTeacherId) ?>"
                                    data-teacher-name="<?= htmlspecialchars($coordTeacherName) ?>">
                                    <span class="truncate"><?= htmlspecialchars($coordAbbrev) ?></span>
                                    <button type="button" onclick="clearAssignment(<?= $c['id'] ?>)"
                                        class="text-brand-DEFAULT/40 hover:text-red-400 transition-colors text-[10px] flex-shrink-0 leading-none px-0.5">✕</button>
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="relative">
                                <input type="text"
                                    id="course-input-<?= $c['id'] ?>"
                                    placeholder="Arraste ou digite…"
                                    autocomplete="off"
                                    class="w-full text-[11px] px-2 py-1 rounded-lg border border-gray-200 bg-white text-gray-700
                                           focus:outline-none focus:ring-1 focus:ring-brand-DEFAULT/50 focus:border-brand-DEFAULT/50
                                           placeholder-gray-300 transition"
                                    oninput="courseInputFilter(<?= $c['id'] ?>, this.value)"
                                    onfocus="courseInputFilter(<?= $c['id'] ?>, this.value)"
                                    onblur="hideCourseDropdown(<?= $c['id'] ?>, 200)">
                                <ul id="course-suggest-<?= $c['id'] ?>"
                                    class="hidden absolute left-0 right-0 top-full mt-0.5 bg-white border border-gray-200 rounded-lg shadow-lg z-50 max-h-40 overflow-y-auto text-xs">
                                </ul>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div><!-- /grid docentes+cursos -->
        </div>
    </div><!-- /coordenações de curso -->

    <!-- ══════════════════════════════════════════════
         OUTRAS COORDENAÇÕES DO CÂMPUS
    ══════════════════════════════════════════════ -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 mb-5">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-gray-900 dark:text-gray-100 text-sm">Outras Coordenações do Câmpus — <?= htmlspecialchars($viewSemester) ?></h2>
                <p class="text-xs text-gray-400 mt-0.5">Arraste um docente para atribuir</p>
            </div>
            <form method="POST" id="campus-form">
                <?= Csrf::field() ?>
                <input type="hidden" name="save_campus" value="1">
                <input type="hidden" name="semester" value="<?= htmlspecialchars($viewSemester) ?>">
                <div id="campus-inputs"></div>
                <button type="submit"
                    class="px-4 py-1.5 bg-brand-DEFAULT text-white text-xs font-semibold rounded-lg hover:bg-brand-dark transition-colors">
                    Salvar
                </button>
            </form>
        </div>

        <div class="p-5">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-3">
                <?php foreach ($campusRoles as $role):
                    $roleData    = $coordByRole[$role] ?? null;
                    $currentName = $roleData['name'] ?? '';
                    $currentTid  = $roleData['tid'] ?? '';
                    $abbrev      = $currentName ? abbrevTeacherName($currentName) : '';
                    $roleId      = 'campus-' . md5($role);
                ?>
                <div class="campus-slot rounded-xl border-2 border-dashed border-gray-200 bg-gray-50
                            hover:border-indigo-200 hover:bg-indigo-50/30 transition-all p-3"
                    data-role="<?= htmlspecialchars($role) ?>"
                    data-slot-id="<?= $roleId ?>"
                    ondragover="handleDragOver(event, this)"
                    ondragleave="handleDragLeave(event, this)"
                    ondrop="handleCampusDrop(event, this)">
                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wide leading-tight mb-2 truncate"
                       title="<?= htmlspecialchars($role) ?>"><?= htmlspecialchars($role) ?></p>
                    <input type="hidden" id="<?= $roleId ?>-name" value="<?= htmlspecialchars($currentName) ?>">
                    <input type="hidden" id="<?= $roleId ?>-tid"  value="<?= htmlspecialchars($currentTid) ?>">
                    <div id="<?= $roleId ?>-display" class="mb-2 <?= $currentName ? '' : 'hidden' ?>">
                        <?php if ($currentName): ?>
                        <span class="inline-flex items-center gap-1 text-xs bg-indigo-100 text-indigo-700
                                     rounded-full pl-2 pr-0.5 py-0.5 font-medium max-w-full min-w-0"
                              title="<?= htmlspecialchars($currentName) ?>">
                            <span class="truncate"><?= htmlspecialchars($abbrev) ?></span>
                            <button type="button" onclick="clearCampusSlot('<?= $roleId ?>')"
                                class="text-indigo-300 hover:text-red-400 transition-colors text-[10px] flex-shrink-0 leading-none px-0.5">✕</button>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="relative">
                        <input type="text"
                            id="<?= $roleId ?>-input"
                            placeholder="Arraste ou digite…"
                            autocomplete="off"
                            class="campus-text-input w-full text-[11px] px-2 py-1 rounded-lg border border-gray-200 bg-white text-gray-700
                                   focus:outline-none focus:ring-1 focus:ring-indigo-300 focus:border-indigo-300 placeholder-gray-300 transition"
                            oninput="campusInputFilter('<?= $roleId ?>', this.value)"
                            onfocus="campusInputFilter('<?= $roleId ?>', this.value)"
                            onblur="hideCampusDropdown('<?= $roleId ?>', 200)">
                        <ul id="<?= $roleId ?>-suggest"
                            class="campus-suggest hidden absolute left-0 right-0 top-full mt-0.5 bg-white border border-gray-200 rounded-lg shadow-lg z-50 max-h-40 overflow-y-auto text-xs">
                        </ul>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div><!-- /outras coordenações -->

    <!-- ══════════════════════════════════════════════
         LISTA COMPLETA DO SEMESTRE
    ══════════════════════════════════════════════ -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden mb-5">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <h2 class="font-semibold text-gray-900 dark:text-gray-100 text-sm">Todos os Registros — <?= htmlspecialchars($viewSemester) ?></h2>
            <span class="text-xs text-gray-400"><?= count($coords) ?> registro(s)</span>
        </div>
        <?php if (empty($coords)): ?>
        <div class="px-5 py-8 text-center text-gray-400 text-sm">Nenhum coordenador cadastrado para <?= htmlspecialchars($viewSemester) ?>.</div>
        <?php else: ?>
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 dark:bg-gray-700/50 text-left">
                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Docente / Servidor</th>
                    <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Função / Coordenação</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                <?php foreach ($coords as $c): ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                    <td class="px-5 py-2.5 font-medium text-gray-800 dark:text-gray-100"><?= htmlspecialchars($c['teacher_name']) ?></td>
                    <td class="px-5 py-2.5 text-gray-600 dark:text-gray-300"><?= htmlspecialchars($c['role_name']) ?></td>
                    <td class="px-5 py-2.5 text-right">
                        <form method="POST" onsubmit="return confirm('Remover esta coordenação?')">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="delete_coord_id" value="<?= $c['id'] ?>">
                            <button type="submit" class="text-xs text-red-500 hover:text-red-700 font-medium">Remover</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Adicionar manualmente -->
    <details class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 mb-5">
        <summary class="px-5 py-3 cursor-pointer font-semibold text-gray-700 dark:text-gray-300 text-sm select-none hover:bg-gray-50 dark:hover:bg-gray-700/30 rounded-xl transition-colors">
            Adicionar manualmente
        </summary>
        <div class="px-5 pb-5 pt-2">
            <form method="POST" class="space-y-4">
                <?= Csrf::field() ?>
                <input type="hidden" name="add_coord" value="1">
                <input type="hidden" name="semester" value="<?= htmlspecialchars($viewSemester) ?>">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Docente / Servidor <span class="text-red-500">*</span></label>
                        <select name="teacher_id" id="coord_teacher_id"
                            class="w-full border border-gray-200 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-gray-50 dark:bg-gray-700 dark:text-gray-100 focus:ring-2 focus:ring-brand-DEFAULT outline-none">
                            <option value="">— Selecione ou use o campo abaixo —</option>
                            <?php foreach ($teachers as $t): ?>
                            <option value="<?= $t['id'] ?>" data-name="<?= htmlspecialchars($t['name']) ?>"><?= htmlspecialchars($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="teacher_name" id="coord_teacher_name"
                            placeholder="Ou digite o nome diretamente"
                            class="w-full mt-2 border border-gray-200 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-gray-50 dark:bg-gray-700 dark:text-gray-100 focus:ring-2 focus:ring-brand-DEFAULT outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Função / Denominação <span class="text-red-500">*</span></label>
                        <input type="text" name="role_name" id="coord_role_name" required
                            placeholder="Ex: NAPNE, Coordenação Pedagógica..."
                            class="w-full border border-gray-200 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-gray-50 dark:bg-gray-700 dark:text-gray-100 focus:ring-2 focus:ring-brand-DEFAULT outline-none">
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="px-5 py-2 bg-brand-DEFAULT text-white text-sm font-semibold rounded-lg hover:bg-brand-dark transition-colors">Adicionar</button>
                </div>
            </form>
        </div>
    </details>

</div><!-- /max-w-7xl -->
</main>

<script>
// ─── State ────────────────────────────────────────────────────────────────────

// assignments: courseId → {teacherId, teacherName}
const assignments = {};

// Pre-fill from server-rendered data
document.querySelectorAll('.course-slot').forEach(slot => {
    const div   = slot.querySelector('[id^="assigned-"]');
    const span  = div?.querySelector('[data-teacher-id]');
    if (span) {
        const cid = slot.dataset.courseId;
        assignments[cid] = {
            teacherId:   span.dataset.teacherId,
            teacherName: span.dataset.teacherName,
        };
    }
});

// ─── Course DnD ───────────────────────────────────────────────────────────────

document.querySelectorAll('.teacher-card').forEach(card => {
    card.addEventListener('dragstart', function(e) {
        e.dataTransfer.effectAllowed = 'copy';
        e.dataTransfer.setData('teacher-id',   this.dataset.teacherId);
        e.dataTransfer.setData('teacher-name', this.dataset.teacherName);
        e.dataTransfer.setData('teacher-abbrev', this.dataset.teacherAbbrev);
        this.classList.add('opacity-40');
    });
    card.addEventListener('dragend', function() { this.classList.remove('opacity-40'); });
});

function handleDragOver(e, el) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'copy';
    el.classList.add('ring-2', 'ring-brand-DEFAULT', 'border-brand-DEFAULT', 'bg-brand-DEFAULT/5');
}

function handleDragLeave(e, el) {
    if (!el.contains(e.relatedTarget))
        el.classList.remove('ring-2', 'ring-brand-DEFAULT', 'border-brand-DEFAULT', 'bg-brand-DEFAULT/5');
}

function handleDrop(e, el) {
    e.preventDefault();
    el.classList.remove('ring-2', 'ring-brand-DEFAULT', 'border-brand-DEFAULT', 'bg-brand-DEFAULT/5');

    const cid  = el.dataset.courseId;
    const tid  = e.dataTransfer.getData('teacher-id');
    const name = e.dataTransfer.getData('teacher-name');
    const abbr = e.dataTransfer.getData('teacher-abbrev');

    assignments[cid] = { teacherId: tid, teacherName: name };

    const div = document.getElementById('assigned-' + cid);
    div.innerHTML =
        `<span class="inline-flex items-center gap-1 text-xs bg-brand-DEFAULT/10 text-brand-DEFAULT
                      rounded-full pl-2 pr-0.5 py-0.5 font-medium max-w-full min-w-0"
              data-teacher-id="${escHtml(tid)}" data-teacher-name="${escHtml(name)}">
            <span class="truncate">${escHtml(abbr)}</span>
            <button type="button" onclick="clearAssignment(${escHtml(cid)})"
                class="text-brand-DEFAULT/40 hover:text-red-400 transition-colors text-[10px] flex-shrink-0 leading-none px-0.5">✕</button>
         </span>`;
    div.classList.remove('hidden');

    updateAssignedBadges();
}

function clearAssignment(cid) {
    delete assignments[cid];
    const div = document.getElementById('assigned-' + cid);
    div.innerHTML = '';
    div.classList.add('hidden');
    updateAssignedBadges();
}

function updateAssignedBadges() {
    const courseIds = new Set(Object.values(assignments).map(a => a.teacherId).filter(Boolean));
    const campusIds = new Set(
        Array.from(document.querySelectorAll('[id$="-tid"]'))
            .map(i => i.value).filter(Boolean)
    );
    const assignedIds = new Set([...courseIds, ...campusIds]);

    document.querySelectorAll('.teacher-card').forEach(card => {
        const isAssigned = assignedIds.has(card.dataset.teacherId);
        let dot = card.querySelector('.assigned-badge');
        if (isAssigned) {
            card.classList.remove('border-gray-200', 'bg-white', 'text-gray-700', 'hover:border-brand-DEFAULT', 'hover:bg-brand-DEFAULT/5', 'hover:shadow-sm');
            card.classList.add('border-amber-300', 'bg-amber-50', 'text-amber-700', 'hover:border-amber-400');
            if (!dot) {
                dot = document.createElement('span');
                dot.className = 'assigned-badge w-1.5 h-1.5 rounded-full bg-amber-400 flex-shrink-0';
                card.appendChild(dot);
            }
        } else {
            card.classList.remove('border-amber-300', 'bg-amber-50', 'text-amber-700', 'hover:border-amber-400');
            card.classList.add('border-gray-200', 'bg-white', 'text-gray-700', 'hover:border-brand-DEFAULT', 'hover:bg-brand-DEFAULT/5', 'hover:shadow-sm');
            if (dot) dot.remove();
        }
    });
}

// Build hidden inputs before submit
document.getElementById('dnd-form').addEventListener('submit', function() {
    const container = document.getElementById('assignment-inputs');
    container.innerHTML = '';
    for (const [cid, val] of Object.entries(assignments)) {
        const inp = document.createElement('input');
        inp.type  = 'hidden';
        inp.name  = `assignments[${cid}]`;
        inp.value = val.teacherId;
        container.appendChild(inp);
    }
});

// ─── Campus DnD ───────────────────────────────────────────────────────────────

function handleCampusDrop(e, el) {
    e.preventDefault();
    el.classList.remove('ring-2', 'ring-brand-DEFAULT', 'border-brand-DEFAULT', 'bg-brand-DEFAULT/5');

    const tid    = e.dataTransfer.getData('teacher-id');
    const name   = e.dataTransfer.getData('teacher-name');
    const abbr   = e.dataTransfer.getData('teacher-abbrev') || name;
    const slotId = el.dataset.slotId;

    document.getElementById(slotId + '-name').value = name;
    document.getElementById(slotId + '-tid').value  = tid || '';

    const display = document.getElementById(slotId + '-display');
    display.innerHTML =
        `<span class="inline-flex items-center gap-1 text-xs bg-indigo-100 text-indigo-700
                      rounded-full pl-2 pr-0.5 py-0.5 font-medium max-w-full min-w-0"
              title="${escHtml(name)}">
            <span class="truncate">${escHtml(abbr)}</span>
            <button type="button" onclick="clearCampusSlot('${escHtml(slotId)}')"
                class="text-indigo-300 hover:text-red-400 transition-colors text-[10px] flex-shrink-0 leading-none px-0.5">✕</button>
         </span>`;
    display.classList.remove('hidden');

    updateAssignedBadges();
}

function clearCampusSlot(slotId) {
    document.getElementById(slotId + '-name').value = '';
    document.getElementById(slotId + '-tid').value  = '';
    const display = document.getElementById(slotId + '-display');
    display.innerHTML = '';
    display.classList.add('hidden');
    const inp = document.getElementById(slotId + '-input');
    if (inp) inp.value = '';
    updateAssignedBadges();
}

// ─── Campus text-input autocomplete ─────────────────────────────────────────

const allTeachers = [
    <?php foreach ($teachers as $t): ?>
    {id: "<?= $t['id'] ?>", name: <?= json_encode($t['name']) ?>, abbrev: <?= json_encode(abbrevTeacherName($t['name'])) ?>},
    <?php endforeach; ?>
];

function campusInputFilter(slotId, query) {
    const ul = document.getElementById(slotId + '-suggest');
    if (!ul) return;
    const q = query.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    const matches = allTeachers.filter(t => {
        const n = t.name.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
        const a = t.abbrev.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
        return n.includes(q) || a.includes(q);
    }).slice(0, 8);
    ul.innerHTML = '';
    if (!q || matches.length === 0) { ul.classList.add('hidden'); return; }
    matches.forEach(t => {
        const li = document.createElement('li');
        li.className = 'px-3 py-2 cursor-pointer hover:bg-indigo-50 hover:text-indigo-700 transition-colors border-b border-gray-50 last:border-0';
        li.textContent = t.abbrev + ' — ' + t.name;
        li.addEventListener('mousedown', () => assignCampusFromInput(slotId, t));
        ul.appendChild(li);
    });
    ul.classList.remove('hidden');
}

function assignCampusFromInput(slotId, teacher) {
    document.getElementById(slotId + '-name').value = teacher.name;
    document.getElementById(slotId + '-tid').value  = teacher.id;
    const inp = document.getElementById(slotId + '-input');
    if (inp) inp.value = '';
    const display = document.getElementById(slotId + '-display');
    display.innerHTML =
        `<span class="inline-flex items-center gap-1 text-xs bg-indigo-100 text-indigo-700
                      rounded-full pl-2 pr-0.5 py-0.5 font-medium max-w-full min-w-0"
              title="${escHtml(teacher.name)}">
            <span class="truncate">${escHtml(teacher.abbrev)}</span>
            <button type="button" onclick="clearCampusSlot('${escHtml(slotId)}')"
                class="text-indigo-300 hover:text-red-400 transition-colors text-[10px] flex-shrink-0 leading-none px-0.5">✕</button>
         </span>`;
    display.classList.remove('hidden');
    document.getElementById(slotId + '-suggest').classList.add('hidden');
    updateAssignedBadges();
}

function hideCampusDropdown(slotId, delay) {
    setTimeout(() => {
        const ul = document.getElementById(slotId + '-suggest');
        if (ul) ul.classList.add('hidden');
    }, delay);
}

// ─── Course text-input autocomplete ──────────────────────────────────────────

function courseInputFilter(cid, query) {
    const ul = document.getElementById('course-suggest-' + cid);
    if (!ul) return;
    const q = query.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    const matches = allTeachers.filter(t => {
        const n = t.name.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
        const a = t.abbrev.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
        return n.includes(q) || a.includes(q);
    }).slice(0, 8);
    ul.innerHTML = '';
    if (!q || matches.length === 0) { ul.classList.add('hidden'); return; }
    matches.forEach(t => {
        const li = document.createElement('li');
        li.className = 'px-3 py-2 cursor-pointer hover:bg-brand-DEFAULT/5 hover:text-brand-DEFAULT transition-colors border-b border-gray-50 last:border-0';
        li.textContent = t.abbrev + ' — ' + t.name;
        li.addEventListener('mousedown', () => assignCourseFromInput(cid, t));
        ul.appendChild(li);
    });
    ul.classList.remove('hidden');
}

function assignCourseFromInput(cid, teacher) {
    assignments[cid] = { teacherId: teacher.id, teacherName: teacher.name };
    const inp = document.getElementById('course-input-' + cid);
    if (inp) inp.value = '';
    const div = document.getElementById('assigned-' + cid);
    div.innerHTML =
        `<span class="inline-flex items-center gap-1 text-xs bg-brand-DEFAULT/10 text-brand-DEFAULT
                      rounded-full pl-2 pr-0.5 py-0.5 font-medium max-w-full min-w-0"
              data-teacher-id="${escHtml(teacher.id)}" data-teacher-name="${escHtml(teacher.name)}">
            <span class="truncate">${escHtml(teacher.abbrev)}</span>
            <button type="button" onclick="clearAssignment(${escHtml(cid)})"
                class="text-brand-DEFAULT/40 hover:text-red-400 transition-colors text-[10px] flex-shrink-0 leading-none px-0.5">✕</button>
         </span>`;
    div.classList.remove('hidden');
    document.getElementById('course-suggest-' + cid)?.classList.add('hidden');
    updateAssignedBadges();
}

function hideCourseDropdown(cid, delay) {
    setTimeout(() => {
        const ul = document.getElementById('course-suggest-' + cid);
        if (ul) ul.classList.add('hidden');
    }, delay);
}

// Build hidden inputs for campus form before submit
document.getElementById('campus-form').addEventListener('submit', function() {
    const container = document.getElementById('campus-inputs');
    container.innerHTML = '';
    document.querySelectorAll('.campus-slot').forEach(slot => {
        const role   = slot.dataset.role;
        const slotId = slot.dataset.slotId;
        const name   = document.getElementById(slotId + '-name')?.value?.trim();
        if (!name || !role) return;
        const h1 = document.createElement('input');
        h1.type = 'hidden'; h1.name = `campus[${role}]`; h1.value = name;
        container.appendChild(h1);
        const tid = document.getElementById(slotId + '-tid')?.value?.trim();
        if (tid) {
            const h2 = document.createElement('input');
            h2.type = 'hidden'; h2.name = `campus_tid[${role}]`; h2.value = tid;
            container.appendChild(h2);
        }
    });
});

// ─── Teacher filter ───────────────────────────────────────────────────────────

document.getElementById('teacher-filter').addEventListener('input', function() {
    const q = this.value.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    document.querySelectorAll('.teacher-card').forEach(card => {
        const name = card.dataset.teacherName.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
        const abbr = card.dataset.teacherAbbrev.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
        card.classList.toggle('hidden', !name.includes(q) && !abbr.includes(q));
    });
});

// ─── Manual form helpers ──────────────────────────────────────────────────────

const teacherSelect = document.getElementById('coord_teacher_id');
const teacherInput  = document.getElementById('coord_teacher_name');

if (teacherSelect) teacherSelect.addEventListener('change', function() {
    const name = this.options[this.selectedIndex]?.dataset.name;
    if (name) teacherInput.value = name;
});

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php require_once 'layout/footer.php'; ?>
