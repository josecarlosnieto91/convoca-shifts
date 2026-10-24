<?php
/**
 * Desinstalar turnos: o se van los turnos y sus términos, o no se toca nada.
 *
 * El caso peligroso es el contrario de lo que parece: **borrar de más** en un plugin que se desinstala por error, o
 * **dejar de menos** (los turnos se quedan huérfanos en la base y reaparecen si se reinstala). Aquí se fijan las dos
 * salidas y la guarda de «conservar datos».
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Desinstalación.
 */
final class DesinstalarTest extends TestCase {

	/**
	 * Previos.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo = array(
			'opciones' => $GLOBALS['_wp_stores']['options'] ?? array(),
			'posts'    => $GLOBALS['_wp_stores']['posts'] ?? array(),
			'borrados' => $GLOBALS['_sh_borrados'] ?? array(),
			'terms'    => $GLOBALS['_sh_terms'] ?? array(),
			'hooks'    => $GLOBALS['_sh_hooks_limpiados'] ?? array(),
		);
		$GLOBALS['_wp_stores']['options']   = array();
		$GLOBALS['_wp_stores']['posts']     = array(
			101 => (object) array( 'ID' => 101, 'post_type' => 'centro_turno', 'post_status' => 'publish' ),
			102 => (object) array( 'ID' => 102, 'post_type' => 'centro_turno', 'post_status' => 'draft' ),
		);
		$GLOBALS['_sh_borrados']            = array();
		$GLOBALS['_sh_terms']               = array();
		$GLOBALS['_sh_hooks_limpiados']     = array();
	}

	/**
	 * Limpia.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['options'] = $this->previo['opciones'];
		$GLOBALS['_wp_stores']['posts']   = $this->previo['posts'];
		$GLOBALS['_sh_borrados']          = $this->previo['borrados'];
		$GLOBALS['_sh_terms']             = $this->previo['terms'];
		$GLOBALS['_sh_hooks_limpiados']   = $this->previo['hooks'];
	}

	/**
	 * Ejecuta el desinstalador.
	 */
	private function desinstalar(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'convoca-shifts/convoca-shifts.php' );
		}
		include dirname( __DIR__, 2 ) . '/uninstall.php';
	}

	/**
	 * Con `convoca_uninstall_keep_data` a 1 no se borra **nada**: ni turnos, ni términos, ni ajustes.
	 */
	public function test_conservar_los_datos(): void {
		$GLOBALS['_wp_stores']['options']['convoca_uninstall_keep_data'] = 1;
		$GLOBALS['_wp_stores']['options']['convoca_shifts_version']      = '2.5.0';
		$GLOBALS['_sh_terms'] = array( (object) array( 'term_id' => 7 ) );

		$this->desinstalar();

		$this->assertSame( array(), (array) $GLOBALS['_sh_borrados'], 'no se borra ni un turno' );
		$this->assertArrayHasKey( 'convoca_shifts_version', $GLOBALS['_wp_stores']['options'] );
		$this->assertSame( array(), (array) $GLOBALS['_sh_hooks_limpiados'] );
	}

	/**
	 * Sin la guarda: turnos **con borrado forzado**, términos y ajustes fuera.
	 */
	public function test_borrar_todo(): void {
		$GLOBALS['_wp_stores']['options']['convoca_uninstall_keep_data'] = 0;
		$GLOBALS['_wp_stores']['options']['convoca_shifts_version']      = '2.5.0';
		$GLOBALS['_sh_terms'] = array( (object) array( 'term_id' => 7 ) );

		$this->desinstalar();

		$posts = array_values( array_filter( (array) $GLOBALS['_sh_borrados'], static fn( $b ) => 'post' === $b['tipo'] ) );
		$this->assertNotEmpty( $posts, 'los turnos se borran' );
		foreach ( $posts as $p ) {
			$this->assertTrue( $p['force'], 'se borran de verdad, no a la papelera: un turno en la papelera sigue en la base' );
		}

		$terms = array_values( array_filter( (array) $GLOBALS['_sh_borrados'], static fn( $b ) => 'term' === $b['tipo'] ) );
		$this->assertNotEmpty( $terms, 'los términos de la taxonomía también' );
		$this->assertSame( 'convoca_shifts_actividad', $terms[0]['tax'] );

		$this->assertArrayNotHasKey( 'convoca_shifts_version', $GLOBALS['_wp_stores']['options'] );
	}

	/**
	 * Sin `WP_UNINSTALL_PLUGIN` no toca nada: se comprueba en un proceso aparte porque corta con `exit`.
	 */
	public function test_sin_wp_uninstall_plugin_no_hace_nada(): void {
		$fichero = dirname( __DIR__, 2 ) . '/uninstall.php';
		$salida  = shell_exec( 'php -r ' . escapeshellarg( 'include ' . var_export( $fichero, true ) . '; echo "SEGUIA";' ) . ' 2>&1' );

		$this->assertIsString( (string) $salida );
		$this->assertStringNotContainsString( 'SEGUIA', (string) $salida );
	}
}
