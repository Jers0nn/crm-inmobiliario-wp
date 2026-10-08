# CRM Inmobiliario Sencillo

Plugin de WordPress que añade un CRM mínimo al escritorio para **agentes inmobiliarios y pequeñas inmobiliarias**: contactos y leads, propiedades y seguimientos comerciales, sin servicios externos ni dependencias de terceros.

> Estado: **versión 0.1.0 (primera versión funcional)**. Pruebas automáticas superadas en WordPress 6.0 (PHP 7.4), 6.8.3 (PHP 8.3) y la última versión (PHP 8.3) sobre MariaDB 10.11. Consulta [Pruebas](#pruebas) y [Limitaciones](#limitaciones).

## Funcionalidades

- **Panel principal** con el total de contactos, leads activos (nuevos, contactados o cualificados), oportunidades (en negociación), propiedades (y cuántas están disponibles), seguimientos pendientes y vencidos, los próximos seguimientos y los últimos contactos.
- **Contactos / leads**: nombre, teléfono, correo electrónico, origen del lead, estado, notas y fecha de creación.
- **Propiedades**: título, referencia (única), operación (venta o alquiler), tipo de inmueble, precio, ubicación, estado y descripción.
- **Seguimientos**: tarea, contacto y propiedad vinculados (opcionales), fecha de seguimiento, estado y notas. Los vencidos y los de hoy se resaltan.
- **Relaciones**: la ficha de un contacto o de una propiedad muestra sus seguimientos y permite crear uno ya vinculado.
- **Búsqueda, filtros, orden y paginación** en cada listado (por estado, origen, operación, tipo, contacto, propiedad y vencimiento).
- **Interfaz en español**, coherente con WordPress y adaptada a móvil (las tablas se convierten en tarjetas).
- **Datos de ejemplo opcionales**: solo se crean si un administrador lo pide expresamente en *Ajustes*, y se pueden eliminar sin tocar los datos reales.

Fuera de alcance a propósito: SaaS, multiempresa, facturación, IA, automatizaciones, WhatsApp, portales inmobiliarios, apps móviles e integraciones externas.

## Requisitos

| Componente | Versión |
|---|---|
| WordPress | 6.0 o superior |
| PHP | 7.4 o superior (8.x recomendado) |
| Base de datos | MySQL 5.7+ / MariaDB 10.3+ (las que admite WordPress) |

No necesita plugins adicionales ni Composer en producción.

## Instalación

**Opción A: ZIP desde GitHub**

1. En GitHub pulsa *Code → Download ZIP*. La descarga ya excluye las pruebas y archivos de desarrollo (ver `.gitattributes`).
2. En WordPress ve a *Plugins → Añadir nuevo → Subir plugin*, selecciona el ZIP e instala.
3. Activa **CRM Inmobiliario Sencillo**. Aparecerá el menú **CRM Inmobiliario**.

> La carpeta se llamará `crm-inmobiliario-wp-main`. Funciona igual; si prefieres el nombre `crm-inmobiliario-wp`, usa la opción B o C.

**Opción B: paquete generado**

```bash
git clone https://github.com/Jers0nn/crm-inmobiliario-wp.git
cd crm-inmobiliario-wp
bin/build-zip.sh        # crea dist/crm-inmobiliario-wp-0.1.0.zip
```

Sube ese ZIP desde *Plugins → Añadir nuevo → Subir plugin*.

**Opción C: copia directa**

Clona o copia el repositorio en `wp-content/plugins/crm-inmobiliario-wp/` y activa el plugin.

## Uso

1. **CRM Inmobiliario → Contactos → Añadir nuevo**: registra un lead con su origen y estado.
2. **Propiedades → Añadir nuevo**: registra inmuebles. El precio admite `250000`, `250.000` o `1.250,50`.
3. **Seguimientos → Añadir nuevo**: crea una tarea con fecha y vincúlala a un contacto o propiedad. También puedes hacerlo desde la ficha del contacto o de la propiedad.
4. Revisa el **Panel** cada día: muestra lo pendiente y lo vencido.
5. En **Ajustes** (solo administradores) puedes crear/eliminar datos de ejemplo y decidir si se borran los datos al desinstalar.

### Permisos

El acceso requiere la capacidad `crmi_manage`, que se concede a los **administradores** al activar el plugin. Para dar acceso a un agente con otro rol, asígnale esa capacidad con un gestor de roles. La pantalla de *Ajustes* exige `manage_options`.

### Política de datos

- **Desactivar** el plugin **nunca** borra datos.
- **Desinstalar** (eliminar desde *Plugins*) **conserva** los datos por defecto. Solo se eliminan las tablas, opciones y la capacidad si un administrador activó previamente *“Eliminar todas las tablas y opciones del CRM cuando se desinstale el plugin”* en *Ajustes*.
- Los datos se guardan en tres tablas propias de tu base de datos: `{prefijo}crmi_contacts`, `{prefijo}crmi_properties` y `{prefijo}crmi_followups`.
- El plugin no realiza llamadas externas, no incluye telemetría ni envía datos a terceros. Al almacenar datos personales de clientes, tú eres responsable de cumplir la normativa aplicable (por ejemplo, el RGPD).

## Desarrollo

```
crm-inmobiliario-wp.php        Cabecera del plugin, constantes y hooks de activación
uninstall.php                  Desinstalación (respeta la política de datos)
includes/
  class-crmi-entities.php      Campos, opciones, saneamiento y validación
  class-crmi-repository.php    Acceso a datos con $wpdb->prepare()
  class-crmi-installer.php     Tablas (dbDelta), capacidad y actualizaciones
  class-crmi-sample-data.php   Datos de ejemplo opcionales
  class-crmi-plugin.php        Arranque
  class-crmi-admin.php         Menús, pantallas y formularios
admin/views/                   Plantillas (panel, listado, formulario, ajustes)
assets/                        CSS y JS del escritorio (sin dependencias)
languages/                     Plantilla de traducción .pot
tests/                         Pruebas de integración (WP-CLI) y de humo (HTTP)
bin/build-zip.sh               Generador del ZIP instalable
```

Guía completa para mantener el proyecto: [SKILL.md](SKILL.md).

### Comprobaciones

```bash
composer install                      # solo herramientas de desarrollo
composer lint                         # php -l en todos los archivos
composer phpcs                        # WordPress Coding Standards + PHPCompatibilityWP (7.4+)
```

### Pruebas

En un WordPress **de desarrollo** con el plugin activo:

```bash
CRMI_RUN_TESTS=1 wp eval-file tests/test-crm.php
tests/e2e-smoke.sh http://localhost:8080 admin contraseña [usuario_sin_permisos contraseña]
```

Resultados de la última ejecución (WordPress 6.8.3, PHP 8.3.6):

| Prueba | MariaDB 10.11 | SQLite (integración oficial) |
|---|---|---|
| `tests/test-crm.php` | 90 correctas, 0 fallidas | 89 correctas, 0 fallidas, 1 omitida* |
| `tests/e2e-smoke.sh` | 31 correctas, 0 fallidas | — |
| `phpcs` | sin errores ni avisos | |

\* La emulación SQLite no respeta el escape de comodines de `esc_like()`; en MySQL/MariaDB sí se comprueba.

El flujo de GitHub Actions (`.github/workflows/ci.yml`) repite en cada envío a `main` la sintaxis en PHP 7.4–8.3, PHPCS y las pruebas de integración y HTTP con WordPress 6.0/PHP 7.4 y la última versión de WordPress/PHP 8.3 (MariaDB 10.11). Primera ejecución: los 8 trabajos en verde; en WordPress 6.0/PHP 7.4, 90/90 pruebas de integración y 31/31 HTTP.

## Limitaciones

- Los selectores de contacto/propiedad en el formulario de seguimiento cargan hasta 500 registros (ordenados alfabéticamente); con más datos haría falta un buscador.
- Sin importación/exportación CSV, adjuntos, fotos de inmuebles ni asignación de registros a agentes concretos: todos los usuarios con `crmi_manage` ven todos los datos.
- El símbolo de moneda es «€» (se puede cambiar con el filtro `crmi_currency_symbol`).
- Los textos de origen están en español; el plugin es traducible mediante `languages/crm-inmobiliario-wp.pot`.
- No probado todavía en multisitio (la tabla se crea por sitio al cargar el escritorio; la desinstalación recorre todos los sitios).

## Hoja de ruta

1. Exportación CSV de contactos y propiedades.
2. Asignación de contactos y seguimientos a un agente (usuario).
3. Historial de actividad por contacto.
4. Buscador AJAX en los selectores de relación.
5. Traducción al inglés (`en_US`).

## Licencia

[MIT](LICENSE). Compatible con la GPL de WordPress.
