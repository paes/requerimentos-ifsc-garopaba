<?php
/**
 * Pagina publica para que o aluno possa consultar o andamento e status do seu requerimento usando o numero de protocolo.
 *
 * @author Prof. Eduardo Gomes
 */
?>
<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../src/Helpers.php';

$result = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificacao do Turnstile
    $turnstileResponse = $_POST['cf-turnstile-response'] ?? '';
    
    // Usando chave secreta de producao igual a do admin/index.php
    if (ENABLE_TURNSTILE) {
        $turnstileResult = Helpers::verifyTurnstile($turnstileResponse, TURNSTILE_SECRET_KEY);
        $isValid = $turnstileResult['success'];
        $errorMessage = $turnstileResult['message'] ?? '';
    } else {
        $isValid = true;
    }
    
    if (!$isValid) {
        $error = $errorMessage;
    } else {
        $email = $_POST['email'];
        $protocol = $_POST['protocol'];
        
        $db = new Database();
        $conn = $db->getConnection();
        
        $query = "
            SELECT r.*, rt.name as type_name 
            FROM requests r
            JOIN request_types rt ON r.request_type_id = rt.id
            WHERE r.student_email = :email AND r.protocol_code = :protocol
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([':email' => $email, ':protocol' => $protocol]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Busca Historico
            $histStmt = $conn->prepare("
                SELECT rh.*, u.name as user_name
                FROM request_history rh
                LEFT JOIN users u ON rh.user_id = u.id
                WHERE rh.request_id = :id
                ORDER BY rh.created_at DESC
            ");
            $histStmt->execute([':id' => $result['id']]);
            $history = $histStmt->fetchAll(PDO::FETCH_ASSOC);

            // Busca etapas do workflow para exibição visual
            $stmtSteps = $conn->prepare("
                SELECT ws.step_order, ro.name as role_name
                FROM workflow_steps ws
                JOIN roles ro ON ws.role_id = ro.id
                WHERE ws.request_type_id = :type_id
                ORDER BY ws.step_order ASC
            ");
            $stmtSteps->execute([':type_id' => $result['request_type_id']]);
            $workflowSteps = $stmtSteps->fetchAll(PDO::FETCH_ASSOC);

            $roleLabels = [
                'Coordenador de Curso'     => 'Aguardando resposta da Coordenação de Curso',
                'Secretaria'               => 'Com a Secretaria Acadêmica',
                'Coordenador Pedagógico'   => 'Aguardando resposta da Coordenadoria Pedagógica',
                'Assistência Estudantil'   => 'Aguardando resposta da Assistência Estudantil',
                'Administrador'            => 'Em análise administrativa',
            ];
        } else {
            $error = 'Requerimento não encontrado. Verifique os dados informados.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR"><head>
    <script>document.documentElement.setAttribute('data-theme', localStorage.getItem('req_theme') || 'default')</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultar Protocolo - IFSC</title>
    <?php if (ENABLE_TURNSTILE): ?>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/themes.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .glass { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); }
        .gradient-bg { background: #F2F4F8; }
    </style>
</head>
<body class="gradient-bg min-h-screen flex flex-col text-gray-800">

    <header class="theme-header text-white shadow-lg">
        <div class="container mx-auto px-6 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <img src="<?= BASE_URL ?>/assets/img/logob.png" alt="IFSC Logo" class="h-10 brightness-0 invert opacity-90 hover:opacity-100 transition-opacity">
                <div class="hidden md:block border-l border-white/30 pl-4">
                    <h1 class="text-lg font-bold tracking-tight">Instituto Federal de Santa Catarina</h1>
                    <p class="text-white/80 text-xs font-medium uppercase tracking-wider">Sistema de Requerimentos</p>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <div class="theme-switcher">
                    <button class="theme-btn" data-t="default" title="Esmeralda">💎</button>
                    <button class="theme-btn" data-t="ifsc"    title="IFSC">🍃</button>
                    <button class="theme-btn" data-t="noturno" title="Noturno">🌙</button>
                </div>
                <a href="<?= BASE_URL ?>/index.php" class="text-sm font-medium text-white/90 hover:text-white transition-colors flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Voltar ao Início
                </a>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-12 flex-grow max-w-3xl">
        
        <div class="text-center mb-10">
            <h2 class="text-3xl font-extrabold text-gray-900 mb-2 tracking-tight">Consultar Situação</h2>
            <p class="text-gray-600">Acompanhe o andamento do seu requerimento.</p>
        </div>
        
        <div class="glass rounded-2xl shadow-xl p-8 border border-white/50 mb-8">
            <form method="POST" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="group">
                        <label class="block text-sm font-medium text-gray-700 mb-2 group-focus-within:text-[#1CBB9B] transition-colors">E-mail Institucional</label>
                        <input type="email" name="email" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent transition-all outline-none">
                    </div>
                    <div class="group">
                        <label class="block text-sm font-medium text-gray-700 mb-2 group-focus-within:text-[#1CBB9B] transition-colors">Número do Protocolo</label>
                        <input type="text" name="protocol" placeholder="Ex: 2025-2-0001" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent transition-all outline-none">
                    </div>
                </div>
                
                <!-- Turnstile Widget -->
                <?php if (ENABLE_TURNSTILE): ?>
                    <div class="cf-turnstile mb-4" data-sitekey="<?= TURNSTILE_SITE_KEY ?>"></div>
                <?php endif; ?>
                
                <button type="submit" class="w-full bg-[#1CBB9B] text-white py-4 rounded-xl hover:bg-[#169C80] transition-all font-bold shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">Consultar</button>
            </form>

            <?php if ($error): ?>
                <div class="mt-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-center text-sm font-medium animate-pulse">
                    <?= $error ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($result): ?>
            <div class="glass rounded-2xl shadow-xl p-8 border border-white/50 animate-fade-in-up">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 border-b border-gray-100 pb-6">
                    <div>
                        <h3 class="text-2xl font-bold text-gray-800">Protocolo: <span class="font-mono text-[#1CBB9B]"><?= htmlspecialchars($result['protocol_code']) ?></span></h3>
                        <p class="text-gray-500 mt-1"><?= htmlspecialchars($result['type_name']) ?></p>
                    </div>
                    <span class="mt-4 md:mt-0 px-4 py-2 rounded-full text-sm font-bold shadow-sm
                        <?= $result['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' : '' ?>
                        <?= $result['status'] === 'approved' ? 'bg-green-100 text-green-800 border border-green-200' : '' ?>
                        <?= $result['status'] === 'concluded' ? 'bg-green-100 text-green-800 border border-green-200' : '' ?>
                        <?= $result['status'] === 'rejected' ? 'bg-red-100 text-red-800 border border-red-200' : '' ?>
                    ">
                        <?= Helpers::translateStatus($result['status']) ?>
                    </span>
                </div>

                <?php if (!empty($workflowSteps)): ?>
                <div class="mb-8">
                    <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-5">Fluxo do Requerimento</h4>
                    <?php
                        $currentStep = (int)($result['current_step_order'] ?? 1);
                        $reqStatus   = $result['status'];
                        $activeLabel = '';
                        $totalSteps  = count($workflowSteps);
                    ?>
                    <div class="flex items-start justify-center gap-0">
                        <?php foreach ($workflowSteps as $i => $step):
                            $order = (int)$step['step_order'];

                            if ($reqStatus === 'rejected') {
                                if ($order < $currentStep)  $state = 'done';
                                elseif ($order === $currentStep) $state = 'rejected';
                                else $state = 'future';
                            } elseif (in_array($reqStatus, ['approved', 'concluded'])) {
                                $state = 'done';
                            } else {
                                if ($order < $currentStep)  $state = 'done';
                                elseif ($order === $currentStep) $state = 'current';
                                else $state = 'future';
                            }

                            $shortLabel = $step['role_name'];
                            if ($state === 'current') {
                                $activeLabel = $roleLabels[$step['role_name']] ?? ('Aguardando análise — ' . $step['role_name']);
                            }

                            $circleClass = match($state) {
                                'done'     => 'bg-green-500 border-green-500',
                                'current'  => 'bg-amber-400 border-amber-400 animate-pulse',
                                'rejected' => 'bg-red-500 border-red-500',
                                default    => 'bg-white border-gray-300',
                            };
                            $textClass = match($state) {
                                'done'     => 'text-green-600',
                                'current'  => 'text-amber-600 font-semibold',
                                'rejected' => 'text-red-600',
                                default    => 'text-gray-400',
                            };
                        ?>
                            <div class="flex items-center">
                                <div class="flex flex-col items-center w-20">
                                    <div class="w-10 h-10 rounded-full border-2 flex items-center justify-center <?= $circleClass ?>">
                                        <?php if ($state === 'done'): ?>
                                            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        <?php elseif ($state === 'rejected'): ?>
                                            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                        <?php elseif ($state === 'current'): ?>
                                            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <?php else: ?>
                                            <span class="w-2 h-2 rounded-full bg-gray-300"></span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-xs text-center mt-2 leading-tight <?= $textClass ?>"><?= htmlspecialchars($shortLabel) ?></p>
                                </div>
                                <?php if ($i < $totalSteps - 1): ?>
                                    <div class="h-0.5 w-8 flex-shrink-0 -mt-5 <?= $state === 'done' ? 'bg-green-400' : 'bg-gray-200' ?>"></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($activeLabel): ?>
                        <p class="text-center text-sm text-amber-700 font-medium mt-4 bg-amber-50 border border-amber-200 rounded-xl py-2 px-4">
                            <?= htmlspecialchars($activeLabel) ?>
                        </p>
                    <?php elseif (in_array($reqStatus, ['approved', 'concluded'])): ?>
                        <p class="text-center text-sm text-green-700 font-medium mt-4 bg-green-50 border border-green-200 rounded-xl py-2 px-4">
                            Requerimento concluído — todas as etapas foram cumpridas.
                        </p>
                    <?php elseif ($reqStatus === 'rejected'): ?>
                        <p class="text-center text-sm text-red-700 font-medium mt-4 bg-red-50 border border-red-200 rounded-xl py-2 px-4">
                            Requerimento indeferido. Veja o histórico abaixo para mais detalhes.
                        </p>
                    <?php endif; ?>

                    <?php if ((int)$result['request_type_id'] === 2): ?>
                        <div class="mt-4 bg-blue-50 border border-blue-200 rounded-xl py-2.5 px-4 flex items-start gap-2">
                            <svg class="w-4 h-4 text-blue-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <p class="text-xs text-blue-700">Em caso de deferimento ou indeferimento, os professores indicados na solicitação serão notificados automaticamente por e-mail.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="mb-4">
                    <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-6">Linha do Tempo</h4>
                    <div class="flow-root">
                        <ul class="-mb-8">
                            <?php foreach ($history as $entry): ?>
                                <li>
                                    <div class="relative pb-8">
                                        <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                        <div class="relative flex space-x-4">
                                            <div>
                                                <span class="h-8 w-8 rounded-full flex items-center justify-center ring-4 ring-white shadow-sm
                                                    <?= $entry['action'] === 'approve' ? 'bg-green-500' : ($entry['action'] === 'reject' ? 'bg-red-500' : 'bg-gray-400') ?>">
                                                    <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <?php if($entry['action'] === 'approve'): ?>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                        <?php elseif($entry['action'] === 'reject'): ?>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                        <?php else: ?>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                                        <?php endif; ?>
                                                    </svg>
                                                </span>
                                            </div>
                                            <div class="min-w-0 flex-1 pt-1.5">
                                                <div class="flex justify-between mb-1">
                                                    <p class="text-sm font-medium text-gray-900">
                                                        <?= $entry['action'] === 'approve' ? 'Aprovado' : ($entry['action'] === 'reject' ? 'Indeferido' : 'Observação') ?>
                                                        <span class="text-gray-500 font-normal">por</span> <?= htmlspecialchars($entry['user_name'] ?? 'Sistema') ?>
                                                    </p>
                                                    <div class="text-right text-xs text-gray-400">
                                                        <?= date('d/m/Y H:i', strtotime($entry['created_at'])) ?>
                                                    </div>
                                                </div>
                                                <div class="bg-gray-50 rounded-lg p-3 border border-gray-100 text-sm text-gray-600">
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
        <?php endif; ?>
    </main>

    <footer class="bg-white border-t border-gray-200 py-8 mt-auto">
        <div class="container mx-auto px-4 text-center">
            <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="IFSC Logo" class="h-8 mx-auto mb-4 opacity-50 grayscale hover:grayscale-0 transition-all">
            <p class="text-sm text-gray-500">&copy; <?= date('Y') ?> Instituto Federal de Santa Catarina - Câmpus Garopaba | Desenvolvido pelo Prof. Eduardo Gomes (Câmpus Canoinhas) e gentilmente cedido ao Câmpus Garopaba</p>
        </div>
    </footer>

</body>
</html>
