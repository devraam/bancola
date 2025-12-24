<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Shortcode_Veedor_Creditos extends Bankitos_Shortcode_Tesorero_Creditos {

    public static function register(): void {
        self::register_shortcode('bankitos_veedor_creditos');
    }

    public static function render($atts = [], $content = null): string {
        if (!is_user_logged_in()) {
            return '<div class="bankitos-form bankitos-panel"><p>' . esc_html__('Inicia sesión para continuar.', 'bankitos') . '</p></div>';
        }
        if (!current_user_can('audit_aportes')) {
            return '<div class="bankitos-form bankitos-panel"><p>' . esc_html__('No tienes permisos para ver estos pagos.', 'bankitos') . '</p></div>';
        }
        return parent::render_list(false);
    }
}