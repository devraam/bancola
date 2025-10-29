<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Shortcode_Panel extends Bankitos_Shortcode_Panel_Base {

    public static function register(): void {
        self::register_shortcode('bankitos_panel');
    }

    public static function render($atts = [], $content = null): string {
        if (!is_user_logged_in()) {
            return '<div class="bankitos-panel"><p>' . esc_html__('Debes iniciar sesión para ver tu panel.', 'bankitos') . ' <a href="' . esc_url(site_url('/acceder')) . '">' . esc_html__('Acceder', 'bankitos') . '</a></p></div>';
        }

        $context = self::get_panel_context();
        $name    = $context['name'];

        ob_start(); ?>
        <div class="bankitos-panel">
          <?php echo self::top_notice_from_query(); ?>
          <h2><?php echo sprintf(esc_html__('Bienvenido, %s', 'bankitos'), esc_html($name)); ?></h2>
          <?php if ($context['banco_id'] > 0): ?>
            <div class="bankitos-panel__grid">
              <div class="bankitos-panel__col bankitos-panel__col--info">
                <?php echo Bankitos_Shortcode_Panel_Info::render_section($context); ?>
              </div>
              <div class="bankitos-panel__col bankitos-panel__col--members">
                <?php echo Bankitos_Shortcode_Panel_Members::render_section($context); ?>
              </div>
            </div>
            <div class="bankitos-panel__cta">
              <a class="button bankitos-btn" href="<?php echo esc_url($context['banco_link']); ?>"><?php esc_html_e('Ver ficha del B@nko', 'bankitos'); ?></a>
            </div>
            <?php echo Bankitos_Shortcode_Panel_Quick_Actions::render_section($context); ?>
          <?php else: ?>
            <p><?php esc_html_e('Aún no perteneces a un B@nko.', 'bankitos'); ?></p>
            <p><a class="button bankitos-btn" href="<?php echo esc_url(site_url('/crear-banko')); ?>"><?php esc_html_e('Crear B@nko', 'bankitos'); ?></a></p>
          <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}