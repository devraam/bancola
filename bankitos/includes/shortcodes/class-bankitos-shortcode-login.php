<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Shortcode_Login extends Bankitos_Shortcode_Base {

    public static function register(): void {
        self::register_shortcode('bankitos_login');
    }

    public static function render($atts = [], $content = null): string {
        $atts = is_array($atts) ? $atts : [];
        $token = isset($atts['invite_token']) ? sanitize_text_field($atts['invite_token']) : '';
        if (!$token && isset($_GET['invite_token'])) {
            $token = sanitize_text_field(wp_unslash($_GET['invite_token']));
        }
        $mode = isset($_GET['mode']) ? sanitize_text_field(wp_unslash($_GET['mode'])) : '';
        $reset_login = isset($_GET['login']) ? sanitize_text_field(wp_unslash($_GET['login'])) : '';
        $reset_key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
        $notice = '';
        if (isset($_GET['ok'])) {
            $ok = sanitize_text_field(wp_unslash($_GET['ok']));
            if ($ok === 'recovery_sent') {
                $notice = __('Si el correo está registrado, te enviamos un enlace para crear una nueva contraseña.', 'bankitos');
            } elseif ($ok === 'password_reset') {
                $notice = __('Tu contraseña fue actualizada. Ya puedes iniciar sesión.', 'bankitos');
                $mode = '';
            }
        }
        if (isset($_GET['err'])) {
            $err = sanitize_text_field(wp_unslash($_GET['err']));
            if ($err === 'invalid_reset') {
                $notice = __('El enlace de recuperación es inválido o expiró. Solicita uno nuevo.', 'bankitos');
                $mode = 'recover';
            } elseif ($err === 'reset_password') {
                $notice = __('Debes ingresar una contraseña válida y confirmarla.', 'bankitos');
            } elseif ($err === 'recovery') {
                $notice = __('Ingresa un correo válido para recuperar tu contraseña.', 'bankitos');
            }
        }

        if (class_exists('Bankitos_Recaptcha') && !Bankitos_Recaptcha::is_enabled()) {
            return '<div class="bankitos-form bankitos-panel"><p>' . esc_html__('El acceso está temporalmente deshabilitado hasta que el administrador configure reCAPTCHA.', 'bankitos') . '</p></div>';
        }
        
        ob_start(); ?>
        <div class="bankitos-login-wrap">
          <?php if ($notice): ?>
            <p class="bankitos-panel__message"><?php echo esc_html($notice); ?></p>
          <?php endif; ?>
          <?php if ($mode === 'recover'): ?>
            <h2><?php esc_html_e('Recuperar contraseña', 'bankitos'); ?></h2>
            <form class="bankitos-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
              <?php echo wp_nonce_field('bankitos_do_recover', '_wpnonce', true, false); ?>
              <input type="hidden" name="action" value="bankitos_do_recover">
              <div class="bankitos-field">
                <label for="bk_recover_email"><?php esc_html_e('Email', 'bankitos'); ?></label>
                <input id="bk_recover_email" type="email" name="email" required autocomplete="email">
              </div>
              <div class="bankitos-actions"><button type="submit" class="bankitos-btn"><?php esc_html_e('Enviar enlace', 'bankitos'); ?></button></div>
            </form>
            <p class="bankitos-register-link" style="margin-top:1rem"><a href="<?php echo esc_url(site_url('/acceder')); ?>"><?php esc_html_e('Volver a iniciar sesión', 'bankitos'); ?></a></p>
          <?php elseif ($mode === 'reset' && $reset_key && $reset_login): ?>
            <h2><?php esc_html_e('Crear nueva contraseña', 'bankitos'); ?></h2>
            <form class="bankitos-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
              <?php echo wp_nonce_field('bankitos_do_reset_password', '_wpnonce', true, false); ?>
              <input type="hidden" name="action" value="bankitos_do_reset_password">
              <input type="hidden" name="login" value="<?php echo esc_attr($reset_login); ?>">
              <input type="hidden" name="key" value="<?php echo esc_attr($reset_key); ?>">
              <div class="bankitos-field">
                <label for="bk_reset_pass"><?php esc_html_e('Nueva contraseña', 'bankitos'); ?></label>
                <input id="bk_reset_pass" type="password" name="password" required autocomplete="new-password">
              </div>
              <div class="bankitos-field">
                <label for="bk_reset_pass_confirm"><?php esc_html_e('Confirmar contraseña', 'bankitos'); ?></label>
                <input id="bk_reset_pass_confirm" type="password" name="password_confirm" required autocomplete="new-password">
              </div>
              <div class="bankitos-actions"><button type="submit" class="bankitos-btn"><?php esc_html_e('Actualizar contraseña', 'bankitos'); ?></button></div>
            </form>
            <p class="bankitos-register-link" style="margin-top:1rem"><a href="<?php echo esc_url(site_url('/acceder')); ?>"><?php esc_html_e('Volver a iniciar sesión', 'bankitos'); ?></a></p>
          <?php else: ?>
            <h2><?php esc_html_e('Iniciar sesión', 'bankitos'); ?></h2>
            <?php $recaptcha_field = class_exists('Bankitos_Recaptcha') ? Bankitos_Recaptcha::field('login') : ''; ?>
            <form class="bankitos-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"<?php if ($recaptcha_field) echo ' data-bankitos-recaptcha="login"'; ?>>
              <?php echo wp_nonce_field('bankitos_do_login', '_wpnonce', true, false); ?>
              <input type="hidden" name="action" value="bankitos_do_login">
              <?php if ($token): ?>
                <input type="hidden" name="invite_token" value="<?php echo esc_attr($token); ?>">
              <?php endif; ?>
              <?php echo $recaptcha_field; ?>
              <div class="bankitos-field">
                <label for="bk_login_email"><?php esc_html_e('Email', 'bankitos'); ?></label>
                <input id="bk_login_email" type="email" name="email" required autocomplete="email">
              </div>
              <div class="bankitos-field">
                <label for="bk_login_pass"><?php esc_html_e('Contraseña', 'bankitos'); ?></label>
                <input id="bk_login_pass" type="password" name="password" required autocomplete="current-password">
              </div>
              <div class="bankitos-field-inline">
                <label><input type="checkbox" name="remember" value="1"> <?php esc_html_e('Recordarme', 'bankitos'); ?></label>
              </div>
              <div class="bankitos-actions"><button type="submit" class="bankitos-btn"><?php esc_html_e('Ingresar', 'bankitos'); ?></button></div>
            </form>
            <p class="bankitos-register-link" style="margin-top:1rem">
              <a href="<?php echo esc_url(add_query_arg('mode', 'recover', site_url('/acceder'))); ?>"><?php esc_html_e('¿Olvidaste tu contraseña?', 'bankitos'); ?></a>
            </p>
            <p class="bankitos-register-link" style="margin-top:0.5rem"><?php esc_html_e('¿No tienes cuenta?', 'bankitos'); ?> <a href="<?php echo esc_url(site_url('/registrarse')); ?><?php if ($token) echo '?invite_token=' . rawurlencode($token); ?>"><?php esc_html_e('Regístrate', 'bankitos'); ?></a></p>
          <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    protected static function should_render_for_current_user($atts = [], $content = null): bool {
        return true;
    }
}
