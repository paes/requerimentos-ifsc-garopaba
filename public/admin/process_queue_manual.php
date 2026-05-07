<?php
/**
 * Script para forcar a execucao manual da fila de e-mails pendentes.
 *
 * @author Prof. Eduardo Gomes
 */
// public/admin/process_queue_manual.php
require_once '../../config/database.php';
require_once '../../src/Auth.php';
require_once '../../src/EmailService.php';

Auth::check();
$user = Auth::user();

// Verifica se e SysAdmin
$db = new Database();
$conn = $db->getConnection();
$roleStmt = $conn->prepare("SELECT is_sysadmin FROM roles WHERE id = :id");
$roleStmt->execute([':id' => $user['user_role']]);
if (!$roleStmt->fetchColumn()) {
    die("Acesso negado.");
}

$emailService = new EmailService($conn);

echo "<h1>Processando Fila de Emails...</h1>";
echo "<pre>";

// Captura a saida
ob_start();
$emailService->processQueue();
$output = ob_get_clean();

echo "Processamento concluído.<br>";
echo "Log:<br>";
echo $output;
echo "</pre>";
echo "<br><a href='email_config.php'>Voltar</a>";
