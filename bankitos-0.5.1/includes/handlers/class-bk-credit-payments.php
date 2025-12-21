<?php
if (!defined('ABSPATH')) exit;

class BK_Credit_Payments_Handler {

    public static function init(): void {
        add_action('admin_post_bankitos_credit_payment_submit', [__CLASS__, 'submit_payment']);
        add_action('admin_post_bankitos_credit_payment_approve', [__CLASS__, 'approve_payment']);
        add_action('admin_post_bankitos_credit_payment_reject', [__CLASS__, 'reject_payment']);
        add_action('admin_post_bankitos_credit_payment_download', [__CLASS__, 'download_receipt']);
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
        return (array) apply_filters('bankitos_credit_payment_allowed_mimes', [
            'jpg|jpeg' => 'image/jpeg',
            'png'      => 'image/png',
            'pdf'      => 'application/pdf',
        ]);
    }

    public static function submit_payment(): void {
        if (!is_user_logged_in()) {
            wp_safe_redirect(site_url('/acceder'));
            exit;
        }
        check_admin_referer('bankitos_credit_payment_submit');
        
        $user_id    = get_current_user_id();
        $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
        
        // Recibimos el monto. El shortcode ahora enviará formato plano (ej: 17666.67)
        // Usamos floatval directo.
        $amount_raw = isset($_POST['amount']) ? $_POST['amount'] : 0;
        $amount     = floatval($amount_raw);
        
        $redirect   = site_url('/creditos/');

        if ($request_id <= 0 || $amount <= 0) {
            self::redirect_with('err', 'pago_invalido', $redirect);
        }

        if (!current_user_can('submit_aportes')) {
            self::redirect_with('err', 'pago_permiso', $redirect);
        }

        // Verificar propiedad del crédito y estado
        $request = Bankitos_Credit_Requests::get_request($request_id);
        if (!$request || (int) $request['user_id'] !== $user_id) {
            self::redirect_with('err', 'pago_permiso', $redirect);
        }
        if ($request['status'] !== 'approved') {
            self::redirect_with('err', 'pago_permiso', $redirect);
        }

        // Verificar banco
        $banco_id = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($user_id) : 0;
        if ($banco_id <= 0 || (int) $request['banco_id'] !== $banco_id) {
            self::redirect_with('err', 'pago_permiso', $redirect);
        }

        // --- MANEJO DE ARCHIVO (alineado con bankitos_aporte_form) ---
        $file = $_FILES['receipt'] ?? null;
        if (!$file || empty($file['name'])) {
            self::redirect_with('err', 'pago_archivo_requerido', $redirect);
        }

        // Cargar librerías de WP necesarias
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Validaciones básicas
        if (!empty($file['error']) && (int) $file['error'] !== UPLOAD_ERR_OK) {
            self::redirect_with('err', 'pago_archivo_subida', $redirect);
        }
        
        $max_size = (int) apply_filters('bankitos_credit_payment_max_filesize', 1 * MB_IN_BYTES);
        if ($max_size > 0 && !empty($file['size']) && (int) $file['size'] > $max_size) {
            self::redirect_with('err', 'pago_archivo_tamano', $redirect);
        }

        // Validar tipo de archivo
        $allowed_mimes = self::allowed_mimes();
        $filetype_check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $allowed_mimes);
        
        if (empty($filetype_check['type']) || !in_array($filetype_check['type'], array_values($allowed_mimes), true)) {
            self::redirect_with('err', 'pago_archivo_tipo', $redirect);
        }

        // Subir el archivo usando la misma mecánica que aportes
        add_filter('upload_mimes', [__CLASS__, 'filter_upload_mimes']);
        $attachment_id = media_handle_upload('receipt', 0);
        remove_filter('upload_mimes', [__CLASS__, 'filter_upload_mimes']);

        if (is_wp_error($attachment_id) || $attachment_id <= 0) {
            self::redirect_with('err', 'pago_archivo_subida', $redirect);
        }

        // Asegurar autor y metadatos consistentes
        wp_update_post([
            'ID'          => $attachment_id,
            'post_author' => $user_id,
        ]);

        // Proteger archivo (mover a carpeta privada si el sistema de seguridad está activo)
        if (class_exists('Bankitos_Secure_Files') && !Bankitos_Secure_Files::protect_attachment($attachment_id)) {
            // Si falla la protección, borramos todo por seguridad
            wp_delete_attachment($attachment_id, true);
            self::redirect_with('err', 'pago_archivo_seguro', $redirect);
        }

        // Insertar registro de pago en la tabla personalizada
        $payment_id = Bankitos_Credit_Payments::insert_payment([
            'request_id'    => $request_id,
            'user_id'       => $user_id,
            'amount'        => $amount,
            'attachment_id' => $attachment_id,
            'status'        => 'pending',
        ]);

        if ($payment_id <= 0) {
            // Si falla guardar el pago, borramos el adjunto
            wp_delete_attachment($attachment_id, true);
            self::redirect_with('err', 'pago_guardar', $redirect);
        }

        self::redirect_with('ok', 'pago_enviado', $redirect);
    }

    public static function filter_upload_mimes($mimes) {
        return array_merge((array) $mimes, self::allowed_mimes());
    }

    public static function approve_payment(): void {
        self::moderate_payment('approved');
    }

    public static function reject_payment(): void {
        self::moderate_payment('rejected');
    }

    public static function get_receipt_download_url(int $payment_id): string {
        if ($payment_id <= 0) {
            return '';
        }
        return wp_nonce_url(add_query_arg([
            'action'     => 'bankitos_credit_payment_download',
            'payment_id' => $payment_id,
        ], admin_url('admin-post.php')), 'bankitos_credit_payment_download_' . $payment_id);
    }
    
    private static function moderate_payment(string $status): void {
        if (!is_user_logged_in()) {
            wp_safe_redirect(site_url('/acceder'));
            exit;
        }
        check_admin_referer('bankitos_credit_payment_mod');
        
        if (!current_user_can('approve_aportes')) {
            self::redirect_with('err', 'pago_permiso', site_url('/creditos'));
        }
        
        $payment_id = isset($_REQUEST['payment_id']) ? absint($_REQUEST['payment_id']) : 0;
        $redirect   = site_url('/creditos');
        
        $payment = Bankitos_Credit_Payments::get_payment($payment_id);
        if (!$payment) {
            self::redirect_with('err', 'pago_invalido', $redirect);
        }
        
        $request = Bankitos_Credit_Requests::get_request((int) $payment['request_id']);
        if (!$request) {
            self::redirect_with('err', 'pago_invalido', $redirect);
        }
        
        $banco_id = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id(get_current_user_id()) : 0;
        if ($banco_id <= 0 || (int) $request['banco_id'] !== $banco_id) {
            self::redirect_with('err', 'pago_permiso', $redirect);
        }
        
        Bankitos_Credit_Payments::update_status($payment_id, $status);
        $code = $status === 'approved' ? 'pago_aprobado' : 'pago_rechazado';
        self::redirect_with('ok', $code, $redirect);
    }

    public static function download_receipt(): void {
        if (!is_user_logged_in()) {
            wp_safe_redirect(site_url('/acceder'));
            exit;
        }
        $payment_id = isset($_GET['payment_id']) ? absint($_GET['payment_id']) : 0;
        $nonce      = isset($_GET['_wpnonce']) ? sanitize_text_field($_GET['_wpnonce']) : '';
        
        if ($payment_id <= 0 || !$nonce || !wp_verify_nonce($nonce, 'bankitos_credit_payment_download_' . $payment_id)) {
            wp_die(__('Solicitud inválida.', 'bankitos'), 400);
        }
        
        $payment = Bankitos_Credit_Payments::get_payment($payment_id);
        if (!$payment) {
            wp_die(__('El comprobante no está disponible.', 'bankitos'), 404);
        }
        
        $request = Bankitos_Credit_Requests::get_request((int) $payment['request_id']);
        if (!$request) {
            wp_die(__('El comprobante no está disponible.', 'bankitos'), 404);
        }
        
        $current_user = wp_get_current_user();
        $is_owner = (int) $current_user->ID === (int) $payment['user_id'];
        $can_manage = current_user_can('approve_aportes') || current_user_can('audit_aportes');
        $same_bank = class_exists('Bankitos_Handlers') && (int) Bankitos_Handlers::get_user_banco_id($current_user->ID) === (int) $request['banco_id'];
        
        if ((!$is_owner && !$can_manage) || !$same_bank) {
            wp_die(__('No tienes permisos para ver este comprobante.', 'bankitos'), 403);
        }
        
        $attachment_id = (int) $payment['attachment_id'];
        if ($attachment_id <= 0 || !class_exists('Bankitos_Secure_Files')) {
            wp_die(__('El comprobante no está disponible.', 'bankitos'), 404);
        }
        
        $path = Bankitos_Secure_Files::get_protected_path($attachment_id);
        if (!$path) {
            wp_die(__('El comprobante no está disponible.', 'bankitos'), 404);
        }
        
        $mime = wp_check_filetype($path);
        $content_type = $mime['type'] ?: (function_exists('mime_content_type') ? mime_content_type($path) : 'application/octet-stream');
        $filename = Bankitos_Secure_Files::get_download_filename($attachment_id);
        
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