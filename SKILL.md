---
name: crm-inmobiliario-wp
description: Guía para entender, desarrollar, probar, corregir y publicar el plugin de WordPress "CRM Inmobiliario Sencillo" (contactos, propiedades y seguimientos en tablas propias). Úsala antes de modificar cualquier archivo de este repositorio.
---

# SKILL: mantenimiento de CRM Inmobiliario Sencillo

## 1. Objetivo y alcance

Plugin de WordPress que ofrece el CRM inmobiliario **más sencillo posible** para agentes y pequeñas inmobiliarias: contactos/leads, propiedades y seguimientos comerciales, con panel resumen, búsqueda y filtros, dentro del escritorio de WordPress y en español.

Principios que no deben romperse:

- **Mínimo y funcional**: antes de añadir algo, comprueba que lo existente sigue funcionando. Prioriza la corrección sobre las funcionalidades.
- **Fuera de alcance** (no implementar sin petición explícita): SaaS, multiempresa, facturación, suscripciones, IA, automatizaciones externas, WhatsApp, portales inmobiliarios, apps móviles, integraciones de terceros.
- **Sin dependencias en producción**: ni librerías de Composer/npm ni plugins obligatorios. JS nativo, CSS propio.
- **Sin llamadas externas, telemetría ni datos ficticios no solicitados.**

## 2. Arquitectura

Arquitectura mínima en capas, sin frameworks:

```
crm-inmobiliario-wp.php   Cabecera, constantes (CRMI_VERSION, CRMI_DB_VERSION, CRMI_CAP…), require y hooks
uninstall.php             Borra datos SOLO si crmi_delete_data_on_uninstall = 'yes' (recorre multisitio)
includes/
  class-crmi-entities.php    FUENTE ÚNICA DE VERDAD: campos, tipos, opciones de los selectores,
                             columnas de búsqueda/filtro/orden, sanitize() y parse_price()
  class-crmi-repository.php  CRUD genérico por entidad: find/insert/update/delete/query/count/
                             options/labels/exists. Todo valor de usuario va por $wpdb->prepare();
                             columnas y tablas solo desde la lista blanca de CRMI_Entities
  class-crmi-installer.php   dbDelta() de las 3 tablas, capacidad crmi_manage al administrador,
                             maybe_upgrade() compara la opción crmi_db_version con CRMI_DB_VERSION
  class-crmi-sample-data.php Datos de ejemplo bajo petición; guarda IDs en crmi_sample_ids para borrarlos
  class-crmi-plugin.php      Arranque en plugins_loaded (textdomain, maybe_upgrade, admin)
  class-crmi-admin.php       Menús, carga de assets solo en sus pantallas, guardado (hook load-{pantalla},
                             patrón POST-redirect-GET), borrado y ajustes (admin-post.php), render de vistas
admin/views/
  dashboard.php  list.php (genérico)  form.php (genérico, se genera desde CRMI_Entities)  settings.php
assets/css/admin.css         Tarjetas, insignias de estado, tablas que pasan a tarjetas < 782 px
assets/js/admin.js           Confirmación para elementos con data-crmi-confirm
languages/crm-inmobiliario-wp.pot
tests/test-crm.php           Pruebas de integración (WP-CLI eval-file)
tests/e2e-smoke.sh           Prueba de humo HTTP con curl (login, formularios, nonces, permisos)
bin/build-zip.sh             ZIP instalable con git archive (respeta export-ignore)
.github/workflows/ci.yml     php -l (7.4–8.3), PHPCS, integración + HTTP con MariaDB
```

### Modelo de datos (tablas propias, prefijo de WordPress + `crmi_`)

| Tabla | Columnas principales |
|---|---|
| `crmi_contacts` | id, name*, phone, email, source, status, notes, created_at, updated_at |
| `crmi_properties` | id, title*, reference (única si no está vacía), operation, property_type, price DECIMAL(12,2) NULL, location, status, description, created_at, updated_at |
| `crmi_followups` | id, title*, contact_id (0 = ninguno), property_id (0 = ninguna), due_date DATE NULL, status, notes, created_at, updated_at |

- Al borrar un contacto o una propiedad, sus seguimientos **se conservan** y se desvinculan (id a 0).
- "Leads" = contactos en `nuevo`, `contactado` o `cualificado` (`CRMI_Entities::LEAD_STATUSES`). "Oportunidades" = `negociacion`.
- Las fechas se guardan en hora local del sitio (`current_time( 'mysql' )`).

### Flujo de una petición de guardado

1. El formulario (`form.php`) envía POST a la misma pantalla con `crmi_save=1`, `id` y nonce `crmi_save_{entidad}_{id}`.
2. `CRMI_Admin::maybe_handle_save()` (hook `load-{pantalla}`, antes de las cabeceras): `current_user_can( CRMI_CAP )` → `check_admin_referer()` → `wp_unslash()` → `CRMI_Entities::sanitize()` → `validate_relations()` (referencia única, relaciones existentes).
3. Con errores: se vuelve a pintar el formulario con lo escrito y los mensajes. Sin errores: insert/update y `wp_safe_redirect()` a la ficha con `crmi_msg=saved`.

## 3. Requisitos técnicos y convenciones

- PHP **7.4+** (no uses sintaxis de PHP 8: `match`, tipos unión, promoción de constructor, `str_contains`, argumentos con nombre…). WordPress **6.0+**.
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/): tabuladores, espacios dentro de paréntesis, condiciones Yoda, `array()` largo, docblocks en español.
- Prefijos: clases `CRMI_`, funciones/variables globales `crmi_`, opciones `crmi_`, tablas `{prefix}crmi_`, constantes `CRMI_`. En las vistas, las variables propias empiezan por `$crmi_`.
- Text domain: `crm-inmobiliario-wp`. Cadenas de origen **en español**. Usa `__()`, `esc_html__()`, `_n()` y comentarios `/* translators: */` con marcadores.
- Para añadir un campo: añádelo en `CRMI_Entities::all()`, en el `CREATE TABLE` de `CRMI_Installer::create_tables()`, **incrementa `CRMI_DB_VERSION`** (dbDelta añadirá la columna) y, si debe verse en el listado, en `CRMI_Admin::list_columns()`. El formulario se genera solo.
- Tipos de campo admitidos: `text`, `email`, `tel`, `textarea`, `select` (con `options` y `default`), `relation` (con `relation`), `price`, `date`. Opciones: `required`, `max`, `unique`.
- Sin sobrearquitectura: no introduzcas contenedores de dependencias, ORMs ni sistemas de plantillas.

## 4. Seguridad y tratamiento de datos (reglas obligatorias)

1. **Permisos**: toda pantalla y toda acción comprueba `current_user_can( CRMI_CAP )`; los ajustes, `manage_options`. Nunca confíes solo en que el menú esté oculto.
2. **Nonces** en todas las escrituras: guardado (`crmi_save_{entidad}_{id}`), borrado (`crmi_delete_{entidad}_{id}`, enlace con `wp_nonce_url`) y ajustes (`crmi_settings`). Verifica con `check_admin_referer()`.
3. **Entrada**: `wp_unslash()` y saneamiento por tipo en `CRMI_Entities::sanitize()`; los selectores solo aceptan claves de la lista; los IDs con `absint()`; los filtros GET se validan contra las opciones.
4. **SQL**: valores siempre con `$wpdb->prepare()` o los métodos `insert/update/delete` de `$wpdb`; `LIKE` con `$wpdb->esc_like()`. Nombres de columna/tabla **solo** desde `CRMI_Entities` (nunca desde la petición). `orderby` pasa por lista blanca.
5. **Salida**: escapa todo en el punto de salida (`esc_html`, `esc_attr`, `esc_url`, `esc_textarea`). `CRMI_Admin::cell()` devuelve HTML ya escapado.
6. **Redirecciones** con `wp_safe_redirect()` + `exit`. Mensajes de aviso por clave de lista blanca (`crmi_msg`).
7. **Datos**: desactivar nunca borra; desinstalar solo borra si el administrador lo activó. Los datos de ejemplo solo se crean con confirmación explícita y se eliminan por ID.
8. **Repositorio**: no subas credenciales, `wp-config.php`, `.env`, volcados de base de datos ni datos personales reales. Los ejemplos usan `example.com`.
9. Sin `eval`, sin `extract`, sin llamadas HTTP salientes, sin cargar recursos desde CDN.

## 5. Instalación para desarrollo y pruebas

Requisitos locales: PHP 7.4+, Composer, WP-CLI y un WordPress de **desarrollo** (MySQL/MariaDB o SQLite).

```bash
# Herramientas de calidad (solo desarrollo)
composer install

# WordPress de pruebas con WP-CLI (ejemplo con MariaDB local)
wp core download --path=/tmp/wp
wp config create --path=/tmp/wp --dbname=wp --dbuser=wp --dbpass=wp --dbhost=localhost
wp core install --path=/tmp/wp --url=http://127.0.0.1:8899 --title=CRM \
  --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email
ln -s "$PWD" /tmp/wp/wp-content/plugins/crm-inmobiliario-wp
wp plugin activate crm-inmobiliario-wp --path=/tmp/wp
wp user create agente agente@example.com --role=editor --user_pass=agente --path=/tmp/wp
php -S 127.0.0.1:8899 -t /tmp/wp &
```

> Si `home`/`siteurl` no coinciden con la URL del servidor, el inicio de sesión de la prueba HTTP falla ("Cookies are blocked"). Corrígelo con `wp option update home …` y `wp option update siteurl …`.

## 6. Comandos de validación

| Comando | Qué comprueba |
|---|---|
| `composer lint` | `php -l` en todos los PHP |
| `composer phpcs` (o `vendor/bin/phpcs`) | WordPress Coding Standards + PHPCompatibilityWP 7.4+ (`phpcs.xml.dist`) |
| `vendor/bin/phpcbf` | Corrige automáticamente lo que pueda |
| `CRMI_RUN_TESTS=1 wp eval-file tests/test-crm.php --path=…` | Integración: instalación, saneamiento, precios, CRUD, búsqueda, filtros, orden, paginación, inyección SQL, relaciones, borrado, panel, render/escape, permisos, datos de ejemplo |
| `tests/e2e-smoke.sh URL admin pass [usuario_sin_cap pass]` | HTTP real: login, pantallas, alta con error y correcta, edición, búsqueda, seguimiento vinculado, nonce falso, borrado protegido, acceso denegado |
| `wp plugin deactivate/uninstall crm-inmobiliario-wp --skip-delete` | Ciclo de vida y política de datos (usa `--skip-delete` si el plugin es un enlace simbólico a tu copia de trabajo) |
| `wp i18n make-pot . languages/crm-inmobiliario-wp.pot --exclude=tests,bin,vendor,dist` | Regenera la plantilla de traducción |

### Resultados reales de la última sesión (2026-10-08, versión 0.1.0)

Entorno: contenedor Linux, PHP 8.3.6, WordPress 6.8.3, WP-CLI 2.11.0.

- `php -l`: sin errores en todos los archivos.
- PHPCS (WPCS 3.4.1 + PHPCompatibilityWP, testVersion 7.4-): **0 errores, 0 avisos**.
- `tests/test-crm.php` sobre **MariaDB 10.11.14**: **90 correctas, 0 fallidas**.
- `tests/test-crm.php` sobre **SQLite** (plugin oficial sqlite-database-integration 2.2.3): **89 correctas, 0 fallidas, 1 omitida** (la emulación no respeta el escape de `esc_like()`).
- `tests/e2e-smoke.sh` contra `php -S` + MariaDB, con un editor sin capacidad: **31 correctas, 0 fallidas**.
- Ciclo de vida verificado con WP-CLI: activación crea 3 tablas y la capacidad; `dbDelta` repetido sin errores; desactivar conserva datos; desinstalar con la política por defecto conserva tablas y opciones; con la opción activada borra tablas, opciones y capacidad; reactivar crea tablas vacías sin datos de ejemplo.
- Revisión visual con Chromium (Playwright) a 1366 px y 390 px: panel, listados y formularios correctos.
- **GitHub Actions** (ejecución 1, commit `f628a52`): los 8 trabajos en verde — `php -l` en PHP 7.4, 8.0, 8.1, 8.2 y 8.3; PHPCS; integración + HTTP con MariaDB 10.11 en **WordPress 6.0 / PHP 7.4** (90/90 y 31/31 según el registro) y en **WordPress latest / PHP 8.3**.
- ZIP de `bin/build-zip.sh` (25 entradas, sin archivos de desarrollo) instalado y activado con `wp plugin install` en un WordPress limpio.
- Comprueba siempre el estado actual en la pestaña *Actions* antes de afirmar que algo pasa.

## 7. Procedimiento para hacer cambios sin romper nada

1. Lee este archivo y el código afectado. Revisa `git log` para entender cambios recientes.
2. Crea una rama desde `main` (`git switch -c tipo/descripcion-corta`).
3. Haz un cambio pequeño y acotado. Mantén `CRMI_Entities` como fuente única de verdad.
4. Si cambias el esquema, incrementa `CRMI_DB_VERSION`; si cambias la versión del plugin, actualiza `Version` en la cabecera, `CRMI_VERSION`, `Stable tag` y el *Changelog* de `readme.txt`.
5. Añade o ajusta pruebas en `tests/test-crm.php` (y en `e2e-smoke.sh` si cambia un flujo de formulario).
6. Ejecuta `composer lint`, `composer phpcs`, las pruebas de integración y la de humo. **No declares que algo funciona si no lo has ejecutado**; si no puedes ejecutarlo, dilo.
7. Revisa a mano la pantalla afectada en escritorio y móvil.
8. Actualiza README.md, readme.txt y la sección 6/9 de este archivo con los resultados reales.

## 8. Reglas de Git

- Rama principal y predeterminada: **`main`** (nunca `master`).
- Cambios pequeños y atómicos; un commit por cambio lógico.
- Mensajes de commit en español, en imperativo y explicando el porqué: `Corrige el filtro de vencidos para excluir completados`.
- No reescribas el historial de `main` (sin `push --force`).
- No subas `vendor/`, `dist/`, ZIPs, `.env` ni registros (ver `.gitignore`).

## 9. Estado actual, limitaciones y tareas pendientes

**Estado (0.1.0)**: funcional y probado como se indica en la sección 6. Panel, contactos, propiedades, seguimientos, relaciones, búsqueda, filtros, orden, paginación, ajustes, datos de ejemplo y política de desinstalación implementados.

**Limitaciones conocidas**

- Los selectores de relación cargan como máximo 500 registros (siempre incluyen el valor actual).
- Todos los usuarios con `crmi_manage` ven todos los registros (sin propietario por agente).
- Sin importación/exportación, adjuntos ni fotos.
- Moneda fija «€» (filtro `crmi_currency_symbol`).
- Multisitio no probado manualmente.
- No hay prueba automatizada de `uninstall.php` (verificado a mano con WP-CLI).

**Tareas pendientes sugeridas** (en orden): exportación CSV; asignación a agente; historial de actividad; selector de relación con búsqueda AJAX; traducción `en_US`; prueba automatizada de desinstalación.

## 10. Preparar una versión instalable

1. Actualiza versión (cabecera, `CRMI_VERSION`, `readme.txt` *Stable tag* y *Changelog*) y regenera el `.pot`.
2. Ejecuta todas las validaciones de la sección 6.
3. Haz commit en `main` (o fusiona la rama) y crea una etiqueta: `git tag v0.1.0 && git push origin v0.1.0`.
4. Genera el paquete: `bin/build-zip.sh` → `dist/crm-inmobiliario-wp-<versión>.zip` (carpeta interna `crm-inmobiliario-wp/`, sin `tests/`, `bin/`, `.github/`, Composer, `README.md` ni `SKILL.md`).
5. Comprueba el ZIP instalándolo en un WordPress limpio (*Plugins → Subir plugin*) y adjúntalo a la *Release* de GitHub.
6. Actualiza la documentación con los resultados reales.

## 11. Documentación oficial de WordPress

- [Plugin Handbook](https://developer.wordpress.org/plugins/)
- [Cabeceras del plugin](https://developer.wordpress.org/plugins/plugin-basics/header-requirements/)
- [Activación y desactivación](https://developer.wordpress.org/plugins/plugin-basics/activation-deactivation-hooks/) · [Desinstalación](https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/)
- [Seguridad: validación y saneamiento](https://developer.wordpress.org/apis/security/sanitizing/) · [Escape de salida](https://developer.wordpress.org/apis/security/escaping/) · [Nonces](https://developer.wordpress.org/apis/security/nonces/) · [Comprobación de permisos](https://developer.wordpress.org/plugins/security/checking-user-capabilities/)
- [Roles y capacidades](https://developer.wordpress.org/plugins/users/roles-and-capabilities/)
- [Clase `wpdb`](https://developer.wordpress.org/reference/classes/wpdb/) · [`dbDelta()`](https://developer.wordpress.org/reference/functions/dbdelta/)
- [Menús de administración](https://developer.wordpress.org/plugins/administration-menus/)
- [Internacionalización](https://developer.wordpress.org/plugins/internationalization/)
- [Estándares de código PHP](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- [Formato de readme.txt](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/)
- [WP-CLI](https://developer.wordpress.org/cli/commands/)

## 12. Recursos relacionados y contexto del sector

Para entender qué esperan los agentes inmobiliarios de una herramienta así (gestión de leads, embudo comercial, seguimiento de propiedades) y decidir qué funcionalidades priorizar en la hoja de ruta, puede consultarse esta [guía sobre CRM inmobiliarios](https://inmotechhub.com/crm-inmobiliario/). Es un recurso divulgativo externo sobre el sector, no documentación técnica de este plugin ni una fuente vinculada a su desarrollo.
