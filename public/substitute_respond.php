<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../src/EmailService.php';
require_once '../src/EmailTemplate.php';
require_once '../src/AscXmlParser.php';

$token  = trim($_GET['token']  ?? '');
$action = trim($_GET['action'] ?? '');

function formatDateWithDow(string $ymd): string {
    $dows = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
    $ts   = strtotime($ymd);
    return $dows[(int)date('w', $ts)] . ', ' . date('d/m/Y', $ts);
}

function renderPage(string $title, string $emoji, string $heading, string $body, string $colorClass = 'text-gray-700'): void {
    echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . htmlspecialchars($title) . ' — IFSC Garopaba</title>
<link rel="stylesheet" href="' . BASE_URL . '/assets/css/tailwind.css">
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-6">
<div class="max-w-md w-full bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
    <div class="text-5xl mb-4">' . $emoji . '</div>
    <h1 class="text-xl font-bold ' . $colorClass . ' mb-3">' . htmlspecialchars($heading) . '</h1>
    <p class="text-sm text-gray-600 leading-relaxed">' . $body . '</p>
    <p class="mt-6 text-xs text-gray-400">IFSC Câmpus Garopaba — Sistema de Requerimentos</p>
</div>
</body>
</html>';
}

// Validações básicas
if (!$token || !in_array($action, ['accept', 'decline'], true)) {
    renderPage('Link inválido', '⚠️', 'Link inválido', 'Este link de resposta é inválido ou está incompleto. Verifique o e-mail recebido e tente novamente.');
    exit;
}

$db   = new Database();
$conn = $db->getConnection();

// Buscar candidato pelo token
$stmtFind = $conn->prepare("
    SELECT rcs.*,
           tr.id              AS request_id,
           tr.protocol_code,
           tr.teacher_name    AS requester_name,
           tr.teacher_email   AS requester_email,
           tr.class_group,
           tr.absence_dates,
           tr.time_slots,
           c.name             AS course_name
    FROM request_candidate_substitutes rcs
    JOIN teacher_requests tr ON tr.id = rcs.teacher_request_id
    JOIN courses c           ON c.id  = tr.course_id
    WHERE rcs.token = :token
");
$stmtFind->execute([':token' => $token]);
$cand = $stmtFind->fetch(PDO::FETCH_ASSOC);

if (!$cand) {
    renderPage('Link inválido', '⚠️', 'Link não encontrado', 'Este link de resposta não existe ou expirou. Verifique o e-mail recebido.');
    exit;
}

if ($cand['status'] !== 'pending') {
    $msg = match($cand['status']) {
        'accepted'  => 'Você já confirmou sua participação nesta substituição. Obrigado!',
        'declined'  => 'Você já registrou que não pode ajudar nesta substituição.',
        'cancelled' => 'Esta solicitação já foi resolvida por outro colega. Obrigado pela disponibilidade!',
        default     => 'Esta solicitação já foi respondida.',
    };
    renderPage('Já respondido', 'ℹ️', 'Resposta já registrada', $msg, 'text-blue-700');
    exit;
}

$emailService  = new EmailService($conn);
$protocol      = $cand['protocol_code'];
$requesterName = $cand['requester_name'];
$requesterEmail= $cand['requester_email'];
$candidateName = $cand['teacher_name'];
$requestId     = $cand['request_id'];
$candidateId   = $cand['id'];

$absenceDates   = json_decode($cand['absence_dates'], true) ?? [];
$timeSlots      = json_decode($cand['time_slots'],    true) ?? [];
$datesFormatted = implode('; ', array_map('formatDateWithDow', $absenceDates));
$slotsFormatted = implode(', ', array_map(fn($s) => AscXmlParser::TIME_SLOT_LABELS[$s] ?? $s, $timeSlots));
$classInfo      = $cand['course_name'] . ($cand['class_group'] ? ' — ' . $cand['class_group'] : '');

// Buscar monitores (Assessoria DEPE / DEPE)
$stmtDepe = $conn->prepare("
    SELECT name, email FROM users
    WHERE role_id IN (14, 6) AND receive_email = 1
    ORDER BY role_id ASC LIMIT 5
");
$stmtDepe->execute();
$depeUsers = $stmtDepe->fetchAll(PDO::FETCH_ASSOC);

if ($action === 'accept') {
    // Marcar este candidato como aceito
    $conn->prepare("UPDATE request_candidate_substitutes SET status='accepted', responded_at=NOW() WHERE id=:id")
         ->execute([':id' => $candidateId]);

    // Buscar candidatos pendentes (para cancelar e notificar)
    $stmtOthers = $conn->prepare("
        SELECT id, teacher_name, teacher_email
        FROM request_candidate_substitutes
        WHERE teacher_request_id = :rid AND id != :id AND status = 'pending'
    ");
    $stmtOthers->execute([':rid' => $requestId, ':id' => $candidateId]);
    $others = $stmtOthers->fetchAll(PDO::FETCH_ASSOC);

    // Cancelar os demais pendentes
    if (!empty($others)) {
        $conn->prepare("
            UPDATE request_candidate_substitutes
            SET status='cancelled' WHERE teacher_request_id=:rid AND id!=:id AND status='pending'
        ")->execute([':rid' => $requestId, ':id' => $candidateId]);
    }

    // E-mail para o requester
    $requesterBody = EmailTemplate::wrap('
        <p>Olá, <strong>' . htmlspecialchars($requesterName) . '</strong>!</p>
        <p>Boa notícia! O(A) Prof(a). <strong>' . htmlspecialchars($candidateName) . '</strong> aceitou realizar a substituição das suas aulas.</p>
        <table style="width:100%;border-collapse:collapse;margin:20px 0;font-size:13px;">
            <tr style="background:#f9fafb;"><td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;width:40%;">Protocolo</td><td style="padding:8px 12px;border:1px solid #e5e7eb;font-family:monospace;">' . $protocol . '</td></tr>
            <tr><td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Substituto(a)</td><td style="padding:8px 12px;border:1px solid #e5e7eb;">' . htmlspecialchars($candidateName) . '</td></tr>
            <tr style="background:#f9fafb;"><td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Turma</td><td style="padding:8px 12px;border:1px solid #e5e7eb;">' . htmlspecialchars($classInfo) . '</td></tr>
            <tr><td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Data(s)</td><td style="padding:8px 12px;border:1px solid #e5e7eb;">' . htmlspecialchars($datesFormatted) . '</td></tr>
            <tr style="background:#f9fafb;"><td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Turno(s)</td><td style="padding:8px 12px;border:1px solid #e5e7eb;">' . htmlspecialchars($slotsFormatted) . '</td></tr>
        </table>
        <p style="color:#6b7280;font-size:13px;">Lembre-se de alinhar os detalhes com o(a) colega substituto(a) e comunicar a coordenação do curso.</p>
    ');
    $emailService->queue($requesterEmail, $requesterName, "Substituição confirmada! Prof(a). $candidateName aceitou — $protocol", $requesterBody);

    // E-mail para candidatos cancelados
    foreach ($others as $other) {
        $cancelBody = EmailTemplate::wrap('
            <p>Olá, <strong>Prof(a). ' . htmlspecialchars($other['teacher_name']) . '</strong>!</p>
            <p>O pedido de substituição do protocolo <strong>' . $protocol . '</strong> foi resolvido —
            o(a) Prof(a). <strong>' . htmlspecialchars($candidateName) . '</strong> ficará responsável pela substituição.
            Sua resposta não é mais necessária.</p>
            <p>Obrigado pela disponibilidade!</p>
        ');
        $emailService->queue($other['teacher_email'], $other['teacher_name'], "Substituição resolvida — $protocol", $cancelBody);
    }

    // E-mail de monitoramento para Assessoria/DEPE
    foreach ($depeUsers as $depe) {
        $monBody = EmailTemplate::wrap('
            <p>Olá, <strong>' . htmlspecialchars($depe['name']) . '</strong>!</p>
            <p>[Monitoramento] A substituição do protocolo <strong>' . $protocol . '</strong> foi confirmada.</p>
            <table style="width:100%;border-collapse:collapse;margin:20px 0;font-size:13px;">
                <tr style="background:#f9fafb;"><td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;width:40%;">Protocolo</td><td style="padding:8px 12px;border:1px solid #e5e7eb;font-family:monospace;">' . $protocol . '</td></tr>
                <tr><td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Docente ausente</td><td style="padding:8px 12px;border:1px solid #e5e7eb;">' . htmlspecialchars($requesterName) . '</td></tr>
                <tr style="background:#f9fafb;"><td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Substituto(a)</td><td style="padding:8px 12px;border:1px solid #e5e7eb;">' . htmlspecialchars($candidateName) . '</td></tr>
                <tr><td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Turma</td><td style="padding:8px 12px;border:1px solid #e5e7eb;">' . htmlspecialchars($classInfo) . '</td></tr>
                <tr style="background:#f9fafb;"><td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Data(s)</td><td style="padding:8px 12px;border:1px solid #e5e7eb;">' . htmlspecialchars($datesFormatted) . '</td></tr>
            </table>
            <p style="color:#6b7280;font-size:13px;">Nenhuma ação é necessária de sua parte.</p>
        ');
        $emailService->queue($depe['email'], $depe['name'], "[Monitoramento] Substituição confirmada — $protocol", $monBody);
    }

    renderPage('Substituição confirmada', '✅', 'Confirmação registrada!',
        'Obrigado, Prof(a). ' . htmlspecialchars($candidateName) . '! Sua confirmação foi registrada. '
        . 'O(A) Prof(a). ' . htmlspecialchars($requesterName) . ' será notificado(a) por e-mail.',
        'text-green-700');

} else {
    // RECUSAR
    $conn->prepare("UPDATE request_candidate_substitutes SET status='declined', responded_at=NOW() WHERE id=:id")
         ->execute([':id' => $candidateId]);

    // Verificar se ainda há candidatos pendentes
    $stmtPending = $conn->prepare("
        SELECT COUNT(*) FROM request_candidate_substitutes
        WHERE teacher_request_id = :rid AND status = 'pending'
    ");
    $stmtPending->execute([':rid' => $requestId]);
    $stillPending = (int)$stmtPending->fetchColumn();

    if ($stillPending === 0) {
        // Todos recusaram — escalação para Assessoria/DEPE
        foreach ($depeUsers as $depe) {
            $escalateBody = EmailTemplate::wrap('
                <p>Olá, <strong>' . htmlspecialchars($depe['name']) . '</strong>!</p>
                <p style="color:#b91c1c;font-weight:bold;">Atenção: todos os candidatos recusaram a substituição.</p>
                <p>O protocolo <strong>' . $protocol . '</strong> do(a) Prof(a). <strong>' . htmlspecialchars($requesterName) . '</strong>
                não possui mais candidatos disponíveis. É necessária intervenção manual para resolver a substituição.</p>
                <table style="width:100%;border-collapse:collapse;margin:20px 0;font-size:13px;">
                    <tr style="background:#f9fafb;"><td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;width:40%;">Protocolo</td><td style="padding:8px 12px;border:1px solid #e5e7eb;font-family:monospace;">' . $protocol . '</td></tr>
                    <tr><td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Docente</td><td style="padding:8px 12px;border:1px solid #e5e7eb;">' . htmlspecialchars($requesterName) . '</td></tr>
                    <tr style="background:#f9fafb;"><td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Turma</td><td style="padding:8px 12px;border:1px solid #e5e7eb;">' . htmlspecialchars($classInfo) . '</td></tr>
                    <tr><td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Data(s)</td><td style="padding:8px 12px;border:1px solid #e5e7eb;">' . htmlspecialchars($datesFormatted) . '</td></tr>
                    <tr style="background:#f9fafb;"><td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:bold;">Turno(s)</td><td style="padding:8px 12px;border:1px solid #e5e7eb;">' . htmlspecialchars($slotsFormatted) . '</td></tr>
                </table>
                <p><a href="' . BASE_URL . '/admin/teacher_requests.php" style="display:inline-block;background:#dc2626;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;font-weight:bold;">Ver solicitação no painel</a></p>
            ');
            $emailService->queue($depe['email'], $depe['name'], "[ATENÇÃO] Todos recusaram — substituição sem solução — $protocol", $escalateBody);
        }
        // Notificar requester também
        $noSolBody = EmailTemplate::wrap('
            <p>Olá, <strong>' . htmlspecialchars($requesterName) . '</strong>!</p>
            <p>Infelizmente todos os colegas indicados para a substituição do protocolo <strong>' . $protocol . '</strong>
            informaram que não conseguem ajudar. A Assessoria DEPE foi notificada e entrará em contato para resolver a situação.</p>
        ');
        $emailService->queue($requesterEmail, $requesterName, "Nenhum substituto disponível — $protocol", $noSolBody);

    } else {
        // Ainda há pendentes — monitoramento para Assessoria
        foreach ($depeUsers as $depe) {
            $monBody = EmailTemplate::wrap('
                <p>Olá, <strong>' . htmlspecialchars($depe['name']) . '</strong>!</p>
                <p>[Monitoramento] O(A) Prof(a). <strong>' . htmlspecialchars($candidateName) . '</strong>
                recusou a substituição do protocolo <strong>' . $protocol . '</strong>.
                Ainda há <strong>' . $stillPending . ' candidato(s) pendente(s)</strong> que serão contactados.</p>
                <p style="color:#6b7280;font-size:13px;">Nenhuma ação é necessária de sua parte por enquanto.</p>
            ');
            $emailService->queue($depe['email'], $depe['name'], "[Monitoramento] Candidato recusou — $protocol", $monBody);
        }
    }

    renderPage('Resposta registrada', '🙏', 'Resposta registrada',
        'Obrigado, Prof(a). ' . htmlspecialchars($candidateName) . '! Sua resposta foi registrada. '
        . ($stillPending > 0
            ? 'Os demais colegas indicados serão contactados.'
            : 'A Assessoria DEPE foi notificada para resolver a situação.'),
        'text-gray-700');
}
