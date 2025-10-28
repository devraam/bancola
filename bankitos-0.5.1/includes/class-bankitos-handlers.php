<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Handlers {

    /** @var array<int,int> */
    private static $user_banco_cache = [];

    public static function init() {
        $base = BANKITOS_PATH . 'includes/handlers/';
        foreach (['class-bk-auth.php','class-bk-banco.php','class-bk-aportes.php'] as $f) {
            $file = $base.$f;
            if (file_exists($file)) require_once $file;
        }
        if (class_exists('BK_Auth_Handler')) BK_Auth_Handler::init();
        if (class_exists('BK_Banco_Handler')) BK_Banco_Handler::init();
        if (class_exists('BK_Aportes_Handler')) BK_Aportes_Handler::init();
    }

    public static function get_user_banco_id(int $user_id): int {
        if (isset(self::$user_banco_cache[$user_id])) {
            return self::$user_banco_cache[$user_id];
        }

        $bid = 0;

        if (class_exists('Bankitos_DB') && Bankitos_DB::members_table_exists()) {
            global $wpdb;
            $table = Bankitos_DB::members_table_name();
            $bid = (int) $wpdb->get_var($wpdb->prepare("SELECT banco_id FROM {$table} WHERE user_id=%d LIMIT 1", $user_id));
        }
        if ($bid <= 0) {
            $bid = (int) get_user_meta($user_id, 'bankitos_banco_id', true);
        }

        $bid = $bid > 0 ? $bid : 0;
        self::$user_banco_cache[$user_id] = $bid;
        return $bid;
    }

    public static function registrar_miembro(int $banco_id, int $user_id, string $rol = 'socio_general') {
        update_user_meta($user_id, 'bankitos_banco_id', $banco_id);
        update_user_meta($user_id, 'bankitos_rol', $rol);

        self::$user_banco_cache[$user_id] = $banco_id;

        if (class_exists('Bankitos_DB') && Bankitos_DB::members_table_exists()) {
            global $wpdb;
            $table = Bankitos_DB::members_table_name();
            $ya = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE banco_id=%d AND user_id=%d", $banco_id, $user_id));
            if (!$ya) {
                $wpdb->insert(
                    $table,
                    [
                        'banco_id'   => $banco_id,
                        'user_id'    => $user_id,
                        'member_role'=> $rol,
                        'joined_at'  => current_time('mysql'),
                    ],
                    ['%d','%d','%s','%s']
                );
            } else {
                $wpdb->update(
                    $table,
                    ['member_role' => $rol],
                    ['banco_id' => $banco_id, 'user_id' => $user_id],
                    ['%s'],
                    ['%d','%d']
                );
            }
        }
    }
}
add_action('init', ['Bankitos_Handlers', 'init']);
