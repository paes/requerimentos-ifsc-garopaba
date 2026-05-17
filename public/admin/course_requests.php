<?php
/**
 * Lista todos os requerimentos do(s) curso(s) do coordenador, com filtros avançados.
 * Acessível por coordenadores (restrito aos seus cursos) e admins (todos os cursos).
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

// Filtros
$search   = $_GET['search']    ?? '';
$semester = $_GET['semester']  ?? Helpers::getCurrentSemester();
$statusF  = $_GET['status']    ?? '';
$typeId   = $_GET['type_id']   ?? '';
$courseId = $_GET['course_id'] ?? '';
$classInfo= $_GET['class_info']?? '';

// Cursos disponíveis para o usuário
$userCourses = $user['user_courses'] ?? [];

// Query base
$query = "
    SELECT r.*, c.name as course_name, rt.name as type_name
    FROM requests r
    LEFT JOIN courses c ON r.course_id = c.id
    LEFT JOIN request_types rt ON r.request_type_id = rt.id
    WHERE 1=1
";
$params = [];

// Restrição de curso
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
if ($statusF) {
    $query .= " AND r.status = :status";
    $params[':status'] = $statusF;
}
if ($typeId) {
    $query .= " AND r.request_type_id = :type_id";
    $params[':type_id'] = $typeId;
}
if ($classInfo) {
    $query .= " AND r.class_info LIKE :class_info";
    $params[':class_info'] = "%$classInfo%";
}

$query .= " ORDER BY r.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tipos para o filtro
$stmtTypes = $conn->query("SELECT id, name FROM request_types WHERE active = 1 ORDER BY name ASC");
$reqTypes  = $stmtTypes->fetchAll(PDO::FETCH_ASSOC);

// Cursos para o filtro (admin vê todos; coordenador vê os seus)
if ($isSysAdmin) {
    $stmtCourses = $conn->query("SELECT id, name FROM courses WHERE active = 1 ORDER BY name ASC");
} else {
    $inList = empty($userCourses) ? '0' : implode(',', array_map('intval', $userCourses));
    $stmtCourses = $conn->query("SELECT id, name FROM courses WHERE id IN ($inList) ORDER BY name ASC");
}
$courses = $stmtCourses->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Requerimentos do Curso';
require_once 'layout/header.php';
?>

<?php require_once 'layout/sidebar.php'; ?>

<main class="flex-1 overflow-y-auto p-8 bg-[#F2F4F8]">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800 tracking-tight">Requerimentos do Curso</h1>
        <p class="text-gray-500 mt-1">Todos os requerimentos — todos os status — com filtros avançados</p>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 items-end">
            <div class="lg:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                    placeholder="Nome, matrícula ou protocolo..."
                    class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Semestre</label>
                <input type="text" name="semester" value="<?= htmlspecialchars($semester) ?>"
                    placeholder="AAAA/S" id="semester-cr"
                    class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                    <option value="">Todos</option>
                    <option value="pending"   <?= $statusF === 'pending'   ? 'selected' : '' ?>>Pendente</option>
                    <option value="approved"  <?= $statusF === 'approved'  ? 'selected' : '' ?>>Deferido</option>
                    <option value="rejected"  <?= $statusF === 'rejected'  ? 'selected' : '' ?>>Indeferido</option>
                    <option value="concluded" <?= $statusF === 'concluded' ? 'selected' : '' ?>>Concluído</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                <select name="type_id" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                    <option value="">Todos os tipos</option>
                    <?php foreach ($reqTypes as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $typeId == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
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
                <label class="block text-sm font-medium text-gray-700 mb-1">Turma</label>
                <input type="text" name="class_info" value="<?= htmlspecialchars($classInfo) ?>"
                    placeholder="Ex: 2026.1"
                    class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] outline-none">
            </div>
            <div class="lg:col-span-<?= $isSysAdmin ? '6' : '5' ?> flex justify-end gap-3">
                <a href="course_requests.php" class="text-gray-500 hover:text-gray-700 text-sm font-medium px-4 py-2">Limpar</a>
                <button type="submit" class="bg-[#1CBB9B] text-white px-6 py-2 rounded-xl font-bold hover:bg-[#169C80] transition-all">Filtrar</button>
            </div>
        </form>
    </div>

    <!-- Contador -->
    <p class="text-sm text-gray-500 mb-3"><?= count($requests) ?> requerimento(s) encontrado(s)</p>

    <!-- Tabela -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Protocolo</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Aluno</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Turma</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Tipo</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Data</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Ver</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="7" class="px-6 py-10 text-center text-gray-500">Nenhum requerimento encontrado com os filtros aplicados.</td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $r): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 text-xs font-mono font-bold text-gray-600"><?= htmlspecialchars($r['protocol_code']) ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <?= htmlspecialchars($r['student_name']) ?>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($r['student_id']) ?> — <?= htmlspecialchars($r['course_name'] ?? '') ?></div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500"><?= htmlspecialchars($r['class_info'] ?? '-') ?></td>
                                <td class="px-6 py-4 text-sm text-gray-500 max-w-[160px] truncate" title="<?= htmlspecialchars($r['type_name'] ?? '') ?>"><?= htmlspecialchars($r['type_name'] ?? '-') ?></td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="px-2 py-1 rounded-lg text-xs font-bold
                                        <?= $r['status'] === 'pending'   ? 'bg-yellow-50 text-yellow-700'    : '' ?>
                                        <?= $r['status'] === 'approved'  ? 'bg-green-50 text-green-700'      : '' ?>
                                        <?= $r['status'] === 'concluded' ? 'bg-teal-50 text-teal-700'        : '' ?>
                                        <?= $r['status'] === 'rejected'  ? 'bg-red-50 text-red-700'          : '' ?>
                                    "><?= Helpers::translateStatus($r['status']) ?></span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500"><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
                                <td class="px-6 py-4 text-right">
                                    <a href="request_details.php?id=<?= $r['id'] ?>&source=course_requests"
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
</main>

<script>
    document.getElementById('semester-cr').addEventListener('input', function(e) {
        var x = e.target.value.replace(/\D/g, '').match(/(\d{0,4})(\d{0,1})/);
        e.target.value = !x[2] ? x[1] : x[1] + '/' + x[2];
    });
</script>

<?php require_once 'layout/footer.php'; ?>
