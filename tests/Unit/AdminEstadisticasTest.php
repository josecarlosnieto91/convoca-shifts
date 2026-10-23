<?php
/**
 * La pantalla de estadísticas de voluntariado.
 *
 * Lo que se prueba: que la página se pinta con lo que hay (sin voluntarios, con voluntarios, con
 * actividad), que los números salen de las consultas agregadas —no de una por persona—, que la
 * exportación a CSV no se dispara sin nonce ni sin permiso, y que el diagnóstico de la tabla de logs
 * dice la verdad cuando la tabla no está.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use Convoca\Shifts\Personas_Asignables;
use PHPUnit\Framework\TestCase;

/**
 * Estadísticas de voluntariado.
 */
final class AdminEstadisticasTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();

		require_once dirname( __DIR__, 2 ) . '/includes/class-personas-asignables.php';
		require_once dirname( __DIR__, 2 ) . '/includes/admin-estadisticas.php';

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
		foreach ( array( '_sh_admin_paginas', '_sh_admin_nonces', '_sh_admin_nonces_comprobados', '_sh_admin_redirecciones', '_sh_db', '_sh_pro', '_wp_caps', '_sh_admin_nonce_ok' ) as $clave ) {
			unset( $GLOBALS[ $clave ] );
		}

		$GLOBALS['__shifts_filtros']           = array();
		$GLOBALS['_wp_stores']['posts']        = array();
		$GLOBALS['_wp_stores']['post_meta']    = array();
		$GLOBALS['_wp_stores']['usuarios']     = array();
		$GLOBALS['_test_caps']                 = array();
		$_GET                                  = array();
		$_POST                                 = array();
	}

	/**
	 * El menú se registra en el tipo de turno, con su capacidad y su callback.
	 */
	public function test_registra_el_menu(): void {
		\Convoca\Shifts\convoca_shifts_add_estadisticas_menu();

		$pagina = $GLOBALS['_sh_admin_paginas'][0];

		$this->assertSame( 'edit.php?post_type=centro_turno', $pagina['padre'], 'cuelga de los turnos' );
		$this->assertSame( 'convoca_shifts_view_stats', $pagina['cap'], 'y pide su capacidad, no `manage_options`' );
		$this->assertSame( 'convoca_shifts_estadisticas', $pagina['slug'] );
		$this->assertSame( 'Convoca\\Shifts\\convoca_shifts_estadisticas_page', $pagina['callback'] );
	}

	/**
	 * Sin tabla de logs, la página lo dice y ofrece crearla.
	 */
	public function test_sin_tabla_de_logs_avisa(): void {
		$GLOBALS['_sh_db']['tabla']   = false;
		$GLOBALS['_sh_db']['get_var'] = 0;

		$html = $this->pagina();

		$this->assertStringContainsString( 'no existe', $html );
		$this->assertStringContainsString( 'convoca_shifts_fix_logs=1', $html, 'y ofrece el enlace para crearla' );
	}

	/**
	 * Con la tabla, no se avisa de nada del esquema.
	 */
	public function test_con_tabla_no_avisa(): void {
		$GLOBALS['_sh_db']['tabla']   = 'wp_convoca_shifts_activity_log';
		$GLOBALS['_sh_db']['get_var'] = 0;

		$html = $this->pagina();

		$this->assertStringNotContainsString( 'no existe', $html );
	}

	/**
	 * Sin voluntarios ni turnos asignados lo dice, en vez de pintar una tabla vacía.
	 */
	public function test_sin_voluntarios_lo_dice(): void {
		$this->sin_personas();

		$html = $this->pagina();

		$this->assertStringContainsString( 'No hay voluntarios registrados aún', $html );
		$this->assertStringContainsString( 'No hay actividad registrada aún', $html );
	}

	/**
	 * Con un voluntario y sus números, la fila sale con sus datos.
	 */
	public function test_pinta_la_fila_del_voluntario(): void {
		$this->con_personas( array( 9 ) );
		$this->usuario( 9, 'Ada Lovelace', 'ada@example.test' );
		$this->estadisticas( 9, 4, 3, 1, 6.5, '2026-10-20' );

		$html = $this->pagina();

		$this->assertStringContainsString( 'Ada Lovelace', $html );
		$this->assertStringContainsString( 'ada@example.test', $html );
		$this->assertStringContainsString( '6.5 h', $html );
		$this->assertStringContainsString( '20/10/2026', $html );
	}

	/**
	 * Quien tiene turnos asignados pero no está en la lista también sale: si no, sus horas no se ven.
	 */
	public function test_incluye_a_quien_tiene_turnos_aunque_no_este_en_la_lista(): void {
		$this->sin_personas();
		$GLOBALS['_sh_db']['get_col'] = array( '7' );
		$this->usuario( 7, 'Grace Hopper', 'grace@example.test' );

		$html = $this->pagina();

		$this->assertStringContainsString( 'Grace Hopper', $html );
	}

	/**
	 * La actividad reciente sale con su persona, su acción y su turno.
	 */
	public function test_pinta_la_actividad_reciente(): void {
		$this->sin_personas();
		$this->usuario( 9, 'Ada Lovelace', 'ada@example.test' );
		$GLOBALS['_wp_stores']['posts'][55] = (object) array(
			'ID'         => 55,
			'post_title' => 'Turno de mañana',
		);

		$this->con_logs(
			array(
				(object) array(
					'user_id'    => 9,
					'action'     => 'turno_cubierto',
					'turno_id'   => 55,
					'data'       => '',
					'created_at' => '2026-10-20 09:30:00',
				),
			)
		);

		$html = $this->pagina();

		$this->assertStringContainsString( 'Ada Lovelace', $html );
		$this->assertStringContainsString( 'turno_cubierto', $html );
		$this->assertStringContainsString( 'Turno de mañana', $html );
		$this->assertStringContainsString( '20/10/2026 09:30', $html );
	}

	/**
	 * Si el turno ya no existe, se usa el voluntario del detalle; y si tampoco, el identificador.
	 */
	public function test_actividad_sin_turno_usa_el_detalle(): void {
		$this->sin_personas();
		$this->usuario( 9, 'Ada Lovelace', 'ada@example.test' );
		$this->usuario( 12, 'Katherine Johnson', 'kj@example.test' );
		$GLOBALS['_wp_stores']['no_existe'][77] = true;

		$this->con_logs(
			array(
				(object) array(
					'user_id'    => 9,
					'action'     => 'voluntario_aprobado',
					'turno_id'   => 0,
					'data'       => wp_json_encode( array( 'voluntario_id' => 12 ) ),
					'created_at' => '2026-10-20 10:00:00',
				),
				(object) array(
					'user_id'    => 9,
					'action'     => 'turno_liberado',
					'turno_id'   => 77,
					'data'       => '',
					'created_at' => '2026-10-20 11:00:00',
				),
			)
		);

		$html = $this->pagina();

		$this->assertStringContainsString( 'Voluntario: Katherine Johnson', $html, 'el detalle dice de quién era' );
		$this->assertStringContainsString( 'Turno ID: 77', $html, 'sin turno, al menos su identificador' );
	}

	/**
	 * Si la persona del registro ya no existe, se dice «usuario desconocido», no se deja en blanco.
	 */
	public function test_actividad_de_una_persona_que_ya_no_existe(): void {
		$this->sin_personas();
		$GLOBALS['_wp_stores']['usuarios'][404] = false;
		$GLOBALS['_wp_stores']['no_existe'][0]   = true;

		$this->con_logs(
			array(
				(object) array(
					'user_id'    => 404,
					'action'     => 'asistencia_ok',
					'turno_id'   => 0,
					'data'       => '',
					'created_at' => '2026-10-20 12:00:00',
				),
			)
		);

		$html = $this->pagina();

		$this->assertStringContainsString( 'Usuario Desconocido', $html );
	}

	/**
	 * Con más de cincuenta registros hay paginación, y en la primera página no hay enlace «anterior».
	 */
	public function test_paginacion_de_la_actividad(): void {
		$this->sin_personas();
		$GLOBALS['_sh_db']['get_var'] = 120;

		$html = $this->pagina();

		$this->assertStringContainsString( 'paged_logs=2', $html, 'se ofrece ir a la siguiente' );
		$this->assertStringNotContainsString( 'paged_logs=0', $html, 'no hay página anterior en la primera' );
		$this->assertStringContainsString( '120 elementos', $html );
	}

	/**
	 * En la segunda página sí se puede volver.
	 */
	public function test_segunda_pagina_tiene_enlace_anterior(): void {
		$this->sin_personas();
		$GLOBALS['_sh_db']['get_var'] = 120;
		$_GET['paged_logs'] = '2';

		$html = $this->pagina();

		$this->assertStringContainsString( 'paged_logs=1', $html );
	}

	/**
	 * Con una sola página no se pinta la barra.
	 */
	public function test_sin_paginacion_cuando_caben_todos(): void {
		$this->sin_personas();
		$GLOBALS['_sh_db']['get_var'] = 10;

		$html = $this->pagina();

		$this->assertStringNotContainsString( 'paged_logs=', $html );
	}

	/**
	 * Sin PRO se ofrece activar la licencia; con PRO, el aviso desaparece.
	 */
	public function test_el_aviso_de_analitica_pro(): void {
		$this->sin_personas();
		$GLOBALS['_sh_db']['get_var'] = 0;

		$sin = $this->pagina();
		$this->assertStringContainsString( 'convoca-license', $sin, 'se ofrece activar la licencia' );

		$GLOBALS['_sh_pro']['analytics'] = true;

		$con = $this->pagina();
		$this->assertStringNotContainsString( 'Analytics Avanzados', $con );
	}

	/**
	 * Los números salen de una consulta agregada y las horas se reparten por persona.
	 */
	public function test_las_estadisticas_se_agregan_de_una_vez(): void {
		$GLOBALS['_sh_db']['por_sql'] = array(
			'TIMESTAMPDIFF' => array(),
			'postmeta'      => array(
				(object) array(
					'user_id'           => '9',
					'total_turnos'      => '4',
					'realizados'        => '3',
					'no_asistio'        => '1',
					'ultimo_turno_date' => '2026-10-20 09:00:00',
				),
			),
		);

		$stats = \Convoca\Shifts\convoca_shifts_preload_voluntario_stats();

		$this->assertArrayHasKey( 9, $stats );
		$this->assertSame( 4, $stats[9]['total_turnos'] );
		$this->assertSame( 3, $stats[9]['realizados'] );
		$this->assertSame( 1, $stats[9]['no_asistio'] );
		$this->assertSame( '20/10/2026', $stats[9]['ultimo_turno'] );
	}

	/**
	 * Las horas se suman de la segunda consulta, redondeadas a un decimal.
	 */
	public function test_las_horas_se_acumulan(): void {
		$GLOBALS['_sh_db']['por_sql'] = array(
			'TIMESTAMPDIFF' => array(
				(object) array(
					'user_id'       => '9',
					'total_seconds' => '5400',
				),
				(object) array(
					'user_id'       => '9',
					'total_seconds' => '1800',
				),
			),
			'postmeta'      => array(
				(object) array(
					'user_id'           => '9',
					'total_turnos'      => '2',
					'realizados'        => '2',
					'no_asistio'        => '0',
					'ultimo_turno_date' => '2026-10-20 09:00:00',
				),
			),
		);

		$stats = \Convoca\Shifts\convoca_shifts_preload_voluntario_stats();

		$this->assertSame( 2.0, $stats[9]['total_horas'], '1,5 h + 0,5 h = 2 h' );
	}

	/**
	 * Sin filas, el resumen es una lista vacía (y no revienta).
	 */
	public function test_sin_filas_no_hay_estadisticas(): void {
		$GLOBALS['_sh_db']['por_sql'] = array();

		$this->assertSame( array(), \Convoca\Shifts\convoca_shifts_preload_voluntario_stats() );
	}

	/**
	 * Si la consulta falla (la base devuelve `false`), el resumen es vacío en vez de reventar.
	 *
	 * La guarda es defensiva: sin ella, un `foreach` sobre `false` suelta un aviso por cada carga del
	 * panel. No se puede distinguir con una mutación —quitar la guarda y devolver vacío da el mismo
	 * resultado—, así que se cubre la línea y se deja dicho.
	 */
	public function test_si_la_consulta_falla_el_resumen_es_vacio(): void {
		$GLOBALS['_sh_db']['por_sql'] = array( 'TIMESTAMPDIFF' => false );

		$this->assertSame( array(), \Convoca\Shifts\convoca_shifts_preload_voluntario_stats() );
	}

	/**
	 * Una persona sin turnos tiene su resumen a cero, no un error.
	 */
	public function test_persona_sin_turnos(): void {
		$GLOBALS['_sh_db']['por_sql'] = array();

		$stats = \Convoca\Shifts\convoca_shifts_get_voluntario_stats( 123 );

		$this->assertSame( 0, $stats['total_turnos'] );
		$this->assertSame( '', $stats['ultimo_turno'] );
	}

	/**
	 * La acción se traduce cuando se conoce; si no, se deja el identificador.
	 */
	public function test_las_etiquetas_de_accion(): void {
		$this->assertSame( 'Cubierto', \Convoca\Shifts\convoca_shifts_get_action_label( 'turno_cubierto' ) );
		$this->assertSame( 'Asistencia OK', \Convoca\Shifts\convoca_shifts_get_action_label( 'asistencia_ok' ) );
		$this->assertSame( 'accion_inventada', \Convoca\Shifts\convoca_shifts_get_action_label( 'accion_inventada' ) );
	}

	/**
	 * La exportación no se dispara sin petición, ni sin nonce, ni sin permiso.
	 */
	public function test_la_exportacion_no_se_dispara_a_la_ligera(): void {
		global $wpdb;

		$voluntarios = array();

		\Convoca\Shifts\convoca_shifts_exportar_stats_csv_handler();
		$this->assertArrayNotHasKey( 'get_col', $GLOBALS['_sh_db'] ?? array(), 'sin petición no se consulta nada' );

		$_POST['convoca_shifts_action'] = 'exportar_stats_csv';
		$GLOBALS['_sh_admin_nonce_ok']  = false;

		\Convoca\Shifts\convoca_shifts_exportar_stats_csv_handler();

		$this->assertContains( 'convoca_shifts_exportar_stats_action', $GLOBALS['_sh_admin_nonces_comprobados'] );
		$this->assertArrayNotHasKey( 'get_col', $GLOBALS['_sh_db'] ?? array(), 'sin nonce válido no se consulta nada' );

		$GLOBALS['_sh_admin_nonce_ok']      = true;
		$GLOBALS['_test_caps']['convoca_shifts_view_stats'] = false;

		\Convoca\Shifts\convoca_shifts_exportar_stats_csv_handler();

		$this->assertArrayNotHasKey( 'get_col', $GLOBALS['_sh_db'] ?? array(), 'sin permiso tampoco' );
	}

	/**
	 * Pinta la página y devuelve su HTML.
	 *
	 * @return string
	 */
	private function pagina(): string {
		ob_start();
		\Convoca\Shifts\convoca_shifts_estadisticas_page();

		return (string) ob_get_clean();
	}

	/**
	 * Sin nadie en la lista de personas.
	 */
	private function sin_personas(): void {
		$GLOBALS['__shifts_filtros'][ Personas_Asignables::FILTRO ] = static function () {
			return array();
		};
		$GLOBALS['_sh_db']['get_col'] = array();

		Personas_Asignables::limpiar_cache();

		$GLOBALS['_sh_db']['tabla'] = $GLOBALS['_sh_db']['tabla'] ?? 'wp_convoca_shifts_activity_log';

		if ( ! isset( $GLOBALS['_sh_db']['get_var'] ) ) {
			$GLOBALS['_sh_db']['get_var'] = 0;
		}
	}

	/**
	 * Con personas en la lista.
	 *
	 * @param array $ids Identificadores.
	 */
	private function con_personas( array $ids ): void {
		$personas = array();
		foreach ( $ids as $id ) {
			$personas[] = (object) array( 'ID' => $id );
		}

		$GLOBALS['__shifts_filtros'][ Personas_Asignables::FILTRO ] = static function () use ( $personas ) {
			return $personas;
		};
		$GLOBALS['_sh_db']['get_col'] = array();

		Personas_Asignables::limpiar_cache();
	}

	/**
	 * Deja una persona en el almacén de usuarios.
	 *
	 * @param int    $id     Identificador.
	 * @param string $nombre Nombre visible.
	 * @param string $email  Correo.
	 */
	private function usuario( int $id, string $nombre, string $email ): void {
		$GLOBALS['_wp_stores']['usuarios'][ $id ] = (object) array(
			'ID'           => $id,
			'display_name' => $nombre,
			'user_email'   => $email,
		);
	}

	/**
	 * Coloca el resultado agregado de estadísticas.
	 *
	 * @param int    $uid        Persona.
	 * @param int    $total      Turnos.
	 * @param int    $hechos     Realizados.
	 * @param int    $faltas     Faltas.
	 * @param float  $horas      Horas.
	 * @param string $ultimo     Último turno.
	 */
	private function estadisticas( int $uid, int $total, int $hechos, int $faltas, float $horas, string $ultimo ): void {
		// La segunda consulta (horas) se distingue por su función de fecha: si no, recibe la primera.
		$GLOBALS['_sh_db']['por_sql'] = array(
			'TIMESTAMPDIFF' => array(
				(object) array(
					'user_id'       => (string) $uid,
					'total_seconds' => (string) ( $horas * 3600 ),
				),
			),
		);

		$GLOBALS['_sh_db']['por_sql']['postmeta'] = array(
			(object) array(
				'user_id'           => (string) $uid,
				'total_turnos'      => (string) $total,
				'realizados'        => (string) $hechos,
				'no_asistio'        => (string) $faltas,
				'ultimo_turno_date' => $ultimo . ' 09:00:00',
				'total_seconds'     => (string) ( $horas * 3600 ),
			),
		);
	}

	/**
	 * Deja los registros de actividad.
	 *
	 * @param array $logs Registros.
	 */
	private function con_logs( array $logs ): void {
		$GLOBALS['_sh_db']['por_sql']['activity_log'] = $logs;
		$GLOBALS['_sh_db']['get_var']                 = count( $logs );
	}
}
