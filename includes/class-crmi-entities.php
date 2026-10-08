<?php
/**
 * Definición de entidades (contactos, propiedades, seguimientos):
 * campos, opciones permitidas, saneamiento y validación.
 *
 * @package CRM_Inmobiliario
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registro central de entidades del CRM.
 *
 * Toda la lista blanca de columnas, valores de selección y reglas de
 * validación vive aquí para que el repositorio y la interfaz compartan
 * una única fuente de verdad.
 */
class CRMI_Entities {

	/**
	 * Estados de contacto que cuentan como "lead" en el panel.
	 *
	 * @var string[]
	 */
	const LEAD_STATUSES = array( 'nuevo', 'contactado', 'cualificado' );

	/**
	 * Estado de contacto que cuenta como "oportunidad" en el panel.
	 *
	 * @var string
	 */
	const OPPORTUNITY_STATUS = 'negociacion';

	/**
	 * Claves de entidad válidas.
	 *
	 * @return string[]
	 */
	public static function keys() {
		return array( 'contacts', 'properties', 'followups' );
	}

	/**
	 * Devuelve la definición de una entidad o null si no existe.
	 *
	 * @param string $key Clave de la entidad.
	 * @return array|null
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Definiciones de todas las entidades.
	 *
	 * @return array
	 */
	public static function all() {
		return array(
			'contacts'   => array(
				'table'    => 'crmi_contacts',
				'singular' => __( 'Contacto', 'crm-inmobiliario-wp' ),
				'plural'   => __( 'Contactos', 'crm-inmobiliario-wp' ),
				'page'     => 'crmi-contacts',
				'label'    => 'name',
				'fields'   => array(
					'name'   => array(
						'label'    => __( 'Nombre', 'crm-inmobiliario-wp' ),
						'type'     => 'text',
						'required' => true,
						'max'      => 190,
					),
					'phone'  => array(
						'label' => __( 'Teléfono', 'crm-inmobiliario-wp' ),
						'type'  => 'tel',
						'max'   => 50,
					),
					'email'  => array(
						'label' => __( 'Correo electrónico', 'crm-inmobiliario-wp' ),
						'type'  => 'email',
						'max'   => 190,
					),
					'source' => array(
						'label'   => __( 'Origen del lead', 'crm-inmobiliario-wp' ),
						'type'    => 'select',
						'options' => self::contact_sources(),
						'default' => 'web',
					),
					'status' => array(
						'label'   => __( 'Estado', 'crm-inmobiliario-wp' ),
						'type'    => 'select',
						'options' => self::contact_statuses(),
						'default' => 'nuevo',
					),
					'notes'  => array(
						'label' => __( 'Notas', 'crm-inmobiliario-wp' ),
						'type'  => 'textarea',
					),
				),
				'search'   => array( 'name', 'email', 'phone' ),
				'filters'  => array( 'status', 'source' ),
				'orderby'  => array( 'name', 'status', 'source', 'created_at' ),
			),
			'properties' => array(
				'table'    => 'crmi_properties',
				'singular' => __( 'Propiedad', 'crm-inmobiliario-wp' ),
				'plural'   => __( 'Propiedades', 'crm-inmobiliario-wp' ),
				'page'     => 'crmi-properties',
				'label'    => 'title',
				'fields'   => array(
					'title'         => array(
						'label'    => __( 'Título', 'crm-inmobiliario-wp' ),
						'type'     => 'text',
						'required' => true,
						'max'      => 190,
					),
					'reference'     => array(
						'label'  => __( 'Referencia', 'crm-inmobiliario-wp' ),
						'type'   => 'text',
						'max'    => 50,
						'unique' => true,
					),
					'operation'     => array(
						'label'   => __( 'Operación', 'crm-inmobiliario-wp' ),
						'type'    => 'select',
						'options' => self::operations(),
						'default' => 'venta',
					),
					'property_type' => array(
						'label'   => __( 'Tipo de inmueble', 'crm-inmobiliario-wp' ),
						'type'    => 'select',
						'options' => self::property_types(),
						'default' => 'piso',
					),
					'price'         => array(
						'label' => __( 'Precio', 'crm-inmobiliario-wp' ),
						'type'  => 'price',
					),
					'location'      => array(
						'label' => __( 'Ubicación', 'crm-inmobiliario-wp' ),
						'type'  => 'text',
						'max'   => 190,
					),
					'status'        => array(
						'label'   => __( 'Estado', 'crm-inmobiliario-wp' ),
						'type'    => 'select',
						'options' => self::property_statuses(),
						'default' => 'disponible',
					),
					'description'   => array(
						'label' => __( 'Descripción', 'crm-inmobiliario-wp' ),
						'type'  => 'textarea',
					),
				),
				'search'   => array( 'title', 'reference', 'location' ),
				'filters'  => array( 'operation', 'property_type', 'status' ),
				'orderby'  => array( 'title', 'reference', 'price', 'status', 'created_at' ),
			),
			'followups'  => array(
				'table'    => 'crmi_followups',
				'singular' => __( 'Seguimiento', 'crm-inmobiliario-wp' ),
				'plural'   => __( 'Seguimientos', 'crm-inmobiliario-wp' ),
				'page'     => 'crmi-followups',
				'label'    => 'title',
				'fields'   => array(
					'title'       => array(
						'label'    => __( 'Tarea', 'crm-inmobiliario-wp' ),
						'type'     => 'text',
						'required' => true,
						'max'      => 190,
					),
					'contact_id'  => array(
						'label'    => __( 'Contacto', 'crm-inmobiliario-wp' ),
						'type'     => 'relation',
						'relation' => 'contacts',
					),
					'property_id' => array(
						'label'    => __( 'Propiedad', 'crm-inmobiliario-wp' ),
						'type'     => 'relation',
						'relation' => 'properties',
					),
					'due_date'    => array(
						'label' => __( 'Fecha de seguimiento', 'crm-inmobiliario-wp' ),
						'type'  => 'date',
					),
					'status'      => array(
						'label'   => __( 'Estado', 'crm-inmobiliario-wp' ),
						'type'    => 'select',
						'options' => self::followup_statuses(),
						'default' => 'pendiente',
					),
					'notes'       => array(
						'label' => __( 'Notas', 'crm-inmobiliario-wp' ),
						'type'  => 'textarea',
					),
				),
				'search'   => array( 'title', 'notes' ),
				'filters'  => array( 'status', 'contact_id', 'property_id' ),
				'orderby'  => array( 'title', 'due_date', 'status', 'created_at' ),
			),
		);
	}

	/**
	 * Orígenes de lead.
	 *
	 * @return array
	 */
	public static function contact_sources() {
		return array(
			'web'      => __( 'Web', 'crm-inmobiliario-wp' ),
			'telefono' => __( 'Teléfono', 'crm-inmobiliario-wp' ),
			'email'    => __( 'Correo electrónico', 'crm-inmobiliario-wp' ),
			'referido' => __( 'Referido', 'crm-inmobiliario-wp' ),
			'portal'   => __( 'Portal inmobiliario', 'crm-inmobiliario-wp' ),
			'redes'    => __( 'Redes sociales', 'crm-inmobiliario-wp' ),
			'oficina'  => __( 'Oficina / cartel', 'crm-inmobiliario-wp' ),
			'otro'     => __( 'Otro', 'crm-inmobiliario-wp' ),
		);
	}

	/**
	 * Estados de contacto (embudo comercial).
	 *
	 * @return array
	 */
	public static function contact_statuses() {
		return array(
			'nuevo'       => __( 'Nuevo', 'crm-inmobiliario-wp' ),
			'contactado'  => __( 'Contactado', 'crm-inmobiliario-wp' ),
			'cualificado' => __( 'Cualificado', 'crm-inmobiliario-wp' ),
			'negociacion' => __( 'En negociación', 'crm-inmobiliario-wp' ),
			'cliente'     => __( 'Cliente', 'crm-inmobiliario-wp' ),
			'descartado'  => __( 'Descartado', 'crm-inmobiliario-wp' ),
		);
	}

	/**
	 * Tipos de operación.
	 *
	 * @return array
	 */
	public static function operations() {
		return array(
			'venta'    => __( 'Venta', 'crm-inmobiliario-wp' ),
			'alquiler' => __( 'Alquiler', 'crm-inmobiliario-wp' ),
		);
	}

	/**
	 * Tipos de inmueble.
	 *
	 * @return array
	 */
	public static function property_types() {
		return array(
			'piso'    => __( 'Piso / apartamento', 'crm-inmobiliario-wp' ),
			'casa'    => __( 'Casa / chalet', 'crm-inmobiliario-wp' ),
			'local'   => __( 'Local comercial', 'crm-inmobiliario-wp' ),
			'oficina' => __( 'Oficina', 'crm-inmobiliario-wp' ),
			'terreno' => __( 'Terreno', 'crm-inmobiliario-wp' ),
			'garaje'  => __( 'Garaje / trastero', 'crm-inmobiliario-wp' ),
			'otro'    => __( 'Otro', 'crm-inmobiliario-wp' ),
		);
	}

	/**
	 * Estados de propiedad.
	 *
	 * @return array
	 */
	public static function property_statuses() {
		return array(
			'disponible' => __( 'Disponible', 'crm-inmobiliario-wp' ),
			'reservada'  => __( 'Reservada', 'crm-inmobiliario-wp' ),
			'vendida'    => __( 'Vendida', 'crm-inmobiliario-wp' ),
			'alquilada'  => __( 'Alquilada', 'crm-inmobiliario-wp' ),
			'retirada'   => __( 'Retirada', 'crm-inmobiliario-wp' ),
		);
	}

	/**
	 * Estados de seguimiento.
	 *
	 * @return array
	 */
	public static function followup_statuses() {
		return array(
			'pendiente'  => __( 'Pendiente', 'crm-inmobiliario-wp' ),
			'completada' => __( 'Completada', 'crm-inmobiliario-wp' ),
			'cancelada'  => __( 'Cancelada', 'crm-inmobiliario-wp' ),
		);
	}

	/**
	 * Etiqueta legible de un valor de selección.
	 *
	 * @param string $entity Clave de entidad.
	 * @param string $field  Campo.
	 * @param string $value  Valor almacenado.
	 * @return string
	 */
	public static function option_label( $entity, $field, $value ) {
		$def = self::get( $entity );
		if ( isset( $def['fields'][ $field ]['options'][ $value ] ) ) {
			return $def['fields'][ $field ]['options'][ $value ];
		}
		return (string) $value;
	}

	/**
	 * Valores por defecto de un registro nuevo.
	 *
	 * @param string $entity Clave de entidad.
	 * @return array
	 */
	public static function defaults( $entity ) {
		$def  = self::get( $entity );
		$data = array();
		foreach ( $def['fields'] as $name => $field ) {
			if ( isset( $field['default'] ) ) {
				$data[ $name ] = $field['default'];
			} elseif ( in_array( $field['type'], array( 'relation' ), true ) ) {
				$data[ $name ] = 0;
			} elseif ( 'price' === $field['type'] || 'date' === $field['type'] ) {
				$data[ $name ] = null;
			} else {
				$data[ $name ] = '';
			}
		}
		return $data;
	}

	/**
	 * Sanea y valida datos de entrada (por ejemplo, $_POST sin barras).
	 *
	 * Solo se aceptan los campos definidos; cualquier otra clave se ignora.
	 *
	 * @param string $entity Clave de entidad.
	 * @param array  $raw    Datos sin procesar (ya pasados por wp_unslash).
	 * @return array { 0: array datos limpios, 1: WP_Error con los errores (vacío si es válido) }
	 */
	public static function sanitize( $entity, array $raw ) {
		$def    = self::get( $entity );
		$data   = array();
		$errors = new WP_Error();

		foreach ( $def['fields'] as $name => $field ) {
			$value = isset( $raw[ $name ] ) && is_scalar( $raw[ $name ] ) ? (string) $raw[ $name ] : '';

			switch ( $field['type'] ) {
				case 'email':
					$typed = trim( $value );
					$value = sanitize_email( $typed );
					if ( '' !== $typed && ( '' === $value || ! is_email( $value ) ) ) {
						/* translators: %s: nombre del campo. */
						$errors->add( $name, sprintf( __( 'El campo «%s» no es una dirección de correo válida.', 'crm-inmobiliario-wp' ), $field['label'] ) );
					}
					break;

				case 'tel':
					// Solo dígitos, espacios, +, -, paréntesis y puntos.
					$value = trim( preg_replace( '/[^0-9+\-().\s]/', '', sanitize_text_field( $value ) ) );
					break;

				case 'textarea':
					$value = sanitize_textarea_field( $value );
					break;

				case 'select':
					$value = sanitize_key( $value );
					if ( ! isset( $field['options'][ $value ] ) ) {
						if ( '' !== $value ) {
							/* translators: %s: nombre del campo. */
							$errors->add( $name, sprintf( __( 'El valor del campo «%s» no es válido.', 'crm-inmobiliario-wp' ), $field['label'] ) );
						}
						$value = $field['default'];
					}
					break;

				case 'relation':
					$value = absint( $value );
					break;

				case 'price':
					$value = self::parse_price( $value );
					if ( false === $value ) {
						/* translators: %s: nombre del campo. */
						$errors->add( $name, sprintf( __( 'El campo «%s» debe ser un número positivo.', 'crm-inmobiliario-wp' ), $field['label'] ) );
						$value = null;
					}
					break;

				case 'date':
					$value = trim( sanitize_text_field( $value ) );
					if ( '' === $value ) {
						$value = null;
					} elseif ( ! self::is_valid_date( $value ) ) {
						/* translators: %s: nombre del campo. */
						$errors->add( $name, sprintf( __( 'El campo «%s» debe ser una fecha válida (AAAA-MM-DD).', 'crm-inmobiliario-wp' ), $field['label'] ) );
						$value = null;
					}
					break;

				default:
					$value = sanitize_text_field( $value );
			}

			if ( is_string( $value ) && ! empty( $field['max'] ) && mb_strlen( $value ) > $field['max'] ) {
				$value = mb_substr( $value, 0, $field['max'] );
			}

			if ( ! empty( $field['required'] ) && ( null === $value || '' === $value ) ) {
				/* translators: %s: nombre del campo. */
				$errors->add( $name, sprintf( __( 'El campo «%s» es obligatorio.', 'crm-inmobiliario-wp' ), $field['label'] ) );
			}

			$data[ $name ] = $value;
		}

		return array( $data, $errors );
	}

	/**
	 * Convierte un precio escrito por el usuario en número.
	 *
	 * Acepta "250000", "250.000", "250.000,50", "250,000.50" y "1.250,5 €".
	 *
	 * @param string $value Texto introducido.
	 * @return float|null|false Número, null si está vacío, false si no es válido.
	 */
	public static function parse_price( $value ) {
		$value = preg_replace( '/[^0-9.,\-]/', '', (string) $value );
		if ( '' === $value ) {
			return null;
		}
		if ( false !== strpos( $value, '-' ) ) {
			return false;
		}

		$last_dot   = strrpos( $value, '.' );
		$last_comma = strrpos( $value, ',' );

		if ( false !== $last_dot && false !== $last_comma ) {
			// El último separador es el decimal.
			$decimal = $last_dot > $last_comma ? '.' : ',';
			$thousan = '.' === $decimal ? ',' : '.';
			$value   = str_replace( $thousan, '', $value );
			$value   = str_replace( $decimal, '.', $value );
		} elseif ( false !== $last_comma ) {
			// Solo comas: decimal si hay una con 1-2 cifras detrás; si no, miles.
			$value = ( 1 === substr_count( $value, ',' ) && strlen( $value ) - $last_comma - 1 <= 2 )
				? str_replace( ',', '.', $value )
				: str_replace( ',', '', $value );
		} elseif ( false !== $last_dot ) {
			// Solo puntos: decimal si hay uno con 1-2 cifras detrás; si no, miles.
			if ( ! ( 1 === substr_count( $value, '.' ) && strlen( $value ) - $last_dot - 1 <= 2 ) ) {
				$value = str_replace( '.', '', $value );
			}
		}

		if ( ! is_numeric( $value ) ) {
			return false;
		}
		$number = round( (float) $value, 2 );
		if ( $number > 9999999999.99 ) {
			return false;
		}
		return $number;
	}

	/**
	 * Comprueba si una cadena es una fecha AAAA-MM-DD real.
	 *
	 * @param string $value Fecha.
	 * @return bool
	 */
	public static function is_valid_date( $value ) {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $value, $m ) ) {
			return false;
		}
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
	}
}
