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
        if (!is_user_logged_in()) {
            return;
        }

        $is_admin_post = false;
        if (is_admin()) {
            $current_screen = $GLOBALS['pagenow'] ?? '';
            $is_admin_post = ($current_screen === 'admin-post.php');
        }

        if ($is_admin_post) {
            $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';
            $allowed_actions = apply_filters(
                'bankitos_public_admin_post_actions',
                [
                    'bankitos_front_create',
                    'bankitos_aporte_submit',
                    'bankitos_aporte_approve',
                    'bankitos_aporte_reject',
                    'bankitos_do_login',
                    'bankitos_do_register',
                    'bankitos_send_invites',
                    'bankitos_accept_invite',
                    'bankitos_reject_invite',
                ]
            );
            if (in_array($action, $allowed_actions, true)) {
                if (!current_user_can('administrator')) {
                    show_admin_bar(false);
                }
                return;
            }
        }

        if (is_admin() && !current_user_can('administrator') && !(defined('DOING_AJAX') && DOING_AJAX)) {
            wp_safe_redirect(site_url('/panel'));
            exit;
        }
        if (!current_user_can('administrator')) {
            show_admin_bar(false);
        }
    }

    private static function is_current_page_slug(string $slug): bool {
        $obj = get_queried_object();
        return $obj && !empty($obj->post_name) && $obj->post_name === $slug;
    }
}
add_action('init', ['Bankitos_Access', 'init']);
