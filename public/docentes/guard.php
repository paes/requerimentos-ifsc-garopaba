<?php
// Guard para páginas do portal docente — incluir no topo de cada página protegida
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../src/Auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

Auth::check(); // redireciona para index.php se não autenticado

$user = Auth::user();

// Verificar role Docente
$db   = new Database();
$conn = $db->getConnection();

if (empty($_SESSION['is_docente'])) {
    $stmtRole = $conn->prepare("SELECT name FROM roles WHERE id = :id");
    $stmtRole->execute([':id' => $user['user_role']]);
    $roleName = $stmtRole->fetchColumn();
    if ($roleName !== 'Docente') {
        Auth::logout();
        header('Location: index.php');
        exit;
    }
    $_SESSION['is_docente'] = true;
}

// Garantir que teacher_id está na sessão
if (empty($_SESSION['teacher_id'])) {
    $stmtEmail = $conn->prepare("SELECT email FROM users WHERE id = :id");
    $stmtEmail->execute([':id' => $user['user_id']]);
    $userEmail = $stmtEmail->fetchColumn();

    $stmtT = $conn->prepare("SELECT id, name FROM teachers WHERE email = :email AND active = 1 LIMIT 1");
    $stmtT->execute([':email' => $userEmail]);
    $teacherRow = $stmtT->fetch(PDO::FETCH_ASSOC);

    if (!$teacherRow) {
        Auth::logout();
        header('Location: index.php?msg=no_teacher');
        exit;
    }
    $_SESSION['teacher_id']    = $teacherRow['id'];
    $_SESSION['teacher_name']  = $teacherRow['name'];
    $_SESSION['teacher_email'] = $userEmail;
}

$teacherId    = (int)$_SESSION['teacher_id'];
$teacherName  = $_SESSION['teacher_name'];
$teacherEmail = $_SESSION['teacher_email'];
