<?php
/**
 * Acceso a datos genérico para las tablas del CRM.
 *
 * @package CRM_Inmobiliario
 */

defined( 'ABSPATH' ) || exit;

/**
 * Repositorio CRUD sobre una tabla propia.
 *
 * Los nombres de tabla y columna proceden siempre de CRMI_Entities (lista
 * blanca); los valores del usuario pasan siempre por $wpdb->prepare().
 */
class CRMI_Repository {

	/**
	 * Clave de entidad.
	 *
	 * @var string
	 */
	protected $entity;

	/**
	 * Definición de la entidad.
	 *
	 * @var array
	 */
	protected $def;

	/**
	 * Nombre completo de la tabla (con prefijo).
	 *
	 * @var string
	 */
	protected $table;

	/**
	 * Constructor.
	 *
	 * @param string $entity Clave de entidad (contacts, properties, followups).
	 * @throws InvalidArgumentException Si la entidad no existe.
	 */
	public function __construct( $entity ) {
		global $wpdb;
		$def = CRMI_Entities::get( $entity );
		if ( ! $def ) {
			throw new InvalidArgumentException( 'Entidad desconocida: ' . esc_html( $entity ) );
		}
		$this->entity = $entity;
		$this->def    = $def;
		$this->table  = $wpdb->prefix . $def['table'];
	}

	/**
	 * Atajo para obtener un repositorio.
	 *
	 * @param string $entity Clave de entidad.
	 * @return CRMI_Repository
	 */
	public static function for_entity( $entity ) {
		return new self( $entity );
	}

	/**
	 * Nombre completo de la tabla.
	 *
	 * @return string
	 */
	public function table() {
		return $this->table;
	}

	/**
	 * Obtiene un registro por ID.
	 *
	 * @param int $id ID.
	 * @return array|null
	 */
	public function find( $id ) {
		global $wpdb;
		$id = absint( $id );
		if ( ! $id ) {
			return null;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tabla propia; nombre de tabla de lista blanca.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ), ARRAY_A );
		return $row ? $row : null;
	}

	/**
	 * Inserta un registro ya saneado.
	 *
	 * @param array $data Datos limpios (ver CRMI_Entities::sanitize()).
	 * @return int|false ID insertado o false si falla.
	 */
	public function insert( array $data ) {
		global $wpdb;
		$now               = current_time( 'mysql' );
		$row               = $this->only_fields( $data );
		$row['created_at'] = $now;
		$row['updated_at'] = $now;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Tabla propia.
		$ok = $wpdb->insert( $this->table, $row, $this->formats( $row ) );
		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Actualiza un registro ya saneado.
	 *
	 * @param int   $id   ID.
	 * @param array $data Datos limpios.
	 * @return bool
	 */
	public function update( $id, array $data ) {
		global $wpdb;
		$row               = $this->only_fields( $data );
		$row['updated_at'] = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Tabla propia.
		$ok = $wpdb->update( $this->table, $row, array( 'id' => absint( $id ) ), $this->formats( $row ), array( '%d' ) );
		return false !== $ok;
	}

	/**
	 * Elimina un registro. Si es un contacto o una propiedad, los
	 * seguimientos asociados se conservan pero quedan desvinculados.
	 *
	 * @param int $id ID.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;
		$id = absint( $id );
		if ( ! $id ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Tabla propia.
		$ok = $wpdb->delete( $this->table, array( 'id' => $id ), array( '%d' ) );
		if ( ! $ok ) {
			return false;
		}

		$link_column = array(
			'contacts'   => 'contact_id',
			'properties' => 'property_id',
		);
		if ( isset( $link_column[ $this->entity ] ) ) {
			$followups = $wpdb->prefix . 'crmi_followups';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Tabla propia.
			$wpdb->update( $followups, array( $link_column[ $this->entity ] => 0 ), array( $link_column[ $this->entity ] => $id ), array( '%d' ), array( '%d' ) );
		}
		return true;
	}

	/**
	 * Busca registros con filtros, búsqueda de texto y paginación.
	 *
	 * Claves admitidas en $args: search (texto), filters (columna => valor,
	 * solo columnas de 'filters'), due (overdue|today|week, solo
	 * seguimientos), orderby (lista blanca), order (ASC|DESC),
	 * per_page (1-200) y page (desde 1).
	 *
	 * @param array $args Argumentos de búsqueda.
	 * @return array Claves «items» (array) y «total» (int).
	 */
	public function query( array $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'search'   => '',
				'filters'  => array(),
				'due'      => '',
				'orderby'  => 'created_at',
				'order'    => 'DESC',
				'per_page' => 20,
				'page'     => 1,
			)
		);

		list( $where_sql, $params ) = $this->build_where( $args );

		$orderby = in_array( $args['orderby'], $this->def['orderby'], true ) ? $args['orderby'] : 'created_at';
		$order   = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';
		$per     = max( 1, min( 200, absint( $args['per_page'] ) ) );
		$page    = max( 1, absint( $args['page'] ) );
		$offset  = ( $page - 1 ) * $per;

		// Las fechas vacías se ordenan siempre al final.
		$order_sql = 'due_date' === $orderby
			? "due_date IS NULL, due_date {$order}, id {$order}"
			: "{$orderby} {$order}, id {$order}";

		$count_sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$where_sql}";
		$items_sql = "SELECT * FROM {$this->table} WHERE {$where_sql} ORDER BY {$order_sql} LIMIT %d OFFSET %d";

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- SQL construido con columnas de lista blanca; valores con marcadores.
		$total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );
		$items = $wpdb->get_results( $wpdb->prepare( $items_sql, array_merge( $params, array( $per, $offset ) ) ), ARRAY_A );
		// phpcs:enable

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Cuenta registros que cumplen unos filtros simples.
	 *
	 * @param array  $filters Columna => valor o lista de valores.
	 * @param string $due    Solo seguimientos: overdue|today|week.
	 * @return int
	 */
	public function count( array $filters = array(), $due = '' ) {
		global $wpdb;
		list( $where_sql, $params ) = $this->build_where(
			array(
				'search'  => '',
				'filters' => $filters,
				'due'     => $due,
			),
			true
		);
		$sql                        = "SELECT COUNT(*) FROM {$this->table} WHERE {$where_sql}";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Columnas de lista blanca; valores con marcadores.
		return (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_var( $sql ) );
	}

	/**
	 * Lista simple id => etiqueta para selectores de relación.
	 *
	 * @param int $limit Máximo de registros.
	 * @return array
	 */
	public function options( $limit = 500 ) {
		global $wpdb;
		$label = $this->def['label'];
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tabla y columna de lista blanca.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, {$label} AS label FROM {$this->table} ORDER BY {$label} ASC LIMIT %d", absint( $limit ) ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['id'] ] = $row['label'];
		}
		return $out;
	}

	/**
	 * Etiquetas (nombre o título) de varios registros en una sola consulta.
	 *
	 * @param int[] $ids IDs.
	 * @return array id => etiqueta
	 */
	public function labels( array $ids ) {
		global $wpdb;
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		if ( ! $ids ) {
			return array();
		}
		$label        = $this->def['label'];
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Tabla y columna de lista blanca; marcadores generados.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, {$label} AS label FROM {$this->table} WHERE id IN ({$placeholders})", $ids ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['id'] ] = $row['label'];
		}
		return $out;
	}

	/**
	 * Comprueba si ya existe otro registro con el mismo valor en una columna.
	 *
	 * @param string $field      Columna (debe estar definida en la entidad).
	 * @param string $value      Valor.
	 * @param int    $exclude_id ID a excluir (edición).
	 * @return bool
	 */
	public function exists( $field, $value, $exclude_id = 0 ) {
		global $wpdb;
		if ( ! isset( $this->def['fields'][ $field ] ) ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tabla y columna de lista blanca.
		$found = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->table} WHERE {$field} = %s AND id <> %d LIMIT 1", $value, absint( $exclude_id ) ) );
		return ! empty( $found );
	}

	/**
	 * Construye la cláusula WHERE.
	 *
	 * @param array $args       Argumentos de query().
	 * @param bool  $allow_list Permite listas de valores en filtros (uso interno).
	 * @return array { 0: string SQL, 1: array parámetros }
	 */
	protected function build_where( array $args, $allow_list = false ) {
		global $wpdb;
		$where  = array( '1=1' );
		$params = array();

		foreach ( (array) $args['filters'] as $column => $value ) {
			$valid_column = in_array( $column, $this->def['filters'], true ) || ( $allow_list && isset( $this->def['fields'][ $column ] ) );
			if ( ! $valid_column || '' === $value || null === $value ) {
				continue;
			}
			$format = 'relation' === $this->def['fields'][ $column ]['type'] ? '%d' : '%s';
			if ( is_array( $value ) ) {
				if ( ! $allow_list || ! $value ) {
					continue;
				}
				$where[] = $column . ' IN (' . implode( ',', array_fill( 0, count( $value ), $format ) ) . ')';
				$params  = array_merge( $params, array_values( $value ) );
			} else {
				$where[]  = "{$column} = {$format}";
				$params[] = $value;
			}
		}

		$search = trim( (string) $args['search'] );
		if ( '' !== $search ) {
			$like  = '%' . $wpdb->esc_like( $search ) . '%';
			$parts = array();
			foreach ( $this->def['search'] as $column ) {
				$parts[]  = "{$column} LIKE %s";
				$params[] = $like;
			}
			$where[] = '(' . implode( ' OR ', $parts ) . ')';
		}

		if ( 'followups' === $this->entity && '' !== (string) $args['due'] ) {
			$today = current_time( 'Y-m-d' );
			switch ( $args['due'] ) {
				case 'overdue':
					$where[]  = "status = 'pendiente' AND due_date IS NOT NULL AND due_date < %s";
					$params[] = $today;
					break;
				case 'today':
					$where[]  = 'due_date = %s';
					$params[] = $today;
					break;
				case 'week':
					$where[]  = 'due_date BETWEEN %s AND %s';
					$params[] = $today;
					$params[] = gmdate( 'Y-m-d', strtotime( $today . ' +7 days' ) );
					break;
			}
		}

		return array( implode( ' AND ', $where ), $params );
	}

	/**
	 * Filtra un array dejando solo columnas definidas.
	 *
	 * @param array $data Datos.
	 * @return array
	 */
	protected function only_fields( array $data ) {
		return array_intersect_key( $data, $this->def['fields'] );
	}

	/**
	 * Formatos de $wpdb para cada columna de una fila.
	 *
	 * @param array $row Fila.
	 * @return array
	 */
	protected function formats( array $row ) {
		$formats = array();
		foreach ( $row as $column => $value ) {
			$type = isset( $this->def['fields'][ $column ] ) ? $this->def['fields'][ $column ]['type'] : 'text';
			if ( 'relation' === $type ) {
				$formats[] = '%d';
			} elseif ( 'price' === $type ) {
				$formats[] = '%f';
			} else {
				$formats[] = '%s';
			}
		}
		return $formats;
	}
}
