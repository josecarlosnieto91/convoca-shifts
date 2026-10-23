<?php
/**
 * Doble de `convoca_shifts_log_activity()`, que vive en el arranque del plugin.
 *
 * Las pruebas unitarias no cargan `convoca-shifts.php`, así que el registro de actividad se apunta
 * aquí para poder afirmar **qué se registró**, que es lo que hace auditable el módulo.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts;

if ( ! function_exists( 'Convoca\Shifts\convoca_shifts_log_activity' ) ) {
	/**
	 * Apunta una acción en el registro de actividad.
	 *
	 * @param int    $user_id  Persona que actúa.
	 * @param int    $turno_id Turno.
	 * @param string $action   Acción.
	 * @param array  $data     Detalle.
	 * @return void
	 */
	function convoca_shifts_log_activity( $user_id, $turno_id, $action, $data = array() ) {
		$GLOBALS['_sh_registro'][] = array(
			'user_id'  => (int) $user_id,
			'turno_id' => (int) $turno_id,
			'action'   => (string) $action,
			'data'     => (array) $data,
		);
	}
}
