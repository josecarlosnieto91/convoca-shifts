<?php
/**
 * El editor propio de turnos: puertas, título de la pestaña y guardas del guardado.
 *
 * Esta pantalla es la que escribe un turno desde el panel. Lo que se prueba aquí son sus puertas: quién
 * puede entrar, qué pasa cuando el formulario llega sin nonce o sin permiso, y que la pestaña del
 * navegador diga de qué turno es (issue #11). Los caminos que terminan en `exit` —la redirección del
 * editor por defecto y el guardado completo— no se pueden medir sin matar la prueba; sus guardas sí.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use Convoca\Shifts\Convoca_Shifts_Admin_Turno_Editor;
use Convoca\Shifts\Personas_Asignables;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once dirname( __DIR__, 2 ) . '/includes/class-admin-turno-editor.php';

/**
 * Puertas, título y guardas del editor.
 */
final class TurnoEditorTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();

		if ( ! class_exists( Personas_Asignables::class ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/class-personas-asignables.php';
		}

		Personas_Asignables::limpiar_cache();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$this->limpiar();
		Personas_Asignables::limpiar_cache();
	}

	/**
	 * Deja el arnés como estaba.
	 */
	private function limpiar(): void {
		foreach ( array( '_wp_stores', '_test_caps', '_sh_registro', '_sh_admin_paginas', '_sh_metaboxes_quitadas', '_sh_nonce_ok', '_sh_insert_error' ) as $clave ) {
			unset( $GLOBALS[ $clave ] );
		}

		$GLOBALS['_wp_stores'] = array();
		$GLOBALS['_test_caps'] = array();
		$_GET                  = array();
		$_POST                 = array();
	}

	/**
	 * El editor engancha sus cinco puertas y el filtro del título.
	 */
	public function test_engancha_sus_puertas(): void {
		$editor = new Convoca_Shifts_Admin_Turno_Editor();

		$enganches = $GLOBALS['_wp_stores']['acciones'] ?? array();
		$callbacks = array();
		foreach ( $enganches as $gancho => $lista ) {
			foreach ( (array) $lista as $callback ) {
				$callbacks[ $gancho ][] = $callback;
			}
		}

		foreach ( array( 'admin_menu', 'admin_post_convoca_shifts_save_turno', 'load-post-new.php', 'add_meta_boxes' ) as $gancho ) {
			$this->assertArrayHasKey( $gancho, $callbacks, 'falta el enganche ' . $gancho );
		}

		$this->assertContains( array( $editor, 'register_page' ), $callbacks['admin_menu'] );
	}

	/**
	 * Fuera de sus pantallas, el título de la pestaña no se toca.
	 */
	public function test_el_titulo_no_se_toca_fuera_de_sus_pantallas(): void {
		$editor = new Convoca_Shifts_Admin_Turno_Editor();

		$this->assertSame( 'Otra cosa — WordPress', $editor->titulo_documento( 'Otra cosa — WordPress', '' ) );
	}

	/**
	 * En la pantalla del editor, el título dice qué se está editando.
	 */
	public function test_el_titulo_en_la_pantalla_del_editor(): void {
		$_GET['page'] = Convoca_Shifts_Admin_Turno_Editor::SLUG;
		$_GET['post'] = 7;
		$GLOBALS['_wp_stores']['posts'][7] = (object) array(
			'ID'         => 7,
			'post_type'  => 'centro_turno',
			'post_title' => 'Turno del lunes',
		);

		$titulo = ( new Convoca_Shifts_Admin_Turno_Editor() )->titulo_documento( ' — WordPress', '' );

		$this->assertStringStartsWith( 'Editar Turno', $titulo );
		$this->assertStringContainsString( 'Turno del lunes', $titulo, 'la pestaña dice de qué turno es' );
	}

	/**
	 * Llegando por el enlace estándar de edición de un turno, también.
	 */
	public function test_el_titulo_desde_el_enlace_estandar(): void {
		$_GET['post'] = 8;
		$GLOBALS['_wp_stores']['posts'][8] = (object) array(
			'ID'         => 8,
			'post_type'  => 'centro_turno',
			'post_title' => 'Turno del martes',
		);

		$titulo = ( new Convoca_Shifts_Admin_Turno_Editor() )->titulo_documento( ' — WordPress', '' );

		$this->assertStringContainsString( 'Editar Turno: Turno del martes', $titulo );
	}

	/**
	 * Pero no en la edición de una entrada normal.
	 */
	public function test_el_titulo_no_se_toca_en_otras_entradas(): void {
		$_GET['post'] = 9;
		$GLOBALS['_wp_stores']['posts'][9] = (object) array(
			'ID'         => 9,
			'post_type'  => 'post',
			'post_title' => 'Una entrada',
		);

		$this->assertSame( 'Entrada — WordPress', ( new Convoca_Shifts_Admin_Turno_Editor() )->titulo_documento( 'Entrada — WordPress', '' ) );
	}

	/**
	 * La página se registra oculta, con su capacidad propia.
	 */
	public function test_la_pagina_se_registra_oculta(): void {
		( new Convoca_Shifts_Admin_Turno_Editor() )->register_page();

		$pagina = $GLOBALS['_sh_admin_paginas'][0];

		$this->assertNull( $pagina['padre'], 'no aparece en el menú' );
		$this->assertSame( 'convoca_shifts_manage_turnos', $pagina['cap'] );
		$this->assertSame( Convoca_Shifts_Admin_Turno_Editor::SLUG, $pagina['slug'] );
	}

	/**
	 * Quita su caja de edición del editor clásico.
	 */
	public function test_quita_su_caja_del_editor_clasico(): void {
		( new Convoca_Shifts_Admin_Turno_Editor() )->remove_metabox();

		$this->assertSame(
			array(
				'id'       => 'convoca_shifts_turno_opciones',
				'pantalla' => 'centro_turno',
				'contexto' => 'normal',
			),
			$GLOBALS['_sh_metaboxes_quitadas'][0]
		);
	}

	/**
	 * La redirección del editor por defecto no toca nada si no es un turno.
	 */
	public function test_la_redireccion_no_toca_otras_pantallas(): void {
		$GLOBALS['typenow'] = 'post';

		( new Convoca_Shifts_Admin_Turno_Editor() )->redirect_from_default();

		$this->assertArrayNotHasKey( '_sh_admin_redirecciones', $GLOBALS, 'no se redirige' );
	}

	/**
	 * Editar un turno ajeno sin permiso se corta en la puerta.
	 */
	public function test_sin_permiso_no_se_entra_al_editor(): void {
		$_GET['id'] = 7;
		$GLOBALS['_wp_stores']['posts'][7] = (object) array(
			'ID'        => 7,
			'post_type' => 'centro_turno',
		);
		$GLOBALS['_test_caps']['edit_post'] = false;

		$this->expectException( RuntimeException::class );

		( new Convoca_Shifts_Admin_Turno_Editor() )->render();
	}

	/**
	 * Un formulario sin nonce no se guarda.
	 */
	public function test_el_guardado_sin_nonce_se_corta(): void {
		$this->expectException( RuntimeException::class );

		( new Convoca_Shifts_Admin_Turno_Editor() )->handle_save();
	}

	/**
	 * Con nonce inválido tampoco.
	 */
	public function test_el_guardado_con_nonce_invalido_se_corta(): void {
		$_POST['_convoca_shifts_nonce'] = 'caducado';
		$GLOBALS['_sh_nonce_ok']        = false;

		$this->expectException( RuntimeException::class );

		( new Convoca_Shifts_Admin_Turno_Editor() )->handle_save();
	}

	/**
	 * Editar exige permiso sobre ese turno; crear exige poder crear entradas. Son permisos distintos.
	 */
	public function test_editar_y_crear_piden_permisos_distintos(): void {
		$_POST['_convoca_shifts_nonce'] = 'bueno';

		// Editar sin permiso sobre el turno.
		$_POST['post_id']                   = 7;
		$GLOBALS['_test_caps']['edit_post'] = false;

		try {
			( new Convoca_Shifts_Admin_Turno_Editor() )->handle_save();
			$this->fail( 'editar sin permiso debería cortarse' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'permisos', $e->getMessage() );
		}

		// Crear sin poder crear entradas.
		$_POST['post_id'] = 0;
		unset( $GLOBALS['_test_caps']['edit_post'] );
		$GLOBALS['_test_caps']['edit_posts'] = false;

		$this->expectException( RuntimeException::class );

		( new Convoca_Shifts_Admin_Turno_Editor() )->handle_save();
	}

	/**
	 * Si la creación falla, se avisa en vez de seguir con un identificador cero.
	 */
	public function test_si_la_creacion_falla_se_avisa(): void {
		$_POST['_convoca_shifts_nonce']      = 'bueno';
		$_POST['post_id']                    = 0;
		$_POST['convoca_shifts_fecha']       = '2026-10-26';
		$_POST['convoca_shifts_hora_ini']    = '10:00';
		$GLOBALS['_test_caps']['edit_posts'] = true;
		$GLOBALS['_sh_insert_error']         = true;

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessageMatches( '/crear/' );

		( new Convoca_Shifts_Admin_Turno_Editor() )->handle_save();
	}
}
