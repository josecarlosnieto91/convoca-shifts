<?php
/**
 * Coherencia entre lo que el diagnóstico pide y lo que el plugin registra.
 *
 * El check de la página del calendario pedía `[calendario_centro]` mientras el shortcode real era
 * `[convoca_calendario]`. Como la comprobación usa `has_shortcode()`, que exige el shortcode
 * registrado, ese check no podía pasar nunca: el panel se quedaba en rojo en cualquier instalación.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Los shortcodes que el diagnóstico exige tienen que existir de verdad.
 */
final class CoherenciaShortcodesTest extends TestCase {

	/**
	 * Directorio raíz del plugin.
	 */
	private function raiz(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Shortcodes que el diagnóstico pide en sus comprobaciones de páginas.
	 *
	 * @return array<int, string>
	 */
	private function shortcodes_pedidos(): array {
		$ajustes = (string) file_get_contents( $this->raiz() . '/includes/admin-settings.php' );
		preg_match_all( "/'shortcode'\s*=>\s*'\[([a-z0-9_]+)\]'/", $ajustes, $coincidencias );

		return array_values( array_unique( $coincidencias[1] ) );
	}

	/**
	 * Shortcodes que el plugin registra en cualquier fichero de includes/.
	 *
	 * @return array<int, string>
	 */
	private function shortcodes_registrados(): array {
		$registrados = array();
		$ficheros    = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $this->raiz() . '/includes' )
		);

		foreach ( $ficheros as $fichero ) {
			if ( 'php' !== $fichero->getExtension() ) {
				continue;
			}

			$contenido = (string) file_get_contents( $fichero->getPathname() );
			preg_match_all( "/add_shortcode\(\s*'([a-z0-9_]+)'/", $contenido, $coincidencias );
			$registrados = array_merge( $registrados, $coincidencias[1] );
		}

		return array_values( array_unique( $registrados ) );
	}

	/**
	 * El diagnóstico no puede pedir una página que nunca podrá reconocer.
	 */
	public function test_los_shortcodes_que_pide_el_diagnostico_existen(): void {
		$pedidos = $this->shortcodes_pedidos();

		$this->assertNotEmpty( $pedidos, 'el diagnóstico tiene que pedir al menos una página' );

		$registrados = $this->shortcodes_registrados();

		foreach ( $pedidos as $shortcode ) {
			$this->assertContains(
				$shortcode,
				$registrados,
				sprintf( 'el diagnóstico pide [%s] y el plugin no lo registra: ese check nunca pasará', $shortcode )
			);
		}
	}

	/**
	 * Control negativo: el test tiene que saber distinguir un shortcode inventado.
	 */
	public function test_un_shortcode_inventado_no_esta_registrado(): void {
		$this->assertNotContains( 'calendario_centro', $this->shortcodes_registrados() );
	}
}
