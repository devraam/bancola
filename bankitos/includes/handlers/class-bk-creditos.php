<?php
if (!defined('ABSPATH')) exit;

class BK_Creditos_Handler {

    public static function init(): void {
        add_action('admin_post_bankitos_credito_solicitar', [__CLASS__, 'submit_request']);
        add_action('admin_post_bankitos_credito_resolver', [__CLASS__, 'resolve_request']);
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

    public static function submit_request(): void {
        if (!is_user_logged_in()) {
            wp_safe_redirect(site_url('/acceder'));
            exit;
        }
        check_admin_referer('bankitos_credito_solicitar');
        $user_id  = get_current_user_id();
        $banco_id = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($user_id) : 0;
        if ($banco_id <= 0) {
            self::redirect_with('err', 'no_banco', site_url('/panel'));
        }
        $documento = isset($_POST['documento']) ? sanitize_text_field(wp_unslash($_POST['documento'])) : '';
        $edad      = isset($_POST['edad']) ? absint($_POST['edad']) : 0;
        $telefono  = isset($_POST['telefono']) ? sanitize_text_field(wp_unslash($_POST['telefono'])) : '';
        $tipo      = isset($_POST['tipo_credito']) ? sanitize_key($_POST['tipo_credito']) : '';
        $monto     = isset($_POST['monto']) ? floatval(wp_unslash($_POST['monto'])) : 0.0;
        $plazo     = isset($_POST['plazo']) ? absint($_POST['plazo']) : 0;
        $descripcion = isset($_POST['descripcion']) ? sanitize_textarea_field(wp_unslash($_POST['descripcion'])) : '';
        $firma       = !empty($_POST['firma']);
        $tipos       = Bankitos_Credit_Requests::get_credit_types();
        if (!$documento || $edad <= 0 || !$telefono || !isset($tipos[$tipo]) || $plazo <= 0 || !$descripcion) {
            self::redirect_with('err', 'credito_datos', site_url('/panel'));
        }
        if (!$firma) {
            self::redirect_with('err', 'credito_firma', site_url('/panel'));
        }
        $terminos = Bankitos_Credit_Requests::get_term_options();
        if (!in_array($plazo, $terminos, true)) {
            self::redirect_with('err', 'credito_datos', site_url('/panel'));
        }
        $totals  = Bankitos_Shortcode_Base::get_banco_financial_totals($banco_id);
        $savings = Bankitos_Credit_Requests::get_user_savings_total($user_id, $banco_id);
        $max     = Bankitos_Credit_Requests::get_max_amount($user_id, $banco_id, $totals);
        if ($max <= 0) {
            self::redirect_with('err', 'credito_sin_fondos', site_url('/panel'));
        }
        if ($monto <= 0) {
            self::redirect_with('err', 'credito_monto', site_url('/panel'));
        }
        if ($monto > $max) {
            self::redirect_with('err', 'credito_limite', site_url('/panel'));
        }
        $request_id = Bankitos_Credit_Requests::insert_request([
            'banco_id'                => $banco_id,
            'user_id'                 => $user_id,
            'request_date'            => current_time('mysql'),
            'document_id'             => $documento,
            'age'                     => $edad,
            'phone'                   => $telefono,
            'credit_type'             => $tipo,
            'amount'                  => $monto,
            'savings_snapshot'        => $savings,
            'bank_available_snapshot' => isset($totals['disponible']) ? (float) $totals['disponible'] : 0.0,
            'term_months'             => $plazo,
            'description'             => $descripcion,
            'signature'               => $firma ? 1 : 0,
        ]);
        if ($request_id <= 0) {
            self::redirect_with('err', 'credito_guardar', site_url('/panel'));
        }
        self::redirect_with('ok', 'credito_solicitado', site_url('/panel'));
    }

    public static function resolve_request(): void {
        if (!is_user_logged_in()) {
            wp_safe_redirect(site_url('/acceder'));
            exit;
        }
        $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
        if ($request_id <= 0) {
            self::redirect_with('err', 'credito_decision', site_url('/panel'));
        }
        check_admin_referer('bankitos_credito_resolver_' . $request_id);
        $user_id  = get_current_user_id();
        $banco_id = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($user_id) : 0;
        if ($banco_id <= 0 || !Bankitos_Credit_Requests::user_can_review($user_id)) {
            self::redirect_with('err', 'credito_permiso', site_url('/panel'));
        }
        $request = Bankitos_Credit_Requests::get_request($request_id);
        if (!$request || (int) $request['banco_id'] !== $banco_id) {
            self::redirect_with('err', 'credito_permiso', site_url('/panel'));
        }
        $decision = isset($_POST['decision']) ? sanitize_key($_POST['decision']) : '';
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            self::redirect_with('err', 'credito_decision', site_url('/panel'));
        }
        $notes    = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '';
        $result = Bankitos_Credit_Requests::record_approval(
            $request_id,
            Bankitos_Credit_Requests::get_user_role_key($user_id),
            $decision,
            $notes
        );
        if (is_wp_error($result)) {
            self::redirect_with('err', 'credito_decision', site_url('/panel'));
        }
        self::redirect_with('ok', 'credito_actualizado', site_url('/panel'));
    }
}