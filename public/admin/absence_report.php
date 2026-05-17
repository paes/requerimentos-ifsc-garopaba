<?php
/**
 * Relatório unificado de justificativas de faltas.
 * Inclui tipo 1 (Justificativa de Falta) e tipo 2 (Segunda Chamada com "justificar falta tb").
 * Exibe datas com dia da semana e quadro resumo por dia ao final.
 *
 * @author Prof. Eduardo Gomes / Prof. Thiago Paes (revisão)
 */
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../src/Auth.php';
require_once '../../src/Helpers.php';

Auth::check();
$user = Auth::user();

$db   = new Database();
$conn = $db->getConnection();

$name     = $_GET['name']      ?? '';
$protocol = $_GET['protocol']  ?? '';
$semester = $_GET['semester']  ?? Helpers::getCurrentSemester();
$courseId = $_GET['course_id'] ?? '';

$query = "
    SELECT r.*, c.name as course_name,
           (r.request_type_id = 2) AS is_segunda_chamada
    FROM requests r
    LEFT JOIN courses c ON r.course_id = c.id
    WHERE (
        r.request_type_id = 1
        OR (r.request_type_id = 2 AND JSON_EXTRACT(r.extra_fields, '$.also_justify_absence') = true)
    )
    AND r.status IN ('approved', 'concluded')
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
$query .= " ORDER BY r.student_name ASC, r.start_date ASC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtCourses = $conn->query("SELECT id, name FROM courses ORDER BY name ASC");
$courses     = $stmtCourses->fetchAll(PDO::FETCH_ASSOC);

// Mapeamento de dias da semana em português
$diasPT = [
    'Sunday'    => 'Dom',
    'Monday'    => 'Seg',
    'Tuesday'   => 'Ter',
    'Wednesday' => 'Qua',
    'Thursday'  => 'Qui',
    'Friday'    => 'Sex',
    'Saturday'  => 'Sáb',
];

function formatDateWithWeekday(string $dateStr, array $diasPT): string {
    $ts = strtotime($dateStr);
    return date('d/m/Y', $ts) . ' (' . $diasPT[date('l', $ts)] . ')';
}

// Quadro resumo: contagem de ocorrências por dia da semana
$counts = ['Dom' => 0, 'Seg' => 0, 'Ter' => 0, 'Qua' => 0, 'Qui' => 0, 'Sex' => 0, 'Sáb' => 0];
foreach ($requests as $r) {
    if (empty($r['start_date'])) continue;
    $start  = new DateTime($r['start_date']);
    $endRaw = !empty($r['end_date']) ? $r['end_date'] : $r['start_date'];
    $end    = (new DateTime($endRaw))->modify('+1 day');
    $period = new DatePeriod($start, new DateInterval('P1D'), $end);
    foreach ($period as $day) {
        $key = $diasPT[$day->format('l')];
        $counts[$key]++;
    }
}

$pageTitle = 'Relatório de Justificativas de Faltas';
require_once 'layout/header.php';
?>

<div class="print:hidden flex h-full">
    <?php require_once 'layout/sidebar.php'; ?>

    <main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#F2F4F8] p-6 w-full">
        <div class="container mx-auto">

            <!-- Filtros -->
            <div class="bg-white rounded-2xl shadow-sm p-6 mb-6 print:hidden">
                <div class="flex flex-col md:flex-row justify-between items-center mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800 tracking-tight">Relatório de Justificativas de Faltas</h2>
                        <p class="text-gray-500 mt-1">Justificativas aprovadas — com dias da semana e resumo por período</p>
                    </div>
                    <button onclick="window.print()"
                        class="mt-4 md:mt-0 bg-[#1CBB9B] text-white px-6 py-2.5 rounded-xl font-bold hover:bg-[#169C80] transition-all shadow-sm flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2-2v4h10z"/>
                        </svg>
                        Imprimir
                    </button>
                </div>
                <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nome do Aluno</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($name) ?>"
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-brand-DEFAULT focus:border-transparent outline-none"
                            placeholder="Buscar por nome...">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Protocolo</label>
                        <input type="text" name="protocol" value="<?= htmlspecialchars($protocol) ?>"
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-brand-DEFAULT focus:border-transparent outline-none"
                            placeholder="Ex: 2026-1...">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Semestre</label>
                        <input type="text" name="semester" id="semester" value="<?= htmlspecialchars($semester) ?>"
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-brand-DEFAULT focus:border-transparent outline-none"
                            placeholder="AAAA/S">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Curso</label>
                        <select name="course_id"
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-brand-DEFAULT focus:border-transparent outline-none">
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

            <!-- Tabela -->
            <div class="bg-white rounded-2xl shadow-sm overflow-hidden print:hidden">
                <div class="overflow-x-auto">
                    <table class="w-full whitespace-nowrap">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100">
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Protocolo</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Aluno</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Turma</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Data Início</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Data Final</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Justificativa</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($requests)): ?>
                                <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">Nenhuma justificativa encontrada.</td></tr>
                            <?php else: ?>
                                <?php foreach ($requests as $r): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 text-xs font-mono text-gray-500">
                                            <a href="request_details.php?id=<?= $r['id'] ?>&source=absence_report"
                                               class="text-[#1CBB9B] hover:underline"><?= htmlspecialchars($r['protocol_code']) ?></a>
                                            <?php if ($r['is_segunda_chamada']): ?>
                                                <span class="ml-1 px-1.5 py-0.5 bg-blue-50 text-blue-600 rounded text-[10px] font-semibold">2ª Chamada</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($r['student_name']) ?>
                                            <div class="text-xs text-gray-500 font-normal"><?= htmlspecialchars($r['course_name'] ?? '') ?></div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500"><?= htmlspecialchars($r['class_info'] ?? '-') ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-700 font-medium">
                                            <?= !empty($r['start_date']) ? formatDateWithWeekday($r['start_date'], $diasPT) : '-' ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-700 font-medium">
                                            <?= !empty($r['end_date']) ? formatDateWithWeekday($r['end_date'], $diasPT) : '-' ?>
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

                <?php if (!empty($requests)): ?>
                <!-- Quadro resumo por dia da semana -->
                <div class="border-t border-gray-100 px-6 py-5">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Resumo — Dias da Semana com Faltas Justificadas</p>
                    <div class="flex flex-wrap gap-3">
                        <?php foreach ($counts as $dia => $total): ?>
                            <div class="flex flex-col items-center bg-gray-50 border border-gray-200 rounded-xl px-5 py-3 min-w-[64px]">
                                <span class="text-xs font-bold text-gray-400 uppercase"><?= $dia ?></span>
                                <span class="text-2xl font-bold <?= $total > 0 ? 'text-[#1CBB9B]' : 'text-gray-300' ?> mt-1"><?= $total ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </main>
</div>

<!-- Versão para Impressão -->
<div class="hidden print:block p-8 bg-white">
    <div class="text-center mb-6 border-b pb-4">
        <img src="../assets/img/logob.png" alt="IFSC" class="h-12 mx-auto mb-2">
        <h1 class="text-xl font-bold text-gray-900">Relatório de Justificativas de Faltas</h1>
        <p class="text-gray-600 text-sm">Semestre: <?= htmlspecialchars($semester) ?><?= $courseId ? ' | Curso: ' . htmlspecialchars(array_column($courses, 'name', 'id')[$courseId] ?? '') : '' ?></p>
        <p class="text-gray-400 text-xs mt-1">Gerado em: <?= date('d/m/Y H:i') ?></p>
    </div>

    <table class="w-full border-collapse border border-gray-300 text-sm mb-6">
        <thead>
            <tr class="bg-gray-100">
                <th class="border border-gray-300 px-3 py-2 text-left font-bold w-[10%]">Protocolo</th>
                <th class="border border-gray-300 px-3 py-2 text-left font-bold w-[20%]">Aluno</th>
                <th class="border border-gray-300 px-3 py-2 text-left font-bold w-[10%]">Turma</th>
                <th class="border border-gray-300 px-3 py-2 text-left font-bold w-[15%]">Data Início</th>
                <th class="border border-gray-300 px-3 py-2 text-left font-bold w-[15%]">Data Final</th>
                <th class="border border-gray-300 px-3 py-2 text-left font-bold w-[30%]">Justificativa</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($requests as $r): ?>
                <tr>
                    <td class="border border-gray-300 px-3 py-2 font-mono text-xs">
                        <?= htmlspecialchars($r['protocol_code']) ?>
                        <?php if ($r['is_segunda_chamada']): ?><br><span style="font-size:9px;color:#555">(2ª Chamada)</span><?php endif; ?>
                    </td>
                    <td class="border border-gray-300 px-3 py-2">
                        <?= htmlspecialchars($r['student_name']) ?>
                        <div class="text-[10px] text-gray-500"><?= htmlspecialchars($r['course_name'] ?? '') ?></div>
                    </td>
                    <td class="border border-gray-300 px-3 py-2"><?= htmlspecialchars($r['class_info'] ?? '-') ?></td>
                    <td class="border border-gray-300 px-3 py-2"><?= !empty($r['start_date']) ? formatDateWithWeekday($r['start_date'], $diasPT) : '-' ?></td>
                    <td class="border border-gray-300 px-3 py-2"><?= !empty($r['end_date']) ? formatDateWithWeekday($r['end_date'], $diasPT) : '-' ?></td>
                    <td class="border border-gray-300 px-3 py-2 text-justify"><?= nl2br(htmlspecialchars($r['description'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (!empty($requests)): ?>
    <div class="border border-gray-300 rounded p-4">
        <p class="text-xs font-bold text-gray-600 uppercase mb-3">Resumo — Dias da Semana com Faltas Justificadas (período filtrado)</p>
        <table class="border-collapse border border-gray-300 text-sm">
            <thead>
                <tr class="bg-gray-100">
                    <?php foreach (array_keys($counts) as $dia): ?>
                        <th class="border border-gray-300 px-6 py-2 font-bold"><?= $dia ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <?php foreach ($counts as $total): ?>
                        <td class="border border-gray-300 px-6 py-2 text-center font-bold text-lg"><?= $total ?></td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>
        <p class="text-xs text-gray-400 mt-2">* Cada dia no intervalo entre data início e data final de cada justificativa é contabilizado.</p>
    </div>
    <?php endif; ?>
</div>

<script>
    document.getElementById('semester').addEventListener('input', function(e) {
        var x = e.target.value.replace(/\D/g, '').match(/(\d{0,4})(\d{0,1})/);
        e.target.value = !x[2] ? x[1] : x[1] + '/' + x[2];
    });
</script>
</body>
</html>
