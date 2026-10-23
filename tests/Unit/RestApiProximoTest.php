<?php
/**
 * Endpoints REST de turnos: próximo libre, alta y formato de fecha.
 *
 * Son las tres respuestas que ve el voluntario cuando pulsa. Se prueba lo que decide qué contesta:
 * que «no hay turnos» se distinga de «hay uno» (con fecha o con respaldo legible), que quien no
 * administra no pueda crear un turno cerrado ni en el pasado, y que el alta avise cuando falla en vez
 * de decir que sí.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/rest-api.php';

/**
 * Próximo libre, alta y formato de fecha.
 */
final class RestApiProximoTest extends TestCase {

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
		if ( ! function_exists( '\Convoca\Shifts\convoca_shifts_insert_turno' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/cpt-turno.php';
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
		foreach ( array( '_sh_db', '_sh_registro', '_wp_stores', '_test_caps', '_sh_insert_error', '_sh_rate_limit', '_sh_rate_limit_consultas' ) as $clave ) {
			unset( $GLOBALS[ $clave ] );
		}

		$GLOBALS['_wp_stores'] = array();
		$GLOBALS['_test_caps'] = array();
	}

	/**
	 * Sin turnos libres lo dice, en vez de devolver una fecha cualquiera.
	 */
	public function test_sin_turno_libre_lo_dice(): void {
		$this->turnos( array() );

		$r = \Convoca\Shifts\convoca_shifts_rest_get_proximo_libre( new \WP_REST_Request() );

		$this->assertFalse( $r->get_data()['encontrado'] );
		$this->assertStringContainsString( 'cubiertos', $r->get_data()['texto'] );
	}

	/**
	 * Con un turno libre devuelve su fecha y hora en el formato de la casa.
	 */
	public function test_con_turno_libre_devuelve_su_fecha(): void {
		$this->turnos(
			array(
				7 => (object) array(
					'ID'        => 7,
					'post_type' => 'centro_turno',
					'post_date' => '2026-10-26 10:00:00',
				),
			)
		);
		$GLOBALS['_wp_stores']['post_meta'][7] = array(
			'_fecha_inicio'   => '2026-10-26 10:00:00',
			'_estado'         => 'abierto_disponible',
			'_id_responsable' => 0,
		);

		$r = \Convoca\Shifts\convoca_shifts_rest_get_proximo_libre( new \WP_REST_Request() );

		$this->assertTrue( $r->get_data()['encontrado'] );
		$this->assertStringContainsString( '26/10/2026 10:00', $r->get_data()['texto'] );
	}

	// El respaldo `convoca_shifts_fecha_legible()` NO se prueba aquí: la consulta filtra por
	// `_fecha_inicio > ahora`, así que un turno encontrado siempre tiene esa meta. La rama es defensiva
	// y queda sin cubrir a propósito, en vez de sembrar un estado que la propia consulta no devuelve.

	/**
	 * Crear un turno sin permiso obliga a que sea «pendiente»: no se cierra el centro desde fuera.
	 */
	public function test_sin_permiso_el_turno_sale_pendiente(): void {
		$this->sin_permisos();

		$r = $this->crear(
			array(
				'date'    => gmdate( 'Y-m-d', strtotime( '+2 days' ) ),
				'h_start' => '10:00',
				'h_end'   => '12:00',
				'estado'  => 'cerrado',
			)
		);

		$this->assertTrue( $r->get_data()['success'] );
		$this->assertSame( 'abierto_disponible', $this->meta( '_estado' ), 'un cerrado de fuera se convierte en pendiente' );
	}

	/**
	 * Y no puede crear turnos en el pasado.
	 */
	public function test_sin_permiso_no_se_crea_un_turno_en_el_pasado(): void {
		$this->sin_permisos();

		$r = $this->crear(
			array(
				'date'    => gmdate( 'Y-m-d', strtotime( '-3 days' ) ),
				'h_start' => '10:00',
				'h_end'   => '12:00',
			)
		);

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertSame( 'past_date', $r->get_error_code() );
		$this->assertArrayNotHasKey( 'insertados', $GLOBALS['_wp_stores'] ?? array(), 'y no se crea nada' );
	}

	/**
	 * Hoy mismo, con la hora ya pasada, tampoco.
	 */
	public function test_hoy_con_la_hora_pasada_tampoco(): void {
		$this->sin_permisos();

		$r = $this->crear(
			array(
				'date'    => gmdate( 'Y-m-d' ),
				'h_start' => '00:01',
				'h_end'   => '00:02',
			)
		);

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertSame( 'past_date', $r->get_error_code() );
	}

	/**
	 * Quien administra sí puede crear un turno cerrado.
	 */
	public function test_admin_puede_crear_cerrado(): void {
		$this->con_permisos();

		$r = $this->crear(
			array(
				'date'    => '2026-10-26',
				'h_start' => '10:00',
				'h_end'   => '12:00',
				'estado'  => 'cerrado',
			)
		);

		$this->assertTrue( $r->get_data()['success'] );
		$this->assertSame( 'cerrado', $this->meta( '_estado' ) );
	}

	/**
	 * El apoyo se acepta en cualquiera de las formas que manda el guion, y solo esas.
	 */
	public function test_el_apoyo_se_lee_en_sus_formas(): void {
		$this->con_permisos();

		foreach ( array( 'true', true, 1, '1' ) as $forma ) {
			$this->limpiar();
			$this->con_permisos();
			$this->crear(
				array(
					'date'    => '2026-10-26',
					'h_start' => '10:00',
					'h_end'   => '12:00',
					'apoyo'   => $forma,
				)
			);

			$this->assertTrue( (bool) $this->meta( '_necesita_apoyo' ), 'forma aceptada: ' . var_export( $forma, true ) );
		}

		$this->limpiar();
		$this->con_permisos();
		$this->crear(
			array(
				'date'    => '2026-10-26',
				'h_start' => '10:00',
				'h_end'   => '12:00',
				'apoyo'   => 'no',
			)
		);

		$this->assertFalse( (bool) $this->meta( '_necesita_apoyo' ), 'cualquier otra cosa es que no' );
	}

	/**
	 * Si el alta falla, se contesta con error en vez de decir que sí.
	 */
	public function test_si_el_alta_falla_se_contesta_con_error(): void {
		$this->con_permisos();
		$GLOBALS['_sh_insert_error'] = true;

		$r = $this->crear(
			array(
				'date'    => '2026-10-26',
				'h_start' => '10:00',
				'h_end'   => '12:00',
			)
		);

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertSame( 'create_failed', $r->get_error_code() );
	}

	/**
	 * Un alta correcta se registra en la actividad con su origen.
	 */
	public function test_un_alta_correcta_se_registra(): void {
		$this->con_permisos();

		$r = $this->crear(
			array(
				'date'    => '2026-10-26',
				'h_start' => '10:00',
				'h_end'   => '12:00',
			)
		);

		$this->assertArrayHasKey( 'id', $r->get_data() );
		$this->assertSame( 'turno_creado', $GLOBALS['_sh_registro'][0]['action'] );
		$this->assertSame( 'frontend', $GLOBALS['_sh_registro'][0]['data']['origen'] );
	}

	/**
	 * Con el límite de peticiones pasado, se contesta 429 en vez de trabajar de más.
	 */
	public function test_el_limite_de_peticiones_se_respeta(): void {
		$GLOBALS['_sh_rate_limit'] = false;

		$proximo = \Convoca\Shifts\convoca_shifts_rest_get_proximo_libre( new \WP_REST_Request() );
		$alta    = \Convoca\Shifts\convoca_shifts_rest_crear_turno( new \WP_REST_Request( array( 'date' => '2026-10-26' ) ) );

		$this->assertSame( 'rest_rate_limit', $proximo->get_error_code() );
		$this->assertSame( 429, $proximo->get_error_data()['status'] );
		$this->assertSame( 'rest_rate_limit', $alta->get_error_code() );
	}

	/**
	 * El formato de fecha: con fecha, «día a las hora»; sin fecha, la de la entrada.
	 */
	public function test_el_formato_de_fecha(): void {
		$post = (object) array( 'post_date' => '2026-10-26 10:00:00' );

		$this->assertSame(
			'26/10/2026 a las 10:00',
			\Convoca\Shifts\apuntarse_proximo_format_date( '2026-10-26 10:00:00', $post )
		);

		$this->assertSame(
			'2026-10-26 10:00:00',
			\Convoca\Shifts\apuntarse_proximo_format_date( '', $post ),
			'sin fecha, se devuelve la de la entrada tal cual'
		);
	}

	/**
	 * Una fecha imposible se devuelve sin tocar, en vez de reventar.
	 */
	public function test_una_fecha_imposible_se_devuelve_tal_cual(): void {
		$post = (object) array( 'post_date' => '2026-10-26 10:00:00' );

		$this->assertSame( 'no es una fecha', \Convoca\Shifts\apuntarse_proximo_format_date( 'no es una fecha', $post ) );
	}

	/**
	 * Llama al endpoint de alta y devuelve su respuesta.
	 *
	 * @param array $params Parámetros.
	 * @return mixed
	 */
	private function crear( array $params ) {
		return \Convoca\Shifts\convoca_shifts_rest_crear_turno( new \WP_REST_Request( $params ) );
	}

	/**
	 * Lee la meta del turno creado.
	 *
	 * @param string $clave Clave.
	 * @return mixed
	 */
	private function meta( string $clave ) {
		foreach ( ( $GLOBALS['_wp_stores']['post_meta'] ?? array() ) as $meta ) {
			if ( array_key_exists( $clave, $meta ) ) {
				return $meta[ $clave ];
			}
		}

		return null;
	}

	/**
	 * Deja el almacén de entradas con estos turnos.
	 *
	 * @param array $turnos Turnos.
	 */
	private function turnos( array $turnos ): void {
		$GLOBALS['_wp_stores']['posts']      = $turnos;
		$GLOBALS['_wp_stores']['post_meta']  = $GLOBALS['_wp_stores']['post_meta'] ?? array();
		$GLOBALS['_wp_stores']['options']    = array(
			'convoca_shifts_hora_apertura' => '08:00',
			'convoca_shifts_hora_cierre'   => '22:00',
		);
	}

	/**
	 * Sin capacidades de gestión.
	 */
	private function sin_permisos(): void {
		$GLOBALS['_test_caps']['manage_options']               = false;
		$GLOBALS['_test_caps']['convoca_shifts_manage_turnos'] = false;
		$this->turnos( array() );
	}

	/**
	 * Con capacidad de gestión de turnos.
	 */
	private function con_permisos(): void {
		$GLOBALS['_test_caps']['convoca_shifts_manage_turnos'] = true;
		$this->turnos( array() );
	}
}
