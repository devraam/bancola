<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Shortcode_Panel_Finanzas extends Bankitos_Shortcode_Panel_Base {

    public static function register(): void {
        self::register_shortcode('bankitos_panel_mis_finanzas');
    }

    public static function render($atts = [], $content = null): string {
        if (!is_user_logged_in()) {
            return '<div class="bankitos-panel__message">' . esc_html__('Inicia sesión para ver tus finanzas.', 'bankitos') . '</div>';
        }
        $context = self::get_panel_context();
        if ($context['banco_id'] <= 0) {
            return '<div class="bankitos-panel__message">' . esc_html__('Debes pertenecer a un B@nko para ver esta información.', 'bankitos') . '</div>';
        }
        $user_id = get_current_user_id();
        $current_url = self::get_current_url();
        $savings_total = Bankitos_Credit_Requests::get_user_savings_total($user_id, $context['banco_id']);
        $aportes = self::get_user_aportes($user_id, $context['banco_id']);
        $credits = array_filter(
            Bankitos_Credit_Requests::get_requests($context['banco_id']),
            static function ($row) use ($user_id) {
                return (int) $row['user_id'] === (int) $user_id;
            }
        );
        ob_start(); ?>
        <section class="bankitos-finanzas" aria-labelledby="bankitos-finanzas-title">
          <div class="bankitos-finanzas__header">
            <h3 id="bankitos-finanzas-title"><?php esc_html_e('Mis finanzas', 'bankitos'); ?></h3>
            <?php echo self::top_notice_from_query(); ?>
            <p class="bankitos-finanzas__summary"><?php printf('%s %s', esc_html__('Mis ahorros:', 'bankitos'), esc_html(self::format_currency($savings_total))); ?></p>
            <button class="bankitos-btn bankitos-finanzas__toggle" type="button" data-target="#bankitos-aportes">
              <?php esc_html_e('Ver más', 'bankitos'); ?>
            </button>
          </div>
          <div id="bankitos-aportes" class="bankitos-finanzas__panel" hidden>
            <?php if (!$aportes->have_posts()): ?>
              <p><?php esc_html_e('Aún no has registrado aportes.', 'bankitos'); ?></p>
            <?php else: ?>
              <div class="bankitos-table-wrapper">
                <table class="bankitos-table">
                  <thead>
                    <tr>
                      <th><?php esc_html_e('Fecha', 'bankitos'); ?></th>
                      <th><?php esc_html_e('Valor del aporte', 'bankitos'); ?></th>
                      <th><?php esc_html_e('Soporte', 'bankitos'); ?></th>
                      <th><?php esc_html_e('Estado', 'bankitos'); ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php while ($aportes->have_posts()): $aportes->the_post();
                        $aporte_id = get_the_ID();
                        $monto = (float) get_post_meta($aporte_id, '_bankitos_monto', true);
                        $status = get_post_status($aporte_id);
                        $estado_label = self::aporte_status_label($status);
                        // --- MODIFICADO: Usar el handler para obtener la URL directa del comprobante ---
                        $thumb_url = class_exists('BK_Aportes_Handler') ? BK_Aportes_Handler::get_comprobante_view_src($aporte_id) : '';
                        $is_image = false;
                        if ($thumb_url && class_exists('BK_Aportes_Handler')) {
                            $is_image = BK_Aportes_Handler::is_file_image(get_post_thumbnail_id($aporte_id));
                        }
                        // --- FIN MODIFICADO ---
                        ?>
                        <tr>
                          <td><?php echo esc_html(get_the_date()); ?></td>
                          <td><?php echo esc_html(self::format_currency($monto)); ?></td>
                          <td>
                            <?php if ($thumb_url): ?>
                              <button type="button" class="bankitos-link bankitos-link--button bankitos-receipt-link" data-receipt="<?php echo esc_url($thumb_url); ?>" data-is-image="<?php echo $is_image ? '1' : '0'; ?>" data-title="<?php echo esc_attr(get_the_title()); ?>"><?php esc_html_e('Ver soporte', 'bankitos'); ?></button>
                            <?php else: ?>
                              <span><?php esc_html_e('No disponible', 'bankitos'); ?></span>
                            <?php endif; ?>
                          </td>
                          <td><?php echo esc_html($estado_label); ?></td>
                        </tr>
                    <?php endwhile; wp_reset_postdata(); ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </section>

        <section class="bankitos-creditos" aria-labelledby="bankitos-creditos-title">
          <div class="bankitos-creditos__header">
            <h3 id="bankitos-creditos-title"><?php esc_html_e('Mis créditos', 'bankitos'); ?></h3>
            <p><?php esc_html_e('Consulta tus solicitudes y pagos.', 'bankitos'); ?></p>
          </div>
          <?php if (!$credits): ?>
            <p class="bankitos-panel__message"><?php esc_html_e('Aún no has solicitado créditos.', 'bankitos'); ?></p>
          <?php else: ?>
            <div class="bankitos-creditos__list">
              <?php foreach ($credits as $credit): ?>
                <?php echo self::render_credit_card($credit, $context['meta']['tasa'], $current_url); ?>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
        <?php echo self::modal_markup(); ?>
        <?php echo self::inline_scripts(); ?>
        <?php
        return ob_get_clean();
    }

    private static function get_user_aportes(int $user_id, int $banco_id): WP_Query {
        return new WP_Query([
            'post_type'      => Bankitos_CPT::SLUG_APORTE,
            'post_status'    => ['pending', 'publish', 'private'],
            'posts_per_page' => 200,
            'author'         => $user_id,
            'meta_query'     => [
                [
                    'key'   => '_bankitos_banco_id',
                    'value' => $banco_id,
                ],
            ],
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);
    }

    private static function aporte_status_label(string $status): string {
        $map = [
            'pending' => __('Pendiente', 'bankitos'),
            'publish' => __('Aprobado', 'bankitos'),
            'private' => __('Rechazado', 'bankitos'),
        ];
        return $map[$status] ?? ucfirst($status);
    }

    private static function render_credit_card(array $credit, float $tasa, string $redirect): string {
        $quota = ($credit['term_months'] > 0) ? (($credit['amount'] * ($tasa / 100)) / (int) $credit['term_months']) : 0;
        $payments = class_exists('Bankitos_Credit_Payments') ? Bankitos_Credit_Payments::get_request_payments((int) $credit['id']) : [];
        $status_classes = [
            'pending'  => 'bankitos-pill--pending',
            'approved' => 'bankitos-pill--accepted',
            'rejected' => 'bankitos-pill--rejected',
        ];
        ob_start(); ?>
        <article class="bankitos-credit-item">
          <header class="bankitos-credit-item__header">
            <div>
              <h4><?php echo esc_html(sprintf(__('Solicitud del %s', 'bankitos'), date_i18n(get_option('date_format'), strtotime($credit['request_date'])))); ?></h4>
              <p class="bankitos-credit-item__meta"><?php printf('%s: %s', esc_html__('Monto', 'bankitos'), esc_html(self::format_currency((float) $credit['amount']))); ?></p>
            </div>
            <div class="bankitos-credit-item__status">
              <span class="bankitos-pill <?php echo esc_attr($status_classes[$credit['status']] ?? 'bankitos-pill--pending'); ?>"><?php echo esc_html(self::aporte_status_label($credit['status'])); ?></span>
            </div>
          </header>
          <dl class="bankitos-credit-item__details">
            <div><dt><?php esc_html_e('Tiempo de duración', 'bankitos'); ?></dt><dd><?php echo esc_html(sprintf(_n('%s mes', '%s meses', (int) $credit['term_months'], 'bankitos'), number_format_i18n((int) $credit['term_months']))); ?></dd></div>
            <div><dt><?php esc_html_e('Cuota estimada', 'bankitos'); ?></dt><dd><?php echo esc_html(self::format_currency($quota)); ?></dd></div>
            <div><dt><?php esc_html_e('Estado', 'bankitos'); ?></dt><dd><?php echo esc_html(self::aporte_status_label($credit['status'])); ?></dd></div>
          </dl>
          <?php if ($credit['status'] === 'approved'): ?>
            <button class="bankitos-btn bankitos-credit-item__toggle" type="button" data-target="#credit-<?php echo esc_attr($credit['id']); ?>">
              <?php esc_html_e('Ver más', 'bankitos'); ?>
            </button>
            <div id="credit-<?php echo esc_attr($credit['id']); ?>" class="bankitos-credit-item__panel" hidden>
              <?php echo self::render_payment_table($credit, $quota, $payments); ?>
              <form class="bankitos-form bankitos-credit-item__form" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php echo wp_nonce_field('bankitos_credit_payment_submit', '_wpnonce', true, false); ?>
                <input type="hidden" name="action" value="bankitos_credit_payment_submit">
                <input type="hidden" name="request_id" value="<?php echo esc_attr($credit['id']); ?>">
                <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect); ?>">
                <div class="bankitos-field">
                  <label for="bk_payment_amount_<?php echo esc_attr($credit['id']); ?>"><?php esc_html_e('Valor de la cuota', 'bankitos'); ?></label>
                  <input id="bk_payment_amount_<?php echo esc_attr($credit['id']); ?>" type="number" name="amount" min="1" step="0.01" required value="<?php echo esc_attr(number_format((float) $quota, 2, '.', '')); ?>">
                </div>
                <div class="bankitos-field">
                  <label for="bk_payment_receipt_<?php echo esc_attr($credit['id']); ?>"><?php esc_html_e('Comprobante de pago', 'bankitos'); ?></label>
                  <input id="bk_payment_receipt_<?php echo esc_attr($credit['id']); ?>" type="file" name="receipt" accept="image/*,application/pdf" required>
                </div>
                <button type="submit" class="bankitos-btn"><?php esc_html_e('Pagar cuota', 'bankitos'); ?></button>
              </form>
            </div>
          <?php endif; ?>
        </article>
        <?php
        return ob_get_clean();
    }

    private static function render_payment_table(array $credit, float $quota, array $payments): string {
        $term = max(1, (int) $credit['term_months']);
        $status_labels = Bankitos_Credit_Payments::get_status_labels();
        ob_start(); ?>
        <div class="bankitos-table-wrapper">
          <table class="bankitos-table">
            <thead>
              <tr>
                <th><?php esc_html_e('Cuota', 'bankitos'); ?></th>
                <th><?php esc_html_e('Fecha', 'bankitos'); ?></th>
                <th><?php esc_html_e('Valor', 'bankitos'); ?></th>
                <th><?php esc_html_e('Soporte', 'bankitos'); ?></th>
                <th><?php esc_html_e('Estado', 'bankitos'); ?></th>
              </tr>
            </thead>
            <tbody>
              <?php for ($i = 0; $i < $term; $i++):
                  $payment = $payments[$i] ?? null;
                  $receipt_url = '';
                  $is_image = false;

                  if ($payment && !empty($payment['attachment_id'])) {
                      // --- MODIFICADO: Usar el handler para obtener la URL de descarga segura ---
                      $receipt_url = class_exists('BK_Credit_Payments_Handler') ? BK_Credit_Payments_Handler::get_receipt_download_url((int) $payment['id']) : '';
                      if ($receipt_url && class_exists('BK_Aportes_Handler')) {
                          $is_image = BK_Aportes_Handler::is_file_image((int) $payment['attachment_id']);
                      }
                      // --- FIN MODIFICADO ---
                  }
                  ?>
                  <tr>
                    <td><?php echo esc_html($i + 1); ?></td>
                    <td><?php echo $payment ? esc_html(date_i18n(get_option('date_format'), strtotime($payment['created_at']))) : esc_html__('Pendiente', 'bankitos'); ?></td>
                    <td><?php echo esc_html(self::format_currency($payment ? (float) $payment['amount'] : $quota)); ?></td>
                    <td>
                      <?php if ($receipt_url): ?>
                        <button type="button" class="bankitos-link bankitos-link--button bankitos-receipt-link" data-receipt="<?php echo esc_url($receipt_url); ?>" data-is-image="<?php echo $is_image ? '1' : '0'; ?>" data-title="<?php echo esc_attr(sprintf(__('Comprobante cuota %s', 'bankitos'), $i + 1)); ?>"><?php esc_html_e('Ver soporte', 'bankitos'); ?></button>
                      <?php else: ?>
                        <span><?php esc_html_e('Pendiente', 'bankitos'); ?></span>
                      <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($payment ? ($status_labels[$payment['status']] ?? $payment['status']) : __('Pendiente', 'bankitos')); ?></td>
                  </tr>
              <?php endfor; ?>
            </tbody>
          </table>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function modal_markup(): string {
        // El <img> se inicializa sin src. El JS lo establecerá condicionalmente.
        return '<div id="bankitos-modal" class="bankitos-modal" hidden><div class="bankitos-modal__backdrop"></div><div class="bankitos-modal__body"><button type="button" class="bankitos-modal__close" aria-label="' . esc_attr__('Cerrar', 'bankitos') . '">&times;</button><img src="" alt="" loading="lazy"></div></div>';
    }

    private static function inline_scripts(): string {
        ob_start(); ?>
        <script>
        (function(){
          var toggles = document.querySelectorAll('[data-target]');
          toggles.forEach(function(btn){
            btn.addEventListener('click', function(){
              var target = document.querySelector(btn.getAttribute('data-target'));
              if(!target){return;}
              var isHidden = target.hasAttribute('hidden');
              if(isHidden){target.removeAttribute('hidden');}else{target.setAttribute('hidden','hidden');}
            });
          });
          var modal = document.getElementById('bankitos-modal');
          if(modal){
            var backdrop = modal.querySelector('.bankitos-modal__backdrop');
            var closeBtn = modal.querySelector('.bankitos-modal__close');
            var img = modal.querySelector('img');
            // var downloadLink = modal.querySelector('.bankitos-modal__download-link'); // No se usa en este modal

            function close(){
              modal.setAttribute('hidden','hidden'); 
              if(img){
                img.removeAttribute('src'); // Limpiar src al cerrar
                img.setAttribute('hidden', ''); // Asegurar que la imagen se oculta
              }
            }
            [backdrop, closeBtn].forEach(function(el){ if(el){ el.addEventListener('click', close); }});

            document.querySelectorAll('.bankitos-receipt-link').forEach(function(link){
              link.addEventListener('click', function(ev){
                ev.preventDefault();
                var receiptUrl = link.getAttribute('data-receipt');
                var isImage = link.getAttribute('data-is-image') === '1';
                var title = link.getAttribute('data-title') || '';
                
                if (isImage) {
                    img.src = receiptUrl;
                    img.alt = title;
                    img.removeAttribute('hidden'); // Mostrar la imagen
                    modal.removeAttribute('hidden'); // Mostrar el modal
                } else {
                    // Si es PDF o no imagen, ocultar <img> y abrir en nueva pestaña para forzar descarga/vista en navegador
                    img.setAttribute('hidden', ''); // Ocultar la imagen
                    window.open(receiptUrl, '_blank'); // Abrir en nueva pestaña
                    // No mostramos el modal si se abre en otra pestaña
                }
              });
            });
          }
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}