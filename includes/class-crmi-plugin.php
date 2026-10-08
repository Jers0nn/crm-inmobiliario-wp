<?php
/**
 * Arranque del plugin.
 *
 * @package CRM_Inmobiliario
 */

defined( 'ABSPATH' ) || exit;

/**
 * Punto de entrada: carga traducciones, actualiza el esquema si hace
 * falta y registra la interfaz de administración.
 */
class CRMI_Plugin {

	/**
	 * Inicializa el plugin (hook plugins_loaded).
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );

		CRMI_Installer::maybe_upgrade();

		if ( is_admin() ) {
			require_once CRMI_DIR . 'includes/class-crmi-admin.php';
			$admin = new CRMI_Admin();
			$admin->hooks();
		}
	}

	/**
	 * Carga las traducciones (hook init, como recomienda WordPress 6.7+).
	 */
	public static function load_textdomain() {
		load_plugin_textdomain( 'crm-inmobiliario-wp', false, dirname( plugin_basename( CRMI_FILE ) ) . '/languages' );
	}
}
