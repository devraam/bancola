<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap bankitos-admin-dashboard">
    <div class="bankitos-admin-dashboard__head">
        <div>
            <h1><?php esc_html_e('Dashboard Administrativo Global', 'bankitos'); ?></h1>
            <p class="description"><?php esc_html_e('Visión panorámica de todos los B@nkos, socios, ahorros y créditos. Rol sugerido: Gestor Global / Supervisor de Bancos.', 'bankitos'); ?></p>
        </div>
        <div class="bankitos-admin-dashboard__actions">
            <a class="button button-primary" href="<?php echo esc_url($export_url); ?>"><?php esc_html_e('Exportar CSV', 'bankitos'); ?></a>
        </div>
    </div>

    <div class="bankitos-kpi-grid">
        <div class="bankitos-kpi">
            <p class="bankitos-kpi__label"><?php esc_html_e('B@nkos creados', 'bankitos'); ?></p>
            <p class="bankitos-kpi__value"><?php echo esc_html(number_format_i18n($snapshot['totals']['bancos'])); ?></p>
        </div>
        <div class="bankitos-kpi">
            <p class="bankitos-kpi__label"><?php esc_html_e('Socios totales', 'bankitos'); ?></p>
            <p class="bankitos-kpi__value"><?php echo esc_html(number_format_i18n($snapshot['totals']['socios_total'])); ?></p>
            <p class="bankitos-kpi__sub"><?php printf(esc_html__('Promedio por B@nko: %s', 'bankitos'), esc_html(number_format_i18n($snapshot['totals']['socios_promedio'], 2))); ?></p>
        </div>
        <div class="bankitos-kpi">
            <p class="bankitos-kpi__label"><?php esc_html_e('Dinero movilizado', 'bankitos'); ?></p>
            <p class="bankitos-kpi__value"><?php echo esc_html(Bankitos_Shortcode_Base::format_currency((float) $snapshot['totals']['ahorros'])); ?></p>
            <p class="bankitos-kpi__sub"><?php printf(esc_html__('Patrimonio promedio: %s', 'bankitos'), esc_html(Bankitos_Shortcode_Base::format_currency((float) $snapshot['totals']['patrimonio']))); ?></p>
        </div>
        <div class="bankitos-kpi">
            <p class="bankitos-kpi__label"><?php esc_html_e('Cartera de crédito', 'bankitos'); ?></p>
            <p class="bankitos-kpi__value"><?php echo esc_html(Bankitos_Shortcode_Base::format_currency((float) $snapshot['totals']['creditos'])); ?></p>
            <p class="bankitos-kpi__sub"><?php printf(esc_html__('Solicitudes aprobadas: %s', 'bankitos'), esc_html(number_format_i18n($snapshot['totals']['creditos_count']))); ?></p>
        </div>
        <div class="bankitos-kpi">
            <p class="bankitos-kpi__label"><?php esc_html_e('Utilización de capital', 'bankitos'); ?></p>
            <p class="bankitos-kpi__value"><?php echo esc_html(number_format_i18n($snapshot['totals']['utilizacion'], 2)); ?>%</p>
            <p class="bankitos-kpi__sub"><?php printf(esc_html__('Ticket promedio crédito: %s', 'bankitos'), esc_html(Bankitos_Shortcode_Base::format_currency((float) $snapshot['totals']['ticket']))); ?></p>
        </div>
        <div class="bankitos-kpi">
            <p class="bankitos-kpi__label"><?php esc_html_e('Salud de pagos', 'bankitos'); ?></p>
            <p class="bankitos-kpi__value"><?php echo esc_html(number_format_i18n($snapshot['health']['efficiency'], 2)); ?>%</p>
            <p class="bankitos-kpi__sub"><?php printf(esc_html__('Morosidad: %s%%', 'bankitos'), esc_html(number_format_i18n($snapshot['health']['rejected_rate'], 2))); ?></p>
        </div>
    </div>

    <div class="bankitos-grid">
        <section class="bankitos-card">
            <header class="bankitos-card__header">
                <div>
                    <h2><?php esc_html_e('Análisis de créditos', 'bankitos'); ?></h2>
                    <p class="description"><?php esc_html_e('Distribución por tipo y estado de solicitudes.', 'bankitos'); ?></p>
                </div>
            </header>
            <div class="bankitos-card__body bankitos-flex">
                <div class="bankitos-chart">
                    <h4><?php esc_html_e('Créditos aprobados por tipo', 'bankitos'); ?></h4>
                    <?php if (empty($snapshot['credits']['types'])): ?>
                        <p><?php esc_html_e('No hay créditos aprobados.', 'bankitos'); ?></p>
                    <?php else: ?>
                        <ul class="bankitos-bar-list">
                            <?php
                            $max_volume = max(wp_list_pluck($snapshot['credits']['types'], 'volume')) ?: 1;
                            foreach ($snapshot['credits']['types'] as $row):
                                $percent = ($row['volume'] / $max_volume) * 100;
                                ?>
                                <li class="bankitos-bar-list__item">
                                    <span class="bankitos-bar-list__label"><?php echo esc_html(ucfirst($row['credit_type'])); ?></span>
                                    <span class="bankitos-bar-list__value"><?php echo esc_html(Bankitos_Shortcode_Base::format_currency((float) $row['volume'])); ?></span>
                                    <span class="bankitos-bar" style="width: <?php echo esc_attr(number_format_i18n($percent, 2)); ?>%"></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <div class="bankitos-chart">
                    <h4><?php esc_html_e('Estado de solicitudes', 'bankitos'); ?></h4>
                    <?php if (empty($snapshot['credits']['status'])): ?>
                        <p><?php esc_html_e('Sin solicitudes registradas.', 'bankitos'); ?></p>
                    <?php else: ?>
                        <ul class="bankitos-pills">
                            <?php foreach ($snapshot['credits']['status'] as $status => $row): ?>
                                <li class="bankitos-pill-chip">
                                    <span class="bankitos-pill-chip__label"><?php echo esc_html(ucfirst($status)); ?></span>
                                    <span class="bankitos-pill-chip__value"><?php echo esc_html(number_format_i18n($row->total)); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="bankitos-card">
            <header class="bankitos-card__header">
                <div>
                    <h2><?php esc_html_e('Salud de cartera', 'bankitos'); ?></h2>
                    <p class="description"><?php esc_html_e('Pagos rechazados, eficiencia de recaudo y flujo esperado vs. real.', 'bankitos'); ?></p>
                </div>
            </header>
            <div class="bankitos-card__body bankitos-grid--two">
                <div>
                    <p class="bankitos-metric__label"><?php esc_html_e('Índice de morosidad', 'bankitos'); ?></p>
                    <p class="bankitos-metric__value"><?php echo esc_html(number_format_i18n($snapshot['health']['rejected_rate'], 2)); ?>%</p>
                    <p class="description"><?php esc_html_e('Porcentaje de pagos marcados como rechazados.', 'bankitos'); ?></p>
                </div>
                <div>
                    <p class="bankitos-metric__label"><?php esc_html_e('Eficiencia de recaudo', 'bankitos'); ?></p>
                    <p class="bankitos-metric__value"><?php echo esc_html(number_format_i18n($snapshot['health']['efficiency'], 2)); ?>%</p>
                    <p class="description"><?php printf(esc_html__('Esperado (a la fecha): %s', 'bankitos'), esc_html(Bankitos_Shortcode_Base::format_currency((float) $snapshot['health']['expected']))); ?></p>
                    <p class="description"><?php printf(esc_html__('Pagado: %s', 'bankitos'), esc_html(Bankitos_Shortcode_Base::format_currency((float) $snapshot['health']['actual']))); ?></p>
                </div>
            </div>
        </section>

        <section class="bankitos-card">
            <header class="bankitos-card__header">
                <div>
                    <h2><?php esc_html_e('Crecimiento de socios', 'bankitos'); ?></h2>
                    <p class="description"><?php esc_html_e('Altas en banco_members agrupadas por mes.', 'bankitos'); ?></p>
                </div>
            </header>
            <div class="bankitos-card__body">
                <?php if (empty($snapshot['growth'])): ?>
                    <p><?php esc_html_e('Aún no hay datos de crecimiento.', 'bankitos'); ?></p>
                <?php else: ?>
                    <ul class="bankitos-timeline">
                        <?php foreach ($snapshot['growth'] as $row): ?>
                            <li>
                                <span class="bankitos-timeline__label"><?php echo esc_html($row['ym']); ?></span>
                                <span class="bankitos-timeline__value"><?php echo esc_html(number_format_i18n($row['total'])); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section class="bankitos-card">
            <header class="bankitos-card__header">
                <div>
                    <h2><?php esc_html_e('Bancos fantasma', 'bankitos'); ?></h2>
                    <p class="description"><?php esc_html_e('Bancos con poca tracción luego de 30 días (menos de 2 miembros o sin aportes).', 'bankitos'); ?></p>
                </div>
            </header>
            <div class="bankitos-card__body">
                <?php if (empty($snapshot['ghost_banks'])): ?>
                    <p><?php esc_html_e('Sin bancos fantasma detectados.', 'bankitos'); ?></p>
                <?php else: ?>
                    <ul class="bankitos-ghost">
                        <?php foreach ($snapshot['ghost_banks'] as $row): ?>
                            <li>
                                <strong><?php echo esc_html($row['title']); ?></strong>
                                <span><?php printf(esc_html__('Miembros: %s | Ahorros: %s', 'bankitos'), esc_html(number_format_i18n($row['members'])), esc_html($row['capital'])); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <section class="bankitos-card">
        <header class="bankitos-card__header">
            <div>
                <h2><?php esc_html_e('Directorio de B@nkos', 'bankitos'); ?></h2>
                <p class="description"><?php esc_html_e('Búsqueda, activación/desactivación y eliminación con doble validación.', 'bankitos'); ?></p>
            </div>
            <form method="get" class="bankitos-search">
                <input type="hidden" name="page" value="<?php echo esc_attr(Bankitos_Admin_Reports::PAGE_SLUG); ?>" />
                <label class="screen-reader-text" for="bankitos-search"><?php esc_html_e('Buscar B@nkos', 'bankitos'); ?></label>
                <input id="bankitos-search" type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Buscar por nombre…', 'bankitos'); ?>" />
                <button class="button" type="submit"><?php esc_html_e('Buscar', 'bankitos'); ?></button>
            </form>
        </header>
        <div class="bankitos-card__body">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('B@nko', 'bankitos'); ?></th>
                        <th><?php esc_html_e('Fecha de creación', 'bankitos'); ?></th>
                        <th><?php esc_html_e('Miembros', 'bankitos'); ?></th>
                        <th><?php esc_html_e('Capital total', 'bankitos'); ?></th>
                        <th><?php esc_html_e('Estado', 'bankitos'); ?></th>
                        <th class="column-actions">&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($directory['rows'])): ?>
                        <tr><td colspan="6"><?php esc_html_e('No se encontraron bancos.', 'bankitos'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($directory['rows'] as $row): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($row['title']); ?></strong>
                                    <?php if (!empty($row['edit_link'])): ?>
                                        <div><a href="<?php echo esc_url($row['edit_link']); ?>"><?php esc_html_e('Ver en WordPress', 'bankitos'); ?></a></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($row['date']); ?></td>
                                <td><?php echo esc_html(number_format_i18n($row['members'])); ?></td>
                                <td><?php echo esc_html($row['capital']); ?></td>
                                <td>
                                    <span class="bankitos-status bankitos-status--<?php echo $row['status'] ? 'active' : 'inactive'; ?>"><?php echo esc_html($row['status_label']); ?></span>
                                </td>
                                <td class="column-actions">
                                    <div class="bankitos-actions">
                                        <?php if (current_user_can('manage_options')): ?>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="bankitos-inline-form">
                                                <?php wp_nonce_field(Bankitos_Admin_Reports::TOGGLE_ACTION . '_' . $row['id']); ?>
                                                <input type="hidden" name="action" value="<?php echo esc_attr(Bankitos_Admin_Reports::TOGGLE_ACTION); ?>" />
                                                <input type="hidden" name="banco_id" value="<?php echo esc_attr($row['id']); ?>" />
                                                <button type="submit" class="button button-secondary">
                                                    <?php echo $row['status'] ? esc_html__('Desactivar', 'bankitos') : esc_html__('Activar', 'bankitos'); ?>
                                                </button>
                                            </form>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="bankitos-inline-form bankitos-delete-form" data-confirm-first="<?php esc_attr_e('¿Seguro que quieres eliminar este B@nko y todos sus datos? Esta acción no se puede deshacer.', 'bankitos'); ?>" data-confirm-second="<?php esc_attr_e('Para continuar escribe ELIMINAR en el campo de confirmación.', 'bankitos'); ?>">
                                                <?php wp_nonce_field(Bankitos_Admin_Reports::DELETE_ACTION . '_' . $row['id']); ?>
                                                <input type="hidden" name="action" value="<?php echo esc_attr(Bankitos_Admin_Reports::DELETE_ACTION); ?>" />
                                                <input type="hidden" name="banco_id" value="<?php echo esc_attr($row['id']); ?>" />
                                                <label class="screen-reader-text" for="confirm-<?php echo esc_attr($row['id']); ?>"><?php esc_html_e('Confirmar eliminación', 'bankitos'); ?></label>
                                                <input type="text" id="confirm-<?php echo esc_attr($row['id']); ?>" name="confirm_phrase" placeholder="ELIMINAR" />
                                                <button type="submit" class="button button-link-delete" onclick="return false;">
                                                    <?php esc_html_e('Eliminar', 'bankitos'); ?>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <em><?php esc_html_e('Solo lectura', 'bankitos'); ?></em>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if (!empty($directory['total_pages']) && $directory['total_pages'] > 1): ?>
                <div class="tablenav bottom">
                    <?php
                    $big = 999999;
                    echo paginate_links([
                        'base'      => str_replace($big, '%#%', esc_url(add_query_arg('paged', $big))),
                        'format'    => '',
                        'current'   => $page,
                        'total'     => $directory['total_pages'],
                        'add_args'  => ['page' => Bankitos_Admin_Reports::PAGE_SLUG, 's' => $search],
                    ]);
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>