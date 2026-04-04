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

### Alta prioridad

| # | Descripción | Archivo(s) | Severidad | Estado |
|---|-------------|------------|-----------|--------|
| 1 | Sin rate limiting en login, reset de contraseña e invitaciones | [class-bk-auth.php](bankitos/includes/handlers/class-bk-auth.php) | ALTA | ✅ RESUELTO |
| 2 | PII sin cifrar en DB (`document_id`, `phone`) | [class-bankitos-credit-requests.php](bankitos/includes/class-bankitos-credit-requests.php) | ALTA | ✅ RESUELTO |
| 3 | Sin lockout de cuenta tras intentos fallidos de login | [class-bk-auth.php](bankitos/includes/handlers/class-bk-auth.php) | ALTA | ✅ RESUELTO |

**Solución 1+3:** Nueva clase [class-bankitos-rate-limiter.php](bankitos/includes/class-bankitos-rate-limiter.php) — máx 5 intentos en 15 min por IP, lockout automático via transients.

**Solución 2:** Nueva clase [class-bankitos-crypto.php](bankitos/includes/class-bankitos-crypto.php) — AES-256-CBC con clave derivada de `AUTH_KEY`. Compatible con datos legados (sin cifrar) gracias al prefijo `bk1:`.

### Media prioridad

| # | Descripción | Archivo(s) | Severidad | Estado |
|---|-------------|------------|-----------|--------|
| 4 | Tokens de invitación expuestos en URL (historial del browser, logs del servidor) | [class-bk-invites.php](bankitos/includes/handlers/class-bk-invites.php) | MEDIA | ⚠️ PENDIENTE (by design — email links) |
| 5 | Sin headers de seguridad HTTP (X-Content-Type-Options, CSP, X-Frame-Options) | [class-bankitos-plugin.php](bankitos/includes/class-bankitos-plugin.php) | MEDIA | ✅ RESUELTO |
| 6 | Posible enumeración de emails en recuperación de contraseña | [class-bk-auth.php](bankitos/includes/handlers/class-bk-auth.php) | MEDIA | ✅ RESUELTO |
| 7 | Logs pueden contener datos sensibles en columna `data_json` sin filtrar | [class-bankitos-logs.php](bankitos/includes/class-bankitos-logs.php) | MEDIA | ✅ RESUELTO |

**Solución 5:** `send_security_headers()` en `Bankitos_Plugin` — X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, Referrer-Policy, Permissions-Policy.

**Solución 6:** Rate limiting en `do_recover()` retorna siempre `ok=recovery_sent` (no revela si el email existe). + `record_failure` por email e IP.

**Solución 7:** `$sensitive_fields` en `Bankitos_Logs::add_log()` — redacta automáticamente campos PII y credenciales antes de guardar en `data_json`.

### Baja prioridad

| # | Descripción | Archivo(s) | Severidad | Estado |
|---|-------------|------------|-----------|--------|
| 8 | Sin verificación de email al registrarse | [class-bk-auth.php](bankitos/includes/handlers/class-bk-auth.php) | BAJA | ⚠️ PENDIENTE |
| 9 | Sin 2FA/MFA | Auth general | BAJA | ⚠️ PENDIENTE |
| 10 | Supresión de errores con `@` en operaciones de archivos | [class-bankitos-secure-files.php](bankitos/includes/class-bankitos-secure-files.php) | BAJA | ✅ RESUELTO |
| 11 | IP del usuario enviada a Google para reCAPTCHA (documentar en política de privacidad) | [class-bankitos-recaptcha.php](bankitos/includes/class-bankitos-recaptcha.php):75 | BAJA | ⚠️ PENDIENTE (documentar) |
| 12 | `.htaccess` del dir de archivos protegidos usaba `Options +Indexes` + `Require all granted` — exponía todos los archivos públicamente | [class-bankitos-secure-files.php](bankitos/includes/class-bankitos-secure-files.php) | **ALTA** | ✅ RESUELTO |

**Solución 10+12:** `.htaccess` corregido a `Options -Indexes` + `Require all denied`. `move_file()` usa `rename()`/`copy()` sin `@`, con `error_log()` en fallo.

---

## Funcionalidades faltantes

| Feature | Descripción | Impacto |
|---------|-------------|---------|
| Rate limiting | Limitar intentos en endpoints de auth | Seguridad crítica |
| Lockout de cuenta | Bloquear temporalmente tras X intentos fallidos | Seguridad alta |
| Cifrado de PII | Cifrar `document_id`, `phone`, `age` en DB | Seguridad alta |
| Headers de seguridad | Agregar X-Content-Type-Options, X-Frame-Options, CSP | Seguridad media |
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

## Contexto de negocio

- El plugin gestiona **bancos comunitarios** (grupos de ahorro y crédito informales, comunes en Colombia y Latinoamérica)
- Cada **banco** es un Custom Post Type con sus propios miembros, aportes y créditos
- Los **aportes** son contribuciones periódicas de los socios al fondo común
- Los **créditos** requieren aprobación de 3 roles antes de desembolsarse
- El **tesorero** maneja los fondos; el **presidente** aprueba; el **veedor** audita
- Los datos financieros son sensibles — los cambios deben ser conservadores y auditables
