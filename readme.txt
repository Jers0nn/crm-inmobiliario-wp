=== CRM Inmobiliario Sencillo ===
Contributors: jers0nn
Tags: crm, inmobiliaria, real estate, leads, propiedades
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.0
License: MIT
License URI: https://opensource.org/licenses/MIT

CRM mínimo para agentes inmobiliarios: contactos y leads, propiedades y seguimientos comerciales dentro del escritorio de WordPress.

== Description ==

CRM Inmobiliario Sencillo añade un menú "CRM Inmobiliario" al escritorio de WordPress con lo esencial para gestionar la actividad comercial de un agente o de una pequeña inmobiliaria:

* Panel con contactos, leads activos, oportunidades en negociación, propiedades disponibles y seguimientos pendientes y vencidos.
* Contactos: nombre, teléfono, correo, origen del lead, estado, notas y fecha de creación.
* Propiedades: título, referencia única, venta o alquiler, tipo de inmueble, precio, ubicación, estado y descripción.
* Seguimientos: tarea, fecha, estado y notas, vinculados opcionalmente a un contacto y a una propiedad.
* Búsqueda, filtros, orden y paginación en todos los listados.
* Interfaz en español y adaptada a móvil.
* Datos de ejemplo solo bajo petición expresa del administrador.

Sin dependencias, sin llamadas externas y sin telemetría. Los datos se guardan en tablas propias de tu base de datos.

== Installation ==

1. Sube el ZIP desde Plugins > Añadir nuevo > Subir plugin (o copia la carpeta en wp-content/plugins/crm-inmobiliario-wp).
2. Activa "CRM Inmobiliario Sencillo".
3. Abre el menú "CRM Inmobiliario".

Los administradores reciben la capacidad crmi_manage al activar el plugin. Para dar acceso a otros roles, asígnales esa capacidad con un gestor de roles.

== Frequently Asked Questions ==

= ¿Se borran mis datos si desactivo el plugin? =

No. Desactivar nunca borra datos.

= ¿Y si lo elimino (desinstalo)? =

Por defecto los datos se conservan. Solo se eliminan si activaste antes la opción correspondiente en CRM Inmobiliario > Ajustes.

= ¿Envía datos a algún servicio externo? =

No. El plugin no hace llamadas externas ni incluye seguimiento.

= ¿Puedo cambiar el símbolo de moneda? =

Sí, con el filtro crmi_currency_symbol.

== Changelog ==

= 0.1.0 =
* Primera versión: panel, contactos, propiedades, seguimientos, búsqueda y filtros, datos de ejemplo opcionales y política de desinstalación configurable.

== Upgrade Notice ==

= 0.1.0 =
Primera versión pública.
