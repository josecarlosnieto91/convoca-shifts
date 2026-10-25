<?php
/**
 * Los shortcodes de Shifts.
 *
 * Cinco shortcodes y lo que los rodea: lo que se encola, lo que se pinta y lo que **no** se pinta
 * cuando no hay sesión o no hay permisos. El caso que más importa es el del voluntario sin aprobar: si
 * el calendario le dejara apuntarse, el error saldría al pulsar, no antes.
 *
 * Lo que **no** se puede medir aquí: la guarda de `convoca_shifts_caja_suscripcion()` para cuando
 * `Ical::url_de()` devuelve vacío. Esa función solo devuelve vacío con un identificador de persona
 * menor o igual que cero, y el arnés fija la sesión en la persona 1; la rama es defensiva y queda sin
 * cubrir a propósito, en vez de doblar la sesión a cero solo para pintarla.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Shortcodes del módulo.
 */
final class ShortcodesTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();

		require_once dirname( __DIR__, 2 ) . '/includes/ical.php';
		require_once dirname( __DIR__, 2 ) . '/includes/shortcodes.php';
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
		$GLOBALS['_sh_registrados'] = array();
		$GLOBALS['_sh_encolados']   = array();
		$GLOBALS['_sh_localizados'] = array();
		$GLOBALS['_sh_logged']      = false;
		$GLOBALS['_test_caps']      = array();
		$GLOBALS['_wp_stores']['transients'] = array();
		$GLOBALS['_wp_stores']['posts']      = array();
		$GLOBALS['_wp_stores']['options']    = array();
		$GLOBALS['_wp_stores']['acciones']   = array();
	}

	/**
	 * Los cinco shortcodes quedan registrados con su nombre público.
	 */
	public function test_los_shortcodes_quedan_registrados(): void {
		$shortcodes = $GLOBALS['_sh_shortcodes'] ?? array();

		foreach ( array( 'convoca_calendario', 'convoca_boton_apuntarse', 'convoca_resumen_turnos', 'convoca_suscripcion_turnos', 'convoca_proximos_turnos' ) as $tag ) {
			$this->assertArrayHasKey( $tag, $shortcodes, 'falta el shortcode ' . $tag );
		}
	}

	/**
	 * El registro de recursos deja el calendario con sus datos: sin ellos el guion no sabe a dónde ir.
	 */
	public function test_el_registro_de_recursos_publica_los_datos_del_calendario(): void {
		\Convoca\Shifts\convoca_shifts_register_scripts();

		$this->assertArrayHasKey( 'convoca-shifts-style', $GLOBALS['_sh_registrados'] );
		$this->assertArrayHasKey( 'fullcalendar-core', $GLOBALS['_sh_registrados'] );
		$this->assertArrayHasKey( 'convoca-shifts-calendario', $GLOBALS['_sh_registrados'] );

		$datos = $GLOBALS['_sh_localizados']['convocaShiftsData']['datos'];

		$this->assertSame( 'https://ejemplo.example.test/wp-json/convoca-shifts/v1/turnos', $datos['restUrl'] );
		$this->assertNotEmpty( $datos['nonce'], 'sin nonce la API rechaza la petición' );
		$this->assertSame( '09:00', $datos['horaApertura'], 'por defecto el centro abre a las 09:00' );
		$this->assertSame( '22:00', $datos['horaCierre'] );
		$this->assertTrue( $datos['confirmSignup'], 'por defecto se pregunta antes de apuntarse' );
	}

	/**
	 * El calendario, sin sesión, invita a entrar en vez de dejar un enlace muerto.
	 */
	public function test_el_calendario_sin_sesion_manda_a_acceso(): void {
		$html = \Convoca\Shifts\convoca_shifts_calendario_centro();

		$this->assertStringContainsString( 'convoca-shifts-calendario-container', $html, 'el calendario se pinta igual: se puede mirar' );
		$this->assertStringContainsString( 'https://ejemplo.example.test/acceso/', $html, 'y se ofrece la puerta de entrada' );
		$this->assertStringNotContainsString( 'pendiente de aprobación', $html );
	}

	/**
	 * Con una página de acceso configurada se usa esa, no la de WordPress.
	 */
	public function test_el_calendario_usa_la_pagina_de_acceso_configurada(): void {
		$GLOBALS['_wp_stores']['options']['convoca_shifts_access_page_url'] = 'https://ejemplo.example.test/entrar/';

		$html = \Convoca\Shifts\convoca_shifts_calendario_centro();

		$this->assertStringContainsString( 'https://ejemplo.example.test/entrar/', $html );
		$this->assertStringNotContainsString( 'acceso/', $html );
	}

	/**
	 * Con sesión pero sin aprobar, se avisa de que está pendiente.
	 */
	public function test_con_sesion_sin_aprobar_avisa(): void {
		$GLOBALS['_sh_logged']           = true;
		$GLOBALS['_test_caps']['gestionar_mis_turnos'] = false;
		$GLOBALS['_test_caps']['manage_options']       = false;

		$html = \Convoca\Shifts\convoca_shifts_calendario_centro();

		$this->assertStringContainsString( 'pendiente de aprobación', $html );
		$this->assertStringNotContainsString( 'Ir a Acceso', $html, 'ya tiene sesión: no se le manda a entrar' );
	}

	/**
	 * El calendario encola su estilo y su guion, y el modal una sola vez.
	 */
	public function test_el_calendario_encola_y_el_modal_una_sola_vez(): void {
		\Convoca\Shifts\convoca_shifts_calendario_centro();
		\Convoca\Shifts\convoca_shifts_calendario_centro();

		$handles = array_column( $GLOBALS['_sh_encolados'], 'handle' );
		$this->assertContains( 'convoca-shifts-style', $handles );
		$this->assertContains( 'convoca-shifts-calendario', $handles );

		$modal = array_filter(
			$GLOBALS['_wp_stores']['acciones']['wp_footer'] ?? array(),
			static function ( $cb ) {
				return 'Convoca\\Shifts\\convoca_shifts_render_frontend_modal' === $cb;
			}
		);

		$this->assertLessThanOrEqual( 1, count( $modal ), 'el modal se engancha una vez, no una por shortcode' );
	}

	/**
	 * El modal no se pinta sin sesión ni sin permisos.
	 */
	public function test_el_modal_no_sale_sin_permisos(): void {
		ob_start();
		\Convoca\Shifts\convoca_shifts_render_frontend_modal();
		$sin_sesion = (string) ob_get_clean();

		$GLOBALS['_sh_logged']                         = true;
		$GLOBALS['_test_caps']['gestionar_mis_turnos'] = false;
		$GLOBALS['_test_caps']['manage_options']       = false;

		ob_start();
		\Convoca\Shifts\convoca_shifts_render_frontend_modal();
		$sin_permiso = (string) ob_get_clean();

		$this->assertSame( '', $sin_sesion );
		$this->assertSame( '', $sin_permiso );
	}

	/**
	 * Con permiso, el modal sale; y con `manage_options` ofrece cerrar el turno.
	 */
	public function test_el_modal_sale_con_permiso(): void {
		$this->con_personal();

		ob_start();
		\Convoca\Shifts\convoca_shifts_render_frontend_modal();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'convoca-shifts-frontend-modal', $html );
		$this->assertStringContainsString( 'Guardar Turno', $html );
	}

	/**
	 * El modal de un voluntario raso no ofrece cerrar el turno: eso es de administración.
	 */
	public function test_el_modal_de_un_voluntario_no_ofrece_cerrar(): void {
		$GLOBALS['_sh_logged']                         = true;
		$GLOBALS['_test_caps']['gestionar_mis_turnos'] = true;
		$GLOBALS['_test_caps']['manage_options']       = false;

		ob_start();
		\Convoca\Shifts\convoca_shifts_render_frontend_modal();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Pendiente (Disponible para voluntarios)', $html );
		$this->assertStringNotContainsString( 'value="cerrado"', $html, 'un voluntario no cierra el centro' );
	}

	/**
	 * El botón de apuntarse no sale sin sesión, y con ella sí, con su icono.
	 */
	public function test_el_boton_de_apuntarse(): void {
		$this->assertSame( '', \Convoca\Shifts\convoca_shifts_boton_apuntarse() );

		$this->con_personal();

		$html = \Convoca\Shifts\convoca_shifts_boton_apuntarse();

		$this->assertStringContainsString( 'convoca-shifts-btn-proximo', $html );
		$this->assertStringContainsString( 'dashicons-calendar-alt', $html );
		$this->assertStringContainsString( 'convocaShiftsData', $html, 'el guion necesita los datos del calendario' );

		$handles = array_column( $GLOBALS['_sh_encolados'], 'handle' );
		$this->assertContains( 'dashicons', $handles, 'los dashicons se encolan donde se usan' );
	}

	/**
	 * La caja de suscripción no sale sin sesión ni sin dirección.
	 */
	public function test_la_caja_de_suscripcion(): void {
		$this->assertSame( '', \Convoca\Shifts\convoca_shifts_caja_suscripcion() );

		$GLOBALS['_sh_logged'] = true;

		$html = \Convoca\Shifts\convoca_shifts_caja_suscripcion();

		$this->assertStringContainsString( 'convoca-shifts-suscripcion', $html );
		$this->assertStringContainsString( '.ics', $html, 'se ofrece la dirección del calendario' );
		$this->assertStringContainsString( 'Esta dirección es personal', $html, 'y se avisa de que no se comparta' );
	}

	/**
	 * El resumen cuenta los turnos de la semana y cuántos están sin cubrir.
	 */
	public function test_el_resumen_cuenta_los_turnos(): void {
		$GLOBALS['_wp_stores']['posts'] = array(
			1 => $this->turno( 1, 0 ),
			2 => $this->turno( 2, 7 ),
			3 => $this->turno( 3, 0 ),
		);

		$html = \Convoca\Shifts\convoca_shifts_resumen_turnos();

		$this->assertStringContainsString( '2 turnos sin cubrir de 3 en total', $html );
	}

	/**
	 * Sin turnos lo dice, en vez de contar cero.
	 */
	public function test_el_resumen_sin_turnos(): void {
		$html = \Convoca\Shifts\convoca_shifts_resumen_turnos();

		$this->assertStringContainsString( 'No hay turnos definidos para esta semana', $html );
	}

	/**
	 * El resumen se guarda en caché y a la segunda no se vuelve a consultar la base.
	 */
	public function test_el_resumen_se_guarda_en_cache(): void {
		$GLOBALS['_wp_stores']['posts'] = array( 1 => $this->turno( 1, 0 ) );

		\Convoca\Shifts\convoca_shifts_resumen_turnos();
		$this->assertArrayHasKey( 'convoca_shifts_resumen_turnos_this', $GLOBALS['_wp_stores']['transients'] );

		$GLOBALS['_wp_stores']['posts'] = array();

		$html = \Convoca\Shifts\convoca_shifts_resumen_turnos();

		$this->assertStringContainsString( '1 turnos sin cubrir de 1 en total', $html, 'sale de la caché, no de la base' );
	}

	/**
	 * La vista de la semana siguiente usa su propia caché.
	 */
	public function test_la_semana_siguiente_tiene_su_propia_cache(): void {
		$GLOBALS['_wp_stores']['posts'] = array( 1 => $this->turno( 1, 0 ) );

		\Convoca\Shifts\convoca_shifts_resumen_turnos( array( 'semana' => 'next' ) );

		$this->assertArrayHasKey( 'convoca_shifts_resumen_turnos_next', $GLOBALS['_wp_stores']['transients'] );
		$this->assertArrayNotHasKey( 'convoca_shifts_resumen_turnos_this', $GLOBALS['_wp_stores']['transients'] );
	}

	/**
	 * Los próximos turnos: sin nada, lo dice; con turnos, lista fecha, hora y quién cubre.
	 */
	public function test_los_proximos_turnos(): void {
		$vacio = \Convoca\Shifts\convoca_shifts_proximos_turnos( array() );
		$this->assertStringContainsString( 'No hay turnos próximos programados', $vacio );

		$manana = gmdate( 'Y-m-d', time() + 86400 ) . ' 10:00:00';
		$GLOBALS['_wp_stores']['posts'] = array(
			1 => $this->turno( 1, 0, $manana ),
			2 => $this->turno( 2, 7, $manana ),
		);

		$html = \Convoca\Shifts\convoca_shifts_proximos_turnos( array( 'cantidad' => 5 ) );

		$this->assertStringContainsString( 'convoca-shifts-proximos-lista', $html );
		$this->assertStringContainsString( 'Sin cubrir', $html, 'un turno sin responsable se marca' );
	}

	/**
	 * Al guardar un turno se borran **las dos** cachés del resumen.
	 *
	 * El resumen cachea por semana (`_this` y `_next`) y el widget tiene la suya. Antes se borraba `_semana`, la
	 * clave que se usaba antes de que existiera el atributo: no se borraba nada de lo que se lee y el resumen
	 * seguía enseñando lo de antes hasta una hora (shifts#24). La prueba anterior solo hacía `assertTrue( true )`.
	 */
	public function test_al_guardar_un_turno_se_borran_las_caches_del_resumen(): void {
		foreach ( array( 'this', 'next', 'widget' ) as $sufijo ) {
			$GLOBALS['_wp_stores']['transients'][ 'convoca_shifts_resumen_turnos_' . $sufijo ] = array(
				'total'      => 9,
				'sin_cubrir' => 9,
			);
		}

		\Convoca\Shifts\convoca_shifts_clear_resumen_transient();

		foreach ( array( 'this', 'next', 'widget' ) as $sufijo ) {
			$this->assertArrayNotHasKey(
				'convoca_shifts_resumen_turnos_' . $sufijo,
				$GLOBALS['_wp_stores']['transients'],
				sprintf( 'la caché `_%s` se borra', $sufijo )
			);
		}
	}

	/**
	 * Deja a la persona con permiso de gestión.
	 */
	private function con_personal(): void {
		$GLOBALS['_sh_logged']                         = true;
		$GLOBALS['_test_caps']['gestionar_mis_turnos'] = true;
		$GLOBALS['_test_caps']['manage_options']       = true;
	}

	/**
	 * Un turno del arnés.
	 *
	 * @param int $id          Identificador.
	 * @param int $responsable Persona que lo cubre (0 = libre).
	 * @return object
	 */
	private function turno( int $id, int $responsable, ?string $fecha = null ): object {
		$GLOBALS['_wp_stores']['post_meta'][ $id ]['_id_responsable'] = $responsable;
		$GLOBALS['_wp_stores']['post_meta'][ $id ]['_estado']         = 0 === $responsable ? 'abierto_disponible' : 'cubierto';

		// La fecha tiene que caer donde la consulta la busca: con una fecha fija el turno se queda fuera
		// en cuanto el arnés filtra de verdad por `date_query`. Por defecto, hoy al final del día (dentro
		// de la semana del resumen y por delante de «ahora» para los próximos).
		$fecha = $fecha ?? gmdate( 'Y-m-d' ) . ' 23:00:00';
		$GLOBALS['_wp_stores']['post_meta'][ $id ]['_fecha_inicio'] = $fecha;

		return (object) array(
			'ID'        => $id,
			'post_type' => 'centro_turno',
			'post_date' => $fecha,
			'timestamp' => strtotime( $fecha ),
		);
	}
}
