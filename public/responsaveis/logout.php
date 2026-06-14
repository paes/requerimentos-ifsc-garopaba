<?php
if (session_status() === PHP_SESSION_NONE) session_start();
session_destroy();
require_once '../../config/config.php';
header('Location: login.php');
exit;
