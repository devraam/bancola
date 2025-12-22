<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap bankitos-wrap">
    <h1><?php esc_html_e('Dominios autorizados', 'bankitos'); ?></h1>
    <p class="description">
        <?php esc_html_e('Solo se permitirá registrar usuarios o enviar invitaciones a correos dentro de estos dominios. Si la lista está vacía, no se aplicará restricción.', 'bankitos'); ?>
    </p>

    <?php if (!empty($notice_message)): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($notice_message); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="notice notice-error">
            <p><?php echo esc_html($error_message); ?></p>
        </div>
    <?php endif; ?>

    <div class="bankitos-domains-grid" style="display:grid;grid-template-columns: 1fr 1.2fr;gap:24px;align-items:start;margin-top:1rem;">
        <div class="card" style="background:#fff;border:1px solid #ccd0d4;padding:16px;border-radius:4px;">
            <h2 style="margin-top:0;"><?php echo $edit_domain ? esc_html__('Editar dominio', 'bankitos') : esc_html__('Agregar dominio', 'bankitos'); ?></h2>
            <form method="post" action="<?php echo esc_url($form_url); ?>">
                <?php wp_nonce_field($nonce_action); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr(Bankitos_Domains::ACTION_SAVE); ?>">
                <input type="hidden" name="domain_action" value="<?php echo $edit_domain ? 'edit' : 'add'; ?>">
                <?php if ($edit_domain): ?>
                    <input type="hidden" name="original_domain" value="<?php echo esc_attr($edit_domain); ?>">
                <?php endif; ?>
                <p>
                    <label for="bankitos-domain"><strong><?php esc_html_e('Dominio', 'bankitos'); ?></strong></label><br>
                    <input type="text" id="bankitos-domain" name="bankitos_domain" class="regular-text" placeholder="midominio.com" value="<?php echo esc_attr($edit_domain); ?>">
                </p>
                <p class="description"><?php esc_html_e('Usa solo el dominio, sin @ ni nombres de usuario. Ejemplo: ejemplo.com', 'bankitos'); ?></p>
                <?php submit_button($edit_domain ? __('Actualizar dominio', 'bankitos') : __('Agregar dominio', 'bankitos')); ?>
                <?php if ($edit_domain): ?>
                    <a class="button-link" href="<?php echo esc_url($page_url); ?>"><?php esc_html_e('Cancelar edición', 'bankitos'); ?></a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card" style="background:#fff;border:1px solid #ccd0d4;padding:16px;border-radius:4px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Dominios permitidos', 'bankitos'); ?></h2>
            <?php if (empty($domains)): ?>
                <p><?php esc_html_e('No hay dominios autorizados asociados para registrar usuarios. Agrega uno con el formulario para habilitar invitaciones.', 'bankitos'); ?></p>
            <?php else: ?>
                <table class="widefat striped" style="max-width:100%;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Dominio', 'bankitos'); ?></th>
                            <th style="width:180px;"><?php esc_html_e('Acciones', 'bankitos'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($domains as $domain): ?>
                            <tr>
                                <td><?php echo esc_html($domain); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(add_query_arg(['page' => Bankitos_Domains::PAGE_SLUG, 'domain' => $domain], admin_url('admin.php'))); ?>">
                                        <?php esc_html_e('Editar', 'bankitos'); ?>
                                    </a>
                                    <form method="post" action="<?php echo esc_url($form_url); ?>" style="display:inline-block;margin-left:12px;">
                                        <?php
                                        $delete_action = Bankitos_Domains::ACTION_SAVE . '_delete_' . $domain;
                                        wp_nonce_field($delete_action);
                                        ?>
                                        <input type="hidden" name="action" value="<?php echo esc_attr(Bankitos_Domains::ACTION_SAVE); ?>">
                                        <input type="hidden" name="domain_action" value="delete">
                                        <input type="hidden" name="bankitos_domain" value="<?php echo esc_attr($domain); ?>">
                                        <button type="submit" class="button-link delete-domain" onclick="return confirm('<?php echo esc_js(__('¿Eliminar este dominio?', 'bankitos')); ?>');">
                                            <?php esc_html_e('Eliminar', 'bankitos'); ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>