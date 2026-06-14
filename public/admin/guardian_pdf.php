<?php
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../src/Auth.php';

Auth::check();
$user = Auth::user();

$db   = new Database();
$conn = $db->getConnection();

$roleStmt = $conn->prepare("SELECT is_sysadmin, name FROM roles WHERE id = :id");
$roleStmt->execute([':id' => $user['user_role']]);
$roleData = $roleStmt->fetch(PDO::FETCH_ASSOC);
if (!$roleData['is_sysadmin'] && $roleData['name'] !== 'Coord. Pedagógica') {
    http_response_code(403);
    exit('Acesso negado.');
}

$regId = (int)($_GET['id'] ?? 0);
$stmt  = $conn->prepare("SELECT signed_pdf_path FROM guardian_registrations WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $regId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || !$row['signed_pdf_path']) {
    http_response_code(404);
    exit('PDF não encontrado.');
}

$filepath = dirname(__DIR__, 2) . '/storage/guardian_docs/' . basename($row['signed_pdf_path']);
if (!file_exists($filepath)) {
    http_response_code(404);
    exit('Arquivo não encontrado no servidor.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="termo_responsavel_' . $regId . '.pdf"');
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
exit;
