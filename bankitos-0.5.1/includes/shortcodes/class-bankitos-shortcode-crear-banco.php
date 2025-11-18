<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Shortcode_Crear_Banco extends Bankitos_Shortcode_Base {

    public static function register(): void {
        self::register_shortcode('bankitos_crear_banco_form');
    }

    public static function render($atts = [], $content = null): string {
        if (class_exists('Bankitos_Handlers') && Bankitos_Handlers::get_user_banco_id(get_current_user_id()) > 0) {
            return '<div class="bankitos-form"><p>' . esc_html__('Ya perteneces a un B@nko.', 'bankitos') . ' <a href="' . esc_url(site_url('/panel')) . '">' . esc_html__('Ir al panel', 'bankitos') . '</a></p></div>';
        }
        if (class_exists('Bankitos_Recaptcha') && !Bankitos_Recaptcha::is_enabled()) {
            return '<div class="bankitos-form"><p>' . esc_html__('No es posible crear un B@nko hasta que el administrador configure reCAPTCHA.', 'bankitos') . '</p></div>';
        }
        
        self::enqueue_create_banco_assets();
        ob_start(); ?>
        <div class="bankitos-form">
          <h2><?php esc_html_e('Crear B@nko', 'bankitos'); ?></h2>
          <?php echo self::top_notice_from_query(); ?>

          <?php $create_recaptcha = class_exists('Bankitos_Recaptcha') ? Bankitos_Recaptcha::field('crear_banco') : ''; ?>
          <form id="bankitos-create-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" novalidate<?php if ($create_recaptcha) echo ' data-bankitos-recaptcha="crear_banco"'; ?>>
            <?php echo wp_nonce_field('bankitos_front_create', '_wpnonce', true, false); ?>
            <input type="hidden" name="action" value="bankitos_front_create">
            <?php echo $create_recaptcha; ?>
            <div class="bankitos-field" id="wrap_nombre">
              <label for="bk_nombre"><?php esc_html_e('Nombre del B@nko', 'bankitos'); ?></label>
              <input id="bk_nombre" type="text" name="nombre" required maxlength="140" autocomplete="off">
              <small class="bankitos-field-error" id="err_nombre" aria-live="polite"></small>
            </div>

            <div class="bankitos-field" id="wrap_obj">
              <label for="bk_obj"><?php esc_html_e('Objetivo', 'bankitos'); ?></label>
              <textarea id="bk_obj" name="objetivo" rows="4" required placeholder="<?php echo esc_attr__('Describe el propósito de ahorro/crédito', 'bankitos'); ?>"></textarea>
              <small class="bankitos-field-error" id="err_obj" aria-live="polite"></small>
            </div>

            <div class="bankitos-field" id="wrap_cuota">
              <label for="bk_cuota"><?php esc_html_e('Cuota (monto)', 'bankitos'); ?></label>
              <input id="bk_cuota" type="number" name="cuota_monto" required min="1000" step="1" inputmode="numeric" placeholder="1000">
              <small class="bankitos-field-error" id="err_cuota" aria-live="polite"></small>
            </div>

            <div class="bankitos-field" id="wrap_per">
              <label for="bk_per"><?php esc_html_e('Periodicidad', 'bankitos'); ?></label>
              <select id="bk_per" name="periodicidad" required>
                <option value="" disabled selected><?php esc_html_e('Selecciona...', 'bankitos'); ?></option>
                <option value="semanal"><?php esc_html_e('Semanal', 'bankitos'); ?></option>
                <option value="quincenal"><?php esc_html_e('Quincenal', 'bankitos'); ?></option>
                <option value="mensual"><?php esc_html_e('Mensual', 'bankitos'); ?></option>
              </select>
              <small class="bankitos-field-error" id="err_per" aria-live="polite"></small>
            </div>

            <div class="bankitos-field" id="wrap_tasa">
              <label for="bk_tasa"><?php esc_html_e('Tasa de interés (%)', 'bankitos'); ?></label>
              <input id="bk_tasa" type="number" name="tasa" required min="0.1" max="3.0" step="0.1" placeholder="0.1 - 3.0">
              <small class="bankitos-field-error" id="err_tasa" aria-live="polite"></small>
            </div>

            <div class="bankitos-field" id="wrap_dur">
              <label for="bk_dur"><?php esc_html_e('Duración (meses)', 'bankitos'); ?></label>
              <select id="bk_dur" name="duracion_meses" required>
                <option value="" disabled selected><?php esc_html_e('Selecciona...', 'bankitos'); ?></option>
                <option value="2">2</option>
                <option value="4">4</option>
                <option value="6">6</option>
                <option value="8">8</option>
                <option value="12">12</option>
              </select>
              <small class="bankitos-field-error" id="err_dur" aria-live="polite"></small>
            </div>

            <div class="bankitos-actions">
              <button type="submit" class="bankitos-btn"><?php esc_html_e('Crear B@nko', 'bankitos'); ?></button>
            </div>
          </form>
        </div>
        <?php
        return ob_get_clean();
    }
}