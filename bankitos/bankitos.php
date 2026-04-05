<?php
/**
 * Plugin Name: BanKitos
 * Description: B@nko comunitario: aportes, préstamos y acceso solo para miembros.
 * Version: 1.0.1
 * Author: Asesor Digital
 * Author URI: https://asesordigital.com.co
 * License: GPL2+
 */

if (!defined('ABSPATH')) exit;

define('BANKITOS_PATH', plugin_dir_path(__FILE__));
define('BANKITOS_URL',  plugin_dir_url(__FILE__));

if (!defined('BANKITOS_RECAPTCHA_SITE'))  define('BANKITOS_RECAPTCHA_SITE', '');
if (!defined('BANKITOS_RECAPTCHA_SECRET'))define('BANKITOS_RECAPTCHA_SECRET', '');

/* ========= Núcleo / Clases ========= */
require_once BANKITOS_PATH . 'includes/class-bankitos-rate-limiter.php';
require_once BANKITOS_PATH . 'includes/class-bankitos-crypto.php';
require_once BANKITOS_PATH . 'includes/class-bankitos-distributions.php';
require_once BANKITOS_PATH . 'includes/class-bankitos-plugin.php';
require_once BANKITOS_PATH . 'includes/class-bankitos-db.php';
require_once BANKITOS_PATH . 'includes/class-bankitos-credit-requests.php';
require_once BANKITOS_PATH . 'includes/class-bankitos-credit-payments.php';
require_once BANKITOS_PATH . 'includes/class-bankitos-settings.php';
require_once BANKITOS_PATH . 'includes/class-bankitos-cpt.php';
require_once BANKITOS_PATH . 'includes/class-bankitos-access.php';
require_once BANKITOS_PATH . 'includes/class-bankitos-recaptcha.php';
require_once BANKITOS_PATH . 'includes/class-bankitos-secure-files.php';
require_once BANKITOS_PATH . 'includes/class-bankitos-shortcodes.php';
require_once BANKITOS_PATH . 'includes/class-bankitos-handlers.php';
require_once BANKITOS_PATH . 'includes/class-bankitos-domains.php';
require_once BANKITOS_PATH . 'includes/class-bankitos-admin-reports.php';
require_once BANKITOS_PATH . 'includes/class-bankitos-logs.php'; // Nueva clase de logs

/* ========= Bootstrap ========= */
function bankitos() {
    static $instance = null;
    if ($instance === null) {
        $instance = new Bankitos_Plugin();
    }
    return $instance;
}
bankitos();

// Inicialización de configuraciones
Bankitos_Settings::init();

// Inicialización de logs (para que el hook 'bankitos_log_event' esté disponible)
if (class_exists('Bankitos_Logs')) {
    Bankitos_Logs::init();
}

if (class_exists('Bankitos_Domains')) {
    Bankitos_Domains::init();
}

if (class_exists('Bankitos_Secure_Files')) {
    Bankitos_Secure_Files::init();
}

// Hook de activación para crear tablas (incluyendo la nueva de logs)
register_activation_hook(__FILE__, function() {
    if (class_exists('Bankitos_DB')) {
        Bankitos_DB::create_tables();
    }
    if (class_exists('Bankitos_Logs')) {
        Bankitos_Logs::create_table();
    }
});

// Ejecutar migraciones en cada carga si no se han aplicado aún (flag en options).
add_action('init', function() {
    if (!get_option('bankitos_db_v2_migrated')) {
        if (class_exists('Bankitos_DB')) {
            Bankitos_DB::create_tables();
        }
        update_option('bankitos_db_v2_migrated', 1);
    }
    // v3: tabla de solicitudes de renuncia
    if (!get_option('bankitos_db_v3_migrated')) {
        if (class_exists('Bankitos_DB')) {
            Bankitos_DB::create_tables();
        }
        update_option('bankitos_db_v3_migrated', 1);
    }
}, 1);

add_action('init', ['Bankitos_CPT', 'init'], 5);