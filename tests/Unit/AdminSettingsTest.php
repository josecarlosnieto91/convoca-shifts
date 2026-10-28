<?php
/**
 * Los ajustes de turnos y el diagnóstico del sistema.
 *
 * La pantalla de ajustes guarda las horas del centro, la página del calendario y los avisos; el
 * diagnóstico dice qué falta para que el plugin funcione. Lo que se prueba aquí es lo que puede mentir:
 * las opciones que se registran y el estado que se informa de cada comprobación.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/admin-settings.php';

/**
 * Ajustes y diagnóstico.
 */
final class AdminSettingsTest extends TestCase {

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
		foreach ( array( '_wp_stores', '_sh_db', '_sh_settings', '_sh_admin_paginas', '_sh_plugins_activos' ) as $clave ) {
			unset( $GLOBALS[ $clave ] );
		}

		$GLOBALS['_wp_stores'] = array();
		$GLOBALS['_sh_db']     = array();
	}

	/**
	 * Las dos pantallas se registran con su capacidad.
	 */
	public function test_las_dos_pantallas(): void {
		\Convoca\Shifts\convoca_shifts_add_settings_menu();

		$paginas = $GLOBALS['_sh_admin_paginas'];

		$this->assertCount( 2, $paginas );
		$this->assertSame( 'manage_inscripciones', $paginas[0]['cap'], 'los ajustes piden la capacidad de inscripciones' );
		$this->assertSame( 'manage_options', $paginas[1]['cap'], 'el diagnóstico, la de administración' );
		$this->assertSame( 'Convoca\\Shifts\\convoca_shifts_status_page', $paginas[1]['callback'] );
	}

	/**
	 * Las opciones que se registran, con su grupo.
	 */
	public function test_las_opciones_se_registran(): void {
		\Convoca\Shifts\convoca_shifts_register_plugin_settings();

		$nombres = array_column( $GLOBALS['_sh_settings'], 'nombre' );

		foreach ( array( 'convoca_shifts_calendar_page_url', 'convoca_shifts_hora_apertura', 'convoca_shifts_hora_cierre', 'convoca_shifts_aviso_socio_en', 'convoca_shifts_aviso_admin_cada', 'convoca_shifts_ventana_dias' ) as $opcion ) {
			$this->assertContains( $opcion, $nombres, 'falta el ajuste ' . $opcion );
		}

		$this->assertSame( 'convoca_shifts_settings_group', $GLOBALS['_sh_settings'][0]['grupo'] );
	}

	/**
	 * La página del calendario se busca por su shortcode.
	 */
	public function test_se_encuentra_la_pagina_por_su_shortcode(): void {
		$GLOBALS['_sh_db']['get_results'] = array(
			(object) array(
				'ID'           => 12,
				'post_content' => 'Horario del centro: [calendario_centro]',
			),
		);

		$this->assertSame( 12, \Convoca\Shifts\convoca_shifts_find_page_by_shortcode( '[calendario_centro]' ) );
	}

	/**
	 * Si hay varias, se queda con la primera; y una que no lo lleva se descarta.
	 *
	 * `has_shortcode()` mira el texto, no si el shortcode se pinta: la primera página que lo lleve gana.
	 * Se fija el contrato tal como es —la primera coincidencia— para que un cambio de criterio se note.
	 */
	public function test_se_queda_con_la_primera_pagina_que_lo_lleva(): void {
		$GLOBALS['_sh_db']['get_results'] = array(
			(object) array(
				'ID'           => 13,
				'post_content' => 'Una página que menciona el calendario sin el shortcode',
			),
			(object) array(
				'ID'           => 14,
				'post_content' => 'Ponemos [calendario_centro] de verdad',
			),
		);

		$this->assertSame( 14, \Convoca\Shifts\convoca_shifts_find_page_by_shortcode( '[calendario_centro]' ), 'se salta la que no lo lleva' );
	}

	/**
	 * Si no hay página, se devuelve vacío (no una página cualquiera).
	 *
	 * La salida temprana de «sin resultados» es redundante con el bucle (una lista vacía no recorre
	 * nada), así que quitarla no cambia el resultado y no se puede distinguir con una mutación.
	 */
	public function test_sin_pagina_no_se_devuelve_nada(): void {
		$GLOBALS['_sh_db']['get_results'] = array();

		$this->assertEmpty( \Convoca\Shifts\convoca_shifts_find_page_by_shortcode( '[calendario_centro]' ) );
	}

	/**
	 * El diagnóstico avisa cuando falta un plugin y calla cuando está.
	 */
	public function test_el_diagnostico_de_plugins(): void {
		$GLOBALS['_sh_plugins_activos']   = array();
		$GLOBALS['_sh_db']['get_results'] = array();

		$checks = \Convoca\Shifts\convoca_shifts_get_system_checks();

		$this->assertSame( 'warning', $checks[0]['status'], 'sin los plugins, aviso' );
		$this->assertStringContainsString( 'Convoca Core', $checks[0]['title'] );
		$this->assertNotEmpty( $checks[0]['fix'], 'y se dice qué hacer' );

		$GLOBALS['_sh_plugins_activos'] = array( 'convoca-core/convoca-core.php', 'convoca-members/convoca-members.php' );

		$checks = \Convoca\Shifts\convoca_shifts_get_system_checks();

		$this->assertSame( 'ok', $checks[0]['status'] );
		$this->assertSame( '', $checks[0]['fix'], 'si está activo no hay nada que arreglar' );
	}

	/**
	 * Y avisa cuando falta la página del calendario, con la página detectada cuando existe.
	 */
	public function test_el_diagnostico_de_la_pagina(): void {
		$GLOBALS['_sh_db']['get_results'] = array();

		$checks = \Convoca\Shifts\convoca_shifts_get_system_checks();
		$pagina = $this->por_titulo( $checks, 'Página: Calendario' );

		$this->assertSame( 'error', $pagina['status'], 'sin página, error' );
		$this->assertStringContainsString( 'Crea una página', $pagina['fix'] );

		$GLOBALS['_sh_db']['get_results'] = array(
			(object) array(
				'ID'           => 12,
				'post_content' => '[convoca_calendario]',
			),
		);
		$GLOBALS['_wp_stores']['posts'][12] = (object) array(
			'ID'         => 12,
			'post_title' => 'Calendario del centro',
		);

		$checks = \Convoca\Shifts\convoca_shifts_get_system_checks();
		$pagina = $this->por_titulo( $checks, 'Página: Calendario' );

		$this->assertSame( 'ok', $pagina['status'] );
		$this->assertStringContainsString( 'Calendario del centro', $pagina['message'], 'se dice cuál se ha detectado' );
	}

	/**
	 * El diagnóstico se aporta al panel de salud con el prefijo del módulo.
	 */
	public function test_se_aporta_al_panel_de_salud(): void {
		$GLOBALS['_sh_db']['get_results'] = array();

		$bloques = \Convoca\Shifts\convoca_shifts_aportar_checks( array( 'otros' => array( 1 ) ) );

		$this->assertArrayHasKey( 'otros', $bloques, 'no se pisa lo que aportan otros' );
		$this->assertArrayHasKey( 'turnos', $bloques );
		$this->assertStringStartsWith( 'Turnos: ', $bloques['turnos'][0]['title'], 'cada comprobación dice de quién es' );
		$this->assertArrayHasKey( 'status', $bloques['turnos'][0] );
		$this->assertArrayHasKey( 'fix', $bloques['turnos'][0] );
	}

	/**
	 * **La guarda de la pantalla «Estado» comprueba el nombre de clase de verdad.**
	 *
	 * Estaba así: `if ( ! class_exists( 'Convoca\Shifts$1' ) )`. Ese `$1` es el resto de una sustitución de
	 * regex a medio hacer, y en PHP una cadena entre comillas simples lo deja literal: `class_exists()`
	 * contestaba **siempre** que no. No rompía porque detrás hay un `require_once` (idempotente), pero la
	 * comprobación no comprobaba nada y con un `require` a secas sería un fatal en la pantalla de ajustes.
	 *
	 * Se mira el código fuente a propósito: el efecto observable (la clase queda cargada) es idéntico en los
	 * dos casos, así que ninguna llamada puede distinguirlos. Lo que distingue es la cadena.
	 */
	public function test_la_guarda_de_estado_usa_el_nombre_completo_de_la_clase(): void {
		$fuente = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/admin-settings.php' );

		$this->assertStringNotContainsString(
			"class_exists( 'Convoca\\Shifts$1' )",
			$fuente,
			'la guarda no puede llevar un grupo de captura de regex'
		);
		$this->assertMatchesRegularExpression(
			'/class_exists\(\s*\'Convoca\\\\Shifts\\\\Convoca_Shifts_Admin_Status\'\s*\)/',
			$fuente,
			'la guarda tiene que nombrar la clase completa'
		);
	}

	/**
	 * Busca una comprobación por su título.
	 *
	 * @param array  $checks Comprobaciones.
	 * @param string $titulo Título.
	 * @return array
	 */
	private function por_titulo( array $checks, string $titulo ): array {
		foreach ( $checks as $check ) {
			if ( $titulo === $check['title'] ) {
				return $check;
			}
		}

		$this->fail( 'no se encontró la comprobación ' . $titulo );

		return array();
	}
}
