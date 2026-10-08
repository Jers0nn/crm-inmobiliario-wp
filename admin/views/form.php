<?php
/**
 * Vista: formulario genérico de alta/edición.
 *
 * Variables disponibles: $entity, $def, $id, $item, $data, $errors,
 * $relations, $related.
 *
 * @package CRM_Inmobiliario
 */

defined( 'ABSPATH' ) || exit;

$crmi_title = $item
	/* translators: %s: nombre de la entidad en singular. */
	? sprintf( __( 'Editar %s', 'crm-inmobiliario-wp' ), mb_strtolower( $def['singular'] ) )
	/* translators: %s: nombre de la entidad en singular. */
	: sprintf( __( 'Añadir %s', 'crm-inmobiliario-wp' ), mb_strtolower( $def['singular'] ) );
?>
<div class="wrap crmi-wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( $crmi_title ); ?></h1>
	<a href="<?php echo esc_url( CRMI_Admin::page_url( $def['page'] ) ); ?>" class="page-title-action"><?php esc_html_e( 'Volver al listado', 'crm-inmobiliario-wp' ); ?></a>
	<hr class="wp-header-end">

	<?php $this->render_notice(); ?>

	<?php if ( $errors instanceof WP_Error && $errors->has_errors() ) : ?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Revisa los siguientes datos:', 'crm-inmobiliario-wp' ); ?></p>
			<ul class="crmi-error-list">
				<?php foreach ( $errors->get_error_messages() as $crmi_message ) : ?>
					<li><?php echo esc_html( $crmi_message ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<form method="post" class="crmi-form" novalidate>
		<?php wp_nonce_field( 'crmi_save_' . $entity . '_' . (int) $id ); ?>
		<input type="hidden" name="id" value="<?php echo esc_attr( (int) $id ); ?>">
		<input type="hidden" name="crmi_save" value="1">

		<table class="form-table" role="presentation">
			<tbody>
				<?php foreach ( $def['fields'] as $crmi_name => $crmi_field ) : ?>
					<?php
					$crmi_id    = 'crmi-' . $crmi_name;
					$crmi_value = isset( $data[ $crmi_name ] ) ? $data[ $crmi_name ] : '';
					$crmi_req   = ! empty( $crmi_field['required'] );
					$crmi_bad   = $errors instanceof WP_Error && $errors->get_error_message( $crmi_name );
					$crmi_attrs = ( $crmi_req ? ' required aria-required="true"' : '' ) . ( $crmi_bad ? ' aria-invalid="true"' : '' );
					?>
					<tr>
						<th scope="row">
							<label for="<?php echo esc_attr( $crmi_id ); ?>">
								<?php echo esc_html( $crmi_field['label'] ); ?>
								<?php if ( $crmi_req ) : ?>
									<span class="crmi-required" aria-hidden="true">*</span>
								<?php endif; ?>
							</label>
						</th>
						<td>
							<?php
							switch ( $crmi_field['type'] ) {
								case 'textarea':
									printf(
										'<textarea id="%1$s" name="%2$s" rows="5" class="large-text"%3$s>%4$s</textarea>',
										esc_attr( $crmi_id ),
										esc_attr( $crmi_name ),
										$crmi_attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Atributos fijos.
										esc_textarea( (string) $crmi_value )
									);
									break;

								case 'select':
								case 'relation':
									$crmi_options = 'select' === $crmi_field['type'] ? $crmi_field['options'] : $relations[ $crmi_name ];
									printf( '<select id="%1$s" name="%2$s"%3$s>', esc_attr( $crmi_id ), esc_attr( $crmi_name ), $crmi_attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Atributos fijos.
									if ( 'relation' === $crmi_field['type'] ) {
										echo '<option value="0">' . esc_html__( '— Ninguno —', 'crm-inmobiliario-wp' ) . '</option>';
									}
									foreach ( $crmi_options as $crmi_key => $crmi_label ) {
										printf(
											'<option value="%1$s"%2$s>%3$s</option>',
											esc_attr( $crmi_key ),
											selected( (string) $crmi_value, (string) $crmi_key, false ),
											esc_html( $crmi_label )
										);
									}
									echo '</select>';
									if ( 'relation' === $crmi_field['type'] && empty( $crmi_options ) ) {
										$crmi_rel_def = CRMI_Entities::get( $crmi_field['relation'] );
										printf(
											'<p class="description"><a href="%1$s">%2$s</a></p>',
											esc_url( CRMI_Admin::page_url( $crmi_rel_def['page'], array( 'action' => 'new' ) ) ),
											/* translators: %s: entidad en plural. */
											esc_html( sprintf( __( 'Aún no hay %s. Crear uno nuevo.', 'crm-inmobiliario-wp' ), mb_strtolower( $crmi_rel_def['plural'] ) ) )
										);
									}
									break;

								case 'price':
									$crmi_display = ( null === $crmi_value || '' === $crmi_value ) ? '' : rtrim( rtrim( number_format( (float) $crmi_value, 2, '.', '' ), '0' ), '.' );
									printf(
										'<input type="text" inputmode="decimal" id="%1$s" name="%2$s" value="%3$s" class="regular-text"%4$s><p class="description">%5$s</p>',
										esc_attr( $crmi_id ),
										esc_attr( $crmi_name ),
										esc_attr( $crmi_display ),
										$crmi_attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Atributos fijos.
										esc_html__( 'Solo el número. Admite 250000, 250.000 o 1.250,50.', 'crm-inmobiliario-wp' )
									);
									break;

								default:
									$crmi_types = array(
										'email' => 'email',
										'tel'   => 'tel',
										'date'  => 'date',
									);
									$crmi_type  = isset( $crmi_types[ $crmi_field['type'] ] ) ? $crmi_types[ $crmi_field['type'] ] : 'text';
									printf(
										'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" class="regular-text"%5$s%6$s>',
										esc_attr( $crmi_type ),
										esc_attr( $crmi_id ),
										esc_attr( $crmi_name ),
										esc_attr( (string) $crmi_value ),
										! empty( $crmi_field['max'] ) ? ' maxlength="' . esc_attr( $crmi_field['max'] ) . '"' : '',
										$crmi_attrs // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Atributos fijos.
									);
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>

				<?php if ( $item ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Fecha de creación', 'crm-inmobiliario-wp' ); ?></th>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item['created_at'] ) ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<p class="submit crmi-submit">
			<?php submit_button( $item ? __( 'Guardar cambios', 'crm-inmobiliario-wp' ) : __( 'Crear', 'crm-inmobiliario-wp' ), 'primary', 'submit', false ); ?>
			<?php if ( $item ) : ?>
				<a class="crmi-delete button-link-delete" href="<?php echo esc_url( CRMI_Admin::delete_url( $entity, $id ) ); ?>" data-crmi-confirm="<?php esc_attr_e( '¿Seguro que quieres eliminar este registro? Esta acción no se puede deshacer.', 'crm-inmobiliario-wp' ); ?>"><?php esc_html_e( 'Eliminar', 'crm-inmobiliario-wp' ); ?></a>
			<?php endif; ?>
		</p>
	</form>

	<?php
	if ( is_array( $related ) ) :
		$crmi_add_url = CRMI_Admin::page_url(
			'crmi-followups',
			array(
				'action'           => 'new',
				$related['column'] => $id, // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variable local del método que incluye la vista.
			)
		);
		?>
		<div class="crmi-panel crmi-related">
			<h2>
				<?php esc_html_e( 'Seguimientos relacionados', 'crm-inmobiliario-wp' ); ?>
				<a class="page-title-action" href="<?php echo esc_url( $crmi_add_url ); ?>"><?php esc_html_e( 'Añadir seguimiento', 'crm-inmobiliario-wp' ); ?></a>
			</h2>
			<?php if ( empty( $related['items'] ) ) : ?>
				<p class="crmi-empty"><?php esc_html_e( 'Sin seguimientos todavía.', 'crm-inmobiliario-wp' ); ?></p>
			<?php else : ?>
				<?php $crmi_other = 'contact_id' === $related['column'] ? 'property_id' : 'contact_id'; ?>
				<table class="widefat striped crmi-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Tarea', 'crm-inmobiliario-wp' ); ?></th>
							<th><?php esc_html_e( 'Fecha', 'crm-inmobiliario-wp' ); ?></th>
							<th><?php echo 'property_id' === $crmi_other ? esc_html__( 'Propiedad', 'crm-inmobiliario-wp' ) : esc_html__( 'Contacto', 'crm-inmobiliario-wp' ); ?></th>
							<th><?php esc_html_e( 'Estado', 'crm-inmobiliario-wp' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $related['items'] as $crmi_row ) : ?>
							<tr>
								<td data-label="<?php esc_attr_e( 'Tarea', 'crm-inmobiliario-wp' ); ?>"><?php echo CRMI_Admin::cell( 'followups', 'title', $crmi_row, $related['labels'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado en cell(). ?></td>
								<td data-label="<?php esc_attr_e( 'Fecha', 'crm-inmobiliario-wp' ); ?>"><?php echo CRMI_Admin::cell( 'followups', 'due_date', $crmi_row, $related['labels'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado en cell(). ?></td>
								<td data-label="<?php echo 'property_id' === $crmi_other ? esc_attr__( 'Propiedad', 'crm-inmobiliario-wp' ) : esc_attr__( 'Contacto', 'crm-inmobiliario-wp' ); ?>"><?php echo CRMI_Admin::cell( 'followups', $crmi_other, $crmi_row, $related['labels'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado en cell(). ?></td>
								<td data-label="<?php esc_attr_e( 'Estado', 'crm-inmobiliario-wp' ); ?>"><?php echo CRMI_Admin::cell( 'followups', 'status', $crmi_row, $related['labels'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado en cell(). ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
