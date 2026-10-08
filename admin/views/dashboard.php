<?php
/**
 * Vista: panel principal.
 *
 * Variables disponibles: $stats, $upcoming, $recent, $labels.
 *
 * @package CRM_Inmobiliario
 */

defined( 'ABSPATH' ) || exit;

$crmi_cards = array(
	array( __( 'Contactos', 'crm-inmobiliario-wp' ), $stats['contacts'], CRMI_Admin::page_url( 'crmi-contacts' ), '' ),
	array( __( 'Leads activos', 'crm-inmobiliario-wp' ), $stats['leads'], CRMI_Admin::page_url( 'crmi-contacts' ), __( 'Nuevos, contactados o cualificados', 'crm-inmobiliario-wp' ) ),
	array( __( 'Oportunidades', 'crm-inmobiliario-wp' ), $stats['opportunities'], CRMI_Admin::page_url( 'crmi-contacts', array( 'status' => 'negociacion' ) ), __( 'Contactos en negociación', 'crm-inmobiliario-wp' ) ),
	/* translators: %d: número de propiedades disponibles. */
	array( __( 'Propiedades', 'crm-inmobiliario-wp' ), $stats['properties'], CRMI_Admin::page_url( 'crmi-properties' ), sprintf( __( '%d disponibles', 'crm-inmobiliario-wp' ), $stats['properties_available'] ) ),
	array( __( 'Seguimientos pendientes', 'crm-inmobiliario-wp' ), $stats['followups_pending'], CRMI_Admin::page_url( 'crmi-followups', array( 'status' => 'pendiente' ) ), '' ),
	array( __( 'Seguimientos vencidos', 'crm-inmobiliario-wp' ), $stats['followups_overdue'], CRMI_Admin::page_url( 'crmi-followups', array( 'due' => 'overdue' ) ), '', $stats['followups_overdue'] > 0 ),
);
?>
<div class="wrap crmi-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'CRM Inmobiliario', 'crm-inmobiliario-wp' ); ?></h1>
	<a href="<?php echo esc_url( CRMI_Admin::page_url( 'crmi-contacts', array( 'action' => 'new' ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'Añadir contacto', 'crm-inmobiliario-wp' ); ?></a>
	<a href="<?php echo esc_url( CRMI_Admin::page_url( 'crmi-properties', array( 'action' => 'new' ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'Añadir propiedad', 'crm-inmobiliario-wp' ); ?></a>
	<a href="<?php echo esc_url( CRMI_Admin::page_url( 'crmi-followups', array( 'action' => 'new' ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'Añadir seguimiento', 'crm-inmobiliario-wp' ); ?></a>
	<hr class="wp-header-end">

	<?php $this->render_notice(); ?>

	<div class="crmi-cards">
		<?php foreach ( $crmi_cards as $crmi_card ) : ?>
			<a class="crmi-card<?php echo ! empty( $crmi_card[4] ) ? ' crmi-card--alert' : ''; ?>" href="<?php echo esc_url( $crmi_card[2] ); ?>">
				<span class="crmi-card__value"><?php echo esc_html( number_format_i18n( $crmi_card[1] ) ); ?></span>
				<span class="crmi-card__label"><?php echo esc_html( $crmi_card[0] ); ?></span>
				<?php if ( '' !== $crmi_card[3] ) : ?>
					<span class="crmi-card__hint"><?php echo esc_html( $crmi_card[3] ); ?></span>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</div>

	<div class="crmi-columns">
		<div class="crmi-panel">
			<h2><?php esc_html_e( 'Próximos seguimientos pendientes', 'crm-inmobiliario-wp' ); ?></h2>
			<?php if ( empty( $upcoming['items'] ) ) : ?>
				<p class="crmi-empty"><?php esc_html_e( 'No hay seguimientos pendientes.', 'crm-inmobiliario-wp' ); ?></p>
			<?php else : ?>
				<table class="widefat striped crmi-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Tarea', 'crm-inmobiliario-wp' ); ?></th>
							<th><?php esc_html_e( 'Fecha', 'crm-inmobiliario-wp' ); ?></th>
							<th><?php esc_html_e( 'Contacto', 'crm-inmobiliario-wp' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $upcoming['items'] as $crmi_row ) : ?>
							<tr>
								<td data-label="<?php esc_attr_e( 'Tarea', 'crm-inmobiliario-wp' ); ?>"><?php echo CRMI_Admin::cell( 'followups', 'title', $crmi_row, $labels ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado en cell(). ?></td>
								<td data-label="<?php esc_attr_e( 'Fecha', 'crm-inmobiliario-wp' ); ?>"><?php echo CRMI_Admin::cell( 'followups', 'due_date', $crmi_row, $labels ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado en cell(). ?></td>
								<td data-label="<?php esc_attr_e( 'Contacto', 'crm-inmobiliario-wp' ); ?>"><?php echo CRMI_Admin::cell( 'followups', 'contact_id', $crmi_row, $labels ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado en cell(). ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<div class="crmi-panel">
			<h2><?php esc_html_e( 'Últimos contactos', 'crm-inmobiliario-wp' ); ?></h2>
			<?php if ( empty( $recent['items'] ) ) : ?>
				<p class="crmi-empty">
					<?php esc_html_e( 'Todavía no hay contactos.', 'crm-inmobiliario-wp' ); ?>
					<a href="<?php echo esc_url( CRMI_Admin::page_url( 'crmi-contacts', array( 'action' => 'new' ) ) ); ?>"><?php esc_html_e( 'Añade el primero', 'crm-inmobiliario-wp' ); ?></a>
				</p>
			<?php else : ?>
				<table class="widefat striped crmi-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Nombre', 'crm-inmobiliario-wp' ); ?></th>
							<th><?php esc_html_e( 'Estado', 'crm-inmobiliario-wp' ); ?></th>
							<th><?php esc_html_e( 'Creado', 'crm-inmobiliario-wp' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recent['items'] as $crmi_row ) : ?>
							<tr>
								<td data-label="<?php esc_attr_e( 'Nombre', 'crm-inmobiliario-wp' ); ?>"><?php echo CRMI_Admin::cell( 'contacts', 'name', $crmi_row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado en cell(). ?></td>
								<td data-label="<?php esc_attr_e( 'Estado', 'crm-inmobiliario-wp' ); ?>"><?php echo CRMI_Admin::cell( 'contacts', 'status', $crmi_row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado en cell(). ?></td>
								<td data-label="<?php esc_attr_e( 'Creado', 'crm-inmobiliario-wp' ); ?>"><?php echo CRMI_Admin::cell( 'contacts', 'created_at', $crmi_row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado en cell(). ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>
</div>
