<?php
/**
 * La página propia del listado de turnos.
 *
 * `Admin_Turnos_List_Page` estaba al 2 % (1/49) y es la que hace que el listado del plugin sea el que
 * se ve: redirige el listado crudo de WordPress, registra la página oculta, arregla el enlace de la
 * barra de administración, pone el título del documento y pinta la pantalla con su botón de exportar.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use Convoca\Shifts\Admin_Turnos_List_Page;
use PHPUnit\Framework\TestCase;

/**
 * Redirección, registro de la página y pintado.
 */
final class ListaTurnosPaginaTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/class-admin-turnos-list.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-admin-turnos-list-page.php';
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
		// Se BORRAN, no se ponen a array(): otras pruebas del repo afirman que la clave no existe
		// («no se redirigió»), y dejarla vacía las rompía según el orden de ejecución.
		unset( $GLOBALS['typenow'] );
		unset(
			$GLOBALS['_sh_admin_paginas'],
			$GLOBALS['_sh_admin_nonces'],
			$GLOBALS['_sh_admin_nonce_ok'],
			$GLOBALS['_sh_admin_redirecciones'],
			$GLOBALS['_sh_admin_checks'],
			$GLOBALS['_sh_pantalla']
		);
		$GLOBALS['_wp_stores'] = array();
		$_GET                  = array();
	}

	/**
	 * El constructor engancha lo que necesita: carga del listado, barra y menú.
	 */
	public function test_el_constructor_engancha_sus_ganchos(): void {
		$pagina = new Admin_Turnos_List_Page();
		$this->assertInstanceOf( Admin_Turnos_List_Page::class, $pagina );

		$this->assertArrayHasKey( 'load-edit.php', $GLOBALS['_wp_stores']['acciones'] );
		$this->assertArrayHasKey( 'admin_menu', $GLOBALS['_wp_stores']['acciones'] );
		$this->assertArrayHasKey( 'admin_bar_menu', $GLOBALS['_wp_stores']['filtros'] );
	}

	/**
	 * La página se registra oculta (padre null), con su capacidad y su slug.
	 */
	public function test_la_pagina_se_registra_oculta(): void {
		$pagina = new Admin_Turnos_List_Page();
		$pagina->register_page();

		$this->assertCount( 1, $GLOBALS['_sh_admin_paginas'] );
		$registrada = $GLOBALS['_sh_admin_paginas'][0];

		$this->assertNull( $registrada['padre'], 'una página oculta no cuelga del menú' );
		$this->assertSame( 'convoca_shifts_manage_turnos', $registrada['cap'] );
		$this->assertSame( 'convoca-shifts-turnos-list', $registrada['slug'] );
		$this->assertSame( array( $pagina, 'render_page' ), $registrada['callback'] );
	}

	/**
	 * El título del documento solo se toca en esta pantalla.
	 */
	public function test_el_titulo_del_documento_solo_en_esta_pantalla(): void {
		$pagina = new Admin_Turnos_List_Page();

		$this->assertSame( 'Convoca — WordPress', $pagina->titulo_documento( 'Convoca — WordPress', '' ), 'sin page no se toca' );

		$_GET['page'] = 'otra-pagina';
		$this->assertSame( 'Convoca — WordPress', $pagina->titulo_documento( 'Convoca — WordPress', '' ) );

		$_GET['page'] = 'convoca-shifts-turnos-list';
		$titulo       = $pagina->titulo_documento( 'Convoca — WordPress', 'Turnos' );

		$this->assertStringStartsWith( 'Todos los Turnos', $titulo, 'la pestaña lleva el nombre de la pantalla' );
		$this->assertStringContainsString( 'Convoca — WordPress', $titulo, 'y no pierde el título del sitio' );
	}

	/**
	 * El listado crudo de turnos se lleva a la página propia; otro tipo no se toca.
	 */
	public function test_el_listado_crudo_se_redirige(): void {
		$pagina = new Admin_Turnos_List_Page();

		$GLOBALS['typenow'] = 'post';
		$pagina->redirect_native_list();
		$this->assertArrayNotHasKey( '_sh_admin_redirecciones', $GLOBALS, 'otro tipo de contenido no se redirige' );

		$GLOBALS['typenow'] = 'centro_turno';
		$_GET['page']       = 'convoca-shifts-turnos-list';
		$pagina->redirect_native_list();
		$this->assertArrayNotHasKey( '_sh_admin_redirecciones', $GLOBALS, 'con page propia tampoco' );
	}

	/**
	 * El enlace «Todos los turnos» de la barra apunta a la página del plugin.
	 */
	public function test_la_barra_de_administracion_apunta_a_la_pagina_propia(): void {
		$pagina = new Admin_Turnos_List_Page();
		$barra  = new \WP_Admin_Bar();

		$nodo       = new \stdClass();
		$nodo->id   = 'edit-centro_turno';
		$nodo->href = '/wp-admin/edit.php?post_type=centro_turno';

		$barra->add_node( $nodo );
		$pagina->customize_admin_bar( $barra );

		$this->assertStringContainsString(
			'page=convoca-shifts-turnos-list',
			$barra->get_node( 'edit-centro_turno' )->href,
			'la barra lleva a la lista del plugin'
		);
	}

	/**
	 * Sin nodo de turnos, la barra no se toca.
	 */
	public function test_sin_nodo_de_turnos_la_barra_no_se_toca(): void {
		$pagina = new Admin_Turnos_List_Page();
		$barra  = new \WP_Admin_Bar();

		$ajeno       = new \stdClass();
		$ajeno->id   = 'edit-post';
		$ajeno->href = '/wp-admin/edit.php';
		$barra->add_node( $ajeno );

		$pagina->customize_admin_bar( $barra );

		$this->assertSame( '/wp-admin/edit.php', $barra->get_node( 'edit-post' )->href );
		$this->assertFalse( $barra->get_node( 'edit-centro_turno' ), 'no se inventa un nodo' );
	}

	/**
	 * La pantalla se pinta con su encabezado, el botón de exportar con nonce y el buscador.
	 */
	public function test_la_pantalla_se_pinta_con_exportar_y_buscador(): void {
		$pagina = new Admin_Turnos_List_Page();

		ob_start();
		$pagina->render_page();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'wp-heading-inline', $html, 'la pantalla lleva encabezado' );
		$this->assertStringContainsString( 'Exportar a CSV', $html, 'el exportador que no tenía botón ahora lo tiene' );
		$this->assertContains( 'convoca_shifts_exportar_csv_action', $GLOBALS['_sh_admin_nonces'], 'con el nonce que espera el manejador' );
		$this->assertStringContainsString( 'name="convoca_shifts_action"', $html );
		$this->assertStringContainsString( 'value="exportar_csv"', $html );
		$this->assertStringContainsString( 'search_id', $html, 'el buscador de la tabla se pinta' );
	}
}
