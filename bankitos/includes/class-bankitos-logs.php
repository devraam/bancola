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

    /**
     * Consulta de trazas con filtros opcionales por banco, tipo de acción y
     * solo-errores. Pensada para el diagnóstico por banco desde el admin.
     *
     * @param array $args days, banco_id, action_type, errors_only, limit
     */
    public static function query_logs(array $args = []): array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        $days     = isset($args['days']) ? max(1, (int) $args['days']) : 30;
        $banco_id = isset($args['banco_id']) ? (int) $args['banco_id'] : 0;
        $action   = isset($args['action_type']) ? (string) $args['action_type'] : '';
        $errors   = !empty($args['errors_only']);
        $limit    = isset($args['limit']) ? max(1, min(2000, (int) $args['limit'])) : 500;

        $where  = ['created_at >= %s'];
        $params = [date('Y-m-d H:i:s', strtotime("-{$days} days"))];

        if ($banco_id > 0) {
            $where[]  = 'banco_id = %d';
            $params[] = $banco_id;
        }
        if ($action !== '') {
            $where[]  = 'action_type = %s';
            $params[] = $action;
        }
        if ($errors) {
            $where[]  = '(action_type LIKE %s OR action_type LIKE %s)';
            $params[] = '%ERROR%';
            $params[] = '%FAIL%';
        }

        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where)
             . " ORDER BY created_at DESC LIMIT {$limit}";

        $results = $wpdb->get_results($wpdb->prepare($sql, $params));
        return $results ?: [];
    }

    /**
     * Devuelve los valores distintos de banco_id y action_type presentes en la
     * ventana de tiempo, para poblar los desplegables de filtro del admin.
     */
    public static function get_facets(int $days = 30): array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $bancos = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT banco_id FROM {$table} WHERE created_at >= %s AND banco_id > 0 ORDER BY banco_id ASC",
            $since
        ));
        $actions = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT action_type FROM {$table} WHERE created_at >= %s ORDER BY action_type ASC",
            $since
        ));

        return [
            'bancos'  => array_map('intval', (array) $bancos),
            'actions' => array_map('strval', (array) $actions),
        ];
    }
}