# BanKitos — Guía para desarrollo asistido por IA

## Descripción del proyecto

**BanKitos** es un plugin de WordPress para gestionar bancos comunitarios de ahorro y crédito. Permite a grupos de personas administrar aportes (ahorros), solicitudes de crédito y pagos de manera colaborativa con roles diferenciados y flujos de aprobación.

- **Versión actual:** 0.6.1
- **Slug del plugin:** `bankitos`
- **Archivo principal:** [bankitos.php](bankitos.php)
- **Idioma:** Español (Colombia)

---

## Estructura del proyecto

```
bankitos/
├── bankitos.php                    # Bootstrap — define constantes y carga clases
├── assets/
│   ├── css/
│   │   ├── bankitos.css
│   │   └── bankitos-admin-dashboard.css
│   └── js/
│       ├── form-submit.js          # Deshabilita botón de submit mientras procesa
│       ├── recaptcha.js            # Inyecta token reCAPTCHA en formularios
│       ├── panel.js                # UI del panel de usuario
│       ├── create-banco.js         # Validación del formulario de creación
│       └── min-amount-validation.js
├── includes/
│   ├── class-bankitos-plugin.php   # Punto de entrada, registra hooks y clases
│   ├── class-bankitos-db.php       # Schema de base de datos, creación de tablas
│   ├── class-bankitos-cpt.php      # Custom Post Types (banco)
│   ├── class-bankitos-roles.php    # Define roles y capacidades personalizadas
│   ├── class-bankitos-access.php   # Lógica centralizada de autorización
│   ├── class-bankitos-recaptcha.php
│   ├── class-bankitos-secure-files.php  # Archivos en directorio protegido
│   ├── class-bankitos-logs.php     # Sistema de auditoría
│   ├── class-bankitos-settings.php
│   ├── class-bankitos-domains.php  # Whitelist de dominios de email
│   ├── class-bankitos-mailer.php
│   ├── class-bankitos-credit-requests.php
│   ├── class-bankitos-rate-limiter.php  # Rate limiting + lockout (transients)
│   ├── class-bankitos-crypto.php        # Cifrado AES-256-CBC para PII
│   ├── class-bankitos-admin-reports.php
│   ├── class-bankitos-handlers.php # Registro de miembros
│   ├── handlers/
│   │   ├── class-bk-auth.php       # Login, registro, recuperación de contraseña
│   │   ├── class-bk-banco.php      # Creación de bancos
│   │   ├── class-bk-aportes.php    # Aportes: submit, aprobación, rechazo, export
│   │   ├── class-bk-invites.php    # Invitaciones por email
│   │   ├── class-bk-creditos.php   # Solicitudes de crédito
│   │   ├── class-bk-credit-payments.php
│   │   └── class-bk-credit-disbursements.php
│   ├── shortcodes/
│   │   └── class-bankitos-shortcode-*.php  # 22 shortcodes para frontend
│   └── views/
│       ├── admin-dashboard.php
│       └── admin-domains.php
```

---

## Arquitectura y flujo de datos

### Roles y capacidades

| Rol | Descripción | Capacidades clave |
|-----|-------------|-------------------|
| `socio_general` | Miembro básico | `submit_aportes` |
| `secretario` | Gestión administrativa | `manage_bank_invites` |
| `tesorero` | Aprueba pagos | `approve_aportes`, `can_review` |
| `presidente` | Aprueba créditos | `approve_aportes`, `can_review` |
| `veedor` | Auditor/observer | `can_review` |
| `gestor_global` | Superusuario del plugin | Acceso a todos los bancos |

### Tablas de base de datos

| Tabla | Propósito |
|-------|-----------|
| `wp_banco_members` | Membresía usuario↔banco |
| `wp_banco_savings` | Aportes/ahorros registrados |
| `wp_banco_loans` | Préstamos (legacy) |
| `wp_banco_loan_payments` | Pagos de préstamos (legacy) |
| `wp_banco_invites` | Invitaciones con tokens únicos |
| `wp_banco_credit_requests` | Solicitudes de crédito |
| `wp_banco_credit_payments` | Pagos de créditos |
| `wp_banco_transaction_logs` | Registro de auditoría (30 días) |

### Flujo de crédito (3 niveles de aprobación)

```
Solicitud (socio) → Revisión presidente → Revisión tesorero → Revisión veedor → Desembolso
```

Estados: `pendiente` → `aprobado`/`rechazado` → `desembolsado`

### Endpoints (admin_post actions)

Todos los endpoints siguen el patrón WordPress `admin_post_{action}`:

- Auth: `bankitos_do_login`, `bankitos_do_register`, `bankitos_do_recover`, `bankitos_do_reset_password`
- Aportes: `bankitos_aporte_submit`, `bankitos_aporte_approve`, `bankitos_aporte_reject`, `bankitos_aporte_export_excel`
- Créditos: `bankitos_credito_solicitar`, `bankitos_credito_resolver`, `bankitos_credit_payment_submit`, `bankitos_credit_payment_approve`, `bankitos_credit_disburse`
- Invitaciones: `bankitos_send_invites`, `bankitos_accept_invite`, `bankitos_reject_invite`, `bankitos_assign_role`
- Banco: `bankitos_front_create`

---

## Convenciones de código

### PHP
- **Prefijo de clases:** `Bankitos_` o `BK_`
- **Prefijo de funciones globales:** `bankitos_`
- **Nonces:** siempre `check_admin_referer('bankitos_{action}', 'bankitos_nonce')`
- **Sanitización de inputs:**
  - Texto: `sanitize_text_field()`
  - Email: `sanitize_email()`
  - Enteros: `absint()`
  - Decimales: `floatval()`
  - HTML: `wp_kses_post()`
- **Escaping de outputs:**
  - Texto: `esc_html()`
  - Atributos HTML: `esc_attr()`
  - URLs: `esc_url()`
- **Queries SQL:** siempre con `$wpdb->prepare()`, nunca interpolación directa
- **Redirecciones:** usar `BK_Aportes_Handler::redirect_with()` o el helper equivalente en cada handler

### JavaScript
- Formularios usan `form-submit.js` para deshabilitar el botón durante el submit
- reCAPTCHA se inyecta desde `recaptcha.js` via `wp_localize_script`

---

## Seguridad — lo que está bien implementado

- **SQL injection:** `$wpdb->prepare()` en todas las queries — NO hacer queries sin prepare
- **CSRF:** nonces en todos los formularios — NO omitir check_admin_referer
- **Autenticación:** `is_user_logged_in()` en todos los endpoints protegidos
- **Autorización:** `current_user_can()` + aislamiento por banco
- **Archivos:** subidos a `uploads/bankitos-private/` con `.htaccess` que bloquea PHP
- **reCAPTCHA:** validación server-side en login, registro y recuperación
- **Contraseñas:** solo via WordPress core (`wp_signon`, `wp_create_user`, `reset_password`)

---

## Vulnerabilidades conocidas (pendientes de resolver)

| # | Descripción | Archivo(s) | Severidad | Notas |
|---|-------------|------------|-----------|-------|
| 1 | Tokens de invitación expuestos en URL (historial del browser, logs del servidor) | [class-bk-invites.php](bankitos/includes/handlers/class-bk-invites.php) | MEDIA | By design — los links viajan por email |
| 2 | Sin verificación de email al registrarse | [class-bk-auth.php](bankitos/includes/handlers/class-bk-auth.php) | BAJA | Pendiente de implementar |
| 3 | Sin 2FA/MFA | Auth general | BAJA | Pendiente de implementar |
| 4 | IP del usuario enviada a Google para reCAPTCHA | [class-bankitos-recaptcha.php](bankitos/includes/class-bankitos-recaptcha.php):75 | BAJA | Documentar en política de privacidad |

---

## Funcionalidades faltantes

| Feature | Descripción | Impacto |
|---------|-------------|---------|
| Verificación de email | Confirmar email antes de activar cuenta | Seguridad/UX |
| Tests unitarios | No hay ningún test en el proyecto | Calidad de código |
| Tests de integración | No hay tests E2E ni de integración | Calidad de código |
| Notificaciones en tiempo real | Solo emails de invitación; sin notificaciones de aprobación/rechazo | UX |
| Rollback de transacciones | Las eliminaciones financieras son permanentes | Integridad de datos |
| Export GDPR por usuario | Solo admin puede exportar; usuario no puede descargar sus datos | Cumplimiento legal |
| Reportes programados | Solo export manual; sin reportes automáticos periódicos | Funcionalidad |
| 2FA/MFA | Autenticación de un solo factor | Seguridad |
| Timeout de sesión configurable | Usa default de WordPress (2 semanas) | Seguridad |
| Documentación de API interna | No hay documentación de clases ni métodos | Mantenimiento |
| Multi-idioma real | Usa `__()` pero solo hay strings en español | Internacionalización |

---

## Integraciones de terceros

| Servicio | Uso | Configuración |
|----------|-----|---------------|
| Google reCAPTCHA v3 | Protección contra bots en forms públicos | Keys en opciones de WP admin |
| Mailjet (opcional) | Envío de emails de invitación | Credentials en opciones de WP admin |
| WordPress Core | Framework base | Requerido |

---

## Instrucciones para Claude (IA)

### Antes de modificar código
- Leer el archivo completo antes de editarlo
- Verificar si existe una función helper reutilizable antes de crear una nueva
- Revisar `class-bankitos-access.php` para entender la lógica de autorización antes de tocar permisos
- Los nombres de tablas se construyen con `$wpdb->prefix . 'banco_{nombre}'`; preferir métodos `::table_name()` cuando existan

### Reglas de seguridad obligatorias (no negociables)
1. **Siempre** usar `$wpdb->prepare()` para queries con datos variables
2. **Siempre** verificar nonce con `check_admin_referer()` en handlers POST
3. **Siempre** verificar `is_user_logged_in()` en endpoints protegidos
4. **Siempre** sanitizar inputs antes de usar; escapar outputs antes de renderizar
5. **Nunca** guardar contraseñas o tokens en texto plano (usar WordPress core)
6. **Nunca** exponer mensajes de error de PHP al usuario final

### Patrones a seguir

```php
// Handler típico (en class-bk-*.php):
add_action('admin_post_bankitos_mi_accion', [$this, 'handle_mi_accion']);

public function handle_mi_accion() {
    check_admin_referer('bankitos_mi_accion', 'bankitos_nonce');

    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url());
        exit;
    }

    $dato = sanitize_text_field($_POST['dato'] ?? '');
    $id   = absint($_POST['id'] ?? 0);

    // ... lógica ...

    // redirect con mensaje:
    $url = add_query_arg(['msg' => 'ok'], wp_get_referer());
    wp_safe_redirect($url);
    exit;
}
```

```php
// Query SQL segura:
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}banco_credit_requests WHERE banco_id = %d AND user_id = %d",
    $banco_id,
    $user_id
));
```

```php
// Output seguro en shortcodes:
echo '<span class="nombre">' . esc_html($user->display_name) . '</span>';
echo '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
```

### Dónde agregar nuevas features
- **Nueva acción POST:** crear handler en `includes/handlers/class-bk-{nombre}.php`, registrar en `class-bankitos-plugin.php`
- **Nueva vista de usuario:** crear shortcode en `includes/shortcodes/class-bankitos-shortcode-{nombre}.php`
- **Nueva tabla DB:** agregar en `class-bankitos-db.php::create_tables()` con `dbDelta()`
- **Nuevo rol o capacidad:** modificar `class-bankitos-roles.php`
- **Nueva lógica de autorización:** extender `class-bankitos-access.php`

---

## Catálogo de shortcodes

Todas las páginas deben usar plantilla de ancho completo (sin sidebar). Si un rol no tiene permisos el shortcode se oculta automáticamente, por lo que varios shortcodes pueden coexistir en la misma página.

### Página `/panel` — panel principal del socio

Orden recomendado (de arriba abajo):

| # | Shortcode | Funcionalidad | Rol mínimo |
|---|-----------|---------------|------------|
| 1 | `[bankitos_panel]` | Saludo de bienvenida; si no pertenece a un banco ofrece crearlo | Cualquier socio autenticado |
| 2 | `[bankitos_invite_portal]` | Acepta o rechaza invitaciones pendientes (se oculta si no hay token activo) | Invitado con token |
| 3 | `[bankitos_panel_mis_finanzas]` | Historial de aportes con comprobantes y capacidad crediticia | socio_general |
| 4 | `[bankitos_rentabilidad]` | Desglose de rentabilidad: capital + intereses + multas + capacidad de crédito (4×) | Cualquier miembro del banco |

### Páginas adicionales recomendadas

| Página | Shortcode(s) | Funcionalidad | Rol mínimo |
|--------|-------------|---------------|------------|
| `/acceder` | `[bankitos_login]` | Inicio de sesión con reCAPTCHA | Público |
| `/registrarse` | `[bankitos_register]` | Alta de nuevos usuarios; lee `invite_token` de la URL | Público |
| `/crear-banko` | `[bankitos_crear_banco_form]` | Formulario guiado para crear un nuevo B@nko; incluye mora, penalización por renuncia | Socio sin banco |
| `/panel-info` | `[bankitos_panel_info]` | Tarjeta de detalles del banco: cuota, tasa, duración, capital total | Cualquier miembro |
| `/panel-acciones` | `[bankitos_panel_quick_actions]` | Botones de acceso rápido contextuales según rol | Cualquier miembro |
| `/miembros` | `[bankitos_panel_members_invite]` + `[bankitos_panel_members]` | Invitar socios y gestionar roles | presidente |
| `/aportar` | `[bankitos_aporte_form]` | Registrar aporte con comprobante; opción de desglosar multa | socio_general (`submit_aportes`) |
| `/tesorero` | `[bankitos_tesorero_aportes]` + `[bankitos_tesorero_creditos]` + `[bankitos_tesorero_desembolsos]` | Aprobar aportes, pagos de crédito y registrar desembolsos | tesorero (`approve_aportes`) |
| `/veedor` | `[bankitos_veedor_aportes]` + `[bankitos_veedor_creditos]` | Auditoría de solo lectura de aportes y pagos | veedor |
| `/creditos` | `[bankitos_credit_request]` + `[bankitos_credit_summary]` + `[bankitos_credit_review]` | Solicitar crédito, ver estado (con mora), revisar solicitudes (comité) | socio_general / comité |

### Referencia completa de shortcodes

| Shortcode | Clase | Funcionalidad | Rol / Capacidad |
|-----------|-------|---------------|-----------------|
| `[bankitos_login]` | `Bankitos_Shortcode_Login` | Formulario de login con reCAPTCHA y soporte de `invite_token` | Público |
| `[bankitos_register]` | `Bankitos_Shortcode_Register` | Alta de usuario; vincula al banco si llega con `invite_token` | Público |
| `[bankitos_invite_portal]` | `Bankitos_Shortcode_Invite_Portal` | Acepta/rechaza invitaciones; muestra detalles del banco que invita | Invitado con token |
| `[bankitos_mobile_menu]` | `Bankitos_Shortcode_Mobile_Menu` | Menú flotante móvil según rol; auto-inyectado en `wp_footer` | Socios autenticados |
| `[bankitos_panel]` | `Bankitos_Shortcode_Panel` | Bienvenida al panel; ofrece crear banco si no pertenece a uno | Cualquier socio |
| `[bankitos_panel_info]` | `Bankitos_Shortcode_Panel_Info` | Tarjeta de información del banco: cuota, tasa, capital, créditos | Cualquier miembro |
| `[bankitos_panel_quick_actions]` | `Bankitos_Shortcode_Panel_Quick_Actions` | Accesos directos contextuales (aportes, créditos, roles especiales) | Cualquier miembro |
| `[bankitos_panel_mis_finanzas]` | `Bankitos_Shortcode_Panel_Finanzas` | Historial de aportes con estado y comprobantes; capacidad crediticia | socio_general |
| `[bankitos_rentabilidad]` | `Bankitos_Shortcode_Rentabilidad` | Desglose de rentabilidad: capital ahorrado, intereses de créditos, multas distribuidas, total y capacidad de crédito (4×) | Cualquier miembro |
| `[bankitos_crear_banco_form]` | `Bankitos_Shortcode_Crear_Banco` | Crear banco con nombre, cuota, tasa, mora configurable y penalización por renuncia | Socio sin banco |
| `[bankitos_aporte_form]` | `Bankitos_Shortcode_Aporte_Form` | Registrar aporte + comprobante; checkbox para desglosar multa del monto | `submit_aportes` |
| `[bankitos_tesorero_aportes]` | `Bankitos_Shortcode_Tesorero_List` | Tabla de aportes pendientes con paginación, filtros y comprobantes | `approve_aportes` (tesorero) |
| `[bankitos_veedor_aportes]` | `Bankitos_Shortcode_Veedor_List` | Aportes aprobados en solo lectura para auditoría | veedor |
| `[bankitos_credit_request]` | `Bankitos_Shortcode_Credit_Request` | Formulario de solicitud de crédito con monto, plazo, destino y firma | socio_general |
| `[bankitos_credit_summary]` | `Bankitos_Shortcode_Credit_Summary` | Estado de créditos del socio; plan de pagos con mora calculada por cuota vencida | socio_general |
| `[bankitos_credit_review]` | `Bankitos_Shortcode_Credit_Review` | Revisión de solicitudes del comité (aprobación/rechazo por rol) | presidente / tesorero / veedor |
| `[bankitos_tesorero_creditos]` | `Bankitos_Shortcode_Tesorero_Creditos` | Aprobar o rechazar pagos de crédito con comprobante | tesorero (`approve_aportes`) |
| `[bankitos_tesorero_desembolsos]` | `Bankitos_Shortcode_Tesorero_Desembolsos` | Registrar desembolsos de créditos aprobados (fecha + comprobante) | tesorero (`approve_aportes`) |
| `[bankitos_veedor_creditos]` | `Bankitos_Shortcode_Veedor_Creditos` | Pagos de créditos en solo lectura para auditoría | veedor |
| `[bankitos_panel_members_invite]` | `Bankitos_Shortcode_Panel_Members_Invite` | Enviar invitaciones por email (varias en una sola acción) | presidente |
| `[bankitos_panel_members]` | `Bankitos_Shortcode_Panel_Members` | Tabla de miembros e invitaciones; asignación de roles | presidente |

---

## Contexto de negocio

- El plugin gestiona **bancos comunitarios** (grupos de ahorro y crédito informales, comunes en Colombia y Latinoamérica)
- Cada **banco** es un Custom Post Type con sus propios miembros, aportes y créditos
- Los **aportes** son contribuciones periódicas de los socios al fondo común
- Los **créditos** requieren aprobación de 3 roles antes de desembolsarse
- El **tesorero** maneja los fondos; el **presidente** aprueba; el **veedor** audita
- Los datos financieros son sensibles — los cambios deben ser conservadores y auditables
