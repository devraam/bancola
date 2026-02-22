<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Access {

    public static function init() {
        add_action('template_redirect', [__CLASS__, 'redirect_logged_in_from_login']);
        add_action('admin_init',        [__CLASS__, 'restrict_admin_for_non_admins']);
        add_action('admin_menu',        [__CLASS__, 'limit_global_manager_menu'], 999);
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
        $is_global_manager = current_user_can(Bankitos_Admin_Reports::CAPABILITY);

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
                    'bankitos_resend_invite',   
                    'bankitos_update_invite',   
                    'bankitos_cancel_invite',
                    'bankitos_assign_role',
                    'bankitos_credito_solicitar',
                    'bankitos_credito_resolver',
                    'bankitos_credit_payment_submit',
                    'bankitos_credit_payment_approve',
                    'bankitos_credit_payment_reject',
                    'bankitos_credit_payment_download',
                    'bankitos_credit_disburse', // <--- Agregado
                    'bankitos_credit_disbursement_download', // <--- Agregado
                    Bankitos_Admin_Reports::EXPORT_ACTION,
                    Bankitos_Admin_Reports::TOGGLE_ACTION,
                    Bankitos_Admin_Reports::DELETE_ACTION,
                    class_exists('Bankitos_Domains') ? Bankitos_Domains::ACTION_SAVE : '',
                ]
            );
            if (in_array($action, $allowed_actions, true)) {
                if (!current_user_can('administrator') && !$is_global_manager) {
                    show_admin_bar(false);
                }
                return;
            }
        }

        if (is_admin() && !current_user_can('administrator')) {
            if ($is_global_manager) {
                if (!(defined('DOING_AJAX') && DOING_AJAX) && !self::is_global_manager_allowed_page()) {
                    wp_safe_redirect(admin_url('admin.php?page=' . Bankitos_Admin_Reports::PAGE_SLUG));
                    exit;
                }
            } elseif (!(defined('DOING_AJAX') && DOING_AJAX)) {
                wp_safe_redirect(site_url('/panel'));
                exit;
            }
        }
        if (!current_user_can('administrator') && !$is_global_manager) {
            show_admin_bar(false);
        }
    }

    public static function limit_global_manager_menu(): void {
        if (!current_user_can(Bankitos_Admin_Reports::CAPABILITY) || current_user_can('administrator')) {
            return;
        }

        $allowed_top = [
            Bankitos_Admin_Reports::PAGE_SLUG,
            'edit.php?post_type=' . Bankitos_CPT::SLUG_BANCO,
        ];
        global $menu, $submenu;

        foreach ((array) $menu as $item) {
            $slug = $item[2] ?? '';
            if (!in_array($slug, $allowed_top, true)) {
                remove_menu_page($slug);
            }
        }

        $bancos_menu = 'edit.php?post_type=' . Bankitos_CPT::SLUG_BANCO;
        if (isset($submenu[$bancos_menu])) {
            $submenu[$bancos_menu] = array_values(array_filter(
                $submenu[$bancos_menu],
                static function ($entry) use ($bancos_menu) {
                    $slug = $entry[2] ?? '';
                    return $slug === $bancos_menu;
                }
            ));
        }
    }

    private static function is_current_page_slug(string $slug): bool {
        $obj = get_queried_object();
        return $obj && !empty($obj->post_name) && $obj->post_name === $slug;
    }

    private static function is_global_manager_allowed_page(): bool {
        $screen = $GLOBALS['pagenow'] ?? '';
        if ($screen === 'admin.php') {
            $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
            return in_array($page, [Bankitos_Admin_Reports::PAGE_SLUG, class_exists('Bankitos_Domains') ? Bankitos_Domains::PAGE_SLUG : ''], true);
        }
        if ($screen === 'edit.php') {
            $post_type = isset($_GET['post_type']) ? sanitize_key(wp_unslash($_GET['post_type'])) : 'post';
            return $post_type === Bankitos_CPT::SLUG_BANCO;
        }
        if (in_array($screen, ['post.php', 'post-new.php'], true)) {
            $post_type = isset($_GET['post_type']) ? sanitize_key(wp_unslash($_GET['post_type'])) : '';
            if (!$post_type && isset($_GET['post'])) {
                $post_type = get_post_type(absint($_GET['post']));
            }
            return $post_type === Bankitos_CPT::SLUG_BANCO;
        }
        return false;
    }
}
add_action('init', ['Bankitos_Access', 'init']);