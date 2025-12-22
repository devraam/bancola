<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Domains {

    const OPTION_KEY = 'bankitos_allowed_domains';
    const PAGE_SLUG  = 'bankitos-domains';
    const CAPABILITY = 'manage_bankitos_domains';
    const ACTION_SAVE = 'bankitos_save_domains';

    private static function can_manage_domains(): bool {
        return current_user_can(self::CAPABILITY) || current_user_can('manage_options');
    }

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_post_' . self::ACTION_SAVE, [__CLASS__, 'handle_save']);
    }

    public static function register_menu(): void {
        $parent = class_exists('Bankitos_Admin_Reports') ? Bankitos_Admin_Reports::PAGE_SLUG : 'options-general.php';

        add_submenu_page(
            $parent,
            __('Dominios permitidos', 'bankitos'),
            __('Dominios permitidos', 'bankitos'),
            self::CAPABILITY,
            self::PAGE_SLUG,
            [__CLASS__, 'render_page']
        );
    }

    public static function render_page(): void {
        if (!self::can_manage_domains()) {
            wp_die(__('No tienes permisos para gestionar dominios.', 'bankitos'));
        }

        $domains = self::get_allowed_domains();
        $edit_domain = '';
        $page_url = add_query_arg(['page' => self::PAGE_SLUG], admin_url('admin.php'));
        $form_url = admin_url('admin-post.php');
        if (isset($_GET['domain'])) {
            $requested = sanitize_text_field(wp_unslash($_GET['domain']));
            if (in_array($requested, $domains, true)) {
                $edit_domain = $requested;
            }
        }

        $notice = isset($_GET['bk_notice']) ? sanitize_key($_GET['bk_notice']) : '';
        $error  = isset($_GET['bk_error']) ? sanitize_key($_GET['bk_error']) : '';
        $notice_message = $notice ? self::get_notice_message($notice) : '';
        $error_message = $error ? self::get_error_message($error) : '';
        $nonce_action = $edit_domain ? self::ACTION_SAVE . '_edit' : self::ACTION_SAVE . '_add';

        $view = BANKITOS_PATH . 'includes/views/admin-domains.php';
        if (file_exists($view)) {
            include $view;
            return;
        }

        // Fallback rendering if the view file is missing.
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Dominios autorizados', 'bankitos'); ?></h1>
            <p><?php esc_html_e('No se pudo cargar la plantilla de la vista.', 'bankitos'); ?></p>
        </div>
        <?php
    }

    public static function handle_save(): void {
        if (!self::can_manage_domains()) {
            wp_die(__('No tienes permisos para gestionar dominios.', 'bankitos'));
        }

        $operation = isset($_POST['domain_action']) ? sanitize_key(wp_unslash($_POST['domain_action'])) : 'add';
        $raw_domain = isset($_POST['bankitos_domain']) ? wp_unslash($_POST['bankitos_domain']) : '';

        if ($operation === 'delete') {
            $nonce_action = self::ACTION_SAVE . '_delete_' . sanitize_text_field($raw_domain);
            check_admin_referer($nonce_action);
        } else {
            $nonce_action = $operation === 'edit' ? self::ACTION_SAVE . '_edit' : self::ACTION_SAVE . '_add';
            check_admin_referer($nonce_action);
        }

        $domains = self::get_allowed_domains();

        if ($operation === 'delete') {
            $target = self::sanitize_single_domain($raw_domain);
            if (!$target) {
                self::redirect_with_error('invalid');
            }
            $domains = array_values(array_diff($domains, [$target]));
            update_option(self::OPTION_KEY, $domains, false);
            self::redirect_with_notice('deleted');
        }

        $domain = self::sanitize_single_domain($raw_domain);
        if (!$domain) {
            self::redirect_with_error('invalid');
        }

        if ($operation === 'edit') {
            $original = isset($_POST['original_domain']) ? sanitize_text_field(wp_unslash($_POST['original_domain'])) : '';
            if (!$original || !in_array($original, $domains, true)) {
                self::redirect_with_error('missing');
            }
            $domains = array_map(function ($item) use ($original, $domain) {
                return $item === $original ? $domain : $item;
            }, $domains);
            $domains = array_values(array_unique($domains));
            update_option(self::OPTION_KEY, $domains, false);
            self::redirect_with_notice('updated');
        }

        if (in_array($domain, $domains, true)) {
            self::redirect_with_notice('exists');
        }

        $domains[] = $domain;

        update_option(self::OPTION_KEY, $domains, false);

        self::redirect_with_notice('added');
    }

    public static function get_allowed_domains(): array {
        $domains = get_option(self::OPTION_KEY, []);
        if (!is_array($domains)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('strtolower', $domains))));
    }

    public static function is_email_allowed(string $email): bool {
        $domains = self::get_allowed_domains();
        if (!$domains) {
            return true;
        }
        $email = sanitize_email($email);
        if (!$email || strpos($email, '@') === false) {
            return false;
        }
        $parts = explode('@', $email);
        $domain = strtolower(array_pop($parts));
        return in_array($domain, $domains, true);
    }

    private static function sanitize_domains($raw): array {
        if (is_array($raw)) {
            $raw = implode("\n", $raw);
        }
        $lines = preg_split('/[\r\n,]+/', (string) $raw);
        $clean = [];
        foreach ($lines as $line) {
            $line = strtolower(trim($line));
            if ($line === '') {
                continue;
            }
            if (!preg_match('/^[a-z0-9.-]+\\.[a-z]{2,}$/i', $line)) {
                continue;
            }
            $clean[] = $line;
        }
        return array_values(array_unique($clean));
    }

    private static function sanitize_single_domain($raw): string {
        $domains = self::sanitize_domains($raw);
        return $domains[0] ?? '';
    }

    private static function redirect_with_notice(string $code): void {
        $url = add_query_arg(['page' => self::PAGE_SLUG, 'bk_notice' => $code], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    private static function redirect_with_error(string $code): void {
        $url = add_query_arg(['page' => self::PAGE_SLUG, 'bk_error' => $code], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    private static function get_notice_message(string $code): string {
        switch ($code) {
            case 'added':
                return __('Dominio agregado correctamente.', 'bankitos');
            case 'updated':
                return __('Dominio actualizado.', 'bankitos');
            case 'deleted':
                return __('Dominio eliminado.', 'bankitos');
            case 'exists':
                return __('El dominio ya estaba en la lista.', 'bankitos');
            default:
                return __('Cambios guardados.', 'bankitos');
        }
    }

    private static function get_error_message(string $code): string {
        switch ($code) {
            case 'invalid':
                return __('Ingresa un dominio válido (ejemplo.com).', 'bankitos');
            case 'missing':
                return __('No se encontró el dominio que intentas editar.', 'bankitos');
            default:
                return __('No se pudo completar la acción.', 'bankitos');
        }
    }
}