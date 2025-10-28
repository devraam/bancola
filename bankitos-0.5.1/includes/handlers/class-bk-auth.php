<?php
if (!defined('ABSPATH')) exit;

class BK_Auth_Handler {
    public static function init() {
        add_action('admin_post_nopriv_bankitos_do_login',  [__CLASS__,'do_login']);
        add_action('admin_post_bankitos_do_login',         [__CLASS__,'do_login']);
        add_action('admin_post_nopriv_bankitos_do_register',[__CLASS__,'do_register']);
        add_action('admin_post_bankitos_do_register',      [__CLASS__,'do_register']);
    }
    public static function do_login() {
        check_admin_referer('bankitos_do_login');
        if (class_exists('Bankitos_Recaptcha') && Bankitos_Recaptcha::is_enabled()) {
            $token = sanitize_text_field($_POST['g-recaptcha-response'] ?? '');
            if (!$token || !Bankitos_Recaptcha::verify_token($token)) {
                wp_safe_redirect(add_query_arg('err','recaptcha', wp_get_referer() ?: site_url('/acceder'))); exit;
            }
        }
        $creds = [
            'user_login'    => sanitize_text_field($_POST['email'] ?? ''),
            'user_password' => (string)($_POST['password'] ?? ''),
            'remember'      => !empty($_POST['remember']),
        ];
        $user = wp_signon($creds, is_ssl());
        if (is_wp_error($user)) {
            wp_safe_redirect(add_query_arg('err','credenciales', wp_get_referer() ?: site_url('/acceder'))); exit;
        }
        wp_safe_redirect(site_url('/panel')); exit;
    }
    public static function do_register() {
        check_admin_referer('bankitos_do_register');
        if (class_exists('Bankitos_Recaptcha') && Bankitos_Recaptcha::is_enabled()) {
            $token = sanitize_text_field($_POST['g-recaptcha-response'] ?? '');
            if (!$token || !Bankitos_Recaptcha::verify_token($token)) {
                wp_safe_redirect(add_query_arg('err','recaptcha', wp_get_referer() ?: site_url('/registrarse'))); exit;
            }
        }
        $email = sanitize_email($_POST['email'] ?? ''); $pass  = (string)($_POST['password'] ?? ''); $name  = sanitize_text_field($_POST['name'] ?? '');
        if (!$email || !$pass) { wp_safe_redirect(add_query_arg('err','validacion', wp_get_referer() ?: site_url('/registrarse'))); exit; }
        $username = sanitize_user(current(explode('@', $email)));
        if (username_exists($username)) $username .= wp_generate_password(4, false, false);
        $user_id = wp_create_user($username, $pass, $email);
        if (is_wp_error($user_id)) { wp_safe_redirect(add_query_arg('err','crear', wp_get_referer() ?: site_url('/registrarse'))); exit; }
        wp_update_user(['ID'=>$user_id,'display_name'=>$name ?: $username,'nickname'=>$name ?: $username]);
        $u = new WP_User($user_id); $u->set_role('socio_general');
        wp_set_current_user($user_id); wp_set_auth_cookie($user_id, true);
        wp_safe_redirect(site_url('/panel')); exit;
    }
}
