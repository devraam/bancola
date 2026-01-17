<?php
if (!defined('ABSPATH')) exit;

class BK_Auth_Handler {
    public static function init() {
        add_action('admin_post_nopriv_bankitos_do_login',  [__CLASS__,'do_login']);
        add_action('admin_post_bankitos_do_login',         [__CLASS__,'do_login']);
        add_action('admin_post_nopriv_bankitos_do_register',[__CLASS__,'do_register']);
        add_action('admin_post_bankitos_do_register',      [__CLASS__,'do_register']);
        add_action('admin_post_nopriv_bankitos_do_recover', [__CLASS__,'do_recover']);
        add_action('admin_post_bankitos_do_recover',        [__CLASS__,'do_recover']);
        add_action('admin_post_nopriv_bankitos_do_reset_password', [__CLASS__,'do_reset_password']);
        add_action('admin_post_bankitos_do_reset_password',        [__CLASS__,'do_reset_password']);
    }
    public static function do_login() {
        check_admin_referer('bankitos_do_login');
        if (class_exists('Bankitos_Recaptcha')) {
            if (!Bankitos_Recaptcha::is_enabled()) {
                wp_safe_redirect(add_query_arg('err','recaptcha_config', wp_get_referer() ?: site_url('/acceder'))); exit;
            }
            $token = sanitize_text_field($_POST['g-recaptcha-response'] ?? '');
            if (!$token || !Bankitos_Recaptcha::verify_token($token)) {
                wp_safe_redirect(add_query_arg('err','recaptcha', wp_get_referer() ?: site_url('/acceder'))); exit;
            }
        }
        $token = isset($_POST['invite_token']) ? sanitize_text_field($_POST['invite_token']) : '';

        $creds = [
            'user_login'    => sanitize_text_field($_POST['email'] ?? ''),
            'user_password' => (string)($_POST['password'] ?? ''),
            'remember'      => !empty($_POST['remember']),
        ];
        $user = wp_signon($creds, is_ssl());
        if (is_wp_error($user)) {
            wp_safe_redirect(add_query_arg('err','credenciales', wp_get_referer() ?: site_url('/acceder'))); exit;
        }
        if ($token && class_exists('BK_Invites_Handler')) {
            $result = BK_Invites_Handler::accept_invite_for_user($token, $user);
            if (is_wp_error($result)) {
                wp_logout();
                $redirect = add_query_arg([
                    'invite_token' => $token,
                    'err'          => 'invite_accept',
                ], BK_Invites_Handler::portal_url());
                wp_safe_redirect($redirect);
                exit;
            }
            wp_safe_redirect(add_query_arg('ok', 'invite_accepted', site_url('/panel')));
            exit;
        }

        wp_safe_redirect(site_url('/panel'));
        exit;
    }
    public static function do_register() {
        check_admin_referer('bankitos_do_register');
        if (class_exists('Bankitos_Recaptcha')) {
            if (!Bankitos_Recaptcha::is_enabled()) {
                wp_safe_redirect(add_query_arg('err','recaptcha_config', wp_get_referer() ?: site_url('/registrarse'))); exit;
            }
            $token = sanitize_text_field($_POST['g-recaptcha-response'] ?? '');
            if (!$token || !Bankitos_Recaptcha::verify_token($token)) {
                wp_safe_redirect(add_query_arg('err','recaptcha', wp_get_referer() ?: site_url('/registrarse'))); exit;
            }
        }
        $token = isset($_POST['invite_token']) ? sanitize_text_field($_POST['invite_token']) : '';

        $email = sanitize_email($_POST['email'] ?? ''); $pass  = (string)($_POST['password'] ?? ''); $name  = sanitize_text_field($_POST['name'] ?? '');
        if (!$email || !$pass) { wp_safe_redirect(add_query_arg('err','validacion', wp_get_referer() ?: site_url('/registrarse'))); exit; }
        if (class_exists('Bankitos_Domains') && !Bankitos_Domains::is_email_allowed($email)) {
            wp_safe_redirect(add_query_arg('err','domain_not_allowed', wp_get_referer() ?: site_url('/registrarse'))); exit;
        }
        $username = sanitize_user(current(explode('@', $email)));
        if (username_exists($username)) $username .= wp_generate_password(4, false, false);
        $user_id = wp_create_user($username, $pass, $email);
        if (is_wp_error($user_id)) { wp_safe_redirect(add_query_arg('err','crear', wp_get_referer() ?: site_url('/registrarse'))); exit; }
        wp_update_user(['ID'=>$user_id,'display_name'=>$name ?: $username,'nickname'=>$name ?: $username]);
        $u = new WP_User($user_id); $u->set_role('socio_general');
        wp_set_current_user($user_id); wp_set_auth_cookie($user_id, true);
        if ($token && class_exists('BK_Invites_Handler')) {
            $result = BK_Invites_Handler::accept_invite_for_user($token, $u);
            if (is_wp_error($result)) {
                $redirect = add_query_arg('err', 'invite_accept', site_url('/panel'));
                wp_safe_redirect($redirect);
                exit;
            }
            wp_safe_redirect(add_query_arg('ok', 'invite_accepted', site_url('/panel')));
            exit;
        }

        wp_safe_redirect(site_url('/panel'));
        exit;
    }

    public static function do_recover() {
        check_admin_referer('bankitos_do_recover');
        $email = sanitize_email($_POST['email'] ?? '');
        $redirect_base = site_url('/acceder');
        if (!$email) {
            wp_safe_redirect(add_query_arg(['mode' => 'recover', 'err' => 'recovery'], $redirect_base));
            exit;
        }

        $user = get_user_by('email', $email);
        if ($user) {
            $key = get_password_reset_key($user);
            if (!is_wp_error($key)) {
                $reset_link = add_query_arg(
                    [
                        'mode'  => 'reset',
                        'login' => $user->user_login,
                        'key'   => $key,
                    ],
                    $redirect_base
                );
                $subject = sprintf(__('Restablecer contraseña en %s', 'bankitos'), get_bloginfo('name'));
                $message = sprintf(
                    __("Hola,\n\nHemos recibido una solicitud para restablecer tu contraseña. Usa el siguiente enlace para crear una nueva:\n\n%s\n\nSi no solicitaste este cambio, puedes ignorar este correo.", 'bankitos'),
                    esc_url_raw($reset_link)
                );
                $headers = self::mail_headers();
                wp_mail($user->user_email, $subject, $message, $headers);
            }
        }

        wp_safe_redirect(add_query_arg(['mode' => 'recover', 'ok' => 'recovery_sent'], $redirect_base));
        exit;
    }

    public static function do_reset_password() {
        check_admin_referer('bankitos_do_reset_password');
        $login = sanitize_text_field($_POST['login'] ?? '');
        $key = sanitize_text_field($_POST['key'] ?? '');
        $pass = (string)($_POST['password'] ?? '');
        $pass_confirm = (string)($_POST['password_confirm'] ?? '');

        $redirect_base = site_url('/acceder');
        $redirect_args = [
            'mode'  => 'reset',
            'login' => $login,
            'key'   => $key,
        ];

        if (!$login || !$key) {
            wp_safe_redirect(add_query_arg(['mode' => 'recover', 'err' => 'invalid_reset'], $redirect_base));
            exit;
        }

        if (!$pass || $pass !== $pass_confirm) {
            wp_safe_redirect(add_query_arg(array_merge($redirect_args, ['err' => 'reset_password']), $redirect_base));
            exit;
        }

        $user = check_password_reset_key($key, $login);
        if (is_wp_error($user)) {
            wp_safe_redirect(add_query_arg(['mode' => 'recover', 'err' => 'invalid_reset'], $redirect_base));
            exit;
        }

        reset_password($user, $pass);
        wp_safe_redirect(add_query_arg(['ok' => 'password_reset'], $redirect_base));
        exit;
    }

    private static function mail_headers(): array {
        $from_name = get_bloginfo('name');
        $from_email = get_bloginfo('admin_email');

        if (class_exists('Bankitos_Settings')) {
            $custom_from = Bankitos_Settings::get('from_email', $from_email);
            if (is_email($custom_from)) {
                $from_email = $custom_from;
            }
        }

        $headers = [];
        if ($from_email) {
            $headers[] = 'From: ' . sprintf('%s <%s>', $from_name, $from_email);
        }
        return $headers;
    }
}
