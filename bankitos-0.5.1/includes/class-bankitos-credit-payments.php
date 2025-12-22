<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Credit_Payments {

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'banco_credit_payments';
    }

    public static function get_status_labels(): array {
        return [
            'pending'  => __('Pendiente', 'bankitos'),
            'approved' => __('Aprobado', 'bankitos'),
            'rejected' => __('Rechazado', 'bankitos'),
        ];
    }

    /**
     * Returns a priority value for a payment status.
     * Higher numbers indicate more definitive outcomes.
     */
    public static function get_status_priority(string $status): int {
        $map = [
            'approved' => 3,
            'pending'  => 2,
            'rejected' => 1,
        ];

        $normalized = strtolower(trim($status));
        return $map[$normalized] ?? 0;
    }
    
    public static function insert_payment(array $data): int {
        global $wpdb;
        $table = self::table_name();
        $inserted = $wpdb->insert(
            $table,
            [
                'request_id'    => $data['request_id'],
                'user_id'       => $data['user_id'],
                'amount'        => $data['amount'],
                'attachment_id' => $data['attachment_id'],
                'status'        => $data['status'],
                'created_at'    => current_time('mysql'),
            ],
            ['%d','%d','%f','%d','%s','%s']
        );
        return $inserted ? (int) $wpdb->insert_id : 0;
    }

    public static function get_payment(int $payment_id): ?array {
        if ($payment_id <= 0) {
            return null;
        }
        global $wpdb;
        $table = self::table_name();
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $payment_id),
            ARRAY_A
        );
        return $row ?: null;
    }

    public static function get_request_payments(int $request_id): array {
        if ($request_id <= 0) {
            return [];
        }
        global $wpdb;
        $table = self::table_name();
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE request_id = %d ORDER BY created_at DESC", $request_id),
            ARRAY_A
        );
        return $rows ?: [];
    }

    public static function update_status(int $payment_id, string $status): bool {
        $allowed = ['pending', 'approved', 'rejected'];
        if ($payment_id <= 0 || !in_array($status, $allowed, true)) {
            return false;
        }
        global $wpdb;
        $table = self::table_name();
        $updated = $wpdb->update($table, ['status' => $status], ['id' => $payment_id], ['%s'], ['%d']);
        return $updated !== false;
    }
}