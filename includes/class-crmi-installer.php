<?php
/**
 * Activación, desactivación y actualización del esquema.
 *
 * @package CRM_Inmobiliario
 */

defined( 'ABSPATH' ) || exit;

/**
 * Gestiona tablas, capacidades y opciones del plugin.
 */
class CRMI_Installer {

	/**
	 * Activación: crea o actualiza tablas y concede la capacidad.
	 *
	 * No inserta datos de ejemplo: eso solo ocurre si un administrador
	 * lo solicita expresamente desde Ajustes.
	 */
	public static function activate() {
		self::install();
	}

	/**
	 * Desactivación: no borra nada. Los datos se conservan.
	 */
	public static function deactivate() {
		// Intencionadamente vacío: desactivar nunca elimina datos.
	}

	/**
	 * Ejecuta la instalación si la versión de esquema guardada es distinta.
	 *
	 * Cubre actualizaciones del plugin sin reactivación y sitios de una red
	 * multisitio en los que el hook de activación no se ejecutó.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'crmi_db_version' ) !== CRMI_DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Crea o actualiza tablas y concede la capacidad al administrador.
	 */
	public static function install() {
		self::create_tables();

		$role = get_role( 'administrator' );
		if ( $role && ! $role->has_cap( CRMI_CAP ) ) {
			$role->add_cap( CRMI_CAP );
		}

		update_option( 'crmi_db_version', CRMI_DB_VERSION, true );
		add_option( 'crmi_delete_data_on_uninstall', 'no', '', false );
	}

	/**
	 * Crea las tablas con dbDelta().
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix;

		dbDelta(
			"CREATE TABLE {$p}crmi_contacts (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(190) NOT NULL DEFAULT '',
  phone varchar(50) NOT NULL DEFAULT '',
  email varchar(190) NOT NULL DEFAULT '',
  source varchar(20) NOT NULL DEFAULT 'web',
  status varchar(20) NOT NULL DEFAULT 'nuevo',
  notes text NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY status (status),
  KEY email (email),
  KEY created_at (created_at)
) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$p}crmi_properties (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  title varchar(190) NOT NULL DEFAULT '',
  reference varchar(50) NOT NULL DEFAULT '',
  operation varchar(20) NOT NULL DEFAULT 'venta',
  property_type varchar(20) NOT NULL DEFAULT 'piso',
  price decimal(12,2) NULL DEFAULT NULL,
  location varchar(190) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'disponible',
  description text NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY reference (reference),
  KEY status (status),
  KEY operation (operation)
) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$p}crmi_followups (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  title varchar(190) NOT NULL DEFAULT '',
  contact_id bigint(20) unsigned NOT NULL DEFAULT 0,
  property_id bigint(20) unsigned NOT NULL DEFAULT 0,
  due_date date NULL DEFAULT NULL,
  status varchar(20) NOT NULL DEFAULT 'pendiente',
  notes text NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY contact_id (contact_id),
  KEY property_id (property_id),
  KEY status_due (status,due_date)
) {$charset};"
		);
	}

	/**
	 * Nombres completos de las tablas del plugin.
	 *
	 * @return string[]
	 */
	public static function tables() {
		global $wpdb;
		return array(
			$wpdb->prefix . 'crmi_followups',
			$wpdb->prefix . 'crmi_properties',
			$wpdb->prefix . 'crmi_contacts',
		);
	}
}
