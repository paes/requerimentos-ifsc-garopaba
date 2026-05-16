<?php
/**
 * Criptografia AES-256-CBC para campos sensíveis (telefone).
 * Chave definida em .env como PHONE_ENCRYPTION_KEY.
 * Formato armazenado: base64(iv):base64(ciphertext)
 */
class CryptoHelper
{
    private static function key(): string
    {
        $k = $_ENV['PHONE_ENCRYPTION_KEY'] ?? '';
        if (strlen($k) < 16) {
            throw new RuntimeException('PHONE_ENCRYPTION_KEY ausente ou curta demais no .env');
        }
        return substr(hash('sha256', $k, true), 0, 32);
    }

    public static function encrypt(?string $plain): ?string
    {
        if ($plain === null || $plain === '') return $plain;
        $iv     = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv) . ':' . base64_encode($cipher);
    }

    public static function decrypt(?string $stored): ?string
    {
        if ($stored === null || !str_contains((string)$stored, ':')) return $stored;
        [$ivB64, $cipherB64] = explode(':', (string)$stored, 2);
        $result = openssl_decrypt(base64_decode($cipherB64), 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, base64_decode($ivB64));
        return $result !== false ? $result : null;
    }
}
