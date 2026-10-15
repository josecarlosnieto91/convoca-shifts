<?php
/**
 * Convoca Shifts — saneado del formulario de «Crear semana tipo».
 *
 * Vive en su propio fichero, como `horario.php`, para que la suite unitaria pueda cargarlo sin arrastrar
 * `cpt-turno.php` (que tira de funciones de WordPress que el arnés no simula).
 *
 * @package    Convoca\Shifts
 * @subpackage Includes
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 */

namespace Convoca\Shifts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanea el array anidado de días que llega de «Crear semana tipo».
 *
 * `sanitize_text_field()` sobre un array devuelve **cadena vacía** (`_sanitize_text_fields()`: si el valor
 * es `array` u `object`, `''`). El `array_map( 'sanitize_text_field', $_POST['dias'] )` que había antes
 * dejaba `$dias` en `[ '0' => '', '1' => '', … ]`, así que el generador no encontraba ningún día `activo`
 * y respondía siempre «Se han generado 0 turnos nuevos.» (issue #9). Se sanea campo a campo.
 *
 * @param array $dias Datos crudos del POST, tal cual: dias[N][campo].
 * @return array Días saneados, conservando el anidamiento.
 */
function convoca_shifts_sanear_dias( array $dias ): array {
	$limpio = array();

	foreach ( $dias as $offset => $conf ) {
		if ( ! is_array( $conf ) ) {
			continue;
		}

		$limpio[ (int) $offset ] = array(
			'activo'       => isset( $conf['activo'] ) ? sanitize_text_field( (string) $conf['activo'] ) : '',
			'hora_inicio'  => isset( $conf['hora_inicio'] ) ? sanitize_text_field( (string) $conf['hora_inicio'] ) : '',
			'hora_fin'     => isset( $conf['hora_fin'] ) ? sanitize_text_field( (string) $conf['hora_fin'] ) : '',
			'estado'       => isset( $conf['estado'] ) ? sanitize_text_field( (string) $conf['estado'] ) : '',
			'actividad_id' => isset( $conf['actividad_id'] ) ? (int) $conf['actividad_id'] : 0,
			'monitor_id'   => isset( $conf['monitor_id'] ) ? (int) $conf['monitor_id'] : 0,
			'apoyo'        => isset( $conf['apoyo'] ) ? sanitize_text_field( (string) $conf['apoyo'] ) : '',
		);
	}

	return $limpio;
}
