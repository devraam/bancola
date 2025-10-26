<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Settings {

    const OPTION_KEY = 'bankitos_options';
    const PAGE_SLUG  = 'bankitos-settings';

    public static function init() : void {
        add_action('admin_menu',        [__CLASS__, 'add_menu']);
        add_action('admin_init',        [__CLASS__, 'register_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
    }

    public static function get_all() : array {
        $opts = get_option(self::OPTION_KEY);
        return is_array($opts) ? $opts : [];
    }
    public static function get(string $key, $default = null) {
        $opts = self::get_all();
        return array_key_exists($key, $opts) ? $opts[$key] : $default;
    }

    public static function add_menu() : void {
        add_options_page('Bankitos','Bankitos','manage_options',self::PAGE_SLUG,[__CLASS__,'render_page']);
    }

    public static function register_settings() : void {
        register_setting('bankitos', self::OPTION_KEY, [
            'type'              => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_options'],
            'default'           => [],
        ]);

        add_settings_section('bankitos_section_main','Ajustes generales de Bankitos',function(){
            echo '<p>Configura reCAPTCHA, caducidad de invitaciones y remitente.</p>';
        }, self::PAGE_SLUG);

        add_settings_field('recaptcha_site','reCAPTCHA v3 - Site key',[__CLASS__,'field_text'], self::PAGE_SLUG,'bankitos_section_main',['key'=>'recaptcha_site']);
        add_settings_field('recaptcha_secret','reCAPTCHA v3 - Secret key',[__CLASS__,'field_text'], self::PAGE_SLUG,'bankitos_section_main',['key'=>'recaptcha_secret']);
        add_settings_field('invite_expiry_days','Caducidad de invitaciones (días)',[__CLASS__,'field_number'], self::PAGE_SLUG,'bankitos_section_main',['key'=>'invite_expiry_days','min'=>1,'step'=>1,'placeholder'=>7]);
        add_settings_field('from_name','Nombre remitente',[__CLASS__,'field_text'], self::PAGE_SLUG,'bankitos_section_main',['key'=>'from_name','placeholder'=>get_bloginfo('name')]);
        add_settings_field('from_email','Correo remitente',[__CLASS__,'field_text'], self::PAGE_SLUG,'bankitos_section_main',['key'=>'from_email','placeholder'=>get_bloginfo('admin_email')]);
        add_settings_field('email_template_invite','Plantilla de correo (Invitación)',[__CLASS__,'field_textarea'], self::PAGE_SLUG,'bankitos_section_main',['key'=>'email_template_invite']);
    }

    public static function sanitize_options($input) : array {
        $out = is_array($input) ? $input : [];
        $out['recaptcha_site']     = isset($input['recaptcha_site']) ? sanitize_text_field($input['recaptcha_site']) : '';
        $out['recaptcha_secret']   = isset($input['recaptcha_secret']) ? sanitize_text_field($input['recaptcha_secret']) : '';
        $out['invite_expiry_days'] = isset($input['invite_expiry_days']) ? max(1, intval($input['invite_expiry_days'])) : 7;
        $out['from_name']          = isset($input['from_name']) ? sanitize_text_field($input['from_name']) : get_bloginfo('name');
        $out['from_email']         = isset($input['from_email']) ? sanitize_email($input['from_email']) : get_bloginfo('admin_email');
        $out['email_template_invite'] = isset($input['email_template_invite']) ? wp_kses_post($input['email_template_invite']) : '';
        return $out;
    }

    public static function render_page() : void {
        if (!current_user_can('manage_options')) return; ?>
        <div class="wrap bankitos-wrap">
            <h1>Bankitos – Ajustes</h1>
            <form method="post" action="options.php">
                <?php settings_fields('bankitos'); do_settings_sections(self::PAGE_SLUG); submit_button('Guardar cambios'); ?>
            </form>
        </div>
    <?php }

    public static function field_text($args) : void {
        $key = $args['key'];
        $ph  = isset($args['placeholder']) ? $args['placeholder'] : '';
        $val = self::get($key, '');
        printf('<input type="text" class="regular-text" name="%1$s[%2$s]" value="%3$s" placeholder="%4$s" />',
            esc_attr(self::OPTION_KEY), esc_attr($key), esc_attr($val), esc_attr($ph));
    }
    public static function field_number($args) : void {
        $key=$args['key']; $min=intval($args['min']??1); $step=intval($args['step']??1); $ph=$args['placeholder']??''; $val=self::get($key,'');
        printf('<input type="number" class="small-text" min="%1$d" step="%2$d" name="%3$s[%4$s]" value="%5$s" placeholder="%6$s" />',
            $min,$step,esc_attr(self::OPTION_KEY),esc_attr($key),esc_attr($val),esc_attr($ph));
    }
    public static function field_textarea($args) : void {
        $key=$args['key']; $val=self::get($key,'');
        printf('<textarea class="large-text code" rows="10" name="%1$s[%2$s]">%3$s</textarea>',esc_attr(self::OPTION_KEY),esc_attr($key),esc_textarea($val));
    }
    public static function enqueue_admin_assets($hook) : void {
        if ($hook === 'settings_page_' . self::PAGE_SLUG) {
            wp_enqueue_style('bankitos-admin', plugins_url('assets/css/bankitos.css', dirname(__FILE__)), [], '1.0');
        }
    }
}
