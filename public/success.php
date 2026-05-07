<?php
/**
 * Pagina de sucesso exibida para o aluno logo apos a conclusao da abertura de um requerimento.
 *
 * @author Prof. Eduardo Gomes
 */
 require_once '../config/config.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitação Enviada - IFSC</title>
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        ifsc: {
                            green: '#32A041',
                            dark: '#1F5037',
                            red: '#C62828'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 flex items-center justify-center h-screen">
    <div class="bg-white p-8 rounded-lg shadow-lg text-center max-w-md w-full">
        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4">
            <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>
        <h2 class="text-2xl font-bold text-gray-900 mb-2">Solicitação Enviada!</h2>
        <p class="text-gray-600 mb-6">Seu requerimento foi registrado com sucesso.</p>
        
        <div class="bg-gray-100 p-4 rounded-md mb-6">
            <p class="text-sm text-gray-500 uppercase tracking-wide">Número do Protocolo</p>
            <p class="text-3xl font-mono font-bold text-ifsc-dark mt-1"><?= htmlspecialchars($_GET['protocol'] ?? 'ERRO') ?></p>
        </div>
        
        <p class="text-sm text-gray-500 mb-6">Um e-mail de confirmação foi enviado para você.</p>
        
        <a href="<?= BASE_URL ?>/index.php" class="text-ifsc-green hover:text-ifsc-dark font-medium">Voltar ao início</a>
    </div>
</body>
</html>
