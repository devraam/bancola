<?php
if (!defined('ABSPATH')) exit;

class Bankitos_CPT {

    const SLUG_BANCO  = 'banco';
    const SLUG_APORTE = 'aporte';

    public static function init() {
        self::register_cpts();
        add_action('add_meta_boxes', [__CLASS__, 'add_banco_metaboxes']);
        add_action('save_post', [__CLASS__, 'save_banco_meta'], 10, 2);
    }

    public static function register_cpts() {
        $banco_caps = [
            'edit_post' => 'edit_banco',
            'read_post' => 'read_banco',
            'delete_post' => 'delete_banco',
            'edit_posts' => 'edit_bancos',
            'edit_others_posts' => 'edit_others_bancos',
            'publish_posts' => 'publish_bancos',
            'read_private_posts' => 'read_private_bancos',
            'delete_posts' => 'delete_bancos',
            'delete_private_posts' => 'delete_private_bancos',
            'delete_published_posts' => 'delete_published_bancos',
            'delete_others_posts' => 'delete_others_bancos',
            'edit_private_posts' => 'edit_private_bancos',
            'edit_published_posts' => 'edit_published_bancos',
            'create_posts' => 'create_bancos',
        ];
        register_post_type(self::SLUG_BANCO,[
            'labels'=>[ 'name'=>__('B@nkos','bankitos'),'singular_name'=>__('B@nko','bankitos'),'menu_name'=>__('B@nkos','bankitos')],
            'public'=>false,'show_ui'=>true,'show_in_menu'=>true,'menu_position'=>26,'menu_icon'=>'dashicons-groups',
            'supports'=>['title','editor','author','thumbnail'],'has_archive'=>false,'rewrite'=>false,'show_in_rest'=>false,
            'map_meta_cap'=>true,'capabilities'=>$banco_caps,
        ]);

        $aporte_caps = [
            'edit_post'=>'edit_aporte','read_post'=>'read_aporte','delete_post'=>'delete_aporte','edit_posts'=>'edit_aportes',
            'edit_others_posts'=>'edit_others_aportes','publish_posts'=>'publish_aportes','read_private_posts'=>'read_private_aportes',
            'delete_posts'=>'delete_aportes','delete_private_posts'=>'delete_private_aportes','delete_published_posts'=>'delete_published_aportes',
            'delete_others_posts'=>'delete_others_aportes','edit_private_posts'=>'edit_private_aportes','edit_published_posts'=>'edit_published_aportes',
            'create_posts'=>'create_aportes',
        ];
        register_post_type(self::SLUG_APORTE,[
            'labels'=>[ 'name'=>__('Aportes','bankitos'),'singular_name'=>__('Aporte','bankitos'),'menu_name'=>__('Aportes','bankitos')],
            'public'=>false,'show_ui'=>true,'show_in_menu'=>'edit.php?post_type='.self::SLUG_BANCO,'supports'=>['title','author','thumbnail'],
            'has_archive'=>false,'rewrite'=>false,'show_in_rest'=>false,'map_meta_cap'=>true,'capabilities'=>$aporte_caps,
        ]);
    }

    public static function add_roles_and_caps() {
        $roles_labels = [
            'socio_general'=>__('Socio General','bankitos'),
            'secretario'=>__('Secretario','bankitos'),
            'tesorero'=>__('Tesorero','bankitos'),
            'presidente'=>__('Presidente','bankitos'),
            'veedor'=>__('Veedor','bankitos'),
        ];
        foreach ($roles_labels as $k=>$label){ if(!get_role($k)) add_role($k,$label,['read'=>true]); }

        $admin_like_caps = [
            'read_banco','read_private_bancos','edit_banco','edit_bancos','edit_private_bancos','edit_published_bancos','edit_others_bancos','delete_banco','delete_bancos','delete_private_bancos','delete_published_bancos','delete_others_bancos','publish_bancos','create_bancos',
            'read_aporte','read_private_aportes','edit_aporte','edit_aportes','edit_private_aportes','edit_published_aportes','edit_others_aportes','delete_aporte','delete_aportes','delete_private_aportes','delete_published_aportes','delete_others_aportes','publish_aportes','create_aportes',
            'submit_aportes','approve_aportes','audit_aportes','manage_bank_invites'
        ];
        self::grant_caps_to_role('presidente',$admin_like_caps);

        self::grant_caps_to_role('tesorero',[
            'read_banco','read_private_bancos','edit_banco','edit_bancos','edit_published_bancos',
            'publish_aportes','edit_aportes','edit_published_aportes','edit_private_aportes',
            'approve_aportes','submit_aportes'
        ]);
        self::grant_caps_to_role('secretario',[
            'read_banco','read_private_bancos','edit_banco','edit_bancos','edit_published_bancos',
            'publish_aportes','edit_aportes','edit_published_aportes','edit_private_aportes',
            'submit_aportes'
        ]);
        self::grant_caps_to_role('veedor',[
            'read_banco','read_private_bancos','read_aporte','read_private_aportes','audit_aportes',
            'submit_aportes'
        ]);
        self::grant_caps_to_role('socio_general',[
            'read_banco','read_private_bancos','read_aporte','submit_aportes','create_bancos','edit_banco','edit_bancos','publish_bancos'
        ]);

        if ($admin=get_role('administrator')) {
            foreach ($admin_like_caps as $cap) $admin->add_cap($cap);
        }
    }
    private static function grant_caps_to_role($role_key, array $caps) {
        if ($role=get_role($role_key)) foreach($caps as $cap) $role->add_cap($cap);
    }

    public static function add_banco_metaboxes() {
        add_meta_box('bk_banco_data',__('Datos del B@nko','bankitos'),[__CLASS__,'render_banco_meta'],self::SLUG_BANCO,'normal','high');
    }
    public static function render_banco_meta($post) {
        wp_nonce_field('bk_save_banco','bk_banco_nonce');
        $objetivo=get_post_meta($post->ID,'_bk_objetivo',true);
        $cuota=get_post_meta($post->ID,'_bk_cuota_monto',true);
        $period=get_post_meta($post->ID,'_bk_periodicidad',true);
        $tasa=get_post_meta($post->ID,'_bk_tasa',true);
        $duracion=get_post_meta($post->ID,'_bk_duracion_meses',true);
        ?>
        <p><label><strong><?php _e('Objetivo','bankitos'); ?></strong></label><br>
        <textarea name="bk_objetivo" rows="4" style="width:100%;"><?php echo esc_textarea($objetivo); ?></textarea></p>

        <p><label><strong><?php _e('Cuota (monto)','bankitos'); ?></strong></label><br>
        <input type="number" name="bk_cuota_monto" min="0" step="1" value="<?php echo esc_attr($cuota); ?>" style="width:160px"></p>

        <p><label><strong><?php _e('Periodicidad','bankitos'); ?></strong></label><br>
        <select name="bk_periodicidad">
            <?php $opts=['semanal'=>__('Semanal','bankitos'),'quincenal'=>__('Quincenal','bankitos'),'mensual'=>__('Mensual','bankitos')];
            foreach($opts as $val=>$lab){ printf('<option value="%s"%s>%s</option>',esc_attr($val),selected($period,$val,false),esc_html($lab)); } ?>
        </select></p>

        <p><label><strong><?php _e('Tasa de interés (%)','bankitos'); ?></strong></label><br>
        <input type="number" name="bk_tasa" min="0.1" max="3.0" step="0.1" value="<?php echo esc_attr($tasa); ?>" style="width:120px"></p>

        <p><label><strong><?php _e('Duración (meses)','bankitos'); ?></strong></label><br>
        <select name="bk_duracion_meses">
            <?php foreach([2,4,6,8,12] as $m){ printf('<option value="%d"%s>%d</option>',$m,selected(intval($duracion),$m,false),$m);} ?>
        </select></p>
        <?php
    }
    public static function save_banco_meta($post_id,$post){
        if ($post->post_type!==self::SLUG_BANCO) return;
        if (!isset($_POST['bk_banco_nonce']) || !wp_verify_nonce($_POST['bk_banco_nonce'],'bk_save_banco')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post',$post_id)) return;
        $objetivo=isset($_POST['bk_objetivo'])?wp_kses_post($_POST['bk_objetivo']):'';
        $cuota=isset($_POST['bk_cuota_monto'])?floatval($_POST['bk_cuota_monto']):0;
        $period=isset($_POST['bk_periodicidad'])?sanitize_key($_POST['bk_periodicidad']):'';
        $tasa=isset($_POST['bk_tasa'])?floatval($_POST['bk_tasa']):0;
        $dur=isset($_POST['bk_duracion_meses'])?intval($_POST['bk_duracion_meses']):0;
        update_post_meta($post_id,'_bk_objetivo',$objetivo);
        update_post_meta($post_id,'_bk_cuota_monto',$cuota);
        update_post_meta($post_id,'_bk_periodicidad',$period);
        update_post_meta($post_id,'_bk_tasa',$tasa);
        update_post_meta($post_id,'_bk_duracion_meses',$dur);
    }


}
add_action('init', ['Bankitos_CPT', 'add_roles_and_caps'], 20);