<?php
/**
 * Vista de admin (solo lectura): créditos de un banco.
 *
 * Variables disponibles (inyectadas desde Bankitos_Admin_Reports::render_banco_credits_page):
 * @var int    $banco_id
 * @var string $banco_name
 * @var array  $totals          ahorros, creditos, creditos_count, disponible
 * @var array  $requests        solicitudes ya preparadas (PII descifrada -> enmascarar aquí)
 * @var array  $types           credit_type => etiqueta
 * @var array  $committee       rol => etiqueta (presidente/tesorero/veedor)
 * @var array  $committee_cols  rol => columna (approved_president/...)
 * @var array  $status_labels   estado => etiqueta
 * @var array  $payment_labels  estado_pago => etiqueta
 * @var array  $payments_by_request  request_id => [pagos]
 * @var array  $banco_options   id => título (para el selector de banco)
 */
if (!defined('ABSPATH')) exit;

$status_colors = Bankitos_Admin_Reports::credit_status_colors();

/**
 * Pinta una pill con color inline (la vista no carga el CSS del dashboard).
 */
$render_pill = static function (string $status, array $labels, array $colors): string {
    $label = $labels[$status] ?? $status;
    $color = $colors[$status] ?? ['#e2e8f0', '#475569'];
    return '<span style="display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:600;background:'
        . esc_attr($color[0]) . ';color:' . esc_attr($color[1]) . ';white-space:nowrap;">'
        . esc_html($label) . '</span>';
};

$columns_count = 10;
?>
<div class="wrap">
    <h1 style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
        <?php echo esc_html($banco_name); ?>
        <span style="color:#94a3b8;font-size:.7em;">#<?php echo esc_html((string) $banco_id); ?></span>
    </h1>
    <?php echo Bankitos_Admin_Reports::render_banco_selector_form($banco_options, (int) $banco_id); ?>

    <p style="color:#475569;max-width:760px;">
        <?php esc_html_e('Vista de solo lectura del estado de los créditos de este B@nko. Los datos personales (documento y teléfono) se muestran enmascarados.', 'bankitos'); ?>
    </p>

    <div style="display:flex;flex-wrap:wrap;gap:1rem;margin:1rem 0 1.5rem;">
        <?php
        $kpis = [
            __('Ahorros del B@nko', 'bankitos')   => Bankitos_Shortcode_Base::format_currency((float) ($totals['ahorros'] ?? 0)),
            __('Cartera de crédito', 'bankitos')  => Bankitos_Shortcode_Base::format_currency((float) ($totals['creditos'] ?? 0)),
            __('Créditos aprobados', 'bankitos')  => number_format_i18n((int) ($totals['creditos_count'] ?? 0)),
            __('Capital disponible', 'bankitos')  => Bankitos_Shortcode_Base::format_currency((float) ($totals['disponible'] ?? 0)),
        ];
        foreach ($kpis as $kpi_label => $kpi_value): ?>
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:.85rem 1.1rem;min-width:170px;">
                <div style="color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:.03em;"><?php echo esc_html($kpi_label); ?></div>
                <div style="font-size:20px;font-weight:700;color:#0f172a;margin-top:.2rem;"><?php echo esc_html($kpi_value); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <h2><?php esc_html_e('Solicitudes de crédito', 'bankitos'); ?></h2>

    <table class="wp-list-table widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Solicitante', 'bankitos'); ?></th>
                <th><?php esc_html_e('Documento', 'bankitos'); ?></th>
                <th><?php esc_html_e('Teléfono', 'bankitos'); ?></th>
                <th><?php esc_html_e('Monto', 'bankitos'); ?></th>
                <th><?php esc_html_e('Plazo', 'bankitos'); ?></th>
                <th><?php esc_html_e('Tipo', 'bankitos'); ?></th>
                <th><?php esc_html_e('Fecha', 'bankitos'); ?></th>
                <th><?php esc_html_e('Estado', 'bankitos'); ?></th>
                <th><?php esc_html_e('Firmas del comité', 'bankitos'); ?></th>
                <th><?php esc_html_e('Notas del comité', 'bankitos'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($requests)): ?>
                <tr>
                    <td colspan="<?php echo (int) $columns_count; ?>" style="text-align:center;padding:2rem;color:#64748b;">
                        <?php esc_html_e('Este B@nko aún no tiene solicitudes de crédito.', 'bankitos'); ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($requests as $request): ?>
                    <?php
                    $status      = (string) ($request['status'] ?? 'pending');
                    $type_label  = $types[$request['credit_type']] ?? ucfirst((string) $request['credit_type']);
                    $term        = (int) $request['term_months'];
                    $req_id      = (int) $request['id'];
                    $payments    = $payments_by_request[$req_id] ?? [];
                    $notes       = trim((string) ($request['committee_notes'] ?? ''));
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($request['display_name']); ?></strong></td>
                        <td><?php echo esc_html(Bankitos_Admin_Reports::mask_pii((string) ($request['document_id'] ?? ''))); ?></td>
                        <td><?php echo esc_html(Bankitos_Admin_Reports::mask_pii((string) ($request['phone'] ?? ''))); ?></td>
                        <td><?php echo esc_html(Bankitos_Shortcode_Base::format_currency((float) $request['amount'])); ?></td>
                        <td><?php echo esc_html(sprintf(_n('%s mes', '%s meses', $term, 'bankitos'), number_format_i18n($term))); ?></td>
                        <td><?php echo esc_html($type_label); ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime((string) $request['request_date']))); ?></td>
                        <td><?php echo $render_pill($status, $status_labels, $status_colors); ?></td>
                        <td>
                            <div style="display:flex;flex-direction:column;gap:3px;">
                                <?php foreach ($committee as $role_key => $role_label):
                                    $col         = $committee_cols[$role_key] ?? '';
                                    $sign_status = (string) ($request[$col] ?? 'pending');
                                    ?>
                                    <span style="display:flex;align-items:center;gap:6px;font-size:12px;">
                                        <span style="color:#64748b;min-width:74px;"><?php echo esc_html($role_label); ?></span>
                                        <?php echo $render_pill($sign_status, $status_labels, $status_colors); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td><?php echo $notes !== '' ? nl2br(esc_html($notes)) : '<span style="color:#94a3b8;">—</span>'; ?></td>
                    </tr>
                    <tr>
                        <td colspan="<?php echo (int) $columns_count; ?>" style="background:#f8fafc;">
                            <details>
                                <summary style="cursor:pointer;font-weight:600;color:#334155;">
                                    <?php
                                    printf(
                                        esc_html(_n('Ver %d pago registrado', 'Ver %d pagos registrados', count($payments), 'bankitos')),
                                        count($payments)
                                    );
                                    ?>
                                </summary>
                                <?php if (empty($payments)): ?>
                                    <p style="color:#64748b;margin:.6rem 0 .2rem;"><?php esc_html_e('Sin pagos registrados para esta solicitud.', 'bankitos'); ?></p>
                                <?php else: ?>
                                    <table class="widefat striped" style="margin:.6rem 0;max-width:760px;">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e('Fecha', 'bankitos'); ?></th>
                                                <th><?php esc_html_e('Monto', 'bankitos'); ?></th>
                                                <th><?php esc_html_e('Mora', 'bankitos'); ?></th>
                                                <th><?php esc_html_e('Estado', 'bankitos'); ?></th>
                                                <th><?php esc_html_e('Comprobante', 'bankitos'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($payments as $payment):
                                                $p_status = (string) ($payment['status'] ?? 'pending');
                                                $has_file = (int) ($payment['attachment_id'] ?? 0) > 0;
                                                ?>
                                                <tr>
                                                    <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime((string) $payment['created_at']))); ?></td>
                                                    <td><?php echo esc_html(Bankitos_Shortcode_Base::format_currency((float) $payment['amount'])); ?></td>
                                                    <td><?php echo esc_html(Bankitos_Shortcode_Base::format_currency((float) ($payment['mora_amount'] ?? 0))); ?></td>
                                                    <td><?php echo $render_pill($p_status, $payment_labels, $status_colors); ?></td>
                                                    <td><?php echo $has_file ? esc_html__('Sí', 'bankitos') : '<span style="color:#94a3b8;">' . esc_html__('No', 'bankitos') . '</span>'; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
// Nota: los comprobantes no son descargables aquí en V1 — los handlers de
// descarga existentes exigen pertenencia al mismo banco y un admin global no
// la tiene. Se indica solo si existe adjunto (Sí/No).
