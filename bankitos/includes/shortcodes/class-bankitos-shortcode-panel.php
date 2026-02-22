<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Shortcode_Panel extends Bankitos_Shortcode_Panel_Base {

    public static function register(): void {
        self::register_shortcode('bankitos_panel');
    }

    public static function render($atts = [], $content = null): string {
        $context = self::get_panel_context();
        $name    = $context['name'];

        $welcome_message = $context['banco_id'] > 0
            ? sprintf(
                esc_html__('Gestiona la información y los miembros de tu B@nko %s desde los módulos disponibles en esta página.', 'bankitos'),
                esc_html($context['banco_title'])
            )
            : esc_html__('Aún no perteneces a un B@nko. Puedes crear uno nuevo o esperar a recibir una invitación.', 'bankitos');

        $can_create_bank = $context['banco_id'] <= 0 && !empty($context['is_general_member']);

        ob_start(); ?>
        <div class="bankitos-panel bienvenida">
          <?php echo self::top_notice_from_query(); ?>
          <h2><?php echo sprintf(esc_html__('Hol@ %s', 'bankitos'), esc_html($name)); ?></h2>
          <p class="bankitos-panel__message"><?php echo $welcome_message; ?></p>
          <?php if ($can_create_bank): ?>
            <div class="bankitos-panel__cta">
              <a class="button bankitos-btn" href="<?php echo esc_url(site_url('/crear-banko')); ?>"><?php esc_html_e('Crear B@nko', 'bankitos'); ?></a>
            </div>
          <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    protected static function render_for_guest($atts = [], $content = null): string {
        ob_start(); ?>
        <div class="bankitos-panel">
          <?php echo self::top_notice_from_query(); ?>
          <p><?php echo esc_html__('Debes iniciar sesión para ver tu panel.', 'bankitos'); ?> <a href="<?php echo esc_url(site_url('/acceder')); ?>"><?php esc_html_e('Acceder', 'bankitos'); ?></a></p>
        </div>
        <?php
        return ob_get_clean();
    }
}