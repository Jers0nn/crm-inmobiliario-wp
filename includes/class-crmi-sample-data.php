<?php
/**
 * Datos de ejemplo opcionales.
 *
 * Solo se crean cuando un administrador lo solicita expresamente desde
 * CRM Inmobiliario > Ajustes. Los IDs creados se guardan para poder
 * eliminarlos después sin tocar los datos reales.
 *
 * @package CRM_Inmobiliario
 */

defined( 'ABSPATH' ) || exit;

/**
 * Crea y elimina datos de demostración ficticios.
 */
class CRMI_Sample_Data {

	const OPTION = 'crmi_sample_ids';

	/**
	 * Indica si hay datos de ejemplo registrados.
	 *
	 * @return bool
	 */
	public static function exists() {
		$ids = get_option( self::OPTION );
		return ! empty( $ids );
	}

	/**
	 * Inserta los datos de ejemplo. No hace nada si ya existen.
	 *
	 * @return bool True si se crearon.
	 */
	public static function create() {
		if ( self::exists() ) {
			return false;
		}

		$ids = array(
			'contacts'   => array(),
			'properties' => array(),
			'followups'  => array(),
		);

		$contacts = array(
			array(
				'name'   => 'Ana Ejemplo',
				'phone'  => '600 000 001',
				'email'  => 'ana@example.com',
				'source' => 'web',
				'status' => 'nuevo',
				'notes'  => __( 'Contacto de ejemplo. Busca piso de 2 habitaciones.', 'crm-inmobiliario-wp' ),
			),
			array(
				'name'   => 'Luis Demo',
				'phone'  => '600 000 002',
				'email'  => 'luis@example.com',
				'source' => 'referido',
				'status' => 'negociacion',
				'notes'  => __( 'Contacto de ejemplo. Interesado en local comercial.', 'crm-inmobiliario-wp' ),
			),
			array(
				'name'   => 'Marta Prueba',
				'phone'  => '600 000 003',
				'email'  => 'marta@example.com',
				'source' => 'portal',
				'status' => 'cliente',
				'notes'  => __( 'Contacto de ejemplo.', 'crm-inmobiliario-wp' ),
			),
		);

		$properties = array(
			array(
				'title'         => __( 'Piso luminoso de ejemplo', 'crm-inmobiliario-wp' ),
				'reference'     => 'DEMO-001',
				'operation'     => 'venta',
				'property_type' => 'piso',
				'price'         => 185000,
				'location'      => __( 'Centro (ejemplo)', 'crm-inmobiliario-wp' ),
				'status'        => 'disponible',
				'description'   => __( 'Inmueble ficticio creado como dato de ejemplo.', 'crm-inmobiliario-wp' ),
			),
			array(
				'title'         => __( 'Local comercial de ejemplo', 'crm-inmobiliario-wp' ),
				'reference'     => 'DEMO-002',
				'operation'     => 'alquiler',
				'property_type' => 'local',
				'price'         => 950,
				'location'      => __( 'Zona comercial (ejemplo)', 'crm-inmobiliario-wp' ),
				'status'        => 'disponible',
				'description'   => __( 'Inmueble ficticio creado como dato de ejemplo.', 'crm-inmobiliario-wp' ),
			),
		);

		$contacts_repo   = CRMI_Repository::for_entity( 'contacts' );
		$properties_repo = CRMI_Repository::for_entity( 'properties' );
		$followups_repo  = CRMI_Repository::for_entity( 'followups' );

		foreach ( $contacts as $row ) {
			$ids['contacts'][] = (int) $contacts_repo->insert( $row );
		}
		foreach ( $properties as $row ) {
			$ids['properties'][] = (int) $properties_repo->insert( $row );
		}

		$today     = current_time( 'Y-m-d' );
		$followups = array(
			array(
				'title'       => __( 'Llamar para concertar visita', 'crm-inmobiliario-wp' ),
				'contact_id'  => $ids['contacts'][0],
				'property_id' => $ids['properties'][0],
				'due_date'    => $today,
				'status'      => 'pendiente',
				'notes'       => __( 'Seguimiento de ejemplo.', 'crm-inmobiliario-wp' ),
			),
			array(
				'title'       => __( 'Enviar propuesta de alquiler', 'crm-inmobiliario-wp' ),
				'contact_id'  => $ids['contacts'][1],
				'property_id' => $ids['properties'][1],
				'due_date'    => gmdate( 'Y-m-d', strtotime( $today . ' -2 days' ) ),
				'status'      => 'pendiente',
				'notes'       => __( 'Seguimiento de ejemplo (vencido).', 'crm-inmobiliario-wp' ),
			),
			array(
				'title'       => __( 'Firma completada', 'crm-inmobiliario-wp' ),
				'contact_id'  => $ids['contacts'][2],
				'property_id' => 0,
				'due_date'    => gmdate( 'Y-m-d', strtotime( $today . ' -10 days' ) ),
				'status'      => 'completada',
				'notes'       => __( 'Seguimiento de ejemplo.', 'crm-inmobiliario-wp' ),
			),
		);
		foreach ( $followups as $row ) {
			$ids['followups'][] = (int) $followups_repo->insert( $row );
		}

		update_option( self::OPTION, $ids, false );
		return true;
	}

	/**
	 * Elimina únicamente los registros creados como ejemplo.
	 *
	 * @return int Número de registros eliminados.
	 */
	public static function remove() {
		$ids = get_option( self::OPTION );
		if ( ! is_array( $ids ) ) {
			return 0;
		}
		$deleted = 0;
		foreach ( array( 'followups', 'properties', 'contacts' ) as $entity ) {
			if ( empty( $ids[ $entity ] ) ) {
				continue;
			}
			$repo = CRMI_Repository::for_entity( $entity );
			foreach ( (array) $ids[ $entity ] as $id ) {
				if ( $repo->delete( $id ) ) {
					++$deleted;
				}
			}
		}
		delete_option( self::OPTION );
		return $deleted;
	}
}
