<?php
/**
 * Pruebas de integración del plugin.
 *
 * Se ejecutan dentro de una instalación de WordPress real con el plugin
 * activo, mediante WP-CLI:
 *
 *     CRMI_RUN_TESTS=1 wp eval-file tests/test-crm.php
 *
 * Crean y eliminan sus propios registros, pero úsalas SOLO en un sitio
 * de desarrollo desechable. Termina con código de salida 1 si algo falla.
 *
 * @package CRM_Inmobiliario
 */

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Script de pruebas de línea de comandos.

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( "Ejecuta este archivo con: wp eval-file tests/test-crm.php\n" );
}
if ( '1' !== getenv( 'CRMI_RUN_TESTS' ) ) {
	WP_CLI::error( 'Define CRMI_RUN_TESTS=1 para confirmar que este es un sitio de pruebas.' );
}
if ( ! class_exists( 'CRMI_Repository' ) ) {
	WP_CLI::error( 'El plugin CRM Inmobiliario Sencillo no está activo.' );
}
require_once CRMI_DIR . 'includes/class-crmi-admin.php';

$GLOBALS['crmi_t'] = array(
	'pass'    => 0,
	'fail'    => 0,
	'created' => array(
		'contacts'   => array(),
		'properties' => array(),
		'followups'  => array(),
	),
);

/**
 * Registra una aserción.
 *
 * @param bool   $condition Condición esperada.
 * @param string $message   Descripción.
 */
function crmi_assert( $condition, $message ) {
	global $crmi_t;
	if ( $condition ) {
		++$crmi_t['pass'];
		WP_CLI::log( "  ✔ {$message}" );
	} else {
		++$crmi_t['fail'];
		WP_CLI::warning( "✘ {$message}" );
	}
}

/**
 * Inserta y recuerda el ID para limpiar al final.
 *
 * @param string $entity Entidad.
 * @param array  $raw    Datos de entrada.
 * @return int
 */
function crmi_make( $entity, array $raw ) {
	global $crmi_t;
	list( $data ) = CRMI_Entities::sanitize( $entity, $raw );
	$id           = CRMI_Repository::for_entity( $entity )->insert( $data );
	if ( $id ) {
		$crmi_t['created'][ $entity ][] = $id;
	}
	return (int) $id;
}

$contacts   = CRMI_Repository::for_entity( 'contacts' );
$properties = CRMI_Repository::for_entity( 'properties' );
$followups  = CRMI_Repository::for_entity( 'followups' );

WP_CLI::log( 'Instalación' );
global $wpdb;
foreach ( CRMI_Installer::tables() as $crmi_table ) {
	crmi_assert( $crmi_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $crmi_table ) ), "existe la tabla {$crmi_table}" );
}
crmi_assert( CRMI_DB_VERSION === get_option( 'crmi_db_version' ), 'versión de esquema guardada' );
crmi_assert( get_role( 'administrator' )->has_cap( CRMI_CAP ), 'el administrador tiene la capacidad crmi_manage' );
crmi_assert( ! get_role( 'subscriber' )->has_cap( CRMI_CAP ), 'el suscriptor NO tiene la capacidad crmi_manage' );
crmi_assert( 'no' === get_option( 'crmi_delete_data_on_uninstall' ), 'por defecto no se borran datos al desinstalar' );

WP_CLI::log( 'Precios y fechas' );
$crmi_prices = array(
	'250000'      => 250000.0,
	'250.000'     => 250000.0,
	'1.250.000'   => 1250000.0,
	'250.000,50'  => 250000.5,
	'250,000.50'  => 250000.5,
	'1250,5'      => 1250.5,
	'1.250,5 €'   => 1250.5,
	'950'         => 950.0,
	'99.99'       => 99.99,
	''            => null,
	'-5'          => false,
	'abc'         => null,
	'99999999999' => false,
);
foreach ( $crmi_prices as $crmi_in => $crmi_expected ) {
	$crmi_got = CRMI_Entities::parse_price( (string) $crmi_in );
	crmi_assert( $crmi_expected === $crmi_got, sprintf( 'parse_price(%s) = %s', var_export( (string) $crmi_in, true ), var_export( $crmi_expected, true ) ) );
}
crmi_assert( CRMI_Entities::is_valid_date( '2024-02-29' ), 'fecha bisiesta válida' );
crmi_assert( ! CRMI_Entities::is_valid_date( '2023-02-29' ), 'fecha inexistente rechazada' );
crmi_assert( ! CRMI_Entities::is_valid_date( '29/02/2024' ), 'formato de fecha incorrecto rechazado' );

WP_CLI::log( 'Saneamiento y validación' );
list( $crmi_data, $crmi_err ) = CRMI_Entities::sanitize(
	'contacts',
	array(
		'name'   => '  <script>alert(1)</script>Ana <b>López</b> ',
		'email'  => 'no-es-un-correo',
		'phone'  => '+34 600<x> 000-000',
		'status' => 'inventado',
		'source' => 'referido',
		'id'     => 999,
		'hack'   => 'DROP TABLE',
	)
);
crmi_assert( 'Ana López' === $crmi_data['name'], 'el nombre se limpia de etiquetas HTML' );
crmi_assert( '+34 600 000-000' === $crmi_data['phone'], 'el teléfono solo conserva caracteres válidos' );
crmi_assert( in_array( 'email', $crmi_err->get_error_codes(), true ), 'correo no válido genera error' );
crmi_assert( in_array( 'status', $crmi_err->get_error_codes(), true ) && 'nuevo' === $crmi_data['status'], 'estado no permitido genera error y vuelve al valor por defecto' );
crmi_assert( 'referido' === $crmi_data['source'], 'origen permitido se conserva' );
crmi_assert( ! isset( $crmi_data['id'] ) && ! isset( $crmi_data['hack'] ), 'se ignoran campos no definidos' );

list( , $crmi_err ) = CRMI_Entities::sanitize( 'contacts', array( 'name' => '   ' ) );
crmi_assert( in_array( 'name', $crmi_err->get_error_codes(), true ), 'el nombre es obligatorio' );

list( $crmi_data, $crmi_err ) = CRMI_Entities::sanitize( 'contacts', array( 'name' => 'Válido', 'email' => 'ok@example.com' ) );
crmi_assert( ! $crmi_err->has_errors() && 'web' === $crmi_data['source'] && 'nuevo' === $crmi_data['status'], 'contacto mínimo válido con valores por defecto' );

list( $crmi_data, $crmi_err ) = CRMI_Entities::sanitize( 'properties', array( 'title' => 'Piso', 'price' => '-100' ) );
crmi_assert( in_array( 'price', $crmi_err->get_error_codes(), true ), 'precio negativo rechazado' );

list( $crmi_data, $crmi_err ) = CRMI_Entities::sanitize( 'properties', array( 'title' => str_repeat( 'x', 300 ), 'price' => '185.000' ) );
crmi_assert( 190 === mb_strlen( $crmi_data['title'] ) && 185000.0 === $crmi_data['price'], 'título truncado a 190 caracteres y precio normalizado' );

list( $crmi_data, $crmi_err ) = CRMI_Entities::sanitize( 'followups', array( 'title' => 'Llamar', 'due_date' => '2024-13-01', 'contact_id' => '-7' ) );
crmi_assert( in_array( 'due_date', $crmi_err->get_error_codes(), true ), 'fecha de seguimiento no válida rechazada' );
crmi_assert( 7 === $crmi_data['contact_id'], 'el ID de relación se convierte a entero positivo' );

WP_CLI::log( 'Repositorio (CRUD, búsqueda, filtros, orden y paginación)' );
$crmi_base_contacts = $contacts->count();
$crmi_base_leads    = $contacts->count( array( 'status' => CRMI_Entities::LEAD_STATUSES ) );
$crmi_tag           = 'zzprueba' . wp_rand( 1000, 9999 );

$crmi_c1 = crmi_make( 'contacts', array( 'name' => "Carmen {$crmi_tag}", 'email' => 'carmen@example.com', 'status' => 'nuevo', 'source' => 'web' ) );
$crmi_c2 = crmi_make( 'contacts', array( 'name' => "Pedro {$crmi_tag}", 'phone' => '611 222 333', 'status' => 'negociacion', 'source' => 'portal' ) );
$crmi_c3 = crmi_make( 'contacts', array( 'name' => "Zoe {$crmi_tag}", 'status' => 'cliente', 'source' => 'portal' ) );
crmi_assert( $crmi_c1 && $crmi_c2 && $crmi_c3, 'se insertan tres contactos' );

$crmi_row = $contacts->find( $crmi_c1 );
crmi_assert( $crmi_row && "Carmen {$crmi_tag}" === $crmi_row['name'] && ! empty( $crmi_row['created_at'] ), 'find() devuelve el contacto con fecha de creación' );
crmi_assert( null === $contacts->find( 0 ) && null === $contacts->find( 99999999 ), 'find() devuelve null para IDs inexistentes' );

list( $crmi_data ) = CRMI_Entities::sanitize( 'contacts', array_merge( $crmi_row, array( 'status' => 'contactado' ) ) );
crmi_assert( $contacts->update( $crmi_c1, $crmi_data ) && 'contactado' === $contacts->find( $crmi_c1 )['status'], 'update() modifica el estado' );

crmi_assert( $crmi_base_contacts + 3 === $contacts->count(), 'count() suma los nuevos contactos' );
crmi_assert( $crmi_base_leads + 1 === $contacts->count( array( 'status' => CRMI_Entities::LEAD_STATUSES ) ), 'count() de leads con lista de estados' );

$crmi_res = $contacts->query( array( 'search' => $crmi_tag ) );
crmi_assert( 3 === $crmi_res['total'], 'búsqueda por nombre encuentra 3' );
$crmi_res = $contacts->query( array( 'search' => '611 222' ) );
crmi_assert( 1 <= $crmi_res['total'] && in_array( (string) $crmi_c2, wp_list_pluck( $crmi_res['items'], 'id' ), true ), 'búsqueda por teléfono' );
$crmi_res = $contacts->query( array( 'search' => $crmi_tag, 'filters' => array( 'source' => 'portal' ) ) );
crmi_assert( 2 === $crmi_res['total'], 'filtro por origen' );
$crmi_res = $contacts->query( array( 'search' => $crmi_tag, 'filters' => array( 'source' => 'portal', 'status' => 'cliente' ) ) );
crmi_assert( 1 === $crmi_res['total'] && (int) $crmi_res['items'][0]['id'] === $crmi_c3, 'filtros combinados' );
$crmi_res = $contacts->query( array( 'search' => $crmi_tag, 'orderby' => 'name', 'order' => 'DESC' ) );
crmi_assert( "Zoe {$crmi_tag}" === $crmi_res['items'][0]['name'], 'orden por nombre descendente' );
$crmi_res = $contacts->query( array( 'search' => $crmi_tag, 'orderby' => 'name', 'order' => 'ASC', 'per_page' => 2, 'page' => 2 ) );
crmi_assert( 3 === $crmi_res['total'] && 1 === count( $crmi_res['items'] ) && "Zoe {$crmi_tag}" === $crmi_res['items'][0]['name'], 'paginación' );
if ( defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE ) {
	// La emulación SQLite no respeta el escape con barra invertida de esc_like(); MySQL/MariaDB sí.
	WP_CLI::log( '  - (omitida en SQLite) el comodín % se trata como texto literal en la búsqueda' );
} else {
	$crmi_res = $contacts->query( array( 'search' => str_replace( 'zz', 'zz%', $crmi_tag ) ) );
	crmi_assert( 0 === $crmi_res['total'], 'el comodín % se trata como texto literal en la búsqueda' );
}

WP_CLI::log( 'Inyección SQL y listas blancas' );
$crmi_res = $contacts->query(
	array(
		'search'  => $crmi_tag,
		'orderby' => 'name; DROP TABLE wp_users',
		'filters' => array(
			'1=1 OR name' => 'x',
			'status'      => "nuevo' OR '1'='1",
		),
	)
);
crmi_assert( 0 === $crmi_res['total'], 'columnas no permitidas se ignoran y los valores van entrecomillados' );
crmi_assert( $wpdb->users === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->users ) ), 'la tabla de usuarios sigue existiendo' );
$crmi_res = $contacts->query( array( 'search' => "' OR 1=1 -- " ) );
crmi_assert( 0 === $crmi_res['total'], 'búsqueda maliciosa no devuelve resultados' );

WP_CLI::log( 'Propiedades y referencia única' );
$crmi_ref = 'T-' . $crmi_tag;
$crmi_p1  = crmi_make( 'properties', array( 'title' => "Ático {$crmi_tag}", 'reference' => $crmi_ref, 'operation' => 'venta', 'property_type' => 'piso', 'price' => '320.000', 'location' => 'Valencia' ) );
$crmi_p2  = crmi_make( 'properties', array( 'title' => "Local {$crmi_tag}", 'operation' => 'alquiler', 'property_type' => 'local', 'price' => '1.200', 'status' => 'reservada' ) );
crmi_assert( $crmi_p1 && $crmi_p2, 'se insertan dos propiedades' );
crmi_assert( 320000.0 === (float) $properties->find( $crmi_p1 )['price'], 'el precio se guarda como decimal' );
crmi_assert( null === $properties->find( $crmi_p2 )['description'] || '' === $properties->find( $crmi_p2 )['description'], 'descripción vacía permitida' );

$crmi_admin                   = new CRMI_Admin();
list( $crmi_data, $crmi_err ) = CRMI_Entities::sanitize( 'properties', array( 'title' => 'Duplicada', 'reference' => $crmi_ref ) );
$crmi_admin->validate_relations( 'properties', $crmi_data, $crmi_err, $properties, 0 );
crmi_assert( in_array( 'reference', $crmi_err->get_error_codes(), true ), 'referencia duplicada rechazada al crear' );
list( $crmi_data, $crmi_err ) = CRMI_Entities::sanitize( 'properties', array( 'title' => 'Misma', 'reference' => $crmi_ref ) );
$crmi_admin->validate_relations( 'properties', $crmi_data, $crmi_err, $properties, $crmi_p1 );
crmi_assert( ! $crmi_err->has_errors(), 'la propia propiedad puede conservar su referencia al editar' );

$crmi_res = $properties->query( array( 'search' => 'Valencia', 'filters' => array( 'operation' => 'venta' ) ) );
crmi_assert( in_array( (string) $crmi_p1, wp_list_pluck( $crmi_res['items'], 'id' ), true ), 'búsqueda por ubicación + filtro de operación' );
$crmi_res = $properties->query( array( 'search' => $crmi_tag, 'orderby' => 'price', 'order' => 'ASC' ) );
crmi_assert( (int) $crmi_res['items'][0]['id'] === $crmi_p2, 'orden por precio ascendente' );

WP_CLI::log( 'Seguimientos y relaciones' );
$crmi_today = current_time( 'Y-m-d' );
$crmi_f1    = crmi_make( 'followups', array( 'title' => "Vencido {$crmi_tag}", 'contact_id' => $crmi_c1, 'property_id' => $crmi_p1, 'due_date' => gmdate( 'Y-m-d', strtotime( $crmi_today . ' -3 days' ) ), 'status' => 'pendiente' ) );
$crmi_f2    = crmi_make( 'followups', array( 'title' => "Hoy {$crmi_tag}", 'contact_id' => $crmi_c1, 'due_date' => $crmi_today ) );
$crmi_f3    = crmi_make( 'followups', array( 'title' => "Hecho {$crmi_tag}", 'contact_id' => $crmi_c2, 'property_id' => $crmi_p1, 'due_date' => gmdate( 'Y-m-d', strtotime( $crmi_today . ' -5 days' ) ), 'status' => 'completada' ) );
$crmi_f4    = crmi_make( 'followups', array( 'title' => "Sin fecha {$crmi_tag}", 'property_id' => $crmi_p2 ) );
crmi_assert( $crmi_f1 && $crmi_f2 && $crmi_f3 && $crmi_f4, 'se insertan cuatro seguimientos' );
crmi_assert( null === $followups->find( $crmi_f4 )['due_date'], 'fecha vacía se guarda como NULL' );

$crmi_ids = function ( $result ) {
	return array_map( 'intval', wp_list_pluck( $result['items'], 'id' ) );
};
$crmi_res = $followups->query( array( 'search' => $crmi_tag, 'due' => 'overdue' ) );
crmi_assert( array( $crmi_f1 ) === $crmi_ids( $crmi_res ), 'filtro "vencidos" excluye completados y sin fecha' );
$crmi_res = $followups->query( array( 'search' => $crmi_tag, 'due' => 'today' ) );
crmi_assert( array( $crmi_f2 ) === $crmi_ids( $crmi_res ), 'filtro "hoy"' );
$crmi_res = $followups->query( array( 'search' => $crmi_tag, 'filters' => array( 'contact_id' => $crmi_c1 ) ) );
crmi_assert( 2 === $crmi_res['total'], 'filtro por contacto' );
$crmi_res  = $followups->query( array( 'search' => $crmi_tag, 'orderby' => 'due_date', 'order' => 'ASC' ) );
$crmi_last = end( $crmi_res['items'] );
crmi_assert( (int) $crmi_last['id'] === $crmi_f4, 'los seguimientos sin fecha se ordenan al final' );

list( $crmi_data, $crmi_err ) = CRMI_Entities::sanitize( 'followups', array( 'title' => 'X', 'contact_id' => 99999999 ) );
$crmi_admin->validate_relations( 'followups', $crmi_data, $crmi_err, $followups, 0 );
crmi_assert( in_array( 'contact_id', $crmi_err->get_error_codes(), true ), 'no se puede vincular un contacto inexistente' );

$crmi_labels = $contacts->labels( array( $crmi_c1, $crmi_c2, 0, $crmi_c1 ) );
crmi_assert( 2 === count( $crmi_labels ) && "Pedro {$crmi_tag}" === $crmi_labels[ $crmi_c2 ], 'labels() devuelve nombres sin duplicados' );

$crmi_stats = CRMI_Admin::get_stats();
crmi_assert( $crmi_stats['followups_overdue'] >= 1 && $crmi_stats['opportunities'] >= 1 && $crmi_stats['contacts'] >= 3, 'estadísticas del panel' );

crmi_assert( $contacts->delete( $crmi_c1 ), 'se elimina un contacto' );
crmi_assert( 0 === (int) $followups->find( $crmi_f1 )['contact_id'] && $crmi_p1 === (int) $followups->find( $crmi_f1 )['property_id'], 'sus seguimientos se conservan y quedan desvinculados del contacto' );
crmi_assert( $properties->delete( $crmi_p1 ), 'se elimina una propiedad' );
crmi_assert( 0 === (int) $followups->find( $crmi_f3 )['property_id'] && $crmi_c2 === (int) $followups->find( $crmi_f3 )['contact_id'], 'sus seguimientos se conservan y quedan desvinculados de la propiedad' );
crmi_assert( ! $contacts->delete( $crmi_c1 ), 'eliminar dos veces devuelve false' );

WP_CLI::log( 'Interfaz de administración (renderizado y escape)' );
$crmi_xss = crmi_make( 'contacts', array( 'name' => 'temporal' ) );
$wpdb->update( $contacts->table(), array( 'name' => '<img src=x onerror=alert(1)>' ), array( 'id' => $crmi_xss ) );
$crmi_admin_user = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
wp_set_current_user( $crmi_admin_user[0]->ID );
set_current_screen( 'dashboard' );

$crmi_pages = array(
	'panel'        => function () use ( $crmi_admin ) {
		$crmi_admin->render_dashboard();
	},
	'contactos'    => function () use ( $crmi_admin ) {
		$crmi_admin->render_entity_page( 'contacts' );
	},
	'propiedades'  => function () use ( $crmi_admin ) {
		$crmi_admin->render_entity_page( 'properties' );
	},
	'seguimientos' => function () use ( $crmi_admin ) {
		$crmi_admin->render_entity_page( 'followups' );
	},
	'ajustes'      => function () use ( $crmi_admin ) {
		$crmi_admin->render_settings();
	},
);
foreach ( $crmi_pages as $crmi_name => $crmi_render ) {
	ob_start();
	$crmi_render();
	$crmi_html = ob_get_clean();
	crmi_assert( false !== strpos( $crmi_html, 'class="wrap crmi-wrap"' ), "la pantalla «{$crmi_name}» se renderiza" );
	crmi_assert( false === strpos( $crmi_html, '<img src=x' ), "la pantalla «{$crmi_name}» escapa los datos" );
}

$_GET = array(
	'action' => 'edit',
	'id'     => (string) $crmi_c2,
);
ob_start();
$crmi_admin->render_entity_page( 'contacts' );
$crmi_html = ob_get_clean();
crmi_assert( false !== strpos( $crmi_html, 'name="_wpnonce"' ) && false !== strpos( $crmi_html, "Pedro {$crmi_tag}" ), 'el formulario de edición incluye nonce y datos' );
crmi_assert( false !== strpos( $crmi_html, "Hecho {$crmi_tag}" ), 'el contacto muestra sus seguimientos relacionados' );
$_GET = array();

wp_set_current_user( 0 );
ob_start();
$crmi_admin->render_entity_page( 'contacts' );
crmi_assert( '' === ob_get_clean(), 'un usuario sin permisos no ve el listado' );

WP_CLI::log( 'Datos de ejemplo' );
$crmi_had_sample = CRMI_Sample_Data::exists();
if ( $crmi_had_sample ) {
	WP_CLI::log( '  (ya había datos de ejemplo; se omite esta sección)' );
} else {
	$crmi_before = $contacts->count() + $properties->count() + $followups->count();
	crmi_assert( CRMI_Sample_Data::create(), 'se crean datos de ejemplo bajo petición' );
	crmi_assert( ! CRMI_Sample_Data::create(), 'no se duplican los datos de ejemplo' );
	crmi_assert( $crmi_before + 8 === $contacts->count() + $properties->count() + $followups->count(), 'se añaden 8 registros de ejemplo' );
	crmi_assert( 8 === CRMI_Sample_Data::remove(), 'se eliminan exactamente los 8 registros de ejemplo' );
	crmi_assert( $crmi_before === $contacts->count() + $properties->count() + $followups->count() && ! CRMI_Sample_Data::exists(), 'los datos reales no se tocan' );
}

// Limpieza.
foreach ( $GLOBALS['crmi_t']['created'] as $crmi_entity => $crmi_list ) {
	foreach ( $crmi_list as $crmi_id ) {
		CRMI_Repository::for_entity( $crmi_entity )->delete( $crmi_id );
	}
}

WP_CLI::log( '' );
if ( $GLOBALS['crmi_t']['fail'] ) {
	WP_CLI::error( sprintf( '%d pruebas correctas, %d fallidas.', $GLOBALS['crmi_t']['pass'], $GLOBALS['crmi_t']['fail'] ) );
}
WP_CLI::success( sprintf( '%d pruebas correctas, 0 fallidas.', $GLOBALS['crmi_t']['pass'] ) );
