<?php
/**
 * Modulo CRUD para gerenciamento dos usuarios administrativos do sistema.
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

$message = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'updated') {
    $message = 'Usuário atualizado com sucesso!';
}
$error = '';

// Busca Perfis e Cursos para Formularios
$roles = $conn->query("SELECT * FROM roles ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$courses = $conn->query("SELECT * FROM courses ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$editingUser = null;
$editingUserCourses = [];

if (isset($_GET['edit'])) {
    $editId = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $editId]);
    $editingUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($editingUser) {
        $ucStmt = $conn->prepare("SELECT course_id FROM user_courses WHERE user_id = :uid");
        $ucStmt->execute([':uid' => $editId]);
        $editingUserCourses = $ucStmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update') {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $email = $_POST['email'];
        $receive_email = isset($_POST['receive_email']) ? 1 : 0;
        $role_id = $_POST['role_id'];
        $course_ids = $_POST['course_ids'] ?? [];

        try {
            $conn->beginTransaction();
            
            $stmt = $conn->prepare("UPDATE users SET name = :name, email = :email, receive_email = :receive_email, role_id = :role_id WHERE id = :id");
            $stmt->execute([':name' => $name, ':email' => $email, ':receive_email' => $receive_email, ':role_id' => $role_id, ':id' => $id]);

            // Atualiza Courses
            $delStmt = $conn->prepare("DELETE FROM user_courses WHERE user_id = :id");
            $delStmt->execute([':id' => $id]);

            if (!empty($course_ids)) {
                $ucStmt = $conn->prepare("INSERT INTO user_courses (user_id, course_id) VALUES (:uid, :cid)");
                foreach ($course_ids as $cid) {
                    $ucStmt->execute([':uid' => $id, ':cid' => $cid]);
                }
            }

            $conn->commit();
            header('Location: users.php?msg=updated');
            exit;
            
        } catch (PDOException $e) {
            $conn->rollBack();
            $error = 'Erro ao atualizar usuário: ' . $e->getMessage();
        }
    } elseif ($action === 'create') {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $receive_email = isset($_POST['receive_email']) ? 1 : 0;
        $role_id = $_POST['role_id'];
        $course_ids = $_POST['course_ids'] ?? [];

        try {
            $conn->beginTransaction();
            
            // Nota: a senha esta vazia mas o campo existe no BD. LDAP cuida da autenticacao.
            $stmt = $conn->prepare("INSERT INTO users (name, email, receive_email, password, role_id) VALUES (:name, :email, :receive_email, '', :role_id)");
            $stmt->execute([':name' => $name, ':email' => $email, ':receive_email' => $receive_email, ':role_id' => $role_id]);
            $userId = $conn->lastInsertId();

            if (!empty($course_ids)) {
                $ucStmt = $conn->prepare("INSERT INTO user_courses (user_id, course_id) VALUES (:uid, :cid)");
                foreach ($course_ids as $cid) {
                    $ucStmt->execute([':uid' => $userId, ':cid' => $cid]);
                }
            }
            $conn->commit();
            $message = 'Usuário criado com sucesso!';
        } catch (PDOException $e) {
            $conn->rollBack();
            $error = 'Erro ao criar usuário: ' . $e->getMessage();
        }
    }
 elseif ($action === 'delete') {
        $id = $_POST['id'];
        if ($id == $user['user_id']) {
            $error = 'Você não pode excluir a si mesmo.';
        } else {
            try {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $message = 'Usuário excluído com sucesso!';
            } catch (PDOException $e) {
                $error = 'Erro ao excluir usuário.';
            }
        }
    }
}

// Busca Usuarios
$usersQuery = "
    SELECT u.*, r.name as role_name, GROUP_CONCAT(c.name SEPARATOR ', ') as course_names
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    LEFT JOIN user_courses uc ON u.id = uc.user_id
    LEFT JOIN courses c ON uc.course_id = c.id 
    GROUP BY u.id
    ORDER BY u.name
";
$users = $conn->query($usersQuery)->fetchAll(PDO::FETCH_ASSOC);

?>
<?php require_once 'layout/header.php'; ?>
<?php require_once 'layout/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto p-8 bg-[#F2F4F8]">
        
        <div class="mb-8 flex justify-between items-end">
            <div>
                <h1 class="text-3xl font-bold text-gray-800 tracking-tight">Gerenciar Usuários</h1>
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

        <!-- Create Form -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-brand-DEFAULT" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                <?= $editingUser ? 'Editar Usuário' : 'Novo Usuário' ?>
            </h3>
            <form method="POST" class="space-y-6">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="<?= $editingUser ? 'update' : 'create' ?>">
                <?php if ($editingUser): ?>
                    <input type="hidden" name="id" value="<?= $editingUser['id'] ?>">
                <?php endif; ?>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Row 1: Basic Info -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nome Completo</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($editingUser['name'] ?? '') ?>" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all placeholder-gray-400">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($editingUser['email'] ?? '') ?>" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all placeholder-gray-400">
                    </div>

                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
                    <!-- Row 2: Role & Options -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Função</label>
                        <select name="role_id" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all">
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['id'] ?>" <?= ($editingUser && $editingUser['role_id'] == $role['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($role['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex items-center h-full pt-8">
                        <label class="flex items-center cursor-pointer p-2 rounded-lg hover:bg-gray-50 transition-colors w-full border border-transparent hover:border-gray-100">
                            <input type="checkbox" name="receive_email" value="1" <?= (!isset($editingUser) || $editingUser['receive_email']) ? 'checked' : '' ?> class="form-checkbox h-5 w-5 text-[#1CBB9B] rounded border-gray-300 focus:ring-[#1CBB9B]">
                            <div class="ml-3">
                                <span class="block text-sm font-medium text-gray-700">Receber Notificações</span>
                                <span class="block text-xs text-gray-500">Enviar emails sobre atualizações</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Row 3: Courses (Full Width) -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Cursos Associados 
                        <span class="text-xs font-normal text-gray-400 ml-1">(Segure Ctrl/Cmd para selecionar múltiplos)</span>
                    </label>
                    <select name="course_ids[]" multiple class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all h-40 text-sm">
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= $course['id'] ?>" <?= (in_array($course['id'], $editingUserCourses)) ? 'selected' : '' ?> class="py-1 px-2 rounded hover:bg-brand-light/20 cursor-pointer">
                                <?= htmlspecialchars($course['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Row 4: Buttons -->
                <div class="pt-4 border-t border-gray-100 flex items-center justify-end gap-3">
                    <?php if ($editingUser): ?>
                        <a href="users.php" class="px-6 py-2.5 rounded-xl font-bold text-gray-600 hover:bg-gray-100 transition-all text-sm">Cancelar</a>
                    <?php endif; ?>
                    <button type="submit" class="bg-[#1CBB9B] text-white px-8 py-2.5 rounded-xl font-bold hover:bg-[#169C80] transition-all shadow-sm hover:shadow-md text-sm transform active:scale-95">
                        <?= $editingUser ? 'Salvar Alterações' : 'Criar Usuário' ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- List -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-lg font-bold text-gray-800">Usuários Cadastrados</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50/50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Nome</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Função</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Curso</th>
                            <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <?php foreach ($users as $u): ?>
                            <tr class="hover:bg-gray-50/80 transition-colors group">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                    <div class="flex items-center">
                                        <div class="h-8 w-8 rounded-full bg-brand-light/20 flex items-center justify-center text-brand-dark font-bold mr-3 text-xs">
                                            <?= strtoupper(substr($u['name'], 0, 1)) ?>
                                        </div>
                                        <?= htmlspecialchars($u['name']) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($u['email']) ?>
                                    <?php if (!$u['receive_email']): ?>
                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800" title="Não recebe emails">
                                            <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <span class="px-2 py-1 rounded-lg bg-gray-100 text-gray-600 text-xs font-medium"><?= htmlspecialchars($u['role_name']) ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 max-w-xs truncate" title="<?= htmlspecialchars($u['course_names'] ?? '') ?>">
                                    <?= htmlspecialchars($u['course_names'] ?? '-') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <?php if ($u['id'] != $user['user_id']): ?>
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="users.php?edit=<?= $u['id'] ?>" class="text-blue-400 hover:text-blue-600 font-bold p-2 hover:bg-blue-50 rounded-lg transition-colors">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                            </a>
                                            <form method="POST" onsubmit="return confirm('Tem certeza que deseja excluir este usuário?');">
                                                <?= Csrf::field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="text-red-400 hover:text-red-600 font-bold p-2 hover:bg-red-50 rounded-lg transition-colors">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>


<?php require_once 'layout/footer.php'; ?>
