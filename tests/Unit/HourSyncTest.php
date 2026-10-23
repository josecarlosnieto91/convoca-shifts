<?php
/**
 * Acreditar y revocar horas cuando se marca la asistencia de un turno.
 *
 * Es el puente entre «el turno se hizo» y «esta persona tiene cuatro horas más en su libro». Si se
 * equivoca, o se acreditan horas dos veces, o se le quitan a quien sí fue —y eso no se ve hasta que alguien
 * mira su libro a fin de mes—. Por eso se prueba lo que hace falta de verdad: que solo se acredite **una
 * vez**, que el turno sin marcar no acredite nada, que desmarcarlo **devuelva** las horas y **revoque** el
 * apunte sin borrarlo, y que la puerta del libro mande.
 *
 * Las pruebas anteriores de este fichero comprobaban que «no hay errores en el registro», que es cierto por
 * muchos motivos a la vez —incluido que el doble del libro no apuntaba nada—: pasaban con el producto roto.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Hour_Sync.php';

/**
 * Sincronización de horas del turno al libro del voluntario.
 */
final class HourSyncTest extends TestCase {

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
		foreach ( array( '_wp_stores', '_sh_db', '__horas_puerta', '__horas_credit', '__horas_revoke' ) as $clave ) {
			unset( $GLOBALS[ $clave ] );
		}

		$GLOBALS['_wp_stores'] = array();
		\Convoca\Core\Logger::clear();
		\Convoca\Core\Utils::clear_fired();
	}

	/**
	 * Sin persona no hay nada que acreditar, y ni se abre transacción.
	 *
	 * La guarda `$user_id <= 0` es **redundante** con la siguiente (`get_userdata()` ya devuelve falso para
	 * 0 y para negativos): se deja porque dice en voz alta la intención, pero no se finge que se pueda
	 * distinguir. Lo que se prueba es el efecto observable.
	 */
	public function test_sin_persona(): void {
		$GLOBALS['__horas_puerta'] = true;
		$this->turno( 1, '2026-11-05 17:00:00', '21:00' );

		\Convoca\Shifts\Hour_Sync::sync_hours_to_volunteer_global( 1, 0, 'realizado' );

		$this->assertSame( array(), $GLOBALS['__horas_credit'] ?? array() );
		$this->assertSame( array(), $GLOBALS['_sh_db']['consultas'] ?? array(), 'no se consulta la base' );
	}

	/**
	 * Una persona que ya no existe tampoco.
	 */
	public function test_persona_que_no_existe(): void {
		$GLOBALS['__horas_puerta']            = true;
		$GLOBALS['_wp_stores']['usuarios'][7] = false;
		$this->turno( 1, '2026-11-05 17:00:00', '21:00' );

		\Convoca\Shifts\Hour_Sync::sync_hours_to_volunteer_global( 1, 7, 'realizado' );

		$this->assertSame( array(), $GLOBALS['__horas_credit'] ?? array() );
		$this->assertSame( array(), $GLOBALS['_sh_db']['consultas'] ?? array() );
	}

	/**
	 * Con la puerta del libro cerrada no se acredita: quién acredita lo dice Core, no este plugin.
	 */
	public function test_con_la_puerta_cerrada_no_se_acredita(): void {
		$this->persona( 7 );
		$this->turno( 1, '2026-11-05 17:00:00', '21:00' );

		\Convoca\Shifts\Hour_Sync::sync_hours_to_volunteer_global( 1, 7, 'realizado' );

		$this->assertSame( array(), $GLOBALS['__horas_credit'] ?? array() );
		$this->assertArrayNotHasKey( '_convoca_shifts_horas_contabilizadas', $GLOBALS['_wp_stores']['post_meta'][1] );
		$this->assertSame( array(), $GLOBALS['_sh_db']['consultas'] ?? array() );
	}

	/**
	 * Lo que no es un turno no acredita horas.
	 */
	public function test_lo_que_no_es_un_turno(): void {
		$GLOBALS['__horas_puerta'] = true;
		$this->persona( 7 );
		$GLOBALS['_wp_stores']['posts'][5] = (object) array(
			'ID'        => 5,
			'post_type' => 'page',
		);

		\Convoca\Shifts\Hour_Sync::sync_hours_to_volunteer_global( 5, 7, 'realizado' );

		$this->assertSame( array(), $GLOBALS['__horas_credit'] ?? array() );
		$this->assertEmpty( \Convoca\Core\Logger::get_logs(), 'ni se apunta un error: no es un fallo' );
	}

	/**
	 * El camino bueno: las horas del turno al libro, apuntadas en el turno y con su aviso.
	 */
	public function test_marcar_realizado_acredita_las_horas(): void {
		$GLOBALS['__horas_puerta'] = true;
		$this->persona( 7 );
		$this->turno( 1, '2026-11-05 17:00:00', '21:00' );

		\Convoca\Shifts\Hour_Sync::sync_hours_to_volunteer_global( 1, 7, 'realizado' );

		$this->assertCount( 1, $GLOBALS['__horas_credit'] );
		$apunte = $GLOBALS['__horas_credit'][0];
		$this->assertSame( 4.0, $apunte['hours'], 'de 17:00 a 21:00 son cuatro horas' );
		$this->assertSame( 'turno', $apunte['origen'] );
		$this->assertSame( 1, $apunte['origen_id'] );
		$this->assertSame( 7, $apunte['user_id'] );
		$this->assertStringContainsString( '#1', $apunte['args']['title'], 'se dice de qué turno' );
		$this->assertStringContainsString( 'Turno 1', $apunte['args']['tareas'] );

		$this->assertSame( 4.0, (float) $GLOBALS['_wp_stores']['post_meta'][1]['_convoca_shifts_horas_contabilizadas'] );
		$this->assertContains( 'COMMIT', $GLOBALS['_sh_db']['consultas'] );
		$this->assertStringContainsString( 'wp_usermeta', implode( ' ', $GLOBALS['_sh_db']['consultas'] ), 'el saldo se actualiza en un solo UPSERT' );

		$avisos = \Convoca\Core\Utils::$actions_fired;
		$this->assertNotEmpty( $avisos, 'se avisa de las horas nuevas' );
		$this->assertSame( array( 7, 4.0 ), $avisos[0]['args'] );
	}

	/**
	 * Un turno que ya estaba contado no se vuelve a acreditar.
	 */
	public function test_no_se_acredita_dos_veces(): void {
		$GLOBALS['__horas_puerta'] = true;
		$this->persona( 7 );
		$this->turno( 1, '2026-11-05 17:00:00', '21:00' );
		$GLOBALS['_sh_db']['get_var'] = '4';

		\Convoca\Shifts\Hour_Sync::sync_hours_to_volunteer_global( 1, 7, 'realizado' );

		$this->assertSame( array(), $GLOBALS['__horas_credit'] ?? array(), 'ya estaba contado' );
	}

	/**
	 * Desmarcarlo devuelve las horas y **revoca** el apunte, sin borrarlo.
	 */
	public function test_desmarcar_devuelve_las_horas(): void {
		$GLOBALS['__horas_puerta'] = true;
		$this->persona( 7 );
		$this->turno( 1, '2026-11-05 17:00:00', '21:00' );
		$GLOBALS['_wp_stores']['post_meta'][1]['_convoca_shifts_horas_contabilizadas'] = 4;
		$GLOBALS['_sh_db']['get_var'] = '4';

		\Convoca\Shifts\Hour_Sync::sync_hours_to_volunteer_global( 1, 7, 'no_asistio' );

		$this->assertSame( array(), $GLOBALS['__horas_credit'] ?? array(), 'no se acredita nada' );
		$this->assertCount( 1, $GLOBALS['__horas_revoke'] ?? array(), 'se revoca el apunte' );
		$this->assertSame( 1, $GLOBALS['__horas_revoke'][0]['origen_id'] );
		$this->assertArrayNotHasKey( '_convoca_shifts_horas_contabilizadas', $GLOBALS['_wp_stores']['post_meta'][1], 'la marca se borra' );

		$avisos = \Convoca\Core\Utils::$actions_fired;
		$this->assertSame( array( 7, -4.0 ), $avisos[0]['args'], 'y se avisa en negativo' );
	}

	/**
	 * Revocar se permite **con la puerta cerrada**: quitar horas es limpieza, no acreditación.
	 */
	public function test_revocar_sin_puerta(): void {
		$this->persona( 7 );
		$this->turno( 1, '2026-11-05 17:00:00', '21:00' );
		$GLOBALS['_wp_stores']['post_meta'][1]['_convoca_shifts_horas_contabilizadas'] = 4;
		$GLOBALS['_sh_db']['get_var'] = '4';

		\Convoca\Shifts\Hour_Sync::sync_hours_to_volunteer_global( 1, 7, 'no_asistio' );

		$this->assertCount( 1, $GLOBALS['__horas_revoke'] ?? array(), 'con la puerta cerrada también' );
	}

	/**
	 * Un turno sin fechas no acredita nada, y no revienta.
	 */
	public function test_sin_fechas_no_hay_horas(): void {
		$GLOBALS['__horas_puerta'] = true;
		$this->persona( 7 );
		$this->turno( 1, '', '' );

		\Convoca\Shifts\Hour_Sync::sync_hours_to_volunteer_global( 1, 7, 'realizado' );

		$this->assertSame( array(), $GLOBALS['__horas_credit'] ?? array() );
		$this->assertEmpty( \Convoca\Core\Logger::get_logs(), 'tampoco es un error' );
	}

	/**
	 * Si la hora de fin no es posterior al inicio, tampoco se acredita.
	 *
	 * Aquí hay dos guardas para lo mismo —`$end > $start` y `$hours > 0`—, así que quitar una sola no cambia
	 * el resultado: se dice, en vez de escribir una prueba que aparente cubrirlas por separado.
	 */
	public function test_un_turno_al_reves_no_acredita(): void {
		$GLOBALS['__horas_puerta'] = true;
		$this->persona( 7 );
		$this->turno( 1, '2026-11-05 21:00:00', '09:00' );

		\Convoca\Shifts\Hour_Sync::sync_hours_to_volunteer_global( 1, 7, 'realizado' );

		$this->assertSame( array(), $GLOBALS['__horas_credit'] ?? array() );
	}

	/**
	 * Si algo revienta, se deshace y se apunta el error.
	 */
	public function test_si_revienta(): void {
		$GLOBALS['__horas_puerta'] = true;
		$this->persona( 7 );
		$this->turno( 1, '2026-11-05 17:00:00', '21:00' );
		$GLOBALS['_sh_db']['lanza_en'] = 'INSERT INTO wp_usermeta';

		\Convoca\Shifts\Hour_Sync::sync_hours_to_volunteer_global( 1, 7, 'realizado' );

		$this->assertContains( 'ROLLBACK', $GLOBALS['_sh_db']['consultas'], 'se deshace la transacción' );
		$this->assertSame( array(), $GLOBALS['__horas_credit'] ?? array(), 'no se llega a acreditar' );
		$this->assertSame( 'Turnos/Sync', \Convoca\Core\Logger::get_logs()[0]['context'] ?? '', 'y queda apuntado' );
	}

	/**
	 * Deja una persona en el arnés.
	 *
	 * @param int $id Persona.
	 */
	private function persona( int $id ): void {
		$GLOBALS['_wp_stores']['usuarios'][ $id ] = (object) array(
			'ID'           => $id,
			'display_name' => 'Ada Lovelace',
			'first_name'   => 'Ada',
		);
	}

	/**
	 * Deja un turno en el arnés.
	 *
	 * @param int    $id    Turno.
	 * @param string $fecha Inicio.
	 * @param string $fin   Hora de fin.
	 */
	private function turno( int $id, string $fecha, string $fin ): void {
		$GLOBALS['_wp_stores']['posts'][ $id ] = (object) array(
			'ID'          => $id,
			'post_type'   => 'centro_turno',
			'post_status' => 'publish',
			'post_title'  => 'Turno ' . $id,
			'post_date'   => $fecha,
		);
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = array(
			'_fecha_inicio' => $fecha,
			'_hora_fin'     => $fin,
		);
	}
}
