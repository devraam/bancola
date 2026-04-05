<?php
if (!defined('ABSPATH')) exit;

class BK_Banco_Handler {

    public static function init() {
        add_action('admin_post_nopriv_bankitos_front_create',[__CLASS__,'front_create_denied']);
        add_action('admin_post_bankitos_front_create',       [__CLASS__,'front_create_banco']);
        add_action('admin_post_bankitos_assign_role',        [__CLASS__,'assign_role']);
    }

    public static function front_create_denied(){ wp_safe_redirect(site_url('/acceder')); exit; }

    private static function redir_err($code){ wp_safe_redirect(add_query_arg('err',$code, site_url('/crear-banko'))); exit; }

    public static function front_create_banco() {
        if (!is_user_logged_in()) { self::front_create_denied(); }
        check_admin_referer('bankitos_front_create');
        if (class_exists('Bankitos_Recaptcha')) {
            if (!Bankitos_Recaptcha::is_enabled()) { self::redir_err('recaptcha_config'); }
            $token = sanitize_text_field($_POST['g-recaptcha-response'] ?? '');
            if (!$token || !Bankitos_Recaptcha::verify_token($token)) { self::redir_err('recaptcha'); }
        }
        $user_id = get_current_user_id();
        if (class_exists('Bankitos_Handlers') && Bankitos_Handlers::get_user_banco_id($user_id) > 0) {
            wp_safe_redirect(add_query_arg('err','ya_miembro', site_url('/panel'))); exit;
        }
        $nombre=sanitize_text_field($_POST['nombre']??''); $objetivo=wp_kses_post($_POST['objetivo']??'');
        $cuota=isset($_POST['cuota_monto'])?floatval($_POST['cuota_monto']):0.0; $period=sanitize_key($_POST['periodicidad']??'');
        $tasa=isset($_POST['tasa'])?floatval($_POST['tasa']):0.0; $dur=intval($_POST['duracion_meses']??0);
        if ($nombre==='')   self::redir_err('nombre_req');
        if ($objetivo==='') self::redir_err('objetivo_req');
        if ($cuota < 1000) self::redir_err('cuota_min');
        if (!in_array($period,['semanal','quincenal','mensual'],true)) self::redir_err('periodicidad_req');
        if ($tasa < 0.1 || $tasa > 3.0) self::redir_err('tasa_rango');
        if (!in_array($dur,[2,4,6,8,12],true)) self::redir_err('duracion_invalida');

        // Mora config (optional)
        $mora_enabled         = !empty($_POST['mora_enabled']) ? 1 : 0;
        $mora_rate            = $mora_enabled ? floatval($_POST['mora_rate'] ?? 0)         : 0.0;
        $mora_grace_days      = $mora_enabled ? absint($_POST['mora_grace_days'] ?? 0)     : 0;
        $resignation_penalty  = absint($_POST['resignation_penalty'] ?? 0);

        if ($mora_enabled && ($mora_rate < 0.1 || $mora_rate > 5.0)) self::redir_err('mora_tasa_rango');
        if ($resignation_penalty > 100) self::redir_err('penalizacion_rango');

        $postarr=['post_type'=>Bankitos_CPT::SLUG_BANCO,'post_title'=>$nombre,'post_content'=>$objetivo,'post_status'=>'publish','post_author'=>$user_id];
        $post_id=wp_insert_post($postarr,true);
        if (is_wp_error($post_id) || !$post_id){ $postarr['post_status']='draft'; $post_id=wp_insert_post($postarr,true);
            if (is_wp_error($post_id) || !$post_id){ self::redir_err('crear_post'); } }
        update_post_meta($post_id, '_bk_objetivo',            $objetivo);
        update_post_meta($post_id, '_bk_cuota_monto',         $cuota);
        update_post_meta($post_id, '_bk_periodicidad',        $period);
        update_post_meta($post_id, '_bk_tasa',                $tasa);
        update_post_meta($post_id, '_bk_duracion_meses',      $dur);
        update_post_meta($post_id, '_bk_mora_enabled',        $mora_enabled);
        update_post_meta($post_id, '_bk_mora_rate',           $mora_rate);
        update_post_meta($post_id, '_bk_mora_grace_days',     $mora_grace_days);
        update_post_meta($post_id, '_bk_resignation_penalty', $resignation_penalty);
        if (class_exists('Bankitos_Handlers')) {
            $registro = Bankitos_Handlers::registrar_miembro($post_id,$user_id,'presidente');
            if (is_wp_error($registro)) {
                wp_delete_post($post_id, true);
                wp_safe_redirect(add_query_arg('err','ya_miembro', site_url('/panel'))); exit;
            }
        }
        $u=new WP_User($user_id);
        if ($u && !$u->has_cap('administrator')) {
            $existing_roles = is_array($u->roles) ? $u->roles : [];
            if (!get_user_meta($user_id, 'bankitos_previous_roles', true)) {
                update_user_meta($user_id, 'bankitos_previous_roles', $existing_roles);
            }
            if (!in_array('presidente', $existing_roles, true)) {
                $u->add_role('presidente');
            }
            if (in_array('socio_general', $existing_roles, true)) {
                $u->remove_role('socio_general');
            }
        }
        wp_safe_redirect(add_query_arg('ok','creado', site_url('/panel'))); exit;
    }

    public static function assign_role() {
        if (!is_user_logged_in()) {
            wp_safe_redirect(site_url('/acceder'));
            exit;
        }
        
        // 1. Validar Nonce y Permisos
        $member_user_id = isset($_POST['member_user_id']) ? absint($_POST['member_user_id']) : 0;
        $redirect_to = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : site_url('/panel');
        
        check_admin_referer('bankitos_assign_role_' . $member_user_id);

        if (!current_user_can('manage_bank_invites')) { // Re-usamos este permiso
            wp_safe_redirect(add_query_arg('err', 'permiso', $redirect_to));
            exit;
        }

        // 2. Validar Datos
        $new_role      = isset($_POST['member_role']) ? sanitize_key($_POST['member_role']) : '';
        $president_id  = get_current_user_id();
        $banco_id      = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($president_id) : 0;
        $member_banco_id = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($member_user_id) : 0;

        $allowed_roles = ['socio_general', 'secretario', 'tesorero', 'veedor', 'presidente'];

        if (!$member_user_id || empty($new_role) || !in_array($new_role, $allowed_roles, true)) {
            wp_safe_redirect(add_query_arg('err', 'validacion', $redirect_to));
            exit;
        }

        // 3. Validar que ambos usuarios estén en el mismo banco
        if ($banco_id <= 0 || $banco_id !== $member_banco_id) {
            wp_safe_redirect(add_query_arg('err', 'permiso', $redirect_to));
            exit;
        }

        // 4. Lógica de transferencia de presidencia
        if ($new_role === 'presidente') {
            $current_user_role = get_user_meta($president_id, 'bankitos_rol', true);
            $target_role       = get_user_meta($member_user_id, 'bankitos_rol', true);

            // Solo el presidente actual puede transferir y solo a un socio_general
            if ($current_user_role !== 'presidente') {
                wp_safe_redirect(add_query_arg('err', 'permiso', $redirect_to));
                exit;
            }
            if ($target_role !== 'socio_general') {
                wp_safe_redirect(add_query_arg('err', 'presidente_solo_a_socio', $redirect_to));
                exit;
            }

            // Aplicar cambios al nuevo presidente
            self::set_member_role($member_user_id, 'presidente', $banco_id);

            // Degradar al presidente actual a socio_general
            self::set_member_role($president_id, 'socio_general', $banco_id);

            // Notificar a todos los miembros
            self::notify_presidency_transfer($banco_id, $president_id, $member_user_id);

            do_action('bankitos_log_event', 'PRESIDENCY_TRANSFER', 'Presidencia transferida de usuario #' . $president_id . ' a usuario #' . $member_user_id, $banco_id, ['from' => $president_id, 'to' => $member_user_id]);
            wp_safe_redirect(add_query_arg('ok', 'presidencia_transferida', $redirect_to));
            exit;
        }

        // 5. Asignación normal de rol (no presidente)
        self::set_member_role($member_user_id, $new_role, $banco_id);

        wp_safe_redirect(add_query_arg('ok', 'role_updated', $redirect_to));
        exit;
    }

    private static function set_member_role(int $user_id, string $role, int $banco_id): void {
        update_user_meta($user_id, 'bankitos_rol', $role);

        $user = new WP_User($user_id);
        foreach (['socio_general', 'secretario', 'tesorero', 'veedor', 'presidente'] as $r) {
            $user->remove_role($r);
        }
        $user->add_role($role);

        if (class_exists('Bankitos_DB') && Bankitos_DB::members_table_exists()) {
            global $wpdb;
            $table = Bankitos_DB::members_table_name();
            $wpdb->update(
                $table,
                ['member_role' => $role],
                ['banco_id' => $banco_id, 'user_id' => $user_id],
                ['%s'],
                ['%d', '%d']
            );
        }
    }

    private static function notify_presidency_transfer(int $banco_id, int $old_president_id, int $new_president_id): void {
        global $wpdb;

        if (!class_exists('Bankitos_DB') || !Bankitos_DB::members_table_exists()) {
            return;
        }

        $old_president  = get_userdata($old_president_id);
        $new_president  = get_userdata($new_president_id);
        $banco_name     = get_the_title($banco_id) ?: 'B@nko #' . $banco_id;

        $old_name = $old_president ? $old_president->display_name : '#' . $old_president_id;
        $new_name = $new_president ? $new_president->display_name : '#' . $new_president_id;

        $subject = sprintf(__('Nuevo presidente en %s', 'bankitos'), $banco_name);
        $message = sprintf(
            __("Hola,\n\nEl banco %s tiene un nuevo presidente: %s.\n\n%s ha dejado el cargo de presidente y ahora es socio general.", 'bankitos'),
            $banco_name,
            $new_name,
            $old_name
        );

        $from_email = get_bloginfo('admin_email');
        if (class_exists('Bankitos_Settings')) {
            $custom = Bankitos_Settings::get('from_email', $from_email);
            if (is_email($custom)) {
                $from_email = $custom;
            }
        }
        $headers = ['From: ' . sprintf('%s <%s>', get_bloginfo('name'), $from_email)];

        $members_table = Bankitos_DB::members_table_name();
        $member_ids    = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$members_table} WHERE banco_id=%d",
            $banco_id
        ));

        foreach ($member_ids as $mid) {
            $member = get_userdata((int) $mid);
            if ($member && $member->user_email) {
                wp_mail($member->user_email, $subject, $message, $headers);
            }
        }
    }

}
