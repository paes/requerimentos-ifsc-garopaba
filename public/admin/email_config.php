<?php
/**
 * Tela de configuracao das credenciais e parametros do servidor SMTP para envio de e-mails.
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

// Lida com a Submissao do Formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = $_POST['host'];
    $port = $_POST['port'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $encryption = $_POST['encryption'];
    $from_email = $_POST['from_email'];
    $from_name = $_POST['from_name'];

    try {
        // Verifica se a configuracao existe
        $check = $conn->query("SELECT id FROM email_config LIMIT 1")->fetch();

        if ($check) {
            // Atualiza
            if (!empty($password)) {
                $stmt = $conn->prepare("UPDATE email_config SET host=?, port=?, username=?, password=?, encryption=?, from_email=?, from_name=? WHERE id=?");
                $stmt->execute([$host, $port, $username, $password, $encryption, $from_email, $from_name, $check['id']]);
            } else {
                $stmt = $conn->prepare("UPDATE email_config SET host=?, port=?, username=?, encryption=?, from_email=?, from_name=? WHERE id=?");
                $stmt->execute([$host, $port, $username, $encryption, $from_email, $from_name, $check['id']]);
            }
        } else {
            // Insere
            $stmt = $conn->prepare("INSERT INTO email_config (host, port, username, password, encryption, from_email, from_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$host, $port, $username, $password, $encryption, $from_email, $from_name]);
        }
        $message = 'Configurações de email salvas com sucesso!';
    } catch (PDOException $e) {
        $error = 'Erro ao salvar configurações: ' . $e->getMessage();
    }
}

// Busca Configuracao Atual
$config = $conn->query("SELECT * FROM email_config LIMIT 1")->fetch(PDO::FETCH_ASSOC);

?>
<?php require_once 'layout/header.php'; ?>
<?php require_once 'layout/sidebar.php'; ?>

<main class="flex-1 overflow-y-auto p-8 bg-[#F2F4F8]">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 tracking-tight">Configuração de Email</h1>
        <p class="text-gray-500 mt-1">Configure as credenciais SMTP para envio de notificações.</p>
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

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 max-w-3xl">
        <form method="POST" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Servidor SMTP (Host)</label>
                    <input type="text" name="host" value="<?= htmlspecialchars($config['host'] ?? '') ?>" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all" placeholder="smtp.gmail.com">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Porta</label>
                    <input type="number" name="port" value="<?= htmlspecialchars($config['port'] ?? '587') ?>" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all" placeholder="587">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Usuário SMTP</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($config['username'] ?? '') ?>" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all" placeholder="seu-email@gmail.com">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Senha SMTP</label>
                    <input type="password" name="password" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all" placeholder="<?= $config ? 'Deixe em branco para manter a atual' : 'Senha do email/app' ?>" <?= $config ? '' : 'required' ?>>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Criptografia</label>
                <select name="encryption" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all">
                    <option value="tls" <?= ($config['encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                    <option value="ssl" <?= ($config['encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                    <option value="none" <?= ($config['encryption'] ?? '') === 'none' ? 'selected' : '' ?>>Nenhuma</option>
                </select>
            </div>

            <div class="border-t border-gray-100 pt-6 mt-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Configurações de Remetente</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email do Remetente</label>
                        <input type="email" name="from_email" value="<?= htmlspecialchars($config['from_email'] ?? '') ?>" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all" placeholder="no-reply@ifsc.edu.br">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nome do Remetente</label>
                        <input type="text" name="from_name" value="<?= htmlspecialchars($config['from_name'] ?? '') ?>" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all" placeholder="Sistema de Requisições IFSC">
                    </div>
                </div>
            </div>

            <div class="pt-4">
                <button type="submit" class="bg-[#1CBB9B] text-white px-8 py-3 rounded-xl font-bold hover:bg-[#169C80] transition-all shadow-sm hover:shadow-md flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                    Salvar Configurações
                </button>
            </div>
        </form>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 max-w-3xl mt-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Fila de Emails</h3>
        <p class="text-gray-500 mb-4">Emails são enviados em segundo plano. Se houver problemas, você pode tentar processar a fila manualmente.</p>
        
        <?php
        // Obtem Estatisticas da Fila
        $queueStats = $conn->query("SELECT status, COUNT(*) as count FROM email_queue GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
        ?>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-gray-50 p-3 rounded-xl border border-gray-100 text-center">
                <span class="block text-2xl font-bold text-gray-800"><?= $queueStats['pending'] ?? 0 ?></span>
                <span class="text-xs text-gray-500 uppercase font-bold">Pendentes</span>
            </div>
            <div class="bg-blue-50 p-3 rounded-xl border border-blue-100 text-center">
                <span class="block text-2xl font-bold text-blue-700"><?= $queueStats['sending'] ?? 0 ?></span>
                <span class="text-xs text-blue-600 uppercase font-bold">Enviando</span>
            </div>
            <div class="bg-green-50 p-3 rounded-xl border border-green-100 text-center">
                <span class="block text-2xl font-bold text-green-700"><?= $queueStats['sent'] ?? 0 ?></span>
                <span class="text-xs text-green-600 uppercase font-bold">Enviados</span>
            </div>
            <div class="bg-red-50 p-3 rounded-xl border border-red-100 text-center">
                <span class="block text-2xl font-bold text-red-700"><?= $queueStats['failed'] ?? 0 ?></span>
                <span class="text-xs text-red-600 uppercase font-bold">Falhas</span>
            </div>
        </div>

        <form method="POST" action="process_queue_manual.php" target="_blank">
            <button type="submit" class="bg-gray-800 text-white px-6 py-2 rounded-xl font-bold hover:bg-gray-700 transition-all shadow-sm hover:shadow-md flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                Processar Fila Agora
            </button>
        </form>
    </div>
</main>

<?php require_once 'layout/footer.php'; ?>
