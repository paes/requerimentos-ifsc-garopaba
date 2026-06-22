<?php
/**
 * Tela para o usuario administrador visualizar e atualizar seu proprio perfil e senha.
 *
 * @author Prof. Eduardo Gomes
 */
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../src/Auth.php';
require_once '../../src/Helpers.php';

Auth::check();
$userSession = Auth::user();

$db = new Database();
$conn = $db->getConnection();

$message = '';
$error = '';

// Lida com a Submissao do Formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receive_email = isset($_POST['receive_email']) ? 1 : 0;
    
    try {
        $stmt = $conn->prepare("UPDATE users SET receive_email = :receive_email WHERE id = :id");
        $stmt->execute([':receive_email' => $receive_email, ':id' => $userSession['user_id']]);

        $message = 'Perfil atualizado com sucesso!';
    } catch (Exception $e) {
        $error = 'Erro: ' . $e->getMessage();
    }
}

// Busca Dados do Usuario
$stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $userSession['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Busca Nome do Perfil
$roleStmt = $conn->prepare("SELECT name FROM roles WHERE id = :id");
$roleStmt->execute([':id' => $user['role_id']]);
$roleName = $roleStmt->fetchColumn();

?>
<?php require_once 'layout/header.php'; ?>
<?php require_once 'layout/sidebar.php'; ?>

<main class="flex-1 overflow-y-auto p-8 bg-[#F2F4F8]">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 tracking-tight">Meu Perfil</h1>
        <p class="text-gray-500 mt-1">Gerencie suas informações e preferências.</p>
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

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 max-w-2xl">
        <div class="flex items-center mb-8 pb-8 border-b border-gray-100">
            <div class="h-20 w-20 rounded-full bg-brand-light/20 flex items-center justify-center text-brand-dark font-bold text-3xl mr-6">
                <?= strtoupper(substr($user['name'], 0, 1)) ?>
            </div>
            <div>
                <h2 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($user['name']) ?></h2>
                <p class="text-gray-500"><?= htmlspecialchars($user['email']) ?></p>
                <span class="inline-block mt-2 px-3 py-1 rounded-lg bg-gray-100 text-gray-600 text-xs font-medium"><?= htmlspecialchars($roleName) ?></span>
            </div>
        </div>

        <form method="POST" class="space-y-6">
            <?= Csrf::field() ?>
            <div>
                <h3 class="text-lg font-bold text-gray-800 mb-4">Preferências</h3>
                <label class="flex items-center cursor-pointer p-4 border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors">
                    <input type="checkbox" name="receive_email" value="1" <?= $user['receive_email'] ? 'checked' : '' ?> class="form-checkbox h-5 w-5 text-[#1CBB9B] rounded border-gray-300 focus:ring-[#1CBB9B]">
                    <div class="ml-3">
                        <span class="block text-sm font-medium text-gray-900">Receber Notificações por Email</span>
                        <span class="block text-xs text-gray-500">Você receberá emails sobre atualizações em suas requisições.</span>
                    </div>
                </label>
            <div class="pt-6">
                <button type="submit" class="w-full bg-[#1CBB9B] text-white px-6 py-3 rounded-xl font-bold hover:bg-[#169C80] transition-all shadow-sm hover:shadow-md">
                    Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</main>

<?php require_once 'layout/footer.php'; ?>
