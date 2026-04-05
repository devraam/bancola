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
        $user_role = get_user_meta(get_current_user_id(), 'bankitos_rol', true) ?: 'socio_general';
        ob_start(); ?>
        <div class="bankitos-panel__quick-actions">
          <?php echo self::top_notice_from_query(); ?>
          <p><strong><?php esc_html_e('Acciones rápidas', 'bankitos'); ?>:</strong></p>
          <ul>
            <li><a href="<?php echo esc_url(site_url('/mi-aporte')); ?>"><?php esc_html_e('Subir aporte', 'bankitos'); ?></a></li>
            <li><a href="<?php echo esc_url(site_url('/solicitud-credito')); ?>"><?php esc_html_e('Solicitar crédito', 'bankitos'); ?></a></li>
            <?php if ($user_role === 'tesorero'): ?>
              <li><a href="<?php echo esc_url(site_url('/auditoria-aportes')); ?>"><?php esc_html_e('Aprobar aportes (Tesorero)', 'bankitos'); ?></a></li>
            <?php endif; ?>
            <?php if ($user_role === 'veedor'): ?>
              <li><a href="<?php echo esc_url(site_url('/auditoria-aportes')); ?>"><?php esc_html_e('Aportes aprobados (Veedor)', 'bankitos'); ?></a></li>
            <?php endif; ?>
            <?php if (class_exists('Bankitos_Credit_Requests') && Bankitos_Credit_Requests::user_can_review()): ?>
              <li><a href="<?php echo esc_url(site_url('/revision-de-solicitudes-de-credito')); ?>"><?php esc_html_e('Revisar solicitudes de crédito', 'bankitos'); ?></a></li>
            <?php endif; ?>
          </ul>

          <hr style="margin:1rem 0; border:none; border-top:1px solid #e5e7eb;">

          <?php if ($user_role === 'presidente'): ?>
            <p style="font-size:0.9rem; color:#6b7280;">
              <?php esc_html_e('Para retirarte del banco, primero debes', 'bankitos'); ?>
              <a href="<?php echo esc_url(site_url('/panel-miembros-presidente')); ?>"><?php esc_html_e('transferir la presidencia', 'bankitos'); ?></a>
              <?php esc_html_e('a otro socio.', 'bankitos'); ?>
            </p>
          <?php else: ?>
            <?php
            $is_socio_general = ($user_role === 'socio_general' || empty($user_role));
            $label = $is_socio_general
                ? __('Retirarme del banco', 'bankitos')
                : __('Solicitar retiro del banco', 'bankitos');
            $hint = $is_socio_general
                ? __('Podrás retirarte si no tienes créditos activos o si tu saldo de créditos es menor o igual a tu total disponible.', 'bankitos')
                : __('Se enviará una solicitud al presidente para su aprobación.', 'bankitos');
            $confirm_msg = $is_socio_general
                ? __('¿Estás seguro que deseas retirarte del banco? Esta acción borrara todos tus datos y no se puede deshacer.', 'bankitos')
                : __('¿Deseas enviar una solicitud de retiro al presidente del banco?', 'bankitos');
            ?>
            <?php
            $resignation_url = add_query_arg(
                [
                    'action'      => 'bankitos_resignation_request',
                    'redirect_to' => self::get_current_url(),
                ],
                self::get_current_url()
            );
            $resignation_url = wp_nonce_url($resignation_url, 'bankitos_resignation_request');
            ?>
            <a
              href="<?php echo esc_url($resignation_url); ?>"
              class="bankitos-btn bankitos-btn--small bankitos-btn--danger"
              onclick="return confirm('<?php echo esc_js($confirm_msg); ?>');"
            >
              <?php echo esc_html($label); ?>
            </a>
            <p style="font-size:0.8rem; color:#9ca3af; margin-top:0.35rem;"><?php echo esc_html($hint); ?></p>
          <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}