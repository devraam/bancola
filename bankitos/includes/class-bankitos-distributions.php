<?php
if (!defined('ABSPATH')) exit;

/**
 * Bankitos_Distributions
 *
 * Handles distribution of credit interest and fines among banco members.
 *
 * - Credit interest: distributed among the snapshot of members active at disbursement time.
 * - Fines (multas):  distributed among ALL members active at the moment the fine is executed.
 */
class Bankitos_Distributions {

    public static function interest_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'banco_interest_distributions';
    }

    public static function fines_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'banco_fine_distributions';
    }

    /**
     * Returns the user IDs of all active members in a banco.
     */
    public static function get_active_member_ids(int $banco_id): array {
        if ($banco_id <= 0) {
            return [];
        }
        $users = get_users([
            'meta_key'   => 'bankitos_banco_id',
            'meta_value' => $banco_id,
            'fields'     => 'ID',
            'number'     => 500,
        ]);
        return array_map('intval', $users ?: []);
    }

    /**
     * Distributes the interest portion of an approved credit payment among
     * the member snapshot captured at disbursement time.
     *
     * @param int   $payment_id     ID of the approved credit payment
     * @param int   $request_id     ID of the credit request
     * @param int   $banco_id       ID of the banco
     * @param float $interest_amount Total interest to distribute
     * @param array $member_ids     Member IDs from the disbursement snapshot
     */
    public static function distribute_credit_interest(
        int $payment_id,
        int $request_id,
        int $banco_id,
        float $interest_amount,
        array $member_ids
    ): bool {
        if ($payment_id <= 0 || $interest_amount <= 0 || empty($member_ids)) {
            return false;
        }

        global $wpdb;
        $table = self::interest_table();
        $count = count($member_ids);
        $share = round($interest_amount / $count, 2);
        // Remainder goes to the first member to avoid losing cents
        $remainder = round($interest_amount - $share * $count, 2);

        $now   = current_time('mysql');
        $first = true;
        foreach ($member_ids as $user_id) {
            $amount = $first ? $share + $remainder : $share;
            $first  = false;
            $wpdb->insert(
                $table,
                [
                    'banco_id'          => $banco_id,
                    'credit_request_id' => $request_id,
                    'payment_id'        => $payment_id,
                    'user_id'           => (int) $user_id,
                    'amount'            => $amount,
                    'created_at'        => $now,
                ],
                ['%d', '%d', '%d', '%d', '%f', '%s']
            );
        }
        return true;
    }

    /**
     * Distributes a fine among ALL currently active members of a banco.
     *
     * @param int    $source_aporte_id  Post ID of the aporte that contains the fine (0 for resignations)
     * @param int    $banco_id          ID of the banco
     * @param float  $fine_amount       Total fine to distribute
     * @param string $source_type       'aporte_fine' | 'resignation'
     */
    public static function distribute_fine(
        int $source_aporte_id,
        int $banco_id,
        float $fine_amount,
        string $source_type = 'aporte_fine'
    ): bool {
        if ($fine_amount <= 0 || $banco_id <= 0) {
            return false;
        }

        $member_ids = self::get_active_member_ids($banco_id);
        if (empty($member_ids)) {
            return false;
        }

        global $wpdb;
        $table     = self::fines_table();
        $count     = count($member_ids);
        $share     = round($fine_amount / $count, 2);
        $remainder = round($fine_amount - $share * $count, 2);

        $now   = current_time('mysql');
        $first = true;
        foreach ($member_ids as $user_id) {
            $amount = $first ? $share + $remainder : $share;
            $first  = false;
            $wpdb->insert(
                $table,
                [
                    'banco_id'         => $banco_id,
                    'source_aporte_id' => $source_aporte_id,
                    'user_id'          => (int) $user_id,
                    'amount'           => $amount,
                    'source_type'      => $source_type,
                    'created_at'       => $now,
                ],
                ['%d', '%d', '%d', '%f', '%s', '%s']
            );
        }
        return true;
    }

    /**
     * Total credit-interest earnings for a user in a banco.
     */
    public static function get_user_interest_total(int $user_id, int $banco_id): float {
        if ($user_id <= 0 || $banco_id <= 0) {
            return 0.0;
        }
        global $wpdb;
        $table = self::interest_table();
        $sum   = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM {$table} WHERE user_id = %d AND banco_id = %d",
            $user_id,
            $banco_id
        ));
        return $sum ? (float) $sum : 0.0;
    }

    /**
     * Total fine-distribution earnings for a user in a banco.
     */
    public static function get_user_fine_total(int $user_id, int $banco_id): float {
        if ($user_id <= 0 || $banco_id <= 0) {
            return 0.0;
        }
        global $wpdb;
        $table = self::fines_table();
        $sum   = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM {$table} WHERE user_id = %d AND banco_id = %d",
            $user_id,
            $banco_id
        ));
        return $sum ? (float) $sum : 0.0;
    }

    /**
     * Returns a full rentabilidad breakdown for a user in a banco.
     *
     * @return array{savings: float, interests: float, fines: float, total: float}
     */
    public static function get_user_rentabilidad(int $user_id, int $banco_id): array {
        $savings   = class_exists('Bankitos_Credit_Requests')
            ? Bankitos_Credit_Requests::get_user_savings_total($user_id, $banco_id)
            : 0.0;
        $interests = self::get_user_interest_total($user_id, $banco_id);
        $fines     = self::get_user_fine_total($user_id, $banco_id);

        return [
            'savings'   => $savings,
            'interests' => $interests,
            'fines'     => $fines,
            'total'     => $savings + $interests + $fines,
        ];
    }
}
