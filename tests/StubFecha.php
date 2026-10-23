<?php
/**
 * Doble de `convoca_shifts_fecha_legible()`, que vive en el arranque del plugin.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts;

if ( ! function_exists( 'Convoca\Shifts\convoca_shifts_fecha_legible' ) ) {
	/**
	 * Fecha legible de un turno (formato fijo para poder afirmarla).
	 *
	 * @param int $timestamp Marca de tiempo.
	 * @return string
	 */
	function convoca_shifts_fecha_legible( $timestamp = 0 ): string {
		return 'legible:' . (int) $timestamp;
	}
}
