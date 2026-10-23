<?php
/**
 * La pantalla de estado del sistema.
 *
 * Es lo primero que mira quien dice «no funciona»: cuatro comprobaciones (PHP, la página del calendario,
 * las tablas compartidas y el cron) con su gravedad y su arreglo. Lo que se prueba es que **no dé por bueno
 * lo que no lo está** —una página sin el shortcode es un aviso, no un «todo correcto»— y que la cabecera
 * diga lo que hay: error si hay algún error, atención si solo hay avisos.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/class-admin-status.php';

/**
 * Comprobaciones y pantalla de estado.
 */
final class AdminStatusTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$this->limpiar();
	}

	/**
	 * Deja el arnés como estaba.
	 */
	private function limpiar(): void {
		foreach ( array( '_wp_stores', '_sh_db', '_sh_cron' ) as $clave ) {
			unset( $GLOBALS[ $clave ] );
		}

		$GLOBALS['_wp_stores'] = array();
	}

	/**
	 * La versión de PHP: con una 8.x sale en verde.
	 */
	public function test_la_version_de_php(): void {
		$r = \Convoca\Shifts\Convoca_Shifts_Admin_Status::check_php_version();

		$this->assertSame( 'php', $r['id'] );
		$this->assertSame( \Convoca\Shifts\Convoca_Shifts_Admin_Status::SEVERITY_OK, $r['severity'] );
		$this->assertStringContainsString( PHP_VERSION, $r['message'] );

		// La rama de «PHP viejo» no se puede recorrer sin cambiar de intérprete: esta comprobación lee
		// `PHP_VERSION`, que es una constante. Queda dicho, no fingido.
	}

	/**
	 * La página del calendario: sin página es un error y se dice qué hacer.
	 */
	public function test_sin_pagina_de_calendario(): void {
		$r = \Convoca\Shifts\Convoca_Shifts_Admin_Status::check_pages();

		$this->assertSame( 'page_calendario', $r['id'] );
		$this->assertSame( \Convoca\Shifts\Convoca_Shifts_Admin_Status::SEVERITY_ERROR, $r['severity'] );
		$this->assertSame( 'No encontrada', $r['message'] );
		$this->assertStringContainsString( 'calendario_centro', $r['fix'], 'y cómo arreglarlo' );
	}

	/**
	 * Con la página publicada y su shortcode, en verde.
	 */
	public function test_pagina_con_shortcode(): void {
		$this->pagina( 'calendario', 'publish', '[calendario_centro]' );

		$r = \Convoca\Shifts\Convoca_Shifts_Admin_Status::check_pages();

		$this->assertSame( \Convoca\Shifts\Convoca_Shifts_Admin_Status::SEVERITY_OK, $r['severity'] );
		$this->assertStringContainsString( 'shortcode', $r['message'] );
	}

	/**
	 * Publicada pero **sin** el shortcode: aviso, no verde. Alguien abrirá una página vacía.
	 */
	public function test_pagina_sin_shortcode(): void {
		$this->pagina( 'calendario', 'publish', 'Hola, esto es un texto cualquiera' );

		$r = \Convoca\Shifts\Convoca_Shifts_Admin_Status::check_pages();

		$this->assertSame( \Convoca\Shifts\Convoca_Shifts_Admin_Status::SEVERITY_WARNING, $r['severity'] );
		$this->assertStringContainsString( 'sin el shortcode', $r['message'] );
		$this->assertStringContainsString( 'calendario_centro', $r['fix'] );
	}

	/**
	 * Una página en borrador no cuenta: el calendario público no la sirve.
	 */
	public function test_pagina_en_borrador(): void {
		$this->pagina( 'calendario', 'draft', '[calendario_centro]' );

		$r = \Convoca\Shifts\Convoca_Shifts_Admin_Status::check_pages();

		$this->assertSame( \Convoca\Shifts\Convoca_Shifts_Admin_Status::SEVERITY_ERROR, $r['severity'] );
	}

	/**
	 * Las tablas: si están, verde; si falta alguna, error y se nombra.
	 */
	public function test_las_tablas(): void {
		$GLOBALS['_sh_db']['tablas_existentes'] = array( 'wp_convoca_logs', 'wp_convoca_locks' );

		$this->assertSame(
			\Convoca\Shifts\Convoca_Shifts_Admin_Status::SEVERITY_OK,
			\Convoca\Shifts\Convoca_Shifts_Admin_Status::check_tables()['severity']
		);

		$GLOBALS['_sh_db']['tablas_existentes'] = array();

		$r = \Convoca\Shifts\Convoca_Shifts_Admin_Status::check_tables();
		$this->assertSame( \Convoca\Shifts\Convoca_Shifts_Admin_Status::SEVERITY_ERROR, $r['severity'] );
		$this->assertStringContainsString( 'convoca_logs', $r['message'] );
		$this->assertStringContainsString( 'convoca_locks', $r['message'] );
		$this->assertSame( 'Activa convoca-common para crear las tablas compartidas.', $r['fix'] );
	}

	/**
	 * El cron: programado en verde; si falta, aviso con lo que falta.
	 */
	public function test_el_cron(): void {
		$GLOBALS['_sh_cron'] = array(
			'convoca_shifts_hourly_event' => 1234,
			'convoca_shifts_daily_event'  => 5678,
		);

		$this->assertSame(
			\Convoca\Shifts\Convoca_Shifts_Admin_Status::SEVERITY_OK,
			\Convoca\Shifts\Convoca_Shifts_Admin_Status::check_cron()['severity']
		);

		$GLOBALS['_sh_cron'] = array( 'convoca_shifts_hourly_event' => 1234 );

		$r = \Convoca\Shifts\Convoca_Shifts_Admin_Status::check_cron();
		$this->assertSame( \Convoca\Shifts\Convoca_Shifts_Admin_Status::SEVERITY_WARNING, $r['severity'] );
		$this->assertStringContainsString( 'convoca_shifts_daily_event', $r['message'] );
		$this->assertStringNotContainsString( 'convoca_shifts_hourly_event', $r['message'], 'el que sí está no se nombra' );
	}

	/**
	 * Las cuatro comprobaciones, con sus cuatro identificadores.
	 */
	public function test_las_cuatro_comprobaciones(): void {
		$resultados = \Convoca\Shifts\Convoca_Shifts_Admin_Status::run_all();

		$this->assertSame( array( 'php', 'page_calendario', 'tables', 'cron' ), array_column( $resultados, 'id' ) );
		foreach ( $resultados as $r ) {
			$this->assertArrayHasKey( 'label', $r );
			$this->assertArrayHasKey( 'message', $r );
			$this->assertArrayHasKey( 'severity', $r );
		}
	}

	/**
	 * Todo en orden: cabecera verde y «Todo correcto».
	 */
	public function test_la_pantalla_cuando_todo_va_bien(): void {
		$this->todo_en_orden();

		$html = $this->pintar();

		$this->assertStringContainsString( 'convoca-diagnostic-header--success', $html );
		$this->assertStringContainsString( 'Todo correcto', $html );
		$this->assertStringContainsString( '4 comprobaciones realizadas', $html );
		$this->assertStringContainsString( '✓', $html );
		$this->assertStringNotContainsString( 'Se encontraron errores', $html );
	}

	/**
	 * Con un error manda el error, aunque también haya avisos.
	 */
	public function test_la_pantalla_con_error(): void {
		$this->todo_en_orden();
		$GLOBALS['_wp_stores']['pages'] = array(); // la página desaparece → error
		$GLOBALS['_sh_cron']            = array();  // y el cron se queda sin eventos → aviso

		$html = $this->pintar();

		$this->assertStringContainsString( 'convoca-diagnostic-header--error', $html );
		$this->assertStringContainsString( 'Se encontraron errores', $html );
		$this->assertStringContainsString( '✗', $html );
		$this->assertStringContainsString( 'No encontrada', $html );
		$this->assertStringContainsString( 'convoca-diagnostic-fix', $html, 'y se enseña el arreglo' );
	}

	/**
	 * Solo avisos: ni verde ni error.
	 */
	public function test_la_pantalla_solo_con_avisos(): void {
		$this->todo_en_orden();
		$GLOBALS['_sh_cron'] = array();

		$html = $this->pintar();

		$this->assertStringContainsString( 'convoca-diagnostic-header--warning', $html );
		$this->assertStringContainsString( 'Atención', $html );
		$this->assertStringContainsString( '⚠', $html );
		$this->assertStringNotContainsString( 'Todo correcto', $html );
	}

	/**
	 * Cada fila dice su etiqueta y su mensaje.
	 */
	public function test_las_filas_de_la_pantalla(): void {
		$this->todo_en_orden();

		$html = $this->pintar();

		foreach ( array( 'PHP', 'Página Calendario', 'Tablas BD', 'Cron' ) as $etiqueta ) {
			$this->assertStringContainsString( '<strong>' . $etiqueta . '</strong>', $html );
		}
		$this->assertSame( 4, substr_count( $html, 'convoca-diagnostic-row' ) );
	}

	/**
	 * Deja todo en orden.
	 */
	private function todo_en_orden(): void {
		$this->pagina( 'calendario', 'publish', '[calendario_centro]' );
		$GLOBALS['_sh_db']['tablas_existentes'] = array( 'wp_convoca_logs', 'wp_convoca_locks' );
		$GLOBALS['_sh_cron']                    = array(
			'convoca_shifts_hourly_event' => 1234,
			'convoca_shifts_daily_event'  => 5678,
		);
	}

	/**
	 * Deja una página en el arnés.
	 *
	 * @param string $slug     Slug.
	 * @param string $estado   Estado.
	 * @param string $contenido Contenido.
	 */
	private function pagina( string $slug, string $estado, string $contenido ): void {
		$GLOBALS['_wp_stores']['pages'][] = (object) array(
			'ID'           => 11,
			'post_type'    => 'page',
			'post_name'    => $slug,
			'post_status'  => $estado,
			'post_content' => $contenido,
		);
	}

	/**
	 * Pinta la pantalla y devuelve el HTML.
	 *
	 * @return string
	 */
	private function pintar(): string {
		ob_start();
		\Convoca\Shifts\Convoca_Shifts_Admin_Status::render_page();

		return (string) ob_get_clean();
	}
}
