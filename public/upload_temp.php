<?php
/**
 * Script para gerenciar o upload temporario de arquivos (anexos) via AJAX durante o preenchimento do formulario.
 *
 * @author Prof. Eduardo Gomes
 */
// public/upload_temp.php
header('Content-Type: application/json');
header('Connection: close');

// Define diretorio temporario de upload
$tempDir = __DIR__ . '/temp/';

// Cria diretorio se nao existir
if (!file_exists($tempDir)) {
    mkdir($tempDir, 0755, true);
}

// Verificacao Basica de Seguranca
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response = ['success' => false, 'message' => 'Método inválido.'];
    echo json_encode($response);
    exit;
}

if (!isset($_FILES['file'])) {
    $response = ['success' => false, 'message' => 'Nenhum arquivo enviado.'];
    echo json_encode($response);
    exit;
}

$file = $_FILES['file'];

// Valida codigo de erro
if ($file['error'] !== UPLOAD_ERR_OK) {
    $response = ['success' => false, 'message' => 'Erro no upload: ' . $file['error']];
    echo json_encode($response);
    exit;
}

// Extensoes permitidas
$allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($extension, $allowedExtensions)) {
    $response = ['success' => false, 'message' => 'Tipo de arquivo não permitido.'];
    echo json_encode($response);
    exit;
}

// Validacao de conteudo (MIME real) — defesa contra arquivo disfarcado pela extensao
$allowedMimes = [
    'application/pdf',
    'image/jpeg', 'image/png',
    'application/msword',                                                       // .doc
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',  // .docx
    'application/zip',           // .docx as vezes detectado assim
    'application/x-ole-storage', // .doc antigo as vezes detectado assim
];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
if (!in_array($mime, $allowedMimes, true)) {
    $response = ['success' => false, 'message' => 'O conteúdo do arquivo não corresponde a um tipo permitido.'];
    echo json_encode($response);
    exit;
}

// Tamanho maximo do arquivo: 5MB
$maxSize = 5 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    $response = ['success' => false, 'message' => 'O arquivo é muito grande (Máx: 5MB).'];
    echo json_encode($response);
    exit;
}

// Gera nome temporario unico
$tempFilename = bin2hex(random_bytes(16)) . '.' . $extension;
$targetPath = $tempDir . $tempFilename;

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    $response = [
        'success' => true,
        'temp_filename' => $tempFilename,
        'original_name' => $file['name'],
        'size' => $file['size']
    ];
} else {
    $response = ['success' => false, 'message' => 'Falha ao salvar arquivo temporário.'];
}

// Envia Resposta e Fecha Conexao imediatamente
$jsonResponse = json_encode($response);
header('Content-Length: ' . (strlen($jsonResponse) + 4096));
echo $jsonResponse;
echo str_repeat(' ', 4096); // Padding to force buffer flush

// Forca o flush do buffer
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    ob_end_flush();
    flush();
}
exit;
