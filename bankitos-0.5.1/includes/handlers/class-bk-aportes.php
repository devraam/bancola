<?php
if (!defined('ABSPATH')) exit;

class BK_Aportes_Handler {
    public static function init() {
        add_action('admin_post_bankitos_aporte_submit',      [__CLASS__,'aporte_submit']);
        add_action('admin_post_bankitos_aporte_approve',     [__CLASS__,'aporte_approve']);
        add_action('admin_post_bankitos_aporte_reject',      [__CLASS__,'aporte_reject']);
        add_action('admin_post_bankitos_aporte_download',    [__CLASS__,'aporte_download']);
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
            'pdf'      => 'application/pdf',
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
            $max_size = (int) apply_filters('bankitos_aporte_max_filesize', 1 * MB_IN_BYTES);
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
            if (class_exists('Bankitos_Secure_Files') && !Bankitos_Secure_Files::protect_attachment($attach_id)) {
                wp_delete_post($aporte_id, true);
                self::redirect_with('err','archivo_seguro', site_url('/panel'));
            }
        }
        self::redirect_with('ok','aporte_enviado', site_url('/panel'));
    }

    public static function get_comprobante_download_url(int $aporte_id): string {
        $attachment_id = get_post_thumbnail_id($aporte_id);
        if (!$attachment_id) {
            return '';
        }

        $attachment_url = wp_get_attachment_url($attachment_id);
        $attached_path  = get_attached_file($attachment_id);

        if ($attachment_url && $attached_path && file_exists($attached_path)) {
            return $attachment_url;
        }

        if (class_exists('Bankitos_Secure_Files')) {
            $path = Bankitos_Secure_Files::get_protected_path($attachment_id);
            if ($path) {
                $download_base = admin_url('admin-post.php', 'relative');

                return wp_nonce_url(add_query_arg([
                    'action' => 'bankitos_aporte_download',
                    'aporte' => $aporte_id,
                ], $download_base), 'bankitos_aporte_download_' . $aporte_id);
            }
        }

        return $attachment_url ?: '';
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

    public static function aporte_download() {
        if (!is_user_logged_in()) { wp_safe_redirect(site_url('/acceder')); exit; }
        $aporte_id = intval($_GET['aporte'] ?? 0);
        if ($aporte_id <= 0) { wp_die(__('Solicitud inválida.', 'bankitos'), 400); }
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field($_GET['_wpnonce']) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'bankitos_aporte_download_' . $aporte_id)) {
            wp_die(__('No tienes permisos para ver este comprobante.', 'bankitos'), 403);
        }
        $current_user = wp_get_current_user();
        $is_owner     = (int) $current_user->ID === (int) get_post_field('post_author', $aporte_id);
        $can_manage   = current_user_can('approve_aportes') || current_user_can('audit_aportes');
        if (!$is_owner && !$can_manage) {
            wp_die(__('No tienes permisos para ver este comprobante.', 'bankitos'), 403);
        }
        if (!self::check_same_banco($aporte_id, $current_user->ID)) {
            wp_die(__('No tienes permisos para ver este comprobante.', 'bankitos'), 403);
        }
        $attachment_id = get_post_thumbnail_id($aporte_id);
        if (!$attachment_id || !class_exists('Bankitos_Secure_Files')) {
            wp_die(__('El comprobante no está disponible.', 'bankitos'), 404);
        }
        $path = Bankitos_Secure_Files::get_protected_path($attachment_id);
        if (!$path) {
            wp_die(__('El comprobante no está disponible.', 'bankitos'), 404);
        }
        $mime = wp_check_filetype($path);
        $filename = Bankitos_Secure_Files::get_download_filename($attachment_id);
        header('Content-Type: ' . ($mime['type'] ?: 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . basename($filename) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}
