<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Shortcode_Credit_Request extends Bankitos_Shortcode_Panel_Base {

    public static function register(): void {
        self::register_shortcode('bankitos_credit_request');
    }

    public static function render($atts = [], $content = null): string {
        if (!is_user_logged_in()) {
            return '<div class="bankitos-form"><p>' . esc_html__('Inicia sesión para solicitar un crédito.', 'bankitos') . '</p></div>';
        }

        $context = self::get_panel_context();
        if ($context['banco_id'] <= 0) {
            return '<div class="bankitos-panel__message">' . esc_html__('Debes pertenecer a un B@nko para solicitar un crédito.', 'bankitos') . '</div>';
        }

        $types = Bankitos_Credit_Requests::get_credit_types();
        if (empty($types)) {
            return '<div class="bankitos-panel__message">' . esc_html__('Por ahora no hay tipos de crédito disponibles.', 'bankitos') . '</div>';
        }

        $terms = Bankitos_Credit_Requests::get_term_options();
        $current_url = self::get_current_url();

        ob_start(); ?>
        <section class="bankitos-credit-request" aria-labelledby="bankitos-credit-request-title">
          <div class="bankitos-credit-request__header">
            <p class="bankitos-credit-request__badge"><?php esc_html_e('Solicitud de crédito', 'bankitos'); ?></p>
            <h3 id="bankitos-credit-request-title"><?php esc_html_e('Completa la información de tu solicitud', 'bankitos'); ?></h3>
            <p class="bankitos-credit-request__intro"><?php esc_html_e('El comité revisará tu solicitud y te notificará la decisión por los medios habituales.', 'bankitos'); ?></p>
          </div>
          <?php echo self::top_notice_from_query(); ?>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="bankitos-form bankitos-credit-request__form">
            <?php echo wp_nonce_field('bankitos_credito_solicitar', '_wpnonce', true, false); ?>
            <input type="hidden" name="action" value="bankitos_credito_solicitar">
            <input type="hidden" name="redirect_to" value="<?php echo esc_url($current_url); ?>">
            <div class="bankitos-credit-request__group">
              <div class="bankitos-field">
                <label for="bk_documento"><?php esc_html_e('Documento de identidad', 'bankitos'); ?></label>
                <input id="bk_documento" type="text" name="documento" required autocomplete="off" placeholder="1234567890">
              </div>
              <div class="bankitos-field">
                <label for="bk_edad"><?php esc_html_e('Edad', 'bankitos'); ?></label>
                <input id="bk_edad" type="number" name="edad" min="18" max="120" required>
              </div>
              <div class="bankitos-field">
                <label for="bk_telefono"><?php esc_html_e('Teléfono de contacto', 'bankitos'); ?></label>
                <input id="bk_telefono" type="tel" name="telefono" required placeholder="3001234567">
              </div>
            </div>
            <div class="bankitos-credit-request__group">
              <div class="bankitos-field">
                <label for="bk_tipo_credito"><?php esc_html_e('Tipo de crédito', 'bankitos'); ?></label>
                <select id="bk_tipo_credito" name="tipo_credito" required>
                  <option value=""><?php esc_html_e('Selecciona una opción', 'bankitos'); ?></option>
                  <?php foreach ($types as $key => $label): ?>
                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="bankitos-field">
                <label for="bk_monto_credito"><?php esc_html_e('Monto solicitado', 'bankitos'); ?></label>
                <input id="bk_monto_credito" type="number" name="monto" min="1" step="0.01" required placeholder="0,00">
              </div>
              <div class="bankitos-field">
                <label for="bk_plazo"><?php esc_html_e('Tiempo de pago (meses)', 'bankitos'); ?></label>
                <select id="bk_plazo" name="plazo" required>
                  <option value=""><?php esc_html_e('Selecciona un plazo', 'bankitos'); ?></option>
                  <?php foreach ($terms as $term): ?>
                    <option value="<?php echo esc_attr($term); ?>"><?php echo esc_html(sprintf(_n('%s mes', '%s meses', (int) $term, 'bankitos'), number_format_i18n((int) $term))); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="bankitos-field">
              <label for="bk_descripcion"><?php esc_html_e('¿Para qué usarás el crédito?', 'bankitos'); ?></label>
              <textarea id="bk_descripcion" name="descripcion" rows="4" required placeholder="<?php echo esc_attr__('Describe cómo invertirás o usarás este crédito.', 'bankitos'); ?>"></textarea>
            </div>
            <label class="bankitos-credit-request__signature">
              <input type="checkbox" name="firma" value="1" required>
              <span><?php esc_html_e('Confirmo que la información suministrada es verdadera y autorizo al comité a revisar mi solicitud.', 'bankitos'); ?></span>
            </label>
            <div class="bankitos-credit-request__actions">
              <button type="submit" class="bankitos-btn"><?php esc_html_e('Enviar solicitud', 'bankitos'); ?></button>
            </div>
            </form>
        </section>
        <?php
        return ob_get_clean();
    }
    protected static function render_for_guest($atts = [], $content = null): string {
        return '<div class="bankitos-panel__message">' . sprintf('%s <a href="%s">%s</a>', esc_html__('Debes iniciar sesión para solicitar un crédito.', 'bankitos'), esc_url(site_url('/acceder')), esc_html__('Acceder', 'bankitos')) . '</div>';
    }
}

