<?php
/**
 * «Me apunto al siguiente turno libre».
 *
 * Es el botón que un voluntario pulsa sin mirar el calendario, así que la ruta elige el turno, comprueba
 * que no se solape con lo que ya tiene y lo asigna dentro de una transacción. Y el mensaje que devuelve
 * lleva la fecha del turno: si se formatea mal, el voluntario lee «te has apuntado al turno del » y no
 * sabe a qué ha dicho que sí (shifts#20).
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/rest-api.php';

/**
 * Alta automática en el siguiente turno libre.
 */
final class RestApiApuntarseProximoTest extends TestCase {

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
		foreach ( array( '_wp_stores', '_test_caps', '_sh_db', '_sh_rate_limit', '_sh_registro', '_sh_publicados' ) as $clave ) {
			unset( $GLOBALS[ $clave ] );
		}

		$GLOBALS['_wp_stores'] = array();
		$GLOBALS['_test_caps'] = array();
	}

	/**
	 * Con el límite pasado, 429.
	 */
	public function test_el_limite_de_peticiones(): void {
		$GLOBALS['_sh_rate_limit'] = false;

		$this->assertSame( 'rest_rate_limit', $this->apuntarse()->get_error_code() );
	}

	/**
	 * Sin turnos libres lo dice, y deshace la transacción.
	 */
	public function test_sin_turnos_libres(): void {
		$GLOBALS['_sh_db']['get_var_cola'] = array( null );

		$r = $this->apuntarse();

		$this->assertSame( 'no_disponible', $r->get_error_code() );
		$this->assertContains( 'ROLLBACK', $GLOBALS['_sh_db']['consultas'] );
	}

	/**
	 * Si se solapa con otro turno, se avisa **con el nombre del turno que choca**.
	 */
	public function test_si_se_solapa_con_otro_turno(): void {
		$this->turno( 7, '2026-10-26 10:00:00', '10:00' );
		$GLOBALS['_wp_stores']['posts'][3] = (object) array(
			'ID'         => 3,
			'post_type'  => 'centro_turno',
			'post_title' => 'Turno de la tarde',
			'post_date'  => '2026-10-26 16:00:00',
		);
		// 1ª consulta: el turno elegido. 2ª: el solapamiento (turno 3).
		$GLOBALS['_sh_db']['get_var_cola'] = array( '7', '3' );

		$r = $this->apuntarse();

		$this->assertSame( 'conflicto_horario', $r->get_error_code() );
		$this->assertStringContainsString( 'Turno de la tarde', $r->get_error_message(), 'se dice con cuál choca' );
		$this->assertContains( 'ROLLBACK', $GLOBALS['_sh_db']['consultas'] );
		$this->assertContains( 'conflicto_horario_detectado', array_column( $GLOBALS['_sh_registro'], 'action' ) );
	}

	/**
	 * Si el turno elegido no se puede asignar (ni UPDATE ni INSERT), otro llegó antes.
	 */
	public function test_si_el_turno_se_ocupó_entre_medias(): void {
		$this->turno( 7, '2026-10-26 10:00:00', '10:00' );
		$GLOBALS['_sh_db']['get_var_cola'] = array( '7', null );
		$GLOBALS['_sh_db']['query_rc']     = array( 1, 0, 0 );

		$r = $this->apuntarse();

		$this->assertSame( 'ya_cubierto', $r->get_error_code() );
	}

	/**
	 * El camino bueno: título con el nombre, estado disponible y mensaje con la fecha del turno.
	 */
	public function test_el_camino_bueno(): void {
		$this->turno( 7, '2026-10-26 10:00:00', '10:00' );
		$GLOBALS['_sh_db']['get_var_cola']    = array( '7', null );
		$GLOBALS['_wp_stores']['usuarios'][1] = (object) array(
			'ID'           => 1,
			'display_name' => 'Ada Lovelace',
			'first_name'   => 'Ada',
		);

		$r = $this->apuntarse();

		$this->assertTrue( $r->get_data()['success'] );
		$this->assertStringContainsString( '26/10/2026', $r->get_data()['message'], 'el mensaje dice el día' );
		$this->assertStringContainsString( '10:00', $r->get_data()['message'] );
		$this->assertSame( 'turno_cubierto', $GLOBALS['_sh_registro'][0]['action'] );
		$this->assertContains( 7, $GLOBALS['_sh_publicados'] );
		$this->assertSame(
			'🟢 Cubierto - Ada',
			$GLOBALS['_wp_stores']['post_meta'][7]['_wp_update_data']['post_title'],
			'el turno pasa a llamarse con el nombre de quien lo cubre'
		);
	}

	/**
	 * Un turno marcado sin hora dice solo el día: no se inventa una hora (shifts#20).
	 */
	public function test_un_turno_sin_hora(): void {
		$this->turno( 7, '2026-10-26 00:00:00', '10:00' );
		$GLOBALS['_wp_stores']['post_meta'][7]['_sin_hora'] = 1;
		$GLOBALS['_sh_db']['get_var_cola']    = array( '7', null );
		$GLOBALS['_wp_stores']['usuarios'][1] = (object) array(
			'ID'           => 1,
			'display_name' => 'Ada',
			'first_name'   => 'Ada',
		);

		$r = $this->apuntarse();

		$this->assertStringContainsString( '26/10/2026', $r->get_data()['message'] );
		$this->assertStringNotContainsString( 'a las', $r->get_data()['message'], 'sin hora no se pone hora' );
	}

	/**
	 * Con una fecha mal formada se usa la de la entrada, en vez de un mensaje a medias.
	 */
	public function test_con_una_fecha_mal_formada(): void {
		$this->turno( 7, '2026-10-26 10:00:00', '10:00' );
		$GLOBALS['_wp_stores']['post_meta'][7]['_fecha_inicio'] = 'no es una fecha';
		$GLOBALS['_sh_db']['get_var_cola']    = array( '7', null );
		$GLOBALS['_wp_stores']['usuarios'][1] = (object) array(
			'ID'           => 1,
			'display_name' => 'Ada',
			'first_name'   => 'Ada',
		);

		$r = $this->apuntarse();

		$this->assertTrue( $r->get_data()['success'] );
		$this->assertStringContainsString( '2026-10-26 10:00:00', $r->get_data()['message'], 'sale la fecha de la entrada' );
	}

	/**
	 * Llama a la ruta.
	 *
	 * @return mixed
	 */
	private function apuntarse() {
		return \Convoca\Shifts\convoca_shifts_rest_apuntarse_proximo( new \WP_REST_Request() );
	}

	/**
	 * Deja un turno libre en el arnés.
	 *
	 * @param int    $id     Turno.
	 * @param string $fecha  Fecha de inicio.
	 * @param string $hora   Hora de fin.
	 */
	private function turno( int $id, string $fecha, string $hora ): void {
		$GLOBALS['_wp_stores']['posts'][ $id ] = (object) array(
			'ID'          => $id,
			'post_type'   => 'centro_turno',
			'post_status' => 'publish',
			'post_title'  => 'Turno ' . $id,
			'post_date'   => $fecha,
		);

		$GLOBALS['_wp_stores']['post_meta'][ $id ] = array(
			'_estado'         => 'abierto_disponible',
			'_id_responsable' => 0,
			'_fecha_inicio'   => $fecha,
			'_hora_fin'       => $hora,
		);
	}
}
