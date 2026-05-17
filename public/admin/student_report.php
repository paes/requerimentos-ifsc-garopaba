<?php
/**
 * Relatório de histórico de requerimentos por aluno.
 * Busca por nome ou matrícula; coordenadores veem apenas alunos de seus cursos.
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

$search    = trim($_GET['search']    ?? '');
$studentId = $_GET['student_id']    ?? '';   // matrícula selecionada
$semester  = $_GET['semester']      ?? '';

$students  = [];
$history   = [];
$selStudent = null;

if ($search) {
    // Busca alunos distintos
    $q = "
        SELECT DISTINCT r.student_id, r.student_name, r.course_id, c.name as course_name
        FROM requests r
        LEFT JOIN courses c ON r.course_id = c.id
        WHERE (r.student_name LIKE :name OR r.student_id LIKE :sid)
    ";
    $qp = [':name' => "%$search%", ':sid' => "%$search%"];

    if ($isCourseBound && !$isSysAdmin) {
        if (empty($userCourses)) {
            $q .= " AND 1=0";
        } else {
            $inList = implode(',', array_map('intval', $userCourses));
            $q .= " AND r.course_id IN ($inList)";
        }
    }
    $q .= " ORDER BY r.student_name ASC";
    $stmtS = $conn->prepare($q);
    $stmtS->execute($qp);
    $students = $stmtS->fetchAll(PDO::FETCH_ASSOC);
}

if ($studentId) {
    // Valida acesso ao aluno
    $checkQ = "SELECT student_name, student_id, course_id FROM requests WHERE student_id = :sid LIMIT 1";
    $checkStmt = $conn->prepare($checkQ);
    $checkStmt->execute([':sid' => $studentId]);
    $selStudent = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($selStudent && $isCourseBound && !$isSysAdmin) {
        if (!in_array((int)$selStudent['course_id'], array_map('intval', $userCourses))) {
            $selStudent = null;
        }
    }

    if ($selStudent) {
        $hq = "
            SELECT r.*, c.name as course_name, rt.name as type_name
            FROM requests r
            LEFT JOIN courses c ON r.course_id = c.id
            LEFT JOIN request_types rt ON r.request_type_id = rt.id
            WHERE r.student_id = :sid
        ";
        $hp = [':sid' => $studentId];
        if ($semester) {
            $hq .= " AND r.protocol_code LIKE :sem";
            $hp[':sem'] = str_replace('/', '-', $semester) . '%';
        }
        $hq .= " ORDER BY r.created_at DESC";
        $histStmt = $conn->prepare($hq);
        $histStmt->execute($hp);
        $history = $histStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$pageTitle = 'Histórico por Aluno';
require_once 'layout/header.php';
?>

<?php require_once 'layout/sidebar.php'; ?>

<main class="flex-1 overflow-y-auto p-8 bg-[#F2F4F8]">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800 tracking-tight">Histórico por Aluno</h1>
        <p class="text-gray-500 mt-1">Busque um aluno e veja todos os requerimentos que ele protocolou</p>
    </div>

    <!-- Busca -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <form method="GET" class="flex flex-col md:flex-row gap-4 items-end">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome ou Matrícula do Aluno</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                    placeholder="Digite o nome ou número de matrícula..."
                    class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
            </div>
            <button type="submit"
                class="bg-[#1CBB9B] text-white px-6 py-2 rounded-xl font-bold hover:bg-[#169C80] transition-all">
                Buscar
            </button>
        </form>
    </div>

    <?php if ($search && empty($students) && !$studentId): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-6 py-10 text-center text-gray-500">
            Nenhum aluno encontrado para "<?= htmlspecialchars($search) ?>".
        </div>
    <?php endif; ?>

    <?php if (!empty($students) && !$studentId): ?>
    <!-- Lista de alunos encontrados -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-100">
            <p class="text-sm text-gray-500"><?= count($students) ?> aluno(s) encontrado(s) — clique para ver o histórico completo</p>
        </div>
        <ul class="divide-y divide-gray-100">
            <?php foreach ($students as $s): ?>
                <li>
                    <a href="?search=<?= urlencode($search) ?>&student_id=<?= urlencode($s['student_id']) ?>"
                       class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition-colors group">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 group-hover:text-[#1CBB9B]"><?= htmlspecialchars($s['student_name']) ?></p>
                            <p class="text-xs text-gray-400">Matrícula: <?= htmlspecialchars($s['student_id']) ?> — <?= htmlspecialchars($s['course_name'] ?? '') ?></p>
                        </div>
                        <svg class="w-4 h-4 text-gray-400 group-hover:text-[#1CBB9B]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if ($selStudent): ?>
    <!-- Histórico do aluno selecionado -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-4">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4">
            <div>
                <h2 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($selStudent['student_name']) ?></h2>
                <p class="text-sm text-gray-500">Matrícula: <?= htmlspecialchars($selStudent['student_id']) ?></p>
            </div>
            <div class="flex items-center gap-3 mt-3 md:mt-0">
                <form method="GET" class="flex items-center gap-2">
                    <input type="hidden" name="search"     value="<?= htmlspecialchars($search) ?>">
                    <input type="hidden" name="student_id" value="<?= htmlspecialchars($studentId) ?>">
                    <input type="text" name="semester" value="<?= htmlspecialchars($semester) ?>"
                        placeholder="Filtrar semestre (AAAA/S)" id="sem-sr"
                        class="bg-gray-50 border border-gray-200 rounded-xl px-4 py-1.5 text-sm focus:ring-2 focus:ring-[#1CBB9B] outline-none w-44">
                    <button type="submit" class="bg-[#1CBB9B] text-white px-4 py-1.5 rounded-xl text-sm font-bold hover:bg-[#169C80] transition-all">Filtrar</button>
                </form>
                <a href="?search=<?= urlencode($search) ?>" class="text-sm text-gray-400 hover:text-gray-600">← Voltar</a>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Protocolo</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Tipo</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Data</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Ver</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($history)): ?>
                        <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">Nenhum requerimento encontrado<?= $semester ? " para o semestre $semester" : '' ?>.</td></tr>
                    <?php else: ?>
                        <?php foreach ($history as $r): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 text-xs font-mono font-bold text-gray-600"><?= htmlspecialchars($r['protocol_code']) ?></td>
                                <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars($r['type_name'] ?? '-') ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 rounded-lg text-xs font-bold
                                        <?= $r['status'] === 'pending'   ? 'bg-yellow-50 text-yellow-700' : '' ?>
                                        <?= $r['status'] === 'approved'  ? 'bg-green-50 text-green-700'   : '' ?>
                                        <?= $r['status'] === 'concluded' ? 'bg-teal-50 text-teal-700'     : '' ?>
                                        <?= $r['status'] === 'rejected'  ? 'bg-red-50 text-red-700'       : '' ?>
                                    "><?= Helpers::translateStatus($r['status']) ?></span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500"><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
                                <td class="px-6 py-4 text-right">
                                    <a href="request_details.php?id=<?= $r['id'] ?>&source=student_report"
                                       class="text-[#1CBB9B] hover:text-[#169C80]">
                                        <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</main>

<script>
    const semInput = document.getElementById('sem-sr');
    if (semInput) {
        semInput.addEventListener('input', function(e) {
            var x = e.target.value.replace(/\D/g, '').match(/(\d{0,4})(\d{0,1})/);
            e.target.value = !x[2] ? x[1] : x[1] + '/' + x[2];
        });
    }
</script>

<?php require_once 'layout/footer.php'; ?>
