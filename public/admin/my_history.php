<?php
/**
 * Tela que exibe o historico de requerimentos em que o usuario logado atuou (analisou ou deferiu).
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

// Parametros de Busca
$search = $_GET['search'] ?? '';
$semester = $_GET['semester'] ?? Helpers::getCurrentSemester();

// Constroi a Query
$query = "
    SELECT rh.*, r.protocol_code, r.student_name, r.created_at as request_date, r.id as request_id
    FROM request_history rh
    JOIN requests r ON rh.request_id = r.id
    WHERE rh.user_id = :uid
";

$params = [':uid' => $user['user_id']];

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

$query .= " ORDER BY rh.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<?php require_once 'layout/header.php'; ?>
<?php require_once 'layout/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto p-8 bg-[#F2F4F8]">
        
        <div class="mb-8 flex justify-between items-end">
            <div>
                <h1 class="text-3xl font-bold text-gray-800 tracking-tight">Meu Histórico</h1>
                <p class="text-gray-500 mt-1">Requisições analisadas por você</p>
            </div>
        </div>

        <!-- Search -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
            <form method="GET" class="flex gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nome do aluno ou protocolo exato..." class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all">
                </div>
                <div class="w-32">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Semestre</label>
                    <input type="text" name="semester" value="<?= htmlspecialchars($semester) ?>" placeholder="AAAA/S" maxlength="6" oninput="this.value = this.value.replace(/[^0-9]/g, '').replace(/^(\d{4})(\d)/, '$1/$2')" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all text-center">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="bg-[#1CBB9B] text-white px-6 py-2 rounded-xl font-bold hover:bg-[#169C80] transition-all shadow-sm hover:shadow-md h-10 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        Filtrar
                    </button>
                    <?php if ($search): ?>
                        <a href="my_history.php" class="ml-2 text-gray-500 hover:text-gray-700 font-medium text-sm py-2">Limpar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- List -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50/50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Data da Ação</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Protocolo</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Aluno</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Ação</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Observação</th>
                            <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Ver</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <?php if (empty($history)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">Nenhum registro encontrado.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($history as $h): ?>
                                <tr class="hover:bg-gray-50/80 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('d/m/Y H:i', strtotime($h['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-700">
                                        <?= htmlspecialchars($h['protocol_code']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= htmlspecialchars($h['student_name']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 py-1 rounded-lg text-xs font-bold
                                            <?= $h['action'] === 'approve' ? 'bg-green-100 text-green-700' : ($h['action'] === 'reject' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700') ?>">
                                            <?= $h['action'] === 'approve' ? 'Deferido' : ($h['action'] === 'reject' ? 'Indeferido' : 'Comentário') ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate" title="<?= htmlspecialchars($h['observation']) ?>">
                                        <?= htmlspecialchars($h['observation']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="request_details.php?id=<?= $h['request_id'] ?>&source=my_history" class="text-[#1CBB9B] hover:text-[#169C80]">
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
