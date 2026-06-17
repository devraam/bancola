<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Admin_Reports {

    const PAGE_SLUG   = 'bankitos-global-dashboard';
    const BANCO_CREDITS_SLUG = 'bankitos-banco-creditos';
    const CAPABILITY  = 'view_global_reports';
    const MANAGE_BANKS_CAPABILITY = 'manage_global_bancos';
    const EXPORT_ACTION = 'bankitos_export_global';
    const TOGGLE_ACTION = 'bankitos_toggle_banco';
    const DELETE_ACTION = 'bankitos_delete_banco';

    private static function can_view_reports(): bool {
        return current_user_can(self::CAPABILITY) || current_user_can('manage_options');
    }

    private static function can_manage_banks(): bool {
        return current_user_can(self::MANAGE_BANKS_CAPABILITY) || current_user_can('manage_options');
    }

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

        add_submenu_page(
            self::PAGE_SLUG,
            __('Logs de Transacciones', 'bankitos'),
            __('Trazas/Logs', 'bankitos'),
            self::CAPABILITY,
            'bankitos-logs',
            [__CLASS__, 'render_logs_page']
        );

        add_submenu_page(
            self::PAGE_SLUG,
            __('Créditos por B@nko', 'bankitos'),
            __('Créditos por B@nko', 'bankitos'),
            self::CAPABILITY,
            self::BANCO_CREDITS_SLUG,
            [__CLASS__, 'render_banco_credits_page']
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
        if (!self::can_view_reports()) {
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
        if (!self::can_view_reports()) {
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

        if (!self::can_manage_banks()) {
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
        if (!self::can_manage_banks()) {
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

        $approved_statuses = ['approved', 'disbursement_pending', 'disbursed'];
        $placeholders = implode(',', array_fill(0, count($approved_statuses), '%s'));

        $types = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT credit_type, COUNT(*) AS total, SUM(amount) AS volume FROM {$table} WHERE status IN ({$placeholders}) GROUP BY credit_type",
                $approved_statuses
            ),
            ARRAY_A
        );
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
        $statuses = ['approved', 'disbursement_pending', 'disbursed'];
        $placeholders = implode(',', array_fill(0, count($statuses), '%s'));

        $amount = $wpdb->get_var($wpdb->prepare("SELECT SUM(amount) FROM {$table} WHERE status IN ({$placeholders})", $statuses));
        $count  = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status IN ({$placeholders})", $statuses));
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

        $approved_statuses = ['approved', 'disbursement_pending', 'disbursed'];
        $placeholders = implode(',', array_fill(0, count($approved_statuses), '%s'));
        $requests = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, banco_id, amount, term_months, approval_date, disbursement_date FROM {$requests_table} WHERE status IN ({$placeholders})",
                $approved_statuses
            ),
            ARRAY_A
        );
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
            $base_date = !empty($request['disbursement_date']) ? (string) $request['disbursement_date'] : (string) $request['approval_date'];
            $plan = self::build_payment_plan((float) $request['amount'], (int) $request['term_months'], $base_date, $tasa);
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

    /**
     * Lista de bancos (id => título) para el selector. Incluye estados no
     * publicados por consistencia con el directorio del Dashboard Global.
     */
    private static function get_banco_options(): array {
        $posts = get_posts([
            'post_type'   => Bankitos_CPT::SLUG_BANCO,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'numberposts' => -1,
            'orderby'     => 'title',
            'order'       => 'ASC',
        ]);
        $options = [];
        foreach ($posts as $p) {
            $title = get_the_title($p);
            $options[(int) $p->ID] = ($title !== '') ? $title : sprintf(__('B@nko #%d', 'bankitos'), $p->ID);
        }
        return $options;
    }

    /**
     * Formulario GET con el selector de banco. Reutilizado en el estado sin
     * banco y en el detalle (con el banco actual preseleccionado).
     */
    public static function render_banco_selector_form(array $options, int $current_id = 0): string {
        ob_start(); ?>
        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin:.5rem 0 1rem;display:flex;gap:.5rem;align-items:flex-end;flex-wrap:wrap;">
            <input type="hidden" name="page" value="<?php echo esc_attr(self::BANCO_CREDITS_SLUG); ?>" />
            <label style="display:flex;flex-direction:column;font-weight:600;">
                <?php esc_html_e('B@nko', 'bankitos'); ?>
                <select name="banco_id" style="min-width:240px;">
                    <option value=""><?php esc_html_e('Selecciona un B@nko', 'bankitos'); ?></option>
                    <?php foreach ($options as $bid => $bname): ?>
                        <option value="<?php echo esc_attr($bid); ?>" <?php selected($current_id, $bid); ?>>
                            <?php echo esc_html($bname . ' (#' . $bid . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="button button-primary"><?php esc_html_e('Ver créditos', 'bankitos'); ?></button>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Página de admin (solo lectura): detalle de créditos de un banco.
     * Permite a los gestores de la plataforma ver el estado de las solicitudes
     * de crédito de cualquier banco sin ser miembros de él.
     */
    public static function render_banco_credits_page(): void {
        if (!self::can_view_reports()) {
            wp_die(__('No tienes permiso para ver estos créditos.', 'bankitos'));
        }

        $banco_id = isset($_GET['banco_id']) ? absint($_GET['banco_id']) : 0;
        $banco    = $banco_id ? get_post($banco_id) : null;
        $valid    = ($banco instanceof WP_Post) && $banco->post_type === Bankitos_CPT::SLUG_BANCO;

        $banco_options = self::get_banco_options();

        if (!$valid) {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('Créditos por B@nko', 'bankitos') . '</h1>';
            echo '<p>' . esc_html__('Elige un B@nko para ver el estado de sus solicitudes de crédito.', 'bankitos') . '</p>';
            echo self::render_banco_selector_form($banco_options, 0);
            echo '</div>';
            return;
        }

        $banco_name     = get_the_title($banco) ?: sprintf(__('B@nko #%d', 'bankitos'), $banco_id);
        $totals         = Bankitos_Shortcode_Base::get_banco_financial_totals($banco_id);
        $requests       = Bankitos_Credit_Requests::get_requests($banco_id);
        $types          = Bankitos_Credit_Requests::get_credit_types();
        $committee      = Bankitos_Credit_Requests::get_committee_roles();
        $payment_labels = Bankitos_Credit_Payments::get_status_labels();
        $status_labels  = self::credit_status_labels();
        $status_classes = self::credit_status_classes();
        $committee_cols = [
            'presidente' => 'approved_president',
            'tesorero'   => 'approved_treasurer',
            'veedor'     => 'approved_veedor',
        ];

        $payments_by_request = self::get_payments_by_requests(wp_list_pluck($requests, 'id'));

        include BANKITOS_PATH . 'includes/views/admin-banco-creditos.php';
    }

    /** Etiquetas de estado de crédito (reutilizadas en la vista admin). */
    public static function credit_status_labels(): array {
        return [
            'pending'              => __('Pendiente', 'bankitos'),
            'approved'             => __('Aprobado', 'bankitos'),
            'disbursement_pending' => __('Pendiente de desembolso', 'bankitos'),
            'disbursed'            => __('Desembolsado', 'bankitos'),
            'rejected'             => __('No aprobado', 'bankitos'),
        ];
    }

    /**
     * Color inline por estado para las pills (la vista no depende del CSS del
     * dashboard porque su hook no pasa el filtro de enqueue_assets()).
     * Devuelve [background, color de texto].
     */
    public static function credit_status_colors(): array {
        return [
            'pending'              => ['#fef9c3', '#854d0e'],
            'approved'             => ['#dcfce7', '#166534'],
            'disbursement_pending' => ['#fef9c3', '#854d0e'],
            'disbursed'            => ['#dcfce7', '#166534'],
            'rejected'             => ['#fee2e2', '#b91c1c'],
        ];
    }

    /** Clases pill (compatibilidad con los shortcodes; no usadas si no hay CSS). */
    public static function credit_status_classes(): array {
        return [
            'pending'              => 'bankitos-pill--pending',
            'approved'             => 'bankitos-pill--accepted',
            'disbursement_pending' => 'bankitos-pill--pending',
            'disbursed'            => 'bankitos-pill--accepted',
            'rejected'             => 'bankitos-pill--rejected',
        ];
    }

    /**
     * Enmascara PII para presentación: muestra solo los últimos $visible
     * caracteres. NO descifra ni altera datos; el descifrado ya ocurrió en
     * Bankitos_Credit_Requests::prepare_row().
     */
    public static function mask_pii(string $value, int $visible = 4): string {
        $value = trim($value);
        if ($value === '') {
            return '—';
        }
        $len = strlen($value);
        if ($len <= $visible) {
            return str_repeat('*', max(0, $len - 1)) . substr($value, -1);
        }
        return str_repeat('*', $len - $visible) . substr($value, -$visible);
    }

    /**
     * Carga en una sola consulta los pagos de todas las solicitudes indicadas y
     * los agrupa por request_id (evita N+1 en la vista).
     */
    private static function get_payments_by_requests(array $request_ids): array {
        $request_ids = array_values(array_unique(array_filter(array_map('intval', $request_ids))));
        if (!$request_ids) {
            return [];
        }
        global $wpdb;
        $table = Bankitos_Credit_Payments::table_name();
        $placeholders = implode(',', array_fill(0, count($request_ids), '%d'));
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE request_id IN ({$placeholders}) ORDER BY created_at DESC", $request_ids),
            ARRAY_A
        );
        $map = [];
        foreach (($rows ?: []) as $row) {
            $map[(int) $row['request_id']][] = $row;
        }
        return $map;
    }

    public static function render_logs_page(): void {
        if (!self::can_view_reports()) {
            wp_die(__('No permitido', 'bankitos'));
        }

        // Filtros (GET): banco, tipo de acción y "solo errores".
        $filter_banco  = isset($_GET['log_banco']) ? absint($_GET['log_banco']) : 0;
        $filter_action = isset($_GET['log_action']) ? sanitize_text_field(wp_unslash($_GET['log_action'])) : '';
        $only_errors   = isset($_GET['log_errors']) && $_GET['log_errors'] === '1';

        $facets = Bankitos_Logs::get_facets(30);

        // Mapa banco_id => nombre legible (resuelto una sola vez).
        $bank_names = [];
        foreach ($facets['bancos'] as $bid) {
            $title = get_the_title($bid);
            $bank_names[$bid] = ($title !== '') ? $title : sprintf(__('Banco #%d', 'bankitos'), $bid);
        }

        $logs = Bankitos_Logs::query_logs([
            'days'        => 30,
            'banco_id'    => $filter_banco,
            'action_type' => $filter_action,
            'errors_only' => $only_errors,
            'limit'       => 1000,
        ]);

        $is_error_action = static function (string $action): bool {
            return stripos($action, 'ERROR') !== false || stripos($action, 'FAIL') !== false;
        };

        $error_count = 0;
        foreach ($logs as $log) {
            if ($is_error_action((string) $log->action_type)) {
                $error_count++;
            }
        }

        $base_url = admin_url('admin.php?page=bankitos-logs');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Trazas de Transacciones (Últimos 30 días)', 'bankitos'); ?></h1>
            <p><?php esc_html_e('Usa esta vista para identificar por qué fallan las aprobaciones, solicitudes de crédito y aportes en cada banco. Las filas en rojo son errores.', 'bankitos'); ?></p>

            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin:1rem 0;display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">
                <input type="hidden" name="page" value="bankitos-logs">
                <label style="display:flex;flex-direction:column;font-weight:600;">
                    <?php esc_html_e('Banco', 'bankitos'); ?>
                    <select name="log_banco" style="min-width:220px;">
                        <option value="0"><?php esc_html_e('Todos los bancos', 'bankitos'); ?></option>
                        <?php foreach ($facets['bancos'] as $bid): ?>
                            <option value="<?php echo esc_attr($bid); ?>" <?php selected($filter_banco, $bid); ?>>
                                <?php echo esc_html($bank_names[$bid] . ' (#' . $bid . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label style="display:flex;flex-direction:column;font-weight:600;">
                    <?php esc_html_e('Tipo de acción', 'bankitos'); ?>
                    <select name="log_action" style="min-width:200px;">
                        <option value=""><?php esc_html_e('Todas las acciones', 'bankitos'); ?></option>
                        <?php foreach ($facets['actions'] as $action): ?>
                            <option value="<?php echo esc_attr($action); ?>" <?php selected($filter_action, $action); ?>>
                                <?php echo esc_html($action); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label style="display:flex;align-items:center;gap:.4rem;font-weight:600;padding-bottom:.4rem;">
                    <input type="checkbox" name="log_errors" value="1" <?php checked($only_errors); ?>>
                    <?php esc_html_e('Solo errores', 'bankitos'); ?>
                </label>
                <button type="submit" class="button button-primary"><?php esc_html_e('Filtrar', 'bankitos'); ?></button>
                <a href="<?php echo esc_url($base_url); ?>" class="button"><?php esc_html_e('Limpiar', 'bankitos'); ?></a>
            </form>

            <p style="color:#475569;">
                <?php
                printf(
                    /* translators: 1: total registros, 2: total errores */
                    esc_html__('Mostrando %1$d registros (%2$d errores).', 'bankitos'),
                    count($logs),
                    $error_count
                );
                ?>
            </p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:150px;"><?php esc_html_e('Fecha', 'bankitos'); ?></th>
                        <th style="width:160px;"><?php esc_html_e('Acción', 'bankitos'); ?></th>
                        <th style="width:200px;"><?php esc_html_e('Banco', 'bankitos'); ?></th>
                        <th><?php esc_html_e('Mensaje', 'bankitos'); ?></th>
                        <th><?php esc_html_e('Datos Técnicos', 'bankitos'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)) : ?>
                    <tr>
                        <td colspan="5" style="text-align:center;padding:2rem;color:#64748b;">
                            <?php esc_html_e('No hay registros que coincidan con el filtro. Las trazas se generan cuando los socios realizan aportes, solicitan créditos o el sistema detecta errores de autorización.', 'bankitos'); ?>
                        </td>
                    </tr>
                    <?php else : ?>
                    <?php foreach ($logs as $log): ?>
                    <?php
                        $action   = (string) $log->action_type;
                        $is_error = $is_error_action($action);
                        $bid      = (int) $log->banco_id;
                    ?>
                    <tr<?php echo $is_error ? ' style="background:#fef2f2;"' : ''; ?>>
                        <td><?php echo esc_html((string) $log->created_at); ?></td>
                        <td>
                            <strong style="<?php echo $is_error ? 'color:#b91c1c;' : ''; ?>"><?php echo esc_html($action); ?></strong>
                        </td>
                        <td>
                            <?php if ($bid > 0): ?>
                                <?php $name = $bank_names[$bid] ?? sprintf(__('Banco #%d', 'bankitos'), $bid); ?>
                                <strong><?php echo esc_html($name); ?></strong>
                                <span style="color:#94a3b8;">#<?php echo esc_html((string) $bid); ?></span>
                            <?php else: ?>
                                <span style="color:#94a3b8;"><?php esc_html_e('Sistema / Global', 'bankitos'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($log->message); ?></td>
                        <td><code><?php echo esc_html($log->data_json); ?></code></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
add_action('init', ['Bankitos_Admin_Reports', 'init']);