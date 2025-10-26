<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Recaptcha {

    private static function keys() : array {
        $site = ''; $secret = '';
        if (class_exists('Bankitos_Settings')) {
            $site   = (string) Bankitos_Settings::get('recaptcha_site', '');
            $secret = (string) Bankitos_Settings::get('recaptcha_secret', '');
        }
        if (!$site   && defined('BANKITOS_RECAPTCHA_SITE'))   $site   = BANKITOS_RECAPTCHA_SITE;
        if (!$secret && defined('BANKITOS_RECAPTCHA_SECRET')) $secret = BANKITOS_RECAPTCHA_SECRET;
        if (!$site)   $site   = apply_filters('bankitos_recaptcha_site', '');
        if (!$secret) $secret = apply_filters('bankitos_recaptcha_secret', '');
        return ['site' => $site ?: '', 'secret' => $secret ?: ''];
    }

    public static function site_key() : string { return self::keys()['site']; }
    public static function secret_key() : string { return self::keys()['secret']; }

    public static function print_badge() : void {
        $site = self::site_key();
        if (!$site) return;
        printf('<script src="https://www.google.com/recaptcha/api.js?render=%s"></script>', esc_attr($site));
    }

    public static function verify_token(string $token) : bool {
        $secret = self::secret_key();
        if (!$secret || !$token) return false;
        $resp = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'timeout' => 8,
            'body'    => [
                'secret'   => $secret,
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ],
        ]);
        if (is_wp_error($resp)) return false;
        $code = wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        return ($code === 200 && !empty($body['success']));
    }
}
