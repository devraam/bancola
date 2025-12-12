<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Shortcode_Credit_Summary extends Bankitos_Shortcode_Panel_Base {

    public static function register(): void {
        self::register_shortcode('bankitos_credit_summary');
    }

    public static function render($atts = [], $content = null): string {
        if (!is_user_logged_in()) {
            return '';
        }

        $context = self::get_panel_context();
        if ($context['banco_id'] <= 0) {
            return '<div class="bankitos-panel__message">' . esc_html__('Aún no perteneces a un B@nko.', 'bankitos') . '</div>';
        }

        $requests = Bankitos_Credit_Requests::get_user_requests($context['banco_id'], get_current_user_id());
        $types    = Bankitos_Credit_Requests::get_credit_types();
        $tasa     = isset($context['meta']['tasa']) ? (float) $context['meta']['tasa'] : 0.0;
        $modals   = [];

        ob_start(); ?>
        <div class="bankitos-credit-summary">
          <div class="bankitos-credit-summary__header">
            <div>
              <p class="bankitos-credit-summary__badge"><?php esc_html_e('Mis solicitudes de crédito', 'bankitos'); ?></p>
              <h3><?php esc_html_e('Resumen de estado', 'bankitos'); ?></h3>
              <p class="bankitos-credit-summary__intro"><?php esc_html_e('Consulta el estado de tus solicitudes: aprobadas, pendientes o rechazadas.', 'bankitos'); ?></p>
            </div>
          </div>
          <?php echo self::top_notice_from_query(); ?>
          <?php if (!$requests): ?>
            <p class="bankitos-panel__message"><?php esc_html_e('Aún no has enviado solicitudes de crédito.', 'bankitos'); ?></p>
          <?php else: ?>
            <div class="bankitos-credit-summary__list">
              <?php foreach ($requests as $request):
                  $type_label   = $types[$request['credit_type']] ?? ucfirst($request['credit_type']);
                  $status_class = self::get_status_class($request['status']);
                  $status_label = self::get_status_label($request['status']);
                  $modal_id     = 'bk-modal-credit-' . (int) $request['id'];
                  $modal_markup = self::render_modal_for_request($request, $modal_id, $tasa);
                  if ($modal_markup) {
                      $modals[] = $modal_markup;
                  }
                  ?>
                  <article class="bankitos-credit-summary__card">
                    <header class="bankitos-credit-summary__card-header">
                      <div>
                        <p class="bankitos-credit-summary__card-badge"><?php echo esc_html($type_label); ?></p>
                        <h4><?php echo esc_html(self::format_currency((float) $request['amount'])); ?></h4>
                      </div>
                      <div class="bankitos-credit-summary__status">
                        <span class="bankitos-pill <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span>
                        <span class="bankitos-credit-summary__date"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($request['request_date']))); ?></span>
                      </div>
                    </header>
                    <dl class="bankitos-credit-summary__details">
                      <div>
                        <dt><?php esc_html_e('Fecha de solicitud', 'bankitos'); ?></dt>
                        <dd><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($request['request_date']))); ?></dd>
                      </div>
                      <div>
                        <dt><?php esc_html_e('Tipo de crédito', 'bankitos'); ?></dt>
                        <dd><?php echo esc_html($type_label); ?></dd>
                      </div>
                      <div>
                        <dt><?php esc_html_e('Monto', 'bankitos'); ?></dt>
                        <dd><?php echo esc_html(self::format_currency((float) $request['amount'])); ?></dd>
                      </div>
                      <div>
                        <dt><?php esc_html_e('Plazo', 'bankitos'); ?></dt>
                        <dd><?php echo esc_html(sprintf(_n('%s mes', '%s meses', (int) $request['term_months'], 'bankitos'), number_format_i18n((int) $request['term_months']))); ?></dd>
                      </div>
                      <div>
                        <dt><?php esc_html_e('Interés mensual', 'bankitos'); ?></dt>
                        <dd><?php echo $tasa > 0 ? esc_html(sprintf('%s%%', number_format_i18n($tasa, 2))) : esc_html__('No definido', 'bankitos'); ?></dd>
                      </div>
                      <div>
                        <dt><?php esc_html_e('Estado', 'bankitos'); ?></dt>
                        <dd><?php echo esc_html($status_label); ?></dd>
                      </div>
                    </dl>
                   <?php if ($modal_markup): ?>
                      <div class="bankitos-credit-summary__actions">
                        <button type="button" class="bankitos-btn" data-bankitos-open="<?php echo esc_attr($modal_id); ?>">
                          <?php echo $request['status'] === 'rejected' ? esc_html__('Ver más', 'bankitos') : esc_html__('Pagar', 'bankitos'); ?>
                        </button>
                      </div>
                    <?php endif; ?>
                  </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <?php if ($modals): ?>
          <?php foreach ($modals as $modal_html) { echo $modal_html; } ?>
          <?php echo self::inline_scripts(); ?>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    private static function get_status_label(string $status): string {
        $labels = [
            'pending'  => __('Pendiente de revisión', 'bankitos'),
            'approved' => __('Aprobado', 'bankitos'),
            'rejected' => __('No aprobado', 'bankitos'),
        ];

        return $labels[$status] ?? $status;
    }

    private static function get_status_class(string $status): string {
        $classes = [
            'pending'  => 'bankitos-pill--pending',
            'approved' => 'bankitos-pill--accepted',
            'rejected' => 'bankitos-pill--rejected',
        ];

        return $classes[$status] ?? 'bankitos-pill--pending';
    }

    private static function render_modal_for_request(array $request, string $modal_id, float $tasa): string {
        if ($request['status'] === 'rejected') {
            return self::render_rejection_modal($request, $modal_id);
        }
        if ($request['status'] === 'approved') {
            return self::render_payment_modal($request, $modal_id, $tasa);
        }
        return '';
    }

    private static function render_rejection_modal(array $request, string $modal_id): string {
        $notes = trim((string) ($request['committee_notes'] ?? ''));
        $message = $notes !== '' ? nl2br(esc_html($notes)) : esc_html__('No hay observaciones registradas.', 'bankitos');
        ob_start(); ?>
        <div id="<?php echo esc_attr($modal_id); ?>" class="bankitos-modal" hidden>
          <div class="bankitos-modal__backdrop" data-bankitos-close></div>
          <div class="bankitos-modal__body bankitos-credit-summary__modal">
            <button type="button" class="bankitos-modal__close" aria-label="<?php esc_attr_e('Cerrar', 'bankitos'); ?>" data-bankitos-close>&times;</button>
            <div class="bankitos-credit-summary__modal-content">
              <p class="bankitos-credit-summary__modal-tag"><?php esc_html_e('Observaciones del crédito', 'bankitos'); ?></p>
              <h4 class="bankitos-credit-summary__modal-title"><?php echo esc_html($request['display_name'] ?? ''); ?></h4>
              <div class="bankitos-credit-summary__modal-notes"><?php echo $message; ?></div>
            </div>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function render_payment_modal(array $request, string $modal_id, float $tasa): string {
        if (empty($request['approval_date'])) {
            return '';
        }

        $plan = self::build_payment_plan((float) $request['amount'], (int) $request['term_months'], $request['approval_date'], $tasa);
        if (!$plan) {
            return '';
        }

        ob_start(); ?>
        <div id="<?php echo esc_attr($modal_id); ?>" class="bankitos-modal" hidden>
          <div class="bankitos-modal__backdrop" data-bankitos-close></div>
          <div class="bankitos-modal__body bankitos-credit-summary__modal">
            <button type="button" class="bankitos-modal__close" aria-label="<?php esc_attr_e('Cerrar', 'bankitos'); ?>" data-bankitos-close>&times;</button>
            <div class="bankitos-credit-summary__modal-content">
              <p class="bankitos-credit-summary__modal-tag"><?php esc_html_e('Plan de pagos', 'bankitos'); ?></p>
              <h4 class="bankitos-credit-summary__modal-title"><?php echo esc_html($request['display_name'] ?? ''); ?></h4>
              <p class="bankitos-credit-summary__modal-subtitle">
                <?php printf(
                    /* translators: %s: interest rate percentage */
                    esc_html__('Tasa mensual del banco: %s%%', 'bankitos'),
                    esc_html(number_format_i18n($tasa, 2))
                ); ?>
              </p>
              <div class="bankitos-table-wrapper">
                <table class="bankitos-table bankitos-credit-summary__payments">
                  <thead>
                    <tr>
                      <th><?php esc_html_e('Fecha', 'bankitos'); ?></th>
                      <th><?php esc_html_e('Cuota', 'bankitos'); ?></th>
                      <th><?php esc_html_e('Saldo de crédito', 'bankitos'); ?></th>
                      <th><?php esc_html_e('Comprobante', 'bankitos'); ?></th>
                      <th><?php esc_html_e('Acción', 'bankitos'); ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($plan as $index => $row):
                        $input_id = sprintf('bk-payment-%d-%d', (int) $request['id'], $index);
                        ?>
                        <tr>
                          <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($row['date']))); ?></td>
                          <td><?php echo esc_html(self::format_currency($row['amount'])); ?></td>
                          <td><?php echo esc_html(self::format_currency($row['balance'])); ?></td>
                          <td>
                            <label class="screen-reader-text" for="<?php echo esc_attr($input_id); ?>"><?php esc_html_e('Subir comprobante', 'bankitos'); ?></label>
                            <input type="file" id="<?php echo esc_attr($input_id); ?>" name="receipt" accept="image/*" form="<?php echo esc_attr($input_id); ?>-form">
                          </td>
                          <td>
                            <form id="<?php echo esc_attr($input_id); ?>-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                              <?php echo wp_nonce_field('bankitos_credit_payment_submit', '_wpnonce', true, false); ?>
                              <input type="hidden" name="action" value="bankitos_credit_payment_submit">
                              <input type="hidden" name="request_id" value="<?php echo esc_attr($request['id']); ?>">
                              <input type="hidden" name="amount" value="<?php echo esc_attr($row['amount']); ?>">
                              <button type="submit" class="bankitos-btn bankitos-btn--primary"><?php esc_html_e('Registrar pago', 'bankitos'); ?></button>
                            </form>
                          </td>
                        </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function build_payment_plan(float $amount, int $months, string $approval_date, float $tasa): array {
        if ($amount <= 0 || $months <= 0) {
            return [];
        }

        $rate         = $tasa > 0 ? $tasa / 100 : 0.0;
        $base         = $months > 0 ? $amount / $months : 0.0;
        $plan         = [];
        $cursor       = $amount;

        for ($i = 1; $i <= $months; $i++) {
            $date = date('Y-m-d', strtotime("{$approval_date} +{$i} month"));
            $interest = $rate > 0 ? $cursor * $rate : 0.0;
            $installment = $base + $interest;
            $plan[] = [
                'date'    => $date,
                'amount'  => round($installment, 2),
                'balance' => round($cursor, 2),
            ];
            $cursor = max(0, $cursor - $base);
        }

        return $plan;
    }

    private static function inline_scripts(): string {
        ob_start(); ?>
        <script>
        (function(){
          var openers = document.querySelectorAll('[data-bankitos-open]');
          if(!openers.length){return;}
          function closeModal(modal){
            if(modal){ modal.setAttribute('hidden','hidden'); }
          }
          openers.forEach(function(btn){
            btn.addEventListener('click', function(){
              var id = btn.getAttribute('data-bankitos-open');
              var modal = document.getElementById(id);
              if(modal){ modal.removeAttribute('hidden'); }
            });
          });
          document.querySelectorAll('.bankitos-modal').forEach(function(modal){
            modal.querySelectorAll('[data-bankitos-close]').forEach(function(closer){
              closer.addEventListener('click', function(){ closeModal(modal); });
            });
            modal.addEventListener('click', function(ev){
              if(ev.target === modal){ closeModal(modal); }
            });
          });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}