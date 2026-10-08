<?php
/**
 * Desinstalación del plugin.
 *
 * Política: los datos del CRM solo se eliminan si un administrador activó
 * expresamente "Eliminar todas las tablas y opciones del CRM cuando se
 * desinstale el plugin" en CRM Inmobiliario > Ajustes. En caso contrario
 * se conservan tablas y opciones para una posible reinstalación.
 *
 * @package CRM_Inmobiliario
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Elimina los datos del sitio actual si así se configuró.
 */
function crmi_uninstall_site() {
	global $wpdb;

	if ( 'yes' !== get_option( 'crmi_delete_data_on_uninstall', 'no' ) ) {
		return;
	}

	foreach ( array( 'crmi_followups', 'crmi_properties', 'crmi_contacts' ) as $crmi_table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$crmi_table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Nombres fijos de tablas propias.
	}

	delete_option( 'crmi_db_version' );
	delete_option( 'crmi_delete_data_on_uninstall' );
	delete_option( 'crmi_sample_ids' );

	foreach ( wp_roles()->role_objects as $crmi_role ) {
		$crmi_role->remove_cap( 'crmi_manage' );
	}
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $crmi_site_id ) {
		switch_to_blog( $crmi_site_id );
		crmi_uninstall_site();
		restore_current_blog();
	}
} else {
	crmi_uninstall_site();
}
