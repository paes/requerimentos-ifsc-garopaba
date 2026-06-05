<?php
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../src/Auth.php';

Auth::check();
$user = Auth::user();

$db     = new Database();
$conn   = $db->getConnection();
$roleStmt = $conn->prepare("SELECT is_sysadmin FROM roles WHERE id = :id");
$roleStmt->execute([':id' => $user['user_role'] ?? $user['role_id']]);
if (!$roleStmt->fetchColumn()) {
    header('Location: dashboard.php');
    exit;
}

$logDir = dirname(__DIR__, 2) . '/storage/email_log';

// Ação: apagar todos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear') {
    foreach (glob("$logDir/*.html") as $f) unlink($f);
    header('Location: email_log.php');
    exit;
}

$files = array_reverse(glob("$logDir/*.html") ?: []);
$preview = null;
if (isset($_GET['view'])) {
    $safe = basename($_GET['view']);
    $path = "$logDir/$safe";
    if (file_exists($path) && str_ends_with($safe, '.html')) {
        $preview = ['name' => $safe, 'html' => file_get_contents($path)];
    }
}
?>
<?php require_once 'layout/header.php'; ?>
<?php require_once 'layout/sidebar.php'; ?>

<main class="flex-1 overflow-y-auto p-8 bg-[#F2F4F8] dark:bg-gray-900">

    <div class="mb-6 flex justify-between items-end">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100 tracking-tight">Log de E-mails (Dev)</h1>
            <p class="text-gray-500 dark:text-gray-400 mt-1">E-mails simulados em modo de desenvolvimento (ENABLE_EMAILS = false)</p>
        </div>
        <?php if (!empty($files)): ?>
        <form method="POST" onsubmit="return confirm('Apagar todos os e-mails do log?');">
            <input type="hidden" name="action" value="clear">
            <button type="submit" class="bg-red-50 border border-red-200 text-red-600 px-4 py-2 rounded-xl text-sm font-bold hover:bg-red-100 transition-colors">
                Limpar log
            </button>
        </form>
        <?php endif; ?>
    </div>

    <?php if (defined('ENABLE_EMAILS') && ENABLE_EMAILS): ?>
    <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-xl mb-6 text-sm font-medium">
        ENABLE_EMAILS está ativo — os e-mails estão sendo enviados de verdade. Esta página só registra simulações de desenvolvimento.
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Lista de arquivos -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                <h3 class="font-bold text-gray-800 dark:text-gray-100 text-sm">
                    <?= count($files) ?> e-mail(s) registrado(s)
                </h3>
            </div>
            <?php if (empty($files)): ?>
            <p class="px-5 py-8 text-center text-gray-400 text-sm">Nenhum e-mail registrado ainda.<br>Realize uma ação que dispare notificação.</p>
            <?php else: ?>
            <ul class="divide-y divide-gray-50 dark:divide-gray-700">
                <?php foreach ($files as $f):
                    $name    = basename($f);
                    $parts   = explode('_', $name, 4); // Ymd, His, email.html
                    $dateStr = isset($parts[0], $parts[1])
                        ? date('d/m H:i:s', mktime(
                            (int)substr($parts[1],0,2), (int)substr($parts[1],2,2), (int)substr($parts[1],4,2),
                            (int)substr($parts[0],4,2), (int)substr($parts[0],6,2), (int)substr($parts[0],0,4)
                          ))
                        : '';
                    // Parsear TO e SUBJECT do bloco meta embutido no arquivo
                    $head    = file_get_contents($f, false, null, 0, 800);
                    $to      = '';
                    $subject = '';
                    if (preg_match('/>TO:<\/strong>\s*(.+?)<br>/i', $head, $m)) {
                        $to = htmlspecialchars_decode(strip_tags($m[1]));
                    }
                    if (preg_match('/>SUBJECT:<\/strong>\s*(.+?)<\/div>/i', $head, $m)) {
                        $subject = htmlspecialchars_decode(strip_tags($m[1]));
                    }
                    $active = ($preview['name'] ?? '') === $name;
                ?>
                <li>
                    <a href="email_log.php?view=<?= urlencode($name) ?>"
                        class="flex flex-col px-5 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors <?= $active ? 'bg-blue-50 dark:bg-blue-900/20 border-l-2 border-blue-500' : '' ?>">
                        <span class="text-xs text-gray-400 font-mono"><?= $dateStr ?></span>
                        <span class="text-sm text-gray-700 dark:text-gray-300 font-medium truncate" title="<?= htmlspecialchars($subject) ?>">
                            <?= htmlspecialchars($subject ?: $name) ?>
                        </span>
                        <?php if ($to): ?>
                        <span class="text-xs text-gray-400 truncate"><?= htmlspecialchars($to) ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <!-- Preview -->
        <div class="lg:col-span-2">
            <?php if ($preview): ?>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                    <span class="text-xs font-mono text-gray-400"><?= htmlspecialchars($preview['name']) ?></span>
                </div>
                <iframe srcdoc="<?= htmlspecialchars($preview['html']) ?>"
                    class="w-full border-0 rounded-b-2xl"
                    style="height:600px;"
                    sandbox="allow-same-origin"></iframe>
            </div>
            <?php elseif (!empty($files)): ?>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 flex items-center justify-center" style="height:400px;">
                <p class="text-gray-400 text-sm">Selecione um e-mail à esquerda para visualizar</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

</main>

<?php require_once 'layout/footer.php'; ?>
