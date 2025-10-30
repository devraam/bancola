<?php
if (!defined('ABSPATH')) exit;

abstract class Bankitos_Shortcode_Panel_Base extends Bankitos_Shortcode_Base {

    protected static function get_panel_context(): array {
        $context = [
            'user'                => null,
            'name'                => '',
            'banco_id'            => 0,
            'banco_title'         => '',
            'banco_link'          => '',
            'meta'                => [],
            'totals'              => [],
            'role_label'          => '',
            'cuota_text'          => '',
            'tasa_text'           => '',
            'duracion_text'       => '',
            'ahorros_text'        => '',
            'creditos_text'       => '',
            'disponible_text'     => '',
            'members'             => [],
            'invites'             => [],
            'invite_stats'        => ['total' => 0, 'pending' => 0, 'accepted' => 0, 'rejected' => 0],
            'min_invites'         => 1,
            'is_first_invite'     => false,
            'can_manage_invites'  => false,
        ];

        if (!is_user_logged_in()) {
            return $context;
        }

        $user = wp_get_current_user();
        $context['user'] = $user;
        $context['name'] = $user->display_name ?: $user->user_login;
        $context['can_manage_invites'] = current_user_can('manage_bank_invites');

        $banco_id = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($user->ID) : 0;
        $context['banco_id'] = $banco_id;

        if ($banco_id <= 0) {
            return $context;
        }

        $context['banco_title'] = get_the_title($banco_id);
        $context['banco_link']  = get_permalink($banco_id) ?: '#';

        $meta   = self::get_banco_meta($banco_id);
        $totals = self::get_banco_financial_totals($banco_id);

        $context['meta']   = $meta;
        $context['totals'] = $totals;

        $role_label = self::get_user_role_label($user);
        $context['role_label'] = $role_label;

        $cuota_text = $meta['cuota'] > 0 ? self::format_currency($meta['cuota']) : esc_html__('No definido', 'bankitos');
        if ($meta['periodicidad']) {
            $cuota_text .= ' / ' . self::get_period_label($meta['periodicidad']);
        }
        $context['cuota_text'] = $cuota_text;

        $context['tasa_text'] = $meta['tasa'] > 0
            ? sprintf('%s%%', number_format_i18n($meta['tasa'], 2))
            : esc_html__('No definida', 'bankitos');

        $context['duracion_text'] = $meta['duracion'] > 0
            ? sprintf(_n('%s mes', '%s meses', $meta['duracion'], 'bankitos'), number_format_i18n($meta['duracion']))
            : esc_html__('No definida', 'bankitos');

        $context['ahorros_text']    = self::format_currency($totals['ahorros']);
        $context['creditos_text']   = self::format_currency($totals['creditos']);
        $context['disponible_text'] = self::format_currency($totals['disponible']);

        $context['members'] = self::get_banco_members($banco_id);
        $members_count = count($context['members']);

        if (class_exists('BK_Invites_Handler')) {
            $invites_data = BK_Invites_Handler::get_bank_invites($banco_id);
            $context['invites']      = $invites_data['rows'];
            $context['invite_stats'] = $invites_data['stats'];
            $context['is_first_invite'] = ($members_count < 4) && ($invites_data['stats']['total'] < 1);
        } else {
            $context['is_first_invite'] = true;
            $context['min_invites'] = 4;
        }

        $initial_needed = max(0, 4 - $members_count);
        $context['initial_invites_needed'] = $initial_needed;
        if (!isset($context['min_invites']) || $context['min_invites'] < 1) {
            $context['min_invites'] = 1;
        }
        if ($initial_needed > 0) {
            $context['min_invites'] = max($context['min_invites'], $initial_needed);
        } else {
            $context['min_invites'] = 1;
        }

        return $context;
    }

    protected static function get_banco_members(int $banco_id): array {
        $users = get_users([
            'meta_key'   => 'bankitos_banco_id',
            'meta_value' => $banco_id,
            'number'     => apply_filters('bankitos_panel_members_limit', 200),
            'orderby'    => 'display_name',
            'order'      => 'ASC',
        ]);

        $members = [];
        foreach ($users as $user) {
            $members[] = [
                'type'         => 'member',
                'name'         => $user->display_name ?: $user->user_login,
                'email'        => $user->user_email,
                'status'       => 'accepted',
                'status_label' => esc_html__('Aceptada', 'bankitos'),
                'avatar'       => get_avatar_url($user->ID, ['size' => 64]),
            ];
        }

        return $members;
    }
}