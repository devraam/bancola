<?php
if (!defined('ABSPATH')) exit;

class BK_Resignation_Handler {

    public static function init(): void {
        add_action('admin_post_bankitos_resignation_request', [__CLASS__, 'submit_resignation']);
        add_action('admin_post_bankitos_resignation_approve', [__CLASS__, 'approve_resignation']);
        add_action('admin_post_bankitos_resignation_reject',  [__CLASS__, 'reject_resignation']);
    }

    private static function redirect_with(string $param, string $code, string $fallback): void {
        $redirect = isset($_REQUEST['redirect_to']) ? wp_unslash($_REQUEST['redirect_to']) : '';
        $target   = $fallback;
        if ($redirect) {
            $validated = wp_validate_redirect(esc_url_raw($redirect), $fallback);
            if ($validated) {
                $target = $validated;
            }
        }
        $referer = wp_get_referer();
        if ($target === $fallback && $referer) {
            $target = $referer;
        }
        wp_safe_redirect(add_query_arg($param, $code, $target));
        exit;
    }

    // -------------------------------------------------------------------------
    // SUBMIT: cualquier miembro solicita retiro
    // -------------------------------------------------------------------------
    public static function submit_resignation(): void {
        if (!is_user_logged_in()) {
            wp_safe_redirect(site_url('/acceder'));
            exit;
        }

        check_admin_referer('bankitos_resignation_request');

        $user_id  = get_current_user_id();
        $banco_id = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($user_id) : 0;
        $redirect = site_url('/panel');

        if ($banco_id <= 0) {
            self::redirect_with('err', 'renuncia_sin_banco', $redirect);
        }

        $user_role = get_user_meta($user_id, 'bankitos_rol', true);

        // El presidente no puede renunciar directamente
        if ($user_role === 'presidente') {
            self::redirect_with('err', 'renuncia_transfiere_primero', $redirect);
        }

        // socio_general: renuncia inmediata si cumple validaciones de crédito
        if ($user_role === 'socio_general' || empty($user_role)) {
            $validation = self::validate_user_credit_for_resignation($user_id, $banco_id);
            if (!$validation['allowed']) {
                self::redirect_with('err', $validation['code'], $redirect);
            }
            self::execute_resignation($user_id, $banco_id);
            wp_safe_redirect(add_query_arg('ok', 'renuncia_ejecutada', site_url('/panel')));
            exit;
        }

        // Roles con cargo (tesorero, veedor, secretario): solicitud pendiente
        if (!class_exists('Bankitos_DB')) {
            self::redirect_with('err', 'renuncia_invalida', $redirect);
        }

        // Crear tabla si aún no existe (primera vez tras la actualización del plugin)
        if (!Bankitos_DB::resignation_table_exists()) {
            Bankitos_DB::create_tables();
        }

        if (!Bankitos_DB::resignation_table_exists()) {
            // Si sigue sin existir, no podemos guardar la solicitud
            self::redirect_with('err', 'renuncia_invalida', $redirect);
        }

        global $wpdb;
        $table    = Bankitos_DB::resignation_table_name();
        $existing = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE banco_id=%d AND user_id=%d AND status='pending'",
            $banco_id,
            $user_id
        ));
        if ($existing > 0) {
            self::redirect_with('err', 'renuncia_ya_solicitada', $redirect);
        }
        $wpdb->insert(
            $table,
            [
                'banco_id'     => $banco_id,
                'user_id'      => $user_id,
                'status'       => 'pending',
                'requested_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s']
        );

        self::notify_president_of_resignation($banco_id, $user_id);
        do_action('bankitos_log_event', 'RESIGNATION_REQUEST', 'Usuario #' . $user_id . ' solicitó retiro del banco #' . $banco_id, $banco_id, ['user_id' => $user_id, 'role' => $user_role]);
        self::redirect_with('ok', 'renuncia_solicitada', $redirect);
    }

    // -------------------------------------------------------------------------
    // APPROVE: el presidente aprueba la solicitud de retiro
    // -------------------------------------------------------------------------
    public static function approve_resignation(): void {
        if (!is_user_logged_in()) {
            wp_safe_redirect(site_url('/acceder'));
            exit;
        }

        $resignation_id = isset($_POST['resignation_id']) ? absint($_POST['resignation_id']) : 0;
        check_admin_referer('bankitos_resignation_approve_' . $resignation_id);

        $president_id = get_current_user_id();
        $banco_id     = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($president_id) : 0;
        $redirect     = site_url('/panel-miembros-presidente');

        if ($banco_id <= 0 || get_user_meta($president_id, 'bankitos_rol', true) !== 'presidente') {
            self::redirect_with('err', 'renuncia_permiso', $redirect);
        }

        if (!class_exists('Bankitos_DB') || !Bankitos_DB::resignation_table_exists() || $resignation_id <= 0) {
            self::redirect_with('err', 'renuncia_invalida', $redirect);
        }

        global $wpdb;
        $table  = Bankitos_DB::resignation_table_name();
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id=%d AND banco_id=%d AND status='pending'",
            $resignation_id,
            $banco_id
        ), ARRAY_A);

        if (!$record) {
            self::redirect_with('err', 'renuncia_invalida', $redirect);
        }

        // El presidente aprueba la solicitud: el miembro queda como socio_general
        // para que se retire por su cuenta desde Acciones rápidas.
        self::set_user_role_to_socio_general((int) $record['user_id'], $banco_id);

        $wpdb->update(
            $table,
            [
                'status'      => 'approved',
                'resolved_at' => current_time('mysql'),
                'resolved_by' => $president_id,
            ],
            ['id' => $resignation_id],
            ['%s', '%s', '%d'],
            ['%d']
        );

        do_action('bankitos_log_event', 'RESIGNATION_APPROVED', 'Solicitud de retiro aprobada para usuario #' . $record['user_id'] . ' por presidente #' . $president_id, $banco_id, ['resignation_id' => $resignation_id]);
        self::redirect_with('ok', 'renuncia_aprobada', $redirect);
    }

    // -------------------------------------------------------------------------
    // REJECT: el presidente rechaza la solicitud de retiro
    // -------------------------------------------------------------------------
    public static function reject_resignation(): void {
        if (!is_user_logged_in()) {
            wp_safe_redirect(site_url('/acceder'));
            exit;
        }

        $resignation_id = isset($_POST['resignation_id']) ? absint($_POST['resignation_id']) : 0;
        check_admin_referer('bankitos_resignation_reject_' . $resignation_id);

        $president_id = get_current_user_id();
        $banco_id     = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($president_id) : 0;
        $redirect     = site_url('/panel-miembros-presidente');

        if ($banco_id <= 0 || get_user_meta($president_id, 'bankitos_rol', true) !== 'presidente') {
            self::redirect_with('err', 'renuncia_permiso', $redirect);
        }

        if (!class_exists('Bankitos_DB') || !Bankitos_DB::resignation_table_exists() || $resignation_id <= 0) {
            self::redirect_with('err', 'renuncia_invalida', $redirect);
        }

        global $wpdb;
        $table = Bankitos_DB::resignation_table_name();

        $wpdb->update(
            $table,
            [
                'status'      => 'rejected',
                'resolved_at' => current_time('mysql'),
                'resolved_by' => $president_id,
            ],
            ['id' => $resignation_id, 'banco_id' => $banco_id, 'status' => 'pending'],
            ['%s', '%s', '%d'],
            ['%d', '%d', '%s']
        );

        do_action('bankitos_log_event', 'RESIGNATION_REJECTED', 'Solicitud de retiro #' . $resignation_id . ' rechazada por presidente #' . $president_id, $banco_id, ['resignation_id' => $resignation_id]);
        self::redirect_with('ok', 'renuncia_rechazada', $redirect);
    }

    // -------------------------------------------------------------------------
    // EXECUTE: desvincula al usuario del banco y aplica penalización
    // -------------------------------------------------------------------------
    public static function execute_resignation(int $user_id, int $banco_id): void {
        global $wpdb;

        // 1. Calcular y distribuir penalización si aplica
        $penalty_pct = (int) get_post_meta($banco_id, '_bk_resignation_penalty', true);
        if ($penalty_pct > 0) {
            $savings_table = $wpdb->prefix . 'banco_savings';
            $total_savings = (float) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(amount),0) FROM {$savings_table} WHERE banco_id=%d AND user_id=%d",
                $banco_id,
                $user_id
            ));

            $retained = round($total_savings * $penalty_pct / 100, 2);

            if ($retained > 0 && class_exists('Bankitos_DB') && Bankitos_DB::members_table_exists()) {
                // Obtener miembros restantes (excluir al que renuncia)
                $members_table = Bankitos_DB::members_table_name();
                $member_ids    = $wpdb->get_col($wpdb->prepare(
                    "SELECT user_id FROM {$members_table} WHERE banco_id=%d AND user_id!=%d",
                    $banco_id,
                    $user_id
                ));

                if (!empty($member_ids)) {
                    $share      = round($retained / count($member_ids), 2);
                    $fine_table = $wpdb->prefix . 'banco_fine_distributions';
                    foreach ($member_ids as $mid) {
                        $wpdb->insert(
                            $fine_table,
                            [
                                'banco_id'        => $banco_id,
                                'source_aporte_id' => 0,
                                'user_id'         => (int) $mid,
                                'amount'          => $share,
                                'source_type'     => 'resignation_penalty',
                                'created_at'      => current_time('mysql'),
                            ],
                            ['%d', '%d', '%d', '%f', '%s', '%s']
                        );
                    }
                }
            }
        }

        // 2. Desvincular al usuario del banco
        if (class_exists('Bankitos_DB') && Bankitos_DB::members_table_exists()) {
            $members_table = Bankitos_DB::members_table_name();
            $wpdb->delete($members_table, ['banco_id' => $banco_id, 'user_id' => $user_id], ['%d', '%d']);
        }

        delete_user_meta($user_id, 'bankitos_banco_id');
        update_user_meta($user_id, 'bankitos_rol', 'socio_general');

        // 3. Normalizar rol WP
        $user = new WP_User($user_id);
        foreach (['presidente', 'tesorero', 'secretario', 'veedor', 'socio_general'] as $r) {
            $user->remove_role($r);
        }
        $user->add_role('socio_general');

        // 4. Limpiar caché estático para que get_user_banco_id() refleje 0 de inmediato
        if (class_exists('Bankitos_Handlers')) {
            Bankitos_Handlers::clear_user_banco_cache($user_id);
        }

        do_action('bankitos_log_event', 'RESIGNATION_EXECUTED', 'Usuario #' . $user_id . ' se retiró del banco #' . $banco_id, $banco_id, ['user_id' => $user_id, 'penalty_pct' => $penalty_pct]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------
    private static function notify_president_of_resignation(int $banco_id, int $user_id): void {
        global $wpdb;

        $member_user = get_userdata($user_id);
        $member_name = $member_user ? $member_user->display_name : '#' . $user_id;

        $members_table = Bankitos_DB::members_table_name();
        $president_id  = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$members_table} WHERE banco_id=%d AND member_role='presidente' LIMIT 1",
            $banco_id
        ));

        if ($president_id <= 0) {
            return;
        }

        $president = get_userdata($president_id);
        if (!$president || !$president->user_email) {
            return;
        }

        $banco_name = get_the_title($banco_id) ?: 'B@nko #' . $banco_id;
        $subject    = sprintf(__('Solicitud de retiro en %s', 'bankitos'), $banco_name);
        $message    = sprintf(
            __("Hola %s,\n\nEl socio %s ha solicitado retirarse del banco %s.\n\nPuedes aprobar o rechazar esta solicitud desde la página de gestión de miembros.", 'bankitos'),
            $president->display_name,
            $member_name,
            $banco_name
        );

        $from_email = get_bloginfo('admin_email');
        if (class_exists('Bankitos_Settings')) {
            $custom = Bankitos_Settings::get('from_email', $from_email);
            if (is_email($custom)) {
                $from_email = $custom;
            }
        }
        $headers = ['From: ' . sprintf('%s <%s>', get_bloginfo('name'), $from_email)];

        wp_mail($president->user_email, $subject, $message, $headers);
    }

    private static function user_has_active_credit(int $user_id, int $banco_id): bool {
        return self::get_user_disbursed_credit_total($user_id, $banco_id) > 0;
    }

    private static function get_user_disbursed_credit_total(int $user_id, int $banco_id): float {
        global $wpdb;
        $credits_table = $wpdb->prefix . 'banco_credit_requests';
        $total = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount),0) FROM {$credits_table} WHERE user_id=%d AND banco_id=%d AND status='disbursed'",
            $user_id,
            $banco_id
        ));
        return $total > 0 ? $total : 0.0;
    }

    private static function validate_user_credit_for_resignation(int $user_id, int $banco_id): array {
        $credit_total = self::get_user_disbursed_credit_total($user_id, $banco_id);
        if ($credit_total <= 0) {
            return ['allowed' => true, 'code' => ''];
        }

        $rentabilidad_total = 0.0;
        if (class_exists('Bankitos_Distributions')) {
            $rentabilidad = Bankitos_Distributions::get_user_rentabilidad($user_id, $banco_id);
            $rentabilidad_total = isset($rentabilidad['total']) ? (float) $rentabilidad['total'] : 0.0;
        }

        if ($credit_total <= $rentabilidad_total) {
            return ['allowed' => true, 'code' => ''];
        }

        return ['allowed' => false, 'code' => 'renuncia_credito_supera_rentabilidad'];
    }

    private static function set_user_role_to_socio_general(int $user_id, int $banco_id): void {
        update_user_meta($user_id, 'bankitos_rol', 'socio_general');

        $user = new WP_User($user_id);
        foreach (['presidente', 'tesorero', 'secretario', 'veedor', 'socio_general'] as $r) {
            $user->remove_role($r);
        }
        $user->add_role('socio_general');

        if (class_exists('Bankitos_DB') && Bankitos_DB::members_table_exists()) {
            global $wpdb;
            $members_table = Bankitos_DB::members_table_name();
            $wpdb->update(
                $members_table,
                ['member_role' => 'socio_general'],
                ['banco_id' => $banco_id, 'user_id' => $user_id],
                ['%s'],
                ['%d', '%d']
            );
        }
    }

    public static function get_pending_resignations(int $banco_id): array {
        if (!class_exists('Bankitos_DB')) {
            return [];
        }
        if (!Bankitos_DB::resignation_table_exists()) {
            Bankitos_DB::create_tables();
            if (!Bankitos_DB::resignation_table_exists()) {
                return [];
            }
        }
        global $wpdb;
        $table = Bankitos_DB::resignation_table_name();
        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE banco_id=%d AND status='pending' ORDER BY requested_at ASC",
            $banco_id
        ), ARRAY_A);
    }
}
