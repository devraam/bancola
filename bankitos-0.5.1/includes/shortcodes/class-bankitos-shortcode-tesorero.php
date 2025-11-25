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
            return '<div class="bankitos-form"><p>' . esc_html__('No tienes permisos para aprobar aportes.', 'bankitos') . '</p></div>';
        }
        $user_id = get_current_user_id();
        $banco_id = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($user_id) : 0;
        if ($banco_id <= 0) {
            return '<div class="bankitos-form"><p>' . esc_html__('No perteneces a un B@nko.', 'bankitos') . '</p></div>';
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
        <div class="bankitos-form">
          <h3><?php esc_html_e('Aportes pendientes', 'bankitos'); ?></h3>
          <?php echo self::top_notice_from_query(); ?>
          <?php echo self::render_aporte_filter_form('tesorero', $filters); ?>
          <?php if (!$q->have_posts()): ?>
            <p><?php esc_html_e('No hay aportes pendientes.', 'bankitos'); ?></p>
          <?php else: ?>
            <table class="bankitos-ficha">
              <thead><tr><th><?php esc_html_e('Miembro', 'bankitos'); ?></th><th><?php esc_html_e('Monto', 'bankitos'); ?></th><th><?php esc_html_e('Comprobante', 'bankitos'); ?></th><th><?php esc_html_e('Acciones', 'bankitos'); ?></th></tr></thead>
              <tbody>
              <?php while ($q->have_posts()): $q->the_post();
                  $aporte_id = get_the_ID();
                  $monto     = get_post_meta($aporte_id, '_bankitos_monto', true);
                  $author    = get_userdata(get_post_field('post_author', $aporte_id));
                  $thumb     = class_exists('BK_Aportes_Handler') ? BK_Aportes_Handler::get_comprobante_view_src($aporte_id) : '';
              ?>
                <tr>
                  <td><?php echo esc_html($author ? ($author->display_name ?: $author->user_login) : '—'); ?></td>
                  <td><strong><?php echo esc_html(number_format((float) $monto, 2, ',', '.')); ?></strong></td>
                  <td><?php if ($thumb): ?><a href="<?php echo esc_url($thumb); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Ver comprobante', 'bankitos'); ?></a><?php else: ?>—<?php endif; ?></td>
                  <td>
                    <?php $redirect = self::get_current_url(); ?>
                    <a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'bankitos_aporte_approve', 'aporte' => $aporte_id, 'redirect_to' => $redirect], admin_url('admin-post.php')), 'bankitos_aporte_mod')); ?>"><?php esc_html_e('Aprobar', 'bankitos'); ?></a>
                    <a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'bankitos_aporte_reject', 'aporte' => $aporte_id, 'redirect_to' => $redirect], admin_url('admin-post.php')), 'bankitos_aporte_mod')); ?>"><?php esc_html_e('Rechazar', 'bankitos'); ?></a>
                  </td>
                </tr>
              <?php endwhile; wp_reset_postdata(); ?>
              </tbody>
            </table>
            <?php echo self::render_aporte_pagination($q, $filters['page_key'], $filters['query_args'], $filters['page']); ?>
          <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}