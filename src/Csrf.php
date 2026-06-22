<?php
/**
 * Proteção CSRF baseada em token por sessão (sincronizador).
 *
 * Uso:
 *   - Em formulários POST:   <?= Csrf::field() ?>
 *   - No início do handler:  Csrf::check();   // aborta com 403 se inválido
 */
class Csrf
{
    private const KEY = 'csrf_token';

    /** Garante a sessão e retorna o token (gera na primeira vez). */
    public static function token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    /** Campo hidden pronto para embutir no formulário. */
    public static function field(): string
    {
        return '<input type="hidden" name="' . self::KEY . '" value="'
             . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }

    /** Validação booleana (comparação em tempo constante). */
    public static function validate(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return !empty($_SESSION[self::KEY])
            && is_string($token)
            && hash_equals($_SESSION[self::KEY], $token);
    }

    /** Exige token válido no POST; aborta a requisição se inválido. */
    public static function check(): void
    {
        if (!self::validate($_POST[self::KEY] ?? null)) {
            http_response_code(403);
            exit('Requisição inválida ou expirada. Recarregue a página e tente novamente.');
        }
    }
}
