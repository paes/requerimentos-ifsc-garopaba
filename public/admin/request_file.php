<?php
// Serve anexos de requerimento (podem conter dados sensíveis — atestados/laudos, LGPD Art. 11).
// Os arquivos ficam FORA do webroot (storage/request_files/); só um administrador autenticado
// pode baixá-los por aqui. Espelha o padrão de admin/guardian_pdf.php.
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../src/Auth.php';

Auth::check(); // redireciona/bloqueia se não autenticado

$db   = new Database();
$conn = $db->getConnection();

$fileId = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT filepath FROM request_files WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $fileId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || !$row['filepath']) {
    http_response_code(404);
    exit('Anexo não encontrado.');
}

$filepath = dirname(__DIR__, 2) . '/storage/request_files/' . basename($row['filepath']);
if (!is_file($filepath)) {
    http_response_code(404);
    exit('Arquivo não encontrado no servidor.');
}

$ext   = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
$types = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
$mime = $types[$ext] ?? 'application/octet-stream';
// PDF/imagens: abrir no navegador; demais: forçar download.
$disposition = in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true) ? 'inline' : 'attachment';

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . basename($row['filepath']) . '"');
header('Content-Length: ' . filesize($filepath));
header('X-Content-Type-Options: nosniff');
readfile($filepath);
exit;
