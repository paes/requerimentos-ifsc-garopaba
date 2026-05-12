<?php
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../src/Auth.php';
require_once '../../src/Helpers.php';

Auth::check();
$user = Auth::user();

$db = new Database();
$conn = $db->getConnection();

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
        $name   = trim($_POST['name'] ?? '');
        $email  = trim($_POST['email'] ?? '') ?: null;
        $active = isset($_POST['active']) ? 1 : 0;

        try {
            if ($action === 'create') {
                $stmt = $conn->prepare("INSERT INTO teachers (name, email, active) VALUES (:name, :email, :active)");
                $stmt->execute([':name' => $name, ':email' => $email, ':active' => $active]);
                $message = 'Docente criado com sucesso!';
            } else {
                $id = intval($_POST['id']);
                $stmt = $conn->prepare("UPDATE teachers SET name = :name, email = :email, active = :active WHERE id = :id");
                $stmt->execute([':name' => $name, ':email' => $email, ':active' => $active, ':id' => $id]);
                $message = 'Docente atualizado com sucesso!';
            }
        } catch (PDOException $e) {
            $error = 'Erro ao salvar: ' . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        try {
            $stmt = $conn->prepare("DELETE FROM teachers WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $message = 'Docente excluído com sucesso!';
        } catch (PDOException $e) {
            $error = 'Erro ao excluir o docente.';
        }
    }
}

$editingTeacher = null;
if (isset($_GET['edit'])) {
    $editStmt = $conn->prepare("SELECT * FROM teachers WHERE id = :id");
    $editStmt->execute([':id' => intval($_GET['edit'])]);
    $editingTeacher = $editStmt->fetch(PDO::FETCH_ASSOC);
}

$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $stmt = $conn->prepare("SELECT * FROM teachers WHERE name LIKE :q ORDER BY name");
    $stmt->execute([':q' => '%' . $search . '%']);
} else {
    $stmt = $conn->query("SELECT * FROM teachers ORDER BY name");
}
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php require_once 'layout/header.php'; ?>
<?php require_once 'layout/sidebar.php'; ?>

<main class="flex-1 overflow-y-auto p-8 bg-[#F2F4F8] dark:bg-gray-900">

    <div class="mb-8 flex justify-between items-end">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100 tracking-tight">Docentes</h1>
            <p class="text-gray-500 dark:text-gray-400 mt-1">Professores disponíveis para seleção nos requerimentos</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6 text-sm font-medium flex items-center shadow-sm">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 text-sm font-medium flex items-center shadow-sm">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 mb-8">
        <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4 flex items-center">
            <svg class="w-5 h-5 mr-2 text-brand-DEFAULT" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
            </svg>
            <?= $editingTeacher ? 'Editar Docente' : 'Novo Docente' ?>
        </h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="<?= $editingTeacher ? 'update' : 'create' ?>">
            <?php if ($editingTeacher): ?>
                <input type="hidden" name="id" value="<?= $editingTeacher['id'] ?>">
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nome do Docente</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($editingTeacher['name'] ?? '') ?>" required
                        class="w-full bg-gray-50 dark:bg-gray-700 dark:text-gray-100 border border-gray-200 dark:border-gray-600 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">E-mail institucional</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($editingTeacher['email'] ?? '') ?>"
                        placeholder="nome@ifsc.edu.br"
                        class="w-full bg-gray-50 dark:bg-gray-700 dark:text-gray-100 border border-gray-200 dark:border-gray-600 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all">
                </div>
            </div>

            <div class="flex items-center justify-between pt-2">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" name="active" value="1" <?= (!isset($editingTeacher) || $editingTeacher['active']) ? 'checked' : '' ?> class="h-4 w-4 text-[#1CBB9B] focus:ring-[#1CBB9B] border-gray-300 rounded">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Ativo (disponível nos requerimentos)</span>
                </label>
                <div class="flex gap-2">
                    <?php if ($editingTeacher): ?>
                        <a href="teachers.php" class="px-6 py-2 rounded-xl font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-all">Cancelar</a>
                    <?php endif; ?>
                    <button type="submit"
                        class="bg-[#1CBB9B] text-white px-8 py-2 rounded-xl font-bold hover:bg-[#169C80] transition-all shadow-sm hover:shadow-md">
                        <?= $editingTeacher ? 'Salvar Alterações' : 'Adicionar Docente' ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100">
                Docentes Cadastrados
                <span class="ml-2 text-sm font-normal text-gray-400">(<?= count($teachers) ?>)</span>
            </h3>
            <form method="GET" class="flex items-center gap-2">
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar por nome..."
                    class="bg-gray-50 dark:bg-gray-700 dark:text-gray-100 border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-1.5 text-sm focus:ring-2 focus:ring-[#1CBB9B] outline-none w-56">
                <button type="submit" class="bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 px-3 py-1.5 rounded-xl text-sm hover:bg-gray-200 transition-colors">Buscar</button>
                <?php if ($search): ?>
                    <a href="teachers.php" class="text-sm text-gray-400 hover:text-gray-600">Limpar</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                <thead class="bg-gray-50/50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nome</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">E-mail</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                    <?php if (empty($teachers)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-gray-400 dark:text-gray-500 text-sm">Nenhum docente encontrado.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($teachers as $t): ?>
                        <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">
                                <?= htmlspecialchars($t['name']) ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <?php if ($t['email']): ?>
                                    <a href="mailto:<?= htmlspecialchars($t['email']) ?>" class="text-[#1CBB9B] hover:underline"><?= htmlspecialchars($t['email']) ?></a>
                                <?php else: ?>
                                    <span class="text-amber-500 text-xs font-medium">sem e-mail</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <?php if ($t['active']): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Ativo</span>
                                <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="teachers.php?edit=<?= $t['id'] ?>"
                                        class="text-blue-400 hover:text-blue-600 font-bold p-2 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </a>
                                    <form method="POST" class="inline-block" onsubmit="return confirm('Excluir este docente?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                        <button type="submit"
                                            class="text-red-400 hover:text-red-600 font-bold p-2 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition-colors">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
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
