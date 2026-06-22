<?php
/**
 * Tela principal de analise de um requerimento, exibindo dados do aluno, anexos e permitindo adicionar pareceres e mudar o status.
 *
 * @author Prof. Eduardo Gomes
 */
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../src/Auth.php';
require_once '../../src/Helpers.php';
require_once '../../src/CryptoHelper.php';
require_once '../../src/EmailService.php';
require_once '../../src/EmailTemplate.php';

Auth::check();
$user = Auth::user();

if (!isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit;
}

$requestId = $_GET['id'];
$db = new Database();
$conn = $db->getConnection();

// Busca Detalhes do Requerimento
$query = "
    SELECT r.*, c.name as course_name, rt.name as type_name 
    FROM requests r
    JOIN courses c ON r.course_id = c.id
    JOIN request_types rt ON r.request_type_id = rt.id
    WHERE r.id = :id
";
$stmt = $conn->prepare($query);
$stmt->execute([':id' => $requestId]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    die("Requerimento não encontrado.");
}

$request['student_phone']  = CryptoHelper::decrypt($request['student_phone']);
$request['guardian_phone'] = CryptoHelper::decrypt($request['guardian_phone']);

// Busca Arquivos
$filesStmt = $conn->prepare("SELECT * FROM request_files WHERE request_id = :id");
$filesStmt->execute([':id' => $requestId]);
$files = $filesStmt->fetchAll(PDO::FETCH_ASSOC);

// Busca Historico
$historyStmt = $conn->prepare("
    SELECT rh.*, u.name as user_name 
    FROM request_history rh 
    LEFT JOIN users u ON rh.user_id = u.id 
    WHERE rh.request_id = :id 
    ORDER BY rh.created_at ASC
");
$historyStmt->execute([':id' => $requestId]);
$history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

// Verifica se o usuario atual pode desfazer a ultima acao
$canUndo = false;
$lastAction = null;
if (!empty($history)) {
    $lastAction = end($history);
    // Permite o estorno se o usuário foi o autor da última ação
    if ($lastAction['user_id'] == $user['user_id']) {
        $canUndo = true;
    }
}

// Busca Etapas do Workflow
$workflowStmt = $conn->prepare("
    SELECT ws.step_order, r.name as role_name 
    FROM workflow_steps ws
    JOIN roles r ON ws.role_id = r.id
    WHERE ws.request_type_id = :tid 
    ORDER BY ws.step_order ASC
");
$workflowStmt->execute([':tid' => $request['request_type_id']]);
$workflowSteps = $workflowStmt->fetchAll(PDO::FETCH_ASSOC);

// Lida com Acoes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action']; // 'approve', 'reject', or 'undo'
    $observation = $_POST['observation'] ?? null;

    if ($action === 'undo' && $canUndo && $lastAction) {
        // Lida com a Logica de Desfazer
        $conn->beginTransaction();
        try {
            // 1. Delete the last history record
            $delStmt = $conn->prepare("DELETE FROM request_history WHERE id = :hid");
            $delStmt->execute([':hid' => $lastAction['id']]);

            // 2. Revert Status and Step Order
            if ($lastAction['action'] === 'reject') {
                // Se foi rejeitado, apenas coloca de volta para pendente
                $revertStatus = 'pending';
                $revertOrder = $request['current_step_order'];
            } else if ($lastAction['action'] === 'approve') {
                // Se foi aprovado, concluiu ou avancou?
                if ($request['status'] === 'concluded') {
                    $revertStatus = 'pending';
                    $revertOrder = $request['current_step_order'];
                } else {
                    // Avancou para a proxima etapa, volta uma etapa
                    $revertStatus = 'pending';
                    $revertOrder = max(1, $request['current_step_order'] - 1);
                }
            }

            $updateStmt = $conn->prepare("UPDATE requests SET status = :status, current_step_order = :order WHERE id = :id");
            $updateStmt->execute([
                ':status' => $revertStatus,
                ':order' => $revertOrder,
                ':id' => $requestId
            ]);

            $conn->commit();

            // Notifica o Aluno about the reversal
            $emailService = new EmailService($conn);
            $subject = 'Aviso: Requisição Retornou para Análise — Protocolo: ' . $request['protocol_code'];
            $body = EmailTemplate::wrap('
                <p>Olá, <strong>' . htmlspecialchars($request['student_name']) . '</strong>!</p>
                <p>O status da sua requisição precisou ser revertido para reanálise devido a um equívoco operacional.</p>
                <table style="width:100%;border-collapse:collapse;margin:20px 0;font-size:13px;">
                    <tr style="background:#f9fafb;">
                        <td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;width:40%;">Protocolo</td>
                        <td style="padding:8px 12px;border:1px solid #e5e7eb;font-family:monospace;">' . htmlspecialchars($request['protocol_code']) . '</td>
                    </tr>
                    <tr>
                        <td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Novo Status</td>
                        <td style="padding:8px 12px;border:1px solid #e5e7eb;">Pendente de Análise</td>
                    </tr>
                </table>
                <p>Você será notificado novamente assim que houver um novo parecer.</p>
                <p><a href="' . BASE_URL . '/check_status.php" style="display:inline-block;background:#1CBB9B;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;font-weight:bold;">Consultar Protocolo</a></p>
            ');
            $emailService->send($request['student_email'], $request['student_name'], $subject, $body, false);
            $emailService->triggerBackgroundProcess();

            header('Location: request_details.php?id=' . $requestId);
            exit;

        } catch (Exception $e) {
            $conn->rollBack();
            die("Erro ao desfazer ação: " . $e->getMessage());
        }
    } else if ($action === 'approve' || $action === 'reject') {
        if ($action === 'approve') {
            // Verifica a proxima etapa
            $nextStepOrder = $request['current_step_order'] + 1;
            $checkStep = $conn->prepare("SELECT * FROM workflow_steps WHERE request_type_id = :tid AND step_order = :order");
            $checkStep->execute([':tid' => $request['request_type_id'], ':order' => $nextStepOrder]);
            $nextStep = $checkStep->fetch();

            if ($nextStep) {
                $newStatus = 'pending';
                $newOrder = $nextStepOrder;
            } else {
                $newStatus = 'concluded';
                $newOrder = $request['current_step_order'];
            }

            $updateQuery = "UPDATE requests SET status = :status, current_step_order = :order WHERE id = :id";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->execute([':status' => $newStatus, ':order' => $newOrder, ':id' => $requestId]);

        } elseif ($action === 'reject') {
            $updateStmt = $conn->prepare("UPDATE requests SET status = 'rejected' WHERE id = :id");
            $updateStmt->execute([':id' => $requestId]);
        }

        // Registra Historico
        $histStmt = $conn->prepare("INSERT INTO request_history (request_id, user_id, action, observation) VALUES (:rid, :uid, :action, :obs)");
        $histStmt->execute([
            ':rid' => $requestId,
            ':uid' => $user['user_id'],
            ':action' => $action,
            ':obs' => $observation
        ]);

        // Envia Email Notification
        require_once '../../src/EmailService.php';
        require_once '../../src/EmailTemplate.php';
        $emailService = new EmailService($conn);

        if ($action === 'approve' && $newStatus === 'pending') {
            // Notifica usuarios sobre a proxima etapa
            $nextRoleStmt = $conn->prepare("SELECT role_id FROM workflow_steps WHERE request_type_id = :tid AND step_order = :order");
            $nextRoleStmt->execute([':tid' => $request['request_type_id'], ':order' => $newOrder]);
            $nextRoleId = $nextRoleStmt->fetchColumn();

            if ($nextRoleId) {
                // Encontra usuarios com este perfil
                // Se o perfil for vinculado a curso, filtra por curso
                $roleCheckStmt = $conn->prepare("SELECT is_course_bound FROM roles WHERE id = :rid");
                $roleCheckStmt->execute([':rid' => $nextRoleId]);
                $isCourseBound = $roleCheckStmt->fetchColumn();

                $userQuery = "SELECT u.name, u.email FROM users u WHERE u.role_id = :rid AND u.receive_email = 1";
                $userParams = [':rid' => $nextRoleId];

                if ($isCourseBound) {
                    $userQuery = "
                        SELECT u.name, u.email 
                        FROM users u 
                        JOIN user_courses uc ON u.id = uc.user_id 
                        WHERE u.role_id = :rid 
                        AND u.receive_email = 1 
                        AND uc.course_id = :cid
                    ";
                    $userParams[':cid'] = $request['course_id'];
                }

                $usersStmt = $conn->prepare($userQuery);
                $usersStmt->execute($userParams);
                $recipients = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($recipients as $recipient) {
                    $subject = "Nova Requisição Pendente - Protocolo: " . $request['protocol_code'];
                    $body = "
                        <h2>Olá, {$recipient['name']}!</h2>
                        <p>Uma nova requisição requer sua análise.</p>
                        <p><strong>Protocolo:</strong> {$request['protocol_code']}</p>
                        <p><strong>Aluno:</strong> {$request['student_name']}</p>
                        <p><strong>Curso:</strong> {$request['course_name']}</p>
                        <p>Acesse o sistema para visualizar os detalhes e tomar uma ação:</p>
                        <p><a href='" . BASE_URL . "/admin/request_details.php?id=$requestId' style='color: #1CBB9B; font-weight: bold;'>Visualizar Requerimento</a></p>
                        <br>
                        <p>Atenciosamente,<br>Sistema de Requisições IFSC</p>
                    ";
                    $emailService->send($recipient['email'], $recipient['name'], $subject, EmailTemplate::wrap($body), false);
                }
                $emailService->triggerBackgroundProcess();
            }
        } elseif ($action === 'reject' || $newStatus === 'concluded') {
            // Notifica o Aluno
            $statusTrans = Helpers::translateStatus($action === 'reject' ? 'rejected' : 'concluded');
            $subject = "Atualização da Requisição - Protocolo: " . $request['protocol_code'];
            $body = "
                <h2>Olá, {$request['student_name']}!</h2>
                <p>Sua requisição teve uma atualização.</p>
                <p><strong>Protocolo:</strong> {$request['protocol_code']}</p>
                <p><strong>Novo Status:</strong> $statusTrans</p>
                <p><strong>Observação:</strong> " . nl2br(htmlspecialchars($observation)) . "</p>
                <p>Para mais detalhes, acesse:</p>
                <p><a href='" . BASE_URL . "/check_status.php' style='color: #1CBB9B; font-weight: bold;'>Consultar Protocolo</a></p>
                <br>
                <p>Atenciosamente,<br>IFSC Câmpus Garopaba</p>
            ";
            $emailService->send($request['student_email'], $request['student_name'], $subject, EmailTemplate::wrap($body), false);

            // Notifica docente(s) — apenas para 2ª Chamada
            if ($request['request_type_id'] == 2) {
                $extra2 = json_decode($request['extra_fields'] ?? '{}', true);
                $teacherIds2 = array_filter((array)($extra2['selected_teachers'] ?? []), 'is_numeric');
                $actionLabel = ($action === 'reject') ? 'indeferida' : 'deferida';
                $actionColor = ($action === 'reject') ? '#dc2626' : '#059669';

                // Busca nomes das UCs selecionadas
                $ucNames2 = '';
                $subjectIds2 = array_filter((array)($extra2['selected_subjects'] ?? []), 'is_numeric');
                if (!empty($subjectIds2)) {
                    $phS2 = implode(',', array_fill(0, count($subjectIds2), '?'));
                    $sStmt2 = $conn->prepare("SELECT name FROM subjects WHERE id IN ($phS2)");
                    $sStmt2->execute(array_values($subjectIds2));
                    $ucNames2 = implode(', ', array_column($sStmt2->fetchAll(PDO::FETCH_ASSOC), 'name'));
                }
                if (!empty($extra2['uc_other_name'])) {
                    $ucNames2 .= ($ucNames2 ? ', ' : '') . htmlspecialchars($extra2['uc_other_name']);
                }

                if (!empty($teacherIds2)) {
                    $placeholders2 = implode(',', array_fill(0, count($teacherIds2), '?'));
                    $tStmt2 = $conn->prepare("SELECT name, email FROM teachers WHERE id IN ($placeholders2) AND email IS NOT NULL AND email != ''");
                    $tStmt2->execute(array_values($teacherIds2));
                    foreach ($tStmt2->fetchAll(PDO::FETCH_ASSOC) as $tRow) {
                        $tSubject = "2ª Chamada – Solicitação {$actionLabel} (Protocolo #{$request['protocol_code']})";
                        $ucHtml  = $ucNames2 ? "<p><strong>Unidade(s) Curricular(es):</strong> {$ucNames2}</p>" : '';
                        $obsHtml = $observation ? '<p><strong>Observação do coordenador:</strong> ' . nl2br(htmlspecialchars($observation)) . '</p>' : '';
                        $tBody = "
                            <p>Prezado(a) <strong>" . htmlspecialchars($tRow['name']) . "</strong>,</p>
                            <p>A solicitação de <strong>Avaliação de 2ª Chamada</strong> do(a) aluno(a)
                               <strong>" . htmlspecialchars($request['student_name']) . "</strong>
                               (Protocolo <strong>#{$request['protocol_code']}</strong>)
                               foi <strong style='color:{$actionColor};'>{$actionLabel}</strong>
                               pela Coordenação de Curso.</p>
                            {$ucHtml}
                            {$obsHtml}
                        ";
                        $emailService->send($tRow['email'], $tRow['name'], $tSubject, EmailTemplate::wrap($tBody), false);
                    }
                }
            }

            $emailService->triggerBackgroundProcess();
        }

        header('Location: dashboard.php');
        exit;
    }
}
?>
<?php require_once 'layout/header.php'; ?>
<?php require_once 'layout/sidebar.php'; ?>

<main class="flex-1 overflow-y-auto p-8 bg-[#F2F4F8]">

    <!-- Header Info -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <?php
                    $backLink = 'dashboard.php';
                    if (isset($_GET['source'])) {
                        switch ($_GET['source']) {
                            case 'my_history':
                                $backLink = 'my_history.php';
                                break;
                            case 'all_requests':
                                $backLink = 'all_requests.php';
                                break;
                        }
                    }
                    ?>
                    <a href="<?= $backLink ?>" class="text-gray-400 hover:text-brand-DEFAULT transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                    </a>
                    <h1 class="text-2xl font-bold text-gray-800 tracking-tight">Protocolo:
                        <?= htmlspecialchars($request['protocol_code']) ?>
                    </h1>
                </div>
                <div class="ml-9 mt-2">
                    <span
                        class="px-4 py-1.5 rounded-full text-sm font-bold bg-blue-50 text-blue-600 border border-blue-100">
                        <?= htmlspecialchars($request['type_name']) ?>
                    </span>
                </div>
            </div>
            <span class="px-4 py-2 rounded-xl text-sm font-bold shadow-sm
                    <?= $request['status'] === 'pending' ? 'bg-yellow-50 text-yellow-700 border border-yellow-100' : '' ?>
                    <?= $request['status'] === 'approved' ? 'bg-green-50 text-green-700 border border-green-100' : '' ?>
                    <?= $request['status'] === 'concluded' ? 'bg-brand-light/20 text-brand-dark border border-brand-light/30' : '' ?>
                    <?= $request['status'] === 'rejected' ? 'bg-red-50 text-red-700 border border-red-100' : '' ?>
                ">
                <?= Helpers::translateStatus($request['status']) ?>
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left Column: Student Data & Files -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-6 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-brand-DEFAULT" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    Dados do Aluno
                </h3>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-6 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Nome</dt>
                        <dd
                            class="text-sm font-medium text-gray-900 bg-gray-50 px-3 py-2 rounded-lg border border-gray-100">
                            <?= htmlspecialchars($request['student_name']) ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Matrícula</dt>
                        <dd
                            class="text-sm font-medium text-gray-900 bg-gray-50 px-3 py-2 rounded-lg border border-gray-100">
                            <?= htmlspecialchars($request['student_id']) ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">E-mail</dt>
                        <dd
                            class="text-sm font-medium text-gray-900 bg-gray-50 px-3 py-2 rounded-lg border border-gray-100">
                            <?= htmlspecialchars($request['student_email']) ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Telefone/WhatsApp</dt>
                        <dd
                            class="text-sm font-medium text-gray-900 bg-gray-50 px-3 py-2 rounded-lg border border-gray-100">
                            <?= !empty($request['student_phone']) ? htmlspecialchars($request['student_phone']) : '-' ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Curso</dt>
                        <dd
                            class="text-sm font-medium text-gray-900 bg-gray-50 px-3 py-2 rounded-lg border border-gray-100">
                            <?= htmlspecialchars($request['course_name']) ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Turma/Módulo/Ano</dt>
                        <dd
                            class="text-sm font-medium text-gray-900 bg-gray-50 px-3 py-2 rounded-lg border border-gray-100">
                            <?= htmlspecialchars($request['class_info'] ?? '-') ?>
                        </dd>
                    </div>
                    <?php if ($request['is_minor']): ?>
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Responsável</dt>
                            <dd
                                class="text-sm font-medium text-gray-900 bg-gray-50 px-3 py-2 rounded-lg border border-gray-100">
                                <?= htmlspecialchars($request['guardian_name']) ?>
                                (<?= htmlspecialchars($request['guardian_phone']) ?>)
                            </dd>
                        </div>
                    <?php endif; ?>

                    <?php if ($request['request_type_id'] == 20): ?>
                        <div class="sm:col-span-2 border-t border-gray-100 pt-6 mt-2">
                            <dt class="text-xs font-bold text-[#1CBB9B] uppercase tracking-wider mb-4 flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Detalhes do Horário Diferenciado
                            </dt>
                            <dd class="bg-[#1CBB9B]/5 p-4 rounded-xl border border-[#1CBB9B]/10">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">Tipo</p>
                                        <p class="text-sm font-semibold text-gray-700">
                                            <?= htmlspecialchars($request['schedule_type']) ?>
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">Declaração Aceita?</p>
                                        <p class="text-sm font-semibold text-green-600">Sim</p>
                                    </div>
                                    <div class="md:col-span-1">
                                        <p class="text-[10px] font-bold text-gray-400 uppercase mb-2">Horários de Chegada
                                        </p>
                                        <div class="flex gap-2">
                                            <span
                                                class="px-2 py-1 bg-white rounded border text-xs text-gray-600"><?= $request['arrival_time_1'] ? substr($request['arrival_time_1'], 0, 5) : '-' ?></span>
                                            <span
                                                class="px-2 py-1 bg-white rounded border text-xs text-gray-600"><?= $request['arrival_time_2'] ? substr($request['arrival_time_2'], 0, 5) : '-' ?></span>
                                        </div>
                                    </div>
                                    <div class="md:col-span-1">
                                        <p class="text-[10px] font-bold text-gray-400 uppercase mb-2">Horários de Saída</p>
                                        <div class="flex gap-2">
                                            <span
                                                class="px-2 py-1 bg-white rounded border text-xs text-gray-600"><?= $request['departure_time_1'] ? substr($request['departure_time_1'], 0, 5) : '-' ?></span>
                                            <span
                                                class="px-2 py-1 bg-white rounded border text-xs text-gray-600"><?= $request['departure_time_2'] ? substr($request['departure_time_2'], 0, 5) : '-' ?></span>
                                        </div>
                                    </div>
                                </div>
                            </dd>
                        </div>
                    <?php endif; ?>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Descrição do Pedido
                        </dt>
                        <dd
                            class="text-sm text-gray-700 bg-gray-50 p-4 rounded-xl border border-gray-100 leading-relaxed">
                            <?= nl2br(htmlspecialchars($request['description'] ?? 'Sem descrição.')) ?>
                        </dd>
                    </div>
                </dl>
            </div>

            <?php
            $extraFields = json_decode($request['extra_fields'] ?? 'null', true);
            $typeIdForExtra = intval($request['request_type_id']);

            // Labels legíveis para cada chave
            $extraLabels = [
                'start_date'          => 'Data de Início',
                'end_date'            => 'Data Final',
                'selected_subjects'   => 'UCs Selecionadas',
                'selected_teachers'   => 'Docentes',
                'teacher_other_name'  => 'Outro Professor',
                'also_justify_absence'=> 'Também justifica falta?',
                'semesters_to_lock'   => 'Semestres a trancar',
                'lock_reason'         => 'Motivo do Trancamento',
                'cancel_reason'       => 'Motivo do Cancelamento',
                'uc_isolated_names'   => 'UC(s)',
                'uc_isolated_course'  => 'Curso que oferece',
                'doc_type'            => 'Tipo de Documento',
                'diploma_course_name' => 'Nome do Curso',
                'graduation_year'     => 'Ano de Conclusão',
                'validation_type'     => 'Tipo de Validação',
                'uc_re_detail'        => 'Detalhes RE',
                'uc_rs_detail'        => 'Detalhes RS',
                'uc_eae_detail'       => 'Detalhes EAE',
                'uc_special_names'    => 'UC(s) de interesse',
                'uc_special_course'   => 'Curso que oferece',
                'uc_changes'          => 'UCs a incluir/cancelar',
                'enade_status'        => 'Situação ENADE',
                'colacao_declaration' => 'Declaração aceita?',
                'uc_cancel_reason'    => 'Motivo do Cancelamento',
            ];

            // Quais chaves mostrar por tipo
            $extraFieldsByType = [
                1  => ['start_date','end_date','selected_subjects'],
                2  => ['selected_subjects','selected_teachers','teacher_other_name','also_justify_absence'],
                4  => ['semesters_to_lock','lock_reason'],
                5  => ['cancel_reason'],
                7  => ['uc_isolated_names','uc_isolated_course'],
                8  => ['doc_type','diploma_course_name','graduation_year'],
                9  => ['validation_type','uc_re_detail','uc_rs_detail','uc_eae_detail'],
                12 => ['uc_special_names','uc_special_course'],
                14 => ['uc_changes'],
                18 => ['enade_status','colacao_declaration'],
                22 => ['selected_subjects','selected_teachers','teacher_other_name'],
                25 => ['selected_subjects','uc_cancel_reason'],
            ];

            if (!empty($extraFields) && isset($extraFieldsByType[$typeIdForExtra])):
                // Carrega nomes de subjects e teachers para lookup
                $subjectNames = [];
                $teacherNames = [];
                $teacherEmails = [];
                $subStmt = $conn->query("SELECT id, name FROM subjects");
                foreach ($subStmt->fetchAll(PDO::FETCH_ASSOC) as $row) $subjectNames[$row['id']] = $row['name'];
                $tchStmt = $conn->query("SELECT id, name, email FROM teachers");
                foreach ($tchStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $teacherNames[$row['id']] = $row['name'];
                    $teacherEmails[$row['id']] = $row['email'];
                }

                function renderExtraValue($key, $value, $subjectNames, $teacherNames, $teacherEmails = []) {
                    if (is_bool($value)) return $value ? 'Sim' : 'Não';
                    if ($key === 'selected_subjects' && is_array($value)) {
                        $names = array_map(fn($id) => $subjectNames[$id] ?? "ID $id", $value);
                        return implode(', ', $names) ?: '—';
                    }
                    if ($key === 'selected_teachers' && is_array($value)) {
                        $parts = array_map(function($id) use ($teacherNames, $teacherEmails) {
                            $name = htmlspecialchars($teacherNames[$id] ?? "ID $id");
                            $hasEmail = !empty($teacherEmails[$id]);
                            $badge = $hasEmail ? '' : ' <span class="text-xs text-amber-500 font-medium">(sem e-mail)</span>';
                            return $name . $badge;
                        }, $value);
                        return $parts ? implode(', ', $parts) : '—';
                    }
                    if (is_array($value)) return implode(', ', $value) ?: '—';
                    if ($value === '' || $value === null) return '—';
                    return htmlspecialchars((string)$value);
                }
            ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-brand-DEFAULT" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    Informações do Requerimento
                </h3>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                    <?php foreach ($extraFieldsByType[$typeIdForExtra] as $key):
                        if (!array_key_exists($key, $extraFields)) continue;
                        $val = $extraFields[$key];
                        // Omite campos vazios/falsos pouco relevantes
                        if (($val === '' || $val === null || $val === [] || $val === false) && $key !== 'colacao_declaration' && $key !== 'also_justify_absence') continue;
                    ?>
                        <div class="<?= in_array($key, ['uc_changes','uc_re_detail','uc_rs_detail','uc_eae_detail','uc_isolated_names','uc_special_names','uc_cancel_reason','selected_subjects','selected_teachers']) ? 'sm:col-span-2' : '' ?>">
                            <dt class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">
                                <?= $extraLabels[$key] ?? $key ?>
                            </dt>
                            <dd class="text-sm font-medium text-gray-800 bg-gray-50 px-3 py-2 rounded-lg border border-gray-100">
                                <?= renderExtraValue($key, $val, $subjectNames, $teacherNames, $teacherEmails) ?>
                            </dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </div>
            <?php endif; ?>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-6 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-brand-DEFAULT" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13">
                        </path>
                    </svg>
                    Arquivos Anexados
                </h3>
                <?php if (empty($files)): ?>
                    <div class="text-center py-8 bg-gray-50 rounded-xl border border-dashed border-gray-200">
                        <p class="text-gray-400 text-sm">Nenhum arquivo anexado.</p>
                    </div>
                <?php else: ?>
                    <ul class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <?php foreach ($files as $file): ?>
                            <li
                                class="flex items-center justify-between p-4 bg-gray-50 rounded-xl border border-gray-100 hover:border-brand-light/50 transition-colors group">
                                <div class="flex items-center overflow-hidden">
                                    <svg class="w-8 h-8 text-gray-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                        </path>
                                    </svg>
                                    <span
                                        class="text-sm font-medium text-gray-700 truncate"><?= htmlspecialchars($file['filepath']) ?></span>
                                </div>
                                <a href="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($file['filepath']) ?>" target="_blank"
                                    class="text-brand-DEFAULT hover:text-brand-dark p-2 rounded-lg hover:bg-brand-light/10 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                        </path>
                                    </svg>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column: Actions & History -->
        <div class="space-y-6">

            <!-- Action Box -->
            <?php
            $canAnalyze = false;
            if ($request['status'] === 'pending') {
                // Verifica se o usuario atual tem o perfil exigido para a etapa atual
                foreach ($workflowSteps as $step) {
                    if ($step['step_order'] == $request['current_step_order']) {
                        // Compara o nome_perfil da etapa com o nome_perfil do usuario
                        // Como temos o array do usuario, vamos consultar o bd para checar seu nome ou id de perfil
                        // Espera, podemos buscar o role_id da etapa diretamente para ser mais seguro
                        $stepRoleStmt = $conn->prepare("SELECT role_id FROM workflow_steps WHERE request_type_id = :tid AND step_order = :order");
                        $stepRoleStmt->execute([':tid' => $request['request_type_id'], ':order' => $request['current_step_order']]);
                        $requiredRoleId = $stepRoleStmt->fetchColumn();

                        if ($requiredRoleId && isset($user['user_role']) && $user['user_role'] == $requiredRoleId) {
                            $canAnalyze = true;

                            // Se o perfil for vinculado a curso, filtra por curso
                            $roleCheckStmt = $conn->prepare("SELECT is_course_bound FROM roles WHERE id = :rid");
                            $roleCheckStmt->execute([':rid' => $requiredRoleId]);
                            $isCourseBound = $roleCheckStmt->fetchColumn();

                            if ($isCourseBound) {
                                $userCourses = $user['user_courses'] ?? [];
                                if (!in_array($request['course_id'], $userCourses)) {
                                    $canAnalyze = false;
                                }
                            }
                        }
                        break;
                    }
                }
            }
            ?>
            <?php
            // Info box: notificação automática para 2ª Chamada
            if ($request['request_type_id'] == 2):
                $ib_extra = json_decode($request['extra_fields'] ?? '{}', true);
                $ib_ids   = array_filter((array)($ib_extra['selected_teachers'] ?? []), 'is_numeric');
                $ib_other = trim($ib_extra['teacher_other_name'] ?? '');
                $ib_rows  = [];
                if (!empty($ib_ids)) {
                    $ib_ph = implode(',', array_fill(0, count($ib_ids), '?'));
                    $ib_s  = $conn->prepare("SELECT name, email FROM teachers WHERE id IN ($ib_ph)");
                    $ib_s->execute(array_values($ib_ids));
                    $ib_rows = $ib_s->fetchAll(PDO::FETCH_ASSOC);
                }
            ?>
            <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 mb-4">
                <p class="text-sm font-bold text-blue-800 mb-2 flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    Notificação automática ao(s) docente(s)
                </p>
                <?php if (empty($ib_rows) && !$ib_other): ?>
                    <p class="text-sm text-blue-700">Nenhum docente vinculado a este requerimento.</p>
                <?php else: ?>
                <ul class="space-y-1 mb-2">
                    <?php foreach ($ib_rows as $ib_t): ?>
                    <li class="text-sm flex items-center gap-2">
                        <?php if ($ib_t['email']): ?>
                            <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span class="font-medium text-gray-800"><?= htmlspecialchars($ib_t['name']) ?></span>
                            <span class="text-gray-400 text-xs"><?= htmlspecialchars($ib_t['email']) ?></span>
                        <?php else: ?>
                            <svg class="w-4 h-4 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="font-medium text-gray-800"><?= htmlspecialchars($ib_t['name']) ?></span>
                            <span class="text-amber-600 text-xs font-medium">sem e-mail — não será notificado</span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                    <?php if ($ib_other): ?>
                    <li class="text-sm flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="font-medium text-gray-800"><?= htmlspecialchars($ib_other) ?></span>
                        <span class="text-amber-600 text-xs font-medium">não cadastrado — não será notificado</span>
                    </li>
                    <?php endif; ?>
                </ul>
                <p class="text-xs text-blue-700">Ao deferir ou indeferir, os docentes acima serão notificados automaticamente por e-mail.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($canAnalyze): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-1 h-full bg-brand-DEFAULT"></div>
                    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-brand-DEFAULT" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01">
                            </path>
                        </svg>
                        Análise do Requerimento
                    </h3>
                    <form method="POST" id="actionForm">
                        <?= Csrf::field() ?>
                        <div class="mb-4">
                            <label class="block text-xs font-bold text-gray-500 mb-1 uppercase tracking-wider">Observação
                                (Obrigatório)</label>
                            <textarea name="observation" required rows="4"
                                class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-sm focus:ring-2 focus:ring-brand-DEFAULT focus:border-transparent outline-none transition-all resize-none"
                                placeholder="Digite sua análise aqui..."></textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="submit" name="action" value="approve" id="approveBtn"
                                class="bg-[#1CBB9B] text-white py-2.5 rounded-xl font-bold hover:bg-[#169C80] transition-all shadow-sm hover:shadow-md flex justify-center items-center disabled:opacity-50 disabled:cursor-not-allowed">
                                <span id="approveContent" class="flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Deferir
                                </span>
                                <span id="approveLoading" class="hidden flex items-center">
                                    <svg class="animate-spin -ml-1 mr-2 h-5 w-5 text-white"
                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                    Aguarde...
                                </span>
                            </button>
                            <button type="submit" name="action" value="reject" id="rejectBtn"
                                class="bg-white border border-red-200 text-red-500 py-2.5 rounded-xl font-bold hover:bg-red-50 hover:text-red-600 transition-all shadow-sm hover:shadow-md flex justify-center items-center disabled:opacity-50 disabled:cursor-not-allowed">
                                <span id="rejectContent" class="flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    Indeferir
                                </span>
                                <span id="rejectLoading" class="hidden flex items-center">
                                    <svg class="animate-spin -ml-1 mr-2 h-5 w-5 text-red-500"
                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                    Aguarde...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Workflow Steps Timeline -->
            <?php if (!empty($workflowSteps)): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-6 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-brand-DEFAULT" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        Fluxo da Requisição
                    </h3>
                    <div class="flow-root">
                        <ul class="-mb-8">
                            <?php foreach ($workflowSteps as $index => $step):
                                $isLast = $index === count($workflowSteps) - 1;

                                // Determina o estado da etapa
                                $state = 'future';
                                if ($request['status'] === 'concluded') {
                                    $state = 'completed';
                                } elseif ($request['status'] === 'rejected') {
                                    if ($step['step_order'] < $request['current_step_order']) {
                                        $state = 'completed';
                                    } elseif ($step['step_order'] == $request['current_step_order']) {
                                        $state = 'rejected';
                                    }
                                } else {
                                    // pendente ou aprovado
                                    if ($step['step_order'] < $request['current_step_order']) {
                                        $state = 'completed';
                                    } elseif ($step['step_order'] == $request['current_step_order']) {
                                        $state = 'current';
                                    }
                                }
                                ?>
                                <li>
                                    <div class="relative pb-8">
                                        <?php if (!$isLast): ?>
                                            <span
                                                class="absolute top-4 left-4 -ml-px h-full w-0.5 <?= $state === 'completed' ? 'bg-green-200' : 'bg-gray-200' ?>"
                                                aria-hidden="true"></span>
                                        <?php endif; ?>
                                        <div class="relative flex space-x-3 items-center">
                                            <div>
                                                <span class="h-8 w-8 rounded-full flex items-center justify-center ring-4 ring-white shadow-sm
                                                <?= $state === 'completed' ? 'bg-green-500' :
                                                    ($state === 'current' ? 'bg-yellow-400' :
                                                        ($state === 'rejected' ? 'bg-red-500' : 'bg-gray-200')) ?>">

                                                    <?php if ($state === 'completed'): ?>
                                                        <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"
                                                            stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M5 13l4 4L19 7"></path>
                                                        </svg>
                                                    <?php elseif ($state === 'current'): ?>
                                                        <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"
                                                            stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                        </svg>
                                                    <?php elseif ($state === 'rejected'): ?>
                                                        <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"
                                                            stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M6 18L18 6M6 6l12 12"></path>
                                                        </svg>
                                                    <?php else: ?>
                                                        <div class="h-2 w-2 rounded-full bg-white"></div>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div>
                                                    <p
                                                        class="text-sm font-bold <?= $state === 'future' ? 'text-gray-400' : 'text-gray-900' ?>">
                                                        <?= htmlspecialchars($step['role_name']) ?>
                                                    </p>
                                                    <?php if ($state === 'completed'): ?>
                                                        <p class="text-xs text-green-600 font-medium">Analisado</p>
                                                    <?php elseif ($state === 'current'): ?>
                                                        <p class="text-xs text-yellow-600 font-medium">Aguardando Análise</p>
                                                    <?php elseif ($state === 'rejected'): ?>
                                                        <p class="text-xs text-red-600 font-medium">Indeferido</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <!-- History -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-bold text-gray-800 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-brand-DEFAULT" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Histórico
                    </h3>

                    <?php if (isset($canUndo) && $canUndo): ?>
                        <form method="POST" id="undoForm"
                            onsubmit="return confirm('Tem certeza que deseja desfazer a sua última decisão? Esta ação retornará a requisição para a etapa anterior e apagará o último registro do histórico.');">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="undo">
                            <button type="submit"
                                class="bg-yellow-50 text-yellow-600 border border-yellow-200 px-4 py-2 rounded-xl text-sm font-bold hover:bg-yellow-100 hover:text-yellow-700 transition-colors shadow-sm flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                                </svg>
                                Desfazer Última Decisão
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="flow-root">
                    <ul class="-mb-8">
                        <?php foreach ($history as $entry): ?>
                            <li>
                                <div class="relative pb-8">
                                    <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-100"
                                        aria-hidden="true"></span>
                                    <div class="relative flex space-x-3">
                                        <div>
                                            <span
                                                class="h-8 w-8 rounded-full flex items-center justify-center ring-4 ring-white shadow-sm
                                                    <?= $entry['action'] === 'approve' ? 'bg-green-500' : ($entry['action'] === 'reject' ? 'bg-red-500' : 'bg-gray-400') ?>">
                                                <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"
                                                    stroke="currentColor">
                                                    <?php if ($entry['action'] === 'approve'): ?>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M5 13l4 4L19 7" />
                                                    <?php elseif ($entry['action'] === 'reject'): ?>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M6 18L18 6M6 6l12 12" />
                                                    <?php else: ?>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                                    <?php endif; ?>
                                                </svg>
                                            </span>
                                        </div>
                                        <div class="min-w-0 flex-1 pt-1.5">
                                            <div class="flex justify-between mb-1">
                                                <p class="text-sm font-bold text-gray-900">
                                                    <?= htmlspecialchars($entry['user_name'] ?? 'Sistema') ?>
                                                </p>
                                                <span class="text-xs text-gray-400 font-medium">
                                                    <?= date('d/m H:i', strtotime($entry['created_at'])) ?>
                                                </span>
                                            </div>
                                            <p class="text-xs text-gray-500 mb-2">
                                                <?= $entry['action'] === 'approve' ? 'Aprovou o requerimento' : ($entry['action'] === 'reject' ? 'Rejeitou o requerimento' : 'Adicionou observação') ?>
                                            </p>
                                            <div
                                                class="text-sm text-gray-700 bg-gray-50 p-3 rounded-lg border border-gray-100">
                                                <?= htmlspecialchars($entry['observation']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('actionForm');
        if (form) {
            form.addEventListener('submit', function (e) {
                const submitter = e.submitter;
                const approveBtn = document.getElementById('approveBtn');
                const rejectBtn = document.getElementById('rejectBtn');

                // Cria input oculto para enviar o valor da acao
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'action';
                hiddenInput.value = submitter.value;
                form.appendChild(hiddenInput);

                // Desabilita ambos os botoes
                approveBtn.disabled = true;
                rejectBtn.disabled = true;

                // Mostra estado de carregamento no botao clicado
                if (submitter.value === 'approve') {
                    document.getElementById('approveContent').classList.add('hidden');
                    document.getElementById('approveLoading').classList.remove('hidden');
                } else if (submitter.value === 'reject') {
                    document.getElementById('rejectContent').classList.add('hidden');
                    document.getElementById('rejectLoading').classList.remove('hidden');
                }
            });
        }
    });
</script>

<?php require_once 'layout/footer.php'; ?>