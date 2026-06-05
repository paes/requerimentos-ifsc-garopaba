<?php
require_once 'guard.php';
require_once '../../src/AscXmlParser.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: dashboard.php');
    exit;
}

// Buscar requerimento — garante que pertence ao docente logado
$stmt = $conn->prepare("
    SELECT tr.*, c.name AS course_name,
           t.name AS suggested_teacher_name
    FROM teacher_requests tr
    JOIN courses c ON c.id = tr.course_id
    LEFT JOIN teachers t ON t.id = tr.suggested_teacher_id
    WHERE tr.id = :id AND tr.teacher_id = :tid
");
$stmt->execute([':id' => $id, ':tid' => $teacherId]);
$req = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$req) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Detalhes — ' . $req['protocol_code'];

// Histórico
$stmtHist = $conn->prepare("
    SELECT trh.*, u.name AS user_name, r.name AS role_name
    FROM teacher_request_history trh
    JOIN users u ON u.id = trh.user_id
    JOIN roles r ON r.id = u.role_id
    WHERE trh.teacher_request_id = :id
    ORDER BY trh.created_at ASC
");
$stmtHist->execute([':id' => $id]);
$history = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

$absenceDates = json_decode($req['absence_dates'] ?? '[]', true);
$timeSlots    = json_decode($req['time_slots'] ?? '[]', true);

$statusLabels = [
    'pending'   => ['label' => 'Em análise',  'class' => 'bg-yellow-100 text-yellow-800'],
    'approved'  => ['label' => 'Aprovado',    'class' => 'bg-green-100 text-green-800'],
    'rejected'  => ['label' => 'Indeferido',  'class' => 'bg-red-100 text-red-800'],
    'concluded' => ['label' => 'Concluído',   'class' => 'bg-blue-100 text-blue-800'],
];

$stepLabels = [
    1 => 'Aguardando Coordenação de Curso',
    2 => 'Aguardando Assessoria DEPE',
    3 => 'Aguardando DEPE',
];

$actionLabels = [
    'approve' => ['label' => 'Aprovado', 'class' => 'text-green-600'],
    'reject'  => ['label' => 'Indeferido', 'class' => 'text-red-600'],
    'comment' => ['label' => 'Comentário', 'class' => 'text-gray-600'],
];

require_once 'layout/header.php';
require_once 'layout/sidebar.php';
?>

<main class="flex-1 overflow-y-auto p-8">
    <div class="max-w-3xl mx-auto">

        <div class="mb-8">
            <a href="dashboard.php" class="text-sm text-gray-400 hover:text-gray-600 inline-flex items-center gap-1 mb-4">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Minhas Solicitações
            </a>
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($req['protocol_code']) ?></h1>
                <?php $st = $statusLabels[$req['status']] ?? ['label' => $req['status'], 'class' => 'bg-gray-100 text-gray-700']; ?>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $st['class'] ?>">
                    <?= $st['label'] ?>
                </span>
            </div>
            <?php if ($req['status'] === 'pending'): ?>
                <p class="text-sm text-gray-500 mt-1"><?= $stepLabels[$req['current_step_order']] ?? '' ?></p>
            <?php endif; ?>
        </div>

        <!-- Detalhes da solicitação -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Dados da Solicitação</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 text-sm">
                <div>
                    <dt class="font-medium text-gray-500">Curso</dt>
                    <dd class="text-gray-900 mt-0.5"><?= htmlspecialchars($req['course_name']) ?></dd>
                </div>
                <?php if ($req['class_group']): ?>
                <div>
                    <dt class="font-medium text-gray-500">Turma</dt>
                    <dd class="text-gray-900 mt-0.5"><?= htmlspecialchars($req['class_group']) ?></dd>
                </div>
                <?php endif; ?>
                <?php if ($req['subject_name']): ?>
                <div>
                    <dt class="font-medium text-gray-500">UC</dt>
                    <dd class="text-gray-900 mt-0.5"><?= htmlspecialchars($req['subject_name']) ?></dd>
                </div>
                <?php endif; ?>
                <div>
                    <dt class="font-medium text-gray-500">Data(s) de ausência</dt>
                    <dd class="text-gray-900 mt-0.5">
                        <?= implode(', ', array_map(fn($d) => date('d/m/Y', strtotime($d)), $absenceDates)) ?>
                    </dd>
                </div>
                <?php if (!empty($timeSlots)): ?>
                <div>
                    <dt class="font-medium text-gray-500">Turno(s)</dt>
                    <dd class="text-gray-900 mt-0.5"><?= implode(', ', array_map(fn($s) => AscXmlParser::TIME_SLOT_LABELS[$s] ?? $s, $timeSlots)) ?></dd>
                </div>
                <?php endif; ?>
                <?php if ($req['suggested_teacher_name']): ?>
                <div>
                    <dt class="font-medium text-gray-500">Substituto sugerido</dt>
                    <dd class="text-gray-900 mt-0.5"><?= htmlspecialchars($req['suggested_teacher_name']) ?></dd>
                </div>
                <?php endif; ?>
                <div class="sm:col-span-2">
                    <dt class="font-medium text-gray-500">Motivo</dt>
                    <dd class="text-gray-900 mt-0.5"><?= nl2br(htmlspecialchars($req['reason'] ?? '')) ?></dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500">Enviado em</dt>
                    <dd class="text-gray-900 mt-0.5"><?= date('d/m/Y H:i', strtotime($req['created_at'])) ?></dd>
                </div>
            </dl>
        </div>

        <!-- Linha do tempo -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Linha do Tempo</h2>

            <!-- Passo fixo: Envio pelo docente -->
            <div class="flex gap-4 mb-4">
                <div class="flex flex-col items-center">
                    <div class="w-8 h-8 rounded-full bg-green-500 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <?php if (!empty($history) || $req['status'] === 'pending'): ?>
                        <div class="w-0.5 flex-1 bg-gray-200 mt-1"></div>
                    <?php endif; ?>
                </div>
                <div class="pb-4">
                    <p class="font-semibold text-sm text-gray-900">Solicitação enviada</p>
                    <p class="text-xs text-gray-400"><?= date('d/m/Y H:i', strtotime($req['created_at'])) ?></p>
                </div>
            </div>

            <!-- Histórico de ações -->
            <?php foreach ($history as $i => $h): ?>
                <?php $act = $actionLabels[$h['action']] ?? ['label' => $h['action'], 'class' => 'text-gray-600']; ?>
                <div class="flex gap-4 mb-4">
                    <div class="flex flex-col items-center">
                        <div class="w-8 h-8 rounded-full <?= $h['action'] === 'approve' ? 'bg-green-500' : ($h['action'] === 'reject' ? 'bg-red-500' : 'bg-gray-400') ?> flex items-center justify-center flex-shrink-0">
                            <?php if ($h['action'] === 'approve'): ?>
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                                </svg>
                            <?php elseif ($h['action'] === 'reject'): ?>
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            <?php else: ?>
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <?php if ($i < count($history) - 1 || $req['status'] === 'pending'): ?>
                            <div class="w-0.5 flex-1 bg-gray-200 mt-1"></div>
                        <?php endif; ?>
                    </div>
                    <div class="pb-4">
                        <p class="font-semibold text-sm <?= $act['class'] ?>"><?= $act['label'] ?></p>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars($h['role_name']) ?> — <?= htmlspecialchars($h['user_name']) ?></p>
                        <p class="text-xs text-gray-400"><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></p>
                        <?php if ($h['observation']): ?>
                            <p class="text-sm text-gray-700 bg-gray-50 rounded p-2 mt-1"><?= nl2br(htmlspecialchars($h['observation'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Step atual (se pendente) -->
            <?php if ($req['status'] === 'pending'): ?>
                <div class="flex gap-4">
                    <div class="w-8 h-8 rounded-full bg-amber-400 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M5 2h14L12 12 5 2zM5 22h14L12 12 5 22z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-semibold text-sm text-amber-700"><?= $stepLabels[$req['current_step_order']] ?? 'Em análise' ?></p>
                        <p class="text-xs text-gray-400">Aguardando resposta</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<?php require_once 'layout/footer.php'; ?>
