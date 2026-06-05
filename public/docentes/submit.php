<?php
require_once 'guard.php';
require_once '../../src/EmailService.php';
require_once '../../src/EmailTemplate.php';
require_once '../../src/AscXmlParser.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: novo.php');
    exit;
}

// --- Validação básica ---
$courseId    = (int)($_POST['course_id'] ?? 0);
$classGroup  = trim($_POST['class_group'] ?? '');
$subjectName = trim($_POST['subject_name'] ?? '');
$reason      = trim($_POST['reason'] ?? '');
$devolutiva  = isset($_POST['devolutiva_agreed']) ? 1 : 0;

// Candidatos a substituto selecionados pelo docente
$candidateNamesRaw  = array_map('trim', (array)($_POST['candidate_names']  ?? []));
$candidateEmailsRaw = array_map('trim', (array)($_POST['candidate_emails'] ?? []));
$candidates = [];
foreach ($candidateNamesRaw as $i => $cname) {
    $cemail = $candidateEmailsRaw[$i] ?? '';
    if ($cname && filter_var($cemail, FILTER_VALIDATE_EMAIL)) {
        $candidates[] = ['name' => $cname, 'email' => $cemail];
    }
}

function formatDateWithDow(string $ymd): string {
    $dows = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
    $ts   = strtotime($ymd);
    return $dows[(int)date('w', $ts)] . ', ' . date('d/m/Y', $ts);
}

$rawDates = $_POST['absence_dates'] ?? [];
$absenceDates = array_values(array_filter(array_map('trim', $rawDates)));

$rawSlots  = $_POST['time_slots'] ?? [];
$timeSlots = array_values(array_filter(array_map('trim', $rawSlots)));

if (!$courseId || empty($absenceDates) || empty($timeSlots) || !$reason || !$devolutiva) {
    header('Location: novo.php?error=validation');
    exit;
}

// Validar course_id real
$stmtC = $conn->prepare("SELECT name FROM courses WHERE id = :id AND active = 1");
$stmtC->execute([':id' => $courseId]);
$courseName = $stmtC->fetchColumn();
if (!$courseName) {
    header('Location: novo.php?error=validation');
    exit;
}

// Gerar protocolo único
do {
    $protocol = 'TR-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    $stmtChk = $conn->prepare("SELECT id FROM teacher_requests WHERE protocol_code = :p");
    $stmtChk->execute([':p' => $protocol]);
} while ($stmtChk->fetchColumn());

// --- Inserção ---
$conn->beginTransaction();
try {
    $stmtIns = $conn->prepare("
        INSERT INTO teacher_requests
            (protocol_code, teacher_id, teacher_name, teacher_email, course_id,
             class_group, subject_name, absence_dates, time_slots, reason,
             suggested_teacher_id, devolutiva_agreed, status, current_step_order)
        VALUES
            (:protocol, :teacher_id, :teacher_name, :teacher_email, :course_id,
             :class_group, :subject_name, :absence_dates, :time_slots, :reason,
             NULL, :devolutiva, 'pending', 1)
    ");
    $stmtIns->execute([
        ':protocol'      => $protocol,
        ':teacher_id'    => $teacherId ?: null,
        ':teacher_name'  => $teacherName,
        ':teacher_email' => $teacherEmail,
        ':course_id'     => $courseId,
        ':class_group'   => $classGroup ?: null,
        ':subject_name'  => $subjectName ?: null,
        ':absence_dates' => json_encode($absenceDates, JSON_UNESCAPED_UNICODE),
        ':time_slots'    => json_encode($timeSlots, JSON_UNESCAPED_UNICODE),
        ':reason'        => $reason,
        ':devolutiva'    => $devolutiva,
    ]);
    $requestId = $conn->lastInsertId();

    // Inserir candidatos a substituto com token único por candidato
    if (!empty($candidates)) {
        $stmtCand = $conn->prepare("
            INSERT INTO request_candidate_substitutes
                (teacher_request_id, teacher_name, teacher_email, token)
            VALUES (:rid, :name, :email, :token)
        ");
        foreach ($candidates as &$c) {
            $c['token'] = bin2hex(random_bytes(32));
            $stmtCand->execute([
                ':rid'   => $requestId,
                ':name'  => $c['name'],
                ':email' => $c['email'],
                ':token' => $c['token'],
            ]);
        }
        unset($c);
    }

    $conn->commit();
} catch (Exception $e) {
    $conn->rollBack();
    header('Location: novo.php?error=db');
    exit;
}

// --- Notificações por e-mail ---
$emailService = new EmailService($conn);

$datesFormatted = implode('; ', array_map('formatDateWithDow', $absenceDates));
$slotLabels     = AscXmlParser::TIME_SLOT_LABELS;
$slotsFormatted = implode(', ', array_map(fn($s) => $slotLabels[$s] ?? $s, $timeSlots));

// 1. Confirmação ao docente
$teacherBody = EmailTemplate::wrap('
    <p>Olá, <strong>' . htmlspecialchars($teacherName) . '</strong>!</p>
    <p>Sua solicitação de substituição de aulas foi recebida com sucesso e está em análise.</p>
    <table style="width:100%;border-collapse:collapse;margin:20px 0;font-size:13px;">
        <tr style="background:#f9fafb;">
            <td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;width:40%;">Protocolo</td>
            <td style="padding:8px 12px;border:1px solid #e5e7eb;font-family:monospace;">' . $protocol . '</td>
        </tr>
        <tr>
            <td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Curso / Turma</td>
            <td style="padding:8px 12px;border:1px solid #e5e7eb;">' . htmlspecialchars($courseName) . ($classGroup ? ' — ' . htmlspecialchars($classGroup) : '') . '</td>
        </tr>
        <tr style="background:#f9fafb;">
            <td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Data(s)</td>
            <td style="padding:8px 12px;border:1px solid #e5e7eb;">' . htmlspecialchars($datesFormatted) . '</td>
        </tr>
        <tr>
            <td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Turno(s)</td>
            <td style="padding:8px 12px;border:1px solid #e5e7eb;">' . htmlspecialchars($slotsFormatted) . '</td>
        </tr>
    </table>
    <p style="color:#6b7280;font-size:13px;">Acompanhe o andamento da sua solicitação no <a href="' . BASE_URL . '/docentes/dashboard.php" style="color:#1CBB9B;">Portal Docente</a>.</p>
');
$emailService->queue($teacherEmail, $teacherName, "Solicitação de substituição recebida — $protocol", $teacherBody);

// 2. Notificar coordenadores do curso (step 1 = Coordenador de Curso, role_id = 2)
$stmtCoords = $conn->prepare("
    SELECT u.name, u.email
    FROM users u
    JOIN user_courses uc ON uc.user_id = u.id
    WHERE uc.course_id = :cid AND u.role_id = 2 AND u.receive_email = 1
");
$stmtCoords->execute([':cid' => $courseId]);
$coords = $stmtCoords->fetchAll(PDO::FETCH_ASSOC);

$adminUrl = BASE_URL . '/admin/teacher_requests.php';
foreach ($coords as $coord) {
    $coordBody = EmailTemplate::wrap('
        <p>Olá, <strong>' . htmlspecialchars($coord['name']) . '</strong>!</p>
        <p>Uma nova solicitação de substituição de aulas aguarda sua análise:</p>
        <table style="width:100%;border-collapse:collapse;margin:20px 0;font-size:13px;">
            <tr style="background:#f9fafb;">
                <td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;width:40%;">Protocolo</td>
                <td style="padding:8px 12px;border:1px solid #e5e7eb;font-family:monospace;">' . $protocol . '</td>
            </tr>
            <tr>
                <td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Docente</td>
                <td style="padding:8px 12px;border:1px solid #e5e7eb;">' . htmlspecialchars($teacherName) . '</td>
            </tr>
            <tr style="background:#f9fafb;">
                <td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Curso / Turma</td>
                <td style="padding:8px 12px;border:1px solid #e5e7eb;">' . htmlspecialchars($courseName) . ($classGroup ? ' — ' . htmlspecialchars($classGroup) : '') . '</td>
            </tr>
            <tr>
                <td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Data(s)</td>
                <td style="padding:8px 12px;border:1px solid #e5e7eb;">' . htmlspecialchars($datesFormatted) . '</td>
            </tr>
        </table>
        <p><a href="' . $adminUrl . '" style="display:inline-block;background:#1CBB9B;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;font-weight:bold;">Analisar Solicitação</a></p>
    ');
    $emailService->queue($coord['email'], $coord['name'], "Nova solicitação de substituição — $protocol", $coordBody);
}

// 3. Assessoria DEPE / DEPE — monitoramento (apenas se há candidatos selecionados)
if (!empty($candidates)) {
    $stmtDepe = $conn->prepare("
        SELECT name, email FROM users
        WHERE role_id IN (14, 6) AND receive_email = 1
        ORDER BY role_id ASC
        LIMIT 5
    ");
    $stmtDepe->execute();
    $depeUsers = $stmtDepe->fetchAll(PDO::FETCH_ASSOC);

    $candidateListHtml = implode('', array_map(
        fn($c) => '<li style="margin:2px 0;">' . htmlspecialchars($c['name']) . ' &lt;' . htmlspecialchars($c['email']) . '&gt;</li>',
        $candidates
    ));
    $nCandidates = count($candidates);

    foreach ($depeUsers as $depe) {
        $depeBody = EmailTemplate::wrap('
            <p>Olá, <strong>' . htmlspecialchars($depe['name']) . '</strong>!</p>
            <p>Esta mensagem é apenas para seu conhecimento. O(A) Prof(a). <strong>' . htmlspecialchars($teacherName) . '</strong>
            submeteu uma solicitação de substituição de aulas e indicou <strong>' . $nCandidates . ' colega(s)</strong> como candidato(s) a substituto.
            O sistema enviará e-mails a eles e acompanhará as respostas automaticamente.</p>
            <table style="width:100%;border-collapse:collapse;margin:20px 0;font-size:13px;">
                <tr style="background:#f9fafb;"><td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;width:40%;">Protocolo</td><td style="padding:8px 12px;border:1px solid #e5e7eb;font-family:monospace;">' . $protocol . '</td></tr>
                <tr><td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Docente</td><td style="padding:8px 12px;border:1px solid #e5e7eb;">' . htmlspecialchars($teacherName) . '</td></tr>
                <tr style="background:#f9fafb;"><td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Curso / Turma</td><td style="padding:8px 12px;border:1px solid #e5e7eb;">' . htmlspecialchars($courseName) . ($classGroup ? ' — ' . htmlspecialchars($classGroup) : '') . '</td></tr>
                <tr><td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Data(s)</td><td style="padding:8px 12px;border:1px solid #e5e7eb;">' . htmlspecialchars($datesFormatted) . '</td></tr>
                <tr style="background:#f9fafb;"><td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Turno(s)</td><td style="padding:8px 12px;border:1px solid #e5e7eb;">' . htmlspecialchars($slotsFormatted) . '</td></tr>
                <tr><td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Candidatos indicados</td><td style="padding:8px 12px;border:1px solid #e5e7eb;"><ul style="margin:0;padding-left:16px;">' . $candidateListHtml . '</ul></td></tr>
            </table>
            <p style="color:#6b7280;font-size:13px;">Você receberá novas notificações quando houver atualizações (aceite ou recusa). <strong>Nenhuma ação é necessária de sua parte neste momento.</strong></p>
        ');
        $emailService->queue($depe['email'], $depe['name'], "[Monitoramento] Pedido de substituição enviado — $protocol", $depeBody);
    }

    // 4. E-mail para cada candidato com links de aceite/recusa
    foreach ($candidates as $c) {
        $acceptUrl  = BASE_URL . '/substitute_respond.php?token=' . urlencode($c['token']) . '&action=accept';
        $declineUrl = BASE_URL . '/substitute_respond.php?token=' . urlencode($c['token']) . '&action=decline';

        $candBody = EmailTemplate::wrap('
            <p>Olá, <strong>Prof(a). ' . htmlspecialchars($c['name']) . '</strong>!</p>
            <p>O(A) Prof(a). <strong>' . htmlspecialchars($teacherName) . '</strong> precisará se ausentar
            e indicou você como possível substituto(a) para as aulas abaixo.</p>
            <table style="width:100%;border-collapse:collapse;margin:20px 0;font-size:13px;">
                <tr style="background:#f9fafb;"><td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;width:40%;">Protocolo</td><td style="padding:8px 12px;border:1px solid #e5e7eb;font-family:monospace;">' . $protocol . '</td></tr>
                <tr><td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Curso / Turma</td><td style="padding:8px 12px;border:1px solid #e5e7eb;">' . htmlspecialchars($courseName) . ($classGroup ? ' — ' . htmlspecialchars($classGroup) : '') . '</td></tr>
                ' . ($subjectName ? '<tr style="background:#f9fafb;"><td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">UC</td><td style="padding:8px 12px;border:1px solid #e5e7eb;">' . htmlspecialchars($subjectName) . '</td></tr>' : '') . '
                <tr ' . ($subjectName ? '' : 'style="background:#f9fafb;"') . '><td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Data(s)</td><td style="padding:8px 12px;border:1px solid #e5e7eb;">' . htmlspecialchars($datesFormatted) . '</td></tr>
                <tr style="background:#f9fafb;"><td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Turno(s)</td><td style="padding:8px 12px;border:1px solid #e5e7eb;">' . htmlspecialchars($slotsFormatted) . '</td></tr>
                <tr><td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Motivo</td><td style="padding:8px 12px;border:1px solid #e5e7eb;">' . nl2br(htmlspecialchars($reason)) . '</td></tr>
            </table>
            <p style="margin-bottom:8px;">Por favor, informe se consegue ajudar:</p>
            <table style="border-collapse:collapse;"><tr>
                <td style="padding-right:12px;">
                    <a href="' . $acceptUrl . '" style="display:inline-block;background:#16a34a;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;font-size:14px;">Aceitar substituição</a>
                </td>
                <td>
                    <a href="' . $declineUrl . '" style="display:inline-block;background:#f3f4f6;color:#374151;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;font-size:14px;border:1px solid #d1d5db;">Não consigo ajudar</a>
                </td>
            </tr></table>
            ' . ($nCandidates > 1 ? '<p style="color:#6b7280;font-size:12px;margin-top:16px;">Se houver mais de um candidato, basta que um confirme — os outros serão avisados automaticamente.</p>' : '') . '
        ');
        $emailService->queue($c['email'], $c['name'], "Pedido de ajuda: substituição de aulas — $protocol", $candBody);
    }
}

header('Location: dashboard.php?success=1&protocol=' . urlencode($protocol));
exit;
