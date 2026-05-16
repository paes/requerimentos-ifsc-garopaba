<?php
/**
 * Limpeza de logs de e-mail com mais de 90 dias.
 * Execução via cron: 0 3 * * * php /path/to/scripts/limpar_logs_email.php
 * LGPD: logs contêm dados pessoais de alunos e não devem ser mantidos indefinidamente.
 */

$logDir  = __DIR__ . '/../storage/email_log/';
$maxDays = 90;
$cutoff  = time() - ($maxDays * 86400);
$removed = 0;

foreach (glob($logDir . '*.html') as $file) {
    if (filemtime($file) < $cutoff) {
        unlink($file);
        $removed++;
    }
}

echo date('Y-m-d H:i:s') . " — Logs removidos: {$removed} (>{$maxDays} dias)\n";
