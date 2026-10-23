<?php
/**
 * Unit test bootstrap — standalone, no WordPress needed.
 *
 * Shifts depende de Convoca Core (Logger, Utils). Reutilizamos el
 * bootstrap-unit de Core (mocks WP) y cargamos ambos autoloaders.
 *
 * Lo propio de Shifts: los dobles de la API REST. El de core para `is_user_logged_in()` devuelve
 * siempre `false`, así que aquí se define antes —y gana— una versión que la prueba puede mover; si no,
 * no habría forma de probar los permisos de los endpoints, que es medio `rest-api.php`.
 *
 * @package Convoca\Shifts
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

if ( ! function_exists( 'is_user_logged_in' ) ) {
	/**
	 * Sesión de mentira, que la prueba abre y cierra a voluntad.
	 *
	 * @return bool
	 */
	function is_user_logged_in() {
		return (bool) ( $GLOBALS['_sh_logged'] ?? false );
	}
}

if ( ! function_exists( 'register_rest_route' ) ) {
	/**
	 * Apunta las rutas en vez de registrarlas: una prueba tiene que poder decir qué endpoints hay,
	 * con qué método y con qué permiso.
	 *
	 * @param string $namespace Espacio de nombres.
	 * @param string $route     Ruta.
	 * @param array  $args      Argumentos del endpoint.
	 * @return bool
	 */
	function register_rest_route( $namespace, $route, $args = array() ) {
		$GLOBALS['_sh_routes'][] = array_merge(
			array(
				'namespace' => $namespace,
				'route'     => $route,
			),
			(array) $args
		);

		return true;
	}
}

if ( ! function_exists( '__return_true' ) ) {
	/**
	 * Devuelve verdadero. Es lo que usa WordPress para los endpoints públicos.
	 *
	 * @return bool
	 */
	function __return_true() {
		return true;
	}
}

$core_bootstrap = dirname( __DIR__, 2 ) . '/convoca-core/tests/bootstrap-unit.php';
if ( file_exists( $core_bootstrap ) ) {
	require_once $core_bootstrap;
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
