<?php
/**
 * Plugin Name:       CRM Inmobiliario Sencillo
 * Plugin URI:        https://github.com/Jers0nn/crm-inmobiliario-wp
 * Description:       CRM mínimo para agentes inmobiliarios y pequeñas inmobiliarias: contactos, propiedades y seguimientos comerciales dentro del escritorio de WordPress.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Jers0nn
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       crm-inmobiliario-wp
 * Domain Path:       /languages
 *
 * @package CRM_Inmobiliario
 */

defined( 'ABSPATH' ) || exit;

define( 'CRMI_VERSION', '0.1.0' );
define( 'CRMI_DB_VERSION', '1' );
define( 'CRMI_FILE', __FILE__ );
define( 'CRMI_DIR', plugin_dir_path( __FILE__ ) );
define( 'CRMI_URL', plugin_dir_url( __FILE__ ) );
define( 'CRMI_CAP', 'crmi_manage' );

require_once CRMI_DIR . 'includes/class-crmi-entities.php';
require_once CRMI_DIR . 'includes/class-crmi-repository.php';
require_once CRMI_DIR . 'includes/class-crmi-installer.php';
require_once CRMI_DIR . 'includes/class-crmi-sample-data.php';
require_once CRMI_DIR . 'includes/class-crmi-plugin.php';

register_activation_hook( __FILE__, array( 'CRMI_Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CRMI_Installer', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'CRMI_Plugin', 'init' ) );
