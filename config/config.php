<?php
/**
 * Arquivo de configuracao geral do sistema. Contem definicoes de URL base, chaves do Cloudflare Turnstile e flags de ambiente (producao/desenvolvimento).
 *
 * @author Prof. Eduardo Gomes
 */
// Define o caminho raiz da URL do projeto
// Se rodando em localhost/127.0.0.1 e NAO no caminho especifico de producao, assume raiz
// Ajuste essa logica se voce tiver uma configuracao local diferente

// TODO: substituir pela URL de produção de Garopaba após implantação no servidor
$isProduction = strpos($_SERVER['HTTP_HOST'], 'TODO_HOST_GAROPABA') !== false;

// TODO: gerar novas chaves Cloudflare Turnstile para o domínio de Garopaba em https://dash.cloudflare.com
define('TURNSTILE_SITE_KEY', 'TODO_TURNSTILE_SITE_KEY');
define('TURNSTILE_SECRET_KEY', 'TODO_TURNSTILE_SECRET_KEY');

if ($isProduction) {
    // TODO: substituir pela URL real do campus Garopaba
    define('BASE_URL', 'https://TODO_HOST_GAROPABA/requerimentos');
    define('ENABLE_TURNSTILE', true);
    define('ENABLE_EMAILS', true);
} else {
    define('BASE_URL', '/requerimentos');
    define('ENABLE_TURNSTILE', false);
    define('ENABLE_EMAILS', false);
}
?>