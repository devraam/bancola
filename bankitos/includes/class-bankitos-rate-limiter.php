<?php
if (!defined('ABSPATH')) exit;

/**
 * Rate limiting y lockout de cuentas usando WordPress transients.
 *
 * Uso:
 *   // Antes de intentar la acción:
 *   if (!Bankitos_Rate_Limiter::check($ip, 'login')) { // bloqueado }
 *
 *   // Si falla:
 *   Bankitos_Rate_Limiter::record_failure($ip, 'login');
 *
 *   // Si tiene éxito:
 *   Bankitos_Rate_Limiter::reset($ip, 'login');
 */
class Bankitos_Rate_Limiter {

    // Intentos fallidos antes de bloquear
    const LOGIN_MAX_ATTEMPTS   = 5;
    const RECOVER_MAX_ATTEMPTS = 3;

    // Ventana en segundos (15 minutos para login, 60 min para recover)
    const LOGIN_WINDOW_SECS   = 900;
    const RECOVER_WINDOW_SECS = 3600;

    /**
     * Verifica si el identificador está bloqueado o ha superado el límite.
     *
     * @param string $identifier IP o email del usuario
     * @param string $action     Nombre de la acción (ej: 'login', 'recover')
     * @param int    $max        Máximo de intentos permitidos
     * @param int    $window     Ventana en segundos
     * @return bool true = puede continuar, false = bloqueado
     */
    public static function check(string $identifier, string $action, int $max = self::LOGIN_MAX_ATTEMPTS, int $window = self::LOGIN_WINDOW_SECS): bool {
        if (get_transient(self::lockout_key($identifier, $action)) !== false) {
            return false;
        }
        $attempts = (int) get_transient(self::attempts_key($identifier, $action));
        return $attempts < $max;
    }

    /**
     * Registra un intento fallido. Si supera el máximo, activa el lockout.
     *
     * @param string $identifier IP o email del usuario
     * @param string $action     Nombre de la acción
     * @param int    $max        Máximo de intentos antes de lockout
     * @param int    $window     Duración del lockout en segundos
     */
    public static function record_failure(string $identifier, string $action, int $max = self::LOGIN_MAX_ATTEMPTS, int $window = self::LOGIN_WINDOW_SECS): void {
        $key      = self::attempts_key($identifier, $action);
        $attempts = (int) get_transient($key) + 1;
        set_transient($key, $attempts, $window);

        if ($attempts >= $max) {
            set_transient(self::lockout_key($identifier, $action), 1, $window);
        }
    }

    /**
     * Resetea los contadores de intentos (ej: después de un login exitoso).
     *
     * @param string $identifier IP o email del usuario
     * @param string $action     Nombre de la acción
     */
    public static function reset(string $identifier, string $action): void {
        delete_transient(self::attempts_key($identifier, $action));
        delete_transient(self::lockout_key($identifier, $action));
    }

    /**
     * Retorna los segundos restantes del lockout, o 0 si no está bloqueado.
     *
     * @param string $identifier
     * @param string $action
     * @return int
     */
    public static function get_lockout_remaining(string $identifier, string $action): int {
        $lockout_key = self::lockout_key($identifier, $action);
        $timeout     = get_option('_transient_timeout_' . $lockout_key);
        if (!$timeout) {
            return 0;
        }
        $remaining = (int) $timeout - time();
        return max(0, $remaining);
    }

    // ---------------------------------------------------------------
    // Helpers privados
    // ---------------------------------------------------------------

    private static function attempts_key(string $identifier, string $action): string {
        // Máx 172 caracteres para la clave del transient (WP limita a 172 con el prefijo)
        return 'bkrl_' . substr(md5($action . '|' . $identifier), 0, 32);
    }

    private static function lockout_key(string $identifier, string $action): string {
        return 'bklo_' . substr(md5($action . '|' . $identifier), 0, 32);
    }
}
