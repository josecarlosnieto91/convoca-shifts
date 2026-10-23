<?php
/**
 * Doble del ayudante `convoca_shifts_fecha_corta()`, que vive en el arranque del plugin.
 *
 * Las pruebas unitarias no cargan `convoca-shifts.php` (arranca el plugin entero), así que el
 * ayudante se dobla aquí con un formato fijo: lo que hay que poder afirmar es **qué pinta** el
 * resumen, no cómo formatea la fecha el plugin.
 *
 * @package Convoca\Shifts\Tests
 */
namespace Convoca\Shifts;

if ( ! function_exists( 'Convoca\Shifts\convoca_shifts_fecha_corta' ) ) {
	/**
	 * Fecha corta de un turno (doble del ayudante que vive en el arranque del plugin).
	 *
	 * El de verdad está en `convoca-shifts.php`, que las pruebas unitarias no cargan; aquí solo hace
	 * falta que sea predecible para poder afirmar qué pinta el resumen.
	 *
	 * @param int $timestamp Marca de tiempo.
	 * @return string
	 */
	function convoca_shifts_fecha_corta( int $timestamp ): string {
		return $timestamp > 0 ? gmdate( 'd/m', $timestamp ) : '';
	}
}
