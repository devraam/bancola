<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Logs {
    const TABLE_NAME = 'banco_transaction_logs';

    public static function init() {
        add_action('bankitos_log_event', [__CLASS__, 'add_log'], 10, 4);
    }

    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            banco_id BIGINT UNSIGNED NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            data_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Campos que contienen PII o datos sensibles que NO deben guardarse en logs.
     */
    private static $sensitive_fields = [
        'document_id', 'phone', 'age',
        'password', 'pass', 'key', 'token', 'secret',
        'invite_token', 'reset_key', 'auth_cookie',
        'card_number', 'cvv', 'account_number',
    ];

    public static function add_log($action_type, $message, $banco_id = 0, $data = []) {
        global $wpdb;

        // Eliminar campos sensibles del payload antes de guardar
        if (is_array($data)) {
            foreach (self::$sensitive_fields as $field) {
                if (array_key_exists($field, $data)) {
                    $data[$field] = '[REDACTED]';
                }
            }
        }

        $wpdb->insert(
            $wpdb->prefix . self::TABLE_NAME,
            [
                'user_id'     => get_current_user_id(),
                'banco_id'    => $banco_id,
                'action_type' => sanitize_text_field((string) $action_type),
                'message'     => sanitize_text_field((string) $message),
                'data_json'   => wp_json_encode($data),
                'created_at'  => current_time('mysql'),
            ]
        );
    }

    public static function get_recent_logs($days = 30) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE created_at >= %s ORDER BY created_at DESC LIMIT 500",
            date('Y-m-d H:i:s', strtotime("-$days days"))
        ));
    }
}