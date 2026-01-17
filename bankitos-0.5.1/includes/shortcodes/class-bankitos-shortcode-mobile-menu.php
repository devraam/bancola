<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Shortcode_Mobile_Menu extends Bankitos_Shortcode_Base {

    public static function register(): void {
        self::register_shortcode('bankitos_mobile_menu');
    }

    /**
     * @param array|string $atts
     * @param string|null $content
     */
    public static function render($atts = [], $content = null): string {
        if (!is_user_logged_in()) {
            return '';
        }

        $user_id = get_current_user_id();

        // VALIDACIÓN: El menú solo se muestra si el usuario pertenece a un Banco
        $banco_id = class_exists('Bankitos_Handlers') 
            ? Bankitos_Handlers::get_user_banco_id($user_id) 
            : 0;

        if ($banco_id <= 0) {
            return '';
        }

        $role_key = class_exists('Bankitos_Credit_Requests')
            ? Bankitos_Credit_Requests::get_user_role_key($user_id)
            : '';
            
        $items = class_exists('Bankitos_Settings')
            ? Bankitos_Settings::get_mobile_menu_items($role_key)
            : [];

        if (!$items) {
            return '';
        }

        wp_enqueue_style('dashicons');

        $current_path = self::normalize_path(self::get_current_url());
        
        // Estructura contenedora
        $html = '<nav class="bankitos-mobile-menu" aria-label="' . esc_attr__('Menú rápido', 'bankitos') . '">';

        foreach ($items as $item) {
            $label = isset($item['label']) ? (string) $item['label'] : '';
            $url   = isset($item['url']) ? (string) $item['url'] : '';
            $icon  = isset($item['icon']) ? (string) $item['icon'] : '';

            if ($label === '' || $url === '') {
                continue;
            }

            $icon_class = self::sanitize_icon_class($icon);
            $target_path = self::normalize_path($url);
            
            // Lógica para detectar página activa
            $is_active = $target_path !== '' && self::path_is_active($current_path, $target_path);
            
            $classes = 'bankitos-mobile-menu__item';
            if ($is_active) {
                $classes .= ' bankitos-mobile-menu__item--active';
            }

            // Nota: Ponemos primero el label y luego el icono para usar flexbox y alinear a la derecha
            $html .= sprintf(
                '<a class="%1$s" href="%2$s">
                    <span class="bankitos-mobile-menu__label">%3$s</span>
                    <span class="bankitos-mobile-menu__icon dashicons %4$s" aria-hidden="true"></span>
                </a>',
                esc_attr($classes),
                esc_url($url),
                esc_html($label),
                esc_attr($icon_class ?: 'dashicons-menu')
            );
        }

        $html .= '</nav>';
        return $html;
    }

    private static function sanitize_icon_class(string $icon): string {
        $icon = trim($icon);
        if ($icon === '') {
            return '';
        }
        $parts = preg_split('/\s+/', $icon) ?: [];
        $sanitized = array_filter(array_map('sanitize_html_class', $parts));
        return implode(' ', $sanitized);
    }

    private static function normalize_path(string $url): string {
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        $path = untrailingslashit($path);
        return $path !== '' ? $path : '/';
    }

    private static function path_is_active(string $current, string $target): bool {
        // Normalización simple para coincidencia
        if ($target === '/' && $current !== '/') return false;
        return strpos($current, $target) !== false;
    }
}