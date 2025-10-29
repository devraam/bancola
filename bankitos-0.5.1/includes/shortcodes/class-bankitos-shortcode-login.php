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

        ob_start(); ?>
        <div class="bankitos-login-wrap">
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
          <p class="bankitos-register-link" style="margin-top:1rem"><?php esc_html_e('¿No tienes cuenta?', 'bankitos'); ?> <a href="<?php echo esc_url(site_url('/registrarse')); ?><?php if ($token) echo '?invite_token=' . rawurlencode($token); ?>"><?php esc_html_e('Regístrate', 'bankitos'); ?></a></p>
        </div>
        <?php
        return ob_get_clean();
    }
}
