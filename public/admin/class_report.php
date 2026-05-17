<?php
/**
 * Relatório por turma — Conselho de Classe.
 * Exibe todos os requerimentos de uma turma agrupados por aluno, com versão impressa.
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

$courseId  = $_GET['course_id']  ?? '';
$classInfo = trim($_GET['class_info'] ?? '');
$semester  = $_GET['semester']   ?? Helpers::getCurrentSemester();

// Cursos disponíveis
if ($isSysAdmin) {
    $stmtCourses = $conn->query("SELECT id, name FROM courses WHERE active = 1 ORDER BY name ASC");
} else {
    $inList = empty($userCourses) ? '0' : implode(',', array_map('intval', $userCourses));
    $stmtCourses = $conn->query("SELECT id, name FROM courses WHERE id IN ($inList) ORDER BY name ASC");
}
$courses = $stmtCourses->fetchAll(PDO::FETCH_ASSOC);

$byStudent = [];
$courseName = '';
$filtered   = false;

if ($courseId && $classInfo) {
    $filtered = true;

    // Valida acesso ao curso
    if ($isCourseBound && !$isSysAdmin && !in_array((int)$courseId, array_map('intval', $userCourses))) {
        header('Location: class_report.php');
        exit;
    }

    $courseName = array_column($courses, 'name', 'id')[$courseId] ?? '';

    $q = "
        SELECT r.*, rt.name as type_name
        FROM requests r
        LEFT JOIN request_types rt ON r.request_type_id = rt.id
        WHERE r.course_id = :cid
          AND r.class_info LIKE :ci
    ";
    $qp = [':cid' => $courseId, ':ci' => "%$classInfo%"];
    if ($semester) {
        $q .= " AND r.protocol_code LIKE :sem";
        $qp[':sem'] = str_replace('/', '-', $semester) . '%';
    }
    $q .= " ORDER BY r.student_name ASC, r.created_at ASC";

    $stmtR = $conn->prepare($q);
    $stmtR->execute($qp);
    $rows = $stmtR->fetchAll(PDO::FETCH_ASSOC);

    // Agrupar por aluno
    foreach ($rows as $r) {
        $key = $r['student_id'] . '|' . $r['student_name'];
        if (!isset($byStudent[$key])) {
            $byStudent[$key] = [
                'student_name' => $r['student_name'],
                'student_id'   => $r['student_id'],
                'class_info'   => $r['class_info'],
                'requests'     => [],
            ];
        }
        $byStudent[$key]['requests'][] = $r;
    }
}

$pageTitle = 'Relatório por Turma';
require_once 'layout/header.php';
?>

<div class="print:hidden flex h-full">
    <?php require_once 'layout/sidebar.php'; ?>

    <main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#F2F4F8] p-6 w-full">
        <div class="container mx-auto">

            <!-- Cabeçalho -->
            <div class="flex flex-col md:flex-row justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 tracking-tight">Relatório por Turma</h1>
                    <p class="text-gray-500 mt-1">Conselho de Classe — requerimentos agrupados por aluno</p>
                </div>
                <?php if ($filtered && !empty($byStudent)): ?>
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
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Curso <span class="text-red-500">*</span></label>
                        <select name="course_id" required
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                            <option value="">Selecione o curso</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $courseId == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Turma <span class="text-red-500">*</span></label>
                        <input type="text" name="class_info" value="<?= htmlspecialchars($classInfo) ?>"
                            placeholder="Ex: 2026.1 ou 1º Módulo"
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none"
                            required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Semestre</label>
                        <input type="text" name="semester" value="<?= htmlspecialchars($semester) ?>"
                            placeholder="AAAA/S" id="sem-cr"
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                    </div>
                    <div class="flex gap-2">
                        <button type="submit"
                            class="flex-1 bg-[#1CBB9B] text-white px-6 py-2 rounded-xl font-bold hover:bg-[#169C80] transition-all">Gerar</button>
                    </div>
                </form>
            </div>

            <?php if ($filtered && empty($byStudent)): ?>
                <div class="bg-white rounded-2xl shadow-sm px-6 py-10 text-center text-gray-500">
                    Nenhum requerimento encontrado para a turma "<?= htmlspecialchars($classInfo) ?>"<?= $semester ? " no semestre $semester" : '' ?>.
                </div>
            <?php elseif ($filtered): ?>

            <!-- Resultado agrupado por aluno -->
            <p class="text-sm text-gray-500 mb-3">
                <?= count($byStudent) ?> aluno(s) com requerimento(s) —
                <strong><?= htmlspecialchars($courseName) ?></strong> | Turma: <strong><?= htmlspecialchars($classInfo) ?></strong>
                <?= $semester ? "| Semestre: <strong>$semester</strong>" : '' ?>
            </p>

            <div class="space-y-4">
                <?php foreach ($byStudent as $studentData): ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="bg-gray-50 border-b border-gray-100 px-6 py-3 flex justify-between items-center">
                            <div>
                                <span class="font-semibold text-gray-800"><?= htmlspecialchars($studentData['student_name']) ?></span>
                                <span class="text-sm text-gray-400 ml-3">Matrícula: <?= htmlspecialchars($studentData['student_id']) ?></span>
                            </div>
                            <span class="text-xs bg-[#1CBB9B]/10 text-[#1CBB9B] px-3 py-1 rounded-full font-semibold">
                                <?= count($studentData['requests']) ?> requerimento(s)
                            </span>
                        </div>
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-50">
                                    <th class="px-6 py-2 text-left text-xs font-bold text-gray-400 uppercase tracking-wider w-[140px]">Protocolo</th>
                                    <th class="px-6 py-2 text-left text-xs font-bold text-gray-400 uppercase tracking-wider">Tipo</th>
                                    <th class="px-6 py-2 text-left text-xs font-bold text-gray-400 uppercase tracking-wider w-[110px]">Status</th>
                                    <th class="px-6 py-2 text-left text-xs font-bold text-gray-400 uppercase tracking-wider w-[90px]">Data</th>
                                    <th class="px-6 py-2 text-right text-xs font-bold text-gray-400 uppercase tracking-wider w-[60px]">Ver</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php foreach ($studentData['requests'] as $r): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-2.5 font-mono text-xs text-gray-600"><?= htmlspecialchars($r['protocol_code']) ?></td>
                                        <td class="px-6 py-2.5 text-gray-700"><?= htmlspecialchars($r['type_name'] ?? '-') ?></td>
                                        <td class="px-6 py-2.5">
                                            <span class="px-2 py-0.5 rounded text-xs font-bold
                                                <?= $r['status'] === 'pending'   ? 'bg-yellow-50 text-yellow-700' : '' ?>
                                                <?= $r['status'] === 'approved'  ? 'bg-green-50 text-green-700'   : '' ?>
                                                <?= $r['status'] === 'concluded' ? 'bg-teal-50 text-teal-700'     : '' ?>
                                                <?= $r['status'] === 'rejected'  ? 'bg-red-50 text-red-700'       : '' ?>
                                            "><?= Helpers::translateStatus($r['status']) ?></span>
                                        </td>
                                        <td class="px-6 py-2.5 text-gray-400"><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
                                        <td class="px-6 py-2.5 text-right">
                                            <a href="request_details.php?id=<?= $r['id'] ?>&source=class_report"
                                               class="text-[#1CBB9B] hover:text-[#169C80]">
                                                <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div>
    </main>
</div>

<!-- Versão para Impressão (Conselho de Classe) -->
<?php if ($filtered && !empty($byStudent)): ?>
<div class="hidden print:block p-8 bg-white">
    <div class="text-center mb-6 border-b pb-4">
        <img src="../assets/img/logob.png" alt="IFSC" class="h-12 mx-auto mb-2">
        <h1 class="text-xl font-bold text-gray-900">Conselho de Classe — Requerimentos por Aluno</h1>
        <p class="text-gray-600 text-sm">
            <?= htmlspecialchars($courseName) ?> | Turma: <?= htmlspecialchars($classInfo) ?>
            <?= $semester ? " | Semestre: $semester" : '' ?>
        </p>
        <p class="text-gray-400 text-xs mt-1">Gerado em: <?= date('d/m/Y H:i') ?></p>
    </div>

    <?php foreach ($byStudent as $studentData): ?>
        <div class="mb-6">
            <div class="bg-gray-100 px-4 py-2 mb-1 flex justify-between">
                <span class="font-bold text-sm"><?= htmlspecialchars($studentData['student_name']) ?></span>
                <span class="text-xs text-gray-500">Matrícula: <?= htmlspecialchars($studentData['student_id']) ?></span>
            </div>
            <table class="w-full border-collapse border border-gray-300 text-xs">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="border border-gray-300 px-3 py-1 text-left font-bold w-[15%]">Protocolo</th>
                        <th class="border border-gray-300 px-3 py-1 text-left font-bold w-[50%]">Tipo de Requerimento</th>
                        <th class="border border-gray-300 px-3 py-1 text-left font-bold w-[20%]">Status</th>
                        <th class="border border-gray-300 px-3 py-1 text-left font-bold w-[15%]">Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($studentData['requests'] as $r): ?>
                        <tr>
                            <td class="border border-gray-300 px-3 py-1 font-mono"><?= htmlspecialchars($r['protocol_code']) ?></td>
                            <td class="border border-gray-300 px-3 py-1"><?= htmlspecialchars($r['type_name'] ?? '-') ?></td>
                            <td class="border border-gray-300 px-3 py-1"><?= Helpers::translateStatus($r['status']) ?></td>
                            <td class="border border-gray-300 px-3 py-1"><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
    const semCr = document.getElementById('sem-cr');
    if (semCr) {
        semCr.addEventListener('input', function(e) {
            var x = e.target.value.replace(/\D/g, '').match(/(\d{0,4})(\d{0,1})/);
            e.target.value = !x[2] ? x[1] : x[1] + '/' + x[2];
        });
    }
</script>
</body>
</html>
