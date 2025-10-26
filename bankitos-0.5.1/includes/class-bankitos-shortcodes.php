<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Shortcodes {

    public static function init() {
        add_shortcode('bankitos_login',            [__CLASS__, 'login']);
        add_shortcode('bankitos_register',         [__CLASS__, 'register']);
        add_shortcode('bankitos_panel',            [__CLASS__, 'panel']);
        add_shortcode('bankitos_crear_banco_form', [__CLASS__, 'crear_banco']);
        add_shortcode('bankitos_aporte_form',      [__CLASS__, 'aporte_form']);
        add_shortcode('bankitos_tesorero_aportes', [__CLASS__, 'tesorero_list']);
        add_shortcode('bankitos_veedor_aportes',   [__CLASS__, 'veedor_list']);
    }

    private static function top_notice_from_query(): string {
        $html = '';
        if (!empty($_GET['ok'])) {
            $ok = sanitize_key($_GET['ok']);
            $map_ok = [
                'creado'          => __('¡Listo! Tu B@nko se creó correctamente.', 'bankitos'),
                'aporte_enviado'  => __('Aporte enviado para validación.', 'bankitos'),
                'aporte_aprobado' => __('Aporte aprobado.', 'bankitos'),
                'aporte_rechazado'=> __('Aporte rechazado.', 'bankitos'),
            ];
            if (!empty($map_ok[$ok])) $html .= '<div class="bankitos-success">'.esc_html($map_ok[$ok]).'</div>';
        }
        if (empty($_GET['err'])) return $html;
        $err = sanitize_key($_GET['err']);
        $map = [
            'recaptcha'=>__('No pudimos verificar que no eres un robot.','bankitos'),
            'validacion'=>__('Revisa los campos obligatorios.','bankitos'),
            'crear_post'=>__('Ocurrió un problema creando el B@nko.','bankitos'),
            'ya_miembro'=>__('Ya perteneces a un B@nko.','bankitos'),
            'tasa_rango'=>__('La tasa debe estar entre 0.1 y 3.0.','bankitos'),
            'duracion_invalida'=>__('Duración inválida.','bankitos'),
            'periodicidad_req'=>__('Selecciona una periodicidad válida.','bankitos'),
            'cuota_min'=>__('La cuota mínima es 1.000.','bankitos'),
            'nombre_req'=>__('El nombre del B@nko es obligatorio.','bankitos'),
            'objetivo_req'=>__('El objetivo del B@nko es obligatorio.','bankitos'),
            'permiso'=>__('No tienes permisos para realizar esta acción.','bankitos'),
            'no_banco'=>__('No perteneces a ningún B@nko.','bankitos'),
            'monto'=>__('Debes ingresar un monto válido.','bankitos'),
            'crear_aporte'=>__('No pudimos crear el aporte.','bankitos'),
        ];
        $msg = $map[$err] ?? __('Ha ocurrido un error. Intenta nuevamente.','bankitos');
        return '<div class="bankitos-error">'.esc_html($msg).'</div>';
    }

    public static function login() {
        ob_start(); ?>
        <div class="bankitos-login-wrap">
          <h2><?php esc_html_e('Iniciar sesión','bankitos'); ?></h2>
          <form class="bankitos-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php echo wp_nonce_field('bankitos_do_login','_wpnonce', true, false); ?>
            <input type="hidden" name="action" value="bankitos_do_login">
            <div class="bankitos-field">
              <label for="bk_login_email"><?php esc_html_e('Email','bankitos'); ?></label>
              <input id="bk_login_email" type="email" name="email" required autocomplete="email">
            </div>
            <div class="bankitos-field">
              <label for="bk_login_pass"><?php esc_html_e('Contraseña','bankitos'); ?></label>
              <input id="bk_login_pass" type="password" name="password" required autocomplete="current-password">
            </div>
            <div class="bankitos-field-inline">
              <label><input type="checkbox" name="remember" value="1"> <?php esc_html_e('Recordarme','bankitos'); ?></label>
            </div>
            <div class="bankitos-actions"><button type="submit" class="bankitos-btn"><?php esc_html_e('Ingresar','bankitos'); ?></button></div>
          </form>
          <p class="bankitos-register-link" style="margin-top:1rem"><?php esc_html_e('¿No tienes cuenta?','bankitos'); ?> <a href="<?php echo esc_url(site_url('/registrarse')); ?>"><?php esc_html_e('Regístrate','bankitos'); ?></a></p>
        </div>
        <?php return ob_get_clean();
    }

    public static function register() {
        if (is_user_logged_in()) { wp_safe_redirect(site_url('/panel')); exit; }
        ob_start(); ?>
        <div class="bankitos-register-wrap">
          <h2><?php esc_html_e('Crear cuenta','bankitos'); ?></h2>
          <form class="bankitos-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php echo wp_nonce_field('bankitos_do_register','_wpnonce', true, false); ?>
            <input type="hidden" name="action" value="bankitos_do_register">
            <div class="bankitos-field">
              <label for="bk_reg_name"><?php esc_html_e('Nombre','bankitos'); ?></label>
              <input id="bk_reg_name" type="text" name="name" required autocomplete="name">
            </div>
            <div class="bankitos-field">
              <label for="bk_reg_email"><?php esc_html_e('Email','bankitos'); ?></label>
              <input id="bk_reg_email" type="email" name="email" required autocomplete="email">
            </div>
            <div class="bankitos-field">
              <label for="bk_reg_pass"><?php esc_html_e('Contraseña','bankitos'); ?></label>
              <input id="bk_reg_pass" type="password" name="password" required autocomplete="new-password">
            </div>
            <div class="bankitos-actions"><button type="submit" class="bankitos-btn"><?php esc_html_e('Registrarme','bankitos'); ?></button></div>
          </form>
          <p style="margin-top:1rem"><?php esc_html_e('¿Ya tienes cuenta?','bankitos'); ?> <a href="<?php echo esc_url(site_url('/acceder')); ?>"><?php esc_html_e('Inicia sesión','bankitos'); ?></a></p>
        </div>
        <?php return ob_get_clean();
    }

    public static function panel() {
        if (!is_user_logged_in()) {
            return '<div class="bankitos-panel"><p>'.esc_html__('Debes iniciar sesión para ver tu panel.', 'bankitos').' <a href="'.esc_url(site_url('/acceder')).'">'.esc_html__('Acceder', 'bankitos').'</a></p></div>';
        }
        $u = wp_get_current_user();
        $name = $u->display_name ?: $u->user_login;
        $banco_id = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($u->ID) : 0;
        ob_start(); ?>
        <div class="bankitos-panel">
          <?php echo self::top_notice_from_query(); ?>
          <h2><?php echo sprintf(esc_html__('Bienvenido, %s','bankitos'), esc_html($name)); ?></h2>
          <?php if ($banco_id > 0): $title = get_the_title($banco_id); $ficha = get_permalink($banco_id) ?: '#'; ?>
            <p><?php echo sprintf(esc_html__('Tu B@nko: %s','bankitos'), '<strong>'.esc_html($title).'</strong>'); ?></p>
            <p><a class="button bankitos-btn" href="<?php echo esc_url($ficha); ?>"><?php esc_html_e('Ver ficha del B@nko','bankitos'); ?></a></p>
            <hr style="margin:1rem 0;opacity:.2">
            <p><strong><?php esc_html_e('Acciones rápidas','bankitos'); ?>:</strong></p>
            <ul>
              <li><a href="<?php echo esc_url(site_url('/mi-aporte')); ?>"><?php esc_html_e('Subir aporte','bankitos'); ?></a></li>
              <?php if (current_user_can('approve_aportes')): ?>
                <li><a href="<?php echo esc_url(site_url('/tesoreria-aportes')); ?>"><?php esc_html_e('Aprobar aportes (Tesorero)','bankitos'); ?></a></li>
              <?php endif; ?>
              <?php if (current_user_can('audit_aportes')): ?>
                <li><a href="<?php echo esc_url(site_url('/auditoria-aportes')); ?>"><?php esc_html_e('Aportes aprobados (Veedor)','bankitos'); ?></a></li>
              <?php endif; ?>
            </ul>
          <?php else: ?>
            <p><?php esc_html_e('Aún no perteneces a un B@nko.','bankitos'); ?></p>
            <p><a class="button bankitos-btn" href="<?php echo esc_url(site_url('/crear-banko')); ?>"><?php esc_html_e('Crear B@nko','bankitos'); ?></a></p>
          <?php endif; ?>
        </div>
        <?php return ob_get_clean();
    }

    public static function crear_banco() {
        if (!is_user_logged_in()) {
            return '<div class="bankitos-form"><p>'.esc_html__('Debes iniciar sesión para crear un B@nko.', 'bankitos').' <a href="'.esc_url(site_url('/acceder')).'">'.esc_html__('Acceder', 'bankitos').'</a></p></div>';
        }
        if (class_exists('Bankitos_Handlers') && Bankitos_Handlers::get_user_banco_id(get_current_user_id()) > 0) {
            return '<div class="bankitos-form"><p>'.esc_html__('Ya perteneces a un B@nko.','bankitos').' <a href="'.esc_url(site_url('/panel')).'">'.esc_html__('Ir al panel','bankitos').'</a></p></div>';
        }
        ob_start(); ?>
        <div class="bankitos-form">
          <h2><?php esc_html_e('Crear B@nko','bankitos'); ?></h2>
          <?php echo self::top_notice_from_query(); ?>

          <form id="bankitos-create-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" novalidate>
            <?php echo wp_nonce_field('bankitos_front_create','_wpnonce', true, false); ?>
            <input type="hidden" name="action" value="bankitos_front_create">

            <div class="bankitos-field" id="wrap_nombre">
              <label for="bk_nombre"><?php esc_html_e('Nombre del B@nko','bankitos'); ?></label>
              <input id="bk_nombre" type="text" name="nombre" required maxlength="140" autocomplete="off">
              <small class="bankitos-field-error" id="err_nombre" aria-live="polite"></small>
            </div>

            <div class="bankitos-field" id="wrap_obj">
              <label for="bk_obj"><?php esc_html_e('Objetivo','bankitos'); ?></label>
              <textarea id="bk_obj" name="objetivo" rows="4" required placeholder="<?php echo esc_attr__('Describe el propósito de ahorro/crédito','bankitos'); ?>"></textarea>
              <small class="bankitos-field-error" id="err_obj" aria-live="polite"></small>
            </div>

            <div class="bankitos-field" id="wrap_cuota">
              <label for="bk_cuota"><?php esc_html_e('Cuota (monto)','bankitos'); ?></label>
              <input id="bk_cuota" type="number" name="cuota_monto" required min="1000" step="1" inputmode="numeric" placeholder="1000">
              <small class="bankitos-field-error" id="err_cuota" aria-live="polite"></small>
            </div>

            <div class="bankitos-field" id="wrap_per">
              <label for="bk_per"><?php esc_html_e('Periodicidad','bankitos'); ?></label>
              <select id="bk_per" name="periodicidad" required>
                <option value="" disabled selected><?php esc_html_e('Selecciona...','bankitos'); ?></option>
                <option value="semanal"><?php esc_html_e('Semanal','bankitos'); ?></option>
                <option value="quincenal"><?php esc_html_e('Quincenal','bankitos'); ?></option>
                <option value="mensual"><?php esc_html_e('Mensual','bankitos'); ?></option>
              </select>
              <small class="bankitos-field-error" id="err_per" aria-live="polite"></small>
            </div>

            <div class="bankitos-field" id="wrap_tasa">
              <label for="bk_tasa"><?php esc_html_e('Tasa de interés (%)','bankitos'); ?></label>
              <input id="bk_tasa" type="number" name="tasa" required min="0.1" max="3.0" step="0.1" placeholder="0.1 - 3.0">
              <small class="bankitos-field-error" id="err_tasa" aria-live="polite"></small>
            </div>

            <div class="bankitos-field" id="wrap_dur">
              <label for="bk_dur"><?php esc_html_e('Duración (meses)','bankitos'); ?></label>
              <select id="bk_dur" name="duracion_meses" required>
                <option value="" disabled selected><?php esc_html_e('Selecciona...','bankitos'); ?></option>
                <option value="2">2</option><option value="4">4</option><option value="6">6</option><option value="8">8</option><option value="12">12</option>
              </select>
              <small class="bankitos-field-error" id="err_dur" aria-live="polite"></small>
            </div>

            <div class="bankitos-actions">
              <button type="submit" class="bankitos-btn"><?php esc_html_e('Crear B@nko','bankitos'); ?></button>
            </div>
          </form>
        </div>

        <script>
        (function(){
          var f = document.getElementById('bankitos-create-form');
          if(!f) return;
          var B = f.querySelector('button[type=submit]');
          var F = {
            nombre:  document.getElementById('bk_nombre'),
            objetivo:document.getElementById('bk_obj'),
            cuota:   document.getElementById('bk_cuota'),
            per:     document.getElementById('bk_per'),
            tasa:    document.getElementById('bk_tasa'),
            dur:     document.getElementById('bk_dur')
          };
          var W = {
            nombre:  document.getElementById('wrap_nombre'),
            objetivo:document.getElementById('wrap_obj'),
            cuota:   document.getElementById('wrap_cuota'),
            per:     document.getElementById('wrap_per'),
            tasa:    document.getElementById('wrap_tasa'),
            dur:     document.getElementById('wrap_dur')
          };
          var E = {
            nombre:  document.getElementById('err_nombre'),
            objetivo:document.getElementById('err_obj'),
            cuota:   document.getElementById('err_cuota'),
            per:     document.getElementById('err_per'),
            tasa:    document.getElementById('err_tasa'),
            dur:     document.getElementById('err_dur')
          };

          function setErr(k,msg){
            if(!W[k]) return;
            if(msg){ W[k].classList.add('has-error'); if(E[k]){E[k].textContent=msg; E[k].style.display='block';} }
            else{ W[k].classList.remove('has-error'); if(E[k]){E[k].textContent=''; E[k].style.display='none';} }
          }

          function vNombre(){ return F.nombre.value.trim() ? '' : 'Este campo es obligatorio.'; }
          function vObjetivo(){ return F.objetivo.value.trim() ? '' : 'Este campo es obligatorio.'; }
          function vCuota(){
            var t = F.cuota.value;
            if(!t) return 'Este campo es obligatorio.';
            var v = parseFloat(t);
            if(isNaN(v)) return 'Introduce un número válido.';
            if(v < 1000) return 'La cuota mínima es 1.000.';
            return '';
          }
          function vPer(){ return F.per.value ? '' : 'Este campo es obligatorio.'; }
          function vTasa(){
            var t = F.tasa.value;
            if(!t) return 'Este campo es obligatorio.';
            var v = parseFloat(t);
            if(isNaN(v)) return 'Introduce un número válido.';
            if(v < 0.1 || v > 3.0) return 'La tasa debe estar entre 0.1 y 3.0.';
            var red = Math.round(v*10)/10; if(Math.abs(v-red)>1e-9) return 'Usa incrementos de 0.1 (ej. 2.3).';
            return '';
          }
          function vDur(){ return F.dur.value ? '' : 'Este campo es obligatorio.'; }

          function run(){
            var m1=vNombre(); setErr('nombre',m1);
            var m2=vObjetivo(); setErr('objetivo',m2);
            var m3=vCuota(); setErr('cuota',m3);
            var m4=vPer(); setErr('per',m4);
            var m5=vTasa(); setErr('tasa',m5);
            var m6=vDur(); setErr('dur',m6);
            var ok = !(m1||m2||m3||m4||m5||m6);
            if(B){ if(ok){ B.removeAttribute('disabled'); B.classList.remove('is-disabled'); } else { B.setAttribute('disabled','disabled'); B.classList.add('is-disabled'); } }
            return ok;
          }

          ['input','change','blur'].forEach(function(ev){
            Object.keys(F).forEach(function(k){ F[k].addEventListener(ev, run); });
          });

          f.addEventListener('submit', function(e){
            if(!run()){
              e.preventDefault();
              var first = f.querySelector('.has-error input, .has-error select, .has-error textarea');
              if(first){ first.focus({preventScroll:false}); first.scrollIntoView({behavior:'smooth', block:'center'}); }
            }
          });

          run();
        })();
        </script>
        <?php return ob_get_clean();
    }

    public static function aporte_form() {
        if (!is_user_logged_in()) return '<div class="bankitos-form"><p>'.esc_html__('Inicia sesión para subir tu aporte.','bankitos').'</p></div>';
        $user_id=get_current_user_id();
        $banco_id = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($user_id) : 0;
        if ($banco_id <= 0) return '<div class="bankitos-form"><p>'.esc_html__('No perteneces a un B@nko.','bankitos').'</p></div>';
        if (!current_user_can('submit_aportes')) return '<div class="bankitos-form"><p>'.esc_html__('No tienes permiso para enviar aportes.','bankitos').'</p></div>';
        ob_start(); ?>
        <div class="bankitos-form">
          <h3><?php esc_html_e('Subir aporte','bankitos'); ?></h3>
          <?php echo self::top_notice_from_query(); ?>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
            <?php echo wp_nonce_field('bankitos_aporte_submit','_wpnonce', true, false); ?>
            <input type="hidden" name="action" value="bankitos_aporte_submit">
            <div class="bankitos-field">
              <label for="bk_monto"><?php esc_html_e('Monto del aporte','bankitos'); ?></label>
              <input id="bk_monto" type="number" name="monto" step="0.01" min="1" required>
            </div>
            <div class="bankitos-field">
              <label for="bk_comp"><?php esc_html_e('Comprobante (imagen)','bankitos'); ?></label>
              <input id="bk_comp" type="file" name="comprobante" accept="image/*" required>
            </div>
            <div class="bankitos-actions">
              <button type="submit" class="bankitos-btn"><?php esc_html_e('Enviar aporte','bankitos'); ?></button>
            </div>
          </form>
        </div>
        <?php return ob_get_clean();
    }

    public static function tesorero_list() {
        if (!is_user_logged_in()) return '';
        if (!current_user_can('approve_aportes')) return '<div class="bankitos-form"><p>'.esc_html__('No tienes permisos para aprobar aportes.','bankitos').'</p></div>';
        $user_id=get_current_user_id();
        $banco_id = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($user_id) : 0;
        if ($banco_id <= 0) return '<div class="bankitos-form"><p>'.esc_html__('No perteneces a un B@nko.','bankitos').'</p></div>';
        $q = new WP_Query(['post_type'=>Bankitos_CPT::SLUG_APORTE,'post_status'=>'pending','posts_per_page'=>50,'meta_query'=>[['key'=>'_bankitos_banco_id','value'=>$banco_id,'compare'=>'=']]]);
        ob_start(); ?>
        <div class="bankitos-form">
          <h3><?php esc_html_e('Aportes pendientes','bankitos'); ?></h3>
          <?php echo self::top_notice_from_query(); ?>
          <?php if (!$q->have_posts()): ?>
            <p><?php esc_html_e('No hay aportes pendientes.','bankitos'); ?></p>
          <?php else: ?>
            <table class="bankitos-ficha">
              <thead><tr><th><?php esc_html_e('Miembro','bankitos'); ?></th><th><?php esc_html_e('Monto','bankitos'); ?></th><th><?php esc_html_e('Comprobante','bankitos'); ?></th><th><?php esc_html_e('Acciones','bankitos'); ?></th></tr></thead>
              <tbody>
              <?php while($q->have_posts()): $q->the_post(); $aporte_id=get_the_ID(); $monto=get_post_meta($aporte_id,'_bankitos_monto',true); $author=get_userdata(get_post_field('post_author',$aporte_id)); $thumb=get_the_post_thumbnail_url($aporte_id,'medium'); ?>
                <tr>
                  <td><?php echo esc_html($author ? ($author->display_name ?: $author->user_login) : '—'); ?></td>
                  <td><strong><?php echo esc_html(number_format((float)$monto,2,',','.')); ?></strong></td>
                  <td><?php if ($thumb): ?><a href="<?php echo esc_url($thumb); ?>" target="_blank"><?php esc_html_e('Ver imagen','bankitos'); ?></a><?php else: ?>—<?php endif; ?></td>
                  <td>
                    <a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action'=>'bankitos_aporte_approve','aporte'=>$aporte_id], admin_url('admin-post.php')), 'bankitos_aporte_mod')); ?>"><?php esc_html_e('Aprobar','bankitos'); ?></a>
                    <a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action'=>'bankitos_aporte_reject','aporte'=>$aporte_id], admin_url('admin-post.php')), 'bankitos_aporte_mod')); ?>"><?php esc_html_e('Rechazar','bankitos'); ?></a>
                  </td>
                </tr>
              <?php endwhile; wp_reset_postdata(); ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
        <?php return ob_get_clean();
    }

    public static function veedor_list() {
        if (!is_user_logged_in()) return '';
        if (!current_user_can('audit_aportes')) return '<div class="bankitos-form"><p>'.esc_html__('No tienes permisos para auditar aportes.','bankitos').'</p></div>';
        $user_id=get_current_user_id();
        $banco_id = class_exists('Bankitos_Handlers') ? Bankitos_Handlers::get_user_banco_id($user_id) : 0;
        if ($banco_id <= 0) return '<div class="bankitos-form"><p>'.esc_html__('No perteneces a un B@nko.','bankitos').'</p></div>';
        $q = new WP_Query(['post_type'=>Bankitos_CPT::SLUG_APORTE,'post_status'=>'publish','posts_per_page'=>50,'meta_query'=>[['key'=>'_bankitos_banco_id','value'=>$banco_id,'compare'=>'=']]]);
        ob_start(); ?>
        <div class="bankitos-form">
          <h3><?php esc_html_e('Aportes aprobados','bankitos'); ?></h3>
          <?php if (!$q->have_posts()): ?>
            <p><?php esc_html_e('No hay aportes aprobados.','bankitos'); ?></p>
          <?php else: ?>
            <table class="bankitos-ficha">
              <thead><tr><th><?php esc_html_e('Miembro','bankitos'); ?></th><th><?php esc_html_e('Monto','bankitos'); ?></th><th><?php esc_html_e('Comprobante','bankitos'); ?></th><th><?php esc_html_e('Fecha','bankitos'); ?></th></tr></thead>
              <tbody>
              <?php while($q->have_posts()): $q->the_post(); $aporte_id=get_the_ID(); $monto=get_post_meta($aporte_id,'_bankitos_monto',true); $author=get_userdata(get_post_field('post_author',$aporte_id)); $thumb=get_the_post_thumbnail_url($aporte_id,'medium'); ?>
                <tr>
                  <td><?php echo esc_html($author ? ($author->display_name ?: $author->user_login) : '—'); ?></td>
                  <td><strong><?php echo esc_html(number_format((float)$monto,2,',','.')); ?></strong></td>
                  <td><?php if ($thumb): ?><a href="<?php echo esc_url($thumb); ?>" target="_blank"><?php esc_html_e('Ver imagen','bankitos'); ?></a><?php else: ?>—<?php endif; ?></td>
                  <td><?php echo esc_html(get_the_date()); ?></td>
                </tr>
              <?php endwhile; wp_reset_postdata(); ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
        <?php return ob_get_clean();
    }
}
add_action('init', ['Bankitos_Shortcodes', 'init']);
