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
            <div class="bankitos-credit-summary__accordion bankitos-accordion" role="list">
              <?php $is_first = true; foreach ($requests as $request):
                  $type_label   = $types[$request['credit_type']] ?? ucfirst($request['credit_type']);
                  $status_class = self::get_status_class($request['status']);
                  $status_label = self::get_status_label($request['status']);
                  $modal_id     = 'bk-modal-credit-' . (int) $request['id'];
                  $modal_markup = self::render_modal_for_request($request, $modal_id, $tasa);
                  if ($modal_markup) {
                      $modals[] = $modal_markup;
                  }
                  ?>
                  <details class="bankitos-accordion__item bankitos-credit-summary__item" role="listitem" <?php echo $is_first ? 'open' : ''; ?>>
                    <summary class="bankitos-accordion__summary bankitos-credit-summary__summary">
                      <div class="bankitos-credit-summary__summary-main">
                        <span class="bankitos-credit-summary__amount"><?php echo esc_html(self::format_currency((float) $request['amount'])); ?></span>
                        <span class="bankitos-credit-summary__type"><?php echo esc_html($type_label); ?></span>
                      </div>
                      <div class="bankitos-credit-summary__summary-meta">
                        <span class="bankitos-pill <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span>
                        <span class="bankitos-credit-summary__date"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($request['request_date']))); ?></span>
                        <span class="bankitos-accordion__chevron" aria-hidden="true"></span>
                      </div>
                    </summary>
                    <div class="bankitos-accordion__content bankitos-credit-summary__content">
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
                    </div>
                  </details>
              <?php $is_first = false; endforeach; ?>
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

        // Recuperar los pagos ya registrados en BD
        $payments           = Bankitos_Credit_Payments::get_request_payments((int) $request['id']);
        $payments_by_amount = self::index_payments_by_amount($payments);
        
        $total_interest     = array_reduce($plan, static function ($carry, $row) {
            return $carry + (float) $row['interest'];
        }, 0.0);
        $interest_paid      = 0.0;
        $unmatched_payments = 0;

        ob_start(); ?>
        <div id="<?php echo esc_attr($modal_id); ?>" class="bankitos-modal" hidden>
          <div class="bankitos-modal__backdrop" data-bankitos-close></div>
          <div class="bankitos-modal__body bankitos-credit-summary__modal" role="dialog" aria-modal="true">
            <button type="button" class="bankitos-modal__close" aria-label="<?php esc_attr_e('Cerrar', 'bankitos'); ?>" data-bankitos-close>&times;</button>
            <div class="bankitos-credit-summary__modal-content">
              <div class="bankitos-credit-summary__modal-header">
                <div>
                  <p class="bankitos-credit-summary__modal-tag"><?php esc_html_e('Plan de pagos', 'bankitos'); ?></p>
                  <h4 class="bankitos-credit-summary__modal-title"><?php echo esc_html($request['display_name'] ?? ''); ?></h4>
                  <p class="bankitos-credit-summary__modal-subtitle">
                    <?php printf(
                        /* translators: %s: interest rate percentage */
                        esc_html__('Tasa mensual del banco: %s%%', 'bankitos'),
                        esc_html(number_format_i18n($tasa, 2))
                    ); ?>
                  </p>
                </div>
                <div class="bankitos-credit-summary__modal-badges">
                  <span class="bankitos-pill bankitos-pill--accepted"><?php echo esc_html(self::format_currency((float) $request['amount'])); ?></span>
                  <span class="bankitos-pill"><?php printf(esc_html__('%s meses', 'bankitos'), esc_html(number_format_i18n((int) $request['term_months']))); ?></span>
                </div>
              </div>
              
              <div class="bankitos-credit-summary__payments-accordion bankitos-accordion" role="list">
                <?php $opened_item = false; foreach ($plan as $index => $row):
                    // Determinar el estado comparando el plan teórico con los pagos en BD
                    $installment = self::get_installment_state($row, $payments_by_amount);
                    
                    $state_label = $installment['state_label'] ?: esc_html__('Pendiente de pago', 'bankitos');
                    $state_class = 'bankitos-pill';
                    
                    if ($installment['state'] === 'approved') {
                        $state_class .= ' bankitos-pill--accepted';
                        $interest_paid += (float) $row['interest'];
                    } elseif ($installment['state'] === 'pending') {
                        $state_class .= ' bankitos-pill--pending';
                        // Forzar etiqueta específica si está pendiente
                        $state_label = esc_html__('Aprobación pendiente', 'bankitos');
                    } elseif ($installment['state'] === 'rejected') {
                        $state_class .= ' bankitos-pill--rejected';
                    } else {
                        $state_class .= ' bankitos-pill--pending';
                    }
                    
                    // Abrir la primera cuota pendiente o rechazada
                    $should_open = !$opened_item && ($installment['state'] !== 'approved' || $index === 0);
                    $opened_item = $opened_item || $should_open;
                    
                    $unique_key = (int) $request['id'] . '-' . $index;
                    $input_id   = 'bk-file-' . $unique_key;
                    ?>
                    
                    <details class="bankitos-accordion__item bankitos-credit-summary__payment-item bankitos-credit-summary__payment-item--<?php echo esc_attr($installment['state']); ?>" role="listitem" <?php echo $should_open ? 'open' : ''; ?>>
                      <summary class="bankitos-accordion__summary bankitos-credit-summary__payment-summary">
                        <div class="bankitos-accordion__title">
                          <span class="bankitos-credit-summary__amount"><?php echo esc_html(self::format_currency($row['amount'])); ?></span>
                          <span class="bankitos-credit-summary__payment-number"><?php printf(esc_html__('Cuota %s', 'bankitos'), esc_html(number_format_i18n($index + 1))); ?></span>
                        </div>
                        <div class="bankitos-accordion__meta">
                          <span><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($row['date']))); ?></span>
                          <span class="<?php echo esc_attr($state_class); ?>"><?php echo esc_html($state_label); ?></span>
                          <span class="bankitos-accordion__chevron" aria-hidden="true"></span>
                        </div>
                      </summary>

                      <div class="bankitos-accordion__content bankitos-credit-summary__payment-content">
                        <?php if ($installment['can_upload']): ?>
                            <form class="bankitos-credit-summary__form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                        <?php endif; ?>

                        <dl class="bankitos-accordion__grid bankitos-credit-summary__payment-grid">
                          <div>
                            <dt><?php esc_html_e('Fecha programada', 'bankitos'); ?></dt>
                            <dd><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($row['date']))); ?></dd>
                          </div>
                          <div>
                            <dt><?php esc_html_e('Saldo de crédito', 'bankitos'); ?></dt>
                            <dd><?php echo esc_html(self::format_currency($row['balance'])); ?></dd>
                          </div>
                          <div>
                            <dt><?php esc_html_e('Cuota total', 'bankitos'); ?></dt>
                            <dd>
                              <span class="bankitos-credit-summary__amount"><?php echo esc_html(self::format_currency($row['amount'])); ?></span>
                              <span class="bankitos-credit-summary__help"><?php printf(esc_html__('(Capital: %s + Interés: %s)', 'bankitos'), esc_html(self::format_currency($row['principal'])), esc_html(self::format_currency($row['interest']))); ?></span>
                            </dd>
                          </div>
                          
                          <div>
                            <dt><?php esc_html_e('Comprobante', 'bankitos'); ?></dt>
                            <dd class="bankitos-credit-summary__upload">
                              <?php if ($installment['receipt']): ?>
                                <a class="bankitos-link" href="<?php echo esc_url($installment['receipt']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Ver comprobante', 'bankitos'); ?></a>
                              <?php endif; ?>

                              <?php if ($installment['can_upload']): ?>
                                <label class="bankitos-file">
                                  <input type="file" id="<?php echo esc_attr($input_id); ?>" name="receipt" accept="image/*,application/pdf,.pdf" required>
                                  <span class="bankitos-file__label" data-default-label><?php esc_html_e('Elegir archivo', 'bankitos'); ?></span>
                                </label>
                                <span class="bankitos-credit-summary__help bankitos-credit-summary__help--error" data-upload-error hidden><?php esc_html_e('Sube un archivo válido (imagen o PDF).', 'bankitos'); ?></span>
                              <?php elseif ($installment['state'] === 'pending'): ?>
                                <span class="bankitos-credit-summary__help"><?php esc_html_e('Archivo enviado. Esperando revisión.', 'bankitos'); ?></span>
                              <?php else: ?>
                                <span class="bankitos-credit-summary__help"><?php echo esc_html($state_label); ?></span>
                              <?php endif; ?>
                            </dd>
                          </div>
                        </dl>

                        <div class="bankitos-credit-summary__payment-actions">
                          <?php if ($installment['state'] === 'pending'): ?>
                            <div class="bankitos-credit-summary__alert" style="margin:0; padding:0.5rem; width:100%; text-align:right; border-color: #f59e0b; background-color: #fffbeb;">
                                <strong style="color: #b45309;"><?php esc_html_e('Pago registrado', 'bankitos'); ?></strong>
                                <br><small style="color: #b45309;"><?php esc_html_e('Tu comprobante está siendo validado por el tesorero.', 'bankitos'); ?></small>
                            </div>
                          <?php elseif ($installment['state'] === 'approved'): ?>
                            <span class="bankitos-pill bankitos-pill--accepted"><?php esc_html_e('Cuota pagada exitosamente', 'bankitos'); ?></span>
                          <?php else: ?>
                            <?php echo wp_nonce_field('bankitos_credit_payment_submit', '_wpnonce', true, false); ?>
                            <input type="hidden" name="action" value="bankitos_credit_payment_submit">
                            <input type="hidden" name="request_id" value="<?php echo esc_attr($request['id']); ?>">
                            <input type="hidden" name="amount" value="<?php echo esc_attr(number_format((float)$row['amount'], 2, '.', '')); ?>">
                            <input type="hidden" name="installment_date" value="<?php echo esc_attr($row['date']); ?>">
                            <input type="hidden" name="redirect_to" value="<?php echo esc_url(self::get_current_url()); ?>">
                            
                            <button type="submit" class="bankitos-btn bankitos-btn--primary" data-bankitos-submit disabled aria-disabled="true">
                                <?php echo $installment['state'] === 'rejected' ? esc_html__('Reintentar pago', 'bankitos') : esc_html__('Registrar pago', 'bankitos'); ?>
                            </button>
                          <?php endif; ?>
                        </div>

                        <?php if ($installment['can_upload']): ?>
                            </form>
                        <?php endif; ?>
                      </div>
                    </details>
                <?php endforeach; ?>
                <?php $unmatched_payments = array_sum(array_map('count', $payments_by_amount)); ?>
              </div>

              <div class="bankitos-credit-summary__overview">
                <div class="bankitos-credit-summary__overview-card">
                  <p class="bankitos-credit-summary__foot-label"><?php esc_html_e('Total del crédito', 'bankitos'); ?></p>
                  <p class="bankitos-credit-summary__foot-value"><?php echo esc_html(self::format_currency((float) $request['amount'])); ?></p>
                </div>
                <div class="bankitos-credit-summary__overview-card">
                  <p class="bankitos-credit-summary__foot-label"><?php esc_html_e('Intereses pagados', 'bankitos'); ?></p>
                  <p class="bankitos-credit-summary__foot-value"><?php echo esc_html(self::format_currency($interest_paid)); ?></p>
                </div>
                <div class="bankitos-credit-summary__overview-card">
                  <p class="bankitos-credit-summary__foot-label"><?php esc_html_e('Interés proyectado', 'bankitos'); ?></p>
                  <p class="bankitos-credit-summary__foot-value"><?php echo esc_html(self::format_currency($total_interest)); ?></p>
                </div>
              </div>

              <?php if ($unmatched_payments > 0): ?>
                <div class="bankitos-credit-summary__alert">
                  <p><?php printf(esc_html(_n('Tienes %s pago registrado adicional en revisión (monto no coincide exactamente con ninguna cuota).', 'Tienes %s pagos registrados adicionales en revisión.', $unmatched_payments, 'bankitos')), esc_html(number_format_i18n($unmatched_payments))); ?></p>
                </div>
              <?php endif; ?>
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

    private static function index_payments_by_amount(array $payments): array {
        $grouped = [];
        foreach ($payments as $payment) {
            // Normalizamos la llave para agrupar pagos
            $key = self::normalize_amount((float) $payment['amount']);
            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $payment;
        }

        // Ordenar pagos del más antiguo al más reciente
        foreach ($grouped as $key => $list) {
            usort($list, static function ($a, $b) {
                return strcmp($a['created_at'], $b['created_at']);
            });
            $grouped[$key] = $list;
        }

        return $grouped;
    }

    private static function get_installment_state(array $row, array &$payments_by_amount): array {
        // Buscar si existe un pago en DB que coincida con el monto de esta cuota
        $payment = self::shift_payment_for_amount((float) $row['amount'], $payments_by_amount);
        $status_labels = Bankitos_Credit_Payments::get_status_labels();
        $state = $payment ? self::normalize_state((string) ($payment['status'] ?? '')) : 'open';
        
        $receipt = '';
        if ($payment && !empty($payment['attachment_id']) && class_exists('BK_Credit_Payments_Handler')) {
            $receipt = BK_Credit_Payments_Handler::get_receipt_download_url((int) $payment['id']);
        }

        // Si el estado es pending o approved, NO se debe poder subir otro archivo
        $can_upload = ($state === 'open' || $state === 'rejected');

        return [
            'state'       => $state,
            'state_label' => $status_labels[$state] ?? '',
            'can_upload'  => $can_upload,
            'receipt'     => $receipt,
        ];
    }

    private static function shift_payment_for_amount(float $target_amount, array &$payments_by_amount): ?array {
        // 1. Intento exacto
        $key = self::normalize_amount($target_amount);
        if (!empty($payments_by_amount[$key])) {
            $payment = array_shift($payments_by_amount[$key]);
            if (empty($payments_by_amount[$key])) {
                unset($payments_by_amount[$key]);
            }
            return $payment;
        }

        // 2. Intento con tolerancia (crucial para solucionar errores de redondeo)
        // Buscamos cualquier pago que esté dentro de un rango de +/- 1.0 unidad
        $tolerance = 1.0; 
        
        foreach ($payments_by_amount as $stored_key => $list) {
            if (empty($list)) {
                continue;
            }
            // Comparamos floats
            if (abs((float) $stored_key - $target_amount) <= $tolerance) {
                // Encontramos un pago cercano, lo tomamos y lo removemos del array
                $payment = array_shift($payments_by_amount[$stored_key]);
                if (empty($payments_by_amount[$stored_key])) {
                    unset($payments_by_amount[$stored_key]);
                }
                return $payment;
            }
        }

        return null;
    }

    private static function normalize_state(string $state): string {
        $normalized = strtolower(trim($state));
        $allowed = ['pending', 'approved', 'rejected'];
        return in_array($normalized, $allowed, true) ? $normalized : 'pending';
    }

    private static function normalize_amount(float $amount): string {
        return number_format($amount, 2, '.', '');
    }
    
    private static function inline_scripts(): string {
        ob_start(); ?>
        <script>
        (function(){
          var openers = document.querySelectorAll('[data-bankitos-open]');
          function closeModal(modal){
            if(modal){ modal.setAttribute('hidden','hidden'); }
          }
          if(openers.length){
            openers.forEach(function(btn){
              btn.addEventListener('click', function(){
                var id = btn.getAttribute('data-bankitos-open');
                var modal = document.getElementById(id);
                if(modal){ modal.removeAttribute('hidden'); }
              });
            });
           }
          document.querySelectorAll('.bankitos-modal').forEach(function(modal){
            modal.querySelectorAll('[data-bankitos-close]').forEach(function(closer){
              closer.addEventListener('click', function(){ closeModal(modal); });
            });
            modal.addEventListener('click', function(ev){
              if(ev.target === modal){ closeModal(modal); }
            });
          });
          
          var allowedTypes = ['image/jpeg','image/png','image/gif','image/webp','application/pdf'];
          var allowedExt = /(\.jpe?g|\.png|\.gif|\.webp|\.pdf)$/i;

          function toggleSubmitState(input){
            var form = input.closest('form'); 
            if (!form) return;
            
            var submit = form.querySelector('[data-bankitos-submit]');
            var errorMsg = form.querySelector('[data-upload-error]');
            var file = input.files && input.files.length ? input.files[0] : null;
            var hasFile = !!file;
            var isValid = false;

            if(hasFile){
              if(file.type && allowedTypes.indexOf(file.type) !== -1){
                isValid = true;
              } else if(file.name && allowedExt.test(file.name)){
                isValid = true;
              }
            }
            if(submit){
              submit.disabled = !isValid;
              submit.setAttribute('aria-disabled', submit.disabled ? 'true' : 'false');
            }
            if(errorMsg){
              errorMsg.hidden = !hasFile || isValid;
            }
          }

          document.querySelectorAll('.bankitos-credit-summary__upload input[type=\"file\"]').forEach(function(input){
            var label = input.parentElement ? input.parentElement.querySelector('.bankitos-file__label') : null;
            var defaultText = label && (label.dataset.defaultLabel || label.textContent);
            
            function updateLabel(){
              var file = input.files && input.files.length ? input.files[0] : null;
              var fileName = file ? file.name : '';
              if(label){
                if(fileName){
                  label.textContent = fileName;
                  label.classList.add('bankitos-file__label--selected');
                }else{
                  label.textContent = defaultText;
                  label.classList.remove('bankitos-file__label--selected');
                }
              }
              toggleSubmitState(input);
            }
            updateLabel();
            input.addEventListener('change', updateLabel);
          });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}