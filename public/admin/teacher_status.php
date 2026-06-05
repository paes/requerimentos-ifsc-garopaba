<?php
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../src/Auth.php';

Auth::check();
$user = Auth::user();

$db   = new Database();
$conn = $db->getConnection();

$roleId = $user['user_role'] ?? $user['role_id'];
$scheduleRoles = [6, 14];
$roleStmt = $conn->prepare("SELECT is_sysadmin FROM roles WHERE id = :id");
$roleStmt->execute([':id' => $roleId]);
$isSysAdmin = (bool)$roleStmt->fetchColumn();
if (!$isSysAdmin && !in_array((int)$roleId, $scheduleRoles)) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error   = '';

$stmtSems = $conn->query("SELECT DISTINCT semester FROM schedule_slots ORDER BY semester DESC");
$semesters = $stmtSems->fetchAll(PDO::FETCH_COLUMN);

$semester = trim($_GET['semester'] ?? $_POST['semester'] ?? ($semesters[0] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $postSem = trim($_POST['semester'] ?? '');
    if (!preg_match('/^\d{4}\.[12]$/', $postSem)) {
        $error = 'Semestre inválido.';
    } else {
        try {
            $conn->beginTransaction();
            $stmtDel = $conn->prepare("DELETE FROM teacher_inactive_semesters WHERE semester = :s");
            $stmtDel->execute([':s' => $postSem]);

            $stmtIns = $conn->prepare("
                INSERT INTO teacher_inactive_semesters (teacher_id, teacher_name, semester, reason)
                VALUES (:tid, :tname, :s, :reason)
            ");
            $stmtTN = $conn->prepare("SELECT name FROM teachers WHERE id = :id AND active = 1");
            foreach ($_POST['inactive_id'] ?? [] as $tid) {
                $tid = (int)$tid;
                if ($tid <= 0) continue;
                $stmtTN->execute([':id' => $tid]);
                $tname = $stmtTN->fetchColumn();
                if (!$tname) continue;
                $reason = trim($_POST['reason'][$tid] ?? '');
                $stmtIns->execute([
                    ':tid'    => $tid,
                    ':tname'  => mb_strtoupper($tname),
                    ':s'      => $postSem,
                    ':reason' => $reason ?: null,
                ]);
            }
            $conn->commit();
            $message  = 'Situação dos docentes salva para ' . htmlspecialchars($postSem) . '.';
            $semester = $postSem;
        } catch (Exception $e) {
            $conn->rollBack();
            $error = 'Erro ao salvar: ' . $e->getMessage();
        }
    }
}

$stmtTeachers = $conn->query("SELECT id, name FROM teachers WHERE active = 1 ORDER BY name");
$teachers = $stmtTeachers->fetchAll(PDO::FETCH_ASSOC);

$inactiveMap = [];
if ($semester) {
    $stmtInact = $conn->prepare("SELECT teacher_name, reason FROM teacher_inactive_semesters WHERE semester = :s");
    $stmtInact->execute([':s' => $semester]);
    foreach ($stmtInact->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $inactiveMap[mb_strtoupper($r['teacher_name'])] = $r['reason'] ?? '';
    }
}

$currentPage = 'teacher_status.php';
?>
<?php require_once 'layout/header.php'; ?>
<?php require_once 'layout/sidebar.php'; ?>

<main class="flex-1 overflow-y-auto p-8 bg-[#F2F4F8]">

    <div class="mb-6 flex justify-between items-end">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 tracking-tight">Situação dos Docentes</h1>
            <p class="text-gray-500 mt-1">Marque quem esteve inativo em cada semestre para não penalizar a média histórica.</p>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6 text-sm font-medium flex items-center shadow-sm">
        <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 text-sm font-medium flex items-center shadow-sm">
        <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- Semester selector -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6 flex items-center gap-4">
        <label class="text-sm font-medium text-gray-700">Semestre:</label>
        <div class="flex gap-2">
            <?php foreach ($semesters as $sem): ?>
            <a href="?semester=<?= urlencode($sem) ?>"
               class="px-4 py-2 rounded-lg text-sm font-semibold border transition
                      <?= $semester === $sem
                          ? 'bg-brand-DEFAULT text-white border-brand-DEFAULT shadow'
                          : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50' ?>">
                <?= htmlspecialchars($sem) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php if (empty($semesters)): ?>
        <span class="text-sm text-gray-400">Nenhum semestre encontrado. Importe uma grade primeiro.</span>
        <?php endif; ?>
    </div>

    <!-- Info banner -->
    <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-xl mb-6 text-sm flex items-start gap-2">
        <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <span>Docentes marcados como inativos <strong>não entram no divisor da média histórica</strong> na Equidade de Horários. Use para licenças, substituições ou afastamentos.</span>
    </div>

    <?php if ($semester): ?>
    <form method="POST">
        <input type="hidden" name="semester" value="<?= htmlspecialchars($semester) ?>">
        <input type="hidden" name="save" value="1">

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <h2 class="font-semibold text-gray-700">Docentes ativos — <?= htmlspecialchars($semester) ?></h2>
                <button type="submit"
                        class="px-5 py-2 bg-brand-DEFAULT text-white text-sm font-semibold rounded-lg hover:bg-brand-dark transition shadow-sm">
                    Salvar
                </button>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-3 text-left w-12">Inativo</th>
                        <th class="px-6 py-3 text-left">Nome</th>
                        <th class="px-6 py-3 text-left">Motivo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($teachers as $t):
                        $tKey      = mb_strtoupper($t['name']);
                        $isInact   = isset($inactiveMap[$tKey]);
                        $reason    = $inactiveMap[$tKey] ?? '';
                    ?>
                    <tr class="hover:bg-gray-50 <?= $isInact ? 'bg-amber-50' : '' ?>" id="row-<?= $t['id'] ?>">
                        <td class="px-6 py-3">
                            <input type="checkbox" name="inactive_id[]" value="<?= $t['id'] ?>"
                                   id="cb-<?= $t['id'] ?>"
                                   <?= $isInact ? 'checked' : '' ?>
                                   onchange="toggleReason(<?= $t['id'] ?>, this.checked)"
                                   class="w-4 h-4 rounded border-gray-300 text-amber-500 focus:ring-amber-400">
                        </td>
                        <td class="px-6 py-3 font-medium text-gray-800">
                            <?= htmlspecialchars($t['name']) ?>
                        </td>
                        <td class="px-6 py-3">
                            <input type="text"
                                   name="reason[<?= $t['id'] ?>]"
                                   value="<?= htmlspecialchars($reason) ?>"
                                   placeholder="Ex: licença saúde, substituição..."
                                   id="reason-<?= $t['id'] ?>"
                                   <?= $isInact ? '' : 'disabled' ?>
                                   class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-300 disabled:opacity-30 disabled:cursor-not-allowed transition">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="px-6 py-4 border-t border-gray-100 flex justify-end">
                <button type="submit"
                        class="px-5 py-2 bg-brand-DEFAULT text-white text-sm font-semibold rounded-lg hover:bg-brand-dark transition shadow-sm">
                    Salvar
                </button>
            </div>
        </div>
    </form>
    <?php else: ?>
    <div class="text-center text-gray-400 py-16">Selecione um semestre para gerenciar a situação dos docentes.</div>
    <?php endif; ?>

</main>

<script>
function toggleReason(teacherId, checked) {
    const row    = document.getElementById('row-' + teacherId);
    const input  = document.getElementById('reason-' + teacherId);
    input.disabled = !checked;
    if (checked) {
        row.classList.add('bg-amber-50');
        row.classList.remove('hover:bg-gray-50');
        input.focus();
    } else {
        row.classList.remove('bg-amber-50');
        row.classList.add('hover:bg-gray-50');
        input.value = '';
    }
}
</script>

<?php require_once 'layout/footer.php'; ?>
