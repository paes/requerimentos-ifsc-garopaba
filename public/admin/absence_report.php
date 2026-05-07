<?php
/**
 * Relatorio gerencial focado nas justificativas de faltas dos alunos.
 *
 * @author Prof. Eduardo Gomes
 */
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../src/Auth.php';
require_once '../../src/Helpers.php';

Auth::check();
$user = Auth::user();

$db = new Database();
$conn = $db->getConnection();

// Filtros
$name = $_GET['name'] ?? '';
$protocol = $_GET['protocol'] ?? '';
$semester = $_GET['semester'] ?? Helpers::getCurrentSemester();
$courseId = $_GET['course_id'] ?? '';

// Constroi a Query
$query = "
    SELECT r.*, c.name as course_name 
    FROM requests r 
    LEFT JOIN courses c ON r.course_id = c.id
    WHERE r.request_type_id = 1 AND r.status IN ('approved', 'concluded')
";
$params = [];

if ($name) {
    $query .= " AND r.student_name LIKE :name";
    $params[':name'] = '%' . $name . '%';
}

if ($protocol) {
    $query .= " AND r.protocol_code LIKE :protocol";
    $params[':protocol'] = '%' . $protocol . '%';
}

if ($semester) {
    $semesterPrefix = str_replace('/', '-', $semester);
    $query .= " AND r.protocol_code LIKE :semester_prefix";
    $params[':semester_prefix'] = $semesterPrefix . '%';
}

if ($courseId) {
    $query .= " AND r.course_id = :course_id";
    $params[':course_id'] = $courseId;
}

$query .= " ORDER BY r.created_at ASC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Busca Cursos para o Filtro
$stmtCourses = $conn->query("SELECT id, name FROM courses ORDER BY name ASC");
$courses = $stmtCourses->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Relatório de Justificativas de Faltas';
require_once 'layout/header.php';
?>

<!-- Sidebar (Hidden on Print) -->
<div class="print:hidden flex h-full">
    <?php require_once 'layout/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#F2F4F8] p-6 w-full">
        <div class="container mx-auto">

            <!-- Header & Filters (Hidden on Print) -->
            <div class="bg-white rounded-2xl shadow-sm p-6 mb-6 print:hidden">
                <div class="flex flex-col md:flex-row justify-between items-center mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800 tracking-tight">Relatório de Justificativas de
                            Faltas</h2>
                        <p class="text-gray-500 mt-1">Filtragem e impressão de justificativas</p>
                    </div>
                    <button onclick="window.print()"
                        class="bg-[#1CBB9B] text-white px-6 py-2.5 rounded-xl font-bold hover:bg-[#169C80] transition-all shadow-sm hover:shadow-md flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2-2v4h10z">
                            </path>
                        </svg>
                        Imprimir Relatório
                    </button>
                </div>

                <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nome do Aluno</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($name) ?>"
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-brand-DEFAULT focus:border-transparent outline-none transition-all"
                            placeholder="Buscar por nome...">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Protocolo</label>
                        <input type="text" name="protocol" value="<?= htmlspecialchars($protocol) ?>"
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-brand-DEFAULT focus:border-transparent outline-none transition-all"
                            placeholder="Ex: 2024-1...">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Semestre</label>
                        <input type="text" name="semester" id="semester" value="<?= htmlspecialchars($semester) ?>"
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-brand-DEFAULT focus:border-transparent outline-none transition-all"
                            placeholder="AAAA/S">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Curso</label>
                        <select name="course_id"
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-brand-DEFAULT focus:border-transparent outline-none transition-all">
                            <option value="">Todos os Cursos</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $courseId == $c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-2 lg:col-span-4 flex justify-end">
                        <button type="submit"
                            class="bg-[#1CBB9B] text-white px-6 py-2 rounded-xl font-bold hover:bg-[#169C80] transition-all">Filtrar</button>
                    </div>
                </form>
            </div>

            <!-- Table (Visible on Screen) -->
            <div class="bg-white rounded-2xl shadow-sm overflow-hidden print:hidden">
                <div class="overflow-x-auto">
                    <table class="w-full whitespace-nowrap">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100">
                                <th
                                    class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                    Aluno</th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                    Turma</th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                    Descrição</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($requests)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-gray-500">Nenhuma justificativa
                                        encontrada.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($requests as $r): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($r['student_name']) ?>
                                            <div class="text-xs text-gray-500 font-normal mt-0.5">
                                                <?= htmlspecialchars($r['course_name'] ?? '') ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            <?= htmlspecialchars($r['class_info'] ?? '-') ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate"
                                            title="<?= htmlspecialchars($r['description']) ?>">
                                            <?= htmlspecialchars($r['description']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Print Only View -->
<div class="hidden print:block p-8 bg-white">
    <div class="text-center mb-8 border-b pb-4">
        <img src="../assets/img/logob.png" alt="IFSC"
            class="h-12 mx-auto mb-2 brightness-0 invert filter grayscale invert-0">
        <h1 class="text-2xl font-bold text-gray-900">Relatório de Justificativas de Faltas</h1>
        <p class="text-gray-600">Semestre: <?= htmlspecialchars($semester) ?></p>
        <p class="text-gray-500 text-sm mt-1">Gerado em: <?= date('d/m/Y H:i') ?></p>
    </div>

    <table class="w-full border-collapse border border-gray-300 table-fixed">
        <thead>
            <tr class="bg-gray-100">
                <th class="border border-gray-300 px-4 py-2 text-left text-sm font-bold text-gray-700 w-[25%]">Aluno
                </th>
                <th class="border border-gray-300 px-4 py-2 text-left text-sm font-bold text-gray-700 w-[15%]">Turma
                </th>
                <th class="border border-gray-300 px-4 py-2 text-left text-sm font-bold text-gray-700 w-[60%]">Descrição
                </th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($requests as $r): ?>
                <tr>
                    <td class="border border-gray-300 px-4 py-2 text-sm">
                        <?= htmlspecialchars($r['student_name']) ?>
                        <div class="text-[10px] text-gray-500 mt-1"><?= htmlspecialchars($r['course_name'] ?? '') ?></div>
                    </td>
                    <td class="border border-gray-300 px-4 py-2 text-sm"><?= htmlspecialchars($r['class_info'] ?? '-') ?>
                    </td>
                    <td class="border border-gray-300 px-4 py-2 text-sm text-justify">
                        <?= nl2br(htmlspecialchars($r['description'])) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
    // Mascara simples para entrada de semestre
    document.getElementById('semester').addEventListener('input', function (e) {
        var x = e.target.value.replace(/\D/g, '').match(/(\d{0,4})(\d{0,1})/);
        e.target.value = !x[2] ? x[1] : x[1] + '/' + x[2];
    });
</script>

<?php
// Nao incluimos footer.php porque ele pode fechar tags que queremos controlar ou adicionar elementos visiveis
// Mas precisamos fechar as tags body/html abertas no header.php
?>
</body>

</html>