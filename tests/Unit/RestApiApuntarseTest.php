<?php
/**
 * Apuntarse y desapuntarse de un turno.
 *
 * Es el camino por el que un voluntario coge su turno, y el que puede dejarlo dos veces a la misma hora
 * si la comprobación de solapamiento falla. Se prueba cada puerta por su efecto observable: el turno que
 * no existe, el que ya está ocupado, el que ya pasó, el solapamiento, y quién puede liberar.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/rest-api.php';

/**
 * Alta y baja en un turno.
 */
final class RestApiApuntarseTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();

		foreach ( array( 'horario', 'class-personas-asignables', 'cpt-turno' ) as $fichero ) {
			require_once dirname( __DIR__, 2 ) . '/includes/' . $fichero . '.php';
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
		foreach ( array( '_wp_stores', '_test_caps', '_sh_db', '_sh_rate_limit', '_sh_registro', '_sh_publicados', '_sh_nonce_ok' ) as $clave ) {
			unset( $GLOBALS[ $clave ] );
		}

		$GLOBALS['_wp_stores'] = array();
		$GLOBALS['_test_caps'] = array();
	}

	/**
	 * Un turno que no existe no se puede coger.
	 */
	public function test_un_turno_que_no_existe(): void {
		$GLOBALS['_wp_stores']['no_existe'][404] = true;

		$r = $this->apuntarse( 404 );

		$this->assertSame( 'not_found', $r->get_error_code() );
	}

	/**
	 * Lo que no es un turno tampoco.
	 */
	public function test_lo_que_no_es_un_turno(): void {
		$GLOBALS['_wp_stores']['posts'][5] = (object) array(
			'ID'        => 5,
			'post_type' => 'post',
		);

		$r = $this->apuntarse( 5 );

		$this->assertSame( 'not_found', $r->get_error_code() );
	}

	/**
	 * Un turno que ya está ocupado o cerrado no se coge: hay que elegir otro.
	 */
	public function test_un_turno_que_ya_no_está_disponible(): void {
		$this->turno( 7, 'abierto_ocupado' );

		$r = $this->apuntarse( 7 );

		$this->assertSame( 'not_available', $r->get_error_code() );
		$this->assertStringContainsString( 'elige otro', $r->get_error_message() );
	}

	/**
	 * A un turno que ya pasó no se apunta nadie.
	 */
	public function test_un_turno_pasado(): void {
		$this->turno( 7, 'abierto_disponible', gmdate( 'Y-m-d H:i:s', time() - 7200 ) );

		$r = $this->apuntarse( 7 );

		$this->assertSame( 'past_turno', $r->get_error_code() );
	}

	/**
	 * Si alguien se adelantó dentro de la transacción, se avisa y se deshace.
	 */
	public function test_si_alguien_se_adelantó(): void {
		$this->turno( 7, 'abierto_disponible', $this->futuro() );
		$GLOBALS['_sh_db']['get_var_cola'] = array( '9' );

		$r = $this->apuntarse( 7 );

		$this->assertSame( 'ya_cubierto', $r->get_error_code() );
		$this->assertContains( 'ROLLBACK', $GLOBALS['_sh_db']['consultas'], 'se deshace la transacción' );
	}

	/**
	 * Un solapamiento con otro turno de la misma persona se rechaza y queda registrado.
	 */
	public function test_un_solapamiento(): void {
		$this->turno( 7, 'abierto_disponible', $this->futuro() );
		// Primera consulta: el cerrojo del turno (libre). Segunda: el solapamiento (turno 3).
		$GLOBALS['_sh_db']['get_var_cola'] = array( '0', '3' );

		$r = $this->apuntarse( 7 );

		$this->assertSame( 'conflicto_horario', $r->get_error_code() );
		$this->assertContains( 'ROLLBACK', $GLOBALS['_sh_db']['consultas'] );
		// El primero que apunta el registro es el detalle del conflicto (lo hace la comprobación); el
		// segundo es el suceso del endpoint.
		$this->assertContains( 'conflicto_horario_detectado', array_column( $GLOBALS['_sh_registro'], 'action' ) );
	}

	/**
	 * Si el turno no tiene fila de responsable, se inserta; y si el INSERT tampoco entra, es que otro llegó antes.
	 */
	public function test_si_no_se_puede_asignar(): void {
		$this->turno( 7, 'abierto_disponible', $this->futuro() );
		$GLOBALS['_sh_db']['get_var_cola'] = array( '0', null );
		// UPDATE 0 filas, INSERT 0 filas.
		$GLOBALS['_sh_db']['query_rc'] = array( 0, 0 );

		$r = $this->apuntarse( 7 );

		$this->assertSame( 'ya_cubierto', $r->get_error_code() );
	}

	/**
	 * El camino bueno: el turno queda a nombre de la persona, con su título y el aviso al centro.
	 */
	public function test_el_camino_bueno(): void {
		$fecha = $this->futuro();
		$this->turno( 7, 'abierto_disponible', $fecha );
		$GLOBALS['_sh_db']['get_var_cola'] = array( '0', null );
		$GLOBALS['_wp_stores']['usuarios'][1] = (object) array(
			'ID'           => 1,
			'display_name' => 'Ada Lovelace',
			'first_name'   => 'Ada',
			'user_email'   => 'ada@example.test',
		);
		$GLOBALS['_wp_stores']['options']['admin_email'] = 'centro@example.test';

		$r = $this->apuntarse( 7 );

		$this->assertTrue( $r->get_data()['success'] );
		$this->assertSame( 'turno_cubierto', $GLOBALS['_sh_registro'][0]['action'] );
		$this->assertContains( 7, $GLOBALS['_sh_publicados'], 'el turno se republica con su título nuevo' );
		$this->assertStringContainsString( 'success', (string) wp_json_encode( $r->get_data() ) );
	}

	/**
	 * Desapuntarse de un turno que no existe, no.
	 */
	public function test_desapuntarse_de_un_turno_inexistente(): void {
		$GLOBALS['_wp_stores']['no_existe'][404] = true;

		$r = $this->desapuntarse( 404 );

		$this->assertSame( 'not_found', $r->get_error_code() );
	}

	/**
	 * De un turno cerrado tampoco.
	 */
	public function test_desapuntarse_de_un_turno_cerrado(): void {
		$this->turno( 7, 'cerrado' );

		$r = $this->desapuntarse( 7 );

		$this->assertSame( 'not_available', $r->get_error_code() );
	}

	/**
	 * Solo lo libera quien lo tiene, o quien gestiona turnos.
	 */
	public function test_no_se_libera_el_turno_de_otro(): void {
		$this->turno( 7, 'abierto_disponible', $this->futuro() );
		$GLOBALS['_sh_db']['get_var_cola']                     = array( '9' );
		$GLOBALS['_test_caps']['manage_options']               = false;
		$GLOBALS['_test_caps']['convoca_shifts_manage_turnos'] = false;

		$r = $this->desapuntarse( 7 );

		$this->assertSame( 'not_yours', $r->get_error_code() );
		$this->assertContains( 'ROLLBACK', $GLOBALS['_sh_db']['consultas'] );
	}

	/**
	 * Quien gestiona sí puede liberar el turno de otra persona.
	 */
	public function test_quien_gestiona_puede_liberar(): void {
		$this->turno( 7, 'abierto_disponible', $this->futuro() );
		// El turno es de otra persona: así se nota que se libera de verdad.
		$GLOBALS['_wp_stores']['post_meta'][7]['_id_responsable'] = 9;
		$GLOBALS['_sh_db']['get_var_cola']                        = array( '9' );
		$GLOBALS['_test_caps']['convoca_shifts_manage_turnos']    = true;

		$r = $this->desapuntarse( 7 );

		$this->assertTrue( $r->get_data()['success'] );
		$this->assertSame( 0, $GLOBALS['_wp_stores']['post_meta'][7]['_id_responsable'], 'el turno queda libre' );
		$this->assertSame( 'turno_liberado', $GLOBALS['_sh_registro'][0]['action'] );
	}

	/**
	 * Y la persona dueña del turno también.
	 */
	public function test_la_persona_del_turno_puede_liberarlo(): void {
		$this->turno( 7, 'abierto_disponible', $this->futuro() );
		$GLOBALS['_wp_stores']['post_meta'][7]['_id_responsable'] = 1;
		$GLOBALS['_sh_db']['get_var_cola']                        = array( '1' );

		$r = $this->desapuntarse( 7 );

		$this->assertTrue( $r->get_data()['success'] );
		$this->assertSame( 0, $GLOBALS['_wp_stores']['post_meta'][7]['_id_responsable'] );
	}

	/**
	 * Con el límite pasado, 429 en las dos rutas.
	 */
	public function test_el_limite_de_peticiones(): void {
		$GLOBALS['_sh_rate_limit'] = false;

		$this->assertSame( 'rest_rate_limit', $this->apuntarse( 7 )->get_error_code() );
		$this->assertSame( 'rest_rate_limit', $this->desapuntarse( 7 )->get_error_code() );
	}

	/**
	 * Llama a la ruta de apuntarse.
	 *
	 * @param int $id Turno.
	 * @return mixed
	 */
	private function apuntarse( int $id ) {
		return \Convoca\Shifts\convoca_shifts_rest_apuntarse_turno( new \WP_REST_Request( array( 'id' => $id ) ) );
	}

	/**
	 * Llama a la ruta de desapuntarse.
	 *
	 * @param int $id Turno.
	 * @return mixed
	 */
	private function desapuntarse( int $id ) {
		return \Convoca\Shifts\convoca_shifts_rest_desapuntarse_turno( new \WP_REST_Request( array( 'id' => $id ) ) );
	}

	/**
	 * Deja un turno en el arnés.
	 *
	 * @param int    $id     Turno.
	 * @param string $estado Estado.
	 * @param string $fecha  Fecha de inicio.
	 */
	private function turno( int $id, string $estado, string $fecha = '' ): void {
		$GLOBALS['_wp_stores']['posts'][ $id ] = (object) array(
			'ID'          => $id,
			'post_type'   => 'centro_turno',
			'post_status' => 'publish',
			'post_title'  => 'Turno ' . $id,
			'post_date'   => '2026-10-26 10:00:00',
		);

		$GLOBALS['_wp_stores']['post_meta'][ $id ] = array(
			'_estado'         => $estado,
			'_id_responsable' => 0,
			'_hora_fin'       => '13:00',
			'_fecha_inicio'   => '' !== $fecha ? $fecha : '2026-10-26 10:00:00',
		);
	}

	/**
	 * Una fecha futura, holgada, para que la prueba no dependa del día.
	 *
	 * @return string
	 */
	private function futuro(): string {
		return gmdate( 'Y-m-d H:i:s', time() + ( 7 * 86400 ) );
	}
}
