<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Credit_Requests {

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'banco_credit_requests';
    }

    public static function get_credit_types(): array {
        return [
            'emprender' => __('Emprender', 'bankitos'),
            'aprender'  => __('Aprender', 'bankitos'),
            'hogar'     => __('Hogar', 'bankitos'),
        ];
    }

    public static function get_term_options(): array {
        return [1, 2, 3, 4, 5, 6];
    }

    public static function get_committee_roles(): array {
        return [
            'presidente' => __('Presidente', 'bankitos'),
            'tesorero'   => __('Tesorero', 'bankitos'),
            'veedor'     => __('Veedor', 'bankitos'),
        ];
    }

    public static function get_user_role_key(?int $user_id = null): string {
        $user_id = $user_id ?: get_current_user_id();
        if ($user_id <= 0) {
            return '';
        }
        $meta_role = get_user_meta($user_id, 'bankitos_rol', true);
        if (is_string($meta_role) && $meta_role !== '') {
            return $meta_role;
        }
        $user = get_user_by('id', $user_id);
        if ($user && is_array($user->roles) && $user->roles) {
            return (string) $user->roles[0];
        }
        return '';
    }

    public static function user_can_review(?int $user_id = null): bool {
        $role = self::get_user_role_key($user_id);
        return $role !== '' && isset(self::get_committee_roles()[$role]);
    }

    public static function get_user_savings_total(int $user_id, int $banco_id): float {
        if ($user_id <= 0 || $banco_id <= 0) {
            return 0.0;
        }
        global $wpdb;
        $sum = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(CAST(m_monto.meta_value AS DECIMAL(18,2)))
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} m_banco ON p.ID = m_banco.post_id AND m_banco.meta_key = %s
             INNER JOIN {$wpdb->postmeta} m_monto ON p.ID = m_monto.post_id AND m_monto.meta_key = %s
             WHERE p.post_type = %s AND p.post_status = 'publish' AND m_banco.meta_value = %d AND p.post_author = %d",
            '_bankitos_banco_id',
            '_bankitos_monto',
            Bankitos_CPT::SLUG_APORTE,
            $banco_id,
            $user_id
        ));
        return $sum ? (float) $sum : 0.0;
    }

    public static function get_max_amount(int $user_id, int $banco_id, ?array $totals = null): float {
        if ($user_id <= 0 || $banco_id <= 0) {
            return 0.0;
        }
        if ($totals === null) {
            $totals = Bankitos_Shortcode_Base::get_banco_financial_totals($banco_id);
        }
        $savings = self::get_user_savings_total($user_id, $banco_id);
        if (empty($totals['disponible'])) {
            return 0.0;
        }
        $limit = min($totals['disponible'], $savings * 4);
        return $limit > 0 ? (float) $limit : 0.0;
    }

    public static function insert_request(array $data): int {
        global $wpdb;
        $table = self::table_name();
        $inserted = $wpdb->insert(
            $table,
            [
                'banco_id'                 => $data['banco_id'],
                'user_id'                  => $data['user_id'],
                'request_date'             => $data['request_date'],
                'document_id'              => $data['document_id'],
                'age'                      => $data['age'],
                'phone'                    => $data['phone'],
                'credit_type'              => $data['credit_type'],
                'amount'                   => $data['amount'],
                'savings_snapshot'         => $data['savings_snapshot'],
                'bank_available_snapshot'  => $data['bank_available_snapshot'],
                'term_months'              => $data['term_months'],
                'description'              => $data['description'],
                'signature'                => $data['signature'],
            ],
            ['%d','%d','%s','%s','%d','%s','%s','%f','%f','%f','%d','%s','%d']
        );
        return $inserted ? (int) $wpdb->insert_id : 0;
    }

    public static function get_requests(int $banco_id): array {
        if ($banco_id <= 0) {
            return [];
        }
        global $wpdb;
        $table = self::table_name();
        $sql = $wpdb->prepare(
            "SELECT r.*, u.display_name, u.user_login
             FROM {$table} r
             LEFT JOIN {$wpdb->users} u ON u.ID = r.user_id
             WHERE r.banco_id = %d
             ORDER BY r.created_at DESC",
            $banco_id
        );
        $rows = $wpdb->get_results($sql, ARRAY_A);
        return $rows ? array_map([__CLASS__, 'prepare_row'], $rows) : [];
    }

    public static function get_user_requests(int $banco_id, int $user_id): array {
        if ($banco_id <= 0 || $user_id <= 0) {
            return [];
        }

        global $wpdb;
        $table = self::table_name();
        $sql = $wpdb->prepare(
            "SELECT r.*, u.display_name, u.user_login
             FROM {$table} r
             LEFT JOIN {$wpdb->users} u ON u.ID = r.user_id
             WHERE r.banco_id = %d AND r.user_id = %d
             ORDER BY r.created_at DESC",
            $banco_id,
            $user_id
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);
        return $rows ? array_map([__CLASS__, 'prepare_row'], $rows) : [];
    }

    public static function get_request(int $request_id): ?array {
        if ($request_id <= 0) {
            return null;
        }
        global $wpdb;
        $table = self::table_name();
        $sql = $wpdb->prepare(
            "SELECT r.*, u.display_name, u.user_login
             FROM {$table} r
             LEFT JOIN {$wpdb->users} u ON u.ID = r.user_id
             WHERE r.id = %d
             LIMIT 1",
            $request_id
        );
        $row = $wpdb->get_row($sql, ARRAY_A);
        return $row ? self::prepare_row($row) : null;
    }

    public static function record_approval(int $request_id, string $role_key, string $decision, string $notes = '') {
        $request = self::get_request($request_id);
        if (!$request) {
            do_action('bankitos_log_event', 'CREDIT_ERROR', 'Solicitud no encontrada', 0, ['request_id' => $request_id]);
            return new WP_Error('bankitos_credit_request_missing', __('La solicitud no existe.', 'bankitos'));
        }

        // CORRECCIÓN: Si el rol viene vacío, intentar recuperarlo forzosamente
        if (empty($role_key)) {
            $role_key = self::get_user_role_key(get_current_user_id());
        }

        $map = [
            'presidente' => 'approved_president',
            'tesorero'   => 'approved_treasurer',
            'veedor'     => 'approved_veedor',
        ];

        if (!isset($map[$role_key])) {
            do_action('bankitos_log_event', 'CREDIT_ERROR', 'Fallo de Rol: ' . $role_key, $request['banco_id'], ['user_id' => get_current_user_id()]);
            return new WP_Error('bankitos_credit_role', __('No puedes actualizar esta solicitud (Rol no reconocido).', 'bankitos'));
        }

        global $wpdb;
        $table = self::table_name();
        
        $update = [$map[$role_key] => $decision];
        if ($notes !== '') { $update['committee_notes'] = $notes; }

        $updated = $wpdb->update($table, $update, ['id' => $request_id]);

        if ($updated === false) {
            do_action('bankitos_log_event', 'CREDIT_ERROR', 'Error en DB al actualizar', $request['banco_id'], $wpdb->last_error);
            return new WP_Error('bankitos_credit_update', __('No fue posible guardar la decisión.', 'bankitos'));
        }

        // Registro de éxito en logs
        do_action('bankitos_log_event', 'CREDIT_APPROVAL', "Firma registrada: $role_key ($decision)", $request['banco_id'], ['request_id' => $request_id]);

        // Recalcular estado final...
        $request = self::get_request($request_id);
        $final_status = self::calculate_status($request);
        if ($final_status !== $request['status']) {
             $wpdb->update($table, ['status' => $final_status], ['id' => $request_id]);
        }

        return true;
    }

    private static function calculate_status(array $row): string {
        $statuses = [
            $row['approved_president'] ?? 'pending',
            $row['approved_treasurer'] ?? 'pending',
            $row['approved_veedor'] ?? 'pending',
        ];
        if (in_array('rejected', $statuses, true)) {
            return 'rejected';
        }
        foreach ($statuses as $status) {
            if ($status !== 'approved') {
                return 'pending';
            }
        }
        return 'approved';
    }

    /**
     * Marca un crédito como desembolsado, guardando fecha y comprobante.
     *
     * @return true|WP_Error
     */
    public static function mark_disbursed(int $request_id, string $date, int $attachment_id) {
        if ($request_id <= 0 || $attachment_id <= 0) {
            return new WP_Error('bankitos_credit_disburse_data', __('La información del desembolso es inválida.', 'bankitos'));
        }

        $request = self::get_request($request_id);
        if (!$request) {
            return new WP_Error('bankitos_credit_request_missing', __('La solicitud no existe.', 'bankitos'));
        }

        // Solo se puede desembolsar si ya está aprobado por el comité.
        if (!in_array($request['status'], ['disbursement_pending', 'approved'], true)) {
            return new WP_Error('bankitos_credit_request_locked', __('El crédito no está listo para desembolso.', 'bankitos'));
        }

        $date_obj = date_create($date);
        if (!$date_obj) {
            return new WP_Error('bankitos_credit_disburse_date', __('La fecha de desembolso no es válida.', 'bankitos'));
        }

        $formatted_date = $date_obj->format('Y-m-d 00:00:00');

        global $wpdb;
        $table = self::table_name();

        $updated = $wpdb->update(
            $table,
            [
                'status'                     => 'disbursed',
                'disbursement_date'          => $formatted_date,
                'disbursement_attachment_id' => $attachment_id,
            ],
            ['id' => $request_id],
            ['%s','%s','%d'],
            ['%d']
        );

        if ($updated === false) {
            return new WP_Error('bankitos_credit_disburse_update', __('No fue posible registrar el desembolso.', 'bankitos'));
        }

        return true;
    }

    private static function prepare_row(array $row): array {
        $row['display_name'] = $row['display_name'] ?: $row['user_login'];
        $row['status'] = self::normalize_status($row);
        return $row;
    }

    private static function normalize_status(array $row): string {
        $status = strtolower(trim((string) ($row['status'] ?? 'pending')));

        // Compatibilidad hacia atrás: un crédito "approved" sin desembolso se considera pendiente de desembolso.
        if ($status === 'approved') {
            if (!empty($row['disbursement_date'])) {
                return 'disbursed';
            }
            return 'disbursement_pending';
        }

        if ($status === 'disbursement_pending' && !empty($row['disbursement_date'])) {
            return 'disbursed';
        }

        $allowed = ['pending', 'rejected', 'disbursement_pending', 'disbursed'];

        if (!in_array($status, $allowed, true)) {
            return 'pending';
        }

        return $status;
    }
}