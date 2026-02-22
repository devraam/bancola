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

        $postarr=['post_type'=>Bankitos_CPT::SLUG_BANCO,'post_title'=>$nombre,'post_content'=>$objetivo,'post_status'=>'publish','post_author'=>$user_id];
        $post_id=wp_insert_post($postarr,true);
        if (is_wp_error($post_id) || !$post_id){ $postarr['post_status']='draft'; $post_id=wp_insert_post($postarr,true);
            if (is_wp_error($post_id) || !$post_id){ self::redir_err('crear_post'); } }
        update_post_meta($post_id,'_bk_objetivo',$objetivo);
        update_post_meta($post_id,'_bk_cuota_monto',$cuota);
        update_post_meta($post_id,'_bk_periodicidad',$period);
        update_post_meta($post_id,'_bk_tasa',$tasa);
        update_post_meta($post_id,'_bk_duracion_meses',$dur);
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
        $new_role = isset($_POST['member_role']) ? sanitize_key($_POST['member_role']) : '';
        $allowed_roles = ['socio_general', 'secretario', 'tesorero', 'veedor']; // Presidente no puede asignar a otro presidente
        
        if (!$member_user_id || empty($new_role) || !in_array($new_role, $allowed_roles, true)) {
            wp_safe_redirect(add_query_arg('err', 'validacion', $redirect_to));
            exit;
        }

        // 3. Validar que el Presidente y el Miembro estén en el mismo banco
        $president_id = get_current_user_id();
        $banco_id = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($president_id) : 0;
        $member_banco_id = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($member_user_id) : 0;

        if ($banco_id <= 0 || $banco_id !== $member_banco_id) {
            wp_safe_redirect(add_query_arg('err', 'permiso', $redirect_to));
            exit;
        }

        // 4. Asignar el Rol
        // Primero, actualizar el meta 'bankitos_rol'
        update_user_meta($member_user_id, 'bankitos_rol', $new_role);

        // Segundo, actualizar el rol de WordPress
        $user = new WP_User($member_user_id);
        if ($user) {
            // Quitar todos los roles del plugin para evitar duplicados
            $user->remove_role('socio_general');
            $user->remove_role('secretario');
            $user->remove_role('tesorero');
            $user->remove_role('veedor');
            
            // Añadir el nuevo rol
            $user->add_role($new_role);
        }
        
        // Tercero, actualizar la tabla 'wp_banco_members'
        if (class_exists('Bankitos_DB') && Bankitos_DB::members_table_exists()) {
            global $wpdb;
            $table = Bankitos_DB::members_table_name();
            $wpdb->update(
                $table,
                ['member_role' => $new_role],
                ['banco_id' => $banco_id, 'user_id' => $member_user_id],
                ['%s'],
                ['%d', '%d']
            );
        }

        // 5. Redirigir
        wp_safe_redirect(add_query_arg('ok', 'role_updated', $redirect_to));
        exit;
    }

}
