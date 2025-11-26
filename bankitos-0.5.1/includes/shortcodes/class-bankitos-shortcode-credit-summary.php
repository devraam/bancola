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
                        <dt><?php esc_html_e('Plazo', 'bankitos'); ?></dt>
                        <dd><?php echo esc_html(sprintf(_n('%s mes', '%s meses', (int) $request['term_months'], 'bankitos'), number_format_i18n((int) $request['term_months']))); ?></dd>
                      </div>
                      <div>
                        <dt><?php esc_html_e('Documento', 'bankitos'); ?></dt>
                        <dd><?php echo esc_html($request['document_id']); ?></dd>
                      </div>
                      <div>
                        <dt><?php esc_html_e('Teléfono', 'bankitos'); ?></dt>
                        <dd><?php echo esc_html($request['phone']); ?></dd>
                      </div>
                    </dl>
                    <div class="bankitos-credit-summary__description">
                      <h5><?php esc_html_e('Uso del crédito', 'bankitos'); ?></h5>
                      <p><?php echo nl2br(esc_html($request['description'])); ?></p>
                    </div>
                  </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
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
}