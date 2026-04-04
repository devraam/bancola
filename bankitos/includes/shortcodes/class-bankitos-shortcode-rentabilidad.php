<?php
if (!defined('ABSPATH')) exit;

/**
 * Shortcode: [bankitos_rentabilidad]
 *
 * Muestra al socio un desglose de su rentabilidad en el banco:
 *  - Capital ahorrado (aportes aprobados, descontando multas propias)
 *  - Ganancia por intereses de créditos
 *  - Ganancia por multas distribuidas
 *  - Total disponible (capital + ganancias)
 *  - Capacidad crediticia = total × 4
 */
class Bankitos_Shortcode_Rentabilidad extends Bankitos_Shortcode_Panel_Base {

    public static function register(): void {
        self::register_shortcode('bankitos_rentabilidad');
    }

    public static function render($atts = [], $content = null): string {
        if (!is_user_logged_in()) {
            return '';
        }

        $user_id  = get_current_user_id();
        $banco_id = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($user_id) : 0;

        if ($banco_id <= 0) {
            return '<div class="bankitos-form bankitos-panel"><p>' . esc_html__('Aún no perteneces a un B@nko.', 'bankitos') . '</p></div>';
        }

        // Build rentabilidad breakdown
        if (class_exists('Bankitos_Distributions')) {
            $data = Bankitos_Distributions::get_user_rentabilidad($user_id, $banco_id);
        } else {
            $savings = class_exists('Bankitos_Credit_Requests')
                ? Bankitos_Credit_Requests::get_user_savings_total($user_id, $banco_id)
                : 0.0;
            $data = ['savings' => $savings, 'interests' => 0.0, 'fines' => 0.0, 'total' => $savings];
        }

        $credit_capacity = $data['total'] * 4;

        ob_start(); ?>
        <div class="bankitos-form bankitos-panel bankitos-rentabilidad">
          <div class="bankitos-panel-info__header">
            <div>
              <h3><?php esc_html_e('Mi Rentabilidad', 'bankitos'); ?></h3>
              <p class="bankitos-rentabilidad__intro"><?php esc_html_e('Resumen de tu participación financiera en el banco.', 'bankitos'); ?></p>
            </div>
          </div>

          <dl class="bankitos-rentabilidad__grid">
            <div class="bankitos-rentabilidad__card">
              <dt><?php esc_html_e('Capital ahorrado', 'bankitos'); ?></dt>
              <dd class="bankitos-rentabilidad__value"><?php echo esc_html(self::format_currency($data['savings'])); ?></dd>
              <dd class="bankitos-rentabilidad__hint"><?php esc_html_e('Suma de tus aportes aprobados.', 'bankitos'); ?></dd>
            </div>

            <div class="bankitos-rentabilidad__card bankitos-rentabilidad__card--positive">
              <dt><?php esc_html_e('Ganancia por intereses', 'bankitos'); ?></dt>
              <dd class="bankitos-rentabilidad__value"><?php echo esc_html(self::format_currency($data['interests'])); ?></dd>
              <dd class="bankitos-rentabilidad__hint"><?php esc_html_e('Tu parte de los intereses pagados por créditos otorgados mientras eras socio activo.', 'bankitos'); ?></dd>
            </div>

            <div class="bankitos-rentabilidad__card bankitos-rentabilidad__card--positive">
              <dt><?php esc_html_e('Ganancia por multas', 'bankitos'); ?></dt>
              <dd class="bankitos-rentabilidad__value"><?php echo esc_html(self::format_currency($data['fines'])); ?></dd>
              <dd class="bankitos-rentabilidad__hint"><?php esc_html_e('Tu parte de las multas y penalizaciones distribuidas entre los socios del banco.', 'bankitos'); ?></dd>
            </div>

            <div class="bankitos-rentabilidad__card bankitos-rentabilidad__card--total">
              <dt><?php esc_html_e('Total disponible', 'bankitos'); ?></dt>
              <dd class="bankitos-rentabilidad__value bankitos-rentabilidad__value--highlight"><?php echo esc_html(self::format_currency($data['total'])); ?></dd>
              <dd class="bankitos-rentabilidad__hint"><?php esc_html_e('Capital + intereses ganados + multas distribuidas.', 'bankitos'); ?></dd>
            </div>

            <div class="bankitos-rentabilidad__card bankitos-rentabilidad__card--capacity">
              <dt><?php esc_html_e('Capacidad de crédito', 'bankitos'); ?></dt>
              <dd class="bankitos-rentabilidad__value bankitos-rentabilidad__value--highlight"><?php echo esc_html(self::format_currency($credit_capacity)); ?></dd>
              <dd class="bankitos-rentabilidad__hint"><?php esc_html_e('Monto máximo que puedes solicitar en crédito (4× tu total disponible).', 'bankitos'); ?></dd>
            </div>
          </dl>

          <?php if ($data['interests'] > 0 || $data['fines'] > 0): ?>
          <div class="bankitos-rentabilidad__breakdown">
            <h4><?php esc_html_e('Desglose de ganancias', 'bankitos'); ?></h4>
            <table class="bankitos-rentabilidad__table">
              <thead>
                <tr>
                  <th><?php esc_html_e('Concepto', 'bankitos'); ?></th>
                  <th style="text-align:right;"><?php esc_html_e('Monto', 'bankitos'); ?></th>
                  <th style="text-align:right;"><?php esc_html_e('% del total', 'bankitos'); ?></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><?php esc_html_e('Capital ahorrado', 'bankitos'); ?></td>
                  <td style="text-align:right;"><?php echo esc_html(self::format_currency($data['savings'])); ?></td>
                  <td style="text-align:right;"><?php echo $data['total'] > 0 ? esc_html(number_format_i18n($data['savings'] / $data['total'] * 100, 1)) . '%' : '—'; ?></td>
                </tr>
                <?php if ($data['interests'] > 0): ?>
                <tr>
                  <td><?php esc_html_e('Intereses de créditos', 'bankitos'); ?></td>
                  <td style="text-align:right;"><?php echo esc_html(self::format_currency($data['interests'])); ?></td>
                  <td style="text-align:right;"><?php echo $data['total'] > 0 ? esc_html(number_format_i18n($data['interests'] / $data['total'] * 100, 1)) . '%' : '—'; ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($data['fines'] > 0): ?>
                <tr>
                  <td><?php esc_html_e('Multas distribuidas', 'bankitos'); ?></td>
                  <td style="text-align:right;"><?php echo esc_html(self::format_currency($data['fines'])); ?></td>
                  <td style="text-align:right;"><?php echo $data['total'] > 0 ? esc_html(number_format_i18n($data['fines'] / $data['total'] * 100, 1)) . '%' : '—'; ?></td>
                </tr>
                <?php endif; ?>
                <tr style="font-weight:bold; border-top:2px solid #e5e7eb;">
                  <td><?php esc_html_e('Total', 'bankitos'); ?></td>
                  <td style="text-align:right;"><?php echo esc_html(self::format_currency($data['total'])); ?></td>
                  <td style="text-align:right;">100%</td>
                </tr>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
