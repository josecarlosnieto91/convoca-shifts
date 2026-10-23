<?php
/**
 * Doble de `Convoca\Core\License_Manager`: si hay licencia y qué funciones PRO incluye.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Core;

if ( ! class_exists( 'Convoca\Core\License_Manager' ) ) {
	/**
	 * Licencias (doble).
	 */
	class License_Manager {

		/**
		 * ¿Está activa esa función PRO?
		 *
		 * @param string $funcion Función.
		 * @return bool
		 */
		public static function has_pro( $funcion = '' ) {
			return (bool) ( $GLOBALS['_sh_pro'][ $funcion ] ?? false );
		}
	}
}
