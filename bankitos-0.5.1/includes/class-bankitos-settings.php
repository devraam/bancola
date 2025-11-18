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
        add_settings_field('mailjet_api_key','Mailjet API Key',[__CLASS__,'field_text'], self::PAGE_SLUG,'bankitos_section_main',['key'=>'mailjet_api_key','placeholder'=>'public key']);
        add_settings_field('mailjet_secret_key','Mailjet Secret Key',[__CLASS__,'field_text'], self::PAGE_SLUG,'bankitos_section_main',['key'=>'mailjet_secret_key','placeholder'=>'private key','type'=>'password']);
        add_settings_field('email_template_invite','Plantilla de correo (Invitación)',[__CLASS__,'field_textarea'], self::PAGE_SLUG,'bankitos_section_main',['key'=>'email_template_invite']);
    }

    public static function sanitize_options($input) : array {
        $out = is_array($input) ? $input : [];
        $out['recaptcha_site']     = isset($input['recaptcha_site']) ? sanitize_text_field($input['recaptcha_site']) : '';
        $out['recaptcha_secret']   = isset($input['recaptcha_secret']) ? sanitize_text_field($input['recaptcha_secret']) : '';
        $out['invite_expiry_days'] = isset($input['invite_expiry_days']) ? max(1, intval($input['invite_expiry_days'])) : 7;
        $out['from_name']          = isset($input['from_name']) ? sanitize_text_field($input['from_name']) : get_bloginfo('name');
        $out['from_email']         = isset($input['from_email']) ? sanitize_email($input['from_email']) : get_bloginfo('admin_email');
        $out['mailjet_api_key']       = isset($input['mailjet_api_key']) ? sanitize_text_field($input['mailjet_api_key']) : '';
        $out['mailjet_secret_key']    = isset($input['mailjet_secret_key']) ? sanitize_text_field($input['mailjet_secret_key']) : '';
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
            <?php self::render_shortcodes_help(); ?>
        </div>
    <?php }

    public static function field_text($args) : void {
        $key  = $args['key'];
        $ph   = isset($args['placeholder']) ? $args['placeholder'] : '';
        $type = isset($args['type']) && in_array($args['type'], ['text','password'], true) ? $args['type'] : 'text';
        $val  = self::get($key, '');
        printf('<input type="%5$s" class="regular-text" name="%1$s[%2$s]" value="%3$s" placeholder="%4$s" autocomplete="off" />',
            esc_attr(self::OPTION_KEY), esc_attr($key), esc_attr($val), esc_attr($ph), esc_attr($type));
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

    protected static function render_shortcodes_help() : void {
        $shortcodes = [
            'bankitos_login' => __('Muestra el formulario de acceso para miembros.', 'bankitos'),
            'bankitos_register' => __('Despliega el formulario de registro para nuevos miembros.', 'bankitos'),
            'bankitos_panel' => __('Panel principal del usuario con acceso rápido a su B@nko.', 'bankitos'),
            'bankitos_panel_info' => __('Resumen financiero y datos clave del B@nko del usuario.', 'bankitos'),
            'bankitos_panel_members' => __('Tabla de miembros e invitaciones del B@nko (solo Presidente).', 'bankitos'),
            'bankitos_panel_members_invite' => __('Formulario para gestionar invitaciones de miembros (solo Presidente).', 'bankitos'),
            'bankitos_panel_quick_actions' => __('Accesos directos a las acciones más usadas dentro del B@nko.', 'bankitos'),
            'bankitos_crear_banco_form' => __('Formulario para crear un nuevo B@nko.', 'bankitos'),
            'bankitos_aporte_form' => __('Formulario de registro de aportes de capital.', 'bankitos'),
            'bankitos_tesorero_aportes' => __('Listado de aportes pendientes para el rol de Tesorero.', 'bankitos'),
            'bankitos_veedor_aportes' => __('Listado de aportes para seguimiento desde el rol de Veedor.', 'bankitos'),
            'bankitos_invite_portal' => __('Portal para aceptar o rechazar invitaciones recibidas.', 'bankitos'),
        ];

        echo '<div class="bankitos-shortcodes-doc">';
        echo '<h2>' . esc_html__('Documentación de shortcodes', 'bankitos') . '</h2>';
        echo '<p>' . esc_html__('Utiliza los siguientes shortcodes en tus páginas para habilitar las funciones principales de Bankitos.', 'bankitos') . '</p>';
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>' . esc_html__('Shortcode', 'bankitos') . '</th><th>' . esc_html__('Descripción', 'bankitos') . '</th></tr></thead>';
        echo '<tbody>';
        foreach ($shortcodes as $tag => $description) {
            echo '<tr>';
            echo '<td><code>[' . esc_html($tag) . ']</code></td>';
            echo '<td>' . esc_html($description) . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
}