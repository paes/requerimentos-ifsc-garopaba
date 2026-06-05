<?php
/**
 * Relatório de Segunda Chamada — requerimentos tipo 2 deferidos/concluídos.
 * Exibe UCs e professores envolvidos com lookup a partir dos IDs em extra_fields JSON.
 *
 * @author Prof. Thiago Paes
 */
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../src/Auth.php';
require_once '../../src/Helpers.php';

Auth::check();
$user = Auth::user();

$db   = new Database();
$conn = $db->getConnection();

$roleStmt = $conn->prepare("SELECT is_course_bound, is_sysadmin FROM roles WHERE id = :id");
$roleStmt->execute([':id' => $user['user_role']]);
$roleData      = $roleStmt->fetch(PDO::FETCH_ASSOC);
$isCourseBound = (bool)$roleData['is_course_bound'];
$isSysAdmin    = (bool)$roleData['is_sysadmin'];

if (!$isCourseBound && !$isSysAdmin) {
    header('Location: dashboard.php');
    exit;
}

$userCourses = $user['user_courses'] ?? [];

// Filtros
$search    = trim($_GET['search']    ?? '');
$semester  = $_GET['semester']       ?? Helpers::getCurrentSemester();
$courseId  = $_GET['course_id']      ?? '';
$teacherId = $_GET['teacher_id']     ?? '';

// Cursos disponíveis
if ($isSysAdmin) {
    $stmtCourses = $conn->query("SELECT id, name FROM courses WHERE active = 1 ORDER BY name ASC");
} else {
    $inList = empty($userCourses) ? '0' : implode(',', array_map('intval', $userCourses));
    $stmtCourses = $conn->query("SELECT id, name FROM courses WHERE id IN ($inList) ORDER BY name ASC");
}
$courses = $stmtCourses->fetchAll(PDO::FETCH_ASSOC);

// Professores para o filtro
$stmtTeachers = $conn->query("SELECT id, name FROM teachers WHERE active = 1 ORDER BY name ASC");
$teachers = $stmtTeachers->fetchAll(PDO::FETCH_ASSOC);

// Query principal
$query = "
    SELECT r.*, c.name as course_name
    FROM requests r
    LEFT JOIN courses c ON r.course_id = c.id
    WHERE r.request_type_id = 2
      AND r.status IN ('approved', 'concluded')
";
$params = [];

// Restrição de curso para coordenadores
if ($isCourseBound && !$isSysAdmin) {
    if (empty($userCourses)) {
        $query .= " AND 1=0";
    } else {
        $inList = implode(',', array_map('intval', $userCourses));
        $query .= " AND r.course_id IN ($inList)";
    }
} elseif ($isSysAdmin && $courseId) {
    $query .= " AND r.course_id = :course_id";
    $params[':course_id'] = $courseId;
}

if ($semester) {
    $query .= " AND r.protocol_code LIKE :semester";
    $params[':semester'] = str_replace('/', '-', $semester) . '%';
}
if ($search) {
    $query .= " AND (r.student_name LIKE :search OR r.protocol_code LIKE :proto OR r.student_id LIKE :sid)";
    $params[':search'] = "%$search%";
    $params[':proto']  = "%$search%";
    $params[':sid']    = "%$search%";
}
if ($teacherId) {
    // JSON_CONTAINS com valor numérico como JSON
    $query .= " AND JSON_CONTAINS(r.extra_fields->'$.selected_teachers', :tid)";
    $params[':tid'] = (string)(int)$teacherId;
}

$query .= " ORDER BY r.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Duas etapas: coleta IDs únicos de UCs e professores para lookup em batch
$allSubjectIds = [];
$allTeacherIds = [];
foreach ($requests as $r) {
    $ef = json_decode($r['extra_fields'] ?? '{}', true);
    foreach ($ef['selected_subjects'] ?? [] as $id) {
        $allSubjectIds[] = (int)$id;
    }
    foreach ($ef['selected_teachers'] ?? [] as $id) {
        $allTeacherIds[] = (int)$id;
    }
}
$allSubjectIds = array_unique($allSubjectIds);
$allTeacherIds = array_unique($allTeacherIds);

$subjectMap = [];
$teacherMap = [];

if (!empty($allSubjectIds)) {
    $inS = implode(',', $allSubjectIds);
    $rows = $conn->query("SELECT id, name FROM subjects WHERE id IN ($inS)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $s) {
        $subjectMap[$s['id']] = $s['name'];
    }
}
if (!empty($allTeacherIds)) {
    $inT = implode(',', $allTeacherIds);
    $rows = $conn->query("SELECT id, name, email FROM teachers WHERE id IN ($inT)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $t) {
        $teacherMap[$t['id']] = ['name' => $t['name'], 'email' => $t['email']];
    }
}

// Monta nome do professor filtrado para exibir no cabeçalho de impressão
$teacherFilterName = '';
if ($teacherId) {
    foreach ($teachers as $t) {
        if ($t['id'] == $teacherId) {
            $teacherFilterName = $t['name'];
            break;
        }
    }
}

$pageTitle = 'Relatório de Segunda Chamada';
require_once 'layout/header.php';
?>

<div class="print:hidden flex h-full">
    <?php require_once 'layout/sidebar.php'; ?>

    <main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#F2F4F8] p-6 w-full">
        <div class="container mx-auto">

            <!-- Cabeçalho -->
            <div class="flex flex-col md:flex-row justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 tracking-tight">Segunda Chamada</h1>
                    <p class="text-gray-500 mt-1">Requerimentos deferidos — UCs e professores envolvidos</p>
                </div>
                <?php if (!empty($requests)): ?>
                <button onclick="window.print()"
                    class="mt-4 md:mt-0 bg-[#1CBB9B] text-white px-6 py-2.5 rounded-xl font-bold hover:bg-[#169C80] transition-all flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2-2v4h10z"/>
                    </svg>
                    Imprimir
                </button>
                <?php endif; ?>
            </div>

            <!-- Filtros -->
            <div class="bg-white rounded-2xl shadow-sm p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
                    <div class="lg:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                            placeholder="Nome, matrícula ou protocolo..."
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Semestre</label>
                        <input type="text" name="semester" value="<?= htmlspecialchars($semester) ?>"
                            placeholder="AAAA/S" id="sem-sc"
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                    </div>
                    <?php if ($isSysAdmin): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Curso</label>
                        <select name="course_id" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                            <option value="">Todos os cursos</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $courseId == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Professor</label>
                        <select name="teacher_id" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                            <option value="">Todos os professores</option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= $teacherId == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <a href="segunda_chamada_report.php" class="text-gray-500 hover:text-gray-700 text-sm font-medium px-4 py-2 self-center">Limpar</a>
                        <button type="submit"
                            class="flex-1 bg-[#1CBB9B] text-white px-6 py-2 rounded-xl font-bold hover:bg-[#169C80] transition-all">Filtrar</button>
                    </div>
                </form>
            </div>

            <!-- Contador -->
            <p class="text-sm text-gray-500 mb-3"><?= count($requests) ?> requerimento(s) encontrado(s)
                <?= $semester ? "— Semestre: <strong>$semester</strong>" : '' ?>
                <?= $teacherFilterName ? "— Professor: <strong>" . htmlspecialchars($teacherFilterName) . "</strong>" : '' ?>
            </p>

            <!-- Tabela -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider w-[160px]">Protocolo</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Aluno</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider w-[100px]">Turma</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Unid. Curriculares</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Professores</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider w-[90px]">Data</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider w-[60px]">Ver</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($requests)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-10 text-center text-gray-500">
                                        Nenhum requerimento de Segunda Chamada deferido encontrado com os filtros aplicados.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($requests as $r):
                                    $ef = json_decode($r['extra_fields'] ?? '{}', true);
                                    $selectedSubjects = $ef['selected_subjects'] ?? [];
                                    $selectedTeachers = $ef['selected_teachers'] ?? [];
                                    $ucOther          = trim($ef['uc_other_name']      ?? '');
                                    $teacherOther     = trim($ef['teacher_other_name'] ?? '');
                                    $alsoJustify      = !empty($ef['also_justify_absence']);

                                    $ucNames = [];
                                    foreach ($selectedSubjects as $sid) {
                                        if (isset($subjectMap[(int)$sid])) {
                                            $ucNames[] = $subjectMap[(int)$sid];
                                        }
                                    }
                                    if ($ucOther) $ucNames[] = $ucOther . ' *';

                                    $teacherNames = [];
                                    foreach ($selectedTeachers as $tid) {
                                        if (isset($teacherMap[(int)$tid])) {
                                            $teacherNames[] = $teacherMap[(int)$tid]['name'];
                                        }
                                    }
                                    if ($teacherOther) $teacherNames[] = $teacherOther . ' *';
                                ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 text-xs font-mono font-bold text-gray-600">
                                        <?= htmlspecialchars($r['protocol_code']) ?>
                                        <?php if ($alsoJustify): ?>
                                            <span class="ml-1 inline-block px-1.5 py-0.5 text-[10px] font-bold bg-blue-50 text-blue-600 rounded">+ Falta</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?= htmlspecialchars($r['student_name']) ?>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($r['student_id']) ?> — <?= htmlspecialchars($r['course_name'] ?? '') ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500"><?= htmlspecialchars($r['class_info'] ?? '-') ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-700">
                                        <?php if (empty($ucNames)): ?>
                                            <span class="text-gray-400">—</span>
                                        <?php else: ?>
                                            <?= implode('<br>', array_map('htmlspecialchars', $ucNames)) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700">
                                        <?php if (empty($teacherNames)): ?>
                                            <span class="text-gray-400">—</span>
                                        <?php else: ?>
                                            <?= implode('<br>', array_map('htmlspecialchars', $teacherNames)) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500"><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="request_details.php?id=<?= $r['id'] ?>&source=segunda_chamada_report"
                                           class="text-[#1CBB9B] hover:text-[#169C80]">
                                            <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if (!empty($requests)): ?>
            <p class="text-xs text-gray-400 mt-2">* UC ou professor informado manualmente pelo aluno (não consta no cadastro)</p>
            <?php endif; ?>

        </div>
    </main>
</div>

<!-- Versão para Impressão -->
<?php if (!empty($requests)): ?>
<div class="hidden print:block p-8 bg-white">
    <div class="text-center mb-6 border-b pb-4">
        <img src="../assets/img/logob.png" alt="IFSC" class="h-12 mx-auto mb-2">
        <h1 class="text-xl font-bold text-gray-900">Segunda Chamada — Requerimentos Deferidos</h1>
        <p class="text-gray-600 text-sm">
            <?= $semester ? "Semestre: $semester" : 'Todos os semestres' ?>
            <?= $teacherFilterName ? " | Professor: " . htmlspecialchars($teacherFilterName) : '' ?>
            <?= ($isSysAdmin && $courseId) ? " | Curso: " . htmlspecialchars(array_column($courses, 'name', 'id')[$courseId] ?? '') : '' ?>
        </p>
        <p class="text-gray-400 text-xs mt-1">Gerado em: <?= date('d/m/Y H:i') ?></p>
    </div>

    <table class="w-full border-collapse border border-gray-300 text-xs">
        <thead>
            <tr class="bg-gray-50">
                <th class="border border-gray-300 px-3 py-1 text-left font-bold w-[15%]">Protocolo</th>
                <th class="border border-gray-300 px-3 py-1 text-left font-bold w-[18%]">Aluno / Matrícula</th>
                <th class="border border-gray-300 px-3 py-1 text-left font-bold w-[8%]">Turma</th>
                <th class="border border-gray-300 px-3 py-1 text-left font-bold w-[27%]">Unidades Curriculares</th>
                <th class="border border-gray-300 px-3 py-1 text-left font-bold w-[22%]">Professores</th>
                <th class="border border-gray-300 px-3 py-1 text-left font-bold w-[10%]">Data</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($requests as $r):
                $ef = json_decode($r['extra_fields'] ?? '{}', true);
                $selectedSubjects = $ef['selected_subjects'] ?? [];
                $selectedTeachers = $ef['selected_teachers'] ?? [];
                $ucOther          = trim($ef['uc_other_name']      ?? '');
                $teacherOther     = trim($ef['teacher_other_name'] ?? '');
                $alsoJustify      = !empty($ef['also_justify_absence']);

                $ucNames = [];
                foreach ($selectedSubjects as $sid) {
                    if (isset($subjectMap[(int)$sid])) $ucNames[] = $subjectMap[(int)$sid];
                }
                if ($ucOther) $ucNames[] = $ucOther . ' *';

                $teacherNames = [];
                foreach ($selectedTeachers as $tid) {
                    if (isset($teacherMap[(int)$tid])) $teacherNames[] = $teacherMap[(int)$tid]['name'];
                }
                if ($teacherOther) $teacherNames[] = $teacherOther . ' *';
            ?>
            <tr>
                <td class="border border-gray-300 px-3 py-1 font-mono">
                    <?= htmlspecialchars($r['protocol_code']) ?>
                    <?= $alsoJustify ? ' (+Falta)' : '' ?>
                </td>
                <td class="border border-gray-300 px-3 py-1">
                    <?= htmlspecialchars($r['student_name']) ?><br>
                    <span class="text-gray-500"><?= htmlspecialchars($r['student_id']) ?></span>
                </td>
                <td class="border border-gray-300 px-3 py-1"><?= htmlspecialchars($r['class_info'] ?? '-') ?></td>
                <td class="border border-gray-300 px-3 py-1"><?= implode(' / ', array_map('htmlspecialchars', $ucNames)) ?: '—' ?></td>
                <td class="border border-gray-300 px-3 py-1"><?= implode(' / ', array_map('htmlspecialchars', $teacherNames)) ?: '—' ?></td>
                <td class="border border-gray-300 px-3 py-1"><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (array_filter($requests, fn($r) => !empty(json_decode($r['extra_fields'] ?? '{}', true)['uc_other_name']) || !empty(json_decode($r['extra_fields'] ?? '{}', true)['teacher_other_name']))): ?>
    <p class="text-xs text-gray-500 mt-3">* UC ou professor informado manualmente pelo aluno (não consta no cadastro)</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
    const semSc = document.getElementById('sem-sc');
    if (semSc) {
        semSc.addEventListener('input', function(e) {
            var x = e.target.value.replace(/\D/g, '').match(/(\d{0,4})(\d{0,1})/);
            e.target.value = !x[2] ? x[1] : x[1] + '/' + x[2];
        });
    }
</script>
</body>
</html>
