<?php
/**
 * Doble de `Convoca\Core\Hour_Ledger` para los tests unitarios de Shifts.
 *
 * El libro de horas vive en Convoca Core, que aquí no está cargado. El doble nace con la PUERTA CERRADA,
 * que es el valor real por defecto del núcleo: así, si un test acredita horas sin abrirla, se está
 * engañando a sí mismo en vez de comprobar lo que pasa en producción.
 *
 * Para abrirla en un test:  $GLOBALS['__horas_puerta'] = true;
 *
 * @package Convoca\Shifts
 */

namespace Convoca\Core;

if ( ! class_exists( 'Convoca\Core\Hour_Ledger' ) ) {

	/**
	 * Doble mínimo del libro de horas.
	 */
	class Hour_Ledger {

		public const ORIGEN_TURNO = 'turno';

		/**
		 * Acredita (no-op sin la puerta abierta).
		 *
		 * @return int
		 */
		public static function credit( $origen, $origen_id, $user_id, $hours, array $args = array() ) {
			return empty( $GLOBALS['__horas_puerta'] ) ? 0 : 1;
		}

		/**
		 * Revoca (siempre, como el de verdad: revocar es limpieza).
		 *
		 * @return bool
		 */
		public static function revoke( $origen, $origen_id, $user_id ) {
			return true;
		}

		/**
		 * La puerta.
		 *
		 * @param int $user_id Usuario.
		 * @return bool
		 */
		public static function puede_acreditar_horas( int $user_id ): bool {
			return ! empty( $GLOBALS['__horas_puerta'] ) && $user_id > 0;
		}
	}
}
