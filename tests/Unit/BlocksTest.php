<?php
/**
 * Los bloques del plugin y cuándo se cargan sus assets.
 *
 * La detección de «esta página necesita los assets» es la que decide si el calendario sale con estilos o
 * desnudo, y mira tres sitios distintos: el filtro de fuerza, el contenido de la entrada y los widgets
 * activos. Un fallo aquí no rompe nada: deja la página fea y sin calendario, que es peor de diagnosticar.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/shortcodes.php';
require_once dirname( __DIR__, 2 ) . '/includes/blocks.php';

/**
 * Detección de assets y registro de bloques.
 */
final class BlocksTest extends TestCase {

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
		foreach ( array( '_sh_encolados', '_sh_bloques', '_sh_sidebars', '_sh_singular' ) as $clave ) {
			unset( $GLOBALS[ $clave ] );
		}

		// No se vacía `_wp_stores` entero: ahí viven los shortcodes ya registrados, y borrarlos dejaba
		// sin datos a las pruebas de shortcodes que corren después en el mismo proceso.
		$GLOBALS['_wp_stores']['posts']     = array();
		$GLOBALS['_wp_stores']['post_meta'] = array();
		$GLOBALS['_wp_stores']['options']   = array();
		$GLOBALS['_sh_encolados'] = array();
		$GLOBALS['__shifts_filtros'] = array();
	}

	/**
	 * Una página cualquiera no carga nada.
	 */
	public function test_una_pagina_cualquiera_no_carga_nada(): void {
		$this->assertFalse( \Convoca\Shifts\convoca_shifts_should_load_assets() );
	}

	/**
	 * El filtro de fuerza manda por encima de todo.
	 */
	public function test_el_filtro_de_fuerza_manda(): void {
		$GLOBALS['__shifts_filtros']['convoca_shifts_force_enqueue_assets'] = static function () {
			return true;
		};

		$this->assertTrue( \Convoca\Shifts\convoca_shifts_should_load_assets() );
	}

	/**
	 * Una entrada con el bloque del calendario los necesita.
	 */
	public function test_una_entrada_con_el_bloque_del_calendario(): void {
		$this->entrada( '<!-- wp:centro-social/calendario /-->' );

		$this->assertTrue( \Convoca\Shifts\convoca_shifts_should_load_assets() );
	}

	/**
	 * Y con el shortcode en el contenido, también.
	 */
	public function test_una_entrada_con_el_shortcode(): void {
		$this->entrada( 'Horario: [convoca_calendario]' );

		$this->assertTrue( \Convoca\Shifts\convoca_shifts_should_load_assets() );
	}

	/**
	 * Un shortcode del plugin que no pinta calendario también cuenta (lleva sus estilos).
	 */
	public function test_otros_shortcodes_del_plugin_cuentan(): void {
		foreach ( array( 'convoca_boton_apuntarse', 'convoca_resumen_turnos', 'convoca_proximos_turnos', 'convoca_suscripcion_turnos' ) as $shortcode ) {
			$this->limpiar();
			$this->entrada( '[' . $shortcode . ']' );

			$this->assertTrue( \Convoca\Shifts\convoca_shifts_should_load_assets(), 'debería cargar con ' . $shortcode );
		}
	}

	/**
	 * Un shortcode de otro plugin no los necesita.
	 */
	public function test_un_shortcode_ajeno_no_cuenta(): void {
		$this->entrada( '[otro_plugin_calendario]' );

		$this->assertFalse( \Convoca\Shifts\convoca_shifts_should_load_assets() );
	}

	/**
	 * Un widget de texto con el shortcode del calendario los necesita: la página no lo dice.
	 */
	public function test_un_widget_de_texto_con_el_shortcode(): void {
		$this->widget( 'text', 2, array( 'text' => 'Mira el [convoca_calendario] del centro' ) );

		$this->assertTrue( \Convoca\Shifts\convoca_shifts_should_load_assets() );
	}

	/**
	 * Un widget de HTML personalizado con el bloque, también.
	 */
	public function test_un_widget_de_html_con_el_bloque(): void {
		$this->widget( 'custom_html', 2, array( 'content' => '<!-- wp:centro-social/resumen /-->' ) );

		$this->assertTrue( \Convoca\Shifts\convoca_shifts_should_load_assets() );
	}

	/**
	 * Un widget sin nada del plugin no los necesita.
	 */
	public function test_un_widget_ajeno_no_cuenta(): void {
		$this->widget( 'text', 2, array( 'text' => 'Solo un saludo' ) );

		$this->assertFalse( \Convoca\Shifts\convoca_shifts_should_load_assets() );
	}

	/**
	 * Los widgets inactivos no cuentan: no se pintan en ninguna parte.
	 */
	public function test_los_widgets_inactivos_no_cuentan(): void {
		$GLOBALS['_sh_sidebars']                                  = array();
		$GLOBALS['_sh_sidebars']['wp_inactive_widgets']           = array( 'text-2' );
		$GLOBALS['_wp_stores']['options']['widget_text'][2]       = array( 'text' => '[convoca_calendario]' );

		$this->assertFalse( \Convoca\Shifts\convoca_shifts_should_load_assets() );
	}

	/**
	 * Con la detección en verde, se encolan la hoja y el guion.
	 */
	public function test_se_encola_lo_que_hace_falta(): void {
		$GLOBALS['__shifts_filtros']['convoca_shifts_force_enqueue_assets'] = static function () {
			return true;
		};

		\Convoca\Shifts\convoca_shifts_enqueue_block_assets();

		$handles = array_column( $GLOBALS['_sh_encolados'], 'handle' );

		$this->assertContains( 'convoca-shifts-style', $handles );
		$this->assertContains( 'convoca-shifts-calendario', $handles );
	}

	/**
	 * Sin detección no se encola nada.
	 */
	public function test_sin_deteccion_no_se_encola_nada(): void {
		\Convoca\Shifts\convoca_shifts_enqueue_block_assets();

		$this->assertSame( array(), $GLOBALS['_sh_encolados'] );
	}

	/**
	 * El editor de bloques carga su guion con sus dependencias.
	 */
	public function test_el_editor_carga_su_guion(): void {
		\Convoca\Shifts\convoca_shifts_enqueue_block_editor_assets();

		$guion = $GLOBALS['_sh_encolados'][0];

		$this->assertSame( 'convoca-shifts-blocks-js', $guion['handle'] );
	}

	/**
	 * Los cuatro bloques quedan registrados con su callback y su hoja de estilos.
	 */
	public function test_los_cuatro_bloques_se_registran(): void {
		\Convoca\Shifts\convoca_shifts_register_blocks();

		$esperados = array(
			'centro-social/calendario',
			'centro-social/boton-apuntarse',
			'centro-social/resumen',
			'centro-social/proximos-turnos',
		);

		foreach ( $esperados as $bloque ) {
			$this->assertArrayHasKey( $bloque, $GLOBALS['_sh_bloques'], 'falta el bloque ' . $bloque );
			$this->assertSame( 'convoca-shifts-style', $GLOBALS['_sh_bloques'][ $bloque ]['style'], 'la previsualización necesita la hoja' );
			$this->assertNotEmpty( $GLOBALS['_sh_bloques'][ $bloque ]['render_callback'], 'y su callback de servidor' );
		}
	}

	/**
	 * Los callbacks de los bloques delegan en los shortcodes: un solo sitio donde se pinta.
	 */
	public function test_los_bloques_delegan_en_los_shortcodes(): void {
		// El del calendario es el único que añade algo: un disparo del guion para la previsualización.
		$html = \Convoca\Shifts\convoca_shifts_calendario_centro();
		$bloque = \Convoca\Shifts\convoca_shifts_render_block_calendario();

		$this->assertStringContainsString( $html, $bloque, 'el bloque pinta lo mismo que el shortcode' );
		$this->assertStringContainsString( 'initCSTCalendar', $bloque, 'y añade el disparo de la previsualización' );

		$this->assertSame(
			\Convoca\Shifts\convoca_shifts_boton_apuntarse(),
			\Convoca\Shifts\convoca_shifts_render_block_boton_apuntarse()
		);
	}

	/**
	 * Deja una entrada como la actual, con su contenido.
	 *
	 * @param string $contenido Contenido.
	 */
	private function entrada( string $contenido ): void {
		$GLOBALS['_sh_singular'] = true;
		$GLOBALS['post']         = (object) array(
			'ID'           => 7,
			'post_content' => $contenido,
		);
	}

	/**
	 * Deja un widget activo en una barra lateral.
	 *
	 * @param string $base      Tipo de widget.
	 * @param int    $numero    Número de instancia.
	 * @param array  $instancia Contenido del widget.
	 */
	private function widget( string $base, int $numero, array $instancia ): void {
		$GLOBALS['_sh_sidebars']                            = array( 'sidebar-1' => array( $base . '-' . $numero ) );
		$GLOBALS['_wp_stores']['options'][ 'widget_' . $base ][ $numero ] = $instancia;
	}
}
