<?php
/**
 * Alta rápida de turnos desde el panel.
 *
 * El plugin quita la pantalla de «Añadir nuevo» de WordPress y pone la suya, redirige los enlaces que
 * apuntaban a la vieja y guarda el turno por `admin-post.php`. Lo que se prueba son las puertas y los
 * avisos: sin nonce o sin permiso no se crea nada, y el listado dice qué pasó con el alta.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once dirname( __DIR__, 2 ) . '/includes/admin-turno-rapido.php';

/**
 * Menú, redirecciones, guardas y avisos.
 */
final class TurnoRapidoTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();

		if ( ! class_exists( '\Convoca\Shifts\Horario' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/horario.php';
		}
		if ( ! class_exists( '\Convoca\Shifts\Personas_Asignables' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/class-personas-asignables.php';
		}
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
		foreach ( array( '_wp_stores', '_test_caps', '_sh_admin_paginas', '_sh_submenus_quitados', '_sh_admin_redirecciones', '_sh_pantalla', '_sh_nonce_ok', '_sh_registro' ) as $clave ) {
			unset( $GLOBALS[ $clave ] );
		}

		$GLOBALS['_wp_stores'] = array();
		$GLOBALS['_test_caps'] = array();
		$_GET                  = array();
		$_POST                 = array();
	}

	/**
	 * La pantalla propia se añade y la de WordPress se quita.
	 */
	public function test_pone_su_pantalla_y_quita_la_de_wordpress(): void {
		\Convoca\Shifts\convoca_shifts_add_turno_rapido_menu();

		$pagina = $GLOBALS['_sh_admin_paginas'][0];

		$this->assertSame( 'convoca_shifts_manage_turnos', $pagina['cap'] );
		$this->assertSame( 'convoca_shifts_turno_rapido', $pagina['slug'] );
		$this->assertSame(
			'post-new.php?post_type=centro_turno',
			$GLOBALS['_sh_submenus_quitados'][0]['slug'],
			'se quita la de WordPress para no tener dos puertas'
		);
	}

	/**
	 * Los enlaces de «Añadir nuevo» apuntan a la pantalla propia; los demás no se tocan.
	 */
	public function test_los_enlaces_de_anadir_nuevo_se_redirigen(): void {
		$url = \Convoca\Shifts\convoca_shifts_redirect_add_new_url( 'https://ejemplo.example.test/wp-admin/post-new.php?post_type=centro_turno', 'post-new.php?post_type=centro_turno' );

		$this->assertStringContainsString( 'page=convoca_shifts_turno_rapido', $url );

		$this->assertSame(
			'https://ejemplo.example.test/wp-admin/edit.php',
			\Convoca\Shifts\convoca_shifts_redirect_add_new_url( 'https://ejemplo.example.test/wp-admin/edit.php', 'edit.php' ),
			'un enlace que no es de alta se queda como estaba'
		);
	}

	/**
	 * Entrar a la pantalla de WordPress a mano no redirige si no es un turno.
	 *
	 * La rama que SÍ redirige termina en `exit`, así que solo se puede medir el lado negativo: quitar la
	 * guarda no cambia lo observable en este caso, y se deja dicho en vez de fingir una comprobación.
	 */
	public function test_la_redireccion_forzada_no_toca_otras_pantallas(): void {
		$GLOBALS['pagenow'] = 'post-new.php';
		$_GET['post_type']  = 'post';

		\Convoca\Shifts\convoca_shifts_force_redirect_standard_editor();

		$this->assertArrayNotHasKey( '_sh_admin_redirecciones', $GLOBALS );
	}

	/**
	 * Sin permiso no se crea nada: se corta antes de tocar la base.
	 */
	public function test_el_alta_rapida_sin_permiso_se_corta(): void {
		$_POST['convoca_shifts_quick_add_nonce'] = 'bueno';
		$GLOBALS['_test_caps']['convoca_shifts_manage_turnos'] = false;

		try {
			\Convoca\Shifts\convoca_shifts_process_quick_add_turno();
			$this->fail( 'sin permiso debería cortarse' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'permisos', $e->getMessage() );
		}

		$this->assertArrayNotHasKey( 'insertados', $GLOBALS['_wp_stores'] ?? array(), 'no se crea ningún turno' );
	}

	/**
	 * El aviso de éxito sale en el listado de turnos.
	 */
	public function test_el_aviso_de_exito(): void {
		$this->en_el_listado();
		$_GET['convoca_shifts_msg'] = 'created';

		ob_start();
		\Convoca\Shifts\convoca_shifts_turno_rapido_notices();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Turno creado correctamente', $html );
	}

	/**
	 * El de error enseña el detalle que devolvió el alta.
	 */
	public function test_el_aviso_de_error_con_detalle(): void {
		$this->en_el_listado();
		$_GET['convoca_shifts_msg'] = 'error';
		$_GET['convoca_shifts_err'] = 'La fecha no es válida.';

		ob_start();
		\Convoca\Shifts\convoca_shifts_turno_rapido_notices();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'La fecha no es válida.', $html );
	}

	/**
	 * Y sin detalle, dice que no lo hay en vez de dejar el mensaje a medias.
	 */
	public function test_el_aviso_de_error_sin_detalle(): void {
		$this->en_el_listado();
		$_GET['convoca_shifts_msg'] = 'error';

		ob_start();
		\Convoca\Shifts\convoca_shifts_turno_rapido_notices();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'no hay mas detalle', $html );
	}

	/**
	 * Fuera del listado de turnos no se pinta ningún aviso.
	 */
	public function test_fuera_del_listado_no_hay_aviso(): void {
		$GLOBALS['_sh_pantalla']    = (object) array( 'id' => 'dashboard' );
		$_GET['convoca_shifts_msg'] = 'created';

		ob_start();
		\Convoca\Shifts\convoca_shifts_turno_rapido_notices();
		$html = (string) ob_get_clean();

		$this->assertSame( '', $html );
	}

	/**
	 * Ni dentro si no hay nada que contar.
	 */
	public function test_sin_mensaje_no_hay_aviso(): void {
		$this->en_el_listado();

		ob_start();
		\Convoca\Shifts\convoca_shifts_turno_rapido_notices();
		$html = (string) ob_get_clean();

		$this->assertSame( '', $html );
	}

	/**
	 * Se pone en la pantalla del listado de turnos.
	 */
	private function en_el_listado(): void {
		$GLOBALS['_sh_pantalla'] = (object) array( 'id' => 'edit-centro_turno' );
	}
}
