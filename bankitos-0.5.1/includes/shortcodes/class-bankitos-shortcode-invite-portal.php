<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Shortcode_Invite_Portal extends Bankitos_Shortcode_Base {

    public static function register(): void {
        self::register_shortcode('bankitos_invite_portal');
    }

    public static function render($atts = [], $content = null): string {
        $atts = is_array($atts) ? $atts : [];
        $token = isset($atts['token']) ? sanitize_text_field($atts['token']) : '';
        if (!$token && isset($_GET['invite_token'])) {
            $token = sanitize_text_field(wp_unslash($_GET['invite_token']));
        }

        ob_start(); ?>
        <div class="bankitos-invite-portal">
          <h2><?php esc_html_e('Invitación al B@nko', 'bankitos'); ?></h2>
          <?php echo self::top_notice_from_query(); ?>
          <?php if (!$token): ?>
            <p><?php esc_html_e('No encontramos la invitación solicitada.', 'bankitos'); ?></p>
          <?php else: ?>
            <?php $context = class_exists('BK_Invites_Handler') ? BK_Invites_Handler::get_invite_context($token) : ['exists' => false]; ?>
            <?php if (empty($context['exists'])): ?>
              <p><?php esc_html_e('La invitación no es válida o ya no está disponible.', 'bankitos'); ?></p>
            <?php else: ?>
              <div class="bankitos-invite-portal__card">
                <p class="bankitos-invite-portal__status"><span class="bankitos-pill bankitos-pill--<?php echo esc_attr($context['status']); ?>"><?php echo esc_html($context['status_label']); ?></span></p>
                <dl class="bankitos-invite-portal__details">
                  <div>
                    <dt><?php esc_html_e('B@nko', 'bankitos'); ?></dt>
                    <dd><?php echo esc_html($context['bank_name'] ?: __('Sin nombre', 'bankitos')); ?></dd>
                  </div>
                  <div>
                    <dt><?php esc_html_e('Correo invitado', 'bankitos'); ?></dt>
                    <dd><?php echo esc_html($context['email']); ?></dd>
                  </div>
                  <div>
                    <dt><?php esc_html_e('Invitado por', 'bankitos'); ?></dt>
                    <dd><?php echo esc_html($context['inviter_name']); ?></dd>
                  </div>
                </dl>
                <?php if (!empty($context['status_message'])): ?>
                  <p class="bankitos-invite-portal__note"><?php echo esc_html($context['status_message']); ?></p>
                <?php endif; ?>
              </div>

              <?php if ($context['status'] === 'pending'): ?>
                <div class="bankitos-invite-portal__actions">
                  <?php if (is_user_logged_in()): ?>
                    <?php $user = wp_get_current_user(); $can_accept = BK_Invites_Handler::user_can_accept($context, $user); ?>
                    <?php if (is_wp_error($can_accept)): ?>
                      <div class="bankitos-error" role="alert"><?php echo esc_html($can_accept->get_error_message()); ?></div>
                    <?php else: ?>
                      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="bankitos-invite-portal__form">
                        <?php echo wp_nonce_field('bankitos_accept_invite', '_wpnonce', true, false); ?>
                        <input type="hidden" name="action" value="bankitos_accept_invite">
                        <input type="hidden" name="invite_token" value="<?php echo esc_attr($token); ?>">
                        <button type="submit" class="bankitos-btn"><?php esc_html_e('Aceptar invitación', 'bankitos'); ?></button>
                      </form>
                    <?php endif; ?>
                  <?php else: ?>
                    <p><?php esc_html_e('Para aceptar la invitación inicia sesión o crea una cuenta con el mismo correo invitado.', 'bankitos'); ?></p>
                    <div class="bankitos-invite-portal__cta">
                      <a class="bankitos-btn" href="<?php echo esc_url(add_query_arg('invite_token', $token, site_url('/acceder'))); ?>"><?php esc_html_e('Iniciar sesión', 'bankitos'); ?></a>
                      <a class="bankitos-btn bankitos-btn--secondary" href="<?php echo esc_url(add_query_arg('invite_token', $token, site_url('/registrarse'))); ?>"><?php esc_html_e('Crear cuenta', 'bankitos'); ?></a>
                    </div>
                  <?php endif; ?>
                  <div class="bankitos-invite-portal__reject">
                    <?php $reject_url = wp_nonce_url(add_query_arg([
                        'action'      => 'bankitos_reject_invite',
                        'token'       => $token,
                        'redirect_to' => BK_Invites_Handler::portal_url($token),
                    ], admin_url('admin-post.php')), 'bankitos_reject_invite'); ?>
                    <a class="bankitos-link" href="<?php echo esc_url($reject_url); ?>"><?php esc_html_e('Rechazar invitación', 'bankitos'); ?></a>
                  </div>
                </div>
              <?php endif; ?>
            <?php endif; ?>
          <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}