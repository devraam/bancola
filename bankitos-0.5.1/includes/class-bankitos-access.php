<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Access {

    public static function init() {
        add_action('template_redirect', [__CLASS__, 'redirect_logged_in_from_login']);
        add_action('admin_init',        [__CLASS__, 'restrict_admin_for_non_admins']);
    }

    public static function redirect_logged_in_from_login() {
        if (!is_user_logged_in()) return;
        if (is_page() && self::is_current_page_slug('acceder')) {
            wp_safe_redirect(site_url('/panel'));
            exit;
        }
    }

    public static function restrict_admin_for_non_admins() {
        if (is_user_logged_in() && is_admin() && !current_user_can('administrator') && !(defined('DOING_AJAX') && DOING_AJAX)) {
            wp_safe_redirect(site_url('/panel'));
            exit;
        }
        if (is_user_logged_in() && !current_user_can('administrator')) {
            show_admin_bar(false);
        }
    }

    private static function is_current_page_slug(string $slug): bool {
        $obj = get_queried_object();
        return $obj && !empty($obj->post_name) && $obj->post_name === $slug;
    }
}
add_action('init', ['Bankitos_Access', 'init']);
