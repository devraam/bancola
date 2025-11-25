<?php
if (!defined('ABSPATH')) exit;

class BK_Aportes_Handler {
    public static function init() {
        add_action('admin_post_bankitos_aporte_submit',      [__CLASS__,'aporte_submit']);
        add_action('admin_post_bankitos_aporte_approve',     [__CLASS__,'aporte_approve']);
        add_action('admin_post_bankitos_aporte_reject',      [__CLASS__,'aporte_reject']);
        add_action('admin_post_bankitos_aporte_download',    [__CLASS__,'aporte_download']);
        add_action('admin_post_bankitos_aporte_view',        [__CLASS__,'aporte_view']);
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

    /**
     * Verifica si un adjunto es un tipo de archivo de imagen (JPG, PNG, etc.).
     */
    public static function is_file_image(int $attachment_id): bool {
        if ($attachment_id <= 0) {
            return false;
        }
        $mime = get_post_mime_type($attachment_id);
        return strpos($mime, 'image/') === 0;
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
        return self::build_secure_comprobante_url($aporte_id, 'bankitos_aporte_download_', 'bankitos_aporte_download');
    }

    public static function get_comprobante_view_url(int $aporte_id): string {
        return self::build_secure_comprobante_url($aporte_id, 'bankitos_aporte_view_', 'bankitos_aporte_view');
    }

    /**
     * Obtiene una URL accesible por el navegador para visualizar el comprobante.
     * Siempre devuelve la URL del handler seguro (admin-post.php) si el archivo está protegido.
     */
    public static function get_comprobante_view_src(int $aporte_id): string {
        if (!is_user_logged_in()) {
            return '';
        }

        $attachment_id = get_post_thumbnail_id($aporte_id);
        if ($attachment_id <= 0) {
            return '';
        }

        $current_user = wp_get_current_user();
        if (!$current_user || !$current_user->ID) {
            return '';
        }

        $is_owner   = (int) get_post_field('post_author', $aporte_id) === (int) $current_user->ID;
        $can_manage = user_can($current_user, 'approve_aportes') || user_can($current_user, 'audit_aportes');

        if (!$is_owner && !$can_manage) {
            return '';
        }

        if (!self::check_same_banco($aporte_id, $current_user->ID)) {
            return '';
        }

        // 1) Si el archivo está protegido, SIEMPRE usamos el endpoint seguro con nonce (admin-post.php).
        $secure_url = self::get_comprobante_view_url($aporte_id);
        if ($secure_url) {
            return $secure_url;
        }

        // 2) Último recurso: la URL pública del adjunto (si el sistema de protección no se aplicó o falló).
        return wp_get_attachment_url($attachment_id) ?: '';
    }

    private static function build_secure_comprobante_url(int $aporte_id, string $nonce_action, string $action): string {
        $attachment_id = get_post_thumbnail_id($aporte_id);
        if ($attachment_id <= 0) {
            return '';
        }
        if (function_exists('get_post_mime_type')) {
            $mime = get_post_mime_type($attachment_id);
            if (!$mime || !preg_match('#^(image|application)/(jpeg|png|pdf)$#', $mime)) {
                return '';
            }
        }
        if (!class_exists('Bankitos_Secure_Files')) {
            return '';
        }
        // Verificamos que el archivo protegido exista antes de generar el enlace seguro.
        $path = Bankitos_Secure_Files::get_protected_path($attachment_id);
        if (!$path) {
            return '';
        }
        
        $download_base = admin_url('admin-post.php');

        return wp_nonce_url(add_query_arg([
            'action' => $action,
            'aporte' => $aporte_id,
        ], $download_base), $nonce_action . $aporte_id);
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

    /**
     * Streams the protected aporte file, handling permissions and output.
     */
    private static function stream_aporte_file(int $aporte_id, string $nonce_action, string $disposition = 'inline'): void {
        if (!is_user_logged_in()) { 
            wp_safe_redirect(site_url('/acceder')); 
            exit; 
        }
        
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field($_GET['_wpnonce']) : '';
        if (!$nonce || !wp_verify_nonce($nonce, $nonce_action . $aporte_id)) {
            if (ob_get_length()) ob_clean(); 
            wp_die(__('No tienes permisos para ver este comprobante.', 'bankitos'), 403);
        }
        
        $current_user = wp_get_current_user();

        $is_owner   = (int) get_post_field('post_author', $aporte_id) === (int) $current_user->ID;
        $can_manage = user_can($current_user, 'approve_aportes') || user_can($current_user, 'audit_aportes');
        
        if (!$is_owner && !$can_manage) {
            if (ob_get_length()) ob_clean();
            wp_die(__('No tienes permisos para ver este comprobante.', 'bankitos'), 403);
        }
        if (!self::check_same_banco($aporte_id, $current_user->ID)) {
            if (ob_get_length()) ob_clean();
            wp_die(__('No tienes permisos para ver este comprobante.', 'bankitos'), 403);
        }

        $attachment_id = get_post_thumbnail_id($aporte_id);
        if (!$attachment_id || !class_exists('Bankitos_Secure_Files')) {
            if (ob_get_length()) ob_clean();
            wp_die(__('El comprobante no está disponible.', 'bankitos'), 404);
        }
        
        $path = Bankitos_Secure_Files::get_protected_path($attachment_id);
        if (!$path || !is_readable($path)) {
            if (ob_get_length()) ob_clean();
            wp_die(__('El archivo del comprobante no se pudo leer.', 'bankitos'), 500);
        }

        $mime = wp_check_filetype($path);
        $content_type = $mime['type'] ?: 'application/octet-stream';
        $filename = Bankitos_Secure_Files::get_download_filename($attachment_id);
        
        // **FIX: Ensure all output buffers are flushed/cleaned and stop gzip compression.**
        // Desactivar compresión GZIP si está activa para evitar que interfiera con la salida binaria.
        @ini_set('zlib.output_compression', 'Off'); 
        
        // Limpiar todos los buffers de salida para evitar que se envíen datos inesperados.
        // Se usa un bucle para limpiar múltiples niveles de buffer.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        nocache_headers();
        
        // Set file headers
        header('Content-Type: ' . $content_type);
        header('Content-Disposition: ' . $disposition . '; filename="' . basename($filename) . '"');
        header('Content-Length: ' . filesize($path));
        
        // Stream the file content
        readfile($path);
        
        // Exit cleanly to stop WordPress execution
        exit;
    }

    public static function aporte_view() {
        $aporte_id = intval($_GET['aporte'] ?? 0);
        self::stream_aporte_file($aporte_id, 'bankitos_aporte_view_', 'inline');
    }

    public static function aporte_download() {
        $aporte_id = intval($_GET['aporte'] ?? 0);
        self::stream_aporte_file($aporte_id, 'bankitos_aporte_download_', 'attachment');
    }
}