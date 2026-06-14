<?php
// Guard para o portal de responsáveis — incluir no topo de cada página protegida
require_once '../../config/database.php';
require_once '../../config/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['guardian_id'])) {
    header('Location: login.php');
    exit;
}

// Forçar troca de senha no primeiro acesso (exceto na própria página de troca)
$currentPage = basename($_SERVER['PHP_SELF']);
if (!empty($_SESSION['guardian_must_change_pw']) && $currentPage !== 'trocar_senha.php') {
    header('Location: trocar_senha.php');
    exit;
}

$guardianId    = (int)$_SESSION['guardian_id'];
$guardianName  = $_SESSION['guardian_name'];
$guardianEmail = $_SESSION['guardian_email'];
