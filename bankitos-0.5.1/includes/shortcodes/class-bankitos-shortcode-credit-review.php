<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Shortcode_Credit_Review extends Bankitos_Shortcode_Panel_Base {

    public static function register(): void {
        self::register_shortcode('bankitos_credit_request_list');
    }

    public static function render($atts = [], $content = null): string {
        if (!is_user_logged_in()) {
            return '';
        }
        $context = self::get_panel_context();
        if ($context['banco_id'] <= 0) {
            return '<div class="bankitos-panel__message">' . esc_html__('Aún no perteneces a un B@nko.', 'bankitos') . '</div>';
        }
        if (!Bankitos_Credit_Requests::user_can_review()) {
            return '<div class="bankitos-panel__message">' . esc_html__('Solo el presidente, tesorero o veedor pueden revisar estas solicitudes.', 'bankitos') . '</div>';
        }
        $requests = Bankitos_Credit_Requests::get_requests($context['banco_id']);
        $role     = Bankitos_Credit_Requests::get_user_role_key();
        $current_url = self::get_current_url();
        ob_start(); ?>
        <div class="bankitos-credit-review bankitos-panel">
          <div class="bankitos-credit-review__header">
            <h3><?php esc_html_e('Solicitudes de crédito', 'bankitos'); ?></h3>
            <p><?php esc_html_e('Consulta y firma las solicitudes creadas por los socios.', 'bankitos'); ?></p>
          </div>
          <?php echo self::top_notice_from_query(); ?>
          <?php if (!$requests): ?>
              <p class="bankitos-panel__message"><?php esc_html_e('Aún no hay solicitudes de crédito.', 'bankitos'); ?></p>
            <?php else: ?>
              <div class="bankitos-credit-review__accordion">
                <?php foreach ($requests as $request): ?>
                  <?php echo self::render_card($request, $role, $current_url); ?>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function render_card(array $request, string $user_role, string $redirect): string {
        $types = Bankitos_Credit_Requests::get_credit_types();
        $type_label = $types[$request['credit_type']] ?? ucfirst($request['credit_type']);
        $status_classes = [
            'pending'  => 'bankitos-pill--pending',
            'approved' => 'bankitos-pill--accepted',
            'rejected' => 'bankitos-pill--rejected',
            'disbursement_pending' => 'bankitos-pill--pending',
            'disbursed' => 'bankitos-pill--accepted',
        ];
        $status_labels = [
            'pending'  => __('Pendiente', 'bankitos'),
            'approved' => __('Aprobado', 'bankitos'),
            'disbursement_pending' => __('Pendiente de desembolso', 'bankitos'),
            'disbursed' => __('Desembolsado', 'bankitos'),
            'rejected' => __('No aprobado', 'bankitos'),    
        ];
        $committee = Bankitos_Credit_Requests::get_committee_roles();
        $columns = [
            'presidente' => 'approved_president',
            'tesorero'   => 'approved_treasurer',
            'veedor'     => 'approved_veedor',
        ];
        $can_act = $request['status'] === 'pending'
            && isset($columns[$user_role])
            && ($request[$columns[$user_role]] ?? 'pending') === 'pending';
        ob_start(); ?>
        <details class="bankitos-credit-accordion bankitos-credit-accordion--<?php echo esc_attr($request['status']); ?>">
          <summary class="bankitos-credit-accordion__summary">
            <div class="bankitos-credit-accordion__title">
              <p class="bankitos-credit-card__badge"><?php esc_html_e('Solicitud de crédito', 'bankitos'); ?></p>
              <h4><?php echo esc_html($request['display_name']); ?></h4>
              <p class="bankitos-credit-accordion__type"><?php echo esc_html($type_label); ?></p>
            </div>
            <div class="bankitos-credit-accordion__meta">
              <div class="bankitos-credit-accordion__meta-item">
                <span><?php esc_html_e('Fecha', 'bankitos'); ?></span>
                <strong><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($request['request_date']))); ?></strong>
              </div>
              <div class="bankitos-credit-accordion__meta-item">
                <span><?php esc_html_e('Monto', 'bankitos'); ?></span>
                <strong><?php echo esc_html(self::format_currency((float) $request['amount'])); ?></strong>
              </div>
              <div class="bankitos-credit-accordion__meta-item">
                <span><?php esc_html_e('Tiempo de pago', 'bankitos'); ?></span>
                <strong><?php echo esc_html(sprintf(_n('%s mes', '%s meses', (int) $request['term_months'], 'bankitos'), number_format_i18n((int) $request['term_months']))); ?></strong>
              </div>
              <span class="bankitos-pill <?php echo esc_attr($status_classes[$request['status']] ?? 'bankitos-pill--pending'); ?>">
                <?php echo esc_html($status_labels[$request['status']] ?? $request['status']); ?>
              </span>          
            </div>
            </summary>
          <div class="bankitos-credit-accordion__content">
            <article class="bankitos-credit-card">
              <dl class="bankitos-credit-card__details">
                <div>
                  <dt><?php esc_html_e('Documento de identidad', 'bankitos'); ?></dt>
                  <dd><?php echo esc_html($request['document_id']); ?></dd>
                </div>
              <div>
                  <dt><?php esc_html_e('Edad', 'bankitos'); ?></dt>
                  <dd><?php echo esc_html($request['age']); ?></dd>
                </div>
                <div>
                  <dt><?php esc_html_e('Teléfono', 'bankitos'); ?></dt>
                  <dd><?php echo esc_html($request['phone']); ?></dd>
                </div>
                <div>
                  <dt><?php esc_html_e('Tipo de crédito', 'bankitos'); ?></dt>
                  <dd><?php echo esc_html($type_label); ?></dd>
                </div>
                <div>
                  <dt><?php esc_html_e('Monto solicitado', 'bankitos'); ?></dt>
                  <dd><?php echo esc_html(self::format_currency((float) $request['amount'])); ?></dd>
                </div>
                <div>
                  <dt><?php esc_html_e('Tiempo de pago', 'bankitos'); ?></dt>
                  <dd><?php echo esc_html(sprintf(_n('%s mes', '%s meses', (int) $request['term_months'], 'bankitos'), number_format_i18n((int) $request['term_months']))); ?></dd>
                </div>
                <div>
                  <dt><?php esc_html_e('Firma del solicitante', 'bankitos'); ?></dt>
                  <dd class="bankitos-credit-card__signature-value">
                    <span class="bankitos-pill <?php echo esc_attr($request['signature'] ? 'bankitos-pill--accepted' : 'bankitos-pill--pending'); ?>">
                      <?php echo $request['signature'] ? esc_html__('Aceptada', 'bankitos') : esc_html__('Pendiente', 'bankitos'); ?>
                    </span>
                  </dd>
                </div>
              </dl>
              <div class="bankitos-credit-card__description">
                <div class="bankitos-credit-card__description-header">
                  <h5><?php esc_html_e('Descripción del uso del crédito', 'bankitos'); ?></h5>
                  <span class="bankitos-credit-card__date"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($request['request_date']))); ?></span>
                </div>
                <p><?php echo nl2br(esc_html($request['description'])); ?></p>
              </div>
              <section class="bankitos-credit-card__committee">
                <div class="bankitos-credit-card__committee-header">
                  <div>
                    <h5><?php esc_html_e('Aprobación del crédito', 'bankitos'); ?></h5>
                    <p><?php esc_html_e('(solo diligencia el comité del Bancola)', 'bankitos'); ?></p>
                  </div>
                  <span class="bankitos-credit-card__status-label bankitos-pill <?php echo esc_attr($status_classes[$request['status']] ?? 'bankitos-pill--pending'); ?>">
                    <?php echo esc_html($status_labels[$request['status']] ?? $request['status']); ?>
                  </span>
                </div>
                <ul class="bankitos-credit-card__committee-list">
                  <?php foreach ($committee as $role => $label):
                      $role_status = $request[$columns[$role]] ?? 'pending';
                      ?>
                      <li>
                        <span><?php echo esc_html($label); ?></span>
                        <span class="bankitos-pill <?php echo esc_attr($status_classes[$role_status] ?? 'bankitos-pill--pending'); ?>"><?php echo esc_html($status_labels[$role_status] ?? $role_status); ?></span>
                      </li>
                  <?php endforeach; ?>
                </ul>
                <?php if ($request['approval_date'] && in_array($request['status'], ['approved','disbursement_pending','disbursed'], true)): ?>
                  <p class="bankitos-credit-card__approval-date"><?php printf('%s %s', esc_html__('Fecha de aprobación:', 'bankitos'), esc_html(date_i18n(get_option('date_format'), strtotime($request['approval_date'])))); ?></p>
                <?php endif; ?>
                <div class="bankitos-credit-card__observaciones">
                  <strong><?php esc_html_e('Observaciones', 'bankitos'); ?></strong>
                  <p><?php echo $request['committee_notes'] ? nl2br(esc_html($request['committee_notes'])) : esc_html__('Pendiente', 'bankitos'); ?></p>
                </div>
                <?php if ($can_act): ?>
                  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="bankitos-credit-card__form">
                    <?php echo wp_nonce_field('bankitos_credito_resolver_' . (int) $request['id'], '_wpnonce', true, false); ?>
                    <input type="hidden" name="action" value="bankitos_credito_resolver">
                    <input type="hidden" name="request_id" value="<?php echo esc_attr($request['id']); ?>">
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect); ?>">
                    <label for="bk_notes_<?php echo esc_attr($request['id']); ?>"><?php esc_html_e('Observaciones del comité (opcional)', 'bankitos'); ?></label>
                    <textarea id="bk_notes_<?php echo esc_attr($request['id']); ?>" name="notes" rows="3"><?php echo esc_textarea($request['committee_notes']); ?></textarea>
                    <div class="bankitos-credit-card__actions">
                      <button type="submit" name="decision" value="approved" class="bankitos-btn"><?php esc_html_e('Aprobar', 'bankitos'); ?></button>
                      <button type="submit" name="decision" value="rejected" class="bankitos-btn bankitos-btn--danger"><?php esc_html_e('No aprobar', 'bankitos'); ?></button>
                    </div>
                  </form>
                  <p class="bankitos-credit-card__footnote"><?php esc_html_e('Al registrar tu firma se cierra la solicitud en los términos descritos.', 'bankitos'); ?></p>
                <?php endif; ?>
              </section>
            </article>
          </div>
        </details>
        <?php
        return ob_get_clean();
    }
}