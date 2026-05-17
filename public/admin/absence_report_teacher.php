<?php
// Redirecionado para o relatório unificado de justificativas de faltas
require_once '../../config/config.php';
header('Location: absence_report.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 301);
exit;
