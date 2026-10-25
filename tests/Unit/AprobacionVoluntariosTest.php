<?php
/**
 * El ciclo de vida del voluntariado visto desde turnos.
 *
 * `includes/admin-approval.php` estaba al 0 % (42 statements). Es lo que pasa cuando alguien aprueba o
 * revoca a una persona: se registra la actividad y, al revocar, se **liberan sus turnos futuros** para
 * que no queden asignados a quien ya no está. Si esto se rompe, los turnos siguen a nombre de alguien
 * que ya no puede venir y el centro se queda sin cubrirlos.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Aprobación y revocación.
 */
final class AprobacionVoluntariosTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();
		require_once dirname( __DIR__, 2 ) . '/includes/admin-approval.php';
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
		$GLOBALS['_wp_stores']      = array( 'post_meta' => array() );
		$GLOBALS['_sh_registro']    = array();
		$GLOBALS['_sh_publicados']  = array();
		$GLOBALS['_test_caps']      = array();
	}

	/**
	 * Las dos escuchas quedan enganchadas: sin ellas, turnos no se enter­a de nada.
	 */
	public function test_el_fichero_engancha_sus_escuchas(): void {
		$acciones = isset( $GLOBALS['_wp_stores']['acciones'] ) ? $GLOBALS['_wp_stores']['acciones'] : array();

		$this->assertArrayHasKey( 'convoca_voluntario_aprobado', $acciones );
		$this->assertContains( 'Convoca\\Shifts\\convoca_shifts_on_volunteer_approved', $acciones['convoca_voluntario_aprobado'] );
		$this->assertArrayHasKey( 'convoca_voluntario_revocado', $acciones );
		$this->assertContains( 'Convoca\\Shifts\\convoca_shifts_on_volunteer_revoked', $acciones['convoca_voluntario_revocado'] );
	}

	/**
	 * Aprobar deja constancia de quién actuó y sobre quién.
	 */
	public function test_aprobar_registra_la_actividad(): void {
		\Convoca\Shifts\convoca_shifts_on_volunteer_approved( 7 );

		$this->assertCount( 1, $GLOBALS['_sh_registro'] );
		$registro = $GLOBALS['_sh_registro'][0];

		$this->assertSame( 'voluntario_aprobado', $registro['action'] );
		$this->assertSame( 7, $registro['data']['voluntario_id'], 'se guarda sobre quién' );
		$this->assertSame( 1, $registro['user_id'], 'y quién lo hizo' );
		$this->assertSame( 0, $registro['turno_id'], 'la aprobación no es de un turno' );
	}

	/**
	 * Revocar sin persona no toca nada: ni la base ni el registro.
	 */
	public function test_revocar_sin_persona_no_toca_nada(): void {
		\Convoca\Shifts\convoca_shifts_on_volunteer_revoked( 0 );

		$this->assertSame( array(), $GLOBALS['_sh_registro'] );
		$this->assertSame( array(), $GLOBALS['_wp_stores']['post_meta'] );
	}

	/**
	 * Revocar libera los turnos futuros de esa persona y solo los suyos.
	 */
	public function test_revocar_libera_los_turnos_futuros_de_esa_persona(): void {
		$this->turno( 101, 7, gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) );
		$this->turno( 102, 8, gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) );

		\Convoca\Shifts\convoca_shifts_on_volunteer_revoked( 7 );

		$this->assertSame( 0, (int) get_post_meta( 101, '_id_responsable', true ), 'el turno se queda sin responsable' );
		$this->assertSame( 'abierto_disponible', get_post_meta( 101, '_estado', true ), 'y vuelve a estar disponible' );
		$this->assertSame( '🟡 Pendiente', get_post_meta( 101, '_wp_update_data', true )['post_title'] );
		$this->assertSame( 'publish', get_post_meta( 101, '_wp_update_data', true )['post_status'] );
		$this->assertContains( 101, $GLOBALS['_sh_publicados'] );

		$this->assertSame( 8, (int) get_post_meta( 102, '_id_responsable', true ), 'el de otra persona no se toca' );
		$this->assertSame( 'cubierto', get_post_meta( 102, '_estado', true ), 'el estado de otra persona no se toca' );
		$this->assertNotContains( 102, $GLOBALS['_sh_publicados'] );

		$this->assertCount( 1, $GLOBALS['_sh_registro'] );
		$this->assertSame( 'voluntario_revocado', $GLOBALS['_sh_registro'][0]['action'] );
		$this->assertSame( 7, $GLOBALS['_sh_registro'][0]['data']['voluntario_id'] );
	}

	/**
	 * Un turno ya pasado no se libera: ya se trabajó y su asistencia está registrada.
	 */
	public function test_un_turno_pasado_no_se_libera(): void {
		$this->turno( 103, 7, gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) );

		\Convoca\Shifts\convoca_shifts_on_volunteer_revoked( 7 );

		$this->assertSame( 7, (int) get_post_meta( 103, '_id_responsable', true ), 'el pasado no se toca' );
		$this->assertNotContains( 103, $GLOBALS['_sh_publicados'] );
		$this->assertCount( 1, $GLOBALS['_sh_registro'], 'pero la revocación sí se registra' );
	}

	/**
	 * Siembra un turno publicado con responsable y fecha.
	 *
	 * @param int    $id      Turno.
	 * @param int    $persona Responsable.
	 * @param string $fecha   Fecha del turno.
	 */
	private function turno( int $id, int $persona, string $fecha ): void {
		$GLOBALS['_wp_stores']['posts'][ $id ] = (object) array(
			'ID'          => $id,
			'post_type'   => 'centro_turno',
			'post_status' => 'publish',
			'post_title'  => '🟢 Cubierto',
			'post_date'   => $fecha,
		);
		$GLOBALS['_wp_stores']['post_meta'][ $id ]['_id_responsable'] = $persona;
		$GLOBALS['_wp_stores']['post_meta'][ $id ]['_estado']         = 'cubierto';
	}
}
