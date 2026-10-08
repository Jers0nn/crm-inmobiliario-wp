<?php
/**
 * Vista: ajustes (datos de ejemplo y política de desinstalación).
 *
 * Variables disponibles: $delete_on_uninstall, $has_sample.
 *
 * @package CRM_Inmobiliario
 */

defined( 'ABSPATH' ) || exit;

$crmi_action = admin_url( 'admin-post.php' );
?>
<div class="wrap crmi-wrap">
	<h1><?php esc_html_e( 'Ajustes del CRM', 'crm-inmobiliario-wp' ); ?></h1>

	<?php $this->render_notice(); ?>

	<div class="crmi-panel">
		<h2><?php esc_html_e( 'Datos al desinstalar', 'crm-inmobiliario-wp' ); ?></h2>
		<p><?php esc_html_e( 'Desactivar el plugin nunca borra datos. Por defecto, tampoco se borran al desinstalarlo (eliminarlo desde Plugins), para evitar pérdidas accidentales.', 'crm-inmobiliario-wp' ); ?></p>
		<form method="post" action="<?php echo esc_url( $crmi_action ); ?>">
			<?php wp_nonce_field( 'crmi_settings' ); ?>
			<input type="hidden" name="action" value="crmi_settings">
			<input type="hidden" name="crmi_task" value="save">
			<label>
				<input type="checkbox" name="crmi_delete_data_on_uninstall" value="1" <?php checked( $delete_on_uninstall ); ?>>
				<?php esc_html_e( 'Eliminar todas las tablas y opciones del CRM cuando se desinstale el plugin (irreversible).', 'crm-inmobiliario-wp' ); ?>
			</label>
			<?php submit_button( __( 'Guardar ajustes', 'crm-inmobiliario-wp' ) ); ?>
		</form>
	</div>

	<div class="crmi-panel">
		<h2><?php esc_html_e( 'Datos de ejemplo', 'crm-inmobiliario-wp' ); ?></h2>
		<p><?php esc_html_e( 'Puedes crear unos pocos contactos, propiedades y seguimientos ficticios para probar el CRM. Solo se crean si lo solicitas aquí y se pueden eliminar después sin afectar a tus datos reales.', 'crm-inmobiliario-wp' ); ?></p>

		<?php if ( $has_sample ) : ?>
			<form method="post" action="<?php echo esc_url( $crmi_action ); ?>">
				<?php wp_nonce_field( 'crmi_settings' ); ?>
				<input type="hidden" name="action" value="crmi_settings">
				<input type="hidden" name="crmi_task" value="sample_remove">
				<?php submit_button( __( 'Eliminar datos de ejemplo', 'crm-inmobiliario-wp' ), 'delete', 'submit', true, array( 'data-crmi-confirm' => __( '¿Eliminar los datos de ejemplo?', 'crm-inmobiliario-wp' ) ) ); ?>
			</form>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( $crmi_action ); ?>">
				<?php wp_nonce_field( 'crmi_settings' ); ?>
				<input type="hidden" name="action" value="crmi_settings">
				<input type="hidden" name="crmi_task" value="sample_create">
				<label>
					<input type="checkbox" name="crmi_confirm_sample" value="1">
					<?php esc_html_e( 'Entiendo que se añadirán datos ficticios a esta instalación.', 'crm-inmobiliario-wp' ); ?>
				</label>
				<?php submit_button( __( 'Crear datos de ejemplo', 'crm-inmobiliario-wp' ), 'secondary' ); ?>
			</form>
		<?php endif; ?>
	</div>

	<div class="crmi-panel">
		<h2><?php esc_html_e( 'Permisos', 'crm-inmobiliario-wp' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: %s: nombre de la capacidad. */
				esc_html__( 'El acceso al CRM requiere la capacidad %s, que se concede automáticamente a los administradores. Puedes asignarla a otros roles con un gestor de roles.', 'crm-inmobiliario-wp' ),
				'<code>' . esc_html( CRMI_CAP ) . '</code>'
			);
			?>
		</p>
	</div>
</div>
