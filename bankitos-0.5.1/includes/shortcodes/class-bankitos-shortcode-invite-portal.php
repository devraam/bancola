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

        $user          = null;
        $user_banco_id = 0;
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if (in_array('presidente', (array) $user->roles, true)) {
                return '';
            }
            if (class_exists('Bankitos_Handlers')) {
                $user_banco_id = Bankitos_Handlers::get_user_banco_id($user->ID);
            }
        }
        
        $contexts    = [];
        $token_error = '';
        if ($token && class_exists('BK_Invites_Handler')) {
            $context = BK_Invites_Handler::get_invite_context($token);
            if (!empty($context['exists'])) {
                $contexts[] = $context;
            } else {
                $token_error = __('La invitación no es válida o ya no está disponible.', 'bankitos');
            }
        }

        $user_invites = [];
        if ($user && class_exists('BK_Invites_Handler') && $user_banco_id <= 0) {
            $user_invites = BK_Invites_Handler::get_pending_invites_for_email($user->user_email);
            if (!$token) {
                $contexts = $user_invites;
            } elseif ($user_invites) {
                $known_tokens = wp_list_pluck($contexts, 'token');
                foreach ($user_invites as $invite_context) {
                    if (!in_array($invite_context['token'], $known_tokens, true)) {
                        $contexts[]   = $invite_context;
                        $known_tokens[] = $invite_context['token'];
                    }
                }
            }
        }

        ob_start(); ?>
        <div class="bankitos-invite-portal">
          <h2><?php esc_html_e('Invitación al B@nko', 'bankitos'); ?></h2>
          <?php echo self::top_notice_from_query(); ?>
          <?php if (!$contexts): ?>
            <?php if ($token_error): ?>
              <p><?php echo esc_html($token_error); ?></p>
            <?php elseif ($token): ?>
              <p><?php esc_html_e('La invitación no es válida o ya no está disponible.', 'bankitos'); ?></p>
            <?php elseif ($user && $user_banco_id > 0): ?>
              <p><?php esc_html_e('Ya perteneces a un B@nko y no tienes invitaciones pendientes.', 'bankitos'); ?></p>
            <?php elseif ($user): ?>
              <p><?php esc_html_e('No tienes invitaciones pendientes para este correo electrónico.', 'bankitos'); ?></p>
            <?php else: ?>
               <p><?php esc_html_e('No encontramos la invitación solicitada. Revisa el enlace que recibiste por correo.', 'bankitos'); ?></p>
            <?php endif; ?>
          <?php else: ?>
            <?php if ($user_invites && !$token): ?>
              <p><?php
                  printf(
                      esc_html(_n('Tienes %d invitación pendiente para unirte a un B@nko.', 'Tienes %d invitaciones pendientes para unirte a un B@nko.', count($contexts), 'bankitos')),
                      count($contexts)
                  );
              ?></p>
            <?php endif; ?>

            <?php foreach ($contexts as $context): ?>
              <?php echo self::render_invite_card($context, $user); ?>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    protected static function render_invite_card(array $context, ?WP_User $user = null): string {
        $token   = $context['token'] ?? '';
        $status  = $context['status'] ?? BK_Invites_Handler::STATUS_PENDING;
        $expires = isset($context['expires_at']) ? (int) $context['expires_at'] : 0;

        $expiry_note = '';
        if ($status === BK_Invites_Handler::STATUS_PENDING && $expires > time()) {
            $expiry_note = sprintf(
                /* translators: %s: formatted date and time */
                esc_html__('La invitación caduca el %s.', 'bankitos'),
                esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $expires))
            );
        }

        ob_start(); ?>
        <article class="bankitos-invite-portal__card" aria-live="polite">
          <p class="bankitos-invite-portal__status"><span class="bankitos-pill bankitos-pill--<?php echo esc_attr($status); ?>"><?php echo esc_html($context['status_label'] ?? $status); ?></span></p>
          <dl class="bankitos-invite-portal__details">
            <div>
              <dt><?php esc_html_e('B@nko', 'bankitos'); ?></dt>
              <dd><?php echo esc_html($context['bank_name'] ?: __('Sin nombre', 'bankitos')); ?></dd>
            </div>
            <div>
              <dt><?php esc_html_e('Invitado por', 'bankitos'); ?></dt>
              <dd><?php echo esc_html($context['inviter_name']); ?></dd>
            </div>
          </dl>
          <?php if (!empty($context['status_message']) || $expiry_note): ?>
            <p class="bankitos-invite-portal__note">
              <?php if (!empty($context['status_message'])): ?>
                <?php echo esc_html($context['status_message']); ?>
              <?php endif; ?>
              <?php if (!empty($context['status_message']) && $expiry_note): ?>
                <br>
              <?php endif; ?>
              <?php if ($expiry_note): ?>
                <?php echo $expiry_note; // Already escaped above. ?>
              <?php endif; ?>
            </p>
          <?php endif; ?>

          <?php if ($status === BK_Invites_Handler::STATUS_PENDING): ?>
            <div class="bankitos-invite-portal__actions">
              <?php if ($user): ?>
                <?php $can_accept = BK_Invites_Handler::user_can_accept($context, $user); ?>
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
                <div class="bankitos-invite-portal__cta">
                  <p><?php esc_html_e('Para aceptar la invitación inicia sesión o crea una cuenta con el mismo correo invitado.', 'bankitos'); ?></p>
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
                  <a class="bankitos-btn bankitos-btn--ghost" href="<?php echo esc_url($reject_url); ?>"><?php esc_html_e('Rechazar invitación', 'bankitos'); ?></a>
                </div>
              </div>
          <?php endif; ?>
        </article>
        <?php
        return ob_get_clean();
    }
}