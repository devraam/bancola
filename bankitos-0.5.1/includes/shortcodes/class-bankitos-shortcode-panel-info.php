<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Shortcode_Panel_Info extends Bankitos_Shortcode_Panel_Base {

    public static function register(): void {
        self::register_shortcode('bankitos_panel_info');
    }

    public static function render($atts = [], $content = null): string {
        if (!is_user_logged_in()) {
            return '';
        }
        $context = self::get_panel_context();
        if ($context['banco_id'] <= 0) {
            return '';
        }
        return self::render_section($context);
    }

    public static function render_section(array $context): string {
        ob_start(); ?>
        <div class="bankitos-panel-info" role="group" aria-label="<?php esc_attr_e('Información general del b@nko', 'bankitos'); ?>">
          <div class="bankitos-panel-info__header">
            <span class="bankitos-panel-info__icon" aria-hidden="true">🏦</span>
            <div>
              <p class="bankitos-panel-info__title"><?php esc_html_e('Información del b@nko', 'bankitos'); ?></p>
              <p class="bankitos-panel-info__subtitle"><?php esc_html_e('Resumen visible para todos los asociados.', 'bankitos'); ?></p>
            </div>
          </div>
          <dl class="bankitos-panel-info__grid">
            <div class="bankitos-panel-info__row">
              <dt class="bankitos-panel-info__label"><?php esc_html_e('Nombre del b@nko', 'bankitos'); ?></dt>
              <dd class="bankitos-panel-info__value"><?php echo esc_html($context['banco_title']); ?></dd>
            </div>
            <div class="bankitos-panel-info__row">
              <dt class="bankitos-panel-info__label"><?php esc_html_e('Rol', 'bankitos'); ?></dt>
              <dd class="bankitos-panel-info__value"><?php echo esc_html($context['role_label']); ?></dd>
            </div>
            <div class="bankitos-panel-info__row">
              <dt class="bankitos-panel-info__label"><?php esc_html_e('Cuota', 'bankitos'); ?></dt>
              <dd class="bankitos-panel-info__value"><?php echo esc_html($context['cuota_text']); ?></dd>
            </div>
            <div class="bankitos-panel-info__row">
              <dt class="bankitos-panel-info__label"><?php esc_html_e('Tasa', 'bankitos'); ?></dt>
              <dd class="bankitos-panel-info__value"><?php echo esc_html($context['tasa_text']); ?></dd>
            </div>
            <div class="bankitos-panel-info__row">
              <dt class="bankitos-panel-info__label"><?php esc_html_e('Duración', 'bankitos'); ?></dt>
              <dd class="bankitos-panel-info__value"><?php echo esc_html($context['duracion_text']); ?></dd>
            </div>
            <div class="bankitos-panel-info__row">
              <dt class="bankitos-panel-info__label"><?php esc_html_e('Ahorros totales', 'bankitos'); ?></dt>
              <dd class="bankitos-panel-info__value"><?php echo esc_html($context['ahorros_text']); ?></dd>
            </div>
            <div class="bankitos-panel-info__row">
              <dt class="bankitos-panel-info__label"><?php esc_html_e('Créditos', 'bankitos'); ?></dt>
              <dd class="bankitos-panel-info__value"><?php echo esc_html($context['creditos_text']); ?></dd>
            </div>
            <div class="bankitos-panel-info__row">
              <dt class="bankitos-panel-info__label"><?php esc_html_e('Disponible', 'bankitos'); ?></dt>
              <dd class="bankitos-panel-info__value"><?php echo esc_html($context['disponible_text']); ?></dd>
            </div>
          </dl>
        </div>
        <?php
        return ob_get_clean();
    }
}