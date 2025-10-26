<?php
if (!defined('ABSPATH')) exit;
if (!defined('BANKITOS_VERSION')) define('BANKITOS_VERSION', '0.5.1');

class Bankitos_Plugin {

    public function __construct() {
        if (class_exists('Bankitos_Recaptcha'))  new Bankitos_Recaptcha();
        if (class_exists('Bankitos_Access'))     new Bankitos_Access();
        if (class_exists('Bankitos_CPT'))        new Bankitos_CPT();
        if (class_exists('Bankitos_Shortcodes')) new Bankitos_Shortcodes();
        if (class_exists('Bankitos_Handlers'))   new Bankitos_Handlers();

        register_activation_hook(BANKITOS_PATH . 'bankitos.php', [$this, 'activate']);
        register_deactivation_hook(BANKITOS_PATH . 'bankitos.php', function(){ flush_rewrite_rules(); });

        add_action('wp_enqueue_scripts', function () {
            if (is_admin()) return;
            wp_enqueue_style('bankitos-style', BANKITOS_URL . 'assets/css/bankitos.css', [], BANKITOS_VERSION);
        }, 50);
    }

    public function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        $members = $wpdb->prefix . 'banco_members';
        $savings = $wpdb->prefix . 'banco_savings';
        $loans   = $wpdb->prefix . 'banco_loans';
        $pays    = $wpdb->prefix . 'banco_loan_payments';
        $invites = $wpdb->prefix . 'banco_invites';

        // Miembros
        dbDelta("CREATE TABLE $members (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            banco_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            member_role VARCHAR(40) NOT NULL DEFAULT 'socio_general',
            joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY banco_user (banco_id, user_id)
        ) $charset;");

        // Migración role -> member_role si existiera
        $has_role = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND COLUMN_NAME='role'",
                DB_NAME, $members
            )
        );
        if ($has_role) {
            $wpdb->query("ALTER TABLE $members CHANGE COLUMN role member_role VARCHAR(40) NOT NULL DEFAULT 'socio_general'");
        }

        // Ahorros
        dbDelta("CREATE TABLE $savings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            banco_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            note TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY banco_user (banco_id, user_id),
            KEY banco_created (banco_id, created_at)
        ) $charset;");

        // Préstamos
        dbDelta("CREATE TABLE $loans (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            banco_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            principal DECIMAL(12,2) NOT NULL,
            interest_rate DECIMAL(5,2) NULL,
            term_months INT NULL,
            issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            PRIMARY KEY  (id),
            KEY banco_user (banco_id, user_id),
            KEY banco_status (banco_id, status)
        ) $charset;");

        // Pagos de préstamo
        dbDelta("CREATE TABLE $pays (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            loan_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            note TEXT NULL,
            paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY loan_paid (loan_id, paid_at)
        ) $charset;");

        // Invitaciones
        dbDelta("CREATE TABLE $invites (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            banco_id BIGINT UNSIGNED NOT NULL,
            email VARCHAR(190) NOT NULL,
            member_role VARCHAR(40) NOT NULL DEFAULT 'socio_general',
            token VARCHAR(64) NOT NULL,
            inviter_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            user_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            accepted_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY token (token),
            KEY banco_email (banco_id,email),
            KEY banco_status (banco_id,status)
        ) $charset;");

        if (class_exists('Bankitos_CPT')) {
            Bankitos_CPT::register_cpts();
            Bankitos_CPT::add_roles_and_caps();
            Bankitos_CPT::maybe_create_members_table();
        }
        flush_rewrite_rules();
    }
}
