<?php
/**
 * Modulo CRUD para gerenciamento dos cursos cadastrados no sistema.
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

    if ($action === 'create') {
        $name         = trim($_POST['name'] ?? '');
        $level        = $_POST['level'] ?? '';
        $abbreviation = trim($_POST['abbreviation'] ?? '') ?: null;
        $isEad        = isset($_POST['is_ead']) ? 1 : 0;

        try {
            $stmt = $conn->prepare("INSERT INTO courses (name, abbreviation, is_ead, level) VALUES (:name, :abbr, :ead, :level)");
            $stmt->execute([':name' => $name, ':abbr' => $abbreviation, ':ead' => $isEad, ':level' => $level]);
            $message = 'Curso criado com sucesso!';
        } catch (PDOException $e) {
            $error = 'Erro ao criar curso: ' . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'];
        try {
            $stmt = $conn->prepare("DELETE FROM courses WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $message = 'Curso excluído com sucesso!';
        } catch (PDOException $e) {
            $error = 'Erro ao excluir curso. Verifique se não há solicitações vinculadas.';
        }
    } elseif ($action === 'update') {
        $id           = (int)$_POST['id'];
        $name         = trim($_POST['name'] ?? '');
        $level        = $_POST['level'] ?? '';
        $active       = isset($_POST['active']) ? 1 : 0;
        $abbreviation = trim($_POST['abbreviation'] ?? '') ?: null;
        $isEad        = isset($_POST['is_ead']) ? 1 : 0;

        try {
            $stmt = $conn->prepare("UPDATE courses SET name = :name, abbreviation = :abbr, is_ead = :ead, level = :level, active = :active WHERE id = :id");
            $stmt->execute([':name' => $name, ':abbr' => $abbreviation, ':ead' => $isEad, ':level' => $level, ':active' => $active, ':id' => $id]);
            $message = 'Curso atualizado com sucesso!';
        } catch (PDOException $e) {
            $error = 'Erro ao atualizar curso: ' . $e->getMessage();
        }
    }
}

// Busca Cursos
$stmt = $conn->query("SELECT * FROM courses ORDER BY name");
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<?php require_once 'layout/header.php'; ?>
<?php require_once 'layout/sidebar.php'; ?>

<main class="flex-1 overflow-y-auto p-8 bg-[#F2F4F8]">

    <div class="mb-8 flex justify-between items-end">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 tracking-tight">Gerenciar Cursos</h1>
            <p class="text-gray-500 mt-1">Administração do Sistema</p>
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

    <!-- Create Form -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
            <svg class="w-5 h-5 mr-2 text-brand-DEFAULT" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6">
                </path>
            </svg>
            Novo Curso
        </h3>
        <form method="POST" class="flex flex-col md:flex-row gap-4 items-end flex-wrap">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="create">
            <div class="flex-1 min-w-48">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome do Curso</label>
                <input type="text" name="name" required
                    class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all">
            </div>
            <div class="w-full md:w-28">
                <label class="block text-sm font-medium text-gray-700 mb-1">Abreviação
                    <span class="text-gray-400 font-normal text-xs">(ex: ADM, SI)</span>
                </label>
                <input type="text" name="abbreviation" maxlength="20" placeholder="ex: ADM"
                    class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all">
            </div>
            <div class="w-full md:w-48">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nível</label>
                <select name="level"
                    class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all">
                    <option value="Técnico Integrado">Técnico Integrado</option>
                    <option value="Técnico Concomitante">Técnico Concomitante</option>
                    <option value="Técnico Subsequente">Técnico Subsequente</option>
                    <option value="Graduação">Graduação</option>
                    <option value="Pós Graduação">Pós Graduação</option>
                    <option value="Formação Continuada">Formação Continuada</option>
                    <option value="PROEJA">PROEJA</option>
                </select>
            </div>
            <div class="flex items-center gap-2 pb-2">
                <input type="checkbox" name="is_ead" id="create_ead" value="1"
                    class="w-4 h-4 text-[#1CBB9B] rounded border-gray-300 focus:ring-[#1CBB9B]">
                <label for="create_ead" class="text-sm font-medium text-gray-700">EAD</label>
            </div>
            <button type="submit"
                class="w-full md:w-auto bg-[#1CBB9B] text-white px-6 py-2 rounded-xl font-bold hover:bg-[#169C80] transition-all shadow-sm hover:shadow-md">Adicionar</button>
        </form>
    </div>

    <!-- List -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-lg font-bold text-gray-800">Cursos Cadastrados</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50/50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Nome</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Abrev.</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Nível</th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">EAD</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    <?php foreach ($courses as $c): ?>
                        <tr class="hover:bg-gray-50/80 transition-colors group">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400 font-mono"><?= $c['id'] ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($c['name']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php if ($c['abbreviation']): ?>
                                <code class="px-1.5 py-0.5 bg-gray-100 rounded text-xs"><?= htmlspecialchars($c['abbreviation']) ?></code>
                                <?php else: ?>
                                <span class="text-gray-300 text-xs">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 capitalize">
                                <span class="px-2 py-1 rounded-lg bg-gray-100 text-gray-600 text-xs font-medium"><?= htmlspecialchars($c['level']) ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                <?php if ($c['is_ead']): ?>
                                <span class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 text-xs font-semibold">EAD</span>
                                <?php else: ?>
                                <span class="text-gray-300 text-xs">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="px-2 py-1 rounded-lg text-xs font-bold <?= $c['active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                    <?= $c['active'] ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button onclick="openEditModal(<?= htmlspecialchars(json_encode($c)) ?>)"
                                    class="text-blue-400 hover:text-blue-600 font-bold p-2 hover:bg-blue-50 rounded-lg transition-colors mr-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                        </path>
                                    </svg>
                                </button>
                                <form method="POST" class="inline-block"
                                    onsubmit="return confirm('Tem certeza que deseja excluir este curso?');">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <button type="submit"
                                        class="text-red-400 hover:text-red-600 font-bold p-2 hover:bg-red-50 rounded-lg transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                            </path>
                                        </svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-xl p-8 w-full max-w-md m-4 animate-fade-in-up">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-gray-800">Editar Curso</h3>
            <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </button>
        </div>
        <form method="POST" class="space-y-4">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome do Curso</label>
                <input type="text" name="name" id="edit_name" required
                    class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Abreviação
                    <span class="text-gray-400 font-normal text-xs ml-1">prefixo usado em class_group (ex: ADM, SI)</span>
                </label>
                <input type="text" name="abbreviation" id="edit_abbreviation" maxlength="20" placeholder="ex: ADM"
                    class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nível</label>
                <select name="level" id="edit_level"
                    class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all">
                    <option value="Técnico Integrado">Técnico Integrado</option>
                    <option value="Técnico Concomitante">Técnico Concomitante</option>
                    <option value="Técnico Subsequente">Técnico Subsequente</option>
                    <option value="Graduação">Graduação</option>
                    <option value="Pós Graduação">Pós Graduação</option>
                    <option value="Formação Continuada">Formação Continuada</option>
                    <option value="PROEJA">PROEJA</option>
                </select>
            </div>

            <div class="flex flex-col gap-2 p-4 bg-gray-50 rounded-xl border border-gray-100">
                <div class="flex items-center">
                    <input type="checkbox" name="active" id="edit_active" value="1"
                        class="w-5 h-5 text-[#1CBB9B] rounded focus:ring-[#1CBB9B] border-gray-300">
                    <label for="edit_active" class="ml-3 block text-sm font-medium text-gray-700">Curso Ativo</label>
                </div>
                <div class="flex items-center">
                    <input type="checkbox" name="is_ead" id="edit_ead" value="1"
                        class="w-5 h-5 text-blue-500 rounded focus:ring-blue-400 border-gray-300">
                    <label for="edit_ead" class="ml-3 block text-sm font-medium text-gray-700">
                        Curso EAD
                        <span class="text-gray-400 font-normal text-xs ml-1">— aulas presenciais pontuam 0,5× no Justiceiro do Tempo</span>
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <button type="button" onclick="closeEditModal()"
                    class="px-4 py-2 text-gray-500 hover:text-gray-700 font-bold transition-colors">Cancelar</button>
                <button type="submit"
                    class="bg-[#1CBB9B] text-white px-6 py-2 rounded-xl font-bold hover:bg-[#169C80] transition-all shadow-sm hover:shadow-md">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditModal(course) {
        document.getElementById('edit_id').value = course.id;
        document.getElementById('edit_name').value = course.name;
        document.getElementById('edit_abbreviation').value = course.abbreviation || '';
        document.getElementById('edit_level').value = course.level;
        document.getElementById('edit_active').checked = course.active == 1;
        document.getElementById('edit_ead').checked = course.is_ead == 1;

        const modal = document.getElementById('editModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeEditModal() {
        const modal = document.getElementById('editModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
</script>

<?php require_once 'layout/footer.php'; ?>