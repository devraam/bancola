<?php
if (!defined('ABSPATH')) exit;

class BK_Aportes_Handler {
    public static function init() {
        add_action('admin_post_bankitos_aporte_submit',      [__CLASS__,'aporte_submit']);
        add_action('admin_post_bankitos_aporte_approve',     [__CLASS__,'aporte_approve']);
        add_action('admin_post_bankitos_aporte_reject',      [__CLASS__,'aporte_reject']);
    }

    public static function aporte_submit() {
        if (!is_user_logged_in()) { wp_safe_redirect(site_url('/acceder')); exit; }
        check_admin_referer('bankitos_aporte_submit');
        $user_id=get_current_user_id();
        $banco_id = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($user_id) : 0;
        if ($banco_id<=0){ wp_safe_redirect(add_query_arg('err','no_banco', wp_get_referer()?:site_url('/panel'))); exit; }
        if (!current_user_can('submit_aportes')){ wp_safe_redirect(add_query_arg('err','permiso', wp_get_referer()?:site_url('/panel'))); exit; }
        $monto=isset($_POST['monto'])?floatval($_POST['monto']):0.0; if ($monto<=0){ wp_safe_redirect(add_query_arg('err','monto', wp_get_referer()?:site_url('/panel'))); exit; }
        $aporte_id=wp_insert_post(['post_type'=>Bankitos_CPT::SLUG_APORTE,'post_title'=>'Aporte de '.wp_get_current_user()->display_name.' ('.current_time('Y-m-d H:i').')','post_status'=>'pending','post_author'=>$user_id],true);
        if (is_wp_error($aporte_id) || !$aporte_id){ wp_safe_redirect(add_query_arg('err','crear_aporte', wp_get_referer()?:site_url('/panel'))); exit; }
        update_post_meta($aporte_id,'_bankitos_banco_id',$banco_id); update_post_meta($aporte_id,'_bankitos_monto',$monto);
        if (!empty($_FILES['comprobante']['name'])){
            require_once ABSPATH.'wp-admin/includes/file.php'; require_once ABSPATH.'wp-admin/includes/media.php'; require_once ABSPATH.'wp-admin/includes/image.php';
            $attach_id=media_handle_upload('comprobante',$aporte_id); if (!is_wp_error($attach_id)) set_post_thumbnail($aporte_id,$attach_id);
        }
        wp_safe_redirect(add_query_arg('ok','aporte_enviado', site_url('/panel'))); exit;
    }

    private static function check_same_banco($aporte_id, $user_id): bool {
        $tes_banco = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($user_id) : 0;
        $ap_banco  = intval(get_post_meta($aporte_id,'_bankitos_banco_id',true));
        return ($tes_banco>0 && $tes_banco===$ap_banco);
    }

    public static function aporte_approve() {
        if (!is_user_logged_in()) { wp_safe_redirect(site_url('/acceder')); exit; }
        check_admin_referer('bankitos_aporte_mod');
        if (!current_user_can('approve_aportes')){ wp_safe_redirect(add_query_arg('err','permiso', wp_get_referer()?:site_url('/panel'))); exit; }
        $aporte_id=intval($_GET['aporte']??0); $aporte=get_post($aporte_id);
        if (!$aporte || $aporte->post_type!==Bankitos_CPT::SLUG_APORTE){ wp_safe_redirect(site_url('/panel')); exit; }
        if (!self::check_same_banco($aporte_id,get_current_user_id())){ wp_safe_redirect(add_query_arg('err','permiso', site_url('/panel'))); exit; }
        wp_update_post(['ID'=>$aporte_id,'post_status'=>'publish']);
        wp_safe_redirect(add_query_arg('ok','aporte_aprobado', site_url('/panel'))); exit;
    }
    public static function aporte_reject() {
        if (!is_user_logged_in()) { wp_safe_redirect(site_url('/acceder')); exit; }
        check_admin_referer('bankitos_aporte_mod');
        if (!current_user_can('approve_aportes')){ wp_safe_redirect(add_query_arg('err','permiso', wp_get_referer()?:site_url('/panel'))); exit; }
        $aporte_id=intval($_GET['aporte']??0); $aporte=get_post($aporte_id);
        if (!$aporte || $aporte->post_type!==Bankitos_CPT::SLUG_APORTE){ wp_safe_redirect(site_url('/panel')); exit; }
        if (!self::check_same_banco($aporte_id,get_current_user_id())){ wp_safe_redirect(add_query_arg('err','permiso', site_url('/panel'))); exit; }
        wp_update_post(['ID'=>$aporte_id,'post_status'=>'private']);
        wp_safe_redirect(add_query_arg('ok','aporte_rechazado', site_url('/panel'))); exit;
    }
}
