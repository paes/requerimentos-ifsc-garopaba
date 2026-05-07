<?php
/**
 * Arquivo de configuracao geral do sistema. Contem definicoes de URL base, chaves do Cloudflare Turnstile e flags de ambiente (producao/desenvolvimento).
 *
 * @author Prof. Eduardo Gomes
 */
// Define o caminho raiz da URL do projeto
// Se rodando em localhost/127.0.0.1 e NAO no caminho especifico de producao, assume raiz
// Ajuste essa logica se voce tiver uma configuracao local diferente

$isProduction = strpos($_SERVER['HTTP_HOST'], 'sites.canoinhas.ifsc.edu.br') !== false;

// Chaves do Cloudflare Turnstile
define('TURNSTILE_SITE_KEY', '0x4AAAAAACGFmVmXjtW4uAwx');
define('TURNSTILE_SECRET_KEY', '0x4AAAAAACGFmf_S0rEwxGs__uhlfaKt5Vg');

if ($isProduction) {
    define('BASE_URL', 'https://sites.canoinhas.ifsc.edu.br/requerimentos');
    define('ENABLE_TURNSTILE', true);
    define('ENABLE_EMAILS', true);
} else {
    // Para desenvolvimento local (ajuste se rodar dentro de uma subpasta localmente) senha dev123
    define('BASE_URL', '');
    define('ENABLE_TURNSTILE', false);
    define('ENABLE_EMAILS', false);
}
?>