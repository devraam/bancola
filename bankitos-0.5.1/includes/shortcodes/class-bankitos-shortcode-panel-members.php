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

        // Si no es presidente, no mostrar nada.
        if (empty($context['is_president'])) {
          return '';
        }

        // Si es presidente pero no tiene banco (raro, pero posible), no mostrar nada.
        if ($context['banco_id'] <= 0) {
          return '';
        }
        
        return self::render_section($context);
    }

    public static function render_section(array $context): string {

        $assignable_roles = [
            'socio_general' => __('Socio general', 'bankitos'),
            'secretario'    => __('Secretario', 'bankitos'),
            'tesorero'      => __('Tesorero', 'bankitos'),
            'veedor'        => __('Veedor', 'bankitos'),
        ];

        $rows = self::merge_members_and_invites($context['members'], $context['invites']);
        $can_manage = $context['can_manage_invites'];
        $current_url = self::get_current_url();

        ob_start(); ?>
        <div class="bankitos-members-table">
          <div class="bankitos-members-table__header">
            <h3 class="bankitos-members-table__title"><?php esc_html_e('Invitaciones enviadas', 'bankitos'); ?></h3>
            <p class="bankitos-members-table__subtitle"><?php esc_html_e('Consulta el estado de cada invitación y miembro registrado.', 'bankitos'); ?></p>
          </div>
          <?php if (empty($rows)): ?>
            <p class="bankitos-members-table__empty"><?php esc_html_e('Aún no hay miembros ni invitaciones registradas.', 'bankitos'); ?></p>
          <?php else: ?>
            <div class="bankitos-accordion bankitos-members-table__accordion" role="list">
              <?php $is_first = true; foreach ($rows as $row): ?>
                <?php
                $display_name = $row['name'] ?: $row['email'];
                $display_email = $row['email'] ?: __('No registrado', 'bankitos');
                $row_id = isset($row['id']) ? (int) $row['id'] : 0;
                $can_update_invite = $can_manage && $row['type'] === 'invite' && $row['status'] !== 'accepted' && $row_id > 0;
                $pill_class = 'bankitos-pill bankitos-pill--' . sanitize_html_class($row['status']);
                $edit_form_id = 'bankitos-edit-' . $row_id;
                ?>
                <details class="bankitos-accordion__item bankitos-member-card" data-bankitos-invite-row role="listitem" <?php echo $is_first ? 'open' : ''; ?>>
                  <summary class="bankitos-accordion__summary">
                    <div class="bankitos-accordion__title">
                      <span class="bankitos-accordion__name"><?php echo esc_html($display_name); ?></span>
                      <span class="bankitos-accordion__email"><?php echo esc_html($display_email); ?></span>
                    </div>
                    <div class="bankitos-accordion__meta">
                      <span class="<?php echo esc_attr($pill_class); ?>"><?php echo esc_html($row['status_label']); ?></span>
                      <span class="bankitos-accordion__chevron" aria-hidden="true"></span>
                    </div>
                  </summary>
                  <div class="bankitos-accordion__content">
                    <dl class="bankitos-accordion__grid bankitos-member-card__grid">
                      <div>
                        <dt><?php esc_html_e('Nombre', 'bankitos'); ?></dt>
                        <dd>
                          <span class="bankitos-table__value" data-bankitos-invite-display data-bankitos-invite-field="name">
                            <?php echo esc_html($display_name); ?>
                          </span>
                          <div class="bankitos-table__edit-field" data-bankitos-invite-edit-field hidden>
                            <label class="screen-reader-text" for="bankitos-edit-name-<?php echo esc_attr($row_id); ?>">
                              <?php esc_html_e('Nombre del invitado', 'bankitos'); ?>
                            </label>
                            <input
                              data-bankitos-invite-edit-input="<?php echo esc_attr($edit_form_id); ?>"
                              id="bankitos-edit-name-<?php echo esc_attr($row_id); ?>"
                              form="<?php echo esc_attr($edit_form_id); ?>"
                              type="text"
                              name="invite_name"
                              value="<?php echo esc_attr($row['name']); ?>"
                              required
                            >   
                          </div>
                        </dd>
                      </div>
                      <div>
                        <dt><?php esc_html_e('Correo', 'bankitos'); ?></dt>
                        <dd>
                          <span class="bankitos-table__value" data-bankitos-invite-display data-bankitos-invite-field="email">
                            <?php echo esc_html($display_email); ?>
                          </span>
                          <div class="bankitos-table__edit-field" data-bankitos-invite-edit-field hidden>
                            <label class="screen-reader-text" for="bankitos-edit-email-<?php echo esc_attr($row_id); ?>">
                              <?php esc_html_e('Correo del invitado', 'bankitos'); ?>
                            </label>
                            <input
                              data-bankitos-invite-edit-input="<?php echo esc_attr($edit_form_id); ?>"
                              id="bankitos-edit-email-<?php echo esc_attr($row_id); ?>"
                              form="<?php echo esc_attr($edit_form_id); ?>"
                              type="email"
                              name="invite_email"
                              value="<?php echo esc_attr($row['email']); ?>"
                              required
                            >
                          </div>
                        </dd>
                      </div>
                      <div>
                        <dt><?php esc_html_e('Estado', 'bankitos'); ?></dt>
                        <dd><span class="<?php echo esc_attr($pill_class); ?>"><?php echo esc_html($row['status_label']); ?></span></dd>
                      </div>
                    </dl>

                    <div class="bankitos-member-card__actions" data-invite-actions-cell>
                      <?php if ($can_update_invite): // --- INICIO: Lógica para INVITACIONES PENDIENTES --- ?>
                        <div class="bankitos-invite-actions">
                          <div class="bankitos-invite-actions__group" data-bankitos-invite-default-actions>
                              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="bankitos-invite-actions__form">
                                <?php echo wp_nonce_field('bankitos_resend_invite_' . $row_id, '_wpnonce', true, false); ?>
                                <input type="hidden" name="action" value="bankitos_resend_invite">
                                <input type="hidden" name="invite_id" value="<?php echo esc_attr($row_id); ?>">
                                <input type="hidden" name="redirect_to" value="<?php echo esc_url($current_url); ?>">
                                <button type="submit" class="bankitos-btn bankitos-btn--small bankitos-btn--ghost"><?php esc_html_e('Reenviar', 'bankitos'); ?></button>
                              </form>

                              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="bankitos-invite-actions__form">
                                <?php echo wp_nonce_field('bankitos_cancel_invite_' . $row_id, '_wpnonce', true, false); ?>
                                <input type="hidden" name="action" value="bankitos_cancel_invite">
                                <input type="hidden" name="invite_id" value="<?php echo esc_attr($row_id); ?>">
                                <input type="hidden" name="redirect_to" value="<?php echo esc_url($current_url); ?>">
                                <button type="submit" class="bankitos-btn bankitos-btn--small bankitos-btn--danger" onclick="return confirm('<?php echo esc_js(__('¿Cancelar esta invitación?', 'bankitos')); ?>');"><?php esc_html_e('Cancelar invitación', 'bankitos'); ?></button>
                              </form>
                            </div>

                          <button
                            type="button"
                            class="bankitos-btn bankitos-btn--small bankitos-btn--ghost"
                            data-bankitos-invite-edit-toggle
                            aria-expanded="false"
                            aria-controls="<?php echo esc_attr($edit_form_id); ?>"
                          >
                            <?php esc_html_e('Editar', 'bankitos'); ?>
                          </button>

                          <form id="<?php echo esc_attr($edit_form_id); ?>" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="bankitos-invite-actions__form bankitos-invite-actions__form--edit" data-bankitos-invite-edit-form hidden>
                            <?php echo wp_nonce_field('bankitos_update_invite_' . $row_id, '_wpnonce', true, false); ?>
                            <input type="hidden" name="action" value="bankitos_update_invite">
                            <input type="hidden" name="invite_id" value="<?php echo esc_attr($row_id); ?>">
                            <input type="hidden" name="redirect_to" value="<?php echo esc_url($current_url); ?>">
                            <div class="bankitos-invite-actions__buttons" data-bankitos-invite-edit-buttons hidden>
                              <button type="submit" class="bankitos-btn bankitos-btn--small"><?php esc_html_e('Guardar cambios', 'bankitos'); ?></button>
                              <button type="button" class="bankitos-btn bankitos-btn--small bankitos-btn--ghost" data-bankitos-invite-edit-cancel>
                                <?php esc_html_e('Cerrar', 'bankitos'); ?>
                              </button>
                            </div>
                          </form>
                        </div>
                      
                      <?php elseif ($row['type'] === 'member'): // --- INICIO: Lógica para MIEMBROS ACEPTADOS --- ?>
                        <div class="bankitos-role-manager">
                          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="bankitos-role-manager__form">
                              <?php $member_user_id = (int) $row['id']; ?>
                              <?php echo wp_nonce_field('bankitos_assign_role_' . $member_user_id, '_wpnonce', true, false); ?>
                              <input type="hidden" name="action" value="bankitos_assign_role">
                              <input type="hidden" name="member_user_id" value="<?php echo esc_attr($member_user_id); ?>">
                              <input type="hidden" name="redirect_to" value="<?php echo esc_url($current_url); ?>">
                              
                              <label for="bankitos-role-<?php echo esc_attr($member_user_id); ?>" class="screen-reader-text"><?php esc_html_e('Asignar Rol', 'bankitos'); ?></label>
                              <select name="member_role" id="bankitos-role-<?php echo esc_attr($member_user_id); ?>">
                                  <?php $current_role_key = $row['role_key'] ?? 'socio_general'; ?>
                                  <?php foreach ($assignable_roles as $role_key => $role_label): ?>
                                      <option value="<?php echo esc_attr($role_key); ?>" <?php selected($current_role_key, $role_key); ?>>
                                          <?php echo esc_html($role_label); ?>
                                      </option>
                                  <?php endforeach; ?>
                              </select>
                              <button type="submit" class="bankitos-btn bankitos-btn--small bankitos-btn--ghost"><?php esc_html_e('Guardar Rol', 'bankitos'); ?></button>
                          </form>
                        </div>

                        <?php else: // Invitaciones Rechazadas, Expiradas, etc. ?>
                        <span class="bankitos-text-muted">—</span>
                      <?php endif; // --- FIN DE LÓGICA DE ACCIONES --- ?>
                    </div>
                  </div>
                </details>
              <?php $is_first = false; endforeach; ?>
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
                'id'           => (int) ($invite['id'] ?? 0),
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

    protected static function render_guest_message(): string {
        ob_start(); ?>
        <div class="bankitos-members bankitos-members--empty-state">
          <p class="bankitos-members__empty"><?php esc_html_e('Debes pertenecer a un B@nko para gestionar miembros e invitaciones.', 'bankitos'); ?></p>
        </div>
        <?php
        return ob_get_clean();
    }
}