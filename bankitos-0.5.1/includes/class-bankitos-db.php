<?php
if (!defined('ABSPATH')) exit;

class Bankitos_DB {

    /** @var bool|null */
    private static $members_table_exists = null;

    /** @var bool|null */
    private static $invites_table_exists = null;
    /**
     * Create or update all plugin tables.
     */
    public static function create_tables(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $members = self::members_table_name();
        $savings = $wpdb->prefix . 'banco_savings';
        $loans   = $wpdb->prefix . 'banco_loans';
        $pays    = $wpdb->prefix . 'banco_loan_payments';
        $invites = $wpdb->prefix . 'banco_invites';
        $credits = $wpdb->prefix . 'banco_credit_requests';
        $payments = $wpdb->prefix . 'banco_credit_payments';

        dbDelta("CREATE TABLE $members (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            banco_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            member_role VARCHAR(40) NOT NULL DEFAULT 'socio_general',
            joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY banco_user (banco_id, user_id)
        ) $charset;");

        self::migrate_members_role_column($members);

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

        dbDelta("CREATE TABLE $pays (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            loan_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            note TEXT NULL,
            paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY loan_paid (loan_id, paid_at)
        ) $charset;");

        dbDelta("CREATE TABLE $invites (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            banco_id BIGINT UNSIGNED NOT NULL,
            email VARCHAR(190) NOT NULL,
            invitee_name VARCHAR(190) NULL,
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

        dbDelta("CREATE TABLE $credits (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            banco_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            request_date DATETIME NOT NULL,
            document_id VARCHAR(190) NOT NULL,
            age INT NOT NULL,
            phone VARCHAR(60) NOT NULL,
            credit_type VARCHAR(40) NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            savings_snapshot DECIMAL(12,2) NOT NULL DEFAULT 0,
            bank_available_snapshot DECIMAL(12,2) NOT NULL DEFAULT 0,
            term_months INT NOT NULL,
            description TEXT NOT NULL,
            signature TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            approved_president VARCHAR(20) NOT NULL DEFAULT 'pending',
            approved_treasurer VARCHAR(20) NOT NULL DEFAULT 'pending',
            approved_veedor VARCHAR(20) NOT NULL DEFAULT 'pending',
            committee_notes TEXT NULL,
            approval_date DATETIME NULL,
            disbursement_date DATETIME NULL,
            disbursement_attachment_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY banco (banco_id),
            KEY banco_user (banco_id, user_id)
        ) $charset;");

        dbDelta("CREATE TABLE $payments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            request_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            attachment_id BIGINT UNSIGNED NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY request_user (request_id, user_id),
            KEY request_status (request_id, status)
        ) $charset;");
        
        self::$members_table_exists = true;
        self::$invites_table_exists = true;
    }

    public static function members_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'banco_members';
    }

    public static function invites_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'banco_invites';
    }

    public static function members_table_exists(): bool {
        if (self::$members_table_exists !== null) {
            return self::$members_table_exists;
        }

        global $wpdb;
        $table = self::members_table_name();
        $exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        self::$members_table_exists = $exists;
        return $exists;
    }

    public static function invites_table_exists(): bool {
        if (self::$invites_table_exists !== null) {
            return self::$invites_table_exists;
        }

        global $wpdb;
        $table = self::invites_table_name();
        $exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        self::$invites_table_exists = $exists;
        return $exists;
    }

    public static function reset_members_table_cache(): void {
        self::$members_table_exists = null;
    }

    public static function reset_invites_table_cache(): void {
        self::$invites_table_exists = null;
    }

    private static function migrate_members_role_column(string $members): void {
        global $wpdb;
        $has_role = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND COLUMN_NAME='role'",
                DB_NAME,
                $members
            )
        );
        if ($has_role) {
            $wpdb->query("ALTER TABLE $members CHANGE COLUMN role member_role VARCHAR(40) NOT NULL DEFAULT 'socio_general'");
        }
    }
}