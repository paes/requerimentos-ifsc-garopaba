<?php
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../src/Auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Se já logado como docente, redireciona
if (!empty($_SESSION['user_id']) && !empty($_SESSION['is_docente'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db   = new Database();
    $conn = $db->getConnection();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (Auth::login($email, $password, $conn)) {
        $user = Auth::user();

        // Verificar se o role é "Docente" (id = 15)
        $stmtRole = $conn->prepare("SELECT name FROM roles WHERE id = :id");
        $stmtRole->execute([':id' => $user['user_role']]);
        $roleName = $stmtRole->fetchColumn();

        if ($roleName !== 'Docente') {
            Auth::logout();
            $error = 'Acesso restrito ao Portal de Docentes. Use o <a href="../admin/" class="underline">acesso administrativo</a> se necessário.';
        } else {
            // Buscar email do usuário para cruzar com teachers
            $stmtEmail = $conn->prepare("SELECT email FROM users WHERE id = :id");
            $stmtEmail->execute([':id' => $user['user_id']]);
            $userEmail = $stmtEmail->fetchColumn();

            // Cruzar com teachers pelo email
            $stmtT = $conn->prepare("SELECT id, name FROM teachers WHERE email = :email AND active = 1 LIMIT 1");
            $stmtT->execute([':email' => $userEmail]);
            $teacherRow = $stmtT->fetch(PDO::FETCH_ASSOC);

            if (!$teacherRow) {
                Auth::logout();
                $error = 'Seu usuário não está vinculado a nenhum docente ativo. Contate a coordenação para que seu e-mail seja cadastrado no sistema.';
            } else {
                $_SESSION['is_docente']     = true;
                $_SESSION['teacher_id']     = $teacherRow['id'];
                $_SESSION['teacher_name']   = $teacherRow['name'];
                $_SESSION['teacher_email']  = $userEmail;
                header('Location: dashboard.php');
                exit;
            }
        }
    } else {
        $error = 'Usuário ou senha incorretos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR"><head>
    <script>document.documentElement.setAttribute('data-theme', localStorage.getItem('req_theme') || 'default')</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Docente - IFSC</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/themes.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/favicon.ico" type="image/x-icon">
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
</head>

<body class="bg-[#F2F4F8] flex items-center justify-center h-screen">
    <div class="bg-white p-10 rounded-lg shadow-2xl w-full max-w-sm">
        <div class="text-center mb-8">
            <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="IFSC Logo" class="h-12 mx-auto mb-4">
            <h2 class="text-2xl font-bold text-gray-800 tracking-tight">Portal Docente</h2>
            <p class="text-sm text-gray-500 mt-1">Solicitações de substituição de aulas</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-md mb-6 text-sm font-medium">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Usuário</label>
                <input type="text" name="email" required placeholder="Seu usuário sem o @ifsc.edu.br"
                    class="w-full bg-gray-50 border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-brand-DEFAULT focus:border-transparent transition-all outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Senha</label>
                <input type="password" name="password" required
                    class="w-full bg-gray-50 border border-gray-200 rounded-md px-4 py-2 focus:ring-2 focus:ring-brand-DEFAULT focus:border-transparent transition-all outline-none">
            </div>
            <button type="submit" id="submit_btn"
                class="w-full bg-[#1CBB9B] text-white py-3.5 rounded-md hover:bg-[#169C80] transition-all font-bold shadow-lg hover:shadow-xl">
                Entrar
            </button>
        </form>

        <div class="mt-8 text-center space-y-2">
            <a href="<?= BASE_URL ?>/index.php" class="block text-sm text-gray-400 hover:text-brand-DEFAULT transition-colors">
                Voltar para o site
            </a>
        </div>
        <div class="mt-4 flex justify-center">
            <div class="theme-switcher">
                <button class="theme-btn text-gray-400" data-t="default" title="Esmeralda">💎</button>
                <button class="theme-btn text-gray-400" data-t="ifsc"    title="IFSC">🍃</button>
                <button class="theme-btn text-gray-400" data-t="noturno" title="Noturno">🌙</button>
            </div>
        </div>
    </div>
    <script>
        document.querySelector('form').addEventListener('submit', function () {
            var btn = document.getElementById('submit_btn');
            btn.disabled = true;
            btn.textContent = 'Verificando...';
        });
    </script>
</body>
</html>
