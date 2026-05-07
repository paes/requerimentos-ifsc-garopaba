<?php
/**
 * Script para encerrar a sessao do usuario administrador (Logout).
 *
 * @author Prof. Eduardo Gomes
 */
require_once '../../src/Auth.php';
Auth::logout();
header('Location: index.php');
exit;
