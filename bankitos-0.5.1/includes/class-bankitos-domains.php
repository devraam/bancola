<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Domains {

    const OPTION_KEY = 'bankitos_allowed_domains';
    const PAGE_SLUG  = 'bankitos-domains';
    const CAPABILITY = 'manage_bankitos_domains';
    const ACTION_SAVE = 'bankitos_save_domains';

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
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(__('No tienes permisos para gestionar dominios.', 'bankitos'));
        }

        $domains = self::get_allowed_domains();
        ?>
        <div class="wrap bankitos-wrap">
            <h1><?php esc_html_e('Dominios autorizados', 'bankitos'); ?></h1>
            <p class="description"><?php esc_html_e('Solo se permitirá registrar usuarios o enviar invitaciones a correos dentro de estos dominios. Si la lista está vacía, no se aplicará restricción.', 'bankitos'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:600px;margin-top:1rem;">
                <?php wp_nonce_field(self::ACTION_SAVE); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_SAVE); ?>">
                <p>
                    <label for="bankitos-domains"><strong><?php esc_html_e('Dominios permitidos (uno por línea)', 'bankitos'); ?></strong></label><br>
                    <textarea id="bankitos-domains" name="bankitos_domains" rows="8" class="large-text code" placeholder="midominio.com&#10;otrodominio.org"><?php echo esc_textarea(implode("\n", $domains)); ?></textarea>
                </p>
                <p class="description"><?php esc_html_e('Usa solo el dominio, sin @ ni nombres de usuario. Ejemplo: ejemplo.com', 'bankitos'); ?></p>
                <?php submit_button(__('Guardar dominios', 'bankitos')); ?>
            </form>
        </div>
        <?php
    }

    public static function handle_save(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(__('No tienes permisos para gestionar dominios.', 'bankitos'));
        }
        check_admin_referer(self::ACTION_SAVE);

        $raw      = isset($_POST['bankitos_domains']) ? wp_unslash($_POST['bankitos_domains']) : '';
        $domains  = self::sanitize_domains($raw);

        update_option(self::OPTION_KEY, $domains, false);

        wp_safe_redirect(add_query_arg('updated', 1, admin_url('admin.php?page=' . self::PAGE_SLUG)));
        exit;
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
}