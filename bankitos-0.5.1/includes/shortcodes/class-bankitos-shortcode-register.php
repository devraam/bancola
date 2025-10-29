<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Shortcode_Register extends Bankitos_Shortcode_Base {

    public static function register(): void {
        self::register_shortcode('bankitos_register');
    }

    public static function render($atts = [], $content = null): string {
        if (is_user_logged_in()) {
            wp_safe_redirect(site_url('/panel'));
            exit;
        }

        $atts = is_array($atts) ? $atts : [];
        $token = isset($atts['invite_token']) ? sanitize_text_field($atts['invite_token']) : '';
        if (!$token && isset($_GET['invite_token'])) {
            $token = sanitize_text_field(wp_unslash($_GET['invite_token']));
        }

        ob_start(); ?>
        <div class="bankitos-register-wrap">
          <h2><?php esc_html_e('Crear cuenta', 'bankitos'); ?></h2>
          <?php $register_recaptcha = class_exists('Bankitos_Recaptcha') ? Bankitos_Recaptcha::field('register') : ''; ?>
          <form class="bankitos-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"<?php if ($register_recaptcha) echo ' data-bankitos-recaptcha="register"'; ?>>
            <?php echo wp_nonce_field('bankitos_do_register', '_wpnonce', true, false); ?>
            <input type="hidden" name="action" value="bankitos_do_register">
            <?php if ($token): ?>
              <input type="hidden" name="invite_token" value="<?php echo esc_attr($token); ?>">
            <?php endif; ?>
            <?php echo $register_recaptcha; ?>
            <div class="bankitos-field">
              <label for="bk_reg_name"><?php esc_html_e('Nombre', 'bankitos'); ?></label>
              <input id="bk_reg_name" type="text" name="name" required autocomplete="name">
            </div>
            <div class="bankitos-field">
              <label for="bk_reg_email"><?php esc_html_e('Email', 'bankitos'); ?></label>
              <input id="bk_reg_email" type="email" name="email" required autocomplete="email"<?php if ($token && isset($_GET['email'])) echo ' value="' . esc_attr(sanitize_email($_GET['email'])) . '"'; ?>>
            </div>
            <div class="bankitos-field">
              <label for="bk_reg_pass"><?php esc_html_e('Contraseña', 'bankitos'); ?></label>
              <input id="bk_reg_pass" type="password" name="password" required autocomplete="new-password">
            </div>
            <div class="bankitos-actions"><button type="submit" class="bankitos-btn"><?php esc_html_e('Registrarme', 'bankitos'); ?></button></div>
          </form>
          <p style="margin-top:1rem"><?php esc_html_e('¿Ya tienes cuenta?', 'bankitos'); ?> <a href="<?php echo esc_url(site_url('/acceder')); ?><?php if ($token) echo '?invite_token=' . rawurlencode($token); ?>"><?php esc_html_e('Inicia sesión', 'bankitos'); ?></a></p>
        </div>
        <?php
        return ob_get_clean();
    }
}