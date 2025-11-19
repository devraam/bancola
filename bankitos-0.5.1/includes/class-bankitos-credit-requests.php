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
            return new WP_Error('bankitos_credit_request_missing', __('La solicitud no existe.', 'bankitos'));
        }
        if (!in_array($request['status'], ['pending'], true)) {
            return new WP_Error('bankitos_credit_request_locked', __('Esta solicitud ya fue resuelta.', 'bankitos'));
        }
        $map = [
            'presidente' => 'approved_president',
            'tesorero'   => 'approved_treasurer',
            'veedor'     => 'approved_veedor',
        ];
        if (!isset($map[$role_key])) {
            return new WP_Error('bankitos_credit_role', __('No puedes actualizar esta solicitud.', 'bankitos'));
        }
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            return new WP_Error('bankitos_credit_decision', __('Debes seleccionar una decisión válida.', 'bankitos'));
        }
        global $wpdb;
        $table = self::table_name();
        $update = [
            $map[$role_key] => $decision,
        ];
        if ($notes !== '') {
            $update['committee_notes'] = $notes;
        }
        $formats = ['%s'];
        if ($notes !== '') {
            $formats[] = '%s';
        }
        $updated = $wpdb->update($table, $update, ['id' => $request_id], $formats, ['%d']);
        if ($updated === false) {
            return new WP_Error('bankitos_credit_update', __('No fue posible guardar la decisión.', 'bankitos'));
        }
        $request[$map[$role_key]] = $decision;
        if ($notes !== '') {
            $request['committee_notes'] = $notes;
        }
        $final_status = self::calculate_status($request);
        if ($final_status !== $request['status']) {
            $fields = ['status' => $final_status];
            $formats = ['%s'];
            if ($final_status === 'approved') {
                $fields['approval_date'] = current_time('mysql');
                $formats[] = '%s';
            } elseif ($final_status === 'rejected') {
                $fields['approval_date'] = null;
                $formats[] = '%s';
            }
            $wpdb->update($table, $fields, ['id' => $request_id], $formats, ['%d']);
            $request['status'] = $final_status;
            $request['approval_date'] = $fields['approval_date'] ?? $request['approval_date'];
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

    private static function prepare_row(array $row): array {
        $row['display_name'] = $row['display_name'] ?: $row['user_login'];
        return $row;
    }
}