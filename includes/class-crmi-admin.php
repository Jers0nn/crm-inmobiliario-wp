<?php
/**
 * Interfaz de administración: menús, pantallas y procesamiento de formularios.
 *
 * @package CRM_Inmobiliario
 */

defined( 'ABSPATH' ) || exit;

/**
 * Controlador del escritorio de WordPress.
 */
class CRMI_Admin {

	/**
	 * Hooks de las pantallas registradas (hook_suffix => entidad|página).
	 *
	 * @var array
	 */
	protected $screens = array();

	/**
	 * Errores de validación del último envío.
	 *
	 * @var WP_Error|null
	 */
	protected $form_errors = null;

	/**
	 * Datos enviados que deben volver a mostrarse tras un error.
	 *
	 * @var array|null
	 */
	protected $form_data = null;

	/**
	 * Registra los hooks.
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_crmi_delete', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_crmi_settings', array( $this, 'handle_settings' ) );
	}

	/**
	 * Menú propio "CRM Inmobiliario" y submenús.
	 */
	public function register_menu() {
		$hook                   = add_menu_page(
			__( 'CRM Inmobiliario', 'crm-inmobiliario-wp' ),
			__( 'CRM Inmobiliario', 'crm-inmobiliario-wp' ),
			CRMI_CAP,
			'crmi',
			array( $this, 'render_dashboard' ),
			'dashicons-building',
			26
		);
		$this->screens[ $hook ] = 'dashboard';

		$hook = add_submenu_page( 'crmi', __( 'Panel', 'crm-inmobiliario-wp' ), __( 'Panel', 'crm-inmobiliario-wp' ), CRMI_CAP, 'crmi', array( $this, 'render_dashboard' ) );

		foreach ( CRMI_Entities::all() as $entity => $def ) {
			$hook                   = add_submenu_page(
				'crmi',
				$def['plural'],
				$def['plural'],
				CRMI_CAP,
				$def['page'],
				function () use ( $entity ) {
					$this->render_entity_page( $entity );
				}
			);
			$this->screens[ $hook ] = $entity;
			add_action(
				'load-' . $hook,
				function () use ( $entity ) {
					$this->maybe_handle_save( $entity );
				}
			);
		}

		$hook                   = add_submenu_page( 'crmi', __( 'Ajustes del CRM', 'crm-inmobiliario-wp' ), __( 'Ajustes', 'crm-inmobiliario-wp' ), 'manage_options', 'crmi-settings', array( $this, 'render_settings' ) );
		$this->screens[ $hook ] = 'settings';
	}

	/**
	 * Carga CSS/JS solo en las pantallas del plugin.
	 *
	 * @param string $hook_suffix Pantalla actual.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! isset( $this->screens[ $hook_suffix ] ) ) {
			return;
		}
		wp_enqueue_style( 'crmi-admin', CRMI_URL . 'assets/css/admin.css', array(), CRMI_VERSION );
		wp_enqueue_script( 'crmi-admin', CRMI_URL . 'assets/js/admin.js', array(), CRMI_VERSION, true );
	}

	/**
	 * URL de una pantalla del plugin.
	 *
	 * @param string $page Slug de la página.
	 * @param array  $args Argumentos de consulta.
	 * @return string
	 */
	public static function page_url( $page, array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * URL de borrado protegida con nonce.
	 *
	 * @param string $entity Entidad.
	 * @param int    $id     ID.
	 * @return string
	 */
	public static function delete_url( $entity, $id ) {
		$url = add_query_arg(
			array(
				'action' => 'crmi_delete',
				'entity' => $entity,
				'id'     => absint( $id ),
			),
			admin_url( 'admin-post.php' )
		);
		return wp_nonce_url( $url, 'crmi_delete_' . $entity . '_' . absint( $id ) );
	}

	/**
	 * Procesa el formulario de alta/edición antes de enviar cabeceras
	 * (hook load-{pantalla}) para poder redirigir tras guardar.
	 *
	 * @param string $entity Entidad.
	 */
	public function maybe_handle_save( $entity ) {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $_POST['crmi_save'] ) ) {
			return;
		}
		if ( ! current_user_can( CRMI_CAP ) ) {
			wp_die( esc_html__( 'No tienes permisos para realizar esta acción.', 'crm-inmobiliario-wp' ), 403 );
		}

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		check_admin_referer( 'crmi_save_' . $entity . '_' . $id );

		$repo = CRMI_Repository::for_entity( $entity );
		if ( $id && ! $repo->find( $id ) ) {
			wp_die( esc_html__( 'El registro no existe.', 'crm-inmobiliario-wp' ), 404 );
		}

		$raw                   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Se sanea campo a campo en CRMI_Entities::sanitize().
		list( $data, $errors ) = CRMI_Entities::sanitize( $entity, (array) $raw );
		$this->validate_relations( $entity, $data, $errors, $repo, $id );

		if ( $errors->has_errors() ) {
			$this->form_errors = $errors;
			$this->form_data   = $data;
			return;
		}

		if ( $id ) {
			$ok = $repo->update( $id, $data );
		} else {
			$id = $repo->insert( $data );
			$ok = (bool) $id;
		}

		$def = CRMI_Entities::get( $entity );
		if ( ! $ok ) {
			$this->form_errors = new WP_Error( 'db', __( 'No se pudo guardar el registro en la base de datos.', 'crm-inmobiliario-wp' ) );
			$this->form_data   = $data;
			return;
		}

		wp_safe_redirect(
			self::page_url(
				$def['page'],
				array(
					'action'   => 'edit',
					'id'       => $id,
					'crmi_msg' => 'saved',
				)
			)
		);
		exit;
	}

	/**
	 * Validaciones que requieren consultar la base de datos.
	 *
	 * @param string          $entity Entidad.
	 * @param array           $data   Datos saneados.
	 * @param WP_Error        $errors Errores acumulados.
	 * @param CRMI_Repository $repo   Repositorio de la entidad.
	 * @param int             $id     ID en edición (0 si es nuevo).
	 */
	public function validate_relations( $entity, array $data, WP_Error $errors, CRMI_Repository $repo, $id ) {
		$def = CRMI_Entities::get( $entity );
		foreach ( $def['fields'] as $name => $field ) {
			if ( ! empty( $field['unique'] ) && '' !== $data[ $name ] && $repo->exists( $name, $data[ $name ], $id ) ) {
				/* translators: %s: nombre del campo. */
				$errors->add( $name, sprintf( __( 'Ya existe otro registro con el mismo valor en «%s».', 'crm-inmobiliario-wp' ), $field['label'] ) );
			}
			if ( 'relation' === $field['type'] && $data[ $name ] && ! CRMI_Repository::for_entity( $field['relation'] )->find( $data[ $name ] ) ) {
				/* translators: %s: nombre del campo. */
				$errors->add( $name, sprintf( __( 'El registro seleccionado en «%s» no existe.', 'crm-inmobiliario-wp' ), $field['label'] ) );
			}
		}
	}

	/**
	 * Borra un registro (admin-post.php?action=crmi_delete).
	 */
	public function handle_delete() {
		if ( ! current_user_can( CRMI_CAP ) ) {
			wp_die( esc_html__( 'No tienes permisos para realizar esta acción.', 'crm-inmobiliario-wp' ), 403 );
		}
		$entity = isset( $_GET['entity'] ) ? sanitize_key( wp_unslash( $_GET['entity'] ) ) : '';
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$def    = CRMI_Entities::get( $entity );
		if ( ! $def || ! $id ) {
			wp_die( esc_html__( 'Solicitud no válida.', 'crm-inmobiliario-wp' ), 400 );
		}
		check_admin_referer( 'crmi_delete_' . $entity . '_' . $id );

		$ok = CRMI_Repository::for_entity( $entity )->delete( $id );
		wp_safe_redirect( self::page_url( $def['page'], array( 'crmi_msg' => $ok ? 'deleted' : 'error' ) ) );
		exit;
	}

	/**
	 * Guarda ajustes y gestiona los datos de ejemplo.
	 */
	public function handle_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos para realizar esta acción.', 'crm-inmobiliario-wp' ), 403 );
		}
		check_admin_referer( 'crmi_settings' );

		$task = isset( $_POST['crmi_task'] ) ? sanitize_key( wp_unslash( $_POST['crmi_task'] ) ) : '';
		$msg  = 'error';

		switch ( $task ) {
			case 'save':
				$delete = ! empty( $_POST['crmi_delete_data_on_uninstall'] ) ? 'yes' : 'no';
				update_option( 'crmi_delete_data_on_uninstall', $delete, false );
				$msg = 'settings_saved';
				break;

			case 'sample_create':
				if ( empty( $_POST['crmi_confirm_sample'] ) ) {
					$msg = 'sample_confirm';
					break;
				}
				$msg = CRMI_Sample_Data::create() ? 'sample_created' : 'sample_exists';
				break;

			case 'sample_remove':
				CRMI_Sample_Data::remove();
				$msg = 'sample_removed';
				break;
		}

		wp_safe_redirect( self::page_url( 'crmi-settings', array( 'crmi_msg' => $msg ) ) );
		exit;
	}

	/**
	 * Cifras del panel principal.
	 *
	 * @return array
	 */
	public static function get_stats() {
		$contacts   = CRMI_Repository::for_entity( 'contacts' );
		$properties = CRMI_Repository::for_entity( 'properties' );
		$followups  = CRMI_Repository::for_entity( 'followups' );

		return array(
			'contacts'             => $contacts->count(),
			'leads'                => $contacts->count( array( 'status' => CRMI_Entities::LEAD_STATUSES ) ),
			'opportunities'        => $contacts->count( array( 'status' => CRMI_Entities::OPPORTUNITY_STATUS ) ),
			'properties'           => $properties->count(),
			'properties_available' => $properties->count( array( 'status' => 'disponible' ) ),
			'followups_pending'    => $followups->count( array( 'status' => 'pendiente' ) ),
			'followups_overdue'    => $followups->count( array(), 'overdue' ),
		);
	}

	/**
	 * Mensaje de aviso tras una redirección (lista blanca).
	 */
	protected function render_notice() {
		$messages = array(
			'saved'          => array( 'success', __( 'Registro guardado correctamente.', 'crm-inmobiliario-wp' ) ),
			'deleted'        => array( 'success', __( 'Registro eliminado.', 'crm-inmobiliario-wp' ) ),
			'settings_saved' => array( 'success', __( 'Ajustes guardados.', 'crm-inmobiliario-wp' ) ),
			'sample_created' => array( 'success', __( 'Datos de ejemplo creados.', 'crm-inmobiliario-wp' ) ),
			'sample_exists'  => array( 'warning', __( 'Los datos de ejemplo ya existen.', 'crm-inmobiliario-wp' ) ),
			'sample_removed' => array( 'success', __( 'Datos de ejemplo eliminados.', 'crm-inmobiliario-wp' ) ),
			'sample_confirm' => array( 'warning', __( 'Marca la casilla de confirmación para crear los datos de ejemplo.', 'crm-inmobiliario-wp' ) ),
			'error'          => array( 'error', __( 'No se pudo completar la operación.', 'crm-inmobiliario-wp' ) ),
		);
		$key      = isset( $_GET['crmi_msg'] ) ? sanitize_key( wp_unslash( $_GET['crmi_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Solo muestra un mensaje.
		if ( isset( $messages[ $key ] ) ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $messages[ $key ][0] ),
				esc_html( $messages[ $key ][1] )
			);
		}
	}

	/**
	 * Pantalla del panel principal.
	 */
	public function render_dashboard() {
		if ( ! current_user_can( CRMI_CAP ) ) {
			return;
		}
		$stats    = self::get_stats();
		$upcoming = CRMI_Repository::for_entity( 'followups' )->query(
			array(
				'filters'  => array( 'status' => 'pendiente' ),
				'orderby'  => 'due_date',
				'order'    => 'ASC',
				'per_page' => 10,
			)
		);
		$recent   = CRMI_Repository::for_entity( 'contacts' )->query( array( 'per_page' => 5 ) );
		$labels   = $this->relation_labels( $upcoming['items'] );
		include CRMI_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Pantalla de listado o formulario de una entidad.
	 *
	 * @param string $entity Entidad.
	 */
	public function render_entity_page( $entity ) {
		if ( ! current_user_can( CRMI_CAP ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Navegación de solo lectura.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		// phpcs:enable

		if ( 'new' === $action || ( 'edit' === $action && $id ) || null !== $this->form_errors ) {
			$this->render_form( $entity, $id );
		} else {
			$this->render_list( $entity );
		}
	}

	/**
	 * Formulario de alta/edición.
	 *
	 * @param string $entity Entidad.
	 * @param int    $id     ID (0 para nuevo).
	 */
	protected function render_form( $entity, $id ) {
		$def  = CRMI_Entities::get( $entity );
		$repo = CRMI_Repository::for_entity( $entity );
		$item = $id ? $repo->find( $id ) : null;

		if ( $id && ! $item ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'El registro no existe o ha sido eliminado.', 'crm-inmobiliario-wp' ) . '</p></div></div>';
			return;
		}

		if ( null !== $this->form_data ) {
			$data = $this->form_data;
		} elseif ( $item ) {
			$data = $item;
		} else {
			$data = CRMI_Entities::defaults( $entity );
			// Permite precargar relaciones: ?action=new&contact_id=3.
			foreach ( $def['fields'] as $name => $field ) {
				if ( 'relation' === $field['type'] && isset( $_GET[ $name ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Solo precarga un valor del formulario.
					$data[ $name ] = absint( $_GET[ $name ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				}
			}
		}

		$errors    = $this->form_errors;
		$relations = array();
		foreach ( $def['fields'] as $name => $field ) {
			if ( 'relation' === $field['type'] ) {
				$rel_repo           = CRMI_Repository::for_entity( $field['relation'] );
				$relations[ $name ] = $rel_repo->options();
				// Garantiza que el valor actual aparezca aunque supere el límite del selector.
				$current = isset( $data[ $name ] ) ? absint( $data[ $name ] ) : 0;
				if ( $current && ! isset( $relations[ $name ][ $current ] ) ) {
					$relations[ $name ] += $rel_repo->labels( array( $current ) );
				}
			}
		}

		$related = null;
		if ( $item && in_array( $entity, array( 'contacts', 'properties' ), true ) ) {
			$column            = 'contacts' === $entity ? 'contact_id' : 'property_id';
			$related           = CRMI_Repository::for_entity( 'followups' )->query(
				array(
					'filters'  => array( $column => $id ),
					'orderby'  => 'due_date',
					'order'    => 'ASC',
					'per_page' => 50,
				)
			);
			$related['column'] = $column;
			$related['labels'] = $this->relation_labels( $related['items'] );
		}

		include CRMI_DIR . 'admin/views/form.php';
	}

	/**
	 * Listado con búsqueda, filtros, orden y paginación.
	 *
	 * @param string $entity Entidad.
	 */
	protected function render_list( $entity ) {
		$def = CRMI_Entities::get( $entity );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Filtros de solo lectura.
		$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';
		$order   = isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ? 'ASC' : 'DESC';
		$paged   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$due     = isset( $_GET['due'] ) ? sanitize_key( wp_unslash( $_GET['due'] ) ) : '';
		$filters = array();
		foreach ( $def['filters'] as $name ) {
			if ( ! isset( $_GET[ $name ] ) || '' === $_GET[ $name ] ) {
				continue;
			}
			$field = $def['fields'][ $name ];
			if ( 'relation' === $field['type'] ) {
				$value = absint( $_GET[ $name ] );
				if ( $value ) {
					$filters[ $name ] = $value;
				}
			} else {
				$value = sanitize_key( wp_unslash( $_GET[ $name ] ) );
				if ( isset( $field['options'][ $value ] ) ) {
					$filters[ $name ] = $value;
				}
			}
		}
		// phpcs:enable

		if ( ! in_array( $due, array( 'overdue', 'today', 'week' ), true ) ) {
			$due = '';
		}
		if ( ! in_array( $orderby, $def['orderby'], true ) ) {
			$orderby = 'followups' === $entity ? 'due_date' : 'created_at';
			$order   = 'followups' === $entity ? 'ASC' : 'DESC';
		}

		$per_page = 20;
		$result   = CRMI_Repository::for_entity( $entity )->query(
			array(
				'search'   => $search,
				'filters'  => $filters,
				'due'      => $due,
				'orderby'  => $orderby,
				'order'    => $order,
				'per_page' => $per_page,
				'page'     => $paged,
			)
		);

		$labels     = 'followups' === $entity ? $this->relation_labels( $result['items'] ) : array();
		$relations  = array();
		$base_args  = array_filter(
			array_merge(
				$filters,
				array(
					's'   => $search,
					'due' => $due,
				)
			)
		);
		$total_page = (int) ceil( $result['total'] / $per_page );
		if ( 'followups' === $entity ) {
			$relations = array(
				'contact_id'  => CRMI_Repository::for_entity( 'contacts' )->options(),
				'property_id' => CRMI_Repository::for_entity( 'properties' )->options(),
			);
		}

		include CRMI_DIR . 'admin/views/list.php';
	}

	/**
	 * Pantalla de ajustes.
	 */
	public function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$delete_on_uninstall = 'yes' === get_option( 'crmi_delete_data_on_uninstall', 'no' );
		$has_sample          = CRMI_Sample_Data::exists();
		include CRMI_DIR . 'admin/views/settings.php';
	}

	/**
	 * Nombres de contactos y títulos de propiedades de una lista de seguimientos.
	 *
	 * @param array $items Seguimientos.
	 * @return array { contacts: array, properties: array }
	 */
	protected function relation_labels( array $items ) {
		return array(
			'contacts'   => CRMI_Repository::for_entity( 'contacts' )->labels( wp_list_pluck( $items, 'contact_id' ) ),
			'properties' => CRMI_Repository::for_entity( 'properties' )->labels( wp_list_pluck( $items, 'property_id' ) ),
		);
	}

	/**
	 * Columnas del listado de cada entidad.
	 *
	 * @param string $entity Entidad.
	 * @return array columna => etiqueta
	 */
	public static function list_columns( $entity ) {
		$columns = array(
			'contacts'   => array(
				'name'       => __( 'Nombre', 'crm-inmobiliario-wp' ),
				'phone'      => __( 'Teléfono', 'crm-inmobiliario-wp' ),
				'email'      => __( 'Correo', 'crm-inmobiliario-wp' ),
				'source'     => __( 'Origen', 'crm-inmobiliario-wp' ),
				'status'     => __( 'Estado', 'crm-inmobiliario-wp' ),
				'created_at' => __( 'Creado', 'crm-inmobiliario-wp' ),
			),
			'properties' => array(
				'title'         => __( 'Título', 'crm-inmobiliario-wp' ),
				'reference'     => __( 'Referencia', 'crm-inmobiliario-wp' ),
				'operation'     => __( 'Operación', 'crm-inmobiliario-wp' ),
				'property_type' => __( 'Tipo', 'crm-inmobiliario-wp' ),
				'price'         => __( 'Precio', 'crm-inmobiliario-wp' ),
				'location'      => __( 'Ubicación', 'crm-inmobiliario-wp' ),
				'status'        => __( 'Estado', 'crm-inmobiliario-wp' ),
			),
			'followups'  => array(
				'title'       => __( 'Tarea', 'crm-inmobiliario-wp' ),
				'due_date'    => __( 'Fecha', 'crm-inmobiliario-wp' ),
				'contact_id'  => __( 'Contacto', 'crm-inmobiliario-wp' ),
				'property_id' => __( 'Propiedad', 'crm-inmobiliario-wp' ),
				'status'      => __( 'Estado', 'crm-inmobiliario-wp' ),
			),
		);
		return $columns[ $entity ];
	}

	/**
	 * HTML (ya escapado) de una celda del listado.
	 *
	 * @param string $entity Entidad.
	 * @param string $column Columna.
	 * @param array  $row    Registro.
	 * @param array  $labels Etiquetas de relaciones (ver relation_labels()).
	 * @return string
	 */
	public static function cell( $entity, $column, array $row, array $labels = array() ) {
		$def   = CRMI_Entities::get( $entity );
		$value = isset( $row[ $column ] ) ? $row[ $column ] : '';
		$field = isset( $def['fields'][ $column ] ) ? $def['fields'][ $column ] : array( 'type' => 'text' );

		if ( $column === $def['label'] ) {
			$url = self::page_url(
				$def['page'],
				array(
					'action' => 'edit',
					'id'     => $row['id'],
				)
			);
			return '<a class="row-title" href="' . esc_url( $url ) . '">' . esc_html( '' !== $value ? $value : __( '(sin título)', 'crm-inmobiliario-wp' ) ) . '</a>';
		}

		switch ( $field['type'] ) {
			case 'select':
				return '<span class="crmi-badge crmi-badge--' . esc_attr( $value ) . '">' . esc_html( CRMI_Entities::option_label( $entity, $column, $value ) ) . '</span>';

			case 'email':
				return '' === $value ? '&mdash;' : '<a href="' . esc_url( 'mailto:' . $value ) . '">' . esc_html( $value ) . '</a>';

			case 'tel':
				return '' === $value ? '&mdash;' : '<a href="' . esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $value ) ) . '">' . esc_html( $value ) . '</a>';

			case 'price':
				return self::format_price( $value );

			case 'date':
				return self::format_due_date( $value, isset( $row['status'] ) ? $row['status'] : '' );

			case 'relation':
				$group = 'contacts' === $field['relation'] ? 'contacts' : 'properties';
				if ( ! $value || ! isset( $labels[ $group ][ (int) $value ] ) ) {
					return '&mdash;';
				}
				$url = self::page_url(
					CRMI_Entities::get( $field['relation'] )['page'],
					array(
						'action' => 'edit',
						'id'     => (int) $value,
					)
				);
				return '<a href="' . esc_url( $url ) . '">' . esc_html( $labels[ $group ][ (int) $value ] ) . '</a>';
		}

		if ( 'created_at' === $column ) {
			return esc_html( mysql2date( get_option( 'date_format' ), $value ) );
		}
		return '' === (string) $value ? '&mdash;' : esc_html( $value );
	}

	/**
	 * Precio formateado según la configuración regional.
	 *
	 * @param mixed $value Precio.
	 * @return string HTML escapado.
	 */
	public static function format_price( $value ) {
		if ( null === $value || '' === $value ) {
			return '&mdash;';
		}
		$decimals = floor( (float) $value ) === (float) $value ? 0 : 2;
		/**
		 * Filtra el símbolo de moneda mostrado junto a los precios.
		 *
		 * @param string $symbol Símbolo (por defecto «€»).
		 */
		$symbol = (string) apply_filters( 'crmi_currency_symbol', '€' );
		return esc_html( trim( number_format_i18n( (float) $value, $decimals ) . ' ' . $symbol ) );
	}

	/**
	 * Fecha de seguimiento con aviso visual si está vencida.
	 *
	 * @param string|null $date   Fecha AAAA-MM-DD.
	 * @param string      $status Estado del seguimiento.
	 * @return string HTML escapado.
	 */
	public static function format_due_date( $date, $status = '' ) {
		if ( empty( $date ) ) {
			return '&mdash;';
		}
		$text  = mysql2date( get_option( 'date_format' ), $date );
		$today = current_time( 'Y-m-d' );
		if ( 'pendiente' === $status && $date < $today ) {
			return '<span class="crmi-overdue">' . esc_html( $text ) . ' &middot; ' . esc_html__( 'vencido', 'crm-inmobiliario-wp' ) . '</span>';
		}
		if ( 'pendiente' === $status && $date === $today ) {
			return '<span class="crmi-today">' . esc_html( $text ) . ' &middot; ' . esc_html__( 'hoy', 'crm-inmobiliario-wp' ) . '</span>';
		}
		return esc_html( $text );
	}
}
