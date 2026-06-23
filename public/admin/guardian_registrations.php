<?php
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../src/Auth.php';
require_once '../../src/EmailService.php';
require_once '../../src/EmailTemplate.php';
require_once '../../src/CryptoHelper.php';

Auth::check();
$user = Auth::user();

$db   = new Database();
$conn = $db->getConnection();

$roleStmt = $conn->prepare("SELECT is_sysadmin, name FROM roles WHERE id = :id");
$roleStmt->execute([':id' => $user['user_role']]);
$roleData = $roleStmt->fetch(PDO::FETCH_ASSOC);
$isSysAdmin = (bool)$roleData['is_sysadmin'];
$roleName   = $roleData['name'];

// Acesso: SysAdmin ou Coord. Pedagógica
$allowedRoles = ['Coord. Pedagógica', 'SysAdmin'];
if (!$isSysAdmin && $roleName !== 'Coord. Pedagógica') {
    header('Location: dashboard.php');
    exit;
}

$flash = '';
$flashType = 'green';

// --- POST: aprovar ou rejeitar ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $regId  = (int)($_POST['reg_id'] ?? 0);
    $note   = trim($_POST['review_note'] ?? '');

    $regStmt = $conn->prepare("SELECT * FROM guardian_registrations WHERE id = :id AND status = 'pending' LIMIT 1");
    $regStmt->execute([':id' => $regId]);
    $reg = $regStmt->fetch(PDO::FETCH_ASSOC);

    if (!$reg) {
        $flash = 'Cadastro não encontrado ou já processado.';
        $flashType = 'red';
    } elseif ($action === 'approve') {
        $conn->beginTransaction();
        try {
            // Cria conta na tabela guardians
            $tempPassword = password_hash($reg['cpf_first5'], PASSWORD_BCRYPT);
            $insG = $conn->prepare("
                INSERT INTO guardians (name, email, cpf_hash, password, phone, active, must_change_password)
                VALUES (:name, :email, :cpf_hash, :pw, :phone, 1, 1)
                ON DUPLICATE KEY UPDATE active = 1, must_change_password = 1, password = :pw2
            ");
            $insG->execute([
                ':name'     => $reg['guardian_name'],
                ':email'    => $reg['guardian_email'],
                ':cpf_hash' => $reg['cpf_hash'],
                ':pw'       => $tempPassword,
                ':phone'    => $reg['guardian_phone'],
                ':pw2'      => $tempPassword,
            ]);
            $guardianId = $conn->lastInsertId() ?: (function() use ($conn, $reg) {
                $s = $conn->prepare("SELECT id FROM guardians WHERE email = :e");
                $s->execute([':e' => $reg['guardian_email']]);
                return $s->fetchColumn();
            })();

            // Cria registros de alunos
            $students = json_decode($reg['students_json'], true) ?? [];
            $insS = $conn->prepare("INSERT INTO guardian_students (guardian_id, student_name, student_matricula, verified) VALUES (:gid, :name, :matr, 1)");
            foreach ($students as $s) {
                $insS->execute([':gid' => $guardianId, ':name' => $s['name'], ':matr' => $s['matricula']]);
            }

            // Atualiza o registro
            $upd = $conn->prepare("
                UPDATE guardian_registrations
                SET status = 'approved', reviewed_by = :by, review_note = :note, reviewed_at = NOW(), guardian_id = :gid
                WHERE id = :id
            ");
            $upd->execute([':by' => $user['user_id'], ':note' => $note, ':gid' => $guardianId, ':id' => $regId]);

            // Zera cpf_first5 por segurança
            $conn->prepare("UPDATE guardian_registrations SET cpf_first5 = '' WHERE id = :id")->execute([':id' => $regId]);

            $conn->commit();

            // E-mail de boas-vindas
            $emailService = new EmailService($conn);
            $portalUrl = BASE_URL . '/responsaveis/login.php';
            $body = EmailTemplate::wrap("
                <h2 style='color:#006633;'>Acesso ao Portal de Responsáveis liberado!</h2>
                <p>Olá, <strong>" . htmlspecialchars($reg['guardian_name']) . "</strong>!</p>
                <p>Seu cadastro foi <strong>aprovado</strong> pela Coordenação Pedagógica do IFSC Câmpus Garopaba.</p>
                <p>Você já pode acessar o <strong>Portal de Responsáveis</strong> com as seguintes credenciais:</p>
                <table style='border-collapse:collapse;width:100%;font-size:14px;margin:12px 0;'>
                    <tr><td style='padding:8px 12px;background:#f9fafb;font-weight:bold;'>E-mail</td><td style='padding:8px 12px;'>" . htmlspecialchars($reg['guardian_email']) . "</td></tr>
                    <tr><td style='padding:8px 12px;background:#f9fafb;font-weight:bold;'>Senha temporária</td><td style='padding:8px 12px;'>Os 5 primeiros dígitos do seu CPF</td></tr>
                </table>
                <p><strong>Importante:</strong> No primeiro acesso você será obrigado a definir uma senha pessoal.</p>
                <p><a href='{$portalUrl}' style='background:#006633;color:white;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;margin-top:8px;'>Acessar o Portal</a></p>
                <p style='color:#666;font-size:12px;margin-top:16px;'>Se você não reconhece este cadastro, entre em contato com o câmpus.</p>
            ");
            $emailService->queue($reg['guardian_email'], $reg['guardian_name'], 'Acesso ao Portal de Responsáveis liberado — IFSC Garopaba', $body);

            $flash = 'Cadastro aprovado. Conta criada e e-mail enviado ao responsável.';
        } catch (Exception $e) {
            $conn->rollBack();
            $flash = 'Erro ao aprovar: ' . $e->getMessage();
            $flashType = 'red';
        }
    } elseif ($action === 'reject') {
        $upd = $conn->prepare("
            UPDATE guardian_registrations
            SET status = 'rejected', reviewed_by = :by, review_note = :note, reviewed_at = NOW()
            WHERE id = :id
        ");
        $upd->execute([':by' => $user['user_id'], ':note' => $note, ':id' => $regId]);

        // E-mail de rejeição
        $emailService = new EmailService($conn);
        $reasonText = $note ? htmlspecialchars($note) : 'Não informado.';
        $body = EmailTemplate::wrap("
            <h2 style='color:#991b1b;'>Cadastro de Responsável não aprovado</h2>
            <p>Olá, <strong>" . htmlspecialchars($reg['guardian_name']) . "</strong>!</p>
            <p>Após análise, seu cadastro no Portal de Responsáveis do IFSC Câmpus Garopaba <strong>não foi aprovado</strong>.</p>
            <p><strong>Motivo:</strong> {$reasonText}</p>
            <p>Caso tenha dúvidas, entre em contato diretamente com a Coordenação Pedagógica do câmpus.</p>
        ");
        $emailService->queue($reg['guardian_email'], $reg['guardian_name'], 'Cadastro de Responsável — IFSC Garopaba', $body);

        $flash = 'Cadastro rejeitado. E-mail enviado ao responsável.';
    }
}

// --- Listagem ---
$filterStatus = $_GET['status'] ?? 'pending';
$validStatuses = ['pending', 'approved', 'rejected'];
if (!in_array($filterStatus, $validStatuses)) $filterStatus = 'pending';

$listStmt = $conn->prepare("
    SELECT gr.*, u.name AS reviewer_name
    FROM guardian_registrations gr
    LEFT JOIN users u ON u.id = gr.reviewed_by
    WHERE gr.status = :status
    ORDER BY gr.created_at DESC
");
$listStmt->execute([':status' => $filterStatus]);
$registrations = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$counts = [];
foreach (['pending', 'approved', 'rejected'] as $s) {
    $c = $conn->prepare("SELECT COUNT(*) FROM guardian_registrations WHERE status = :s");
    $c->execute([':s' => $s]);
    $counts[$s] = (int)$c->fetchColumn();
}
?>
<?php require_once 'layout/header.php'; ?>
<?php require_once 'layout/sidebar.php'; ?>

<main class="flex-1 overflow-y-auto p-8 bg-[#F2F4F8]">
    <div class="mb-6 flex justify-between items-end">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Cadastros de Responsáveis</h1>
            <p class="text-gray-500 mt-1 text-sm">Análise de solicitações de acesso ao Portal de Responsáveis</p>
        </div>
    </div>

    <?php if ($flash): ?>
    <div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium border
        <?= $flashType === 'red' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-green-50 border-green-200 text-green-700' ?>">
        <?= htmlspecialchars($flash) ?>
    </div>
    <?php endif; ?>

    <!-- Filtros por status -->
    <div class="flex gap-2 mb-6">
        <?php foreach (['pending' => 'Pendentes', 'approved' => 'Aprovados', 'rejected' => 'Rejeitados'] as $s => $label): ?>
        <a href="?status=<?= $s ?>"
            class="px-4 py-2 rounded-lg text-sm font-semibold transition-colors
            <?= $filterStatus === $s ? 'bg-brand-DEFAULT text-white' : 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-200' ?>">
            <?= $label ?>
            <span class="ml-1 text-xs <?= $filterStatus === $s ? 'opacity-75' : 'text-gray-400' ?>">
                (<?= $counts[$s] ?>)
            </span>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($registrations)): ?>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-12 text-center text-gray-400 text-sm">
        Nenhum cadastro <?= $filterStatus === 'pending' ? 'pendente' : ($filterStatus === 'approved' ? 'aprovado' : 'rejeitado') ?>.
    </div>
    <?php else: ?>
    <div class="space-y-4">
        <?php foreach ($registrations as $reg):
            $students = json_decode($reg['students_json'], true) ?? [];
        ?>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 flex items-start justify-between gap-4 border-b border-gray-50">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="font-mono text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded">
                            <?= htmlspecialchars($reg['protocol_code']) ?>
                        </span>
                        <?php if ($reg['status'] === 'pending'): ?>
                        <span class="text-xs bg-amber-100 text-amber-700 font-semibold px-2 py-0.5 rounded-full">Pendente</span>
                        <?php elseif ($reg['status'] === 'approved'): ?>
                        <span class="text-xs bg-green-100 text-green-700 font-semibold px-2 py-0.5 rounded-full">Aprovado</span>
                        <?php else: ?>
                        <span class="text-xs bg-red-100 text-red-700 font-semibold px-2 py-0.5 rounded-full">Rejeitado</span>
                        <?php endif; ?>
                    </div>
                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($reg['guardian_name']) ?></p>
                    <p class="text-sm text-gray-500"><?= htmlspecialchars($reg['guardian_email']) ?></p>
                    <p class="text-xs text-gray-400 mt-0.5">Enviado em <?= date('d/m/Y H:i', strtotime($reg['created_at'])) ?></p>
                </div>
                <?php if ($reg['signed_pdf_path']): ?>
                <a href="guardian_pdf.php?id=<?= $reg['id'] ?>"
                    class="shrink-0 text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold px-3 py-1.5 rounded-lg transition-colors">
                    Ver PDF assinado
                </a>
                <?php endif; ?>
            </div>

            <div class="px-6 py-3">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Alunos informados</p>
                <div class="space-y-1">
                    <?php foreach ($students as $s): ?>
                    <div class="flex items-center gap-2 text-sm">
                        <span class="text-gray-800"><?= htmlspecialchars($s['name']) ?></span>
                        <span class="text-gray-400">·</span>
                        <span class="font-mono text-xs text-gray-500"><?= htmlspecialchars($s['matricula']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($reg['status'] === 'pending'): ?>
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
                <p class="text-xs font-bold text-gray-500 mb-2">
                    Verifique os dados acima no SIGAA antes de aprovar. O e-mail deve coincidir com o cadastrado como responsável.
                </p>
                <div class="flex gap-3 items-start">
                    <form method="POST" class="flex-1 flex gap-2 items-start">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="reg_id" value="<?= $reg['id'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <input type="text" name="review_note" placeholder="Observação (opcional)"
                            class="flex-1 bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 outline-none">
                        <button type="submit"
                            onclick="return confirm('Aprovar o cadastro de <?= htmlspecialchars(addslashes($reg['guardian_name']), ENT_QUOTES) ?>?')"
                            class="shrink-0 font-semibold text-sm px-4 py-2 rounded-lg transition-colors"
                            style="background:#16a34a;color:#fff;"
                            onmouseover="this.style.background='#15803d'" onmouseout="this.style.background='#16a34a'">
                            Aprovar
                        </button>
                    </form>
                    <form method="POST">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="reg_id" value="<?= $reg['id'] ?>">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="review_note" value="">
                        <button type="submit"
                            onclick="return confirm('Rejeitar o cadastro de <?= htmlspecialchars(addslashes($reg['guardian_name']), ENT_QUOTES) ?>?')"
                            class="shrink-0 bg-red-50 text-red-600 font-semibold text-sm px-4 py-2 rounded-lg border border-red-200 hover:bg-red-100 transition-colors">
                            Rejeitar
                        </button>
                    </form>
                </div>
            </div>
            <?php elseif ($reg['review_note']): ?>
            <div class="px-6 py-3 bg-gray-50 border-t border-gray-100 text-xs text-gray-500">
                <span class="font-semibold">Obs:</span> <?= htmlspecialchars($reg['review_note']) ?>
                <?php if ($reg['reviewer_name']): ?>
                — por <?= htmlspecialchars($reg['reviewer_name']) ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<?php require_once 'layout/footer.php'; ?>
