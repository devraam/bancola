<?php
/**
 * Plugin Name: BanKitos
 * Description: B@nko comunitario: aportes, préstamos y acceso solo para miembros.
 * Version: 0.5.1
 * Author: Bancola
 * License: GPL2+
 */

if (!defined('ABSPATH')) exit;

define('BANKITOS_PATH', plugin_dir_path(__FILE__));
define('BANKITOS_URL',  plugin_dir_url(__FILE__));

if (!defined('BANKITOS_RECAPTCHA_SITE'))  define('BANKITOS_RECAPTCHA_SITE', '');
if (!defined('BANKITOS_RECAPTCHA_SECRET'))define('BANKITOS_RECAPTCHA_SECRET', '');

/* ========= Núcleo / Clases ========= */
require_once BANKITOS_PATH . 'includes/class-bankitos-plugin.php';
require_once BANKITOS_PATH . 'includes/class-bankitos-settings.php';
require_once BANKITOS_PATH . 'includes/class-bankitos-cpt.php';
require_once BANKITOS_PATH . 'includes/class-bankitos-access.php';
require_once BANKITOS_PATH . 'includes/class-bankitos-recaptcha.php';
require_once BANKITOS_PATH . 'includes/class-bankitos-shortcodes.php';
require_once BANKITOS_PATH . 'includes/class-bankitos-handlers.php';

/* ========= Bootstrap ========= */
function bankitos() {
    static $instance = null;
    if ($instance === null) {
        $instance = new Bankitos_Plugin();
    }
    return $instance;
}
bankitos();

Bankitos_Settings::init();
add_action('init', ['Bankitos_CPT', 'init'], 5);
