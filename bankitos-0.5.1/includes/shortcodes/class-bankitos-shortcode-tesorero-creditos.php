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
            return '<div class="bankitos-form"><p>' . esc_html__('Inicia sesión para continuar.', 'bankitos') . '</p></div>';
        }
        if ($can_moderate && !current_user_can('approve_aportes')) {
            return '<div class="bankitos-form"><p>' . esc_html__('No tienes permisos para aprobar pagos.', 'bankitos') . '</p></div>';
        }
        $context = self::get_panel_context();
        if ($context['banco_id'] <= 0) {
            return '<div class="bankitos-form"><p>' . esc_html__('Debes pertenecer a un B@nko.', 'bankitos') . '</p></div>';
        }
        
        // Obtenemos solo los créditos aprobados, ya que solo estos reciben pagos
        $credits = array_filter(
            Bankitos_Credit_Requests::get_requests($context['banco_id']),
            static function ($row) {
                return $row['status'] === 'approved';
            }
        );
        $status_labels = Bankitos_Credit_Payments::get_status_labels();
        $payments      = self::collect_payments($credits, $status_labels);

        ob_start(); ?>
        <section class="bankitos-credit-review">
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
              <?php $is_first = true; foreach ($payments as $payment): ?>
                <?php echo self::render_payment_item($payment, $can_moderate, $is_first); ?>
              <?php $is_first = false; endforeach; ?>
            </div>
        <?php endif; ?>
      </section>
      <?php echo self::modal_markup(); ?>
      <?php echo self::inline_scripts(); ?>
      <?php
        return ob_get_clean();
    }

    protected static function collect_payments(array $credits, array $status_labels): array {
        $payments = [];
        foreach ($credits as $credit) {
            $credit_payments = Bankitos_Credit_Payments::get_request_payments((int) $credit['id']);
            if (!$credit_payments) {
                continue;
            }
            foreach ($credit_payments as $payment) {
                $receipt = (!empty($payment['attachment_id']) && class_exists('BK_Credit_Payments_Handler'))
                    ? BK_Credit_Payments_Handler::get_receipt_download_url((int) $payment['id'])
                    : '';
                $mime = !empty($payment['attachment_id']) ? get_post_mime_type((int) $payment['attachment_id']) : '';
                $is_image = $mime && strpos($mime, 'image/') === 0;
                $payments[] = [
                    'credit'       => $credit,
                    'payment'      => $payment,
                    'receipt'      => $receipt,
                    'is_image'     => $is_image,
                    'status_label' => $status_labels[$payment['status']] ?? $payment['status'],
                ];
            }
        }
        return $payments;
    }

    protected static function render_payment_item(array $data, bool $can_moderate, bool $is_first): string {
        $credit   = $data['credit'];
        $payment  = $data['payment'];
        $receipt  = $data['receipt'] ?? '';
        $is_image = (bool) ($data['is_image'] ?? false);
        $status   = $payment['status'];

        $pill_class = 'bankitos-pill';
        if ($status === 'approved') {
            $pill_class .= ' bankitos-pill--accepted';
        } elseif ($status === 'rejected') {
            $pill_class .= ' bankitos-pill--rejected';
        } else {
            $pill_class .= ' bankitos-pill--pending';
        }

        $term_label    = sprintf(_n('%s mes', '%s meses', (int) $credit['term_months'], 'bankitos'), number_format_i18n((int) $credit['term_months']));
        $request_date  = !empty($credit['request_date']) ? date_i18n(get_option('date_format'), strtotime($credit['request_date'])) : '—';
        $payment_date  = date_i18n(get_option('date_format'), strtotime($payment['created_at']));
        $member_name   = $credit['display_name'] ?? '';
        $status_label  = $data['status_label'] ?? $status;
        $item_classes  = 'bankitos-accordion__item bankitos-credit-payment bankitos-credit-payment--' . sanitize_html_class($status);
        $title_attr    = esc_attr__('Comprobante de pago', 'bankitos');
        
        ob_start(); ?>
        <details class="<?php echo esc_attr($item_classes); ?>" role="listitem" <?php echo $is_first ? 'open' : ''; ?>>
          <summary class="bankitos-accordion__summary">
            <div class="bankitos-accordion__title">
              <span class="bankitos-accordion__amount"><?php echo esc_html(self::format_currency((float) $payment['amount'])); ?></span>
              <span class="bankitos-accordion__name"><?php echo esc_html($member_name ?: '—'); ?></span>
            </div>
            <div class="bankitos-accordion__meta">
              <span><?php echo esc_html($payment_date); ?></span>
              <span class="<?php echo esc_attr($pill_class); ?>"><?php echo esc_html($status_label); ?></span>
              <span class="bankitos-accordion__chevron" aria-hidden="true"></span>
            </div>
          </summary>
          <div class="bankitos-accordion__content">
            <div class="bankitos-credit-payment__resume">
              <div class="bankitos-credit-payment__credit">
                <p class="bankitos-credit-payment__credit-label"><?php esc_html_e('Crédito', 'bankitos'); ?></p>
                <p class="bankitos-credit-payment__credit-name"><?php echo esc_html($member_name ?: '—'); ?></p>
                <div class="bankitos-credit-payment__credit-meta">
                  <span><?php echo esc_html(self::format_currency((float) $credit['amount'])); ?></span>
                  <span><?php echo esc_html($term_label); ?></span>
                  <span><?php echo esc_html($request_date); ?></span>
                </div>
              </div>
              <span class="bankitos-pill bankitos-pill--accepted"><?php esc_html_e('Crédito aprobado', 'bankitos'); ?></span>
            </div>
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
              <div>
                <dt><?php esc_html_e('Monto del crédito', 'bankitos'); ?></dt>
                <dd><?php echo esc_html(self::format_currency((float) $credit['amount'])); ?></dd>
              </div>
              <div>
                <dt><?php esc_html_e('Plazo', 'bankitos'); ?></dt>
                <dd><?php echo esc_html($term_label); ?></dd>
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