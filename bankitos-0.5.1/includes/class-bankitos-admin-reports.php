<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Admin_Reports {

    const PAGE_SLUG   = 'bankitos-global-dashboard';
    const CAPABILITY  = 'view_global_reports';
    const EXPORT_ACTION = 'bankitos_export_global';
    const TOGGLE_ACTION = 'bankitos_toggle_banco';
    const DELETE_ACTION = 'bankitos_delete_banco';

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('admin_post_' . self::EXPORT_ACTION, [__CLASS__, 'handle_export']);
        add_action('admin_post_' . self::TOGGLE_ACTION, [__CLASS__, 'handle_toggle_banco']);
        add_action('admin_post_' . self::DELETE_ACTION, [__CLASS__, 'handle_delete_banco']);
    }

    public static function register_menu(): void {
        add_menu_page(
            __('Dashboard Global', 'bankitos'),
            __('Dashboard Global', 'bankitos'),
            self::CAPABILITY,
            self::PAGE_SLUG,
            [__CLASS__, 'render_page'],
            'dashicons-chart-pie',
            25
        );
    }

    public static function enqueue_assets(string $hook): void {
        if (strpos($hook, self::PAGE_SLUG) === false) {
            return;
        }
        wp_enqueue_style('bankitos-admin-dashboard', plugins_url('assets/css/bankitos-admin-dashboard.css', dirname(__FILE__)), [], BANKITOS_VERSION);
        wp_enqueue_script('bankitos-admin-dashboard', plugins_url('assets/js/bankitos-admin-dashboard.js', dirname(__FILE__)), ['jquery'], BANKITOS_VERSION, true);
        wp_localize_script('bankitos-admin-dashboard', 'bankitosAdminDashboard', [
            'deleteWarning' => __('Debes escribir ELIMINAR para completar la acción.', 'bankitos'),
        ]);
    }

    public static function render_page(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(__('No tienes permiso para ver este informe.', 'bankitos'));
        }

        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $page   = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;

        $snapshot  = self::build_snapshot();
        $directory = self::get_banks_directory($search, $page);
        $export_url = wp_nonce_url(admin_url('admin-post.php?action=' . self::EXPORT_ACTION), self::EXPORT_ACTION);

        include BANKITOS_PATH . 'includes/views/admin-dashboard.php';
    }

    public static function handle_export(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(__('No tienes permiso para exportar estos datos.', 'bankitos'));
        }
        check_admin_referer(self::EXPORT_ACTION);

        $snapshot  = self::build_snapshot();
        $directory = self::get_banks_directory();

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=bankitos-global-report.csv');

        $output = fopen('php://output', 'w');
        if (!$output) {
            exit;
        }

        fputcsv($output, ['Bankitos – Dashboard Global']);
        fputcsv($output, []);

        fputcsv($output, ['Totales']);
        fputcsv($output, ['Bancos', $snapshot['totals']['bancos']]);
        fputcsv($output, ['Socios totales', $snapshot['totals']['socios_total']]);
        fputcsv($output, ['Ahorros globales', $snapshot['totals']['ahorros']]);
        fputcsv($output, ['Cartera de crédito', $snapshot['totals']['creditos']]);
        fputcsv($output, ['Tasa de utilización de capital', $snapshot['totals']['utilizacion'] . '%']);
        fputcsv($output, []);

        fputcsv($output, ['Directorio de B@nkos']);
        fputcsv($output, ['Nombre', 'Creado', 'Miembros', 'Capital', 'Estado']);
        foreach ($directory['rows'] as $row) {
            fputcsv($output, [
                $row['title'],
                $row['date'],
                $row['members'],
                $row['capital'],
                $row['status_label'],
            ]);
        }

        fclose($output);
        exit;
    }

    public static function handle_toggle_banco(): void {
        $banco_id = isset($_POST['banco_id']) ? absint($_POST['banco_id']) : 0;
        $nonce    = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';

        if (!$banco_id || !wp_verify_nonce($nonce, self::TOGGLE_ACTION . '_' . $banco_id)) {
            wp_die(__('Solicitud no válida.', 'bankitos'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para modificar este B@nko.', 'bankitos'));
        }

        $active = get_post_meta($banco_id, '_bankitos_active', true);
        $new    = $active === '0' ? '1' : '0';
        update_post_meta($banco_id, '_bankitos_active', $new);

        wp_safe_redirect(add_query_arg(['page' => self::PAGE_SLUG, 'toggled' => 1], admin_url('admin.php')));
        exit;
    }

    public static function handle_delete_banco(): void {
        $banco_id = isset($_POST['banco_id']) ? absint($_POST['banco_id']) : 0;
        $nonce    = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        $confirm  = isset($_POST['confirm_phrase']) ? sanitize_text_field(wp_unslash($_POST['confirm_phrase'])) : '';

        if (!$banco_id || !wp_verify_nonce($nonce, self::DELETE_ACTION . '_' . $banco_id)) {
            wp_die(__('Solicitud no válida.', 'bankitos'));
        }
        if (strtoupper($confirm) !== 'ELIMINAR') {
            wp_die(__('Debes confirmar la eliminación escribiendo ELIMINAR.', 'bankitos'));
        }
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para eliminar este B@nko.', 'bankitos'));
        }

        global $wpdb;

        // Limpiar miembros y metadatos
        $member_ids = [];
        if (class_exists('Bankitos_DB') && Bankitos_DB::members_table_exists()) {
            $members_table = Bankitos_DB::members_table_name();
            $member_ids = $wpdb->get_col($wpdb->prepare("SELECT user_id FROM {$members_table} WHERE banco_id = %d", $banco_id));
            $wpdb->delete($members_table, ['banco_id' => $banco_id], ['%d']);
        } else {
            $users = get_users([
                'meta_key'   => 'bankitos_banco_id',
                'meta_value' => $banco_id,
                'fields'     => 'ids',
                'number'     => -1,
            ]);
            $member_ids = $users;
        }

        if ($member_ids) {
            foreach ($member_ids as $uid) {
                delete_user_meta((int) $uid, 'bankitos_banco_id');
                delete_user_meta((int) $uid, 'bankitos_rol');
            }
        }

        // Eliminar invitaciones
        if (class_exists('Bankitos_DB') && Bankitos_DB::invites_table_exists()) {
            $invites_table = Bankitos_DB::invites_table_name();
            $wpdb->delete($invites_table, ['banco_id' => $banco_id], ['%d']);
        }

        // Eliminar créditos y pagos
        if (class_exists('Bankitos_Credit_Requests')) {
            $credits_table  = Bankitos_Credit_Requests::table_name();
            $payments_table = class_exists('Bankitos_Credit_Payments') ? Bankitos_Credit_Payments::table_name() : '';

            $request_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$credits_table} WHERE banco_id = %d", $banco_id));
            if ($request_ids && $payments_table) {
                $placeholders = implode(',', array_fill(0, count($request_ids), '%d'));
                $sql = $wpdb->prepare("DELETE FROM {$payments_table} WHERE request_id IN ({$placeholders})", ...$request_ids);
                $wpdb->query($sql);
            }
            $wpdb->delete($credits_table, ['banco_id' => $banco_id], ['%d']);
        }

        // Tablas legadas
        $legacy_tables = [
            $wpdb->prefix . 'banco_savings'       => 'banco_id',
            $wpdb->prefix . 'banco_loans'         => 'banco_id',
            $wpdb->prefix . 'banco_loan_payments' => 'loan_id',
        ];
        foreach ($legacy_tables as $table => $column) {
            $exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if (!$exists) {
                continue;
            }
            if ($column === 'loan_id') {
                $loan_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}banco_loans WHERE banco_id = %d", $banco_id));
                if ($loan_ids) {
                    $placeholders = implode(',', array_fill(0, count($loan_ids), '%d'));
                    $sql = $wpdb->prepare("DELETE FROM {$table} WHERE {$column} IN ({$placeholders})", ...$loan_ids);
                    $wpdb->query($sql);
                }
                continue;
            }
            $wpdb->delete($table, [$column => $banco_id], ['%d']);
        }

        // Eliminar aportes asociados
        $aportes = get_posts([
            'post_type'      => Bankitos_CPT::SLUG_APORTE,
            'post_status'    => ['publish', 'pending', 'private'],
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_key'       => '_bankitos_banco_id',
            'meta_value'     => $banco_id,
        ]);
        foreach ($aportes as $aporte_id) {
            wp_delete_post($aporte_id, true);
        }

        // Finalmente, eliminar el B@nko
        wp_delete_post($banco_id, true);

        wp_safe_redirect(add_query_arg(['page' => self::PAGE_SLUG, 'deleted' => 1], admin_url('admin.php')));
        exit;
    }

    private static function build_snapshot(): array {
        $totals      = self::get_totals();
        $credits     = self::get_credit_insights();
        $health      = self::get_payment_health();
        $growth      = self::get_member_growth();
        $ghost_banks = self::find_ghost_banks();

        return [
            'totals'          => $totals,
            'credits'         => $credits,
            'health'          => $health,
            'growth'          => $growth,
            'ghost_banks'     => $ghost_banks,
        ];
    }

    private static function get_totals(): array {
        $banks_count = wp_count_posts(Bankitos_CPT::SLUG_BANCO);
        $published   = $banks_count && isset($banks_count->publish) ? (int) $banks_count->publish : 0;

        $members_total  = self::count_members();
        $savings_total  = self::sum_savings();
        $credits_totals = self::sum_credits();

        $utilization = $savings_total > 0 ? round(($credits_totals['amount'] / $savings_total) * 100, 2) : 0;
        $average_equity = $published > 0 ? round($savings_total / $published, 2) : 0;
        $average_ticket = $credits_totals['count'] > 0 ? round($credits_totals['amount'] / $credits_totals['count'], 2) : 0;
        $members_avg    = $published > 0 ? round($members_total / $published, 2) : 0;

        return [
            'bancos'          => $published,
            'socios_total'    => $members_total,
            'socios_promedio' => $members_avg,
            'ahorros'         => $savings_total,
            'creditos'        => $credits_totals['amount'],
            'creditos_count'  => $credits_totals['count'],
            'utilizacion'     => $utilization,
            'patrimonio'      => $average_equity,
            'ticket'          => $average_ticket,
        ];
    }

    private static function get_credit_insights(): array {
        global $wpdb;
        $table = Bankitos_Credit_Requests::table_name();
        $exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if (!$exists) {
            return [
                'types'   => [],
                'status'  => [],
                'totals'  => ['amount' => 0, 'count' => 0],
            ];
        }

        $types = $wpdb->get_results("SELECT credit_type, COUNT(*) AS total, SUM(amount) AS volume FROM {$table} WHERE status = 'approved' GROUP BY credit_type", ARRAY_A);
        $status = $wpdb->get_results("SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status", OBJECT_K);
        $totals = self::sum_credits();

        return [
            'types'  => $types ?: [],
            'status' => $status ?: [],
            'totals' => $totals,
        ];
    }

    private static function get_payment_health(): array {
        global $wpdb;
        $table = Bankitos_Credit_Payments::table_name();
        $exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if (!$exists) {
            return [
                'rejected_rate' => 0,
                'efficiency'    => 0,
                'expected'      => 0,
                'actual'        => 0,
            ];
        }

        $total_payments    = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $rejected_payments = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status = %s", 'rejected'));
        $approved_amount   = (float) $wpdb->get_var($wpdb->prepare("SELECT SUM(amount) FROM {$table} WHERE status = %s", 'approved'));

        $rejected_rate = $total_payments > 0 ? round(($rejected_payments / $total_payments) * 100, 2) : 0;

        $expected = self::calculate_expected_cashflow();
        $efficiency = $expected > 0 ? round(($approved_amount / $expected) * 100, 2) : 0;

        return [
            'rejected_rate' => $rejected_rate,
            'efficiency'    => $efficiency,
            'expected'      => $expected,
            'actual'        => $approved_amount,
        ];
    }

    private static function get_member_growth(): array {
        if (!class_exists('Bankitos_DB') || !Bankitos_DB::members_table_exists()) {
            return [];
        }

        global $wpdb;
        $table = Bankitos_DB::members_table_name();
        $rows = $wpdb->get_results("SELECT DATE_FORMAT(joined_at, '%Y-%m') AS ym, COUNT(*) AS total FROM {$table} GROUP BY ym ORDER BY ym DESC LIMIT 6", ARRAY_A);
        if (!$rows) {
            return [];
        }
        return array_reverse($rows);
    }

    private static function find_ghost_banks(): array {
        $banks = self::get_banks_directory('', 1, -1);
        $ghosts = [];
        $threshold_days = (int) apply_filters('bankitos_ghost_bank_days', 30);
        $cutoff = strtotime(sprintf('-%d days', $threshold_days));

        foreach ($banks['rows'] as $row) {
            $created_ts = strtotime($row['date']);
            $is_old = $created_ts && $created_ts < $cutoff;
            $low_members = $row['members'] < 2;
            $no_capital = $row['capital_raw'] <= 0;
            if ($is_old && ($low_members || $no_capital)) {
                $ghosts[] = $row;
            }
        }

        return $ghosts;
    }

    private static function count_members(): int {
        if (class_exists('Bankitos_DB') && Bankitos_DB::members_table_exists()) {
            global $wpdb;
            $table = Bankitos_DB::members_table_name();
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        }
        $users = get_users([
            'meta_key'   => 'bankitos_banco_id',
            'meta_value' => 0,
            'meta_compare' => '>',
            'meta_type' => 'NUMERIC',
            'fields'     => 'ids',
            'number'     => -1,
        ]);
        return is_array($users) ? count($users) : 0;
    }

    private static function sum_savings(): float {
        global $wpdb;
        $sum = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(CAST(m_monto.meta_value AS DECIMAL(18,2)))
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} m_banco ON p.ID = m_banco.post_id AND m_banco.meta_key = %s
             INNER JOIN {$wpdb->postmeta} m_monto ON p.ID = m_monto.post_id AND m_monto.meta_key = %s
             WHERE p.post_type = %s AND p.post_status = 'publish'",
            '_bankitos_banco_id',
            '_bankitos_monto',
            Bankitos_CPT::SLUG_APORTE
        ));
        return $sum ? (float) $sum : 0.0;
    }

    private static function sum_credits(): array {
        global $wpdb;
        $table  = Bankitos_Credit_Requests::table_name();
        $exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if (!$exists) {
            return ['amount' => 0.0, 'count' => 0];
        }
        $amount = $wpdb->get_var($wpdb->prepare("SELECT SUM(amount) FROM {$table} WHERE status = %s", 'approved'));
        $count  = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status = %s", 'approved'));
        return [
            'amount' => $amount ? (float) $amount : 0.0,
            'count'  => $count ? (int) $count : 0,
        ];
    }

    private static function get_banks_directory(string $search = '', int $page = 1, int $per_page = 20): array {
        $args = [
            'post_type'      => Bankitos_CPT::SLUG_BANCO,
            'post_status'    => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => $per_page,
            'paged'          => $page,
            's'              => $search,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ($per_page === -1) {
            $args['nopaging'] = true;
            unset($args['posts_per_page']);
        }

        $query = new WP_Query($args);
        $bank_ids = wp_list_pluck($query->posts, 'ID');

        $members_by_bank = self::get_members_by_bank($bank_ids);
        $capital_by_bank = self::get_capital_by_bank($bank_ids);

        $rows = [];
        foreach ($query->posts as $post) {
            $active = get_post_meta($post->ID, '_bankitos_active', true);
            $is_active = ($active === '' ? true : $active === '1') && ($post->post_status === 'publish');
            $status_label = $is_active ? __('Activo', 'bankitos') : __('Inactivo', 'bankitos');

            $rows[] = [
                'id'            => (int) $post->ID,
                'title'         => get_the_title($post),
                'date'          => get_the_date('', $post),
                'members'       => $members_by_bank[$post->ID] ?? 0,
                'capital_raw'   => $capital_by_bank[$post->ID] ?? 0.0,
                'capital'       => Bankitos_Shortcode_Base::format_currency((float) ($capital_by_bank[$post->ID] ?? 0.0)),
                'status'        => $is_active,
                'status_label'  => $status_label,
                'edit_link'     => get_edit_post_link($post),
            ];
        }

        return [
            'rows'       => $rows,
            'total'      => (int) $query->found_posts,
            'per_page'   => $per_page,
            'total_pages'=> (int) $query->max_num_pages,
        ];
    }

    private static function get_members_by_bank(array $bank_ids): array {
        if (!$bank_ids) {
            return [];
        }
        $counts = [];
        if (class_exists('Bankitos_DB') && Bankitos_DB::members_table_exists()) {
            global $wpdb;
            $table = Bankitos_DB::members_table_name();
            $placeholders = implode(',', array_fill(0, count($bank_ids), '%d'));
            $sql = $wpdb->prepare("SELECT banco_id, COUNT(*) AS total FROM {$table} WHERE banco_id IN ({$placeholders}) GROUP BY banco_id", ...$bank_ids);
            $rows = $wpdb->get_results($sql);
            foreach ($rows as $row) {
                $counts[(int) $row->banco_id] = (int) $row->total;
            }
        } else {
            foreach ($bank_ids as $bank_id) {
                $users = get_users([
                    'meta_key'   => 'bankitos_banco_id',
                    'meta_value' => $bank_id,
                    'fields'     => 'ids',
                    'number'     => -1,
                ]);
                $counts[$bank_id] = is_array($users) ? count($users) : 0;
            }
        }
        return $counts;
    }

    private static function get_capital_by_bank(array $bank_ids): array {
        if (!$bank_ids) {
            return [];
        }
        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($bank_ids), '%d'));
        $sql = $wpdb->prepare(
            "SELECT m_banco.meta_value AS banco_id, SUM(CAST(m_monto.meta_value AS DECIMAL(18,2))) AS total
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} m_banco ON p.ID = m_banco.post_id AND m_banco.meta_key = %s
             INNER JOIN {$wpdb->postmeta} m_monto ON p.ID = m_monto.post_id AND m_monto.meta_key = %s
             WHERE p.post_type = %s AND p.post_status = 'publish' AND m_banco.meta_value IN ({$placeholders})
             GROUP BY banco_id",
            '_bankitos_banco_id',
            '_bankitos_monto',
            Bankitos_CPT::SLUG_APORTE,
            ...$bank_ids
        );
        $rows = $wpdb->get_results($sql);
        $totals = [];
        foreach ($rows as $row) {
            $totals[(int) $row->banco_id] = (float) $row->total;
        }
        return $totals;
    }

    private static function calculate_expected_cashflow(): float {
        global $wpdb;
        $requests_table = Bankitos_Credit_Requests::table_name();
        $exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $requests_table));
        if (!$exists) {
            return 0.0;
        }

        $requests = $wpdb->get_results("SELECT id, banco_id, amount, term_months, approval_date FROM {$requests_table} WHERE status = 'approved'", ARRAY_A);
        if (!$requests) {
            return 0.0;
        }

        $today = current_time('Y-m-d');
        $cache_tasa = [];
        $expected = 0.0;

        foreach ($requests as $request) {
            $banco_id = (int) $request['banco_id'];
            if (!isset($cache_tasa[$banco_id])) {
                $cache_tasa[$banco_id] = (float) get_post_meta($banco_id, '_bk_tasa', true);
            }
            $tasa = $cache_tasa[$banco_id];
            $plan = self::build_payment_plan((float) $request['amount'], (int) $request['term_months'], (string) $request['approval_date'], $tasa);
            if (!$plan) {
                continue;
            }
            foreach ($plan as $installment) {
                if (($installment['date'] ?? '') <= $today) {
                    $expected += (float) $installment['amount'];
                }
            }
        }

        return round($expected, 2);
    }

    private static function build_payment_plan(float $amount, int $months, string $approval_date, float $tasa): array {
        if ($amount <= 0 || $months <= 0 || empty($approval_date)) {
            return [];
        }

        $rate   = $tasa > 0 ? $tasa / 100 : 0.0;
        $base   = $months > 0 ? $amount / $months : 0.0;
        $plan   = [];
        $cursor = $amount;

        for ($i = 1; $i <= $months; $i++) {
            $date = date('Y-m-d', strtotime("{$approval_date} +{$i} month"));
            $interest = $rate > 0 ? $cursor * $rate : 0.0;
            $installment = $base + $interest;
            $plan[] = [
                'date'      => $date,
                'amount'    => round($installment, 2),
                'balance'   => round($cursor, 2),
                'interest'  => round($interest, 2),
                'principal' => round($base, 2),
            ];
            $cursor = max(0, $cursor - $base);
        }

        return $plan;
    }
}
add_action('init', ['Bankitos_Admin_Reports', 'init']);