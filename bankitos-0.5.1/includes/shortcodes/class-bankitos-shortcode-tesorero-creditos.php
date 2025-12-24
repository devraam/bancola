<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Shortcode_Tesorero_Creditos extends Bankitos_Shortcode_Panel_Base {

    public static function register(): void {
        self::register_shortcode('bankitos_tesorero_creditos');
    }

    public static function render($atts = [], $content = null): string {
        return self::render_list(true);
    }

    protected static function render_list(bool $can_moderate): string {
        if (!is_user_logged_in()) {
            return '<div class="bankitos-form bankitos-panel"><p>' . esc_html__('Inicia sesión para continuar.', 'bankitos') . '</p></div>';
        }
        if ($can_moderate && !current_user_can('approve_aportes')) {
            return '<div class="bankitos-form bankitos-panel"><p>' . esc_html__('No tienes permisos para aprobar pagos.', 'bankitos') . '</p></div>';
        }
        $context = self::get_panel_context();
        if ($context['banco_id'] <= 0) {
            return '<div class="bankitos-form bankitos-panel"><p>' . esc_html__('Debes pertenecer a un B@nko.', 'bankitos') . '</p></div>';
        }
        
        $tasa = isset($context['meta']['tasa']) ? (float) $context['meta']['tasa'] : 0.0;
        // Obtenemos solo los créditos aprobados, ya que solo estos reciben pagos
        $credits = array_filter(
            Bankitos_Credit_Requests::get_requests($context['banco_id']),
            static function ($row) {
                return in_array($row['status'], ['disbursed', 'approved'], true);
            }
        );
        $status_labels = Bankitos_Credit_Payments::get_status_labels();
        $payments      = self::collect_grouped_payments($credits, $status_labels, $tasa);

        ob_start(); ?>
        <section class="bankitos-credit-review bankitos-panel">
          <div class="bankitos-credit-review__header">
            <h3><?php esc_html_e('Pagos de créditos', 'bankitos'); ?></h3>
            <p><?php echo $can_moderate ? esc_html__('Aprueba o rechaza los pagos enviados por los socios.', 'bankitos') : esc_html__('Consulta los pagos registrados en el banco.', 'bankitos'); ?></p>
          </div>
          
        <?php echo self::top_notice_from_query(); ?>
        
        <?php if (!$credits): ?>
            <p class="bankitos-panel__message"><?php esc_html_e('No hay créditos aprobados activos.', 'bankitos'); ?></p>
        <?php elseif (!$payments): ?>
            <p class="bankitos-panel__message"><?php esc_html_e('Aún no se han registrado pagos para los créditos aprobados.', 'bankitos'); ?></p>
        <?php else: ?>
            <div class="bankitos-credit-review__accordion bankitos-accordion" role="list">
              <?php $is_first = true; foreach ($payments as $payment_group): ?>
                <?php echo self::render_credit_group($payment_group, $can_moderate, $is_first); ?>
              <?php $is_first = false; endforeach; ?>
            </div>
        <?php endif; ?>
      </section>
      <?php echo self::modal_markup(); ?>
      <?php echo self::inline_scripts(); ?>
      <?php
        return ob_get_clean();
    }

    protected static function collect_grouped_payments(array $credits, array $status_labels, float $tasa): array {
        $payments = [];
        foreach ($credits as $credit) {
            $credit_payments = Bankitos_Credit_Payments::get_request_payments((int) $credit['id']);
            if (!$credit_payments) {
                continue;
            }

            $plan = self::build_payment_plan((float) $credit['amount'], (int) $credit['term_months'], self::get_schedule_base_date($credit), $tasa);
            $prepared = self::prepare_payments($credit_payments, $status_labels, $plan);
            if (!$prepared) {
                continue;
            }

            $payments[] = [
                'credit'   => $credit,
                'payments' => $prepared,
            ];
        }
        return $payments;
    }

    protected static function prepare_payments(array $credit_payments, array $status_labels, array $plan): array {
        $prepared = [];
        foreach ($credit_payments as $payment) {
            $receipt = (!empty($payment['attachment_id']) && class_exists('BK_Credit_Payments_Handler'))
                ? BK_Credit_Payments_Handler::get_receipt_download_url((int) $payment['id'])
                : '';
            $mime = !empty($payment['attachment_id']) ? get_post_mime_type((int) $payment['attachment_id']) : '';
            $is_image = $mime && strpos($mime, 'image/') === 0;
            $installment_index = self::find_installment_for_amount((float) $payment['amount'], $plan);

        $prepared[] = [
                'credit_payment'     => $payment,
                'receipt'            => $receipt,
                'is_image'           => $is_image,
                'status_label'       => $status_labels[$payment['status']] ?? $payment['status'],
                'installment_number' => $installment_index !== null ? $installment_index + 1 : null,
            ];
        }

        return self::sort_payments_by_installment($prepared);
    }

    protected static function render_credit_group(array $data, bool $can_moderate, bool $is_first): string {
        $credit    = $data['credit'];
        $payments  = $data['payments'] ?? [];
        $member    = $credit['display_name'] ?? '';
        $term_label   = sprintf(_n('%s mes', '%s meses', (int) $credit['term_months'], 'bankitos'), number_format_i18n((int) $credit['term_months']));
        $request_date = !empty($credit['request_date']) ? date_i18n(get_option('date_format'), strtotime($credit['request_date'])) : '—';
        $status_meta  = self::get_credit_status_meta($credit['status'] ?? '');

        ob_start(); ?>
        <details class="bankitos-accordion__item bankitos-credit-payment__credit" role="listitem" <?php echo $is_first ? 'open' : ''; ?>>
          <summary class="bankitos-accordion__summary">
            <div class="bankitos-accordion__title">
              <span class="bankitos-accordion__amount"><?php echo esc_html(self::format_currency((float) $credit['amount'])); ?></span>
              <span class="bankitos-accordion__name"><?php echo esc_html($member ?: '—'); ?></span>
            </div>
            <div class="bankitos-accordion__meta">
              <span><?php echo esc_html($request_date); ?></span>
              <span class="<?php echo esc_attr($status_meta['class']); ?>"><?php echo esc_html($status_meta['label']); ?></span>
              <span class="bankitos-accordion__chevron" aria-hidden="true"></span>
            </div>
          </summary>
          <div class="bankitos-accordion__content">
            <div class="bankitos-credit-payment__resume">
              <div class="bankitos-credit-payment__credit">
                <p class="bankitos-credit-payment__credit-label"><?php esc_html_e('Crédito', 'bankitos'); ?></p>
                <p class="bankitos-credit-payment__credit-name"><?php echo esc_html($member ?: '—'); ?></p>
                <div class="bankitos-credit-payment__credit-meta">
                  <span><?php echo esc_html(self::format_currency((float) $credit['amount'])); ?></span>
                  <span><?php echo esc_html($term_label); ?></span>
                  <span><?php echo esc_html($request_date); ?></span>
                </div>
              </div>
              <span class="bankitos-pill bankitos-pill--accepted"><?php esc_html_e('Pagos agrupados por crédito', 'bankitos'); ?></span>
            </div>
            <div class="bankitos-credit-review__payments" role="list">
              <?php if (!$payments): ?>
                <p class="bankitos-credit-payment__note"><?php esc_html_e('No hay pagos registrados para este crédito.', 'bankitos'); ?></p>
              <?php else: ?>
                <?php foreach ($payments as $payment_data): ?>
                  <?php echo self::render_payment_item($credit, $payment_data, $can_moderate); ?>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            </div>
        </details>
        <?php
        return ob_get_clean();
    }

    protected static function render_payment_item(array $credit, array $data, bool $can_moderate): string {
        $payment   = $data['credit_payment'];
        $receipt   = $data['receipt'] ?? '';
        $is_image  = (bool) ($data['is_image'] ?? false);
        $status    = $payment['status'];
        $status_label = $data['status_label'] ?? $status;
        $installment_number = $data['installment_number'];

        $pill_class = 'bankitos-pill';
        if ($status === 'approved') {
            $pill_class .= ' bankitos-pill--accepted';
        } elseif ($status === 'rejected') {
            $pill_class .= ' bankitos-pill--rejected';
        } else {
            $pill_class .= ' bankitos-pill--pending';
        }

        $payment_date = date_i18n(get_option('date_format'), strtotime($payment['created_at']));
        $member_name  = $credit['display_name'] ?? '';
        $item_classes = 'bankitos-accordion__item bankitos-credit-payment bankitos-credit-payment--' . sanitize_html_class($status);
        $title_attr   = esc_attr__('Comprobante de pago', 'bankitos');
        $installment_label = $installment_number
            ? sprintf(__('Cuota %s', 'bankitos'), number_format_i18n($installment_number))
            : __('Pago sin cuota asignada', 'bankitos');

        ob_start(); ?>
        <details class="<?php echo esc_attr($item_classes); ?>" role="listitem">
          <summary class="bankitos-accordion__summary">
            <div class="bankitos-accordion__title">
              <span class="bankitos-accordion__amount"><?php echo esc_html(self::format_currency((float) $payment['amount'])); ?></span>
              <span class="bankitos-accordion__name"><?php echo esc_html($installment_label); ?></span>
            </div>
            <div class="bankitos-accordion__meta">
              <span><?php echo esc_html($payment_date); ?></span>
              <span class="<?php echo esc_attr($pill_class); ?>"><?php echo esc_html($status_label); ?></span>
              <span class="bankitos-accordion__chevron" aria-hidden="true"></span>
            </div>
          </summary>
          <div class="bankitos-accordion__content">
            <dl class="bankitos-accordion__grid bankitos-credit-payment__grid">
              <div>
                <dt><?php esc_html_e('Monto del pago', 'bankitos'); ?></dt>
                <dd><?php echo esc_html(self::format_currency((float) $payment['amount'])); ?></dd>
              </div>
              <div>
                <dt><?php esc_html_e('Fecha del pago', 'bankitos'); ?></dt>
                <dd><?php echo esc_html($payment_date); ?></dd>
              </div>
              <div>
                <dt><?php esc_html_e('Cuota', 'bankitos'); ?></dt>
                <dd><?php echo esc_html($installment_label); ?></dd>
              </div>
              <div>
                <dt><?php esc_html_e('Estado', 'bankitos'); ?></dt>
                <dd><span class="<?php echo esc_attr($pill_class); ?>"><?php echo esc_html($status_label); ?></span></dd>
              </div>
              <div>
                <dt><?php esc_html_e('Comprobante', 'bankitos'); ?></dt>
                <dd>
                  <?php if ($receipt): ?>
                    <button type="button" class="bankitos-link bankitos-link--button bankitos-receipt-link" data-receipt="<?php echo esc_url($receipt); ?>" data-is-image="<?php echo $is_image ? '1' : '0'; ?>" data-title="<?php echo $title_attr; ?>"><?php esc_html_e('Ver comprobante', 'bankitos'); ?></button>
                  <?php else: ?>
                    <?php esc_html_e('No disponible', 'bankitos'); ?>
                  <?php endif; ?>
                </dd>
              </div>
            </dl>
            <?php if ($can_moderate): ?>
              <div class="bankitos-accordion__actions bankitos-credit-payment__actions" aria-label="<?php esc_attr_e('Acciones del pago', 'bankitos'); ?>">
                <?php if ($status === 'pending'): ?>
                  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php echo wp_nonce_field('bankitos_credit_payment_mod', '_wpnonce', true, false); ?>
                    <input type="hidden" name="action" value="bankitos_credit_payment_approve">
                    <input type="hidden" name="payment_id" value="<?php echo esc_attr($payment['id']); ?>">
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url(self::get_current_url()); ?>">
                    <button type="submit" class="bankitos-btn bankitos-btn--small"><?php esc_html_e('Aprobar', 'bankitos'); ?></button>
                  </form>
                  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php echo wp_nonce_field('bankitos_credit_payment_mod', '_wpnonce', true, false); ?>
                    <input type="hidden" name="action" value="bankitos_credit_payment_reject">
                    <input type="hidden" name="payment_id" value="<?php echo esc_attr($payment['id']); ?>">
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url(self::get_current_url()); ?>">
                    <button type="submit" class="bankitos-btn bankitos-btn--small bankitos-btn--danger"><?php esc_html_e('Rechazar', 'bankitos'); ?></button>
                  </form>
                <?php else: ?>
                  <p class="bankitos-credit-payment__note"><?php esc_html_e('Este pago ya fue revisado.', 'bankitos'); ?></p>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </details>
        <?php
        return ob_get_clean();
    }

    protected static function sort_payments_by_installment(array $payments): array {
        usort($payments, static function ($a, $b) {
            $installment_a = (int) ($a['installment_number'] ?? 0);
            $installment_b = (int) ($b['installment_number'] ?? 0);

            if ($installment_a && $installment_b && $installment_a !== $installment_b) {
                return $installment_a <=> $installment_b;
            }
            if ($installment_a && !$installment_b) {
                return -1;
            }
            if (!$installment_a && $installment_b) {
                return 1;
            }

            $priority_a = Bankitos_Credit_Payments::get_status_priority($a['credit_payment']['status'] ?? '');
            $priority_b = Bankitos_Credit_Payments::get_status_priority($b['credit_payment']['status'] ?? '');
            if ($priority_a !== $priority_b) {
                return $priority_b <=> $priority_a;
            }

            return strcmp($b['credit_payment']['created_at'] ?? '', $a['credit_payment']['created_at'] ?? '');
        });

        return $payments;
    }

    protected static function find_installment_for_amount(float $amount, array $plan): ?int {
        if (!$plan) {
            return null;
        }

        $best_index = null;
        $best_diff  = null;
        $tolerance  = 1.0;

        foreach ($plan as $index => $row) {
            if (!isset($row['amount'])) {
                continue;
            }
            $diff = abs((float) $row['amount'] - $amount);
            if ($diff > $tolerance) {
                continue;
            }
            if ($best_diff === null || $diff < $best_diff) {
                $best_diff = $diff;
                $best_index = (int) $index;
            }
        }

        return $best_index;
    }

    protected static function get_schedule_base_date(array $credit): string {
        if (!empty($credit['disbursement_date'])) {
            return (string) $credit['disbursement_date'];
        }

        return (string) ($credit['approval_date'] ?? '');
    }

    protected static function get_credit_status_meta(string $status): array {
        $map = [
            'disbursed' => [
                'label' => __('Crédito desembolsado', 'bankitos'),
                'class' => 'bankitos-pill bankitos-pill--accepted',
            ],
            'disbursement_pending' => [
                'label' => __('Pendiente de desembolso', 'bankitos'),
                'class' => 'bankitos-pill bankitos-pill--pending',
            ],
            'approved' => [
                'label' => __('Crédito aprobado', 'bankitos'),
                'class' => 'bankitos-pill bankitos-pill--accepted',
            ],
        ];

        return $map[$status] ?? [
            'label' => $status,
            'class' => 'bankitos-pill bankitos-pill--pending',
        ];
    }
    
    protected static function build_payment_plan(float $amount, int $months, string $approval_date, float $tasa): array {
        if ($amount <= 0 || $months <= 0 || empty($approval_date)) {
            return [];
        }

        $rate   = $tasa > 0 ? $tasa / 100 : 0.0;
        $base   = $months > 0 ? $amount / $months : 0.0;
        $plan   = [];
        $cursor = $amount;

        for ($i = 1; $i <= $months; $i++) {
            $date = date('Y-m-d', strtotime("{$approval_date} +{$i} month"));
            $interest = $rate > 0 ? $cursor * $rate : 0.0;
            $installment = $base + $interest;
            $plan[] = [
                'date'      => $date,
                'amount'    => round($installment, 2),
                'balance'   => round($cursor, 2),
                'interest'  => round($interest, 2),
                'principal' => round($base, 2),
            ];
            $cursor = max(0, $cursor - $base);
        }

        return $plan;
    }

    protected static function modal_markup(): string {
        return '<div id="bankitos-modal" class="bankitos-modal" hidden><div class="bankitos-modal__backdrop"></div><div class="bankitos-modal__body"><button type="button" class="bankitos-modal__close" aria-label="' . esc_attr__('Cerrar', 'bankitos') . '">&times;</button><p class="bankitos-modal__error" hidden></p><iframe class="bankitos-modal__frame" src="" title="' . esc_attr__('Comprobante', 'bankitos') . '" hidden></iframe><img src="" alt="" loading="lazy" hidden></div></div>';
    }

    protected static function inline_scripts(): string {
        ob_start(); ?>
        <script>
        (function(){
          var modal = document.getElementById('bankitos-modal');
          if(!modal){return;}
          var backdrop = modal.querySelector('.bankitos-modal__backdrop');
          var closeBtn = modal.querySelector('.bankitos-modal__close');
          var frame = modal.querySelector('.bankitos-modal__frame');
          var img = modal.querySelector('img');
          var errorBox = modal.querySelector('.bankitos-modal__error');

          function close(){
            modal.setAttribute('hidden','hidden'); 
            if(frame){
              frame.removeAttribute('src');
              frame.setAttribute('hidden', '');
              frame.setAttribute('aria-hidden', 'true');
              frame.removeAttribute('title');
            } 
            if(img){
              img.removeAttribute('src');
              img.setAttribute('hidden', '');
            }
            if(errorBox){
              errorBox.textContent = '';
              errorBox.setAttribute('hidden', ''); 
            }
          }
          
          [backdrop, closeBtn].forEach(function(el){ if(el){ el.addEventListener('click', close); }});

          function showError(message){
            if(!errorBox){ return; }
            errorBox.textContent = message || '';
            errorBox.removeAttribute('hidden');
          }

          function resetContent(){
            if(img){
              img.removeAttribute('src');
              img.setAttribute('hidden', '');
            }
            if(frame){
              frame.removeAttribute('src');
              frame.setAttribute('hidden', '');
              frame.setAttribute('aria-hidden', 'true');
            }
            if(errorBox){
              errorBox.textContent = '';
              errorBox.setAttribute('hidden', '');
            }
          }

          function openReceipt(receiptUrl, isImage, title){
            if (!receiptUrl){ return; }

            resetContent();

            if (isImage && img) {
              img.onload = function(){
                img.onload = null;
                img.onerror = null;
                img.removeAttribute('hidden');
                modal.removeAttribute('hidden');
              };
              img.onerror = function(){
                img.onload = null;
                img.onerror = null;
                img.setAttribute('hidden', '');
                showError('No se pudo cargar el comprobante.');
                modal.removeAttribute('hidden');
              };
              img.alt = title || '';
              img.src = receiptUrl;
              modal.removeAttribute('hidden');
              return;
            }

            if (frame) {
              frame.onload = function(){
                frame.onload = null;
                frame.onerror = null;
              };
              frame.onerror = function(){
                frame.onload = null;
                frame.onerror = null;
                frame.setAttribute('hidden', '');
                showError('No se pudo cargar el comprobante.');
              };
              frame.removeAttribute('aria-hidden');
              frame.removeAttribute('hidden');
              frame.title = title || '';
              frame.src = receiptUrl;
              modal.removeAttribute('hidden');
              return;
            }
            
            showError('No se pudo cargar el comprobante.');
            modal.removeAttribute('hidden');
          }

          document.addEventListener('click', function(ev){
            var link = ev.target.closest('.bankitos-receipt-link');
            if (!link){ return; }
            ev.preventDefault();
            var receiptUrl = link.getAttribute('data-receipt');
            var isImage = link.getAttribute('data-is-image') === '1';
            var title = link.getAttribute('data-title') || '';
            openReceipt(receiptUrl, isImage, title);
          });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}