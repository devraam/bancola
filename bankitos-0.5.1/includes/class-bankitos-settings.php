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

        add_settings_section('bankitos_section_mobile_menu', __('Menú móvil por roles', 'bankitos'), function () {
            echo '<p>' . esc_html__('Configura los botones del menú móvil (solo visible en celulares y para usuarios autenticados). Usa el formato: Etiqueta | dashicons-algún-icono | /ruta', 'bankitos') . '</p>';
        }, self::PAGE_SLUG);

        foreach (self::get_mobile_menu_roles() as $role_key => $role_label) {
            add_settings_field(
                'mobile_menu_' . $role_key,
                sprintf(__('Menú para %s', 'bankitos'), $role_label),
                [__CLASS__, 'field_mobile_menu'],
                self::PAGE_SLUG,
                'bankitos_section_mobile_menu',
                ['role' => $role_key]
            );
        }
    }

    public static function sanitize_options($input) : array {
        $out = is_array($input) ? $input : [];
        $out['recaptcha_site']     = isset($input['recaptcha_site']) ? sanitize_text_field($input['recaptcha_site']) : '';
        $out['recaptcha_secret']   = isset($input['recaptcha_secret']) ? sanitize_text_field($input['recaptcha_secret']) : '';
        $out['invite_expiry_days'] = isset($input['invite_expiry_days']) ? max(1, intval($input['invite_expiry_days'])) : 7;
        $default_name  = get_bloginfo('name');
        $default_email = get_bloginfo('admin_email');

        $from_name = isset($input['from_name']) ? sanitize_text_field($input['from_name']) : '';
        $from_email = isset($input['from_email']) ? sanitize_email($input['from_email']) : '';

        $out['from_name']  = $from_name !== '' ? $from_name : $default_name;
        $out['from_email'] = is_email($from_email) ? $from_email : $default_email;
        $out['mailjet_api_key']       = isset($input['mailjet_api_key']) ? sanitize_text_field($input['mailjet_api_key']) : '';
        $out['mailjet_secret_key']    = isset($input['mailjet_secret_key']) ? sanitize_text_field($input['mailjet_secret_key']) : '';
        $out['email_template_invite'] = isset($input['email_template_invite']) ? wp_kses_post($input['email_template_invite']) : '';
        
        $mobile_menu = [];
        $menu_input = isset($input['mobile_menu']) && is_array($input['mobile_menu']) ? $input['mobile_menu'] : [];
        foreach (self::get_mobile_menu_roles() as $role_key => $role_label) {
            if (array_key_exists($role_key, $menu_input)) {
                $raw = is_string($menu_input[$role_key]) ? $menu_input[$role_key] : '';
                $mobile_menu[$role_key] = self::parse_mobile_menu_lines($raw);
            } else {
                $defaults = self::get_mobile_menu_defaults();
                $mobile_menu[$role_key] = $defaults[$role_key] ?? [];
            }
        }
        $out['mobile_menu'] = $mobile_menu;

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
        echo '<p class="description">' . esc_html__('Una línea por botón. Ejemplo: Panel | dashicons-dashboard | /panel', 'bankitos') . '</p>';
    }
    public static function enqueue_admin_assets($hook) : void {
        if ($hook === 'settings_page_' . self::PAGE_SLUG) {
            wp_enqueue_style('bankitos-admin', plugins_url('assets/css/bankitos.css', dirname(__FILE__)), [], '1.0');
        }
    }

    protected static function render_shortcodes_help() : void {
        $sections = self::get_shortcodes_reference();

        echo '<div class="bankitos-shortcodes-doc">';
        echo '<div class="bankitos-shortcodes-doc__intro">';
        echo '<h2>' . esc_html__('Documentación de shortcodes', 'bankitos') . '</h2>';
        echo '<p>' . esc_html__('Usa estos bloques para construir las páginas principales de Bankitos. Cada tarjeta indica el rol que lo utiliza, las acciones disponibles y el shortcode listo para copiar.', 'bankitos') . '</p>';
        echo '<ul class="bankitos-shortcodes-doc__tips">';
        echo '<li>' . esc_html__('Todos los shortcodes usan un diseño móvil primero; publícalos en plantillas de ancho completo para evitar barras laterales.', 'bankitos') . '</li>';
        echo '<li>' . esc_html__('Si un rol no tiene permisos, el contenido se oculta o muestra un mensaje contextual, por lo que puedes colocar varios shortcodes en la misma página.', 'bankitos') . '</li>';
        echo '</ul>';
        echo '</div>';

        foreach ($sections as $section) {
            echo '<section class="bankitos-shortcodes-doc__section">';
            echo '<div class="bankitos-shortcodes-doc__section-head">';
            echo '<h3>' . esc_html($section['title']) . '</h3>';
            if (!empty($section['description'])) {
                echo '<p>' . esc_html($section['description']) . '</p>';
            }
            echo '</div>';
            echo '<div class="bankitos-shortcodes-doc__grid">';
            foreach ($section['items'] as $item) {
                echo '<article class="bankitos-shortcodes-doc__card">';
                echo '<div class="bankitos-shortcodes-doc__tag"><code>[' . esc_html($item['tag']) . ']</code></div>';
                echo '<h4 class="bankitos-shortcodes-doc__name">' . esc_html($item['name']) . '</h4>';
                echo '<p class="bankitos-shortcodes-doc__role">' . esc_html($item['role']) . '</p>';
                echo '<p class="bankitos-shortcodes-doc__summary">' . esc_html($item['summary']) . '</p>';
                if (!empty($item['actions'])) {
                    echo '<ul class="bankitos-shortcodes-doc__list">';
                    foreach ($item['actions'] as $action) {
                        echo '<li>' . esc_html($action) . '</li>';
                    }
                    echo '</ul>';
                }
                if (!empty($item['usage'])) {
                    echo '<p class="bankitos-shortcodes-doc__usage">' . esc_html($item['usage']) . '</p>';
                }
                echo '</article>';
            }
            echo '</div>';
            echo '</section>';
        }
        echo '</div>';
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
                        'role'    => __('Invitados y socios con invitación', 'bankitos'),
                        'summary' => __('Formulario de inicio de sesión compatible con reCAPTCHA y con tokens de invitación.', 'bankitos'),
                        'actions' => [
                            __('Valida reCAPTCHA si el administrador lo habilitó.', 'bankitos'),
                            __('Permite recordar sesión y pasar el parámetro invite_token.', 'bankitos'),
                            __('Muestra un enlace directo a la página de registro.', 'bankitos'),
                        ],
                        'usage'   => __('Úsalo en la página pública de acceso (ej. /acceder).', 'bankitos'),
                    ],
                    [
                        'tag'     => 'bankitos_register',
                        'name'    => __('Registro de socios', 'bankitos'),
                        'role'    => __('Invitados', 'bankitos'),
                        'summary' => __('Alta de nuevos usuarios, incluyendo registros que llegan con invite_token.', 'bankitos'),
                        'actions' => [
                            __('Solicita datos básicos para crear la cuenta.', 'bankitos'),
                            __('Cuando llega con invite_token vincula al B@nko correcto.', 'bankitos'),
                        ],
                        'usage'   => __('Colócalo en la página pública de registro (ej. /registrarse).', 'bankitos'),
                    ],
                    [
                        'tag'     => 'bankitos_invite_portal',
                        'name'    => __('Portal de invitaciones', 'bankitos'),
                        'role'    => __('Invitados con invitación', 'bankitos'),
                        'summary' => __('Vista donde cada persona acepta o rechaza invitaciones pendientes a un B@nko.', 'bankitos'),
                        'actions' => [
                            __('Muestra detalles del B@nko que invita (rol, cuota, duración).', 'bankitos'),
                            __('Guarda la decisión y limpia el token de la URL para evitar reutilización.', 'bankitos'),
                        ],
                        'usage'   => __('Añádelo a la URL que usas en los correos de invitación.', 'bankitos'),
                    ],
                ],
            ],
            [
                'title'       => __('Navegación móvil', 'bankitos'),
                'description' => __('Menú flotante pensado para celulares.', 'bankitos'),
                'items'       => [
                    [
                        'tag'     => 'bankitos_mobile_menu',
                        'name'    => __('Menú móvil por roles', 'bankitos'),
                        'role'    => __('Socios autenticados', 'bankitos'),
                        'summary' => __('Muestra botones verticales según el rol y resalta la pantalla activa.', 'bankitos'),
                        'actions' => [
                            __('Solo se ve en mobile y para usuarios con sesión.', 'bankitos'),
                            __('La administración define íconos y rutas por rol.', 'bankitos'),
                        ],
                        'usage'   => __('Ubícalo en la plantilla principal del panel.', 'bankitos'),
                    ],
                ],
            ],
            [
                'title'       => __('Panel general del socio', 'bankitos'),
                'description' => __('Bloques base que conforman la experiencia principal de cualquier miembro.', 'bankitos'),
                'items'       => [
                    [
                        'tag'     => 'bankitos_panel',
                        'name'    => __('Bienvenida al panel', 'bankitos'),
                        'role'    => __('Socios autenticados', 'bankitos'),
                        'summary' => __('Saludo inicial que muestra el estado del socio y ofrece crear un B@nko si aplica.', 'bankitos'),
                        'actions' => [
                            __('Si no pertenece a un B@nko ofrece el botón para crearlo.', 'bankitos'),
                            __('Si ya pertenece, confirma el nombre del B@nko activo.', 'bankitos'),
                        ],
                        'usage'   => __('Ubícalo en la página principal del panel después de iniciar sesión.', 'bankitos'),
                    ],
                    [
                        'tag'     => 'bankitos_panel_info',
                        'name'    => __('Resumen del B@nko', 'bankitos'),
                        'role'    => __('Socios del B@nko', 'bankitos'),
                        'summary' => __('Tarjeta de información general del B@nko: cuota, tasa, duración y totales.', 'bankitos'),
                        'actions' => [
                            __('Incluye rol del usuario, ahorro total, créditos activos y dinero disponible.', 'bankitos'),
                            __('Se oculta si el socio no pertenece a ningún B@nko.', 'bankitos'),
                        ],
                        'usage'   => __('Úsalo junto al panel para dar contexto financiero rápido.', 'bankitos'),
                    ],
                    [
                        'tag'     => 'bankitos_panel_quick_actions',
                        'name'    => __('Acciones rápidas', 'bankitos'),
                        'role'    => __('Socios del B@nko', 'bankitos'),
                        'summary' => __('Listado de accesos directos según permisos (aportes, créditos y revisiones).', 'bankitos'),
                        'actions' => [
                            __('Enlaza a subir aporte y solicitar crédito.', 'bankitos'),
                            __('Muestra enlaces extra si el usuario es tesorero, veedor o parte del comité de crédito.', 'bankitos'),
                        ],
                        'usage'   => __('Ideal debajo del resumen para guiar al socio al siguiente paso.', 'bankitos'),
                    ],
                    [
                        'tag'     => 'bankitos_panel_mis_finanzas',
                        'name'    => __('Mis aportes y capacidad de crédito', 'bankitos'),
                        'role'    => __('Socios del B@nko', 'bankitos'),
                        'summary' => __('Historial de aportes con estados (pendiente, aprobado, rechazado) y capacidad de crédito calculada.', 'bankitos'),
                        'actions' => [
                            __('Muestra cada aporte con su comprobante y fecha.', 'bankitos'),
                            __('Calcula la capacidad de crédito como 4x los aportes aprobados.', 'bankitos'),
                        ],
                        'usage'   => __('Coloca este bloque en la vista personal del socio (ej. /mi-aporte).', 'bankitos'),
                    ],
                ],
            ],
            [
                'title'       => __('Gestión de miembros', 'bankitos'),
                'description' => __('Herramientas exclusivas para el rol Presidente.', 'bankitos'),
                'items'       => [
                    [
                        'tag'     => 'bankitos_panel_members_invite',
                        'name'    => __('Invitar miembros', 'bankitos'),
                        'role'    => __('Presidente', 'bankitos'),
                        'summary' => __('Formulario dinámico para enviar varias invitaciones en una sola acción.', 'bankitos'),
                        'actions' => [
                            __('Controla el mínimo de invitaciones requeridas para activar el B@nko.', 'bankitos'),
                            __('Agrega o elimina filas de invitados sin recargar la página.', 'bankitos'),
                        ],
                        'usage'   => __('Úsalo en la sección de gestión de miembros del panel.', 'bankitos'),
                    ],
                    [
                        'tag'     => 'bankitos_panel_members',
                        'name'    => __('Miembros e invitaciones', 'bankitos'),
                        'role'    => __('Presidente', 'bankitos'),
                        'summary' => __('Tabla unificada de miembros aceptados e invitaciones pendientes.', 'bankitos'),
                        'actions' => [
                            __('Permite reenviar, cancelar o editar invitaciones en línea.', 'bankitos'),
                            __('Asigna o cambia roles a miembros activos (socio, secretario, tesorero, veedor).', 'bankitos'),
                        ],
                        'usage'   => __('Colócalo después de la sección de invitación para continuar con el seguimiento.', 'bankitos'),
                    ],
                ],
            ],
            [
                'title'       => __('Operación del B@nko', 'bankitos'),
                'description' => __('Shortcodes para crear B@nkos, registrar aportes y habilitar flujos financieros.', 'bankitos'),
                'items'       => [
                    [
                        'tag'     => 'bankitos_crear_banco_form',
                        'name'    => __('Crear B@nko', 'bankitos'),
                        'role'    => __('Socios generales sin B@nko', 'bankitos'),
                        'summary' => __('Formulario guiado para definir nombre, objetivo, cuota, tasa y duración del B@nko.', 'bankitos'),
                        'actions' => [
                            __('Valida montos mínimos y rangos permitidos antes de enviar.', 'bankitos'),
                            __('Carga mensajes de error y éxito sin recargar la página.', 'bankitos'),
                        ],
                        'usage'   => __('Ubícalo en la página donde los socios crean su B@nko (ej. /crear-banko).', 'bankitos'),
                    ],
                    [
                        'tag'     => 'bankitos_aporte_form',
                        'name'    => __('Subir aporte', 'bankitos'),
                        'role'    => __('Socios del B@nko', 'bankitos'),
                        'summary' => __('Registro de aportes de capital con carga de comprobante.', 'bankitos'),
                        'actions' => [
                            __('Valida tipo y tamaño del archivo antes de enviarlo.', 'bankitos'),
                            __('Envía el aporte al tesorero para su aprobación.', 'bankitos'),
                        ],
                        'usage'   => __('Coloca este formulario en la página donde los socios reportan aportes.', 'bankitos'),
                    ],
                ],
            ],
            [
                'title'       => __('Créditos', 'bankitos'),
                'description' => __('Flujo completo de solicitud, seguimiento y revisión de créditos.', 'bankitos'),
                'items'       => [
                    [
                        'tag'     => 'bankitos_credit_request',
                        'name'    => __('Solicitud de crédito', 'bankitos'),
                        'role'    => __('Socios del B@nko', 'bankitos'),
                        'summary' => __('Formulario para que el socio capture monto, plazo y destino del crédito.', 'bankitos'),
                        'actions' => [
                            __('Incluye aceptación de términos y firma de responsabilidad.', 'bankitos'),
                            __('Envía la solicitud al comité para firma del presidente, tesorero y veedor.', 'bankitos'),
                        ],
                        'usage'   => __('Ponlo en la página destinada a solicitar crédito (ej. /solicitud-credito).', 'bankitos'),
                    ],
                    [
                        'tag'     => 'bankitos_credit_summary',
                        'name'    => __('Estado de mis créditos', 'bankitos'),
                        'role'    => __('Socios del B@nko', 'bankitos'),
                        'summary' => __('Resumen en acordeón de todas las solicitudes del socio con montos, plazos y tasas.', 'bankitos'),
                        'actions' => [
                            __('Muestra etiquetas de estado (pendiente, aprobado, rechazado).', 'bankitos'),
                            __('Desglosa los detalles y la cronología de cada solicitud.', 'bankitos'),
                        ],
                        'usage'   => __('Agrégalo junto al formulario de solicitud para que el socio vea su historial.', 'bankitos'),
                    ],
                    [
                        'tag'     => 'bankitos_credit_request_list',
                        'name'    => __('Revisión de solicitudes', 'bankitos'),
                        'role'    => __('Presidente, tesorero y veedor', 'bankitos'),
                        'summary' => __('Listado de solicitudes que cada miembro del comité puede firmar o comentar.', 'bankitos'),
                        'actions' => [
                            __('Resalta el estado general y la fecha de solicitud.', 'bankitos'),
                            __('Habilita botones de aprobación o rechazo según el rol actual.', 'bankitos'),
                        ],
                        'usage'   => __('Úsalo en la página interna de comité (ej. /solicitudes-credito).', 'bankitos'),
                    ],
                ],
            ],
            [
                'title'       => __('Pagos de créditos', 'bankitos'),
                'description' => __('Seguimiento de pagos de créditos aprobados.', 'bankitos'),
                'items'       => [
                    [
                        'tag'     => 'bankitos_tesorero_creditos',
                        'name'    => __('Pagos para aprobar', 'bankitos'),
                        'role'    => __('Tesorero', 'bankitos'),
                        'summary' => __('Muestra cada pago registrado y permite aprobarlo o marcarlo como rechazado.', 'bankitos'),
                        'actions' => [
                            __('Lista pagos de créditos aprobados con su comprobante.', 'bankitos'),
                            __('Permite aprobar o rechazar según corresponda.', 'bankitos'),
                        ],
                        'usage'   => __('Inclúyelo en la página de tesorería de créditos.', 'bankitos'),
                    ],
                    [
                        'tag'     => 'bankitos_veedor_creditos',
                        'name'    => __('Pagos auditados', 'bankitos'),
                        'role'    => __('Veedor', 'bankitos'),
                        'summary' => __('Vista de sólo lectura para auditar pagos aprobados o rechazados.', 'bankitos'),
                        'actions' => [
                            __('Muestra los pagos ya cargados con sus soportes.', 'bankitos'),
                            __('Bloquea acciones si el usuario no tiene el rol de veedor.', 'bankitos'),
                        ],
                        'usage'   => __('Ubícalo en la sección de auditoría de créditos.', 'bankitos'),
                    ],
                ],
            ],
            [
                'title'       => __('Aportes', 'bankitos'),
                'description' => __('Herramientas del tesorero y veedor para validar aportes.', 'bankitos'),
                'items'       => [
                    [
                        'tag'     => 'bankitos_tesorero_aportes',
                        'name'    => __('Aportes pendientes', 'bankitos'),
                        'role'    => __('Tesorero', 'bankitos'),
                        'summary' => __('Tabla de aportes enviados por los socios para que el tesorero los apruebe o rechace.', 'bankitos'),
                        'actions' => [
                            __('Incluye paginación, filtros de fecha y enlaces a comprobantes.', 'bankitos'),
                            __('Actualiza el estado del aporte y notifica al socio.', 'bankitos'),
                        ],
                        'usage'   => __('Colócalo en la página interna del tesorero.', 'bankitos'),
                    ],
                    [
                        'tag'     => 'bankitos_veedor_aportes',
                        'name'    => __('Aportes aprobados', 'bankitos'),
                        'role'    => __('Veedor', 'bankitos'),
                        'summary' => __('Listado de aportes aprobados para fines de auditoría.', 'bankitos'),
                        'actions' => [
                            __('Muestra comprobantes y fechas de aprobación.', 'bankitos'),
                            __('Restringe el acceso si el usuario no tiene el rol de veedor.', 'bankitos'),
                        ],
                        'usage'   => __('Utilízalo en la página de auditoría de aportes.', 'bankitos'),
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
                ['label' => __('Miembros', 'bankitos'), 'icon' => 'dashicons-admin-users', 'url' => '/miembros'],
                ['label' => __('Créditos', 'bankitos'), 'icon' => 'dashicons-money-alt', 'url' => '/creditos'],
            ],
            'secretario' => [
                ['label' => __('Panel', 'bankitos'), 'icon' => 'dashicons-dashboard', 'url' => '/panel'],
                ['label' => __('Miembros', 'bankitos'), 'icon' => 'dashicons-id', 'url' => '/miembros'],
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