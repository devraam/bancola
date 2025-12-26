<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Shortcode_Tesorero_Desembolsos extends Bankitos_Shortcode_Panel_Base {

    public static function register(): void {
        self::register_shortcode('bankitos_tesorero_desembolsos');
    }

    public static function render($atts = [], $content = null): string {
        if (!is_user_logged_in()) {
            return '<div class="bankitos-form"><p>' . esc_html__('Inicia sesión para continuar.', 'bankitos') . '</p></div>';
        }
        if (!current_user_can('approve_aportes')) {
            return '<div class="bankitos-form"><p>' . esc_html__('No tienes permisos para aprobar desembolsos.', 'bankitos') . '</p></div>';
        }

        $context = self::get_panel_context();
        if ($context['banco_id'] <= 0) {
            return '<div class="bankitos-form"><p>' . esc_html__('Debes pertenecer a un B@nko.', 'bankitos') . '</p></div>';
        }

        $credits = array_filter(
            Bankitos_Credit_Requests::get_requests($context['banco_id']),
            static function ($row) {
                return in_array($row['status'], ['disbursement_pending', 'disbursed'], true);
            }
        );

        $types       = Bankitos_Credit_Requests::get_credit_types();
        // IMPORTANTE: Capturamos la URL actual para que el handler sepa a dónde volver
        $current_url = self::get_current_url();

        ob_start(); ?>
        <section class="bankitos-credit-review">
          <div class="bankitos-credit-review__header">
            <h3><?php esc_html_e('Desembolsos de créditos', 'bankitos'); ?></h3>
            <p><?php esc_html_e('Gestiona los desembolsos de créditos aprobados y registra sus comprobantes.', 'bankitos'); ?></p>
          </div>

          <?php echo self::top_notice_from_query(); ?>

          <?php if (!$credits): ?>
            <p class="bankitos-panel__message"><?php esc_html_e('No hay créditos aprobados pendientes de desembolso.', 'bankitos'); ?></p>
          <?php else: ?>
            <div class="bankitos-credit-review__accordion bankitos-accordion" role="list">
              <?php $is_first = true; foreach ($credits as $credit):
                  $type_label = $types[$credit['credit_type']] ?? ucfirst($credit['credit_type']);
                  $status_info = self::get_status_meta($credit['status']);
                  $disbursement_receipt = self::get_disbursement_receipt($credit);
                  ?>
                  <details class="bankitos-accordion__item bankitos-credit-payment__credit" role="listitem" <?php echo $is_first ? 'open' : ''; ?>>
                    <summary class="bankitos-accordion__summary">
                      <div class="bankitos-accordion__title">
                        <span class="bankitos-accordion__amount"><?php echo esc_html(self::format_currency((float) $credit['amount'])); ?></span>
                        <span class="bankitos-accordion__name"><?php echo esc_html($credit['display_name'] ?? '—'); ?></span>
                      </div>
                      <div class="bankitos-accordion__meta">
                        <span><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($credit['request_date']))); ?></span>
                        <span class="bankitos-pill <?php echo esc_attr($status_info['class']); ?>"><?php echo esc_html($status_info['label']); ?></span>
                        <span class="bankitos-accordion__chevron" aria-hidden="true"></span>
                      </div>
                    </summary>
                    <div class="bankitos-accordion__content">
                      <dl class="bankitos-accordion__grid bankitos-credit-payment__grid">
                        <div>
                          <dt><?php esc_html_e('Crédito', 'bankitos'); ?></dt>
                          <dd><?php echo esc_html($type_label); ?></dd>
                        </div>
                        <div>
                          <dt><?php esc_html_e('Monto', 'bankitos'); ?></dt>
                          <dd><?php echo esc_html(self::format_currency((float) $credit['amount'])); ?></dd>
                        </div>
                        <div>
                          <dt><?php esc_html_e('Plazo', 'bankitos'); ?></dt>
                          <dd><?php printf(esc_html__('%s meses', 'bankitos'), esc_html(number_format_i18n((int) $credit['term_months']))); ?></dd>
                        </div>
                        <div>
                          <dt><?php esc_html_e('Fecha de aprobación', 'bankitos'); ?></dt>
                          <dd><?php echo !empty($credit['approval_date']) ? esc_html(date_i18n(get_option('date_format'), strtotime($credit['approval_date']))) : '—'; ?></dd>
                        </div>
                        <?php if (!empty($credit['disbursement_date'])): ?>
                        <div>
                          <dt><?php esc_html_e('Fecha de desembolso', 'bankitos'); ?></dt>
                          <dd><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($credit['disbursement_date']))); ?></dd>
                        </div>
                        <?php endif; ?>
                        <?php if ($disbursement_receipt['url']): ?>
                        <div>
                          <dt><?php esc_html_e('Comprobante', 'bankitos'); ?></dt>
                          <dd>
                            <button type="button" class="bankitos-link bankitos-link--button bankitos-receipt-link" data-receipt="<?php echo esc_url($disbursement_receipt['url']); ?>" data-is-image="<?php echo $disbursement_receipt['is_image'] ? '1' : '0'; ?>" data-title="<?php esc_attr_e('Comprobante de desembolso', 'bankitos'); ?>"><?php esc_html_e('Ver comprobante', 'bankitos'); ?></button>
                          </dd>
                        </div>
                        <?php endif; ?>
                      </dl>

                      <?php if ($credit['status'] === 'disbursement_pending'): ?>
                        <form class="bankitos-credit-summary__form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                          <?php echo wp_nonce_field('bankitos_credit_disburse_' . (int) $credit['id'], '_wpnonce', true, false); ?>
                          <input type="hidden" name="action" value="bankitos_credit_disburse">
                          <input type="hidden" name="request_id" value="<?php echo esc_attr($credit['id']); ?>">
                          <input type="hidden" name="redirect_to" value="<?php echo esc_url($current_url); ?>">

                          <div class="bankitos-field">
                            <label for="bk_disbursement_date_<?php echo esc_attr($credit['id']); ?>"><?php esc_html_e('Fecha de desembolso', 'bankitos'); ?></label>
                            <input id="bk_disbursement_date_<?php echo esc_attr($credit['id']); ?>" type="date" name="disbursement_date" required value="<?php echo esc_attr(date_i18n('Y-m-d')); ?>">
                          </div>

                          <div class="bankitos-field bankitos-credit-summary__upload">
                            <label for="bk_disbursement_file_<?php echo esc_attr($credit['id']); ?>"><?php esc_html_e('Comprobante (imagen o PDF, máximo 10MB)', 'bankitos'); ?></label>
                            <label class="bankitos-file">
                              <input id="bk_disbursement_file_<?php echo esc_attr($credit['id']); ?>" type="file" name="disbursement_receipt" accept=".jpg,.jpeg,.png,.pdf,image/*" required>
                              <span class="bankitos-file__label" data-default-label><?php esc_html_e('Elegir archivo', 'bankitos'); ?></span>
                            </label>
                            <span class="bankitos-credit-summary__help bankitos-credit-summary__help--error" data-upload-error hidden><?php esc_html_e('Sube un archivo válido (JPG/PNG o PDF).', 'bankitos'); ?></span>
                            <span class="bankitos-credit-summary__help bankitos-credit-summary__help--error" data-upload-size-error hidden><?php esc_html_e('El archivo no debe superar 10MB.', 'bankitos'); ?></span>
                            <span class="bankitos-credit-summary__help bankitos-credit-summary__help--error" data-upload-load-error hidden><?php esc_html_e('No se pudo cargar la imagen. Intenta con otro archivo.', 'bankitos'); ?></span>
                          </div>

                          <div class="bankitos-accordion__actions bankitos-credit-payment__actions">
                            <button type="submit" class="bankitos-btn bankitos-btn--primary" data-bankitos-submit disabled aria-disabled="true"><?php esc_html_e('Desembolsar', 'bankitos'); ?></button>
                          </div>
                        </form>
                      <?php else: ?>
                        <div class="bankitos-credit-payment__note">
                          <p><strong><?php esc_html_e('Desembolsado', 'bankitos'); ?></strong></p>
                          <?php if (!empty($credit['disbursement_date'])): ?>
                            <p><?php printf('%s %s', esc_html__('Fecha:', 'bankitos'), esc_html(date_i18n(get_option('date_format'), strtotime($credit['disbursement_date'])))); ?></p>
                          <?php endif; ?>
                        </div>
                      <?php endif; ?>
                    </div>
                  </details>
              <?php $is_first = false; endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
        <?php echo self::modal_markup(); ?>
        <?php echo self::inline_scripts(); ?>
        <?php
        return ob_get_clean();
    }

    private static function get_status_meta(string $status): array {
        $map = [
            'disbursement_pending' => [
                'label' => __('Pendiente de desembolso', 'bankitos'),
                'class' => 'bankitos-pill--pending',
            ],
            'disbursed' => [
                'label' => __('Desembolsado', 'bankitos'),
                'class' => 'bankitos-pill--accepted',
            ],
            'approved' => [
                'label' => __('Aprobado', 'bankitos'),
                'class' => 'bankitos-pill--accepted',
            ],
        ];

        return $map[$status] ?? [
            'label' => $status,
            'class' => 'bankitos-pill--pending',
        ];
    }

    private static function get_disbursement_receipt(array $credit): array {
        $attachment_id = isset($credit['disbursement_attachment_id']) ? (int) $credit['disbursement_attachment_id'] : 0;
        if ($attachment_id <= 0 || $credit['status'] !== 'disbursed') {
            return ['url' => '', 'is_image' => false];
        }

        $url = class_exists('BK_Credit_Disbursements_Handler')
            ? BK_Credit_Disbursements_Handler::get_receipt_download_url((int) $credit['id'])
            : '';

        $mime = $attachment_id > 0 ? get_post_mime_type($attachment_id) : '';
        $is_image = $mime && strpos($mime, 'image/') === 0;

        return [
            'url'      => $url,
            'is_image' => $is_image,
        ];
    }

    protected static function modal_markup(): string {
        return '<div id="bankitos-modal" class="bankitos-modal" hidden><div class="bankitos-modal__backdrop"></div><div class="bankitos-modal__body"><button type="button" class="bankitos-modal__close" aria-label="' . esc_attr__('Cerrar', 'bankitos') . '">&times;</button><p class="bankitos-modal__error" hidden></p><iframe class="bankitos-modal__frame" src="" title="' . esc_attr__('Comprobante', 'bankitos') . '" hidden></iframe><img src="" alt="" loading="lazy" hidden></div></div>';
    }

    protected static function inline_scripts(): string {
        ob_start(); ?>
        <script>
        (function(){
          var modal = document.getElementById('bankitos-modal');
          var allowedTypes = ['image/jpeg','image/png','application/pdf'];
          var allowedExt = /(\.jpe?g|\.png|\.pdf)$/i;
          // MODIFICADO: Aumento del límite de 1MB a 10MB
          var maxSize = 10 * 1024 * 1024; // 10MB
          var loadTokens = new WeakMap();

          function closeModal(){
            if(!modal){ return; }
            modal.setAttribute('hidden','hidden');
            var frame = modal.querySelector('.bankitos-modal__frame');
            var img = modal.querySelector('img');
            var errorBox = modal.querySelector('.bankitos-modal__error');
            if(frame){
              frame.removeAttribute('src');
              frame.setAttribute('hidden','');
              frame.setAttribute('aria-hidden','true');
            }
            if(img){
              img.removeAttribute('src');
              img.setAttribute('hidden','');
            }
            if(errorBox){
              errorBox.textContent = '';
              errorBox.setAttribute('hidden','');
            }
          }

          function showError(message){
            var errorBox = modal ? modal.querySelector('.bankitos-modal__error') : null;
            if(!errorBox){ return; }
            errorBox.textContent = message || '';
            errorBox.removeAttribute('hidden');
          }

          function openReceipt(receiptUrl, isImage, title){
            if(!modal || !receiptUrl){ return; }
            var frame = modal.querySelector('.bankitos-modal__frame');
            var img = modal.querySelector('img');

            if(img){
              img.removeAttribute('src');
              img.setAttribute('hidden','');
            }
            if(frame){
              frame.removeAttribute('src');
              frame.setAttribute('hidden','');
              frame.setAttribute('aria-hidden','true');
            }

            if(isImage && img){
              img.onload = function(){ img.removeAttribute('hidden'); modal.removeAttribute('hidden'); };
              img.onerror = function(){ img.setAttribute('hidden',''); showError('No se pudo cargar el comprobante.'); modal.removeAttribute('hidden'); };
              img.alt = title || '';
              img.src = receiptUrl;
              modal.removeAttribute('hidden');
              return;
            }

            if(frame){
              frame.onload = function(){};
              frame.onerror = function(){ frame.setAttribute('hidden',''); showError('No se pudo cargar el comprobante.'); };
              frame.removeAttribute('hidden');
              frame.removeAttribute('aria-hidden');
              frame.title = title || '';
              frame.src = receiptUrl;
              modal.removeAttribute('hidden');
              return;
            }
          }

          document.addEventListener('click', function(ev){
            if(!modal){ return; }
            var link = ev.target.closest('.bankitos-receipt-link');
            if(!link){ return; }
            ev.preventDefault();
            var receiptUrl = link.getAttribute('data-receipt');
            var isImage = link.getAttribute('data-is-image') === '1';
            var title = link.getAttribute('data-title') || '';
            openReceipt(receiptUrl, isImage, title);
          });

          if(modal){
            var backdrop = modal.querySelector('.bankitos-modal__backdrop');
            var closeBtn = modal.querySelector('.bankitos-modal__close');
            [backdrop, closeBtn].forEach(function(el){
              if(el){ el.addEventListener('click', closeModal); }
            });
          }

          function setSubmitState(submit, enabled){
            if(!submit){ return; }
            submit.disabled = !enabled;
            submit.setAttribute('aria-disabled', submit.disabled ? 'true' : 'false');
          }

          function validateImageLoad(file, onSuccess, onError){
            var url = URL.createObjectURL(file);
            var img = new Image();
            img.onload = function(){
              URL.revokeObjectURL(url);
              onSuccess();
            };
            img.onerror = function(){
              URL.revokeObjectURL(url);
              onError();
            };
            img.src = url;
          }

          function toggleSubmitState(input){
            var form = input.closest('form');
            if(!form){ return; }
            var submit = form.querySelector('[data-bankitos-submit]');
            var errorMsg = form.querySelector('[data-upload-error]');
            var sizeMsg = form.querySelector('[data-upload-size-error]');
            var loadMsg = form.querySelector('[data-upload-load-error]');
            var file = input.files && input.files.length ? input.files[0] : null;
            var hasFile = !!file;
            var isValid = false;
            var isSizeValid = true;
            if(loadMsg){
              loadMsg.hidden = true;
            }

            if(hasFile){
              if((file.type && allowedTypes.indexOf(file.type) !== -1) || (file.name && allowedExt.test(file.name))){
                isValid = true;
              }
              if(file.size && file.size > maxSize){
                isSizeValid = false;
              }
            }

            var shouldEnable = hasFile && isValid && isSizeValid;

            if(shouldEnable && file && file.type && file.type.indexOf('image/') === 0){
              var token = Date.now().toString();
              loadTokens.set(input, token);
              setSubmitState(submit, false);
              validateImageLoad(file, function(){
                if(loadTokens.get(input) !== token){ return; }
                if(loadMsg){ loadMsg.hidden = true; }
                setSubmitState(submit, true);
              }, function(){
                if(loadTokens.get(input) !== token){ return; }
                setSubmitState(submit, false);
                if(loadMsg){ loadMsg.hidden = false; }
              });
            } else {
              setSubmitState(submit, shouldEnable);
            }
            
            if(errorMsg){
              errorMsg.hidden = !hasFile || isValid;
            }
            if(sizeMsg){
              sizeMsg.hidden = !hasFile || isSizeValid;
            }
          }

          document.querySelectorAll('.bankitos-credit-summary__upload input[type="file"]').forEach(function(input){
            var label = input.parentElement ? input.parentElement.querySelector('.bankitos-file__label') : null;
            var defaultText = label && (label.dataset.defaultLabel || label.textContent);
            function updateLabel(){
              var file = input.files && input.files.length ? input.files[0] : null;
              var fileName = file ? file.name : '';
              if(label){
                if(fileName){
                  label.textContent = fileName;
                  label.classList.add('bankitos-file__label--selected');
                } else {
                  label.textContent = defaultText;
                  label.classList.remove('bankitos-file__label--selected');
                }
              }
              toggleSubmitState(input);
            }
            updateLabel();
            input.addEventListener('change', updateLabel);
          });

          document.querySelectorAll('.bankitos-credit-summary__form').forEach(function(form){
            var submit = form.querySelector('[data-bankitos-submit]');
            var fileInput = form.querySelector('input[type="file"][name="disbursement_receipt"]');
            if(!submit || !fileInput){ return; }
            form.addEventListener('submit', function(ev){
              if(submit.disabled || !fileInput.files || !fileInput.files.length){
                ev.preventDefault();
                toggleSubmitState(fileInput);
              }
            });
          });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}