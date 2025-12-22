<?php
if (!defined('ABSPATH')) exit;

abstract class Bankitos_Shortcode_Base {

    public static function register_shortcode(string $tag): void {
        add_shortcode($tag, [static::class, 'maybe_render']);
    }

    /**
     * Wrapper executed by WordPress before delegating to the concrete render method.
     *
     * @param array|string $atts
     * @param string|null  $content
     */
    public static function maybe_render($atts = [], $content = null, string $shortcode = ''): string {
        if (!static::should_render_for_current_user($atts, $content)) {
            return static::render_for_guest($atts, $content);
        }

        return static::render($atts, $content);
    }

    /**
     * @param array|string $atts
     * @param string|null $content
     */
    abstract public static function render($atts = [], $content = null): string;

    /**
     * Determine whether the shortcode should be visible for the current visitor.
     *
     * @param array|string $atts
     * @param string|null  $content
     */
    protected static function should_render_for_current_user($atts = [], $content = null): bool {
        return is_user_logged_in();
    }

    /**
     * Output rendered when the shortcode should not be visible for the current visitor.
     *
     * @param array|string $atts
     * @param string|null  $content
     */
    protected static function render_for_guest($atts = [], $content = null): string {
        return '';
    }

    protected static function top_notice_from_query(): string {
        $html = '';
        $has_notice = false;
        $should_cleanup = false;
        if (!empty($_GET['ok'])) {
            $should_cleanup = true;
            $ok = sanitize_key($_GET['ok']);
            $map_ok = [
                'creado'            => __('¡Listo! Tu B@nko se creó correctamente.', 'bankitos'),
                'aporte_enviado'    => __('Aporte enviado para validación.', 'bankitos'),
                'aporte_aprobado'   => __('Aporte aprobado.', 'bankitos'),
                'aporte_rechazado'  => __('Aporte rechazado.', 'bankitos'),
                'invite_sent'       => __('Invitaciones enviadas correctamente.', 'bankitos'),
                'invite_resent'     => __('Invitación reenviada correctamente.', 'bankitos'),
                'invite_updated'    => __('Invitación actualizada correctamente.', 'bankitos'),
                'invite_cancelled'  => __('Invitación cancelada.', 'bankitos'),
                'invite_accepted'   => __('¡Bienvenido! Invitación aceptada.', 'bankitos'),
                'invite_rejected'   => __('La invitación fue rechazada.', 'bankitos'),
                'invite_updated_sent' => __('Invitación actualizada y reenviada correctamente.', 'bankitos'),
                'role_updated'      => __('El rol del miembro ha sido actualizado.', 'bankitos'),
                'credito_solicitado'=> __('Solicitud de crédito enviada al comité.', 'bankitos'),
                'credito_actualizado'=> __('Decisión registrada correctamente.', 'bankitos'),
                'pago_enviado'      => __('Pago enviado al tesorero para revisión.', 'bankitos'),
                'pago_aprobado'     => __('Pago aprobado correctamente.', 'bankitos'),
                'pago_rechazado'    => __('Pago marcado como rechazado.', 'bankitos'),
            ];
            if (!empty($map_ok[$ok])) {
                $html .= '<div class="bankitos-success">' . esc_html($map_ok[$ok]) . '</div>';
                $has_notice = true;
            }
        }
        if (empty($_GET['err'])) {
            if (!$has_notice && !$should_cleanup) {
                return $html;
            }
            
            return $html . ($should_cleanup ? self::notice_cleanup_script() : '');
        }
        $should_cleanup = true;
        $err = sanitize_key($_GET['err']);
        $map = [
            'recaptcha'       => __('No pudimos verificar que no eres un robot.', 'bankitos'),
            'recaptcha_config'=> __('Esta acción requiere que reCAPTCHA esté configurado por un administrador.', 'bankitos'),
            'validacion'      => __('Revisa los campos obligatorios.', 'bankitos'),
            'crear_post'      => __('Ocurrió un problema creando el B@nko.', 'bankitos'),
            'ya_miembro'      => __('Ya perteneces a un B@nko.', 'bankitos'),
            'tasa_rango'      => __('La tasa debe estar entre 0.1 y 3.0.', 'bankitos'),
            'duracion_invalida'=> __('Duración inválida.', 'bankitos'),
            'periodicidad_req'=> __('Selecciona una periodicidad válida.', 'bankitos'),
            'cuota_min'       => __('La cuota mínima es 1.000.', 'bankitos'),
            'nombre_req'      => __('El nombre del B@nko es obligatorio.', 'bankitos'),
            'objetivo_req'    => __('El objetivo del B@nko es obligatorio.', 'bankitos'),
            'permiso'         => __('No tienes permisos para realizar esta acción.', 'bankitos'),
            'no_banco'        => __('No perteneces a ningún B@nko.', 'bankitos'),
            'monto'           => __('Debes ingresar un monto válido.', 'bankitos'),
            'crear_aporte'    => __('No pudimos crear el aporte.', 'bankitos'),
            'credenciales'    => __('Las credenciales no son válidas. Intenta nuevamente.', 'bankitos'),
            'archivo_tipo'    => __('El comprobante debe ser una imagen válida.', 'bankitos'),
            'archivo_tamano'  => __('El comprobante excede el tamaño permitido.', 'bankitos'),
            'archivo_subida'  => __('Hubo un problema subiendo el comprobante.', 'bankitos'),
            'archivo_seguro'  => __('No pudimos proteger el comprobante subido.', 'bankitos'),
            'invite_send'     => __('No pudimos enviar las invitaciones. Intenta nuevamente.', 'bankitos'),
            'invite_min'      => __('Debes invitar al menos la cantidad mínima requerida.', 'bankitos'),
            'invite_accept'   => __('No fue posible aceptar la invitación con este usuario.', 'bankitos'),
            'invite_token'    => __('La invitación no es válida o ha expirado.', 'bankitos'),
            'invite_resend'   => __('No pudimos reenviar la invitación.', 'bankitos'),
            'invite_update'   => __('No pudimos actualizar la invitación.', 'bankitos'),
            'invite_cancel'   => __('No pudimos cancelar la invitación.', 'bankitos'),
            'invite_domain'   => __('Solo puedes invitar correos con dominios permitidos.', 'bankitos'),
            'domain_not_allowed' => __('El dominio de correo no está permitido para registrarse.', 'bankitos'),
            'credito_datos'   => __('Completa todos los campos obligatorios de la solicitud.', 'bankitos'),
            'credito_firma'   => __('Debes firmar la solicitud antes de enviarla.', 'bankitos'),
            'credito_sin_fondos'=> __('No hay fondos suficientes para nuevos créditos.', 'bankitos'),
            'credito_monto'   => __('Debes ingresar un monto válido.', 'bankitos'),
            'credito_limite'  => __('El monto solicitado supera el límite permitido.', 'bankitos'),
            'credito_guardar' => __('No fue posible guardar la solicitud de crédito.', 'bankitos'),
            'credito_permiso' => __('No tienes permisos para realizar esta acción.', 'bankitos'),
            'credito_decision'=> __('No pudimos actualizar el estado del crédito.', 'bankitos'),
            'pago_invalido'   => __('La información del pago es inválida.', 'bankitos'),
            'pago_permiso'    => __('No tienes permisos para registrar este pago.', 'bankitos'),
            'pago_archivo_subida' => __('Hubo un problema subiendo el comprobante.', 'bankitos'),
            'pago_archivo_tamano' => __('El comprobante excede el tamaño permitido.', 'bankitos'),
            'pago_archivo_tipo'   => __('El comprobante debe ser una imagen válida.', 'bankitos'),
            'pago_archivo_requerido' => __('Debes adjuntar un comprobante de pago.', 'bankitos'),
            'pago_archivo_seguro' => __('No pudimos proteger el comprobante subido.', 'bankitos'),
            'pago_guardar'    => __('No fue posible guardar el pago.', 'bankitos'),
        ];
        $msg = $map[$err] ?? __('Ha ocurrido un error. Intenta nuevamente.', 'bankitos');
        $html .= '<div class="bankitos-error">' . esc_html($msg) . '</div>';

        return $html . self::notice_cleanup_script();
    }

    protected static function notice_cleanup_script(): string {
        return '<script>(function(){if(!window.history||!window.history.replaceState){return;}try{var url=new URL(window.location.href);var params=url.searchParams;var removed=false;["ok","err"].forEach(function(key){if(params.has(key)){params.delete(key);removed=true;}});if(!removed){return;}var newQuery=params.toString();var newUrl=url.pathname+(newQuery?"?"+newQuery:"")+url.hash;window.history.replaceState({},document.title,newUrl);}catch(e){}})();</script>';
    }

    protected static function enqueue_create_banco_assets(): void {
        if (!wp_script_is('bankitos-create-banco', 'registered')) {
            wp_register_script('bankitos-create-banco', BANKITOS_URL . 'assets/js/create-banco.js', [], defined('BANKITOS_VERSION') ? BANKITOS_VERSION : '1.0.0', true);
        }

        wp_enqueue_script('bankitos-create-banco');
        $messages = [
            'required'        => __('Este campo es obligatorio.', 'bankitos'),
            'number'          => __('Introduce un número válido.', 'bankitos'),
            'cuotaMin'        => __('La cuota mínima es 1.000.', 'bankitos'),
            'periodRequired'  => __('Selecciona una periodicidad válida.', 'bankitos'),
            'tasaRange'       => __('La tasa debe estar entre 0.1 y 3.0.', 'bankitos'),
            'tasaStep'        => __('Usa incrementos de 0.1 (ej. 2.3).', 'bankitos'),
            'focusMessage'    => __('Revisa los campos marcados en rojo.', 'bankitos'),
        ];

        $config = [
            'form'     => '#bankitos-create-form',
            'submit'   => '#bankitos-create-form button[type="submit"]',
            'fields'   => [
                'nombre'   => '#bk_nombre',
                'objetivo' => '#bk_obj',
                'cuota'    => '#bk_cuota',
                'per'      => '#bk_per',
                'tasa'     => '#bk_tasa',
                'dur'      => '#bk_dur',
            ],
            'wrappers' => [
                'nombre'   => '#wrap_nombre',
                'objetivo' => '#wrap_obj',
                'cuota'    => '#wrap_cuota',
                'per'      => '#wrap_per',
                'tasa'     => '#wrap_tasa',
                'dur'      => '#wrap_dur',
            ],
            'errors'   => [
                'nombre'   => '#err_nombre',
                'objetivo' => '#err_obj',
                'cuota'    => '#err_cuota',
                'per'      => '#err_per',
                'tasa'     => '#err_tasa',
                'dur'      => '#err_dur',
            ],
            'limits'   => [
                'cuotaMin'  => 1000,
                'tasaMin'   => 0.1,
                'tasaMax'   => 3.0,
                'tasaStep'  => 0.1,
            ],
        ];

        wp_localize_script('bankitos-create-banco', 'bankitosCreateBanco', [
            'messages' => $messages,
            'config'   => $config,
        ]);
    }

    protected static function get_current_url(): string {
        global $wp;
        $request = is_object($wp) && isset($wp->request) ? $wp->request : '';
        $base = home_url($request ? '/' . ltrim($request, '/') : '/');
        if (empty($_GET)) {
            return $base;
        }
        $params = [];
        foreach ($_GET as $key => $value) {
            if ($key === 'redirect_to') {
                continue;
            }
            if (is_scalar($value)) {
                $params[$key] = sanitize_text_field($value);
            }
        }
        return $params ? add_query_arg($params, $base) : $base;
    }

    protected static function filtered_hidden_inputs(array $exclude_keys): string {
        $html = '';
        foreach ($_GET as $key => $value) {
            if (in_array($key, $exclude_keys, true) || $key === 'redirect_to') {
                continue;
            }
            if (is_array($value)) {
                continue;
            }
            $html .= sprintf('<input type="hidden" name="%s" value="%s" />', esc_attr($key), esc_attr(sanitize_text_field($value)));
        }
        return $html;
    }

    protected static function get_aporte_filters(string $context): array {
        $prefix = $context === 'tesorero' ? 'bk_tes_' : 'bk_vee_';
        $from_key = $prefix . 'from';
        $to_key   = $prefix . 'to';
        $page_key = $prefix . 'page';

        $from = isset($_GET[$from_key]) ? sanitize_text_field($_GET[$from_key]) : '';
        $to   = isset($_GET[$to_key]) ? sanitize_text_field($_GET[$to_key]) : '';

        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : '';
        $to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : '';

        $range = [];
        if ($from) {
            $range['after'] = $from;
        }
        if ($to) {
            $range['before'] = $to;
        }
        if ($range) {
            $range['inclusive'] = true;
        }

        $page = isset($_GET[$page_key]) ? max(1, absint($_GET[$page_key])) : 1;

        return [
            'from'       => $from,
            'to'         => $to,
            'page'       => $page,
            'from_key'   => $from_key,
            'to_key'     => $to_key,
            'page_key'   => $page_key,
            'date_query' => $range ? [$range] : [],
            'query_args' => array_filter([
                $from_key => $from,
                $to_key   => $to,
            ]),
        ];
    }

    protected static function render_aporte_filter_form(string $context, array $filters): string {
        $title = $context === 'tesorero'
            ? __('Filtrar aportes pendientes', 'bankitos')
            : __('Filtrar aportes aprobados', 'bankitos');
        $exclude = [$filters['from_key'], $filters['to_key'], $filters['page_key']];
        $html  = '<form method="get" class="bankitos-filter-form">';
        $html .= '<fieldset><legend>' . esc_html($title) . '</legend>';
        $html .= self::filtered_hidden_inputs($exclude);
        $html .= sprintf('<label>%s <input type="date" name="%s" value="%s"></label>', esc_html__('Desde', 'bankitos'), esc_attr($filters['from_key']), esc_attr($filters['from'] ?? ''));
        $html .= sprintf('<label>%s <input type="date" name="%s" value="%s"></label>', esc_html__('Hasta', 'bankitos'), esc_attr($filters['to_key']), esc_attr($filters['to'] ?? ''));
        $html .= sprintf('<input type="hidden" name="%s" value="1" />', esc_attr($filters['page_key']));
        $html .= '<button type="submit" class="bankitos-btn">' . esc_html__('Aplicar filtros', 'bankitos') . '</button>';
        $html .= '</fieldset></form>';
        return $html;
    }

    protected static function render_aporte_pagination(WP_Query $query, string $page_key, array $extra_args = [], int $current = 1): string {
        if ($query->max_num_pages <= 1) {
            return '';
        }
        $sanitized_args = [];
        foreach ($extra_args as $key => $value) {
            if (is_scalar($value)) {
                $sanitized_args[$key] = sanitize_text_field($value);
            }
        }
        $links = paginate_links([
            'total'    => $query->max_num_pages,
            'current'  => max(1, $current),
            'format'   => false,
            'add_args' => $sanitized_args,
            'type'     => 'array',
            'base'     => add_query_arg($page_key, '%#%'),
        ]);
        if (!$links) {
            return '';
        }
        $html = '<nav class="bankitos-pagination"><ul class="page-numbers">';
        foreach ($links as $link) {
            $html .= '<li>' . $link . '</li>';
        }
        $html .= '</ul></nav>';
        return $html;
    }

    protected static function get_user_role_label(WP_User $user): string {
        $map = [
            'presidente'    => __('Presidente', 'bankitos'),
            'secretario'    => __('Secretario', 'bankitos'),
            'veedor'        => __('Veedor', 'bankitos'),
            'socio_general' => __('Socio general', 'bankitos'),
            'tesorero'      => __('Tesorero', 'bankitos'),
        ];

        $role_meta = get_user_meta($user->ID, 'bankitos_rol', true);
        $role_meta = is_string($role_meta) ? $role_meta : '';

        if ($role_meta && isset($map[$role_meta])) {
            return $map[$role_meta];
        }

        foreach ((array) $user->roles as $role) {
            if (isset($map[$role])) {
                return $map[$role];
            }
        }

        if ($role_meta) {
            return ucwords(str_replace('_', ' ', $role_meta));
        }

        $fallback = is_array($user->roles) && $user->roles ? $user->roles[0] : '';
        return $fallback ? ucwords(str_replace('_', ' ', $fallback)) : __('Socio', 'bankitos');
    }

    protected static function get_banco_meta(int $banco_id): array {
        return [
            'cuota'        => (float) get_post_meta($banco_id, '_bk_cuota_monto', true),
            'periodicidad' => (string) get_post_meta($banco_id, '_bk_periodicidad', true),
            'tasa'         => (float) get_post_meta($banco_id, '_bk_tasa', true),
            'duracion'     => (int) get_post_meta($banco_id, '_bk_duracion_meses', true),
        ];
    }

    protected static function get_period_label(string $period): string {
        $map = [
            'semanal'   => __('Semanal', 'bankitos'),
            'quincenal' => __('Quincenal', 'bankitos'),
            'mensual'   => __('Mensual', 'bankitos'),
        ];

        return $map[$period] ?? ucwords(str_replace('_', ' ', $period));
    }

    public static function format_currency(float $amount): string {
        $symbol   = apply_filters('bankitos_panel_currency_symbol', '$');
        $decimals = (int) apply_filters('bankitos_panel_currency_decimals', 0);
        $formatted = number_format_i18n($amount, $decimals);
        return sprintf('%s%s', $symbol, $formatted);
    }

    public static function get_banco_financial_totals(int $banco_id): array {
        $totals = [
            'ahorros'    => 0.0,
            'creditos'   => 0.0,
            'creditos_count' => 0,
            'disponible' => 0.0,
        ];

        if ($banco_id <= 0) {
            return $totals;
        }

        global $wpdb;

        $sum_aportes = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(CAST(m_monto.meta_value AS DECIMAL(18,2)))
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} m_banco ON p.ID = m_banco.post_id AND m_banco.meta_key = %s
             INNER JOIN {$wpdb->postmeta} m_monto ON p.ID = m_monto.post_id AND m_monto.meta_key = %s
             WHERE p.post_type = %s AND p.post_status = 'publish' AND m_banco.meta_value = %d",
            '_bankitos_banco_id',
            '_bankitos_monto',
            Bankitos_CPT::SLUG_APORTE,
            $banco_id
        ));

        if ($sum_aportes) {
            $totals['ahorros'] = (float) $sum_aportes;
        }

        $credits_table = $wpdb->prefix . 'banco_credit_requests';
        $table_exists  = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $credits_table));

        if ($table_exists) {
           $approved_sum = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(amount) FROM {$credits_table} WHERE banco_id = %d AND status = %s",
                $banco_id,
                'approved'
            ));
            $approved_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$credits_table} WHERE banco_id = %d AND status = %s",
                $banco_id,
                'approved'
            ));

            if ($approved_sum) {
                $totals['creditos'] = (float) $approved_sum;
            }

            if ($approved_count) {
                $totals['creditos_count'] = (int) $approved_count;
            }
        }

        // Fallback to legacy loans table if no approved credits were found.
        if ($totals['creditos'] <= 0 && $totals['creditos_count'] === 0) {
            $loans_table    = $wpdb->prefix . 'banco_loans';
            $payments_table = $wpdb->prefix . 'banco_loan_payments';
            $loan_statuses  = (array) apply_filters('bankitos_panel_active_loan_statuses', ['active', 'pending', 'late']);

            $table_exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $loans_table));
            if ($table_exists) {
                $status_placeholders = $loan_statuses ? implode(',', array_fill(0, count($loan_statuses), '%s')) : '';
                if ($status_placeholders) {
                    $sql  = "SELECT id, principal FROM {$loans_table} WHERE banco_id = %d AND status IN ({$status_placeholders})";
                    $args = array_merge([$sql, $banco_id], $loan_statuses);
                    $query = call_user_func_array([$wpdb, 'prepare'], $args);
                } else {
                    $query = $wpdb->prepare("SELECT id, principal FROM {$loans_table} WHERE banco_id = %d", $banco_id);
                }

                $loans = $wpdb->get_results($query);
                if ($loans) {
                    $principal_total = 0.0;
                    $loan_ids = [];
                    foreach ($loans as $loan) {
                        $principal_total += (float) $loan->principal;
                        $loan_ids[] = (int) $loan->id;
                    }
                $totals['creditos_count'] = count($loans);

                    $outstanding = $principal_total;

                    if ($loan_ids && (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $payments_table))) {
                        $placeholders = implode(',', array_fill(0, count($loan_ids), '%d'));
                        $sql  = "SELECT SUM(amount) FROM {$payments_table} WHERE loan_id IN ({$placeholders})";
                        $args = array_merge([$sql], $loan_ids);
                        $payments_query = call_user_func_array([$wpdb, 'prepare'], $args);
                        $paid = $wpdb->get_var($payments_query);
                        if ($paid) {
                            $outstanding = max(0.0, $outstanding - (float) $paid);
                        }
                    }

                    $totals['creditos'] = max(0.0, $outstanding);
                }
            }
        }

        $totals['disponible'] = max(0.0, $totals['ahorros'] - $totals['creditos']);

        return (array) apply_filters('bankitos_panel_financial_totals', $totals, $banco_id);
    }
}