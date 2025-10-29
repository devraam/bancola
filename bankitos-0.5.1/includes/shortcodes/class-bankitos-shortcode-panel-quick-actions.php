<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Shortcode_Panel_Quick_Actions extends Bankitos_Shortcode_Panel_Base {

    public static function register(): void {
        self::register_shortcode('bankitos_panel_quick_actions');
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
        <div class="bankitos-panel__quick-actions">
          <p><strong><?php esc_html_e('Acciones rápidas', 'bankitos'); ?>:</strong></p>
          <ul>
            <li><a href="<?php echo esc_url(site_url('/mi-aporte')); ?>"><?php esc_html_e('Subir aporte', 'bankitos'); ?></a></li>
            <?php if (current_user_can('approve_aportes')): ?>
              <li><a href="<?php echo esc_url(site_url('/tesoreria-aportes')); ?>"><?php esc_html_e('Aprobar aportes (Tesorero)', 'bankitos'); ?></a></li>
            <?php endif; ?>
            <?php if (current_user_can('audit_aportes')): ?>
              <li><a href="<?php echo esc_url(site_url('/auditoria-aportes')); ?>"><?php esc_html_e('Aportes aprobados (Veedor)', 'bankitos'); ?></a></li>
            <?php endif; ?>
          </ul>
        </div>
        <?php
        return ob_get_clean();
    }
}