<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Shortcode_Panel_Finanzas extends Bankitos_Shortcode_Panel_Base {

    public static function register(): void {
        self::register_shortcode('bankitos_panel_mis_finanzas');
    }

    public static function render($atts = [], $content = null): string {
        if (!is_user_logged_in()) {
            return '';
        }
        $filters  = self::get_aporte_filters('finanzas');
        $per_page = (int) apply_filters('bankitos_aportes_per_page', 20, 'finanzas');
        $args = [
            'post_type'      => Bankitos_CPT::SLUG_APORTE,
            'post_status'    => ['publish','pending','private'],
            'posts_per_page' => $per_page,
            'paged'          => $filters['page'],
            'author'         => get_current_user_id(),
        ];
        if (!empty($filters['date_query'])) {
            $args['date_query'] = $filters['date_query'];
        }
        
        $approved_total   = self::get_user_approved_total(get_current_user_id());
        $credit_capacity  = $approved_total * 4;
        $q                = new WP_Query($args);
        ob_start(); ?>
        <div class="bankitos-form bankitos-panel">
          <div class="bankitos-panel-info__header">
            <?php echo self::top_notice_from_query(); ?>
            <img decoding="async" width="80" class="df_ab_blurb_image_img" src="https://bancola.tarcem.com/wp-content/uploads/2025/12/Icono-aportes.svg" alt="">
            <div>
              <h3><?php esc_html_e('Mis Aportes', 'bankitos'); ?></h3>
              <div class="bankitos-finanzas__summary">
                <p><strong><?php esc_html_e('Total de mis aportes:', 'bankitos'); ?></strong> <?php echo esc_html(self::format_currency($approved_total)); ?></p>
                <p><strong><?php esc_html_e('Mi Capacidad de crédito:', 'bankitos'); ?></strong> <?php echo esc_html(self::format_currency($credit_capacity)); ?></p>
              </div>
            </div>
          </div>
          <?php if (!$q->have_posts()): ?>
            <p><?php esc_html_e('No tienes aportes registrados.', 'bankitos'); ?></p>
          <?php else: ?>
            <div class="bankitos-accordion" role="list">
              <?php $is_first = true; while ($q->have_posts()): $q->the_post();
                  $aporte_id = get_the_ID();
                  $monto     = get_post_meta($aporte_id, '_bankitos_monto', true);
                  $status_slug = get_post_status($aporte_id);
                  $status    = get_post_status_object($status_slug);
                  $status_label = self::transform_status_label($status_slug, $status ? $status->label : '—');
                  $thumb     = class_exists('BK_Aportes_Handler') ? BK_Aportes_Handler::get_comprobante_view_src($aporte_id) : '';
                  $is_image  = false;
                  if ($thumb && class_exists('BK_Aportes_Handler')) {
                      $is_image = BK_Aportes_Handler::is_file_image(get_post_thumbnail_id($aporte_id));
                  }
                  $badge_class = 'bankitos-pill bankitos-pill--pending';
                  if ($status_slug === 'publish') {
                      $badge_class = 'bankitos-pill bankitos-pill--accepted';
                  } elseif ($status_slug === 'private') {
                      $badge_class = 'bankitos-pill bankitos-pill--rejected';
                  }
              ?>
                <details class="bankitos-accordion__item" role="listitem" <?php echo $is_first ? 'open' : ''; ?>>
                  <summary class="bankitos-accordion__summary">
                    <div class="bankitos-accordion__title">
                      <span class="bankitos-accordion__amount"><?php echo esc_html(self::format_currency($monto)); ?></span>
                      <span class="<?php echo esc_attr($badge_class); ?>"><?php echo esc_html($status_label); ?></span>
                    </div>
                    <div class="bankitos-accordion__meta">
                      <span><?php echo esc_html(get_the_date()); ?></span>
                      <span class="bankitos-accordion__chevron" aria-hidden="true"></span>
                    </div>
                  </summary>
                  <div class="bankitos-accordion__content">
                    <dl class="bankitos-accordion__grid">
                      <div>
                        <dt><?php esc_html_e('Monto', 'bankitos'); ?></dt>
                        <dd><?php echo esc_html(self::format_currency($monto)); ?></dd>
                      </div>
                      <div>
                        <dt><?php esc_html_e('Estado', 'bankitos'); ?></dt>
                        <dd><?php echo esc_html($status_label); ?></dd>
                      </div>
                      <div>
                        <dt><?php esc_html_e('Fecha', 'bankitos'); ?></dt>
                        <dd><?php echo esc_html(get_the_date()); ?></dd>
                      </div>
                      <div>
                        <dt><?php esc_html_e('Comprobante', 'bankitos'); ?></dt>
                        <dd>
                          <?php if ($thumb): ?>
                            <button type="button" class="bankitos-link bankitos-link--button bankitos-receipt-link" data-receipt="<?php echo esc_url($thumb); ?>" data-is-image="<?php echo $is_image ? '1' : '0'; ?>" data-title="<?php echo esc_attr(get_the_title()); ?>"><?php esc_html_e('Ver comprobante', 'bankitos'); ?></button>
                          <?php else: ?>—<?php endif; ?>
                        </dd>
                      </div>
                    </dl>
                  </div>
                </details>
              <?php $is_first = false; endwhile; wp_reset_postdata(); ?>
            </div>
            <?php echo self::render_aporte_pagination($q, $filters['page_key'], $filters['query_args'], $filters['page']); ?>
          <?php endif; ?>
        </div>
        <?php echo self::modal_markup(); ?>
        <?php echo self::inline_scripts(); ?>
        <?php
        return ob_get_clean();
    }
    
    protected static function modal_markup(): string {
        // El <img> se inicializa sin src. El JS lo establecerá condicionalmente.
        return '<div id="bankitos-modal" class="bankitos-modal" hidden><div class="bankitos-modal__backdrop"></div><div class="bankitos-modal__body"><button type="button" class="bankitos-modal__close" aria-label="' . esc_attr__('Cerrar', 'bankitos') . '">&times;</button><p class="bankitos-modal__error" hidden></p><iframe class="bankitos-modal__frame" src="" title="' . esc_attr__('Comprobante', 'bankitos') . '" hidden></iframe><img src="" alt="" loading="lazy" hidden></div></div>';
    }

    protected static function get_user_approved_total(int $user_id): float {
        if ($user_id <= 0) {
            return 0.0;
        }

        $approved = get_posts([
            'post_type'      => Bankitos_CPT::SLUG_APORTE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'author'         => $user_id,
        ]);

        if (empty($approved)) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($approved as $aporte_id) {
            $total += (float) get_post_meta($aporte_id, '_bankitos_monto', true);
        }

        return (float) $total;
    }

    protected static function transform_status_label(?string $status_slug, string $fallback): string {
        switch ($status_slug) {
            case 'publish':
                return __('Aprobado', 'bankitos');
            case 'private':
                return __('Rechazado', 'bankitos');
            default:
                return $fallback;
        }
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
              // Abrir modal mientras se carga para dar feedback inmediato
              modal.removeAttribute('hidden');
              return;
            }

           // Si es PDF u otro tipo de archivo, mostrarlo en el iframe del modal
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