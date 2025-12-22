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
        $edit_domain = '';
        if (isset($_GET['domain'])) {
            $requested = sanitize_text_field(wp_unslash($_GET['domain']));
            if (in_array($requested, $domains, true)) {
                $edit_domain = $requested;
            }
        }

        $notice = isset($_GET['bk_notice']) ? sanitize_key($_GET['bk_notice']) : '';
        $error  = isset($_GET['bk_error']) ? sanitize_key($_GET['bk_error']) : '';
        ?>
        <div class="wrap bankitos-wrap">
            <h1><?php esc_html_e('Dominios autorizados', 'bankitos'); ?></h1>
            <p class="description"><?php esc_html_e('Solo se permitirá registrar usuarios o enviar invitaciones a correos dentro de estos dominios. Si la lista está vacía, no se aplicará restricción.', 'bankitos'); ?></p>
            
            <?php if ($notice): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html(self::get_notice_message($notice)); ?></p>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html(self::get_error_message($error)); ?></p>
                </div>
            <?php endif; ?>

            <div class="bankitos-domains-grid" style="display:grid;grid-template-columns: 1fr 1.2fr;gap:24px;align-items:start;margin-top:1rem;">
                <div class="card" style="background:#fff;border:1px solid #ccd0d4;padding:16px;border-radius:4px;">
                    <h2 style="margin-top:0;"><?php echo $edit_domain ? esc_html__('Editar dominio', 'bankitos') : esc_html__('Agregar dominio', 'bankitos'); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php
                        $nonce_action = $edit_domain ? self::ACTION_SAVE . '_edit' : self::ACTION_SAVE . '_add';
                        wp_nonce_field($nonce_action);
                        ?>
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_SAVE); ?>">
                        <input type="hidden" name="domain_action" value="<?php echo $edit_domain ? 'edit' : 'add'; ?>">
                        <?php if ($edit_domain): ?>
                            <input type="hidden" name="original_domain" value="<?php echo esc_attr($edit_domain); ?>">
                        <?php endif; ?>
                        <p>
                            <label for="bankitos-domain"><strong><?php esc_html_e('Dominio', 'bankitos'); ?></strong></label><br>
                            <input type="text" id="bankitos-domain" name="bankitos_domain" class="regular-text" placeholder="midominio.com" value="<?php echo esc_attr($edit_domain); ?>">
                        </p>
                        <p class="description"><?php esc_html_e('Usa solo el dominio, sin @ ni nombres de usuario. Ejemplo: ejemplo.com', 'bankitos'); ?></p>
                        <?php submit_button($edit_domain ? __('Actualizar dominio', 'bankitos') : __('Agregar dominio', 'bankitos')); ?>
                        <?php if ($edit_domain): ?>
                            <a class="button-link" href="<?php echo esc_url(add_query_arg(['page' => self::PAGE_SLUG], admin_url('admin.php'))); ?>"><?php esc_html_e('Cancelar edición', 'bankitos'); ?></a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="card" style="background:#fff;border:1px solid #ccd0d4;padding:16px;border-radius:4px;">
                    <h2 style="margin-top:0;"><?php esc_html_e('Dominios permitidos', 'bankitos'); ?></h2>
                    <?php if (empty($domains)): ?>
                        <p><?php esc_html_e('Aún no hay dominios configurados.', 'bankitos'); ?></p>
                    <?php else: ?>
                        <table class="widefat striped" style="max-width:100%;">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Dominio', 'bankitos'); ?></th>
                                    <th style="width:160px;"><?php esc_html_e('Acciones', 'bankitos'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($domains as $domain): ?>
                                    <tr>
                                        <td><?php echo esc_html($domain); ?></td>
                                        <td>
                                            <a href="<?php echo esc_url(add_query_arg(['page' => self::PAGE_SLUG, 'domain' => $domain], admin_url('admin.php'))); ?>">
                                                <?php esc_html_e('Editar', 'bankitos'); ?>
                                            </a>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-left:12px;">
                                                <?php
                                                $delete_action = self::ACTION_SAVE . '_delete_' . $domain;
                                                wp_nonce_field($delete_action);
                                                ?>
                                                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_SAVE); ?>">
                                                <input type="hidden" name="domain_action" value="delete">
                                                <input type="hidden" name="bankitos_domain" value="<?php echo esc_attr($domain); ?>">
                                                <button type="submit" class="button-link delete-domain" onclick="return confirm('<?php echo esc_js(__('¿Eliminar este dominio?', 'bankitos')); ?>');">
                                                    <?php esc_html_e('Eliminar', 'bankitos'); ?>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    public static function handle_save(): void {
        if (!current_user_can(self::CAPABILITY)) {
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