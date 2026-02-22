<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Shortcode_Tesorero_List extends Bankitos_Shortcode_Base {

    public static function register(): void {
        self::register_shortcode('bankitos_tesorero_aportes');
    }

    public static function render($atts = [], $content = null): string {
        if (!is_user_logged_in()) {
            return '';
        }
        if (!current_user_can('approve_aportes')) {
            return '<div class="bankitos-form bankitos-panel"><p>' . esc_html__('No tienes permisos para aprobar aportes.', 'bankitos') . '</p></div>';
        }
        $user_id = get_current_user_id();
        $banco_id = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($user_id) : 0;
        if ($banco_id <= 0) {
            return '<div class="bankitos-form bankitos-panel"><p>' . esc_html__('No perteneces a un B@nko.', 'bankitos') . '</p></div>';
        }
        $filters  = self::get_aporte_filters('tesorero');
        $per_page = (int) apply_filters('bankitos_aportes_per_page', 20, 'pending');
        $args = [
            'post_type'      => Bankitos_CPT::SLUG_APORTE,
            'post_status'    => 'pending',
            'posts_per_page' => $per_page,
            'paged'          => $filters['page'],
            'meta_query'     => [[
                'key'   => '_bankitos_banco_id',
                'value' => $banco_id,
                'compare' => '=',
            ]],
        ];
        if (!empty($filters['date_query'])) {
            $args['date_query'] = $filters['date_query'];
        }
        $q = new WP_Query($args);
        ob_start(); ?>
        <div class="bankitos-form bankitos-panel">
          <h3><?php esc_html_e('Aportes pendientes', 'bankitos'); ?></h3>
          <?php echo self::top_notice_from_query(); ?>
          <?php echo self::render_aporte_filter_form('tesorero', $filters); ?>
          <?php if (!$q->have_posts()): ?>
            <p><?php esc_html_e('No hay aportes pendientes.', 'bankitos'); ?></p>
          <?php else: ?>
            <div class="bankitos-accordion" role="list">
              <?php $is_first = true; $redirect = self::get_current_url(); while ($q->have_posts()): $q->the_post();
                  $aporte_id = get_the_ID();
                  $monto     = get_post_meta($aporte_id, '_bankitos_monto', true);
                  $author    = get_userdata(get_post_field('post_author', $aporte_id));
                  $thumb     = class_exists('BK_Aportes_Handler') ? BK_Aportes_Handler::get_comprobante_view_src($aporte_id) : '';
                  $is_image  = false;
                  if ($thumb && class_exists('BK_Aportes_Handler')) {
                      $is_image = BK_Aportes_Handler::is_file_image(get_post_thumbnail_id($aporte_id));
                  }
              ?>
                <details class="bankitos-accordion__item" role="listitem" <?php echo $is_first ? 'open' : ''; ?>>
                  <summary class="bankitos-accordion__summary">
                    <div class="bankitos-accordion__title">
                      <span class="bankitos-accordion__amount"><?php echo esc_html(self::format_currency((float) $monto)); ?></span>
                      <span class="bankitos-accordion__name"><?php echo esc_html($author ? ($author->display_name ?: $author->user_login) : '—'); ?></span>
                    </div>
                    <div class="bankitos-accordion__meta">
                      <span><?php echo esc_html(get_the_date()); ?></span>
                      <span class="bankitos-pill bankitos-pill--pending"><?php esc_html_e('Pendiente', 'bankitos'); ?></span>
                      <span class="bankitos-accordion__chevron" aria-hidden="true"></span>
                    </div>
                  </summary>
                  <div class="bankitos-accordion__content">
                    <dl class="bankitos-accordion__grid">
                      <div>
                        <dt><?php esc_html_e('Miembro', 'bankitos'); ?></dt>
                        <dd><?php echo esc_html($author ? ($author->display_name ?: $author->user_login) : '—'); ?></dd>
                      </div>
                      <div>
                        <dt><?php esc_html_e('Monto', 'bankitos'); ?></dt>
                        <dd><?php echo esc_html(self::format_currency((float) $monto)); ?></dd>
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
                    <div class="bankitos-accordion__actions" aria-label="<?php esc_attr_e('Acciones del aporte', 'bankitos'); ?>">
                      <a class="bankitos-btn bankitos-btn--small" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'bankitos_aporte_approve', 'aporte' => $aporte_id, 'redirect_to' => $redirect], admin_url('admin-post.php')), 'bankitos_aporte_mod')); ?>"><?php esc_html_e('Aprobar', 'bankitos'); ?></a>
                      <a class="bankitos-btn bankitos-btn--small bankitos-btn--danger" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'bankitos_aporte_reject', 'aporte' => $aporte_id, 'redirect_to' => $redirect], admin_url('admin-post.php')), 'bankitos_aporte_mod')); ?>"><?php esc_html_e('Rechazar', 'bankitos'); ?></a>
                    </div>
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