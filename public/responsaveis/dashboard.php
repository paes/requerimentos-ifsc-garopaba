<?php
require_once 'guard.php';

$db   = new Database();
$conn = $db->getConnection();

// Busca alunos vinculados a este responsável
$stmt = $conn->prepare("SELECT * FROM guardian_students WHERE guardian_id = :gid ORDER BY student_name ASC");
$stmt->execute([':gid' => $guardianId]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Início</h1>
        <p class="text-gray-500 text-sm mt-1">Bem-vindo(a) ao Portal de Responsáveis do IFSC Câmpus Garopaba.</p>
    </div>

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

    <!-- Aviso sobre funcionalidades futuras -->
    <div class="bg-blue-50 border border-blue-100 rounded-xl p-5 text-sm text-blue-700">
        <p class="font-semibold mb-1">Em breve neste portal:</p>
        <ul class="list-disc ml-4 space-y-0.5 text-blue-600">
            <li>Protocolação de pedidos em nome dos alunos</li>
            <li>Acompanhamento de requerimentos</li>
            <li>Autorizações com assinatura digital</li>
        </ul>
        <p class="mt-2 text-xs text-blue-500">Dúvidas? Entre em contato com a Coordenação Pedagógica do câmpus.</p>
    </div>

</main>
</body>
</html>
