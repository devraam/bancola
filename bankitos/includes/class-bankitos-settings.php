<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Settings {

    const OPTION_KEY = 'bankitos_options';
    const MENU_SLUG  = 'bankitos-config';
    const PAGE_SLUG_RECAPTCHA   = 'bankitos-config';
    const PAGE_SLUG_MAILJET     = 'bankitos-config-mailjet';
    const PAGE_SLUG_INVITATION  = 'bankitos-config-invitacion';
    const PAGE_SLUG_MOBILE_MENU = 'bankitos-config-menu-movil';
    const PAGE_SLUG_SHORTCODES  = 'bankitos-config-shortcodes';

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
         add_menu_page(
            'B@nkos Config',
            'B@nkos Config',
            'manage_options',
            self::MENU_SLUG,
            [__CLASS__, 'render_recaptcha_page'],
            'dashicons-admin-generic',
            58
        );
        add_submenu_page(
            self::MENU_SLUG,
            __('Configuración reCAPTCHA', 'bankitos'),
            __('reCAPTCHA', 'bankitos'),
            'manage_options',
            self::PAGE_SLUG_RECAPTCHA,
            [__CLASS__, 'render_recaptcha_page']
        );
        add_submenu_page(
            self::MENU_SLUG,
            __('Configuración Mailjet', 'bankitos'),
            __('Mailjet', 'bankitos'),
            'manage_options',
            self::PAGE_SLUG_MAILJET,
            [__CLASS__, 'render_mailjet_page']
        );
        add_submenu_page(
            self::MENU_SLUG,
            __('Plantilla de invitación', 'bankitos'),
            __('Correo de invitación', 'bankitos'),
            'manage_options',
            self::PAGE_SLUG_INVITATION,
            [__CLASS__, 'render_invitation_page']
        );
        add_submenu_page(
            self::MENU_SLUG,
            __('Menú móvil por roles', 'bankitos'),
            __('Menú móvil', 'bankitos'),
            'manage_options',
            self::PAGE_SLUG_MOBILE_MENU,
            [__CLASS__, 'render_mobile_menu_page']
        );
        add_submenu_page(
            self::MENU_SLUG,
            __('Documentación de shortcodes', 'bankitos'),
            __('Shortcodes', 'bankitos'),
            'manage_options',
            self::PAGE_SLUG_SHORTCODES,
            [__CLASS__, 'render_shortcodes_page']
        );
    }

    public static function register_settings() : void {
        register_setting('bankitos', self::OPTION_KEY, [
            'type'              => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_options'],
            'default'           => [],
        ]);

        add_settings_section('bankitos_section_recaptcha', __('Configuración reCAPTCHA', 'bankitos'), function () {
            echo '<p>' . esc_html__('Configura las llaves de reCAPTCHA v3 para proteger el acceso y registro.', 'bankitos') . '</p>';
        }, self::PAGE_SLUG_RECAPTCHA);

        add_settings_field('recaptcha_site','reCAPTCHA v3 - Site key',[__CLASS__,'field_text'], self::PAGE_SLUG_RECAPTCHA,'bankitos_section_recaptcha',['key'=>'recaptcha_site']);
        add_settings_field('recaptcha_secret','reCAPTCHA v3 - Secret key',[__CLASS__,'field_text'], self::PAGE_SLUG_RECAPTCHA,'bankitos_section_recaptcha',['key'=>'recaptcha_secret']);

        add_settings_section('bankitos_section_mailjet', __('Configuración Mailjet', 'bankitos'), function () {
            echo '<p>' . esc_html__('Define las credenciales para el envío de correos con Mailjet.', 'bankitos') . '</p>';
        }, self::PAGE_SLUG_MAILJET);

        add_settings_field('mailjet_api_key','Mailjet API Key',[__CLASS__,'field_text'], self::PAGE_SLUG_MAILJET,'bankitos_section_mailjet',['key'=>'mailjet_api_key','placeholder'=>'public key']);
        add_settings_field('mailjet_secret_key','Mailjet Secret Key',[__CLASS__,'field_text'], self::PAGE_SLUG_MAILJET,'bankitos_section_mailjet',['key'=>'mailjet_secret_key','placeholder'=>'private key','type'=>'password']);

        add_settings_section('bankitos_section_invitation', __('Correo de invitación', 'bankitos'), function () {
            echo '<p>' . esc_html__('Configura el remitente, la vigencia y la plantilla del correo de invitación.', 'bankitos') . '</p>';
        }, self::PAGE_SLUG_INVITATION);

        add_settings_field('invite_expiry_days','Caducidad de invitaciones (días)',[__CLASS__,'field_number'], self::PAGE_SLUG_INVITATION,'bankitos_section_invitation',['key'=>'invite_expiry_days','min'=>1,'step'=>1,'placeholder'=>7]);
        add_settings_field('from_name','Nombre remitente',[__CLASS__,'field_text'], self::PAGE_SLUG_INVITATION,'bankitos_section_invitation',['key'=>'from_name','placeholder'=>get_bloginfo('name')]);
        add_settings_field('from_email','Correo remitente',[__CLASS__,'field_text'], self::PAGE_SLUG_INVITATION,'bankitos_section_invitation',['key'=>'from_email','placeholder'=>get_bloginfo('admin_email')]);
        add_settings_field('email_template_invite','Plantilla de correo (Invitación)',[__CLASS__,'field_textarea'], self::PAGE_SLUG_INVITATION,'bankitos_section_invitation',['key'=>'email_template_invite']);

        add_settings_section('bankitos_section_mobile_menu', __('Menú móvil por roles', 'bankitos'), function () {
            echo '<p>' . esc_html__('Configura los botones del menú móvil (solo visible en celulares y para usuarios autenticados).', 'bankitos') . '</p>';
        }, self::PAGE_SLUG_MOBILE_MENU);

        foreach (self::get_mobile_menu_roles() as $role_key => $role_label) {
            add_settings_field(
                'mobile_menu_' . $role_key,
                sprintf(__('Menú para %s', 'bankitos'), $role_label),
                [__CLASS__, 'field_mobile_menu'],
                self::PAGE_SLUG_MOBILE_MENU,
                'bankitos_section_mobile_menu',
                ['role' => $role_key]
            );
        }
    }

    public static function sanitize_options($input) : array {
        $input = is_array($input) ? $input : [];
        $out = self::get_all();
        $out = is_array($out) ? $out : [];

        if (array_key_exists('recaptcha_site', $input)) {
            $out['recaptcha_site'] = sanitize_text_field($input['recaptcha_site']);
        } elseif (!isset($out['recaptcha_site'])) {
            $out['recaptcha_site'] = '';
        }

        if (array_key_exists('recaptcha_secret', $input)) {
            $out['recaptcha_secret'] = sanitize_text_field($input['recaptcha_secret']);
        } elseif (!isset($out['recaptcha_secret'])) {
            $out['recaptcha_secret'] = '';
        }

        if (array_key_exists('invite_expiry_days', $input)) {
            $out['invite_expiry_days'] = max(1, intval($input['invite_expiry_days']));
        } elseif (!isset($out['invite_expiry_days'])) {
            $out['invite_expiry_days'] = 7;
        }
        $default_name  = get_bloginfo('name');
        $default_email = get_bloginfo('admin_email');

       if (array_key_exists('from_name', $input)) {
            $from_name = sanitize_text_field($input['from_name']);
            $out['from_name'] = $from_name !== '' ? $from_name : $default_name;
        } elseif (!isset($out['from_name'])) {
            $out['from_name'] = $default_name;
        }

        if (array_key_exists('from_email', $input)) {
            $from_email = sanitize_email($input['from_email']);
            $out['from_email'] = is_email($from_email) ? $from_email : $default_email;
        } elseif (!isset($out['from_email'])) {
            $out['from_email'] = $default_email;
        }

        if (array_key_exists('mailjet_api_key', $input)) {
            $out['mailjet_api_key'] = sanitize_text_field($input['mailjet_api_key']);
        } elseif (!isset($out['mailjet_api_key'])) {
            $out['mailjet_api_key'] = '';
        }

        if (array_key_exists('mailjet_secret_key', $input)) {
            $out['mailjet_secret_key'] = sanitize_text_field($input['mailjet_secret_key']);
        } elseif (!isset($out['mailjet_secret_key'])) {
            $out['mailjet_secret_key'] = '';
        }

        if (array_key_exists('email_template_invite', $input)) {
            $out['email_template_invite'] = wp_kses_post($input['email_template_invite']);
        } elseif (!isset($out['email_template_invite'])) {
            $out['email_template_invite'] = '';
        }
        
        if (array_key_exists('mobile_menu', $input) && is_array($input['mobile_menu'])) {
            $mobile_menu = [];
            $menu_input = $input['mobile_menu'];
            $defaults = self::get_mobile_menu_defaults();
            $existing_menus = isset($out['mobile_menu']) && is_array($out['mobile_menu']) ? $out['mobile_menu'] : [];
            foreach (self::get_mobile_menu_roles() as $role_key => $role_label) {
                if (array_key_exists($role_key, $menu_input)) {
                    $raw = is_string($menu_input[$role_key]) ? $menu_input[$role_key] : '';
                    $mobile_menu[$role_key] = self::parse_mobile_menu_lines($raw);
                } elseif (isset($existing_menus[$role_key])) {
                    $mobile_menu[$role_key] = $existing_menus[$role_key];
                } else {
                    $mobile_menu[$role_key] = $defaults[$role_key] ?? [];
                }
            }
            $out['mobile_menu'] = $mobile_menu;
        } elseif (!isset($out['mobile_menu'])) {
            $out['mobile_menu'] = self::get_mobile_menu_defaults();
        }

        return $out;
    }

    public static function render_recaptcha_page() : void {
        self::render_settings_page(__('B@nkos Config - reCAPTCHA', 'bankitos'), self::PAGE_SLUG_RECAPTCHA);
    }

    public static function render_mailjet_page() : void {
        self::render_settings_page(__('B@nkos Config - Mailjet', 'bankitos'), self::PAGE_SLUG_MAILJET);
    }

    public static function render_invitation_page() : void {
        self::render_settings_page(__('B@nkos Config - Correo de invitación', 'bankitos'), self::PAGE_SLUG_INVITATION);
    }

    public static function render_mobile_menu_page() : void {
        self::render_settings_page(__('B@nkos Config - Menú móvil por roles', 'bankitos'), self::PAGE_SLUG_MOBILE_MENU);
    }

    public static function render_shortcodes_page() : void {
        if (!current_user_can('manage_options')) {
            return;
        } ?>
        <div class="wrap bankitos-wrap">
            <h1><?php echo esc_html__('B@nkos Config - Documentación de shortcodes', 'bankitos'); ?></h1>
            <?php self::render_shortcodes_help(); ?>
        </div>
    <?php }

    protected static function render_settings_page(string $title, string $page_slug): void {
        if (!current_user_can('manage_options')) {
            return;
        } ?>
        <div class="wrap bankitos-wrap">
            <h1><?php echo esc_html($title); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('bankitos'); do_settings_sections($page_slug); submit_button('Guardar cambios'); ?>
            </form>
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

    public static function field_mobile_menu($args): void {
        $role = $args['role'] ?? '';
        $items = self::get_mobile_menu_items($role);
        $value = self::format_mobile_menu_lines($items);
        printf(
            '<textarea class="large-text code" rows="6" name="%1$s[mobile_menu][%2$s]">%3$s</textarea>',
            esc_attr(self::OPTION_KEY),
            esc_attr($role),
            esc_textarea($value)
        );
        echo '<p class="description">' . esc_html__('Formato: <b>Etiqueta | clase-del-icono | /ruta</b> (una por línea).', 'bankitos') . '<br>' . 
             sprintf(esc_html__('Busca iconos aquí: %s (copia el nombre de la clase, ej: dashicons-chart-pie)', 'bankitos'), '<a href="https://developer.wordpress.org/resource/dashicons/" target="_blank">Dashicons</a>') . '</p>';
    }
    public static function enqueue_admin_assets($hook) : void {
        $allowed_hooks = [
            'toplevel_page_' . self::MENU_SLUG,
            self::MENU_SLUG . '_page_' . self::PAGE_SLUG_MAILJET,
            self::MENU_SLUG . '_page_' . self::PAGE_SLUG_INVITATION,
            self::MENU_SLUG . '_page_' . self::PAGE_SLUG_MOBILE_MENU,
            self::MENU_SLUG . '_page_' . self::PAGE_SLUG_SHORTCODES,
        ];
        if (in_array($hook, $allowed_hooks, true)) {
            wp_enqueue_style('bankitos-admin', BANKITOS_URL . 'assets/css/bankitos.css', [], '1.0');
        }
    }

    /**
     * Devuelve la clase CSS del pill según el texto del rol.
     */
    private static function role_pill_class(string $role): string {
        $role_lower = mb_strtolower($role);
        if (str_contains($role_lower, 'tesorero'))   return 'bk-pill--tesorero';
        if (str_contains($role_lower, 'presidente')) return 'bk-pill--presidente';
        if (str_contains($role_lower, 'veedor'))     return 'bk-pill--veedor';
        if (str_contains($role_lower, 'socio'))      return 'bk-pill--socio';
        return 'bk-pill--pub';
    }

    protected static function render_shortcodes_help() : void {
        $sections = self::get_shortcodes_reference();

        echo '<div class="bk-sc-doc">';

        // Cabecera + tips
        echo '<div class="bk-sc-doc__intro">';
        echo '<p>' . esc_html__('Cada tarjeta describe un shortcode: su función, el rol que lo activa, en qué página colocarlo y qué acciones realiza. Usa plantillas de ancho completo para evitar sidebars. Si el usuario no tiene el permiso requerido, el bloque se oculta automáticamente.', 'bankitos') . '</p>';
        echo '</div>';

        foreach ($sections as $section) {
            echo '<section class="bk-sc-doc__section">';

            // Cabecera de sección
            echo '<div class="bk-sc-doc__section-head">';
            echo '<h3 class="bk-sc-doc__section-title">' . esc_html($section['title']) . '</h3>';
            if (!empty($section['description'])) {
                echo '<p class="bk-sc-doc__section-desc">' . esc_html($section['description']) . '</p>';
            }
            echo '</div>';

            // Grid de tarjetas
            echo '<div class="bk-sc-doc__grid">';
            foreach ($section['items'] as $item) {
                $page    = $item['page'] ?? '';
                $roles   = array_map('trim', explode(',', $item['role']));

                echo '<article class="bk-sc-doc__card">';

                // --- Header de tarjeta: shortcode tag ---
                echo '<div class="bk-sc-doc__card-header">';
                echo '<code class="bk-sc-doc__tag">[' . esc_html($item['tag']) . ']</code>';
                echo '</div>';

                // --- Cuerpo: nombre + descripción ---
                echo '<div class="bk-sc-doc__card-body">';
                echo '<h4 class="bk-sc-doc__name">' . esc_html($item['name']) . '</h4>';
                echo '<p class="bk-sc-doc__summary">' . esc_html($item['summary']) . '</p>';
                echo '</div>';

                // --- Metadatos: página + roles ---
                echo '<div class="bk-sc-doc__card-meta">';

                if (!empty($page)) {
                    echo '<div class="bk-sc-doc__meta-row">';
                    echo '<span class="bk-sc-doc__meta-label">' . esc_html__('Página', 'bankitos') . '</span>';
                    echo '<span class="bk-sc-doc__page-pill">' . esc_html($page) . '</span>';
                    echo '</div>';
                }

                echo '<div class="bk-sc-doc__meta-row">';
                echo '<span class="bk-sc-doc__meta-label">' . esc_html__('Roles', 'bankitos') . '</span>';
                echo '<span class="bk-sc-doc__pills">';
                foreach ($roles as $role_label) {
                    $pill_class = self::role_pill_class($role_label);
                    echo '<span class="bk-sc-pill ' . esc_attr($pill_class) . '">' . esc_html($role_label) . '</span>';
                }
                echo '</span>';
                echo '</div>';

                echo '</div>'; // .bk-sc-doc__card-meta

                // --- Funcionalidades ---
                if (!empty($item['actions'])) {
                    echo '<ul class="bk-sc-doc__actions">';
                    foreach ($item['actions'] as $action) {
                        echo '<li>' . esc_html($action) . '</li>';
                    }
                    echo '</ul>';
                }

                echo '</article>';
            }
            echo '</div>'; // .bk-sc-doc__grid
            echo '</section>';
        }

        echo '</div>'; // .bk-sc-doc
    }

    protected static function get_shortcodes_reference(): array {
        return [
            [
                'title'       => __('Acceso y registro', 'bankitos'),
                'description' => __('Pantallas públicas para que los invitados entren o creen su usuario.', 'bankitos'),
                'items'       => [
                    [
                        'tag'     => 'bankitos_login',
                        'name'    => __('Acceso de socios', 'bankitos'),
                        'role'    => __('Público, Socios con invitación', 'bankitos'),
                        'page'    => '/acceder',
                        'summary' => __('Formulario de inicio de sesión compatible con reCAPTCHA y con tokens de invitación.', 'bankitos'),
                        'actions' => [
                            __('Valida reCAPTCHA si el administrador lo habilitó.', 'bankitos'),
                            __('Acepta el parámetro invite_token para redirigir al portal de invitaciones tras el login.', 'bankitos'),
                            __('Incluye enlace directo a la página de registro.', 'bankitos'),
                        ],
                    ],
                    [
                        'tag'     => 'bankitos_register',
                        'name'    => __('Registro de socios', 'bankitos'),
                        'role'    => __('Público', 'bankitos'),
                        'page'    => '/registrarse',
                        'summary' => __('Alta de nuevos usuarios, incluyendo registros que llegan con invite_token desde un correo de invitación.', 'bankitos'),
                        'actions' => [
                            __('Solicita nombre, email y contraseña para crear la cuenta.', 'bankitos'),
                            __('Cuando llega con invite_token vincula automáticamente al B@nko correcto.', 'bankitos'),
                        ],
                    ],
                    [
                        'tag'     => 'bankitos_invite_portal',
                        'name'    => __('Portal de invitaciones', 'bankitos'),
                        'role'    => __('Socios con invitación pendiente', 'bankitos'),
                        'page'    => '/panel',
                        'summary' => __('Vista para que el socio acepte o rechace una invitación pendiente a un B@nko. Se oculta si no hay invitación activa.', 'bankitos'),
                        'actions' => [
                            __('Muestra detalles del B@nko que invita: rol asignado, cuota y duración.', 'bankitos'),
                            __('Limpia el token de la URL tras confirmar la decisión para evitar reutilización.', 'bankitos'),
                        ],
                    ],
                ],
            ],
            [
                'title'       => __('Navegación móvil', 'bankitos'),
                'description' => __('Menú flotante optimizado para celulares, configurable por rol desde el admin.', 'bankitos'),
                'items'       => [
                    [
                        'tag'     => 'bankitos_mobile_menu',
                        'name'    => __('Menú móvil por roles', 'bankitos'),
                        'role'    => __('Socios autenticados', 'bankitos'),
                        'page'    => __('(automático — pie de página)', 'bankitos'),
                        'summary' => __('Barra de navegación flotante que muestra los accesos del rol activo y resalta la pantalla actual. Solo visible en dispositivos móviles.', 'bankitos'),
                        'actions' => [
                            __('El plugin lo inyecta en wp_footer sin necesidad de colocarlo manualmente.', 'bankitos'),
                            __('Los íconos y rutas por rol se configuran en B@nkos Config → Menú móvil.', 'bankitos'),
                        ],
                    ],
                ],
            ],
            [
                'title'       => __('Panel general del socio', 'bankitos'),
                'description' => __('Bloques base que conforman el panel principal de cualquier miembro. Se recomienda colocarlos en /panel en este orden.', 'bankitos'),
                'items'       => [
                    [
                        'tag'     => 'bankitos_panel',
                        'name'    => __('Bienvenida al panel', 'bankitos'),
                        'role'    => __('Socios autenticados', 'bankitos'),
                        'page'    => '/panel',
                        'summary' => __('Saludo inicial con el nombre del socio y el estado de su B@nko. Si aún no pertenece a un B@nko ofrece el botón para crearlo.', 'bankitos'),
                        'actions' => [
                            __('Detecta si el socio ya tiene B@nko y muestra el nombre del mismo.', 'bankitos'),
                            __('Ofrece acceso directo a crear un B@nko si el socio no pertenece a ninguno.', 'bankitos'),
                        ],
                    ],
                    [
                        'tag'     => 'bankitos_panel_info',
                        'name'    => __('Resumen del B@nko', 'bankitos'),
                        'role'    => __('Socios del B@nko', 'bankitos'),
                        'page'    => '/panel',
                        'summary' => __('Tarjeta con los datos clave del B@nko: cuota, tasa de interés, duración, capital total, créditos activos y dinero disponible.', 'bankitos'),
                        'actions' => [
                            __('Muestra el rol del usuario dentro del B@nko.', 'bankitos'),
                            __('Se oculta automáticamente si el socio no pertenece a ningún B@nko.', 'bankitos'),
                        ],
                    ],
                    [
                        'tag'     => 'bankitos_panel_quick_actions',
                        'name'    => __('Acciones rápidas', 'bankitos'),
                        'role'    => __('Socios del B@nko', 'bankitos'),
                        'page'    => '/panel',
                        'summary' => __('Lista de accesos directos según los permisos del socio: aportes, créditos y acciones de roles especiales (tesorero, veedor, comité).', 'bankitos'),
                        'actions' => [
                            __('Enlaza a subir aporte y solicitar crédito para el socio general.', 'bankitos'),
                            __('Agrega enlaces de gestión para tesorero, veedor y presidente según el rol activo.', 'bankitos'),
                        ],
                    ],
                    [
                        'tag'     => 'bankitos_panel_mis_finanzas',
                        'name'    => __('Mis aportes y capacidad de crédito', 'bankitos'),
                        'role'    => __('Socios del B@nko', 'bankitos'),
                        'page'    => '/panel',
                        'summary' => __('Historial completo de aportes con estado (pendiente, aprobado, rechazado), comprobante descargable y capacidad de crédito calculada como 4× los aportes aprobados.', 'bankitos'),
                        'actions' => [
                            __('Lista cada aporte con fecha, monto, estado y enlace al comprobante.', 'bankitos'),
                            __('Calcula y muestra la capacidad de crédito disponible.', 'bankitos'),
                        ],
                    ],
                    [
                        'tag'     => 'bankitos_rentabilidad',
                        'name'    => __('Mi rentabilidad', 'bankitos'),
                        'role'    => __('Socios del B@nko', 'bankitos'),
                        'page'    => '/panel',
                        'summary' => __('Desglose de capital ahorrado, ganancia proporcional por intereses de créditos activos, ganancia por multas distribuidas y capacidad de crédito total (4× el fondo).', 'bankitos'),
                        'actions' => [
                            __('Desglosa capital, intereses y multas en filas separadas.', 'bankitos'),
                            __('Muestra tabla de desglose solo cuando hay ganancias mayores a cero.', 'bankitos'),
                            __('Calcula la capacidad de crédito como 4× el total disponible del socio.', 'bankitos'),
                        ],
                    ],
                ],
            ],
            [
                'title'       => __('Gestión de miembros', 'bankitos'),
                'description' => __('Herramientas exclusivas del Presidente para invitar socios y asignar roles.', 'bankitos'),
                'items'       => [
                    [
                        'tag'     => 'bankitos_panel_members_invite',
                        'name'    => __('Invitar miembros', 'bankitos'),
                        'role'    => __('Presidente', 'bankitos'),
                        'page'    => '/panel-miembros-presidente',
                        'summary' => __('Formulario dinámico para enviar varias invitaciones por email en una sola acción, con control del mínimo requerido para activar el B@nko.', 'bankitos'),
                        'actions' => [
                            __('Permite agregar o eliminar filas de invitados sin recargar la página.', 'bankitos'),
                            __('Verifica que se alcance el mínimo de socios antes de activar el B@nko.', 'bankitos'),
                        ],
                    ],
                    [
                        'tag'     => 'bankitos_panel_members',
                        'name'    => __('Miembros e invitaciones', 'bankitos'),
                        'role'    => __('Presidente', 'bankitos'),
                        'page'    => '/panel-miembros-presidente',
                        'summary' => __('Tabla unificada que muestra miembros activos e invitaciones pendientes. Permite gestionar roles y realizar acciones sobre invitaciones.', 'bankitos'),
                        'actions' => [
                            __('Reenvía, cancela o edita invitaciones existentes.', 'bankitos'),
                            __('Asigna o cambia el rol de cada miembro: socio, secretario, tesorero o veedor.', 'bankitos'),
                        ],
                    ],
                ],
            ],
            [
                'title'       => __('Operación del B@nko', 'bankitos'),
                'description' => __('Shortcodes para crear B@nkos y registrar aportes de capital.', 'bankitos'),
                'items'       => [
                    [
                        'tag'     => 'bankitos_crear_banco_form',
                        'name'    => __('Crear B@nko', 'bankitos'),
                        'role'    => __('Socios sin B@nko', 'bankitos'),
                        'page'    => '/crear-banko',
                        'summary' => __('Formulario guiado paso a paso para definir nombre, objetivo, cuota mensual, tasa de interés, mora y duración del nuevo B@nko.', 'bankitos'),
                        'actions' => [
                            __('Valida montos mínimos y rangos antes de enviar.', 'bankitos'),
                            __('Muestra mensajes de error y éxito en línea sin recargar.', 'bankitos'),
                        ],
                    ],
                    [
                        'tag'     => 'bankitos_aporte_form',
                        'name'    => __('Registrar aporte', 'bankitos'),
                        'role'    => __('Socios del B@nko', 'bankitos'),
                        'page'    => '/aportar',
                        'summary' => __('Formulario para que el socio registre su aporte de capital con comprobante adjunto. Incluye opción para desglosar una multa dentro del monto total.', 'bankitos'),
                        'actions' => [
                            __('Valida tipo y tamaño del archivo del comprobante antes de enviarlo.', 'bankitos'),
                            __('Permite indicar si el monto incluye multa (se distribuye entre socios al aprobar).', 'bankitos'),
                            __('Envía el aporte al tesorero para revisión y aprobación.', 'bankitos'),
                        ],
                    ],
                ],
            ],
            [
                'title'       => __('Créditos', 'bankitos'),
                'description' => __('Flujo completo de solicitud, seguimiento del socio y revisión por el comité.', 'bankitos'),
                'items'       => [
                    [
                        'tag'     => 'bankitos_credit_request',
                        'name'    => __('Solicitar crédito', 'bankitos'),
                        'role'    => __('Socios del B@nko', 'bankitos'),
                        'page'    => '/creditos',
                        'summary' => __('Formulario donde el socio captura monto, plazo en meses, destino del crédito y firma de aceptación de términos.', 'bankitos'),
                        'actions' => [
                            __('Requiere firma electrónica de responsabilidad antes de enviar.', 'bankitos'),
                            __('Envía la solicitud al comité (presidente, tesorero y veedor) para aprobación escalonada.', 'bankitos'),
                        ],
                    ],
                    [
                        'tag'     => 'bankitos_credit_summary',
                        'name'    => __('Estado de mis créditos', 'bankitos'),
                        'role'    => __('Socios del B@nko', 'bankitos'),
                        'page'    => '/creditos',
                        'summary' => __('Historial en acordeón con todas las solicitudes de crédito del socio: montos, plazos, tasas, estado actual y plan de pagos con mora calculada.', 'bankitos'),
                        'actions' => [
                            __('Muestra etiqueta de estado: pendiente, aprobado o rechazado.', 'bankitos'),
                            __('Despliega el detalle y la cronología de firmas de cada solicitud.', 'bankitos'),
                        ],
                    ],
                    [
                        'tag'     => 'bankitos_credit_review',
                        'name'    => __('Revisión de solicitudes (comité)', 'bankitos'),
                        'role'    => __('Presidente, Tesorero, Veedor', 'bankitos'),
                        'page'    => '/creditos',
                        'summary' => __('Panel del comité para aprobar o rechazar cada solicitud según el rol. Cada miembro solo puede firmar en su turno dentro del flujo de 3 niveles.', 'bankitos'),
                        'actions' => [
                            __('Muestra monto, plazo, fecha y estado global de cada solicitud.', 'bankitos'),
                            __('Activa los botones de decisión únicamente para el rol que corresponde en ese momento.', 'bankitos'),
                        ],
                    ],
                ],
            ],
            [
                'title'       => __('Pagos de créditos', 'bankitos'),
                'description' => __('Herramientas para registrar, aprobar y auditar los pagos de créditos desembolsados.', 'bankitos'),
                'items'       => [
                    [
                        'tag'     => 'bankitos_tesorero_creditos',
                        'name'    => __('Aprobar pagos de créditos', 'bankitos'),
                        'role'    => __('Tesorero', 'bankitos'),
                        'page'    => '/tesorero',
                        'summary' => __('Lista los pagos de crédito registrados por los socios con sus comprobantes para que el tesorero los apruebe o rechace.', 'bankitos'),
                        'actions' => [
                            __('Muestra cada pago con su comprobante adjunto.', 'bankitos'),
                            __('Permite aprobar o rechazar con un clic.', 'bankitos'),
                        ],
                    ],
                    [
                        'tag'     => 'bankitos_tesorero_desembolsos',
                        'name'    => __('Registrar desembolsos', 'bankitos'),
                        'role'    => __('Tesorero', 'bankitos'),
                        'page'    => '/tesorero',
                        'summary' => __('Muestra los créditos aprobados que aún no han sido desembolsados para que el tesorero registre la fecha y el comprobante de entrega del dinero.', 'bankitos'),
                        'actions' => [
                            __('Lista créditos aprobados pendientes de desembolso.', 'bankitos'),
                            __('Permite cargar la fecha y el comprobante del desembolso.', 'bankitos'),
                        ],
                    ],
                    [
                        'tag'     => 'bankitos_veedor_creditos',
                        'name'    => __('Auditoría de pagos', 'bankitos'),
                        'role'    => __('Veedor', 'bankitos'),
                        'page'    => '/veedor',
                        'summary' => __('Vista de solo lectura para que el veedor audite los pagos de créditos aprobados o rechazados, con acceso a los comprobantes.', 'bankitos'),
                        'actions' => [
                            __('Muestra todos los pagos registrados con sus comprobantes y estados.', 'bankitos'),
                            __('No permite modificar ni aprobar — solo lectura.', 'bankitos'),
                        ],
                    ],
                ],
            ],
            [
                'title'       => __('Aportes', 'bankitos'),
                'description' => __('Herramientas del tesorero y el veedor para validar y auditar los aportes de los socios.', 'bankitos'),
                'items'       => [
                    [
                        'tag'     => 'bankitos_tesorero_aportes',
                        'name'    => __('Aprobar aportes', 'bankitos'),
                        'role'    => __('Tesorero', 'bankitos'),
                        'page'    => '/tesorero',
                        'summary' => __('Tabla de aportes enviados por los socios para que el tesorero los revise y apruebe o rechace. Incluye paginación, filtros de fecha y comprobantes descargables.', 'bankitos'),
                        'actions' => [
                            __('Filtra aportes por fecha para facilitar la revisión periódica.', 'bankitos'),
                            __('Aprueba o rechaza cada aporte; el estado se actualiza en tiempo real.', 'bankitos'),
                        ],
                    ],
                    [
                        'tag'     => 'bankitos_veedor_aportes',
                        'name'    => __('Auditoría de aportes', 'bankitos'),
                        'role'    => __('Veedor', 'bankitos'),
                        'page'    => '/veedor',
                        'summary' => __('Listado de aportes aprobados en modo solo lectura para que el veedor verifique montos, fechas y comprobantes.', 'bankitos'),
                        'actions' => [
                            __('Muestra comprobantes y fechas de aprobación de cada aporte.', 'bankitos'),
                            __('No permite modificar ni aprobar — solo lectura.', 'bankitos'),
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function get_mobile_menu_items(string $role_key): array {
        $options = self::get_all();
        $menus = isset($options['mobile_menu']) && is_array($options['mobile_menu']) ? $options['mobile_menu'] : [];
        if ($role_key && isset($menus[$role_key]) && is_array($menus[$role_key])) {
            return $menus[$role_key];
        }
        $defaults = self::get_mobile_menu_defaults();
        if ($role_key && isset($defaults[$role_key])) {
            return $defaults[$role_key];
        }
        return $defaults['socio_general'] ?? [];
    }

    protected static function get_mobile_menu_roles(): array {
        return [
            'presidente'    => __('Presidente', 'bankitos'),
            'secretario'    => __('Secretario', 'bankitos'),
            'tesorero'      => __('Tesorero', 'bankitos'),
            'veedor'        => __('Veedor', 'bankitos'),
            'socio_general' => __('Socio general', 'bankitos'),
        ];
    }

    protected static function get_mobile_menu_defaults(): array {
        return [
            'presidente' => [
                ['label' => __('Panel', 'bankitos'), 'icon' => 'dashicons-dashboard', 'url' => '/panel'],
                ['label' => __('Mi B@nko', 'bankitos'), 'icon' => 'dashicons-groups', 'url' => '/mi-banco'],
                ['label' => __('Miembros', 'bankitos'), 'icon' => 'dashicons-admin-users', 'url' => '/panel-miembros-presidente'],
                ['label' => __('Créditos', 'bankitos'), 'icon' => 'dashicons-money-alt', 'url' => '/creditos'],
            ],
            'secretario' => [
                ['label' => __('Panel', 'bankitos'), 'icon' => 'dashicons-dashboard', 'url' => '/panel'],
                ['label' => __('Miembros', 'bankitos'), 'icon' => 'dashicons-id', 'url' => '/panel-miembros-presidente'],
                ['label' => __('Invitaciones', 'bankitos'), 'icon' => 'dashicons-email-alt', 'url' => '/invitaciones'],
            ],
            'tesorero' => [
                ['label' => __('Panel', 'bankitos'), 'icon' => 'dashicons-dashboard', 'url' => '/panel'],
                ['label' => __('Aportes', 'bankitos'), 'icon' => 'dashicons-chart-line', 'url' => '/aportes'],
                ['label' => __('Créditos', 'bankitos'), 'icon' => 'dashicons-money-alt', 'url' => '/creditos'],
                ['label' => __('Desembolsos', 'bankitos'), 'icon' => 'dashicons-bank', 'url' => '/desembolsos'],
            ],
            'veedor' => [
                ['label' => __('Panel', 'bankitos'), 'icon' => 'dashicons-dashboard', 'url' => '/panel'],
                ['label' => __('Aportes', 'bankitos'), 'icon' => 'dashicons-chart-area', 'url' => '/aportes'],
                ['label' => __('Créditos', 'bankitos'), 'icon' => 'dashicons-visibility', 'url' => '/creditos'],
            ],
            'socio_general' => [
                ['label' => __('Panel', 'bankitos'), 'icon' => 'dashicons-dashboard', 'url' => '/panel'],
                ['label' => __('Aportes', 'bankitos'), 'icon' => 'dashicons-money', 'url' => '/aportes'],
                ['label' => __('Créditos', 'bankitos'), 'icon' => 'dashicons-clipboard', 'url' => '/creditos'],
                ['label' => __('Perfil', 'bankitos'), 'icon' => 'dashicons-admin-users', 'url' => '/perfil'],
            ],
        ];
    }

    protected static function parse_mobile_menu_lines(string $raw): array {
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $items = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $label = $parts[0] ?? '';
            $icon  = $parts[1] ?? '';
            $url   = $parts[2] ?? '';
            if ($label === '' || $url === '') {
                continue;
            }
            $items[] = [
                'label' => sanitize_text_field($label),
                'icon'  => sanitize_text_field($icon),
                'url'   => esc_url_raw($url),
            ];
        }
        return $items;
    }

    protected static function format_mobile_menu_lines(array $items): string {
        $lines = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = isset($item['label']) ? (string) $item['label'] : '';
            $icon = isset($item['icon']) ? (string) $item['icon'] : '';
            $url = isset($item['url']) ? (string) $item['url'] : '';
            if ($label === '' && $url === '') {
                continue;
            }
            $lines[] = trim($label . ' | ' . $icon . ' | ' . $url);
        }
        return implode("\n", $lines);
    }
}