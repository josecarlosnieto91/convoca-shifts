<?php
/**
 * WordPress de mentira para las pruebas: `wp-admin/includes/upgrade.php`.
 *
 * El código del plugin hace `require_once ABSPATH . 'wp-admin/includes/upgrade.php'` antes de `dbDelta()`.
 * En el repo ese fichero no existe, así que cualquier camino que cree tablas reventaba en la suite.
 * Aquí vive un `dbDelta()` doblado que APUNTA el DDL en `$GLOBALS['_ddl']`.
 *
 * @package Convoca\Shifts\Tests
 */

if ( ! function_exists( 'dbDelta' ) ) {
	/**
	 * Aplica el DDL: aquí solo se apunta.
	 *
	 * @param string|array $queries Consultas.
	 * @return array<string, string>
	 */
	function dbDelta( $queries = '' ) {
		foreach ( (array) $queries as $consulta ) {
			$GLOBALS['_ddl'][] = (string) $consulta;
		}

		return array();
	}
}
