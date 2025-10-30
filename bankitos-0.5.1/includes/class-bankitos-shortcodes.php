<?php
if (!defined('ABSPATH')) exit;

class Bankitos_Shortcodes {

  public static function init(): void {
    $base = BANKITOS_PATH . 'includes/shortcodes/';
    $files = [
        'class-bankitos-shortcode-base.php',
        'class-bankitos-shortcode-panel-base.php',
        'class-bankitos-shortcode-login.php',
        'class-bankitos-shortcode-register.php',
        'class-bankitos-shortcode-panel-info.php',
        'class-bankitos-shortcode-panel-members.php',
        'class-bankitos-shortcode-panel-members-invite.php',
        'class-bankitos-shortcode-panel-quick-actions.php',
        'class-bankitos-shortcode-panel.php',
        'class-bankitos-shortcode-crear-banco.php',
        'class-bankitos-shortcode-aporte-form.php',
        'class-bankitos-shortcode-tesorero.php',
        'class-bankitos-shortcode-veedor.php',
        'class-bankitos-shortcode-invite-portal.php',
    ];

    foreach ($files as $file) {
        $path = $base . $file;
        if (file_exists($path)) {
            require_once $path;
        }
    }

    $classes = [
        'Bankitos_Shortcode_Login',
        'Bankitos_Shortcode_Register',
        'Bankitos_Shortcode_Panel_Info',
        'Bankitos_Shortcode_Panel_Members',
        'Bankitos_Shortcode_Panel_Members_Invite',
        'Bankitos_Shortcode_Panel_Quick_Actions',
        'Bankitos_Shortcode_Panel',
        'Bankitos_Shortcode_Crear_Banco',
        'Bankitos_Shortcode_Aporte_Form',
        'Bankitos_Shortcode_Tesorero_List',
        'Bankitos_Shortcode_Veedor_List',
        'Bankitos_Shortcode_Invite_Portal',
    ];

    foreach ($classes as $class) {
        if (class_exists($class) && method_exists($class, 'register')) {
            $class::register();
        }
    }

      
  }
}
add_action('init', ['Bankitos_Shortcodes', 'init']);