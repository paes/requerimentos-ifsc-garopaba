<?php
require_once 'guard.php';

$db   = new Database();
$conn = $db->getConnection();

// Alunos vinculados
$stmt = $conn->prepare("SELECT * FROM guardian_students WHERE guardian_id = :gid ORDER BY student_name ASC");
$stmt->execute([':gid' => $guardianId]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Requerimentos protocolados pelo responsável
$reqStmt = $conn->prepare("
    SELECT r.protocol_code, r.student_name, rt.name AS type_name, r.status, r.created_at
    FROM requests r
    JOIN request_types rt ON rt.id = r.request_type_id
    WHERE r.submitted_by_guardian_id = :gid
    ORDER BY r.created_at DESC
    LIMIT 20
");
$reqStmt->execute([':gid' => $guardianId]);
$myRequests = $reqStmt->fetchAll(PDO::FETCH_ASSOC);

$successProtocol = $_GET['success'] ?? '';

$statusLabels = [
    'pending'    => ['label' => 'Pendente',   'class' => 'bg-amber-100 text-amber-700'],
    'in_review'  => ['label' => 'Em análise', 'class' => 'bg-blue-100 text-blue-700'],
    'approved'   => ['label' => 'Aprovado',   'class' => 'bg-green-100 text-green-700'],
    'rejected'   => ['label' => 'Indeferido', 'class' => 'bg-red-100 text-red-700'],
    'completed'  => ['label' => 'Concluído',  'class' => 'bg-gray-100 text-gray-600'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <script>document.documentElement.setAttribute('data-theme', localStorage.getItem('req_theme') || 'default')</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal de Responsáveis — IFSC Garopaba</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/themes.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/favicon.ico">
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
</head>
<body class="bg-[#F2F4F8] min-h-screen">

<!-- Barra superior -->
<header class="bg-white border-b border-gray-100 shadow-sm sticky top-0 z-10">
    <div class="max-w-4xl mx-auto px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="IFSC" class="h-8">
            <div>
                <p class="text-xs text-gray-400 leading-tight">Portal de Responsáveis</p>
                <p class="text-sm font-semibold text-gray-700 leading-tight"><?= htmlspecialchars($guardianName) ?></p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div class="theme-switcher">
                <button class="theme-btn text-gray-400 text-xs" data-t="default" title="Esmeralda">💎</button>
                <button class="theme-btn text-gray-400 text-xs" data-t="ifsc" title="IFSC">🍃</button>
                <button class="theme-btn text-gray-400 text-xs" data-t="noturno" title="Noturno">🌙</button>
            </div>
            <a href="logout.php" class="text-sm text-red-400 hover:text-red-600 font-medium transition-colors">Sair</a>
        </div>
    </div>
</header>

<main class="max-w-4xl mx-auto px-4 py-8">

    <div class="mb-6 flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Início</h1>
            <p class="text-gray-500 text-sm mt-1">Bem-vindo(a) ao Portal de Responsáveis do IFSC Câmpus Garopaba.</p>
        </div>
        <a href="novo_requerimento.php"
            class="inline-flex items-center gap-2 font-semibold text-sm px-4 py-2.5 rounded-lg text-white transition-colors"
            style="background:#1CBB9B;" onmouseover="this.style.background='#169C80'" onmouseout="this.style.background='#1CBB9B'">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Novo Requerimento
        </a>
    </div>

    <?php if ($successProtocol): ?>
    <div class="mb-4 bg-green-50 border border-green-200 text-green-800 text-sm font-medium px-4 py-3 rounded-xl flex items-center gap-2">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Requerimento protocolado com sucesso! Protocolo: <strong class="font-mono"><?= htmlspecialchars($successProtocol) ?></strong>
    </div>
    <?php endif; ?>

    <!-- Alunos vinculados -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="font-bold text-gray-700">Alunos sob sua responsabilidade</h2>
        </div>
        <?php if (empty($students)): ?>
        <div class="px-6 py-8 text-center text-gray-400 text-sm">
            Nenhum aluno vinculado. Entre em contato com a Coordenação Pedagógica.
        </div>
        <?php else: ?>
        <div class="divide-y divide-gray-50">
            <?php foreach ($students as $s): ?>
            <div class="px-6 py-4 flex items-center justify-between">
                <div>
                    <p class="font-semibold text-gray-800 text-sm"><?= htmlspecialchars($s['student_name']) ?></p>
                    <p class="text-xs text-gray-400">Matrícula: <?= htmlspecialchars($s['student_matricula']) ?></p>
                </div>
                <?php if ($s['verified']): ?>
                <span class="text-xs bg-green-100 text-green-700 font-semibold px-2.5 py-1 rounded-full">Verificado</span>
                <?php else: ?>
                <span class="text-xs bg-amber-100 text-amber-700 font-semibold px-2.5 py-1 rounded-full">Aguardando verificação</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Requerimentos protocolados -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-bold text-gray-700">Meus Requerimentos</h2>
            <a href="novo_requerimento.php" class="text-xs font-semibold text-brand-DEFAULT hover:underline">+ Novo</a>
        </div>
        <?php if (empty($myRequests)): ?>
        <div class="px-6 py-8 text-center text-gray-400 text-sm">
            Nenhum requerimento protocolado ainda.
            <a href="novo_requerimento.php" class="block mt-2 text-brand-DEFAULT hover:underline font-medium">Protocolar agora</a>
        </div>
        <?php else: ?>
        <div class="divide-y divide-gray-50">
            <?php foreach ($myRequests as $req):
                $sl = $statusLabels[$req['status']] ?? ['label' => ucfirst($req['status']), 'class' => 'bg-gray-100 text-gray-600'];
            ?>
            <div class="px-6 py-3 flex items-center gap-4 flex-wrap">
                <span class="font-mono text-xs text-gray-400 shrink-0"><?= htmlspecialchars($req['protocol_code']) ?></span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-800 truncate"><?= htmlspecialchars($req['type_name']) ?></p>
                    <p class="text-xs text-gray-400"><?= htmlspecialchars($req['student_name']) ?> · <?= date('d/m/Y', strtotime($req['created_at'])) ?></p>
                </div>
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full shrink-0 <?= $sl['class'] ?>">
                    <?= $sl['label'] ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="text-center text-xs text-gray-400 mt-2">
        Dúvidas? Entre em contato com a Coordenação Pedagógica do câmpus.
    </div>

</main>
</body>
</html>
