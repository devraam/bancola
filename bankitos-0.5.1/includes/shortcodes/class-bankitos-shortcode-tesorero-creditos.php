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
        
        ob_start(); ?>
        <section class="bankitos-credit-review">
          <div class="bankitos-credit-review__header">
            <h3><?php esc_html_e('Pagos de créditos', 'bankitos'); ?></h3>
            <p><?php echo $can_moderate ? esc_html__('Aprueba o rechaza los pagos enviados por los socios.', 'bankitos') : esc_html__('Consulta los pagos registrados en el banco.', 'bankitos'); ?></p>
          </div>
          
        <?php echo self::top_notice_from_query(); ?>
        
        <?php if (!$credits): ?>
            <p class="bankitos-panel__message"><?php esc_html_e('No hay créditos aprobados activos.', 'bankitos'); ?></p>
        <?php else: ?>
            <div class="bankitos-credit-review__list">
              <?php foreach ($credits as $credit): ?>
                <?php echo self::render_credit_block($credit, $can_moderate); ?>
              <?php endforeach; ?>
            </div>
        <?php endif; ?>
      </section>
      <?php echo self::modal_markup(); ?>
      <?php echo self::inline_scripts(); ?>
      <?php
        return ob_get_clean();
    }

    protected static function render_credit_block(array $credit, bool $can_moderate): string {
        // Obtenemos todos los pagos asociados a este crédito
        $payments = Bankitos_Credit_Payments::get_request_payments((int) $credit['id']);
        $status_labels = Bankitos_Credit_Payments::get_status_labels();
        
        ob_start(); ?>
        <article class="bankitos-credit-card">
          <header class="bankitos-credit-card__header">
            <div>
              <p class="bankitos-credit-card__badge"><?php esc_html_e('Crédito en curso', 'bankitos'); ?></p>
              <h4><?php echo esc_html($credit['display_name'] ?? ''); ?></h4>
            </div>
            <div class="bankitos-credit-card__status">
              <span class="bankitos-pill bankitos-pill--accepted"><?php esc_html_e('Aprobado', 'bankitos'); ?></span>
              <span class="bankitos-credit-card__date"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($credit['request_date']))); ?></span>
            </div>
          </header>
          <dl class="bankitos-credit-card__details">
            <div><dt><?php esc_html_e('Monto Total', 'bankitos'); ?></dt><dd><?php echo esc_html(self::format_currency((float) $credit['amount'])); ?></dd></div>
            <div><dt><?php esc_html_e('Plazo', 'bankitos'); ?></dt><dd><?php echo esc_html(sprintf(_n('%s mes', '%s meses', (int) $credit['term_months'], 'bankitos'), number_format_i18n((int) $credit['term_months']))); ?></dd></div>
          </dl>
          
          <div class="bankitos-table-wrapper" style="margin-top:1rem;">
            <table class="bankitos-table">
              <thead>
                <tr>
                  <th><?php esc_html_e('Fecha Pago', 'bankitos'); ?></th>
                  <th><?php esc_html_e('Monto', 'bankitos'); ?></th>
                  <th><?php esc_html_e('Soporte', 'bankitos'); ?></th>
                  <th><?php esc_html_e('Estado', 'bankitos'); ?></th>
                  <?php if ($can_moderate): ?><th><?php esc_html_e('Acciones', 'bankitos'); ?></th><?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php if (!$payments): ?>
                  <tr><td colspan="<?php echo $can_moderate ? '5' : '4'; ?>"><?php esc_html_e('Aún no se han registrado pagos para este crédito.', 'bankitos'); ?></td></tr>
                <?php else: foreach ($payments as $payment):
                    $receipt = !empty($payment['attachment_id']) ? BK_Credit_Payments_Handler::get_receipt_download_url((int) $payment['id']) : '';
                    $st = $payment['status'];
                    $pill_class = 'bankitos-pill';
                    if($st === 'approved') $pill_class .= ' bankitos-pill--accepted';
                    elseif($st === 'rejected') $pill_class .= ' bankitos-pill--rejected';
                    else $pill_class .= ' bankitos-pill--pending';
                    ?>
                    <tr>
                      <td data-title="<?php esc_attr_e('Fecha', 'bankitos'); ?>"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($payment['created_at']))); ?></td>
                      <td data-title="<?php esc_attr_e('Monto', 'bankitos'); ?>"><?php echo esc_html(self::format_currency((float) $payment['amount'])); ?></td>
                      <td data-title="<?php esc_attr_e('Soporte', 'bankitos'); ?>">
                          <?php if ($receipt): ?>
                            <button type="button" class="bankitos-link bankitos-link--button bankitos-receipt-link" data-receipt="<?php echo esc_url($receipt); ?>" data-title="<?php echo esc_attr__('Comprobante de pago', 'bankitos'); ?>"><?php esc_html_e('Ver soporte', 'bankitos'); ?></button>
                          <?php else: ?>
                            <?php esc_html_e('No disponible', 'bankitos'); ?>
                          <?php endif; ?>
                      </td>
                      <td data-title="<?php esc_attr_e('Estado', 'bankitos'); ?>"><span class="<?php echo esc_attr($pill_class); ?>"><?php echo esc_html($status_labels[$st] ?? $st); ?></span></td>
                      <?php if ($can_moderate): ?>
                        <td data-title="<?php esc_attr_e('Acciones', 'bankitos'); ?>">
                          <?php if ($st === 'pending'): ?>
                            <div style="display:flex; gap:0.5rem;">
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
                            </div>
                          <?php else: ?>
                            <span class="bankitos-text-muted">—</span>
                          <?php endif; ?>
                        </td>
                      <?php endif; ?>
                    </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </article>
        <?php
        return ob_get_clean();
    }

    protected static function modal_markup(): string {
        return '<div id="bankitos-modal" class="bankitos-modal" hidden><div class="bankitos-modal__backdrop"></div><div class="bankitos-modal__body"><button type="button" class="bankitos-modal__close" aria-label="' . esc_attr__('Cerrar', 'bankitos') . '">&times;</button><img src="" alt="" loading="lazy"></div></div>';
    }

    protected static function inline_scripts(): string {
        ob_start(); ?>
        <script>
        (function(){
          var modal = document.getElementById('bankitos-modal');
          if(!modal){return;}
          var backdrop = modal.querySelector('.bankitos-modal__backdrop');
          var closeBtn = modal.querySelector('.bankitos-modal__close');
          var img = modal.querySelector('img');
          
          function close(){
            modal.setAttribute('hidden','hidden');
            if(img){
              img.removeAttribute('src');
              img.setAttribute('hidden','');
            }
          }
          [backdrop, closeBtn].forEach(function(el){ if(el){ el.addEventListener('click', close); }});
          
          document.querySelectorAll('.bankitos-receipt-link').forEach(function(link){
            link.addEventListener('click', function(ev){
              ev.preventDefault();
              var receiptUrl = link.getAttribute('data-receipt');
              var title = link.getAttribute('data-title') || '';
              
              if (img) {
                // Intentamos cargar la imagen
                img.onload = function(){
                  img.onload = null;
                  img.onerror = null;
                  img.removeAttribute('hidden');
                  modal.removeAttribute('hidden');
                };
                img.onerror = function(){
                  // Si falla (es PDF u otro), abrimos en nueva pestaña
                  img.onload = null;
                  img.onerror = null;
                  img.setAttribute('hidden','');
                  modal.setAttribute('hidden','hidden');
                  window.open(receiptUrl, '_blank');
                };
                img.alt = title;
                img.src = receiptUrl;
              } else {
                window.open(receiptUrl, '_blank');
              }
            });
          });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}