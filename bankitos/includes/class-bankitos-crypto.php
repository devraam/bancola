<?php
if (!defined('ABSPATH')) exit;

/**
 * Cifrado reversible AES-256-CBC para campos PII en base de datos.
 *
 * Uso:
 *   $cifrado  = Bankitos_Crypto::encrypt($valor_plano);
 *   $plano    = Bankitos_Crypto::decrypt($valor_cifrado);
 *
 * La clave de cifrado se deriva de AUTH_KEY de WordPress (wp-config.php),
 * por lo que es única por instalación y nunca se almacena en la DB.
 *
 * Formato almacenado: base64( IV[16 bytes] + ciphertext )
 * Prefijo "bk1:" permite detectar valores ya cifrados vs texto plano legado.
 */
class Bankitos_Crypto {

    private const PREFIX    = 'bk1:';
    private const CIPHER    = 'AES-256-CBC';
    private const IV_LENGTH = 16;

    /**
     * Cifra un valor de texto plano.
     * Si el valor ya está cifrado (prefijo bk1:) lo retorna sin cambios.
     * Si openssl no está disponible retorna el valor original sin modificar.
     *
     * @param string $plaintext
     * @return string
     */
    public static function encrypt(string $plaintext): string {
        if ($plaintext === '' || self::is_encrypted($plaintext)) {
            return $plaintext;
        }
        if (!function_exists('openssl_encrypt')) {
            return $plaintext;
        }
        $key = self::get_key();
        $iv  = random_bytes(self::IV_LENGTH);
        $encrypted = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            return $plaintext;
        }
        return self::PREFIX . base64_encode($iv . $encrypted);
    }

    /**
     * Descifra un valor previamente cifrado con encrypt().
     * Si el valor no tiene el prefijo bk1: lo retorna como está (compatibilidad con datos legados).
     *
     * @param string $ciphertext
     * @return string
     */
    public static function decrypt(string $ciphertext): string {
        if ($ciphertext === '' || !self::is_encrypted($ciphertext)) {
            return $ciphertext; // valor legado sin cifrar — retornar tal cual
        }
        if (!function_exists('openssl_decrypt')) {
            return $ciphertext;
        }
        $raw = base64_decode(substr($ciphertext, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) <= self::IV_LENGTH) {
            return $ciphertext;
        }
        $key       = self::get_key();
        $iv        = substr($raw, 0, self::IV_LENGTH);
        $encrypted = substr($raw, self::IV_LENGTH);
        $decrypted = openssl_decrypt($encrypted, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        return $decrypted !== false ? $decrypted : $ciphertext;
    }

    /**
     * Indica si un valor ya fue cifrado por esta clase.
     *
     * @param string $value
     * @return bool
     */
    public static function is_encrypted(string $value): bool {
        return str_starts_with($value, self::PREFIX);
    }

    // ---------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------

    /**
     * Deriva una clave de 32 bytes a partir de AUTH_KEY de WordPress.
     * La clave nunca se almacena — se recalcula en cada request.
     */
    private static function get_key(): string {
        $salt = defined('AUTH_KEY') ? AUTH_KEY : wp_salt('auth');
        return substr(hash('sha256', 'bankitos_pii_v1|' . $salt, true), 0, 32);
    }
}
