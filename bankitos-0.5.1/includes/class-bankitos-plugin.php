<?php
if (!defined('ABSPATH')) exit;
if (!defined('BANKITOS_VERSION')) define('BANKITOS_VERSION', '0.5.1');

class Bankitos_Plugin {

    public function __construct() {
        register_activation_hook(BANKITOS_PATH . 'bankitos.php', [$this, 'activate']);
        register_deactivation_hook(BANKITOS_PATH . 'bankitos.php', function () {
            flush_rewrite_rules();
        });
        add_action('wp_enqueue_scripts', [$this, 'register_public_assets'], 50);
    }   

    public function activate(): void {
        if (class_exists('Bankitos_DB')) {
            Bankitos_DB::create_tables();
        }
        if (class_exists('Bankitos_CPT')) {
            Bankitos_CPT::register_cpts();
            Bankitos_CPT::add_roles_and_caps();
        }
        flush_rewrite_rules();
    }

    public function register_public_assets(): void {
        if (is_admin()) {
            return;
        }

        wp_enqueue_style('bankitos-style', BANKITOS_URL . 'assets/css/bankitos.css', [], BANKITOS_VERSION);
        wp_register_script('bankitos-create-banco', BANKITOS_URL . 'assets/js/create-banco.js', [], BANKITOS_VERSION, true);
        wp_register_script('bankitos-recaptcha', BANKITOS_URL . 'assets/js/recaptcha.js', [], BANKITOS_VERSION, true);
    }
}
