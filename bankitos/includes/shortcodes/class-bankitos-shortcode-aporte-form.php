<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Shortcode_Aporte_Form extends Bankitos_Shortcode_Base {

    public static function register(): void {
        self::register_shortcode('bankitos_aporte_form');
    }

    /**
     * @param array|string $atts
     * @param string|null $content
     */
    public static function render($atts = [], $content = null): string {
        if (!is_user_logged_in()) {
            return '<div class="bankitos-form bankitos-panel"><p>' . esc_html__('Inicia sesión para subir tu aporte.', 'bankitos') . '</p></div>';
        }
        $user_id = get_current_user_id();
        $banco_id = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($user_id) : 0;
        if ($banco_id <= 0) {
            return '<div class="bankitos-form bankitos-panel"><p>' . esc_html__('No perteneces a un B@nko.', 'bankitos') . '</p></div>';
        }

        if (!current_user_can('submit_aportes')) {
            return '<div class="bankitos-form bankitos-panel"><p>' . esc_html__('No tienes permiso para enviar aportes.', 'bankitos') . '</p></div>';
        }

        ob_start(); ?>
        <div class="bankitos-form bankitos-panel">
          <h3><?php esc_html_e('Subir aporte', 'bankitos'); ?></h3>
          <?php echo self::top_notice_from_query(); ?>
          <form id="bk-aporte-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" data-bankitos-min-form>
            <?php echo wp_nonce_field('bankitos_aporte_submit', '_wpnonce', true, false); ?>
            <input type="hidden" name="action" value="bankitos_aporte_submit">

            <div class="bankitos-field">
              <label for="bk_monto"><?php esc_html_e('Monto total del aporte', 'bankitos'); ?></label>
              <input id="bk_monto" type="number" name="monto" step="0.01" min="1000" required data-bankitos-min-amount="1000">
              <span class="bankitos-field-error" data-bankitos-min-error></span>
            </div>

            <div class="bankitos-field bankitos-field--checkbox">
              <label>
                <input type="checkbox" id="bk_tiene_multa" name="tiene_multa" value="1">
                <?php esc_html_e('Este aporte incluye una multa', 'bankitos'); ?>
              </label>
              <small class="bankitos-field-hint"><?php esc_html_e('Activa esto si parte del monto corresponde a una sanción que debe distribuirse entre todos los socios.', 'bankitos'); ?></small>
            </div>

            <div id="bk_multa_fields" class="bankitos-field" style="display:none; border-left:3px solid #f59e0b; padding-left:1rem; margin-top:0.5rem;">
              <label for="bk_fine"><?php esc_html_e('Monto de la multa (parte del total)', 'bankitos'); ?></label>
              <input id="bk_fine" type="number" name="fine_amount" step="0.01" min="0" value="0" placeholder="0">
              <small class="bankitos-field-hint" id="bk_savings_hint"></small>
            </div>

            <div class="bankitos-field">
              <label for="bk_comp"><?php esc_html_e('Comprobante (imagen o PDF, máximo 10MB)', 'bankitos'); ?></label>
              <input id="bk_comp" type="file" name="comprobante" accept=".jpg,.jpeg,.png,.pdf,image/*" required>
            </div>
            <div class="bankitos-actions">
              <button type="submit" class="bankitos-btn"><?php esc_html_e('Registrar aporte', 'bankitos'); ?></button>
            </div>
          </form>
        </div>
        <script>
        (function(){
          var checkbox  = document.getElementById('bk_tiene_multa');
          var fields    = document.getElementById('bk_multa_fields');
          var montoEl   = document.getElementById('bk_monto');
          var fineEl    = document.getElementById('bk_fine');
          var hintEl    = document.getElementById('bk_savings_hint');
          var form      = document.getElementById('bk-aporte-form');
          if (!checkbox || !fields || !montoEl || !fineEl) return;

          function updateHint() {
            var monto = parseFloat(montoEl.value) || 0;
            var fine  = parseFloat(fineEl.value) || 0;
            var savings = monto - fine;
            if (savings > 0) {
              hintEl.textContent = '<?php echo esc_js(__('Ahorro neto que se acredita a tu cuenta: ', 'bankitos')); ?>' + savings.toLocaleString('es-CO', {style:'currency', currency:'COP', minimumFractionDigits:0});
            } else if (fine > 0) {
              hintEl.textContent = '<?php echo esc_js(__('El monto de la multa no puede ser igual o mayor al total del aporte.', 'bankitos')); ?>';
            } else {
              hintEl.textContent = '';
            }
          }

          checkbox.addEventListener('change', function() {
            fields.style.display = this.checked ? 'block' : 'none';
            if (!this.checked) {
              fineEl.value = '0';
              hintEl.textContent = '';
            } else {
              updateHint();
            }
          });

          montoEl.addEventListener('input', updateHint);
          fineEl.addEventListener('input', updateHint);

          form.addEventListener('submit', function(e) {
            if (!checkbox.checked) return; // no fine, nothing to validate
            var monto = parseFloat(montoEl.value) || 0;
            var fine  = parseFloat(fineEl.value) || 0;
            if (fine <= 0) { fineEl.value = '0'; return; }
            if (fine >= monto) {
              e.preventDefault();
              hintEl.textContent = '<?php echo esc_js(__('El monto de la multa no puede ser igual o mayor al total del aporte.', 'bankitos')); ?>';
              fineEl.focus();
            }
          });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}