<?php
/**
 * Ninguna acción del panel sin su botón (issue #13).
 *
 * El exportador de turnos a CSV existía, estaba enganchado y funcionaba… y no lo podía lanzar nadie: no había
 * ninguna pantalla que enviara `convoca_shifts_action=exportar_csv`. Una función que sólo se puede llamar por
 * correo no existe para quien usa el plugin, y la web la estaba anunciando.
 *
 * Esta prueba recorre las acciones que el plugin sabe atender y exige que cada una aparezca en algún sitio que
 * la dispare (un formulario, un botón o un enlace).
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

class AccionesConBotonTest extends TestCase {

	/**
	 * Todo el PHP del plugin, menos las pruebas y el vendor.
	 *
	 * @return string
	 */
	private function plugin(): string {
		$raiz   = dirname( __DIR__, 2 );
		$codigo = '';
		foreach ( glob( $raiz . '/{*.php,includes/*.php,admin/*.php}', GLOB_BRACE ) as $fichero ) {
			if ( false !== strpos( $fichero, '/tests/' ) ) {
				continue;
			}
			$codigo .= (string) file_get_contents( $fichero );
		}

		return $codigo;
	}

	public function test_cada_accion_del_panel_tiene_quien_la_lance(): void {
		$codigo  = $this->plugin();
		$acciones = array();

		// Acciones que el plugin atiende: `$_POST['convoca_shifts_action'] === 'x'` y sus hermanas.
		preg_match_all( "/convoca_shifts_action'?\s*\]\s*[^;]*?===\s*'([a-z_]+)'/", $codigo, $post );
		preg_match_all( "/\['convoca_shifts_action'\]\s*[^;]*?===\s*'([a-z_]+)'/", $codigo, $get );
		preg_match_all( "/convoca_shifts_action\s*=\s*([a-z_]+)/", $codigo, $url );

		foreach ( array_merge( $post[1], $get[1] ) as $accion ) {
			$acciones[ $accion ] = true;
		}

		$this->assertNotEmpty( $acciones, 'No se han encontrado acciones: revisa la expresión de búsqueda' );

		foreach ( array_keys( $acciones ) as $accion ) {
			// Tiene que aparecer al menos dos veces: donde se atiende y donde se dispara.
			$apariciones = preg_match_all( "/convoca_shifts_action[^;]{0,80}" . preg_quote( $accion, '/' ) . "/", $codigo );
			$this->assertGreaterThan(
				1,
				$apariciones,
				sprintf( 'La acción «%s» se atiende pero no hay ningún botón ni enlace que la dispare', $accion )
			);
		}
	}
}
