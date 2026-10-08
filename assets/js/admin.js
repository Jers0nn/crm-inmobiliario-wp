/**
 * CRM Inmobiliario Sencillo: confirmación antes de acciones destructivas.
 *
 * Cualquier enlace o botón con el atributo data-crmi-confirm pide
 * confirmación antes de continuar.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var target = event.target.closest( '[data-crmi-confirm]' );
		if ( ! target ) {
			return;
		}
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( target.getAttribute( 'data-crmi-confirm' ) ) ) {
			event.preventDefault();
		}
	} );
}() );
