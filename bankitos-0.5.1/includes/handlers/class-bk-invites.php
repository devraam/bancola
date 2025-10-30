<?php
if (!defined('ABSPATH')) exit;

class BK_Invites_Handler {

    const STATUS_PENDING  = 'pending';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';
    const STATUS_EXPIRED  = 'expired';

    public static function init(): void {
        add_action('admin_post_bankitos_send_invites',        [__CLASS__, 'send_invites']);
        add_action('admin_post_bankitos_accept_invite',       [__CLASS__, 'accept_invite']);
        add_action('admin_post_nopriv_bankitos_accept_invite',[__CLASS__, 'accept_invite']);
        add_action('admin_post_bankitos_reject_invite',       [__CLASS__, 'reject_invite']);
        add_action('admin_post_nopriv_bankitos_reject_invite',[__CLASS__, 'reject_invite']);
        add_action('admin_post_bankitos_resend_invite',       [__CLASS__, 'resend_invite']);
        add_action('admin_post_bankitos_update_invite',       [__CLASS__, 'update_invite']);
        add_action('admin_post_bankitos_cancel_invite',       [__CLASS__, 'cancel_invite']);
    }

    public static function portal_url(string $token = ''): string {
        $base = apply_filters('bankitos_invite_portal_url', site_url('/invitacion'));
        if ($token) {
            $base = add_query_arg('invite_token', $token, $base);
        }
        return $base;
    }

    public static function send_invites(): void {
        if (!is_user_logged_in()) {
            wp_safe_redirect(site_url('/acceder'));
            exit;
        }
        check_admin_referer('bankitos_send_invites');

        if (!current_user_can('manage_bank_invites')) {
            self::redirect_with('err', 'permiso', site_url('/panel'));
        }

        $user_id  = get_current_user_id();
        $banco_id = isset($_POST['banco_id']) ? intval($_POST['banco_id']) : 0;
        $redirect = site_url('/panel');
        if ($banco_id <= 0) {
            self::redirect_with('err', 'invite_send', $redirect);
        }

        $user_banco = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($user_id) : 0;
        if ($user_banco !== $banco_id) {
            self::redirect_with('err', 'permiso', $redirect);
        }

        if (!class_exists('Bankitos_DB') || !Bankitos_DB::invites_table_exists()) {
            self::redirect_with('err', 'invite_send', $redirect);
        }

        $names  = isset($_POST['invite_name']) ? (array) $_POST['invite_name'] : [];
        $emails = isset($_POST['invite_email']) ? (array) $_POST['invite_email'] : [];

        $invites = [];
        $total   = max(count($names), count($emails));
        for ($i = 0; $i < $total; $i++) {
            $name  = isset($names[$i]) ? sanitize_text_field(wp_unslash($names[$i])) : '';
            $email = isset($emails[$i]) ? sanitize_email(wp_unslash($emails[$i])) : '';
            if (!$name || !$email || !is_email($email)) {
                continue;
            }
            $invites[strtolower($email)] = [
                'name'  => $name,
                'email' => $email,
            ];
        }

        if (!$invites) {
            self::redirect_with('err', 'invite_min', $redirect);
        }

        $existing_data  = self::get_bank_invites($banco_id);
        $members_count  = self::count_banco_members($banco_id);
        $requirements   = self::get_invite_requirements($banco_id, $members_count, $existing_data);
        $min_required   = $requirements['min_required'];

        if (count($invites) < $min_required) {
            self::redirect_with('err', 'invite_min', $redirect);
        }

        $sent = 0;
        $errors = false;
        foreach ($invites as $invite) {
            $result = self::store_invite($banco_id, $user_id, $invite['email'], $invite['name']);
            if (is_wp_error($result)) {
                $errors = true;
                continue;
            }
            if (!self::send_invite_email($result)) {
                $errors = true;
                continue;
            }
            $sent++;
        }

        if ($sent < 1 || $errors) {
            self::redirect_with('err', 'invite_send', $redirect);
        }

        self::redirect_with('ok', 'invite_sent', $redirect);
    }

    public static function resend_invite(): void {
        if (!is_user_logged_in()) {
            wp_safe_redirect(site_url('/acceder'));
            exit;
        }

        $invite_id = isset($_POST['invite_id']) ? absint($_POST['invite_id']) : 0;
        $redirect  = site_url('/panel');
        if ($invite_id <= 0) {
            self::redirect_with('err', 'invite_resend', $redirect);
        }

        check_admin_referer('bankitos_resend_invite_' . $invite_id);

        if (!current_user_can('manage_bank_invites')) {
            self::redirect_with('err', 'permiso', $redirect);
        }

        $invite = self::get_invite_by_id($invite_id);
        if (!$invite || (int) $invite['banco_id'] <= 0) {
            self::redirect_with('err', 'invite_resend', $redirect);
        }

        $user_id    = get_current_user_id();
        $user_banco = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($user_id) : 0;
        if ($user_banco !== (int) $invite['banco_id']) {
            self::redirect_with('err', 'permiso', $redirect);
        }

        if ($invite['status'] === self::STATUS_ACCEPTED) {
            self::redirect_with('err', 'invite_resend', $redirect);
        }

        $regen = self::regenerate_invite($invite, $user_id);
        if (is_wp_error($regen)) {
            self::redirect_with('err', 'invite_resend', $redirect);
        }

        if (!self::send_invite_email($regen)) {
            self::redirect_with('err', 'invite_resend', $redirect);
        }

        self::redirect_with('ok', 'invite_resent', $redirect);
    }

    public static function update_invite(): void {
        if (!is_user_logged_in()) {
            wp_safe_redirect(site_url('/acceder'));
            exit;
        }

        $invite_id = isset($_POST['invite_id']) ? absint($_POST['invite_id']) : 0;
        $redirect  = site_url('/panel');
        if ($invite_id <= 0) {
            self::redirect_with('err', 'invite_update', $redirect);
        }

        check_admin_referer('bankitos_update_invite_' . $invite_id);

        if (!current_user_can('manage_bank_invites')) {
            self::redirect_with('err', 'permiso', $redirect);
        }

        $invite = self::get_invite_by_id($invite_id);
        if (!$invite || (int) $invite['banco_id'] <= 0) {
            self::redirect_with('err', 'invite_update', $redirect);
        }

        $user_id    = get_current_user_id();
        $user_banco = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($user_id) : 0;
        if ($user_banco !== (int) $invite['banco_id']) {
            self::redirect_with('err', 'permiso', $redirect);
        }

        if ($invite['status'] === self::STATUS_ACCEPTED) {
            self::redirect_with('err', 'invite_update', $redirect);
        }

        $name  = isset($_POST['invite_name']) ? sanitize_text_field(wp_unslash($_POST['invite_name'])) : '';
        $email = isset($_POST['invite_email']) ? sanitize_email(wp_unslash($_POST['invite_email'])) : '';

        if (!$name || !$email || !is_email($email)) {
            self::redirect_with('err', 'invite_update', $redirect);
        }

        $data = [
            'invitee_name' => $name,
            'email'        => strtolower($email),
        ];

        // 1. Actualizar el nombre/correo en la BD
        $result = self::update_invite_row($invite_id, $data);
        if (is_wp_error($result)) {
            self::redirect_with('err', 'invite_update', $redirect);
        }

        // 2. Obtener la invitación recién actualizada
        $updated_invite = self::get_invite_by_id($invite_id);
        if (!$updated_invite) {
             self::redirect_with('err', 'invite_update', $redirect);
        }

        // 3. Regenerar token y reenviar (misma lógica que resend_invite)
        // $user_id ya estaba definido al inicio de esta función
        $regen = self::regenerate_invite($updated_invite, $user_id);
        if (is_wp_error($regen)) {
            self::redirect_with('err', 'invite_resend', $redirect);
        }

        // 4. Enviar el correo
        if (!self::send_invite_email($regen)) {
            self::redirect_with('err', 'invite_resend', $redirect);
        }

        // 5. Redirigir con un nuevo mensaje de éxito
        self::redirect_with('ok', 'invite_updated_sent', $redirect);
    }

    public static function cancel_invite(): void {
        if (!is_user_logged_in()) {
            wp_safe_redirect(site_url('/acceder'));
            exit;
        }

        $invite_id = isset($_POST['invite_id']) ? absint($_POST['invite_id']) : 0;
        $redirect  = site_url('/panel');
        if ($invite_id <= 0) {
            self::redirect_with('err', 'invite_cancel', $redirect);
        }

        check_admin_referer('bankitos_cancel_invite_' . $invite_id);

        if (!current_user_can('manage_bank_invites')) {
            self::redirect_with('err', 'permiso', $redirect);
        }

        $invite = self::get_invite_by_id($invite_id);
        if (!$invite || (int) $invite['banco_id'] <= 0) {
            self::redirect_with('err', 'invite_cancel', $redirect);
        }

        $user_id    = get_current_user_id();
        $user_banco = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($user_id) : 0;
        if ($user_banco !== (int) $invite['banco_id']) {
            self::redirect_with('err', 'permiso', $redirect);
        }

        if ($invite['status'] === self::STATUS_ACCEPTED) {
            self::redirect_with('err', 'invite_cancel', $redirect);
        }

        $deleted = self::delete_invite_row($invite_id);
        if (is_wp_error($deleted)) {
            self::redirect_with('err', 'invite_cancel', $redirect);
        }

        self::redirect_with('ok', 'invite_cancelled', $redirect);
    }

    public static function accept_invite(): void {
        $token = isset($_REQUEST['invite_token']) ? sanitize_text_field(wp_unslash($_REQUEST['invite_token'])) : '';
        $portal = self::portal_url($token);

        check_admin_referer('bankitos_accept_invite');

        if (!$token) {
            self::redirect_with('err', 'invite_token', $portal);
        }

        if (!is_user_logged_in()) {
            $login_url = add_query_arg('invite_token', $token, site_url('/acceder'));
            wp_safe_redirect($login_url);
            exit;
        }

        $user   = wp_get_current_user();
        $result = self::accept_invite_for_user($token, $user);
        if (is_wp_error($result)) {
            self::redirect_with('err', 'invite_accept', $portal);
        }

        self::redirect_with('ok', 'invite_accepted', site_url('/panel'));
    }

    public static function reject_invite(): void {
        $token = isset($_REQUEST['token']) ? sanitize_text_field(wp_unslash($_REQUEST['token'])) : '';
        $portal = self::portal_url($token);

        check_admin_referer('bankitos_reject_invite');

        if (!$token) {
            self::redirect_with('err', 'invite_token', $portal);
        }

        $context = self::get_invite_context($token);
        if (empty($context['exists']) || $context['status'] === self::STATUS_ACCEPTED) {
            self::redirect_with('err', 'invite_token', $portal);
        }

        self::update_invite_status((int) $context['id'], self::STATUS_REJECTED);
        self::redirect_with('ok', 'invite_rejected', $portal);
    }

    /**
     * @return true|WP_Error
     */
    public static function accept_invite_for_user(string $token, WP_User $user) {
        $context = self::get_invite_context($token);
        if (empty($context['exists'])) {
            return new WP_Error('bankitos_invite_invalid', __('La invitación no es válida.', 'bankitos'));
        }

        $can_accept = self::user_can_accept($context, $user);
        if (is_wp_error($can_accept)) {
            return $can_accept;
        }

        if (!class_exists('Bankitos_Handlers')) {
            return new WP_Error('bankitos_invite_internal', __('No pudimos procesar la invitación.', 'bankitos'));
        }

        $register = Bankitos_Handlers::registrar_miembro((int) $context['banco_id'], $user->ID);
        if (is_wp_error($register)) {
            return $register;
        }

        self::update_invite_status((int) $context['id'], self::STATUS_ACCEPTED, [
            'user_id'      => $user->ID,
            'accepted_at'  => current_time('mysql'),
        ]);

        return true;
    }

    /**
     * @return true|WP_Error
     */
    public static function user_can_accept(array $context, WP_User $user) {
        if (empty($context['exists'])) {
            return new WP_Error('bankitos_invite_invalid', __('La invitación no es válida.', 'bankitos'));
        }

        if ($context['status'] !== self::STATUS_PENDING) {
            return new WP_Error('bankitos_invite_status', __('La invitación ya no está disponible.', 'bankitos'));
        }

        $invited_email = strtolower($context['email']);
        $user_email    = strtolower($user->user_email);
        if (!$user_email || $user_email !== $invited_email) {
            return new WP_Error('bankitos_invite_email', __('Esta invitación fue enviada a otro correo.', 'bankitos'));
        }

        $user_banco = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($user->ID) : 0;
        if ($user_banco && $user_banco !== (int) $context['banco_id']) {
            return new WP_Error('bankitos_invite_other_bank', __('Ya perteneces a otro B@nko.', 'bankitos'));
        }

        return true;
    }

    public static function get_invite_context(string $token): array {
        $context = [
            'exists'         => false,
            'id'             => 0,
            'token'          => $token,
            'banco_id'       => 0,
            'bank_name'      => '',
            'email'          => '',
            'name'           => '',
            'inviter_id'     => 0,
            'inviter_name'   => '',
            'status'         => self::STATUS_REJECTED,
            'status_label'   => __('Rechazada', 'bankitos'),
            'status_message' => '',
        ];

        if (!$token || !class_exists('Bankitos_DB') || !Bankitos_DB::invites_table_exists()) {
            return $context;
        }

        global $wpdb;
        $table = Bankitos_DB::invites_table_name();
        $row   = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE token = %s", $token), ARRAY_A);

        if (!$row) {
            return $context;
        }

        $status = $row['status'];
        $created_at = isset($row['created_at']) ? strtotime($row['created_at']) : 0;
        $expiry_days = self::get_expiry_days();
        $expires_at  = $created_at ? strtotime('+' . $expiry_days . ' days', $created_at) : 0;
        $is_expired  = $status === self::STATUS_PENDING && $expires_at && $expires_at < time();
        if ($is_expired) {
            $status = self::STATUS_EXPIRED;
        }

        $status_labels = [
            self::STATUS_PENDING  => __('Enviada', 'bankitos'),
            self::STATUS_ACCEPTED => __('Aceptada', 'bankitos'),
            self::STATUS_REJECTED => __('Rechazada', 'bankitos'),
            self::STATUS_EXPIRED  => __('Expirada', 'bankitos'),
        ];

        $status_messages = [
            self::STATUS_ACCEPTED => __('Esta invitación ya fue aceptada.', 'bankitos'),
            self::STATUS_REJECTED => __('La invitación fue rechazada.', 'bankitos'),
            self::STATUS_EXPIRED  => __('La invitación ha expirado. Solicita una nueva.', 'bankitos'),
        ];

        $inviter_name = '';
        if (!empty($row['inviter_id'])) {
            $inviter = get_user_by('id', (int) $row['inviter_id']);
            if ($inviter) {
                $inviter_name = $inviter->display_name ?: $inviter->user_login;
            }
        }

        $context = array_merge($context, [
            'exists'         => true,
            'id'             => (int) $row['id'],
            'banco_id'       => (int) $row['banco_id'],
            'bank_name'      => get_the_title((int) $row['banco_id']),
            'email'          => strtolower($row['email']),
            'name'           => $row['invitee_name'],
            'inviter_id'     => (int) $row['inviter_id'],
            'inviter_name'   => $inviter_name,
            'status'         => $status,
            'status_label'   => $status_labels[$status] ?? $status,
            'status_message' => $status_messages[$status] ?? '',
            'expires_at'     => $expires_at,
        ]);

        return $context;
    }

    public static function get_bank_invites(int $banco_id): array {
        $data = [
            'rows'  => [],
            'stats' => [
                'total'    => 0,
                'pending'  => 0,
                'accepted' => 0,
                'rejected' => 0,
            ],
        ];

        if ($banco_id <= 0 || !class_exists('Bankitos_DB') || !Bankitos_DB::invites_table_exists()) {
            return $data;
        }

        global $wpdb;
        $table = Bankitos_DB::invites_table_name();
        $rows  = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE banco_id = %d ORDER BY created_at DESC", $banco_id), ARRAY_A);

        if (!$rows) {
            return $data;
        }

        $expiry_days = self::get_expiry_days();
        foreach ($rows as $row) {
            $status     = $row['status'];
            $created_at = isset($row['created_at']) ? strtotime($row['created_at']) : 0;
            $expires_at = $created_at ? strtotime('+' . $expiry_days . ' days', $created_at) : 0;
            $is_expired = $status === self::STATUS_PENDING && $expires_at && $expires_at < time();
            if ($is_expired) {
                $status = self::STATUS_EXPIRED;
            }

            $data['stats']['total']++;
            if ($status === self::STATUS_PENDING) {
                $data['stats']['pending']++;
            } elseif ($status === self::STATUS_ACCEPTED) {
                $data['stats']['accepted']++;
            } else {
                $data['stats']['rejected']++;
            }

            $data['rows'][] = [
                'id'           => (int) $row['id'],
                'name'         => $row['invitee_name'],
                'email'        => $row['email'],
                'status'       => $status,
                'status_label' => self::status_label($status),
                'created_at'   => $row['created_at'],
            ];
        }

        return $data;
    }

    private static function store_invite(int $banco_id, int $inviter_id, string $email, string $name) {
        global $wpdb;

        $table = Bankitos_DB::invites_table_name();
        $token = self::generate_unique_token();
        $now   = current_time('mysql');

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE banco_id = %d AND email = %s ORDER BY id DESC LIMIT 1",
                $banco_id,
                $email
            ),
            ARRAY_A
        );

        if ($existing) {
            $updated = $wpdb->update(
                $table,
                [
                    'invitee_name' => $name,
                    'token'        => $token,
                    'inviter_id'   => $inviter_id,
                    'status'       => self::STATUS_PENDING,
                    'created_at'   => $now,
                ],
                ['id' => (int) $existing['id']],
                ['%s', '%s', '%d', '%s', '%s'],
                ['%d']
            );
            if ($updated === false) {
                return new WP_Error('bankitos_invite_save', __('No pudimos guardar la invitación.', 'bankitos'));
            }
            $wpdb->query($wpdb->prepare("UPDATE {$table} SET accepted_at = NULL, user_id = NULL WHERE id = %d", (int) $existing['id']));
            $invite_id = (int) $existing['id'];
        } else {
            $inserted = $wpdb->insert(
                $table,
                [
                    'banco_id'     => $banco_id,
                    'email'        => $email,
                    'invitee_name' => $name,
                    'token'        => $token,
                    'inviter_id'   => $inviter_id,
                    'status'       => self::STATUS_PENDING,
                    'created_at'   => $now,
                ],
                ['%d', '%s', '%s', '%s', '%d', '%s', '%s']
            );
            if (!$inserted) {
                return new WP_Error('bankitos_invite_save', __('No pudimos guardar la invitación.', 'bankitos'));
            }
            $invite_id = (int) $wpdb->insert_id;
        }

        return [
            'id'         => $invite_id,
            'token'      => $token,
            'banco_id'   => $banco_id,
            'email'      => $email,
            'name'       => $name,
            'inviter_id' => $inviter_id,
        ];
    }

    private static function send_invite_email(array $invite): bool {
        $bank_name    = get_the_title($invite['banco_id']) ?: get_bloginfo('name');
        $inviter      = get_user_by('id', (int) $invite['inviter_id']);
        $inviter_name = $inviter ? ($inviter->display_name ?: $inviter->user_login) : get_bloginfo('name');
        $portal_url   = self::portal_url($invite['token']);
        $register_url = add_query_arg('invite_token', $invite['token'], site_url('/registrarse'));
        $login_url    = add_query_arg('invite_token', $invite['token'], site_url('/acceder'));

        $placeholders = [
            '{invitee_name}' => esc_html($invite['name']),
            '{invite_url}'   => esc_url($portal_url),
            '{bank_name}'    => esc_html($bank_name),
            '{inviter_name}' => esc_html($inviter_name),
            '{site_name}'    => esc_html(get_bloginfo('name')),
            '{register_url}' => esc_url($register_url),
            '{login_url}'    => esc_url($login_url),
            '{invite_link}'  => sprintf('<a href="%1$s">%2$s</a>', esc_url($portal_url), esc_html__('Ver invitación', 'bankitos')),
            '{register_link}' => sprintf('<a href="%1$s">%2$s</a>', esc_url($register_url), esc_html__('Crear cuenta', 'bankitos')),
            '{login_link}'    => sprintf('<a href="%1$s">%2$s</a>', esc_url($login_url), esc_html__('Iniciar sesión', 'bankitos')),
        ];

        $alternate_keys = [];
        foreach ($placeholders as $key => $value) {
            if ($key[0] === '{' && substr($key, -1) === '}') {
                $alternate_keys[str_replace(['{', '}'], ['[', ']'], $key)] = $value;
            }
        }
        $placeholders = array_merge($placeholders, $alternate_keys);

        $template = class_exists('Bankitos_Settings') ? Bankitos_Settings::get('email_template_invite', '') : '';
        if ($template) {
            $body = strtr($template, $placeholders);
            if ($body === strip_tags($body)) {
                $body = nl2br(esc_html($body));
            } else {
                $body  = '<p>' . sprintf(esc_html__('Hola %1$s, te han invitado a unirte al B@nko %2$s.', 'bankitos'), esc_html($invite['name']), esc_html($bank_name)) . '</p>';
                $body .= '<p><a href="' . esc_url($portal_url) . '">' . esc_html__('Ver invitación', 'bankitos') . '</a></p>';
                $body .= '<p>' . esc_html__('Si no esperabas este mensaje puedes ignorarlo.', 'bankitos') . '</p>';
                $body .= '<hr>';
                $body .= '<p>' . esc_html__('¿Aún no tienes cuenta? Crea una nueva o inicia sesión con los enlaces siguientes.', 'bankitos') . '</p>';
                $body .= '<p><a href="' . esc_url($register_url) . '">' . esc_html__('Crear cuenta', 'bankitos') . '</a> · <a href="' . esc_url($login_url) . '">' . esc_html__('Iniciar sesión', 'bankitos') . '</a></p>';
            }
        } else {
            $body = sprintf(
                "%s\n\n%s\n%s",
                sprintf(__('Hola %s, te han invitado a unirte al B@nko %s.', 'bankitos'), $invite['name'], $bank_name),
                sprintf(__('Haz clic en el siguiente enlace para ver la invitación: %s', 'bankitos'), $portal_url),
                __('Si no esperabas este mensaje puedes ignorarlo.', 'bankitos')
            );
        }

        $subject = apply_filters(
            'bankitos_invite_email_subject',
            sprintf(__('Invitación al B@nko %s', 'bankitos'), $bank_name),
            $invite,
            $placeholders
        );

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        if (class_exists('Bankitos_Settings')) {
            $from_name  = Bankitos_Settings::get('from_name', get_bloginfo('name'));
            $from_email = Bankitos_Settings::get('from_email', get_bloginfo('admin_email'));
            if ($from_email) {
                $headers[] = 'From: ' . sprintf('%s <%s>', $from_name, $from_email);
            }
        }

        $body = apply_filters('bankitos_invite_email_body', $body, $invite, $placeholders);

        return (bool) wp_mail($invite['email'], $subject, $body, $headers);
    }

    private static function update_invite_status(int $invite_id, string $status, array $extra = []): void {
        if (!class_exists('Bankitos_DB') || !Bankitos_DB::invites_table_exists()) {
            return;
        }

        global $wpdb;
        $table = Bankitos_DB::invites_table_name();
        $data  = array_merge(['status' => $status], $extra);
        $formats = [];
        foreach ($data as $value) {
            $formats[] = is_int($value) ? '%d' : '%s';
        }
        $wpdb->update($table, $data, ['id' => $invite_id], $formats, ['%d']);
    }

    private static function get_invite_by_id(int $invite_id): ?array {
        if ($invite_id <= 0 || !class_exists('Bankitos_DB') || !Bankitos_DB::invites_table_exists()) {
            return null;
        }

        global $wpdb;
        $table = Bankitos_DB::invites_table_name();
        $row   = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $invite_id), ARRAY_A);

        return $row ?: null;
    }

    private static function regenerate_invite(array $invite, int $inviter_id) {
        if (!class_exists('Bankitos_DB') || !Bankitos_DB::invites_table_exists()) {
            return new WP_Error('bankitos_invite_resend', __('No pudimos reenviar la invitación.', 'bankitos'));
        }

        global $wpdb;
        $table = Bankitos_DB::invites_table_name();
        $token = self::generate_unique_token();
        $now   = current_time('mysql');

        $updated = $wpdb->update(
            $table,
            [
                'token'        => $token,
                'status'       => self::STATUS_PENDING,
                'inviter_id'   => $inviter_id,
                'created_at'   => $now,
            ],
            ['id' => (int) $invite['id']],
            ['%s', '%s', '%d', '%s'],
            ['%d']
        );

        if ($updated === false) {
            return new WP_Error('bankitos_invite_resend', __('No pudimos reenviar la invitación.', 'bankitos'));
        }

        $wpdb->query($wpdb->prepare("UPDATE {$table} SET accepted_at = NULL, user_id = NULL WHERE id = %d", (int) $invite['id']));

        return [
            'id'         => (int) $invite['id'],
            'token'      => $token,
            'banco_id'   => (int) $invite['banco_id'],
            'email'      => sanitize_email($invite['email']),
            'name'       => isset($invite['invitee_name']) ? $invite['invitee_name'] : '',
            'inviter_id' => $inviter_id,
        ];
    }

    private static function update_invite_row(int $invite_id, array $data) {
        if (!class_exists('Bankitos_DB') || !Bankitos_DB::invites_table_exists()) {
            return new WP_Error('bankitos_invite_update', __('No pudimos actualizar la invitación.', 'bankitos'));
        }

        if (!$data) {
            return true;
        }

        global $wpdb;
        $table = Bankitos_DB::invites_table_name();
        $formats = [];
        foreach ($data as $value) {
            $formats[] = is_int($value) ? '%d' : '%s';
        }

        $updated = $wpdb->update($table, $data, ['id' => $invite_id], $formats, ['%d']);
        if ($updated === false) {
            return new WP_Error('bankitos_invite_update', __('No pudimos actualizar la invitación.', 'bankitos'));
        }

        return true;
    }

    private static function delete_invite_row(int $invite_id) {
        if (!class_exists('Bankitos_DB') || !Bankitos_DB::invites_table_exists()) {
            return new WP_Error('bankitos_invite_cancel', __('No pudimos cancelar la invitación.', 'bankitos'));
        }

        global $wpdb;
        $table = Bankitos_DB::invites_table_name();
        $deleted = $wpdb->delete($table, ['id' => $invite_id], ['%d']);

        if ($deleted === false) {
            return new WP_Error('bankitos_invite_cancel', __('No pudimos cancelar la invitación.', 'bankitos'));
        }

        return true;
    }

    private static function generate_unique_token(): string {
        global $wpdb;
        $table = class_exists('Bankitos_DB') ? Bankitos_DB::invites_table_name() : '';
        do {
            $token = wp_generate_password(32, false, false);
            $exists = false;
            if ($table) {
                $exists = (bool) $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$table} WHERE token = %s", $token));
            }
        } while ($exists);
        return $token;
    }

    private static function count_banco_members(int $banco_id): int {
        if ($banco_id <= 0) {
            return 0;
        }

        $query = new WP_User_Query([
            'meta_key'     => 'bankitos_banco_id',
            'meta_value'   => $banco_id,
            'fields'       => 'ID',
            'number'       => 1,
            'count_total'  => true,
        ]);

        if (method_exists($query, 'get_total')) {
            return (int) $query->get_total();
        }

        return (int) $query->total_users;
    }

    public static function get_invite_requirements(int $banco_id, int $members_count, array $existing_data = []): array {
        $stats = isset($existing_data['stats']) && is_array($existing_data['stats']) ? $existing_data['stats'] : [];
        $total_invites = isset($stats['total']) ? (int) $stats['total'] : 0;

        $default_min = $total_invites < 1 ? 4 : 1;
        $min_required = (int) apply_filters('bankitos_min_invites_required', $default_min, $banco_id, $existing_data);
        $min_required = max(1, $min_required);

        $initial_needed = max(0, 4 - max(0, $members_count));
        if ($initial_needed > 0) {
            $min_required = max($min_required, $initial_needed);
        }

        return [
            'min_required'   => $min_required,
            'initial_needed' => $initial_needed,
        ];
    }
    
    private static function status_label(string $status): string {
        switch ($status) {
            case self::STATUS_ACCEPTED:
                return __('Aceptada', 'bankitos');
            case self::STATUS_REJECTED:
                return __('Rechazada', 'bankitos');
            case self::STATUS_EXPIRED:
                return __('Expirada', 'bankitos');
            default:
                return __('Enviada', 'bankitos');
        }
    }

    private static function get_expiry_days(): int {
        $days = class_exists('Bankitos_Settings') ? (int) Bankitos_Settings::get('invite_expiry_days', 7) : 7;
        return max(1, $days);
    }

    private static function get_redirect_target(string $fallback): string {
        $redirect = isset($_REQUEST['redirect_to']) ? wp_unslash($_REQUEST['redirect_to']) : '';
        if ($redirect) {
            $valid = wp_validate_redirect(esc_url_raw($redirect), $fallback);
            if ($valid) {
                return $valid;
            }
        }
        $referer = wp_get_referer();
        if ($referer) {
            return $referer;
        }
        return $fallback;
    }

    private static function redirect_with(string $param, string $code, string $fallback): void {
        $target = self::get_redirect_target($fallback);
        wp_safe_redirect(add_query_arg($param, $code, $target));
        exit;
    }
}