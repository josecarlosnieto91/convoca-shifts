<?php
/**
 * Horario de apertura y cierre del centro.
 *
 * @package Convoca\Shifts
 */

namespace Convoca\Shifts;

defined( 'ABSPATH' ) || exit;

/**
 * Horario del centro, con el valor por defecto de verdad.
 *
 * `get_option( 'convoca_shifts_hora_apertura', '09:00' )` **no** aplica el valor por defecto cuando la
 * opción existe vacía: devuelve '' y el horario se queda sin límites. Y guardarla en blanco desde la
 * pantalla de Ajustes es lo normal, no un caso raro. Medido: con las dos opciones vacías el turno rápido
 * no guardaba nada y los turnos nacían sin hora y desaparecían del calendario.
 */
class Horario {

	public const APERTURA_POR_DEFECTO = '09:00';
	public const CIERRE_POR_DEFECTO   = '22:00';

	/**
	 * Hora de apertura utilizable.
	 */
	public static function apertura(): string {
		return self::valor( 'convoca_shifts_hora_apertura', self::APERTURA_POR_DEFECTO );
	}

	/**
	 * Hora de cierre utilizable.
	 */
	public static function cierre(): string {
		return self::valor( 'convoca_shifts_hora_cierre', self::CIERRE_POR_DEFECTO );
	}

	/**
	 * El vacío (o solo espacios) cuenta como «sin configurar».
	 */
	private static function valor( string $opcion, string $defecto ): string {
		$valor = (string) get_option( $opcion, '' );
		return '' === trim( $valor ) ? $defecto : $valor;
	}
}
