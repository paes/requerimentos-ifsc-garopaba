<?php
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../src/Auth.php';
require_once '../../src/EmailService.php';
require_once '../../src/EmailTemplate.php';
require_once '../../src/AscXmlParser.php';

Auth::check();
$user = Auth::user();

$db   = new Database();
$conn = $db->getConnection();

// Determinar role e permissões
$stmtRole = $conn->prepare("SELECT name, is_course_bound, is_sysadmin FROM roles WHERE id = :id");
$stmtRole->execute([':id' => $user['user_role']]);
$roleData      = $stmtRole->fetch(PDO::FETCH_ASSOC);
$roleName      = $roleData['name'];
$isSysAdmin    = (bool)$roleData['is_sysadmin'];
$isCourseBound = (bool)$roleData['is_course_bound'];

// Roles autorizados: Sysadmin, Coordenador (id=2), Assessoria DEPE (id=14), DEPE (id=6)
$allowedRoles = [1, 2, 6, 14]; // ids dos roles acima
$allowedRoleNames = ['Administrador do Sistema', 'Coordenador de Curso', 'DEPE', 'Assessoria DEPE'];

if (!$isSysAdmin && !in_array((int)$user['user_role'], $allowedRoles)) {
    header('Location: dashboard.php');
    exit;
}

$userCourses = array_map('intval', $user['user_courses'] ?? []);

// Qual step o usuário atual gerencia?
$userStep = null;
if ($isSysAdmin) {
    $userStep = null; // vê todos
} elseif ((int)$user['user_role'] === 2) {
    $userStep = 1; // Coordenador de Curso
} elseif ((int)$user['user_role'] === 14) {
    $userStep = 2; // Assessoria DEPE
} elseif ((int)$user['user_role'] === 6) {
    $userStep = 3; // DEPE
}

// --- Processamento de ação (aprovar/rejeitar) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['request_id'])) {
    $reqId      = (int)$_POST['request_id'];
    $action     = in_array($_POST['action'], ['approve', 'reject', 'comment']) ? $_POST['action'] : null;
    $observation = trim($_POST['observation'] ?? '');

    if ($action && $reqId) {
        // Buscar request para validação
        $stmtR = $conn->prepare("SELECT tr.*, c.name AS course_name FROM teacher_requests tr JOIN courses c ON c.id = tr.course_id WHERE tr.id = :id");
        $stmtR->execute([':id' => $reqId]);
        $reqData = $stmtR->fetch(PDO::FETCH_ASSOC);

        $canAct = false;
        if ($reqData && $reqData['status'] === 'pending') {
            if ($isSysAdmin) {
                $canAct = true;
            } elseif ($userStep !== null && $reqData['current_step_order'] === $userStep) {
                if ($isCourseBound) {
                    $canAct = in_array((int)$reqData['course_id'], $userCourses);
                } else {
                    $canAct = true;
                }
            }
        }

        if ($canAct) {
            $conn->beginTransaction();
            try {
                // Registrar histórico
                $stmtH = $conn->prepare("
                    INSERT INTO teacher_request_history (teacher_request_id, user_id, action, observation)
                    VALUES (:rid, :uid, :action, :obs)
                ");
                $stmtH->execute([
                    ':rid'    => $reqId,
                    ':uid'    => $user['user_id'],
                    ':action' => $action,
                    ':obs'    => $observation ?: null,
                ]);

                $emailService = new EmailService($conn);
                $dates = json_decode($reqData['absence_dates'] ?? '[]', true);
                $datesStr = implode(', ', array_map(fn($d) => date('d/m/Y', strtotime($d)), $dates));
                $adminUrl = BASE_URL . '/admin/teacher_requests.php';

                if ($action === 'approve') {
                    $nextStep = $reqData['current_step_order'] + 1;

                    if ($nextStep > 3) {
                        // Aprovação final — DEPE aprovou
                        $stmtUpd = $conn->prepare("UPDATE teacher_requests SET status = 'approved', current_step_order = 3, updated_at = NOW() WHERE id = :id");
                        $stmtUpd->execute([':id' => $reqId]);

                        // Notificar docente
                        $approvedBody = EmailTemplate::wrap('
                            <p>Olá, <strong>' . htmlspecialchars($reqData['teacher_name']) . '</strong>!</p>
                            <p>Sua solicitação de substituição de aulas foi <strong style="color:#16a34a;">aprovada</strong>.</p>
                            <table style="width:100%;border-collapse:collapse;margin:20px 0;font-size:13px;">
                                <tr style="background:#f9fafb;">
                                    <td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;width:40%;">Protocolo</td>
                                    <td style="padding:8px 12px;border:1px solid #e5e7eb;font-family:monospace;">' . $reqData['protocol_code'] . '</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Curso</td>
                                    <td style="padding:8px 12px;border:1px solid #e5e7eb;">' . htmlspecialchars($reqData['course_name']) . '</td>
                                </tr>
                                <tr style="background:#f9fafb;">
                                    <td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Data(s)</td>
                                    <td style="padding:8px 12px;border:1px solid #e5e7eb;">' . htmlspecialchars($datesStr) . '</td>
                                </tr>
                            </table>
                            ' . ($observation ? '<p><strong>Observação:</strong> ' . nl2br(htmlspecialchars($observation)) . '</p>' : '') . '
                            <p style="color:#6b7280;font-size:13px;">Em breve a coordenação entrará em contato com informações sobre o substituto.</p>
                        ');
                        $emailService->queue($reqData['teacher_email'], $reqData['teacher_name'], 'Solicitação de substituição aprovada — ' . $reqData['protocol_code'], $approvedBody);

                        // Notificar Coordenadoria Pedagógica (role_id = 10)
                        $stmtCP = $conn->prepare("SELECT name, email FROM users WHERE role_id = 10 AND receive_email = 1");
                        $stmtCP->execute();
                        foreach ($stmtCP->fetchAll(PDO::FETCH_ASSOC) as $cp) {
                            $cpBody = EmailTemplate::wrap('
                                <p>Olá, <strong>' . htmlspecialchars($cp['name']) . '</strong>!</p>
                                <p>Para sua ciência, a solicitação de substituição de aulas <strong>' . $reqData['protocol_code'] . '</strong> foi aprovada pelo DEPE.</p>
                                <p>Docente: ' . htmlspecialchars($reqData['teacher_name']) . ' | Curso: ' . htmlspecialchars($reqData['course_name']) . ' | Data(s): ' . htmlspecialchars($datesStr) . '</p>
                            ');
                            $emailService->queue($cp['email'], $cp['name'], 'Substituição aprovada — ' . $reqData['protocol_code'], $cpBody);
                        }
                    } else {
                        // Avança para o próximo step
                        $stmtUpd = $conn->prepare("UPDATE teacher_requests SET current_step_order = :step, updated_at = NOW() WHERE id = :id");
                        $stmtUpd->execute([':step' => $nextStep, ':id' => $reqId]);

                        // Determinar próximo role e notificar
                        $nextRoles = [2 => 14, 3 => 6]; // step 2 = Assessoria DEPE, step 3 = DEPE
                        $nextRoleId = $nextRoles[$nextStep] ?? null;
                        if ($nextRoleId) {
                            $stmtNext = $conn->prepare("SELECT name, email FROM users WHERE role_id = :rid AND receive_email = 1");
                            $stmtNext->execute([':rid' => $nextRoleId]);
                            foreach ($stmtNext->fetchAll(PDO::FETCH_ASSOC) as $nextUser) {
                                $nextBody = EmailTemplate::wrap('
                                    <p>Olá, <strong>' . htmlspecialchars($nextUser['name']) . '</strong>!</p>
                                    <p>Uma solicitação de substituição de aulas aguarda sua análise:</p>
                                    <p>Protocolo: <strong>' . $reqData['protocol_code'] . '</strong> | Docente: ' . htmlspecialchars($reqData['teacher_name']) . ' | Curso: ' . htmlspecialchars($reqData['course_name']) . '</p>
                                    <p><a href="' . $adminUrl . '" style="display:inline-block;background:#1CBB9B;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;font-weight:bold;">Analisar</a></p>
                                ');
                                $emailService->queue($nextUser['email'], $nextUser['name'], 'Solicitação de substituição aguarda análise — ' . $reqData['protocol_code'], $nextBody);
                            }
                        }
                    }
                } elseif ($action === 'reject') {
                    $stmtUpd = $conn->prepare("UPDATE teacher_requests SET status = 'rejected', updated_at = NOW() WHERE id = :id");
                    $stmtUpd->execute([':id' => $reqId]);

                    // Notificar docente
                    $rejBody = EmailTemplate::wrap('
                        <p>Olá, <strong>' . htmlspecialchars($reqData['teacher_name']) . '</strong>!</p>
                        <p>Sua solicitação de substituição de aulas foi <strong style="color:#dc2626;">indeferida</strong>.</p>
                        <p>Protocolo: <strong>' . $reqData['protocol_code'] . '</strong></p>
                        ' . ($observation ? '<p><strong>Motivo:</strong> ' . nl2br(htmlspecialchars($observation)) . '</p>' : '') . '
                        <p style="color:#6b7280;font-size:13px;">Em caso de dúvidas, entre em contato com a coordenação do curso.</p>
                    ');
                    $emailService->queue($reqData['teacher_email'], $reqData['teacher_name'], 'Solicitação de substituição indeferida — ' . $reqData['protocol_code'], $rejBody);
                }

                $conn->commit();
                header('Location: teacher_requests.php?ok=1');
                exit;
            } catch (Exception $e) {
                $conn->rollBack();
            }
        }
    }
}

// --- Listagem ---
$filterStatus = $_GET['status'] ?? 'pending';
$filterCourse = (int)($_GET['course'] ?? 0);

$q = "
    SELECT tr.*, c.name AS course_name, t_sug.name AS suggested_teacher_name
    FROM teacher_requests tr
    JOIN courses c ON c.id = tr.course_id
    LEFT JOIN teachers t_sug ON t_sug.id = tr.suggested_teacher_id
    WHERE 1=1
";
$p = [];

// Filtro por role
if (!$isSysAdmin) {
    if ($userStep === 1 && $isCourseBound && !empty($userCourses)) {
        $inList = implode(',', $userCourses);
        $q .= " AND tr.course_id IN ($inList) AND tr.current_step_order = 1";
    } elseif ($userStep !== null) {
        $q .= " AND tr.current_step_order = :step";
        $p[':step'] = $userStep;
    }
}

if ($filterStatus) {
    $q .= " AND tr.status = :status";
    $p[':status'] = $filterStatus;
}
if ($filterCourse > 0 && $isSysAdmin) {
    $q .= " AND tr.course_id = :course";
    $p[':course'] = $filterCourse;
}

$q .= " ORDER BY tr.created_at DESC";

$stmtList = $conn->prepare($q);
$stmtList->execute($p);
$requests = $stmtList->fetchAll(PDO::FETCH_ASSOC);

// Cursos para filtro (sysadmin)
$courses = [];
if ($isSysAdmin) {
    $stmtCr = $conn->prepare("SELECT id, name FROM courses WHERE active = 1 ORDER BY name");
    $stmtCr->execute();
    $courses = $stmtCr->fetchAll(PDO::FETCH_ASSOC);
}

$currentMonth    = (int)date('n');
$currentYear     = date('Y');
$currentSemester = $currentYear . '.' . ($currentMonth >= 2 && $currentMonth <= 7 ? '1' : '2');

$statusLabels = [
    'pending'   => ['label' => 'Em análise',  'class' => 'bg-yellow-100 text-yellow-800'],
    'approved'  => ['label' => 'Aprovado',    'class' => 'bg-green-100 text-green-800'],
    'rejected'  => ['label' => 'Indeferido',  'class' => 'bg-red-100 text-red-800'],
    'concluded' => ['label' => 'Concluído',   'class' => 'bg-blue-100 text-blue-800'],
];

$stepLabels = [
    1 => 'Coord. de Curso',
    2 => 'Assessoria DEPE',
    3 => 'DEPE',
];

$pageTitle = 'Substituição de Aulas — Docentes';
require_once 'layout/header.php';
require_once 'layout/sidebar.php';
?>

<main class="flex-1 overflow-y-auto p-8">
    <div class="max-w-6xl mx-auto">

        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Substituição de Aulas — Docentes</h1>
            <p class="text-sm text-gray-500 mt-1">Requerimentos de substituição submetidos pelos docentes do câmpus</p>
        </div>

        <?php if (isset($_GET['ok'])): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 text-sm">
                Ação registrada com sucesso.
            </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
            <form method="GET" class="flex flex-wrap gap-3 items-end">
                <div class="flex-1 min-w-[160px]">
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wider">Status</label>
                    <select name="status" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-DEFAULT outline-none bg-gray-50">
                        <option value="" <?= $filterStatus === '' ? 'selected' : '' ?>>Todos</option>
                        <option value="pending"   <?= $filterStatus === 'pending'   ? 'selected' : '' ?>>Em análise</option>
                        <option value="approved"  <?= $filterStatus === 'approved'  ? 'selected' : '' ?>>Aprovados</option>
                        <option value="rejected"  <?= $filterStatus === 'rejected'  ? 'selected' : '' ?>>Indeferidos</option>
                        <option value="concluded" <?= $filterStatus === 'concluded' ? 'selected' : '' ?>>Concluídos</option>
                    </select>
                </div>
                <?php if ($isSysAdmin && !empty($courses)): ?>
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wider">Curso</label>
                    <select name="course" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-DEFAULT outline-none bg-gray-50">
                        <option value="">Todos os cursos</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $filterCourse === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div>
                    <button type="submit" class="px-4 py-2 bg-brand-DEFAULT text-white text-sm font-medium rounded-lg hover:bg-brand-dark transition-colors">
                        Filtrar
                    </button>
                </div>
            </form>
        </div>

        <!-- Listagem -->
        <?php if (empty($requests)): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
                <p class="text-gray-400 text-sm">Nenhuma solicitação encontrada com os filtros selecionados.</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($requests as $req): ?>
                    <?php
                    $st = $statusLabels[$req['status']] ?? ['label' => $req['status'], 'class' => 'bg-gray-100 text-gray-700'];
                    $dates = json_decode($req['absence_dates'] ?? '[]', true);
                    $datesStr = implode(', ', array_map(fn($d) => date('d/m/Y', strtotime($d)), $dates));
                    $timeSlots = json_decode($req['time_slots'] ?? '[]', true);
                    $slotLabels = AscXmlParser::TIME_SLOT_LABELS;
                    $slotsStr = implode(', ', array_map(fn($s) => $slotLabels[$s] ?? $s, $timeSlots));

                    // Pode agir neste request?
                    $canActNow = false;
                    if ($req['status'] === 'pending') {
                        if ($isSysAdmin) {
                            $canActNow = true;
                        } elseif ($userStep !== null && $req['current_step_order'] === $userStep) {
                            if ($isCourseBound) {
                                $canActNow = in_array((int)$req['course_id'], $userCourses);
                            } else {
                                $canActNow = true;
                            }
                        }
                    }
                    ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="p-6">
                            <div class="flex items-start justify-between gap-4 flex-wrap">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-2 flex-wrap">
                                        <span class="font-mono text-xs font-bold text-gray-500 bg-gray-100 px-2 py-0.5 rounded">
                                            <?= htmlspecialchars($req['protocol_code']) ?>
                                        </span>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $st['class'] ?>">
                                            <?= $st['label'] ?>
                                        </span>
                                        <?php if ($req['status'] === 'pending'): ?>
                                            <span class="text-xs text-gray-400 bg-gray-50 px-2 py-0.5 rounded border border-gray-200">
                                                <?= $stepLabels[$req['current_step_order']] ?? '' ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="font-semibold text-gray-900">
                                        <?= htmlspecialchars($req['teacher_name']) ?>
                                        <span class="font-normal text-gray-500">— <?= htmlspecialchars($req['course_name']) ?><?= $req['class_group'] ? ' / ' . htmlspecialchars($req['class_group']) : '' ?></span>
                                    </p>
                                    <?php if ($req['subject_name']): ?>
                                        <p class="text-sm text-gray-600 mt-0.5"><?= htmlspecialchars($req['subject_name']) ?></p>
                                    <?php endif; ?>
                                    <p class="text-xs text-gray-400 mt-1">
                                        <?= htmlspecialchars($datesStr) ?>
                                        <?php if ($slotsStr): ?> · <?= htmlspecialchars($slotsStr) ?><?php endif; ?>
                                        · Enviado <?= date('d/m/Y H:i', strtotime($req['created_at'])) ?>
                                    </p>
                                    <?php if ($req['suggested_teacher_name']): ?>
                                        <p class="text-xs text-gray-500 mt-0.5">Substituto sugerido: <strong><?= htmlspecialchars($req['suggested_teacher_name']) ?></strong></p>
                                    <?php endif; ?>
                                    <?php if ($req['reason']): ?>
                                        <p class="text-sm text-gray-600 mt-2 italic">"<?= htmlspecialchars(mb_substr($req['reason'], 0, 120)) ?><?= mb_strlen($req['reason']) > 120 ? '…' : '' ?>"</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Substitutos sugeridos (server-side) -->
                            <?php
                            if ($req['class_group'] && !empty($timeSlots)) {
                                $slotPlaceholders = implode(',', array_fill(0, count($timeSlots), '?'));
                                $stmtSug = $conn->prepare("
                                    SELECT DISTINCT teacher_name, day_of_week, time_slot
                                    FROM schedule_slots
                                    WHERE semester = ?
                                      AND class_group = ?
                                      AND time_slot NOT IN ($slotPlaceholders)
                                      AND (teacher_id IS NULL OR teacher_id != ?)
                                    ORDER BY teacher_name, day_of_week
                                ");
                                $stmtSug->execute(array_merge(
                                    [$currentSemester, $req['class_group']],
                                    $timeSlots,
                                    [$req['teacher_id'] ?? 0]
                                ));
                                $sugRows = $stmtSug->fetchAll(PDO::FETCH_ASSOC);

                                // Agrupar por professor
                                $sugGrouped = [];
                                foreach ($sugRows as $sr) {
                                    $n = $sr['teacher_name'];
                                    $sugGrouped[$n][] = ($slotLabels[$sr['time_slot']] ?? $sr['time_slot']) . ' (' . (AscXmlParser::DAY_LABELS[(int)$sr['day_of_week']] ?? $sr['day_of_week']) . ')';
                                }
                                if (!empty($sugGrouped)):
                            ?>
                            <div class="mt-3 bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 text-xs">
                                <p class="font-semibold text-gray-600 mb-1">Substitutos sugeridos (ensinam <?= htmlspecialchars($req['class_group']) ?> em outros turnos):</p>
                                <ul class="space-y-0.5 text-gray-700">
                                    <?php foreach ($sugGrouped as $tName => $tSlots): ?>
                                        <li>• <strong><?= htmlspecialchars($tName) ?></strong> — <?= htmlspecialchars(implode(', ', $tSlots)) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; } ?>

                            <!-- Ações -->
                            <?php if ($canActNow): ?>
                                <div class="mt-4 pt-4 border-t border-gray-100">
                                    <form method="POST" class="space-y-3">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Observação (opcional)</label>
                                            <textarea name="observation" rows="2" placeholder="Motivo, instruções ao docente..."
                                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-DEFAULT outline-none bg-gray-50 resize-none"></textarea>
                                        </div>
                                        <div class="flex gap-2">
                                            <button type="submit" name="action" value="approve"
                                                class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors">
                                                ✓ Aprovar
                                            </button>
                                            <button type="submit" name="action" value="reject"
                                                onclick="return confirm('Confirma indeferimento desta solicitação?')"
                                                class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors">
                                                ✗ Indeferir
                                            </button>
                                            <button type="submit" name="action" value="comment"
                                                class="px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">
                                                Comentar
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</main>

<?php require_once 'layout/footer.php'; ?>
