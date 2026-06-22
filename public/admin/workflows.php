<?php
/**
 * Modulo CRUD para definir as etapas e o fluxo de aprovacao de cada tipo de requerimento.
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

// Verifica se e SysAdmin
$roleStmt = $conn->prepare("SELECT is_sysadmin FROM roles WHERE id = :id");
$roleStmt->execute([':id' => $user['user_role']]);
if (!$roleStmt->fetchColumn()) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';

// Lida com Acoes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_type') {
        $name = $_POST['name'];
        try {
            $stmt = $conn->prepare("INSERT INTO request_types (name, active) VALUES (:name, 1)");
            $stmt->execute([':name' => $name]);
            $message = 'Tipo de requerimento criado!';
        } catch (PDOException $e) {
            $error = 'Erro ao criar tipo.';
        }
    } elseif ($action === 'add_step') {
        $type_id = $_POST['request_type_id'];
        $role_id = $_POST['role_id'];
        $order = $_POST['step_order'];
        
        try {
            $stmt = $conn->prepare("INSERT INTO workflow_steps (request_type_id, step_order, role_id) VALUES (:tid, :order, :rid)");
            $stmt->execute([':tid' => $type_id, ':order' => $order, ':rid' => $role_id]);
            $message = 'Etapa adicionada!';
        } catch (PDOException $e) {
            $error = 'Erro ao adicionar etapa. Verifique se a ordem já existe.';
        }
    } elseif ($action === 'delete_step') {
        $id = $_POST['id'];
        try {
            $stmt = $conn->prepare("DELETE FROM workflow_steps WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $message = 'Etapa removida!';
        } catch (PDOException $e) {
            $error = 'Erro ao remover etapa.';
        }
    }
}

// Busca Dados
$types = $conn->query("SELECT * FROM request_types ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$roles = $conn->query("SELECT * FROM roles ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

?>
<?php require_once 'layout/header.php'; ?>
<?php require_once 'layout/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto p-8 bg-[#F2F4F8]">
        
        <div class="mb-8 flex justify-between items-end">
            <div>
                <h1 class="text-3xl font-bold text-gray-800 tracking-tight">Gerenciar Workflows</h1>
                <p class="text-gray-500 mt-1">Administração do Sistema</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6 text-sm font-medium flex items-center shadow-sm">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <?= $message ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 text-sm font-medium flex items-center shadow-sm">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- Create Request Type -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-brand-DEFAULT" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Novo Tipo de Requerimento
            </h3>
            <form method="POST" class="flex gap-4 items-end">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="create_type">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome do Tipo</label>
                    <input type="text" name="name" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all">
                </div>
                <button type="submit" class="w-auto bg-[#1CBB9B] text-white px-6 py-2 rounded-xl font-bold hover:bg-[#169C80] transition-all shadow-sm hover:shadow-md">Criar Tipo</button>
            </form>
        </div>

        <!-- List Types and Steps -->
        <div class="space-y-6">
            <?php foreach ($types as $type): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50/50 border-b border-gray-100 flex justify-between items-center cursor-pointer hover:bg-gray-100 transition-colors" onclick="toggleWorkflow(<?= $type['id'] ?>)">
                        <div class="flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-brand-light/20 flex items-center justify-center text-brand-dark font-bold">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                            </div>
                            <h3 class="text-lg font-bold text-gray-800"><?= htmlspecialchars($type['name']) ?></h3>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-xs font-medium text-gray-400 uppercase tracking-wider bg-gray-100 px-2 py-1 rounded-md">ID: <?= $type['id'] ?></span>
                            <div id="icon-<?= $type['id'] ?>" class="text-gray-400">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                            </div>
                        </div>
                    </div>
                    
                    <div id="content-<?= $type['id'] ?>" class="hidden p-6 border-t border-gray-100">
                        <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                            Etapas do Fluxo
                        </h4>
                        
                        <?php
                        $stepsStmt = $conn->prepare("
                            SELECT ws.*, r.name as role_name 
                            FROM workflow_steps ws 
                            JOIN roles r ON ws.role_id = r.id 
                            WHERE ws.request_type_id = :tid 
                            ORDER BY ws.step_order
                        ");
                        $stepsStmt->execute([':tid' => $type['id']]);
                        $steps = $stepsStmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>

                        <ul class="space-y-3 mb-6">
                            <?php foreach ($steps as $step): ?>
                                <li class="flex items-center justify-between bg-gray-50 p-3 rounded-xl border border-gray-100 group hover:border-brand-light/30 transition-colors">
                                    <div class="flex items-center">
                                        <span class="bg-white border border-gray-200 text-gray-600 font-bold rounded-lg w-8 h-8 flex items-center justify-center text-sm mr-3 shadow-sm">
                                            <?= $step['step_order'] ?>
                                        </span>
                                        <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($step['role_name']) ?></span>
                                    </div>
                                    <form method="POST" onsubmit="return confirm('Remover etapa?');">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="action" value="delete_step">
                                        <input type="hidden" name="id" value="<?= $step['id'] ?>">
                                        <button type="submit" class="text-gray-400 hover:text-red-500 p-1 rounded-md hover:bg-red-50 transition-all">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                            <?php if (empty($steps)): ?>
                                <li class="text-sm text-gray-400 italic pl-2">Nenhuma etapa definida.</li>
                            <?php endif; ?>
                        </ul>

                        <form method="POST" class="flex gap-4 items-end bg-gray-50 p-4 rounded-xl border border-dashed border-gray-300 hover:border-brand-DEFAULT/50 transition-colors">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="add_step">
                            <input type="hidden" name="request_type_id" value="<?= $type['id'] ?>">
                            
                            <div class="w-24">
                                <label class="block text-xs font-bold text-gray-500 mb-1 uppercase tracking-wider">Ordem</label>
                                <input type="number" name="step_order" required class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-DEFAULT focus:border-transparent outline-none transition-all">
                            </div>
                            <div class="flex-1">
                                <label class="block text-xs font-bold text-gray-500 mb-1 uppercase tracking-wider">Papel Responsável</label>
                                <select name="role_id" required class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-DEFAULT focus:border-transparent outline-none transition-all">
                                    <?php foreach ($roles as $r): ?>
                                        <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="bg-white border border-gray-200 text-[#1CBB9B] px-4 py-2 rounded-lg text-sm font-bold hover:bg-[#1CBB9B] hover:text-white hover:border-[#1CBB9B] transition-all shadow-sm">
                                <span class="flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                    Adicionar
                                </span>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <script>
    function toggleWorkflow(id) {
        const content = document.getElementById('content-' + id);
        const iconContainer = document.getElementById('icon-' + id);
        
        if (content.classList.contains('hidden')) {
            content.classList.remove('hidden');
            // Icone de menos
            iconContainer.innerHTML = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path></svg>';
        } else {
            content.classList.add('hidden');
            // Icone de mais
            iconContainer.innerHTML = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>';
        }
    }
    </script>

<?php require_once 'layout/footer.php'; ?>
