<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Location: ' . (empty($_SESSION['guardian_id']) ? 'login.php' : 'dashboard.php'));
exit;
