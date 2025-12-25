<?php
if (!defined('ABSPATH')) exit;

class BK_Credit_Disbursements_Handler {

    public static function init(): void {
        add_action('admin_post_bankitos_credit_disburse', [__CLASS__, 'submit_disbursement']);
        add_action('admin_post_bankitos_credit_disbursement_download', [__CLASS__, 'download_receipt']);
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
        return (array) apply_filters('bankitos_credit_disbursement_allowed_mimes', [
            'jpg|jpeg' => 'image/jpeg',
            'png'      => 'image/png',
            'pdf'      => 'application/pdf',
        ]);
    }

    public static function submit_disbursement(): void {
        if (!is_user_logged_in()) {
            wp_safe_redirect(site_url('/acceder'));
            exit;
        }

        $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
        check_admin_referer('bankitos_credit_disburse_' . $request_id);

        $redirect = site_url('/desembolsos');

        if (!current_user_can('approve_aportes')) {
            self::redirect_with('err', 'desembolso_permiso', $redirect);
        }

        $disbursement_date = isset($_POST['disbursement_date']) ? sanitize_text_field(wp_unslash($_POST['disbursement_date'])) : '';
        $file              = $_FILES['disbursement_receipt'] ?? null;

        if ($request_id <= 0 || !$disbursement_date) {
            self::redirect_with('err', 'desembolso_invalido', $redirect);
        }

        $request = Bankitos_Credit_Requests::get_request($request_id);
        if (!$request) {
            self::redirect_with('err', 'desembolso_invalido', $redirect);
        }

        $user_id  = get_current_user_id();
        $banco_id = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($user_id) : 0;
        if ($banco_id <= 0 || (int) $request['banco_id'] !== $banco_id) {
            self::redirect_with('err', 'desembolso_permiso', $redirect);
        }

        if (!in_array($request['status'], ['disbursement_pending', 'approved'], true)) {
            self::redirect_with('err', 'desembolso_estado', $redirect);
        }

        $date_obj = date_create($disbursement_date);
        if (!$date_obj) {
            self::redirect_with('err', 'desembolso_fecha', $redirect);
        }

        if (!$file || empty($file['name'])) {
            self::redirect_with('err', 'desembolso_archivo', $redirect);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        if (!empty($file['error']) && (int) $file['error'] !== UPLOAD_ERR_OK) {
            self::redirect_with('err', 'desembolso_archivo', $redirect);
        }

        // MODIFICADO: Aumento del límite a 10MB
        $max_size = (int) apply_filters('bankitos_credit_disbursement_max_filesize', 10 * MB_IN_BYTES);
        if ($max_size > 0 && !empty($file['size']) && (int) $file['size'] > $max_size) {
            self::redirect_with('err', 'desembolso_archivo_tamano', $redirect);
        }

        $allowed_mimes = self::allowed_mimes();
        $filetype      = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $allowed_mimes);
        if (empty($filetype['type']) || !in_array($filetype['type'], array_values($allowed_mimes), true)) {
            self::redirect_with('err', 'desembolso_archivo_tipo', $redirect);
        }

        add_filter('upload_mimes', [__CLASS__, 'filter_upload_mimes']);
        $attachment_id = media_handle_upload('disbursement_receipt', 0);
        remove_filter('upload_mimes', [__CLASS__, 'filter_upload_mimes']);

        if (is_wp_error($attachment_id) || $attachment_id <= 0) {
            self::redirect_with('err', 'desembolso_archivo', $redirect);
        }

        wp_update_post([
            'ID'          => $attachment_id,
            'post_author' => $user_id,
        ]);

        if (class_exists('Bankitos_Secure_Files') && !Bankitos_Secure_Files::protect_attachment($attachment_id)) {
            wp_delete_attachment($attachment_id, true);
            self::redirect_with('err', 'desembolso_archivo_seguro', $redirect);
        }

        $result = Bankitos_Credit_Requests::mark_disbursed($request_id, $disbursement_date, (int) $attachment_id);
        if (is_wp_error($result)) {
            wp_delete_attachment($attachment_id, true);
            self::redirect_with('err', 'desembolso_guardar', $redirect);
        }

        self::redirect_with('ok', 'desembolso_registrado', $redirect);
    }

    public static function filter_upload_mimes($mimes) {
        return array_merge((array) $mimes, self::allowed_mimes());
    }

    public static function get_receipt_download_url(int $request_id): string {
        if ($request_id <= 0) {
            return '';
        }
        return wp_nonce_url(add_query_arg([
            'action'     => 'bankitos_credit_disbursement_download',
            'request_id' => $request_id,
        ], admin_url('admin-post.php')), 'bankitos_credit_disbursement_download_' . $request_id);
    }

    public static function download_receipt(): void {
        if (!is_user_logged_in()) {
            wp_safe_redirect(site_url('/acceder'));
            exit;
        }
        
        $request_id = isset($_GET['request_id']) ? absint($_GET['request_id']) : 0;
        $nonce      = isset($_GET['_wpnonce']) ? sanitize_text_field($_GET['_wpnonce']) : '';
        
        if ($request_id <= 0 || !$nonce || !wp_verify_nonce($nonce, 'bankitos_credit_disbursement_download_' . $request_id)) {
            wp_die(__('Solicitud inválida.', 'bankitos'), 400);
        }

        $request = Bankitos_Credit_Requests::get_request($request_id);
        if (!$request) {
            wp_die(__('El comprobante no está disponible.', 'bankitos'), 404);
        }

        $current_user = wp_get_current_user();
        $is_owner     = (int) $current_user->ID === (int) $request['user_id'];
        $can_manage   = current_user_can('approve_aportes') || current_user_can('audit_aportes');
        $same_bank    = class_exists('Bankitos_Handlers') && (int) Bankitos_Handlers::get_user_banco_id($current_user->ID) === (int) $request['banco_id'];

        if ((!$is_owner && !$can_manage) || !$same_bank) {
            wp_die(__('No tienes permisos para ver este comprobante.', 'bankitos'), 403);
        }

        $attachment_id = (int) ($request['disbursement_attachment_id'] ?? 0);
        if ($attachment_id <= 0 || !class_exists('Bankitos_Secure_Files')) {
            wp_die(__('El comprobante no está disponible.', 'bankitos'), 404);
        }

        $path = Bankitos_Secure_Files::get_protected_path($attachment_id);
        if (!$path || !file_exists($path)) {
            wp_die(__('El archivo del comprobante no se encuentra.', 'bankitos'), 404);
        }

        $mime         = wp_check_filetype($path);
        $content_type = $mime['type'] ?: (function_exists('mime_content_type') ? mime_content_type($path) : 'application/octet-stream');
        $filename     = Bankitos_Secure_Files::get_download_filename($attachment_id);

        // --- CORRECCIÓN CLAVE: Limpiar buffer y desactivar compresión ---
        @ini_set('zlib.output_compression', 'Off');
        
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        nocache_headers();
        header('Content-Type: ' . $content_type);
        header('Content-Disposition: inline; filename="' . basename($filename) . '"');
        header('Content-Length: ' . filesize($path));
        
        readfile($path);
        exit;
    }
}