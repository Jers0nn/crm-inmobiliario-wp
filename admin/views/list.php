<?php
/**
 * Vista: listado genérico con búsqueda, filtros, orden y paginación.
 *
 * Variables disponibles: $entity, $def, $result, $search, $filters, $due,
 * $orderby, $order, $paged, $total_page, $base_args, $labels, $relations.
 *
 * @package CRM_Inmobiliario
 */

defined( 'ABSPATH' ) || exit;

$crmi_columns = CRMI_Admin::list_columns( $entity );
?>
<div class="wrap crmi-wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( $def['plural'] ); ?></h1>
	<a href="<?php echo esc_url( CRMI_Admin::page_url( $def['page'], array( 'action' => 'new' ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'Añadir nuevo', 'crm-inmobiliario-wp' ); ?></a>
	<hr class="wp-header-end">

	<?php $this->render_notice(); ?>

	<form method="get" class="crmi-filters" role="search">
		<input type="hidden" name="page" value="<?php echo esc_attr( $def['page'] ); ?>">

		<?php foreach ( $def['filters'] as $crmi_name ) : ?>
			<?php
			$crmi_field   = $def['fields'][ $crmi_name ];
			$crmi_options = 'relation' === $crmi_field['type'] ? $relations[ $crmi_name ] : $crmi_field['options'];
			$crmi_current = isset( $filters[ $crmi_name ] ) ? (string) $filters[ $crmi_name ] : '';
			?>
			<label class="screen-reader-text" for="crmi-filter-<?php echo esc_attr( $crmi_name ); ?>"><?php echo esc_html( $crmi_field['label'] ); ?></label>
			<select name="<?php echo esc_attr( $crmi_name ); ?>" id="crmi-filter-<?php echo esc_attr( $crmi_name ); ?>">
				<?php /* translators: %s: nombre del campo. */ ?>
				<option value=""><?php echo esc_html( sprintf( __( '%s: todos', 'crm-inmobiliario-wp' ), $crmi_field['label'] ) ); ?></option>
				<?php foreach ( $crmi_options as $crmi_value => $crmi_label ) : ?>
					<option value="<?php echo esc_attr( $crmi_value ); ?>" <?php selected( $crmi_current, (string) $crmi_value ); ?>><?php echo esc_html( $crmi_label ); ?></option>
				<?php endforeach; ?>
			</select>
		<?php endforeach; ?>

		<?php if ( 'followups' === $entity ) : ?>
			<label class="screen-reader-text" for="crmi-filter-due"><?php esc_html_e( 'Vencimiento', 'crm-inmobiliario-wp' ); ?></label>
			<select name="due" id="crmi-filter-due">
				<option value=""><?php esc_html_e( 'Fecha: todas', 'crm-inmobiliario-wp' ); ?></option>
				<option value="overdue" <?php selected( $due, 'overdue' ); ?>><?php esc_html_e( 'Vencidos', 'crm-inmobiliario-wp' ); ?></option>
				<option value="today" <?php selected( $due, 'today' ); ?>><?php esc_html_e( 'Hoy', 'crm-inmobiliario-wp' ); ?></option>
				<option value="week" <?php selected( $due, 'week' ); ?>><?php esc_html_e( 'Próximos 7 días', 'crm-inmobiliario-wp' ); ?></option>
			</select>
		<?php endif; ?>

		<label class="screen-reader-text" for="crmi-search"><?php esc_html_e( 'Buscar', 'crm-inmobiliario-wp' ); ?></label>
		<input type="search" id="crmi-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Buscar…', 'crm-inmobiliario-wp' ); ?>">
		<?php submit_button( __( 'Filtrar', 'crm-inmobiliario-wp' ), 'secondary', '', false ); ?>
		<?php if ( $base_args ) : ?>
			<a class="button-link" href="<?php echo esc_url( CRMI_Admin::page_url( $def['page'] ) ); ?>"><?php esc_html_e( 'Limpiar filtros', 'crm-inmobiliario-wp' ); ?></a>
		<?php endif; ?>
	</form>

	<p class="crmi-count">
		<?php
		/* translators: %s: número de registros. */
		echo esc_html( sprintf( _n( '%s registro', '%s registros', $result['total'], 'crm-inmobiliario-wp' ), number_format_i18n( $result['total'] ) ) );
		?>
	</p>

	<table class="widefat striped crmi-table">
		<thead>
			<tr>
				<?php foreach ( $crmi_columns as $crmi_col => $crmi_label ) : ?>
					<th scope="col">
						<?php if ( in_array( $crmi_col, $def['orderby'], true ) ) : ?>
							<?php
							$crmi_next = ( $orderby === $crmi_col && 'ASC' === $order ) ? 'desc' : 'asc';
							$crmi_url  = CRMI_Admin::page_url(
								$def['page'],
								array_merge(
									$base_args,
									array(
										'orderby' => $crmi_col,
										'order'   => $crmi_next,
									)
								)
							);
							?>
							<a href="<?php echo esc_url( $crmi_url ); ?>"><?php echo esc_html( $crmi_label ); ?>
								<?php if ( $orderby === $crmi_col ) : ?>
									<span aria-hidden="true"><?php echo 'ASC' === $order ? '&#9650;' : '&#9660;'; ?></span>
								<?php endif; ?>
							</a>
						<?php else : ?>
							<?php echo esc_html( $crmi_label ); ?>
						<?php endif; ?>
					</th>
				<?php endforeach; ?>
				<th scope="col" class="crmi-actions-col"><?php esc_html_e( 'Acciones', 'crm-inmobiliario-wp' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $result['items'] ) ) : ?>
				<tr>
					<td colspan="<?php echo esc_attr( count( $crmi_columns ) + 1 ); ?>" class="crmi-empty"><?php esc_html_e( 'No se han encontrado registros.', 'crm-inmobiliario-wp' ); ?></td>
				</tr>
			<?php endif; ?>
			<?php foreach ( $result['items'] as $crmi_row ) : ?>
				<tr>
					<?php foreach ( $crmi_columns as $crmi_col => $crmi_label ) : ?>
						<td data-label="<?php echo esc_attr( $crmi_label ); ?>"><?php echo CRMI_Admin::cell( $entity, $crmi_col, $crmi_row, $labels ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado en cell(). ?></td>
					<?php endforeach; ?>
					<td class="crmi-actions" data-label="<?php esc_attr_e( 'Acciones', 'crm-inmobiliario-wp' ); ?>">
						<?php
						$crmi_edit = CRMI_Admin::page_url(
							$def['page'],
							array(
								'action' => 'edit',
								'id'     => $crmi_row['id'],
							)
						);
						?>
						<a href="<?php echo esc_url( $crmi_edit ); ?>"><?php esc_html_e( 'Editar', 'crm-inmobiliario-wp' ); ?></a>
						|
						<a class="crmi-delete" href="<?php echo esc_url( CRMI_Admin::delete_url( $entity, $crmi_row['id'] ) ); ?>" data-crmi-confirm="<?php esc_attr_e( '¿Seguro que quieres eliminar este registro? Esta acción no se puede deshacer.', 'crm-inmobiliario-wp' ); ?>"><?php esc_html_e( 'Eliminar', 'crm-inmobiliario-wp' ); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $total_page > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg(
								'paged',
								'%#%',
								CRMI_Admin::page_url(
									$def['page'],
									array_merge(
										$base_args,
										array_filter(
											array(
												'orderby' => $orderby,
												'order'   => strtolower( $order ),
											)
										)
									)
								)
							),
							'format'    => '',
							'current'   => $paged,
							'total'     => $total_page,
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
						)
					)
				);
				?>
			</div>
		</div>
	<?php endif; ?>
</div>
