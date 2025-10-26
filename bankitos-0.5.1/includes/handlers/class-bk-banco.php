<?php
if (!defined('ABSPATH')) exit;

class BK_Banco_Handler {
    public static function init() {
        add_action('admin_post_nopriv_bankitos_front_create',[__CLASS__,'front_create_denied']);
        add_action('admin_post_bankitos_front_create',       [__CLASS__,'front_create_banco']);
    }
    public static function front_create_denied(){ wp_safe_redirect(site_url('/acceder')); exit; }

    private static function redir_err($code){ wp_safe_redirect(add_query_arg('err',$code, site_url('/crear-banko'))); exit; }

    public static function front_create_banco() {
        if (!is_user_logged_in()) { self::front_create_denied(); }
        check_admin_referer('bankitos_front_create');
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
        if (class_exists('Bankitos_Handlers')) Bankitos_Handlers::registrar_miembro($post_id,$user_id,'presidente');
        $u=new WP_User($user_id); if ($u && !$u->has_cap('administrator')) $u->set_role('presidente');
        wp_safe_redirect(add_query_arg('ok','creado', site_url('/panel'))); exit;
    }
}
