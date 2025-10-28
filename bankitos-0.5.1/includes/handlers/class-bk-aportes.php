<?php
if (!defined('ABSPATH')) exit;

class BK_Aportes_Handler {
    public static function init() {
        add_action('admin_post_bankitos_aporte_submit',      [__CLASS__,'aporte_submit']);
        add_action('admin_post_bankitos_aporte_approve',     [__CLASS__,'aporte_approve']);
        add_action('admin_post_bankitos_aporte_reject',      [__CLASS__,'aporte_reject']);
    }

    private static function get_redirect_target(string $fallback): string {
        $redirect = isset($_REQUEST['redirect_to']) ? wp_unslash($_REQUEST['redirect_to']) : '';
        if ($redirect) {
            $validated = wp_validate_redirect(esc_url_raw($redirect), $fallback);
            if ($validated) {
                return $validated;
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

    private static function allowed_mimes(): array {
        return (array) apply_filters('bankitos_aporte_allowed_mimes', [
            'jpg|jpeg' => 'image/jpeg',
            'png'      => 'image/png',
            'gif'      => 'image/gif',
            'webp'     => 'image/webp',
        ]);
    }

    public static function filter_upload_mimes($mimes) {
        return array_merge((array) $mimes, self::allowed_mimes());
    }

    public static function aporte_submit() {
        if (!is_user_logged_in()) { wp_safe_redirect(site_url('/acceder')); exit; }
        check_admin_referer('bankitos_aporte_submit');
        $user_id=get_current_user_id();
        $banco_id = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($user_id) : 0;
        if ($banco_id<=0){ self::redirect_with('err','no_banco', site_url('/panel')); }
        if (!current_user_can('submit_aportes')){ self::redirect_with('err','permiso', site_url('/panel')); }
        $monto=isset($_POST['monto'])?floatval($_POST['monto']):0.0; if ($monto<=0){ self::redirect_with('err','monto', site_url('/panel')); }
        $aporte_id=wp_insert_post(['post_type'=>Bankitos_CPT::SLUG_APORTE,'post_title'=>'Aporte de '.wp_get_current_user()->display_name.' ('.current_time('Y-m-d H:i').')','post_status'=>'pending','post_author'=>$user_id],true);
        if (is_wp_error($aporte_id) || !$aporte_id){ self::redirect_with('err','crear_aporte', site_url('/panel')); }
        update_post_meta($aporte_id,'_bankitos_banco_id',$banco_id); update_post_meta($aporte_id,'_bankitos_monto',$monto);
        if (!empty($_FILES['comprobante']['name'])){
            require_once ABSPATH.'wp-admin/includes/file.php'; require_once ABSPATH.'wp-admin/includes/media.php'; require_once ABSPATH.'wp-admin/includes/image.php';
            $file = $_FILES['comprobante'];
            if (!empty($file['error']) && (int) $file['error'] !== UPLOAD_ERR_OK) {
                wp_delete_post($aporte_id, true);
                self::redirect_with('err','archivo_subida', site_url('/panel'));
            }
            $max_size = (int) apply_filters('bankitos_aporte_max_filesize', 5 * MB_IN_BYTES);
            if ($max_size > 0 && !empty($file['size']) && (int) $file['size'] > $max_size) {
                wp_delete_post($aporte_id, true);
                self::redirect_with('err','archivo_tamano', site_url('/panel'));
            }
            $allowed_mimes = self::allowed_mimes();
            $filetype = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $allowed_mimes);
            if (empty($filetype['type']) || !in_array($filetype['type'], array_values($allowed_mimes), true)) {
                wp_delete_post($aporte_id, true);
                self::redirect_with('err','archivo_tipo', site_url('/panel'));
            }
            add_filter('upload_mimes', [__CLASS__, 'filter_upload_mimes']);
            $attach_id=media_handle_upload('comprobante',$aporte_id);
            remove_filter('upload_mimes', [__CLASS__, 'filter_upload_mimes']);
            if (is_wp_error($attach_id)) {
                wp_delete_post($aporte_id, true);
                self::redirect_with('err','archivo_subida', site_url('/panel'));
            }
            set_post_thumbnail($aporte_id,$attach_id);
        }
        self::redirect_with('ok','aporte_enviado', site_url('/panel'));
    }

    private static function check_same_banco($aporte_id, $user_id): bool {
        $tes_banco = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($user_id) : 0;
        $ap_banco  = intval(get_post_meta($aporte_id,'_bankitos_banco_id',true));
        return ($tes_banco>0 && $tes_banco===$ap_banco);
    }

    public static function aporte_approve() {
        if (!is_user_logged_in()) { wp_safe_redirect(site_url('/acceder')); exit; }
        check_admin_referer('bankitos_aporte_mod');
        if (!current_user_can('approve_aportes')){ self::redirect_with('err','permiso', site_url('/panel')); }
        $aporte_id=intval($_GET['aporte']??0); $aporte=get_post($aporte_id);
        if (!$aporte || $aporte->post_type!==Bankitos_CPT::SLUG_APORTE){ wp_safe_redirect(site_url('/panel')); exit; }
        if (!self::check_same_banco($aporte_id,get_current_user_id())){ self::redirect_with('err','permiso', site_url('/panel')); }
        wp_update_post(['ID'=>$aporte_id,'post_status'=>'publish']);
        self::redirect_with('ok','aporte_aprobado', site_url('/panel'));
    }
    public static function aporte_reject() {
        if (!is_user_logged_in()) { wp_safe_redirect(site_url('/acceder')); exit; }
        check_admin_referer('bankitos_aporte_mod');
        if (!current_user_can('approve_aportes')){ self::redirect_with('err','permiso', site_url('/panel')); }
        $aporte_id=intval($_GET['aporte']??0); $aporte=get_post($aporte_id);
        if (!$aporte || $aporte->post_type!==Bankitos_CPT::SLUG_APORTE){ wp_safe_redirect(site_url('/panel')); exit; }
        if (!self::check_same_banco($aporte_id,get_current_user_id())){ self::redirect_with('err','permiso', site_url('/panel')); }
        wp_update_post(['ID'=>$aporte_id,'post_status'=>'private']);
        self::redirect_with('ok','aporte_rechazado', site_url('/panel'));
    }
}
