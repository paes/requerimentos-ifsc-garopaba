<?php
/**
 * Tela que lista todos os requerimentos do sistema, permitindo buscas e filtros avancados.
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

// Verifica se e SysAdmin
$roleStmt = $conn->prepare("SELECT is_sysadmin FROM roles WHERE id = :id");
$roleStmt->execute([':id' => $user['user_role']]);
if (!$roleStmt->fetchColumn()) {
    header('Location: dashboard.php');
    exit;
}

// Parametros de Busca
$search = $_GET['search'] ?? '';
$semester = $_GET['semester'] ?? Helpers::getCurrentSemester();
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

// Constroi a Query
$query = "
    SELECT r.*, c.name as course_name, rt.name as type_name 
    FROM requests r
    JOIN courses c ON r.course_id = c.id
    JOIN request_types rt ON r.request_type_id = rt.id
    WHERE 1=1
";

$params = [];

if ($semester) {
    $semesterPrefix = str_replace('/', '-', $semester);
    $query .= " AND r.protocol_code LIKE :semester_prefix";
    $params[':semester_prefix'] = $semesterPrefix . '%';
}

if ($search) {
    $query .= " AND (r.student_name LIKE :search OR r.protocol_code = :protocol)";
    $params[':search'] = "%$search%";
    $params[':protocol'] = $search;
}

if ($startDate) {
    $query .= " AND DATE(r.created_at) >= :start_date";
    $params[':start_date'] = $startDate;
}

if ($endDate) {
    $query .= " AND DATE(r.created_at) <= :end_date";
    $params[':end_date'] = $endDate;
}

$query .= " ORDER BY r.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<?php require_once 'layout/header.php'; ?>
<?php require_once 'layout/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto p-8 bg-[#F2F4F8]">
        
        <div class="mb-8 flex justify-between items-end">
            <div>
                <h1 class="text-3xl font-bold text-gray-800 tracking-tight">Todas as Requisições</h1>
                <p class="text-gray-500 mt-1">Visão geral administrativa do sistema</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
            <form method="GET" class="flex flex-col md:flex-row gap-4 items-end">
                <div class="flex-1 w-full">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nome do aluno ou protocolo..." class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all">
                </div>
                <div class="w-full md:w-32">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Semestre</label>
                    <input type="text" name="semester" value="<?= htmlspecialchars($semester) ?>" placeholder="AAAA/S" maxlength="6" oninput="this.value = this.value.replace(/[^0-9]/g, '').replace(/^(\d{4})(\d)/, '$1/$2')" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all text-center">
                </div>
                <div class="w-full md:w-40">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Data Inicial</label>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all">
                </div>
                <div class="w-full md:w-40">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Data Final</label>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all">
                </div>
                <button type="submit" class="bg-[#1CBB9B] text-white px-6 py-2 rounded-xl font-bold hover:bg-[#169C80] transition-all shadow-sm hover:shadow-md h-10 flex items-center justify-center w-full md:w-auto">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    Filtrar
                </button>
                <?php if ($search || $startDate || $endDate): ?>
                    <a href="all_requests.php" class="text-gray-500 hover:text-gray-700 font-medium text-sm py-2 px-4">Limpar</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- List -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50/50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Protocolo</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Aluno</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Curso</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Tipo</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Data</th>
                            <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">Nenhuma requisição encontrada.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($requests as $r): ?>
                                <tr class="hover:bg-gray-50/80 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-700">
                                        <?= htmlspecialchars($r['protocol_code']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= htmlspecialchars($r['student_name']) ?>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($r['student_id']) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= htmlspecialchars($r['course_name']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= htmlspecialchars($r['type_name']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 py-1 rounded-lg text-xs font-bold
                                            <?= $r['status'] === 'pending' ? 'bg-yellow-50 text-yellow-700' : '' ?>
                                            <?= $r['status'] === 'approved' ? 'bg-green-50 text-green-700' : '' ?>
                                            <?= $r['status'] === 'concluded' ? 'bg-brand-light/20 text-brand-dark' : '' ?>
                                            <?= $r['status'] === 'rejected' ? 'bg-red-50 text-red-700' : '' ?>
                                        ">
                                            <?= Helpers::translateStatus($r['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('d/m/Y', strtotime($r['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="request_details.php?id=<?= $r['id'] ?>&source=all_requests" class="text-[#1CBB9B] hover:text-[#169C80]">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
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

<?php require_once 'layout/footer.php'; ?>
