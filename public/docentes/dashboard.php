<?php
require_once 'guard.php';

$pageTitle = 'Minhas Solicitações';

// Buscar requerimentos do docente logado
$stmt = $conn->prepare("
    SELECT tr.*, c.name AS course_name,
           t.name AS suggested_teacher_name
    FROM teacher_requests tr
    JOIN courses c ON c.id = tr.course_id
    LEFT JOIN teachers t ON t.id = tr.suggested_teacher_id
    WHERE tr.teacher_id = :tid
    ORDER BY tr.created_at DESC
");
$stmt->execute([':tid' => $teacherId]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

require_once 'layout/header.php';
require_once 'layout/sidebar.php';
?>

<main class="flex-1 overflow-y-auto p-8">
    <div class="max-w-5xl mx-auto">

        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Minhas Solicitações</h1>
                <p class="text-sm text-gray-500 mt-1">Requerimentos de substituição de aulas enviados por você</p>
            </div>
            <a href="novo.php"
                class="inline-flex items-center px-4 py-2 bg-brand-DEFAULT text-white text-sm font-medium rounded-lg hover:bg-brand-dark transition-colors shadow-sm">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Nova Solicitação
            </a>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span>Solicitação enviada com sucesso! Protocolo: <strong><?= htmlspecialchars($_GET['protocol'] ?? '') ?></strong></span>
            </div>
        <?php endif; ?>

        <?php if (empty($requests)): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                <p class="text-gray-500 text-sm">Você ainda não enviou nenhuma solicitação.</p>
                <a href="novo.php" class="mt-4 inline-block text-brand-DEFAULT text-sm font-medium hover:underline">
                    Criar primeira solicitação
                </a>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($requests as $req): ?>
                    <?php
                    $st = $statusLabels[$req['status']] ?? ['label' => $req['status'], 'class' => 'bg-gray-100 text-gray-700'];
                    $dates = json_decode($req['absence_dates'] ?? '[]', true);
                    $datesStr = implode(', ', array_map(fn($d) => date('d/m/Y', strtotime($d)), $dates));
                    ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-3 mb-2 flex-wrap">
                                    <span class="font-mono text-xs font-bold text-gray-500 bg-gray-100 px-2 py-0.5 rounded">
                                        <?= htmlspecialchars($req['protocol_code']) ?>
                                    </span>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $st['class'] ?>">
                                        <?= $st['label'] ?>
                                    </span>
                                    <?php if ($req['status'] === 'pending'): ?>
                                        <span class="text-xs text-gray-400">
                                            <?= $stepLabels[$req['current_step_order']] ?? '' ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <h3 class="font-semibold text-gray-900">
                                    <?= htmlspecialchars($req['course_name']) ?>
                                    <?php if ($req['class_group']): ?>
                                        — <?= htmlspecialchars($req['class_group']) ?>
                                    <?php endif; ?>
                                </h3>
                                <?php if ($req['subject_name']): ?>
                                    <p class="text-sm text-gray-600 mt-0.5"><?= htmlspecialchars($req['subject_name']) ?></p>
                                <?php endif; ?>
                                <p class="text-xs text-gray-400 mt-1">
                                    <?= $datesStr ?: '—' ?>
                                    · Enviado em <?= date('d/m/Y H:i', strtotime($req['created_at'])) ?>
                                </p>
                            </div>
                            <a href="detalhes.php?id=<?= $req['id'] ?>"
                                class="flex-shrink-0 text-sm text-brand-DEFAULT hover:text-brand-dark font-medium">
                                Ver detalhes →
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</main>

<?php require_once 'layout/footer.php'; ?>
