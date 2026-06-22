<?php
/**
 * Modulo CRUD para gerenciar os diferentes tipos de requerimentos disponiveis no sistema.
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
$roleId = $user['user_role'] ?? $user['role_id'];
$roleStmt->execute([':id' => $roleId]);
if (!$roleStmt->fetchColumn()) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $name = $_POST['name'];
        $information = $_POST['information'];
        $attention = $_POST['attention'];
        $active = isset($_POST['active']) ? 1 : 0;
        $featured = isset($_POST['featured']) ? 1 : 0;

        try {
            if ($action === 'create') {
                $stmt = $conn->prepare("INSERT INTO request_types (name, information, attention, active, featured) VALUES (:name, :information, :attention, :active, :featured)");
                $stmt->execute([':name' => $name, ':information' => $information, ':attention' => $attention, ':active' => $active, ':featured' => $featured]);
                $message = 'Tipo de requisição criado com sucesso!';
            } else {
                $id = $_POST['id'];
                $stmt = $conn->prepare("UPDATE request_types SET name = :name, information = :information, attention = :attention, active = :active, featured = :featured WHERE id = :id");
                $stmt->execute([':name' => $name, ':information' => $information, ':attention' => $attention, ':active' => $active, ':featured' => $featured, ':id' => $id]);
                $message = 'Tipo de requisição atualizado com sucesso!';
            }
        } catch (PDOException $e) {
            $error = 'Erro ao salvar: ' . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'];
        try {
            $stmt = $conn->prepare("DELETE FROM request_types WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $message = 'Tipo de requisição excluído com sucesso!';
        } catch (PDOException $e) {
            $error = 'Erro ao excluir: Verifique se existem requisições deste tipo.';
        }
    }
}

$editingType = null;
if (isset($_GET['edit'])) {
    $editStmt = $conn->prepare("SELECT * FROM request_types WHERE id = :id");
    $editStmt->execute([':id' => $_GET['edit']]);
    $editingType = $editStmt->fetch(PDO::FETCH_ASSOC);
}

$stmt = $conn->query("SELECT * FROM request_types ORDER BY id");
$types = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php require_once 'layout/header.php'; ?>
<?php require_once 'layout/sidebar.php'; ?>

<main class="flex-1 overflow-y-auto p-8 bg-[#F2F4F8]">



    <div class="mb-8 flex justify-between items-end">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 tracking-tight">Tipos de Requisição</h1>
            <p class="text-gray-500 mt-1">Configuração de formulários e orientações</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div
            class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6 text-sm font-medium flex items-center shadow-sm">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <?= $message ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div
            class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 text-sm font-medium flex items-center shadow-sm">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <?= $error ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
            <svg class="w-5 h-5 mr-2 text-brand-DEFAULT" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                </path>
            </svg>
            <?= $editingType ? 'Editar Tipo' : 'Novo Tipo de Requisição' ?>
        </h3>
        <form method="POST" class="space-y-4">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="<?= $editingType ? 'update' : 'create' ?>">
            <?php if ($editingType): ?>
                <input type="hidden" name="id" value="<?= $editingType['id'] ?>">
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome do Tipo</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($editingType['name'] ?? '') ?>" required
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Informação (Azul)</label>
                    <textarea name="information"
                        class="w-full h-48 bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all resize-y overflow-y-auto"><?= htmlspecialchars($editingType['information'] ?? '') ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Atenção (Vermelho)</label>
                    <textarea name="attention"
                        class="w-full h-48 bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all resize-y overflow-y-auto"><?= htmlspecialchars($editingType['attention'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="flex items-center justify-between pt-2">
                <div class="flex items-center gap-6">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="active" value="1" <?= (!isset($editingType) || $editingType['active']) ? 'checked' : '' ?> class="h-4 w-4 text-[#1CBB9B] focus:ring-[#1CBB9B] border-gray-300 rounded">
                        <span class="ml-2 text-sm text-gray-700">Ativo (visível no formulário)</span>
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="featured" value="1" <?= ($editingType['featured'] ?? 0) ? 'checked' : '' ?> class="h-4 w-4 text-amber-500 focus:ring-amber-400 border-gray-300 rounded">
                        <span class="ml-2 text-sm text-gray-700">Destacar como "Mais utilizado"</span>
                    </label>
                </div>
                <div class="flex gap-2">
                    <?php if ($editingType): ?>
                        <a href="request_types.php"
                            class="px-6 py-2 rounded-xl font-bold text-gray-600 hover:bg-gray-100 transition-all">Cancelar</a>
                    <?php endif; ?>
                    <button type="submit"
                        class="bg-[#1CBB9B] text-white px-8 py-2 rounded-xl font-bold hover:bg-[#169C80] transition-all shadow-sm hover:shadow-md">
                        <?= $editingType ? 'Salvar Alterações' : 'Adicionar Tipo' ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-lg font-bold text-gray-800">Tipos Cadastrados</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50/50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Nome</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Info / Atenção</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Destaque</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    <?php foreach ($types as $t): ?>
                        <tr class="hover:bg-gray-50/80 transition-colors group">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($t['name']) ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <div class="max-w-xs truncate" title="<?= htmlspecialchars($t['information']) ?>">
                                    <span class="text-blue-500 font-bold">I:</span>
                                    <?= htmlspecialchars(substr($t['information'], 0, 50)) ?>...
                                </div>
                                <div class="max-w-xs truncate" title="<?= htmlspecialchars($t['attention']) ?>">
                                    <span class="text-red-500 font-bold">A:</span>
                                    <?= htmlspecialchars(substr($t['attention'], 0, 50)) ?>...
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php if ($t['active']): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Ativo</span>
                                <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php if (!empty($t['featured'])): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-amber-100 text-amber-800">Destaque</span>
                                <?php else: ?>
                                    <span class="text-gray-300">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="request_types.php?edit=<?= $t['id'] ?>"
                                        class="text-blue-400 hover:text-blue-600 font-bold p-2 hover:bg-blue-50 rounded-lg transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                            </path>
                                        </svg>
                                    </a>
                                    <form method="POST" class="inline-block"
                                        onsubmit="return confirm('Tem certeza que deseja excluir esta função?');">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                        <button type="submit"
                                            class="text-red-400 hover:text-red-600 font-bold p-2 hover:bg-red-50 rounded-lg transition-colors">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                </path>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php require_once 'layout/footer.php'; ?>