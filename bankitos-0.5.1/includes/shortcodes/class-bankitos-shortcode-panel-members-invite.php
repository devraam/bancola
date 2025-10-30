<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Shortcode_Panel_Members_Invite extends Bankitos_Shortcode_Panel_Members {

    public static function register(): void {
        self::register_shortcode('bankitos_panel_members_invite');
    }

    public static function render($atts = [], $content = null): string {
        if (!is_user_logged_in()) {
            return '';
        }

        $context = self::get_panel_context();
        if ($context['banco_id'] <= 0) {
          if (!empty($context['is_general_member'])) {
            return '';
          }
          return self::render_guest_message();
        }

        if (!$context['can_manage_invites']) {
          return '';
        }

        return self::render_invite_section($context);
    }

    protected static function render_invite_section(array $context): string {

        $min_required = max(1, (int) $context['min_invites']);
        $initial_needed = max(0, (int) ($context['initial_invites_needed'] ?? 0));
        if ($initial_needed > 0) {
            $first_message = sprintf(
                _n(
                    'Debes invitar al menos a %d persona para completar tu B@nko.',
                    'Debes invitar al menos a %d personas para completar tu B@nko.',
                    $initial_needed,
                    'bankitos'
                ),
                $initial_needed
            );
        } else {
            $first_message = __('Puedes invitar a uno o varios miembros cuando lo necesites.', 'bankitos');
        }

        $section_attributes = ['data-bankitos-invite'];
        if ($initial_needed > 0) {
            $section_attributes[] = 'data-bankitos-invite-initial-open';
        }
        $section_attributes = implode(' ', array_map('esc_attr', $section_attributes));

        ob_start(); ?>
        <div class="bankitos-members" <?php echo $section_attributes; ?>>
          <div class="bankitos-members__header">
            <div class="bankitos-members__heading">
              <div class="bankitos-members__icon" aria-hidden="true">👥</div>
              <div>
                <p class="bankitos-members__title"><?php esc_html_e('Miembros', 'bankitos'); ?></p>
                <p class="bankitos-members__subtitle"><?php esc_html_e('Gestiona invitaciones y seguimiento de tu equipo.', 'bankitos'); ?></p>
              </div>
            </div>
            <button type="button" class="bankitos-btn bankitos-btn--secondary" data-bankitos-invite-open aria-expanded="false">
              <?php esc_html_e('Invitar miembros', 'bankitos'); ?>
            </button>
          </div>

          <div class="bankitos-members__invite" data-bankitos-invite-panel hidden>
            <p class="bankitos-members__invite-intro"><?php echo esc_html($first_message); ?></p>
            <div class="bankitos-members__invite-error" data-bankitos-invite-error aria-live="assertive"></div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-bankitos-invite-form data-min-required="<?php echo esc_attr($min_required); ?>">
              <?php echo wp_nonce_field('bankitos_send_invites', '_wpnonce', true, false); ?>
              <input type="hidden" name="action" value="bankitos_send_invites">
              <input type="hidden" name="banco_id" value="<?php echo esc_attr($context['banco_id']); ?>">
              <input type="hidden" name="redirect_to" value="<?php echo esc_url(self::get_current_url()); ?>">
              <div class="bankitos-invite-rows" data-bankitos-invite-rows>
                <?php echo self::render_invite_rows($min_required); ?>
              </div>
              <div class="bankitos-members__invite-actions">
                <button type="button" class="bankitos-btn bankitos-btn--ghost" data-bankitos-invite-add>
                  <?php esc_html_e('Agregar otra invitación', 'bankitos'); ?>
                </button>
                <div class="bankitos-members__invite-buttons">
                  <button type="submit" class="bankitos-btn"><?php esc_html_e('Enviar invitaciones', 'bankitos'); ?></button>
                  <button type="button" class="bankitos-btn bankitos-btn--ghost" data-bankitos-invite-close>
                    <?php esc_html_e('Cancelar', 'bankitos'); ?>
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }
}