<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Shortcode_Aporte_Form extends Bankitos_Shortcode_Base {

    public static function register(): void {
        self::register_shortcode('bankitos_aporte_form');
    }

    /**
     * @param array|string $atts
     * @param string|null $content
     */
    public static function render($atts = [], $content = null): string {
        if (!is_user_logged_in()) {
            return '<div class="bankitos-form bankitos-panel"><p>' . esc_html__('Inicia sesión para subir tu aporte.', 'bankitos') . '</p></div>';
        }
        $user_id = get_current_user_id();
        $banco_id = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($user_id) : 0;
        if ($banco_id <= 0) {
            return '<div class="bankitos-form bankitos-panel"><p>' . esc_html__('No perteneces a un B@nko.', 'bankitos') . '</p></div>';
        }

        if (!current_user_can('submit_aportes')) {
            return '<div class="bankitos-form bankitos-panel"><p>' . esc_html__('No tienes permiso para enviar aportes.', 'bankitos') . '</p></div>';
        }

        ob_start(); ?>
        <div class="bankitos-form bankitos-panel">
          <h3><?php esc_html_e('Subir aporte', 'bankitos'); ?></h3>
          <?php echo self::top_notice_from_query(); ?>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
            <?php echo wp_nonce_field('bankitos_aporte_submit', '_wpnonce', true, false); ?>
            <input type="hidden" name="action" value="bankitos_aporte_submit">
            <div class="bankitos-field">
              <label for="bk_monto"><?php esc_html_e('Monto del aporte', 'bankitos'); ?></label>
              <input id="bk_monto" type="number" name="monto" step="0.01" min="1" required>
            </div>
            <div class="bankitos-field">
              <label for="bk_comp"><?php esc_html_e('Comprobante (imagen o PDF, máximo 1MB)', 'bankitos'); ?></label>
              <input id="bk_comp" type="file" name="comprobante" accept=".jpg,.jpeg,.png,.pdf,image/*" capture="environment" required>
            </div>
            <div class="bankitos-actions">
              <button type="submit" class="bankitos-btn"><?php esc_html_e('Registrar aporte', 'bankitos'); ?></button>
            </div>
          </form>
        </div>
        <?php
        return ob_get_clean();
    }
}