<?php
/**
 * Duplicar la semana pasada y generar una semana nueva.
 *
 * Las dos son operaciones de bloqueo: cogen un cerrojo, abren transacción e insertan turnos en lote. Lo
 * que se prueba aquí es lo que puede salir caro si falla: que un cerrojo ocupado no deje pasar, que **no
 * se duplique** un turno que ya existe en ese día y hora (shifts#12 — se comparaba `_hora_fin` con la
 * fecha completa y no casaba nunca), que las horas se recorten al horario del centro, que un fallo a
 * medias deshaga todo, y que el cerrojo se suelte pase lo que pase.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/cpt-turno.php';

/**
 * Duplicado y generación de semanas.
 */
final class SemanasTurnosTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();
		require_once dirname( __DIR__, 2 ) . '/includes/horario.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-personas-asignables.php';
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
		foreach ( array( '_wp_stores', '_test_caps', '_sh_db', '_sh_registro', '_sh_insert_error', '_sh_publicados', '_sh_transients' ) as $clave ) {
			unset( $GLOBALS[ $clave ] );
		}

		$GLOBALS['_wp_stores'] = array();
		$GLOBALS['_test_caps'] = array();
	}

	/**
	 * El cerrojo se concede.
	 */
	private function cerrojo_libre(): void {
		$GLOBALS['_sh_lock'] = true;
	}

	/**
	 * El cerrojo está ocupado por otro proceso.
	 */
	private function cerrojo_ocupado(): void {
		$GLOBALS['_sh_lock'] = false;
	}

	/**
	 * Con el cerrojo ocupado no se duplica nada.
	 */
	public function test_duplicar_sin_cerrojo(): void {
		$this->turno( 1, gmdate( 'Y-m-d H:i:s', time() - 3600 ), '13:00' );
		$this->cerrojo_ocupado();

		$this->assertSame( 0, \Convoca\Shifts\convoca_shifts_duplicar_semana() );
		$this->assertArrayNotHasKey( '_sh_insert', $GLOBALS, 'no se intenta insertar nada' );
	}

	/**
	 * El camino bueno: el turno se copia una semana después, con sus notas y su actividad.
	 */
	public function test_duplicar_la_semana(): void {
		$this->turno( 1, gmdate( 'Y-m-d H:i:s', time() - 3600 ), '13:00' );
		$GLOBALS['_wp_stores']['post_meta'][1]['_notas']       = 'Llevar las llaves';
		$GLOBALS['_wp_stores']['post_meta'][1]['_necesita_apoyo'] = 1;
		$GLOBALS['_wp_stores']['post_meta'][1]['_monitor']      = 9;
		$this->cerrojo_libre();

		$total = \Convoca\Shifts\convoca_shifts_duplicar_semana();

		$this->assertSame( 1, $total, 'un turno duplicado' );
		$this->assertSame( 'Llevar las llaves', $GLOBALS['_wp_stores']['post_meta'][ $this->nuevo_id() ]['_notas'], 'las notas viajan' );
		$this->assertContains( 'COMMIT', $GLOBALS['_sh_db']['consultas'] );
		$this->assertContains( 'turno_creado', array_column( $GLOBALS['_sh_registro'], 'action' ) );
		$this->assertContains( 'origen', array_keys( $GLOBALS['_sh_registro'][0]['data'] ), 'se dice de dónde viene' );
		$this->assertContains( 'convoca_shifts_resumen_turnos_semana', $GLOBALS['_wp_stores']['transients_borrados'] ?? array(), 'la caché del resumen se invalida' );
	}

	/**
	 * El recorte al horario es la clave con la que se busca el duplicado: si se comparara la hora sin
	 * recortar, no casaría con el turno que se creó (que sí está recortado) y se duplicaría el trabajo.
	 */
	public function test_el_recorte_es_la_clave_del_duplicado(): void {
		$dia  = gmdate( 'Y-m-d', time() - 3600 );
		$ayer = $dia . ' 03:00:00';
		$this->turno( 1, $ayer, '23:30' );

		// El turno que ya existe en el destino, con las horas YA recortadas al horario.
		$destino = gmdate( 'Y-m-d', strtotime( $ayer ) + ( 7 * 86400 ) );
		$GLOBALS['_wp_stores']['posts'][2] = (object) array(
			'ID'          => 2,
			'post_type'   => 'centro_turno',
			'post_status' => 'publish',
			'post_title'  => 'Ya existe',
			'post_date'   => $destino . ' ' . \Convoca\Shifts\Horario::apertura() . ':00',
		);
		$GLOBALS['_wp_stores']['post_meta'][2] = array(
			'_hora_fin'     => \Convoca\Shifts\Horario::cierre(),
			'_fecha_inicio' => $destino . ' ' . \Convoca\Shifts\Horario::apertura() . ':00',
		);
		$this->cerrojo_libre();

		$this->assertSame(
			0,
			\Convoca\Shifts\convoca_shifts_duplicar_semana(),
			'el destino ya existe con las horas recortadas: no se duplica'
		);
	}

	/**
	 * Un turno con la hora al revés se endereza **antes** de buscar duplicados: si no, la clave de
	 * búsqueda sería (21:00, 09:00) y no casaría con el turno que se creó enderezado.
	 */
	public function test_la_hora_al_reves_se_endereza_para_el_duplicado(): void {
		$dia = gmdate( 'Y-m-d', time() - 3600 );
		$this->turno( 1, $dia . ' 21:00:00', '09:00' );

		$destino = gmdate( 'Y-m-d', strtotime( $dia . ' 21:00:00' ) + ( 7 * 86400 ) );
		$GLOBALS['_wp_stores']['posts'][2] = (object) array(
			'ID'          => 2,
			'post_type'   => 'centro_turno',
			'post_status' => 'publish',
			'post_title'  => 'Ya existe',
			'post_date'   => $destino . ' ' . \Convoca\Shifts\Horario::apertura() . ':00',
		);
		$GLOBALS['_wp_stores']['post_meta'][2] = array(
			'_hora_fin'     => \Convoca\Shifts\Horario::cierre(),
			'_fecha_inicio' => $destino . ' ' . \Convoca\Shifts\Horario::apertura() . ':00',
		);
		$this->cerrojo_libre();

		$this->assertSame(
			0,
			\Convoca\Shifts\convoca_shifts_duplicar_semana(),
			'enderezado queda (apertura, cierre): el destino ya está y no se duplica'
		);
	}

	/**
	 * Un turno que ya existe en el día y hora de destino no se duplica (shifts#12).
	 */
	public function test_no_se_duplica_un_turno_que_ya_existe(): void {
		// Dentro del horario del centro, para que el recorte no cambie las horas y el destino sea predecible.
		$fecha = gmdate( 'Y-m-d', time() - 3600 ) . ' ' . \Convoca\Shifts\Horario::apertura() . ':00';
		$this->turno( 1, $fecha, '13:00' );
		// El destino se calcula igual que lo hace el código: hora local de la entrada, una semana después.
		$arranque = wp_date( 'H:i', get_post_timestamp( $GLOBALS['_wp_stores']['posts'][1] ) );
		$destino  = gmdate( 'Y-m-d', get_post_timestamp( $GLOBALS['_wp_stores']['posts'][1] ) + ( 7 * 86400 ) );
		$GLOBALS['_wp_stores']['posts'][2] = (object) array(
			'ID'          => 2,
			'post_type'   => 'centro_turno',
			'post_status' => 'publish',
			'post_title'  => 'Ya existe',
			'post_date'   => $destino . ' ' . $arranque . ':00',
		);
		$GLOBALS['_wp_stores']['post_meta'][2] = array(
			'_hora_fin'     => wp_date( 'H:i', strtotime( '13:00' ) ),
			'_fecha_inicio' => $destino . ' ' . $arranque . ':00',
		);
		$this->cerrojo_libre();

		$this->assertSame( 0, \Convoca\Shifts\convoca_shifts_duplicar_semana(), 'ya estaba: no se toca' );
	}

	/**
	 * Las horas se recortan al horario del centro, por abajo y por arriba.
	 */
	public function test_las_horas_se_recortan_al_horario(): void {
		// Madrugada (antes de abrir) y un fin más tarde del cierre.
		$this->turno( 1, gmdate( 'Y-m-d', time() - 3600 ) . ' 03:00:00', '23:30' );
		$this->cerrojo_libre();

		\Convoca\Shifts\convoca_shifts_duplicar_semana();

		$meta = $GLOBALS['_wp_stores']['post_meta'][ $this->nuevo_id() ];
		$this->assertSame(
			\Convoca\Shifts\Horario::apertura(),
			substr( $meta['_fecha_inicio'], 11, 5 ),
			'el arranque se sube a la hora de apertura'
		);
		$this->assertSame(
			\Convoca\Shifts\Horario::cierre(),
			$meta['_hora_fin'],
			'el fin se baja a la hora de cierre'
		);
	}

	/**
	 * Si el turno no se puede crear, no se cuenta y se sigue con el resto.
	 */
	public function test_si_un_turno_no_se_crea(): void {
		$this->turno( 1, gmdate( 'Y-m-d H:i:s', time() - 3600 ), '13:00' );
		$this->cerrojo_libre();
		$GLOBALS['_sh_insert_error'] = new \WP_Error( 'boom', 'no se pudo' );

		$this->assertSame( 0, \Convoca\Shifts\convoca_shifts_duplicar_semana() );
		$this->assertNotContains( 'turno_creado', array_column( $GLOBALS['_sh_registro'] ?? array(), 'action' ) );
	}

	/**
	 * Si algo revienta a medias, se deshace **y el cerrojo se suelta**.
	 */
	public function test_si_revienta_se_deshace_y_se_suelta_el_cerrojo(): void {
		$this->turno( 1, gmdate( 'Y-m-d H:i:s', time() - 3600 ), '13:00' );
		$this->cerrojo_libre();
		$GLOBALS['_sh_db']['lanza_en'] = 'COMMIT';

		try {
			\Convoca\Shifts\convoca_shifts_duplicar_semana();
			$this->fail( 'debería haber lanzado' );
		} catch ( \RuntimeException $e ) {
			$this->assertContains( 'ROLLBACK', $GLOBALS['_sh_db']['consultas'], 'se deshace' );
		}

		$this->assertArrayNotHasKey(
			'convoca_shifts_duplicating_week',
			$GLOBALS['_wp_stores']['locks'] ?? array(),
			'el cerrojo se suelta en el finally, aunque reviente'
		);
	}

	/**
	 * Generar una semana sin fecha no hace nada.
	 */
	public function test_generar_sin_fecha(): void {
		$r = \Convoca\Shifts\convoca_shifts_crear_semana_tipo( '', array() );

		$this->assertSame( 'no_date', $r->get_error_code() );
	}

	/**
	 * Con una fecha que no se entiende, tampoco.
	 */
	public function test_generar_con_fecha_invalida(): void {
		$this->cerrojo_libre();

		$r = \Convoca\Shifts\convoca_shifts_crear_semana_tipo( 'no es una fecha', array() );

		$this->assertSame( 'invalid_date', $r->get_error_code() );
	}

	/**
	 * Con el cerrojo ocupado no se genera nada.
	 */
	public function test_generar_sin_cerrojo(): void {
		$this->cerrojo_ocupado();

		$total = \Convoca\Shifts\convoca_shifts_crear_semana_tipo(
			'2026-11-02',
			array( 0 => array( 'activo' => '1' ) )
		);

		$this->assertSame( 0, $total, 'sin cerrojo no se genera' );
		$this->assertContains( 'convoca_shifts_creating_week', $GLOBALS['_sh_locks_denegados'], 'se pidió el cerrojo' );
	}

	/**
	 * Los días apagados no generan turno; los encendidos sí, con sus valores por defecto.
	 */
	public function test_generar_la_semana(): void {
		$this->cerrojo_libre();

		$total = \Convoca\Shifts\convoca_shifts_crear_semana_tipo(
			'2026-11-02',
			array(
				0 => array(
					'activo' => '1',
					'hora_inicio' => '17:00',
					'hora_fin'    => '19:00',
					'estado'      => 'abierto_disponible',
				),
				1 => array( 'activo' => '0' ),
				2 => array(
					'activo'      => '1',
					'hora_inicio' => 'esto no es una hora',
					'hora_fin'    => '21:00',
					'apoyo'       => '1',
				),
			)
		);

		$this->assertSame( 2, $total, 'dos días activos' );
		$this->assertContains( 'turno_creado', array_column( $GLOBALS['_sh_registro'], 'action' ) );
		$this->assertContains( 'COMMIT', $GLOBALS['_sh_db']['consultas'] );
	}

	/**
	 * Si el alta de un día falla, no cuenta como turno creado y no se le escriben metas al id 0.
	 */
	public function test_generar_si_un_dia_no_se_crea(): void {
		$this->cerrojo_libre();
		$GLOBALS['_sh_insert_error'] = new \WP_Error( 'boom', 'no se pudo' );

		$total = \Convoca\Shifts\convoca_shifts_crear_semana_tipo(
			'2026-11-02',
			array(
				0 => array(
					'activo'      => '1',
					'hora_inicio' => '17:00',
					'hora_fin'    => '19:00',
				),
			)
		);

		$this->assertSame( 0, $total, 'el día no se creó' );
		$this->assertArrayNotHasKey( '_fecha_inicio', $GLOBALS['_wp_stores']['post_meta'][0] ?? array(), 'nada escrito en el id 0' );
	}

	/**
	 * Un día sin horas usa las de por defecto; y una hora mal escrita también.
	 */
	public function test_horas_por_defecto_y_mal_escritas(): void {
		$this->cerrojo_libre();

		\Convoca\Shifts\convoca_shifts_crear_semana_tipo(
			'2026-11-02',
			array(
				0 => array(
					'activo'      => '1',
					'hora_inicio' => '10:00',
					'hora_fin'    => '12:00',
				),
				1 => array( 'activo' => '1' ),
				2 => array(
					'activo'      => '1',
					'hora_inicio' => 'esto no es una hora',
					'hora_fin'    => 'esto tampoco',
				),
			)
		);

		$horas = array();
		foreach ( $GLOBALS['_wp_stores']['post_meta'] as $metas ) {
			if ( isset( $metas['_fecha_inicio'] ) ) {
				$horas[] = substr( $metas['_fecha_inicio'], 11, 5 ) . '-' . $metas['_hora_fin'];
			}
		}

		$this->assertSame(
			array( '10:00-12:00', '17:00-21:00', '17:00-21:00' ),
			$horas,
			'la hora explícita se respeta; sin hora y con hora ilegible, las de por defecto'
		);
	}

	/**
	 * Un día con la hora al revés se corrige con el horario del centro, no se descarta.
	 */
	public function test_generar_corrige_horas_imposibles(): void {
		$this->cerrojo_libre();

		$total = \Convoca\Shifts\convoca_shifts_crear_semana_tipo(
			'2026-11-02',
			array(
				0 => array(
					'activo'      => '1',
					'hora_inicio' => '21:00',
					'hora_fin'    => '09:00',
				),
			)
		);

		$this->assertSame( 1, $total, 'se genera igual' );
		$meta = $GLOBALS['_wp_stores']['post_meta'][ $this->nuevo_id() ];
		$this->assertSame( \Convoca\Shifts\Horario::apertura(), substr( $meta['_fecha_inicio'], 11, 5 ), 'arranca al abrir' );
		$this->assertSame( \Convoca\Shifts\Horario::cierre(), $meta['_hora_fin'], 'y acaba al cerrar' );
	}

	/**
	 * Un día cuya hora ya existe no se vuelve a insertar (shifts#12).
	 */
	public function test_generar_no_repite_un_dia_que_ya_esta(): void {
		$GLOBALS['_wp_stores']['posts'][2] = (object) array(
			'ID'          => 2,
			'post_type'   => 'centro_turno',
			'post_status' => 'publish',
			'post_title'  => 'Ya existe',
			'post_date'   => '2026-11-02 17:00:00',
		);
		$GLOBALS['_wp_stores']['post_meta'][2] = array(
			'_hora_fin'     => '19:00',
			'_fecha_inicio' => '2026-11-02 17:00:00',
		);
		$this->cerrojo_libre();

		$total = \Convoca\Shifts\convoca_shifts_crear_semana_tipo(
			'2026-11-02',
			array(
				0 => array(
					'activo'      => '1',
					'hora_inicio' => '17:00',
					'hora_fin'    => '19:00',
				),
			)
		);

		$this->assertSame( 0, $total, 'ya estaba en ese día y hora' );
	}

	/**
	 * Si algo revienta a medias al generar, se deshace y se suelta el cerrojo.
	 */
	public function test_generar_si_revienta(): void {
		$this->cerrojo_libre();
		$GLOBALS['_sh_db']['lanza_en'] = 'COMMIT';

		try {
			\Convoca\Shifts\convoca_shifts_crear_semana_tipo(
				'2026-11-02',
				array( 0 => array( 'activo' => '1' ) )
			);
			$this->fail( 'debería haber lanzado' );
		} catch ( \RuntimeException $e ) {
			$this->assertContains( 'ROLLBACK', $GLOBALS['_sh_db']['consultas'], 'se deshace' );
		}

		$this->assertArrayNotHasKey(
			'convoca_shifts_creating_week',
			$GLOBALS['_wp_stores']['locks'] ?? array(),
			'el cerrojo se suelta en el finally'
		);
	}

	/**
	 * Al generar, un día con la hora al revés se endereza antes de buscar duplicados.
	 */
	public function test_generar_endereza_la_hora_al_reves_para_el_duplicado(): void {
		$GLOBALS['_wp_stores']['posts'][2] = (object) array(
			'ID'          => 2,
			'post_type'   => 'centro_turno',
			'post_status' => 'publish',
			'post_title'  => 'Ya existe',
			'post_date'   => '2026-11-02 ' . \Convoca\Shifts\Horario::apertura() . ':00',
		);
		$GLOBALS['_wp_stores']['post_meta'][2] = array(
			'_hora_fin'     => \Convoca\Shifts\Horario::cierre(),
			'_fecha_inicio' => '2026-11-02 ' . \Convoca\Shifts\Horario::apertura() . ':00',
		);
		$this->cerrojo_libre();

		$total = \Convoca\Shifts\convoca_shifts_crear_semana_tipo(
			'2026-11-02',
			array(
				0 => array(
					'activo'      => '1',
					'hora_inicio' => '21:00',
					'hora_fin'    => '09:00',
				),
			)
		);

		$this->assertSame( 0, $total, 'enderezado es (apertura, cierre) y ya existe' );
	}

	/**
	 * El id del turno recién creado.
	 *
	 * @return int
	 */
	private function nuevo_id(): int {
		$ids = array();
		foreach ( $GLOBALS['_wp_stores']['post_meta'] ?? array() as $id => $metas ) {
			if ( isset( $metas['_wp_insert_data'] ) ) {
				$ids[] = (int) $id;
			}
		}

		return (int) end( $ids );
	}

	/**
	 * Deja un turno en el arnés.
	 *
	 * @param int    $id    Turno.
	 * @param string $fecha Fecha de inicio.
	 * @param string $hora  Hora de fin.
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
