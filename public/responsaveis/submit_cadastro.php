<?php
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../src/Csrf.php';
require_once '../../src/CryptoHelper.php';
require_once '../../src/EmailService.php';
require_once '../../src/EmailTemplate.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cadastro.php');
    exit;
}

// CSRF: protege a criação de cadastro (com upload de arquivo)
Csrf::check();

// --- Coleta e validação básica ---
$guardianName  = trim($_POST['guardian_name'] ?? '');
$guardianEmail = strtolower(trim($_POST['guardian_email'] ?? ''));
$guardianCpf   = preg_replace('/\D/', '', $_POST['guardian_cpf'] ?? '');
$guardianPhone = trim($_POST['guardian_phone'] ?? '');

$errors = [];

if (strlen($guardianName) < 3)    $errors[] = 'Informe seu nome completo.';
if (!filter_var($guardianEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail inválido.';
if (strlen($guardianCpf) !== 11)  $errors[] = 'CPF inválido — informe os 11 dígitos.';
if (empty($guardianPhone))         $errors[] = 'Informe um telefone.';
if (!isset($_POST['declaration'])) $errors[] = 'Você precisa aceitar a declaração.';

// Alunos
$studentNames     = $_POST['student_name'] ?? [];
$studentMatriculas = $_POST['student_matricula'] ?? [];
$students = [];
foreach ($studentNames as $i => $sName) {
    $sName = trim($sName);
    $sMatr = trim($studentMatriculas[$i] ?? '');
    if ($sName !== '' || $sMatr !== '') {
        if ($sName === '') { $errors[] = "Nome do aluno " . ($i + 1) . " está em branco."; continue; }
        if ($sMatr === '') { $errors[] = "Matrícula do aluno " . ($i + 1) . " está em branco."; continue; }
        $students[] = ['name' => $sName, 'matricula' => $sMatr];
    }
}
if (empty($students)) $errors[] = 'Informe ao menos um aluno.';

// Arquivo PDF assinado
$signedPdfPath = null;
if (empty($_FILES['signed_pdf']['tmp_name'])) {
    $errors[] = 'Anexe o PDF assinado via gov.br.';
} else {
    $file = $_FILES['signed_pdf'];
    if ($file['error'] !== UPLOAD_ERR_OK)       $errors[] = 'Erro no upload do PDF.';
    elseif ($file['size'] > 5 * 1024 * 1024)    $errors[] = 'O PDF não pode ultrapassar 5 MB.';
    else {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if ($mime !== 'application/pdf') $errors[] = 'O arquivo deve ser um PDF válido.';
    }
}

if ($errors) {
    $_SESSION['guardian_reg_error'] = implode('<br>', $errors);
    $_SESSION['guardian_reg_old']   = $_POST;
    header('Location: cadastro.php');
    exit;
}

$db   = new Database();
$conn = $db->getConnection();

// Verifica duplicata de e-mail
$stmtDup = $conn->prepare("SELECT id FROM guardian_registrations WHERE guardian_email = :email AND status IN ('pending','approved')");
$stmtDup->execute([':email' => $guardianEmail]);
if ($stmtDup->fetchColumn()) {
    $_SESSION['guardian_reg_error'] = 'Já existe um cadastro com este e-mail em análise ou aprovado.';
    header('Location: cadastro.php');
    exit;
}

// Salva o PDF assinado
$docsDir = dirname(__DIR__, 2) . '/storage/guardian_docs';
if (!is_dir($docsDir)) mkdir($docsDir, 0755, true);
$pdfFilename  = date('Ymd_His') . '_' . preg_replace('/[^a-z0-9]/i', '_', $guardianEmail) . '.pdf';
$signedPdfPath = $pdfFilename;
move_uploaded_file($_FILES['signed_pdf']['tmp_name'], "$docsDir/$pdfFilename");

// Gera protocolo R-AAAA-NNNN
$year = date('Y');
$stmtLast = $conn->query("SELECT COUNT(*) FROM guardian_registrations WHERE YEAR(created_at) = $year");
$seq = (int)$stmtLast->fetchColumn() + 1;
$protocol = sprintf('R-%s-%04d', $year, $seq);

// Insere registro
$cpfFirst5  = substr($guardianCpf, 0, 5);
$cpfHash    = password_hash($guardianCpf, PASSWORD_BCRYPT);
$phoneEnc   = CryptoHelper::encrypt($guardianPhone);
$studJson   = json_encode($students, JSON_UNESCAPED_UNICODE);

$conn->beginTransaction();
try {
    $stmt = $conn->prepare("
        INSERT INTO guardian_registrations
            (protocol_code, guardian_name, guardian_email, guardian_phone, cpf_hash, cpf_first5, students_json, signed_pdf_path, status)
        VALUES
            (:protocol, :name, :email, :phone, :cpf_hash, :cpf5, :students, :pdf_path, 'pending')
    ");
    $stmt->execute([
        ':protocol'  => $protocol,
        ':name'      => $guardianName,
        ':email'     => $guardianEmail,
        ':phone'     => $phoneEnc,
        ':cpf_hash'  => $cpfHash,
        ':cpf5'      => $cpfFirst5,
        ':students'  => $studJson,
        ':pdf_path'  => $signedPdfPath,
    ]);
    $conn->commit();
} catch (Exception $e) {
    $conn->rollBack();
    $_SESSION['guardian_reg_error'] = 'Erro ao registrar. Tente novamente.';
    header('Location: cadastro.php');
    exit;
}

// E-mail de confirmação ao responsável
$emailService = new EmailService($conn);
$confirmBody = EmailTemplate::wrap("
    <h2 style='color:#006633;'>Cadastro recebido — {$protocol}</h2>
    <p>Olá, <strong>{$guardianName}</strong>!</p>
    <p>Seu cadastro no <strong>Portal de Responsáveis</strong> do IFSC Câmpus Garopaba foi recebido com sucesso.</p>
    <p><strong>Protocolo:</strong> {$protocol}</p>
    <p>Seu cadastro será analisado pela <strong>Coordenação Pedagógica</strong>. Você receberá um e-mail assim que houver uma decisão.</p>
    <p>Os alunos informados foram:</p>
    <ul>" . implode('', array_map(fn($s) => "<li>{$s['name']} — Matrícula: {$s['matricula']}</li>", $students)) . "</ul>
    <p style='color:#666;font-size:13px;'>Se você não realizou este cadastro, ignore este e-mail.</p>
");
$emailService->queue($guardianEmail, $guardianName, "Cadastro de Responsável recebido — {$protocol}", $confirmBody);

// E-mail de aviso para a Coord. Pedagógica (role_id = 9 = Coord. Pedagógica)
$stmtCP = $conn->prepare("SELECT u.email, u.name FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = 'Coord. Pedagógica' LIMIT 5");
$stmtCP->execute();
$cpUsers = $stmtCP->fetchAll(PDO::FETCH_ASSOC);

$studList = implode('', array_map(fn($s) => "<li>{$s['name']} — {$s['matricula']}</li>", $students));
$notifyBody = EmailTemplate::wrap("
    <h2 style='color:#006633;'>Novo cadastro de responsável pendente</h2>
    <p>Um novo cadastro de responsável aguarda análise no sistema.</p>
    <table style='border-collapse:collapse;width:100%;font-size:14px;'>
        <tr><td style='padding:6px 12px;font-weight:bold;background:#f9fafb;'>Protocolo</td><td style='padding:6px 12px;'>{$protocol}</td></tr>
        <tr><td style='padding:6px 12px;font-weight:bold;background:#f9fafb;'>Responsável</td><td style='padding:6px 12px;'>{$guardianName}</td></tr>
        <tr><td style='padding:6px 12px;font-weight:bold;background:#f9fafb;'>E-mail</td><td style='padding:6px 12px;'>{$guardianEmail}</td></tr>
    </table>
    <p><strong>Alunos informados:</strong></p>
    <ul>{$studList}</ul>
    <p><a href='" . BASE_URL . "/admin/guardian_registrations.php' style='background:#006633;color:white;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold;'>Ver no painel admin</a></p>
");
foreach ($cpUsers as $cp) {
    $emailService->queue($cp['email'], $cp['name'], "Novo cadastro de responsável — {$protocol}", $notifyBody);
}

// Redireciona para página de sucesso
header("Location: cadastro_sucesso.php?p=" . urlencode($protocol));
exit;
