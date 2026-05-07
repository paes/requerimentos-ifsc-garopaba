<?php
/**
 * Script executado via Cron Job para processar e enviar os e-mails que estao na fila de envio do banco de dados.
 *
 * @author Prof. Eduardo Gomes
 */
// public/cron/process_queue.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/EmailService.php';

// Evita timeout
set_time_limit(0);

$db = new Database();
$conn = $db->getConnection();
$emailService = new EmailService($conn);

echo "Starting queue processing...\n";

$emailService->processQueue();

echo "Queue processing finished.\n";
