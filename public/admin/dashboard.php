<?php
/**
 * Painel de controle do administrador (Dashboard) com indicadores, graficos e os requerimentos mais recentes.
 *
 * @author Prof. Eduardo Gomes
 */
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../src/Auth.php';

Auth::check();
$user = Auth::user();

$db = new Database();
$conn = $db->getConnection();

// Determina se o usuario esta vinculado a um curso
$roleStmt = $conn->prepare("SELECT is_course_bound, is_sysadmin FROM roles WHERE id = :id");
$roleStmt->execute([':id' => $user['user_role']]);
$roleData = $roleStmt->fetch(PDO::FETCH_ASSOC);
$isCourseBound = $roleData['is_course_bound'];
$isSysAdmin = $roleData['is_sysadmin'];

// Constroi a Query for Tasks
$query = "
    SELECT r.*, c.name as course_name, rt.name as type_name 
    FROM requests r
    JOIN courses c ON r.course_id = c.id
    JOIN request_types rt ON r.request_type_id = rt.id
    JOIN workflow_steps ws ON r.request_type_id = ws.request_type_id AND r.current_step_order = ws.step_order
    WHERE r.status = 'pending'
    AND ws.role_id = :role_id
";

$params = [':role_id' => $user['user_role']];

if ($isCourseBound) {
    $userCourses = $user['user_courses'] ?? [];
    if (!empty($userCourses)) {
        $inQuery = "";
        foreach ($userCourses as $i => $cid) {
            $key = ":course_id_$i";
            $inQuery .= "$key,";
            $params[$key] = $cid;
        }
        $inQuery = rtrim($inQuery, ',');
        $query .= " AND r.course_id IN ($inQuery)";
    } else {
        $query .= " AND 1=0";
    }
}

$query .= " ORDER BY r.created_at ASC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<?php require_once 'layout/header.php'; ?>
<?php require_once 'layout/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto p-8 bg-[#F2F4F8]">
        
        <div class="mb-8 flex justify-between items-end">
            <div>
                <h1 class="text-3xl font-bold text-gray-800 tracking-tight">Dashboard</h1>
                <p class="text-gray-500 mt-1">Visão geral das solicitações e pendências</p>
            </div>
            <!-- Optional: Date or other info -->
        </div>

        <?php if (empty($requests)): ?>
            <div class="flex flex-col items-center justify-center h-96 bg-white rounded-2xl shadow-sm border border-gray-100 text-center">
                <div class="h-20 w-20 bg-brand-light/10 rounded-full flex items-center justify-center mb-6">
                    <svg class="w-10 h-10 text-brand-DEFAULT" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Tudo limpo!</h3>
                <p class="text-gray-500 max-w-md mx-auto">Nenhuma tarefa pendente para o seu perfil no momento. Aproveite o dia!</p>
            </div>
        <?php else: ?>
            
            <!-- Stats Cards (Optional placeholder for "Minton" style stats) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-gray-500 text-sm font-medium uppercase tracking-wider">Pendentes</h3>
                        <span class="p-2 bg-yellow-50 text-yellow-600 rounded-lg">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </span>
                    </div>
                    <div class="text-3xl font-bold text-gray-800"><?= count($requests) ?></div>
                    <div class="text-xs text-gray-400 mt-2">Tarefas aguardando ação</div>
                </div>
                <!-- Add more stats if data available -->
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-gray-800">Tarefas Recentes</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50/50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Protocolo</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Aluno</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Curso</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Tipo</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Data</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Ação</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <?php foreach ($requests as $req): ?>
                                <tr class="hover:bg-gray-50/80 transition-colors group">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-brand-dark">
                                        <?= htmlspecialchars($req['protocol_code']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div class="font-bold"><?= htmlspecialchars($req['student_name']) ?></div>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($req['student_id']) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= htmlspecialchars($req['course_name']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-600 border border-blue-100">
                                            <?= htmlspecialchars($req['type_name']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('d/m/Y', strtotime($req['created_at'])) ?>
                                        <span class="text-xs text-gray-400 ml-1"><?= date('H:i', strtotime($req['created_at'])) ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="request_details.php?id=<?= $req['id'] ?>&source=dashboard" class="inline-flex items-center justify-center px-4 py-2 bg-[#1CBB9B] text-white text-xs font-bold uppercase tracking-wide rounded-lg hover:bg-[#169C80] transition-all shadow-sm hover:shadow-md">
                                            Analisar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>

<?php require_once 'layout/footer.php'; ?>
