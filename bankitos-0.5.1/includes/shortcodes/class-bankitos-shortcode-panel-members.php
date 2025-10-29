<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Shortcode_Panel_Members extends Bankitos_Shortcode_Panel_Base {

    public static function register(): void {
        self::register_shortcode('bankitos_panel_members');
    }

    public static function render($atts = [], $content = null): string {
        if (!is_user_logged_in()) {
            return '';
        }
        $context = self::get_panel_context();
        if ($context['banco_id'] <= 0) {
          return self::render_guest_message();
        }
        return self::render_section($context);
    }

    public static function render_section(array $context): string {
        self::enqueue_invite_assets($context);

        $rows = self::merge_members_and_invites($context['members'], $context['invites']);
        $can_manage = $context['can_manage_invites'];
        $min_required = max(1, (int) $context['min_invites']);
        $first_message = $context['is_first_invite']
            ? sprintf(__('La primera vez debes invitar al menos a %d personas.', 'bankitos'), $min_required)
            : __('Puedes invitar a uno o varios miembros cuando lo necesites.', 'bankitos');

        ob_start(); ?>
        <div class="bankitos-members" data-bankitos-members>
          <div class="bankitos-members__header">
            <div class="bankitos-members__heading">
              <div class="bankitos-members__icon" aria-hidden="true">👥</div>
              <div>
                <p class="bankitos-members__title"><?php esc_html_e('Miembros', 'bankitos'); ?></p>
                <p class="bankitos-members__subtitle"><?php esc_html_e('Gestiona invitaciones y seguimiento de tu equipo.', 'bankitos'); ?></p>
              </div>
            </div>
            <?php if ($can_manage): ?>
              <button type="button" class="bankitos-btn bankitos-btn--secondary" data-bankitos-invite-open aria-expanded="false">
                <?php esc_html_e('Invitar miembros', 'bankitos'); ?>
              </button>
            <?php endif; ?>
          </div>

          <?php if ($can_manage): ?>
            <div class="bankitos-members__invite" data-bankitos-invite-panel hidden>
              <p class="bankitos-members__invite-intro"><?php echo esc_html($first_message); ?></p>
              <div class="bankitos-members__invite-error" data-bankitos-invite-error aria-live="assertive"></div>
              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-bankitos-invite-form data-min-required="<?php echo esc_attr($min_required); ?>">
                <?php echo wp_nonce_field('bankitos_send_invites', '_wpnonce', true, false); ?>
                <input type="hidden" name="action" value="bankitos_send_invites">
                <input type="hidden" name="banco_id" value="<?php echo esc_attr($context['banco_id']); ?>">
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
            <?php endif; ?>
        </div>

        <div class="bankitos-members-table">
          <div class="bankitos-members-table__header">
            <h3 class="bankitos-members-table__title"><?php esc_html_e('Invitaciones enviadas', 'bankitos'); ?></h3>
            <p class="bankitos-members-table__subtitle"><?php esc_html_e('Consulta el estado de cada invitación y miembro registrado.', 'bankitos'); ?></p>
          </div>
          <?php if (empty($rows)): ?>
            <p class="bankitos-members-table__empty"><?php esc_html_e('Aún no hay miembros ni invitaciones registradas.', 'bankitos'); ?></p>
          <?php else: ?>
            <div class="bankitos-table-wrapper">
              <table class="bankitos-table">
                <thead>
                  <tr>
                    <th scope="col"><?php esc_html_e('Nombre', 'bankitos'); ?></th>
                    <th scope="col"><?php esc_html_e('Correo', 'bankitos'); ?></th>
                    <th scope="col"><?php esc_html_e('Estado', 'bankitos'); ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($rows as $row): ?>
                    <?php
                    $display_name = $row['name'] ?: $row['email'];
                    $display_email = $row['email'] ?: __('No registrado', 'bankitos');
                    ?>
                    <tr>
                      <td data-title="<?php esc_attr_e('Nombre', 'bankitos'); ?>"><?php echo esc_html($display_name); ?></td>
                      <td data-title="<?php esc_attr_e('Correo', 'bankitos'); ?>"><?php echo esc_html($display_email); ?></td>
                      <td data-title="<?php esc_attr_e('Estado', 'bankitos'); ?>">
                        <span class="bankitos-pill bankitos-pill--<?php echo esc_attr($row['status']); ?>"><?php echo esc_html($row['status_label']); ?></span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    protected static function merge_members_and_invites(array $members, array $invites): array {
        $rows = $members;
        foreach ($invites as $invite) {
            if (($invite['status'] ?? '') === 'accepted') {
                continue;
            }
            $rows[] = [
                'type'         => 'invite',
                'name'         => $invite['name'] ?? '',
                'email'        => $invite['email'] ?? '',
                'status'       => $invite['status'] ?? 'pending',
                'status_label' => $invite['status_label'] ?? __('Enviada', 'bankitos'),
                'avatar'       => '',
            ];
        }
        return $rows;
    }

    protected static function render_invite_rows(int $min_required): string {
        $initial = max(1, $min_required);
        $html = '';
        for ($i = 0; $i < $initial; $i++) {
            $html .= self::render_invite_row();
        }
        return $html;
    }

    protected static function render_invite_row(): string {
        ob_start(); ?>
        <div class="bankitos-invite-row" data-bankitos-invite-row>
          <div class="bankitos-field">
            <label><?php esc_html_e('Nombre', 'bankitos'); ?></label>
            <input type="text" name="invite_name[]" required>
          </div>
          <div class="bankitos-field">
            <label><?php esc_html_e('Correo electrónico', 'bankitos'); ?></label>
            <input type="email" name="invite_email[]" required>
          </div>
          <button type="button" class="bankitos-invite-row__remove" aria-label="<?php esc_attr_e('Eliminar fila', 'bankitos'); ?>" data-bankitos-invite-remove>×</button>
        </div>
        <?php
        return ob_get_clean();
    }

    protected static function enqueue_invite_assets(array $context): void {
        if (!is_user_logged_in() || !$context['can_manage_invites']) {
            return;
        }
        if (!wp_script_is('bankitos-panel', 'enqueued')) {
            wp_enqueue_script('bankitos-panel');
        }
        $data = [
            'minRequiredError' => __('Debes completar al menos el mínimo de invitaciones requerido.', 'bankitos'),
            'invalidEmailError' => __('Ingresa correos electrónicos válidos.', 'bankitos'),
            'missingFieldsError'=> __('Completa nombre y correo en cada fila.', 'bankitos'),
            'nameLabel'        => __('Nombre', 'bankitos'),
            'emailLabel'       => __('Correo electrónico', 'bankitos'),
            'removeLabel'      => __('Eliminar fila', 'bankitos'),
        ];
        wp_localize_script('bankitos-panel', 'bankitosPanelInvites', $data);
    }

    protected static function render_guest_message(): string {
        ob_start(); ?>
        <div class="bankitos-members bankitos-members--empty-state">
          <p class="bankitos-members__empty"><?php esc_html_e('Debes pertenecer a un B@nko para gestionar miembros e invitaciones.', 'bankitos'); ?></p>
        </div>
        <?php
        return ob_get_clean();
    }
}