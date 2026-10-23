<?php
/**
 * Reglas del turno: solapamientos, alta y guardado desde el editor.
 *
 * Lo que se prueba es lo que decide si una persona queda apuntada dos veces a la misma hora, si se
 * respeta el horario del centro y qué se guarda al editar un turno. Un fallo aquí no se ve en pantalla:
 * se ve en el cuadrante, cuando alguien ya no puede cubrir su turno.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use Convoca\Shifts\Horario;
use Convoca\Shifts\Personas_Asignables;
use PHPUnit\Framework\TestCase;

/**
 * Solapamientos, alta y guardado.
 */
final class TurnoReglasTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();
		$base = dirname( __DIR__, 2 ) . '/includes/';

		foreach ( array( 'horario', 'class-personas-asignables', 'cpt-turno' ) as $fichero ) {
			require_once $base . $fichero . '.php';
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
		foreach ( array( '_sh_db', '_sh_registro', '_sh_publicados', '_sh_terminos', '_sh_terminos_existentes', '_sh_insert_error', '_wp_stores' ) as $clave ) {
			unset( $GLOBALS[ $clave ] );
		}

		$GLOBALS['_wp_stores']              = array();
		$GLOBALS['__shifts_filtros']        = array();
		$GLOBALS['_test_caps']              = array();
		$_POST                              = array();
	}

	/**
	 * Con una fecha que no se entiende no hay conflicto y no se consulta la base.
	 */
	public function test_una_fecha_ilegible_no_consulta_la_base(): void {
		$this->devolver_conflicto( 99 );

		$this->assertSame( 0, \Convoca\Shifts\convoca_shifts_check_user_overlap( 9, 'no es una fecha', '2026-10-26 12:00:00' ) );
		$this->assertSame( array(), $GLOBALS['_sh_db']['consultas'] ?? array(), 'ni se pregunta' );
	}

	/**
	 * Sin solapamiento no hay conflicto.
	 */
	public function test_sin_solapamiento_no_hay_conflicto(): void {
		$this->devolver_conflicto( null );

		$this->assertEmpty( \Convoca\Shifts\convoca_shifts_check_user_overlap( 9, '2026-10-26 10:00:00', '2026-10-26 13:00:00' ) );
	}

	/**
	 * Con solapamiento devuelve el turno que choca.
	 */
	public function test_con_solapamiento_devuelve_el_turno_que_choca(): void {
		$this->devolver_conflicto( 77 );

		$this->assertSame( 77, \Convoca\Shifts\convoca_shifts_check_user_overlap( 9, '2026-10-26 10:00:00', '2026-10-26 13:00:00' ) );
	}

	/**
	 * La consulta lleva la persona, el turno excluido y el rango, y no bloquea filas si no se pide.
	 */
	public function test_la_consulta_lleva_los_datos_y_no_bloquea_por_defecto(): void {
		$this->devolver_conflicto( null );

		\Convoca\Shifts\convoca_shifts_check_user_overlap( 9, '2026-10-26 10:00:00', '2026-10-26 13:00:00', 55 );

		$sql = (string) ( $GLOBALS['_sh_db']['consultas'][0] ?? '' );

		$this->assertStringContainsString( 'pm_ini.meta_value <', $sql );
		$this->assertStringNotContainsString( 'FOR UPDATE', $sql, 'sin pedir bloqueo, la consulta no bloquea' );
	}

	/**
	 * Cuando se pide para actualizar, la consulta bloquea la fila: sin eso dos peticiones a la vez
	 * pueden pasar las dos la comprobación y dejar a la persona apuntada dos veces.
	 */
	public function test_para_actualizar_la_consulta_bloquea(): void {
		$this->devolver_conflicto( null );

		\Convoca\Shifts\convoca_shifts_check_user_overlap( 9, '1730000000', '1730010800', 0, true );

		$sql = (string) ( $GLOBALS['_sh_db']['consultas'][0] ?? '' );

		$this->assertStringContainsString( 'FOR UPDATE', $sql );
	}

	/**
	 * Un solapamiento se registra en la actividad, para poder mirarlo después.
	 */
	public function test_un_solapamiento_queda_registrado(): void {
		$this->devolver_conflicto( 77 );
		$GLOBALS['_wp_stores']['post_meta'][77] = array(
			'_fecha_inicio' => '2026-10-26 09:00:00',
			'_hora_fin'     => '10:30',
		);

		\Convoca\Shifts\convoca_shifts_check_user_overlap( 9, '2026-10-26 10:00:00', '2026-10-26 13:00:00' );

		$registro = $GLOBALS['_sh_registro'][0];

		$this->assertSame( 'log_debug_conflict', $registro['action'] );
		$this->assertSame( 77, $registro['turno_id'] );
		$this->assertSame( '2026-10-26 10:00:00', $registro['data']['requested_start'] );
		$this->assertSame( '10:30', $registro['data']['existing_end'] );
	}

	/**
	 * Sin conflicto no se registra nada.
	 */
	public function test_sin_conflicto_no_se_registra_nada(): void {
		$this->devolver_conflicto( null );

		\Convoca\Shifts\convoca_shifts_check_user_overlap( 9, '2026-10-26 10:00:00', '2026-10-26 13:00:00' );

		$this->assertArrayNotHasKey( '_sh_registro', $GLOBALS );
	}

	/**
	 * El alta respeta el horario del centro: lo que se sale se ajusta.
	 */
	public function test_el_alta_ajusta_al_horario_del_centro(): void {
		$GLOBALS['_wp_stores']['options']['convoca_shifts_hora_apertura'] = '10:00';
		$GLOBALS['_wp_stores']['options']['convoca_shifts_hora_cierre']   = '20:00';

		$id = \Convoca\Shifts\convoca_shifts_insert_turno(
			array(
				'date'    => '2026-10-26',
				'h_start' => '07:00',
				'h_end'   => '23:30',
			)
		);

		$meta = $GLOBALS['_wp_stores']['post_meta'][ $id ];

		$this->assertSame( '2026-10-26 10:00:00', $meta['_fecha_inicio'], 'se recorta a la apertura' );
		$this->assertSame( '20:00', $meta['_hora_fin'], 'y al cierre' );
	}

	/**
	 * Un rango al revés ocupa la jornada entera en vez de escribir una hora imposible.
	 */
	public function test_un_rango_al_reves_ocupa_la_jornada(): void {
		$GLOBALS['_wp_stores']['options']['convoca_shifts_hora_apertura'] = '10:00';
		$GLOBALS['_wp_stores']['options']['convoca_shifts_hora_cierre']   = '20:00';

		$id = \Convoca\Shifts\convoca_shifts_insert_turno(
			array(
				'date'    => '2026-10-26',
				'h_start' => '18:00',
				'h_end'   => '11:00',
			)
		);

		$meta = $GLOBALS['_wp_stores']['post_meta'][ $id ];

		$this->assertSame( '10:00', substr( $meta['_fecha_inicio'], 11, 5 ) );
		$this->assertSame( '20:00', $meta['_hora_fin'] );
	}

	/**
	 * El título dice el estado: pendiente, cerrado, ocupado por una actividad o cubierto por alguien.
	 */
	public function test_el_titulo_cuenta_el_estado(): void {
		$titulos = array();

		foreach ( array(
			'pendiente' => array( array() ),
			'cerrado'   => array( array( 'estado' => 'cerrado' ) ),
			'ocupado'   => array(
				array(
					'estado'       => 'abierto_ocupado',
					'actividad_id' => 5,
				),
			),
			'cubierto'  => array( array( 'id_responsable' => 7 ) ),
		) as $caso => $args ) {
			$this->limpiar();
			$GLOBALS['_wp_stores']['usuarios'][7]       = (object) array(
				'ID'           => 7,
				'display_name' => 'Ada Lovelace',
				'first_name'   => 'Ada',
			);
			$GLOBALS['_sh_terminos_existentes'][5]      = array(
				'term_id' => 5,
				'name'    => 'Taller de huerto',
			);
			$GLOBALS['_sh_personas_permitidas'][7]      = true;

			\Convoca\Shifts\convoca_shifts_insert_turno( array_merge( array( 'date' => '2026-10-26' ), $args[0] ) );

			$titulos[ $caso ] = $this->titulo_insertado();
		}

		$this->assertStringContainsString( 'Pendiente', $titulos['pendiente'] );
		$this->assertStringContainsString( 'Cerrado', $titulos['cerrado'] );
		$this->assertStringContainsString( 'Taller de huerto', $titulos['ocupado'], 'una actividad da nombre al turno' );
		$this->assertStringContainsString( 'Cubierto - Ada', $titulos['cubierto'] );
	}

	/**
	 * El alta guarda el contrato de metadatos: estado, inicio completo, hora de fin y apoyo.
	 */
	public function test_el_alta_guarda_el_contrato_de_metadatos(): void {
		$id = \Convoca\Shifts\convoca_shifts_insert_turno(
			array(
				'date'           => '2026-10-26',
				'h_start'        => '11:00',
				'h_end'          => '12:00',
				'estado'         => 'cerrado',
				'necesita_apoyo' => 1,
			)
		);

		$meta = $GLOBALS['_wp_stores']['post_meta'][ $id ];

		$this->assertSame( 'cerrado', $meta['_estado'] );
		$this->assertSame( '2026-10-26 11:00:00', $meta['_fecha_inicio'], 'el listado ordena por la fecha completa' );
		$this->assertSame( '12:00', $meta['_hora_fin'], 'y la hora de fin va sola' );
		$this->assertSame( 1, $meta['_necesita_apoyo'] );
		$this->assertContains( $id, $GLOBALS['_sh_publicados'] ?? array(), 'el turno se publica' );
	}

	/**
	 * Si la creación falla, no se escribe nada.
	 */
	public function test_si_la_creacion_falla_no_se_escribe_nada(): void {
		$GLOBALS['_sh_insert_error'] = true;

		$r = \Convoca\Shifts\convoca_shifts_insert_turno( array( 'date' => '2026-10-26' ) );

		$this->assertSame( 0, $r, 'sin `$wp_error`, WordPress devuelve 0' );
		$this->assertArrayNotHasKey( 'post_meta', $GLOBALS['_wp_stores'], 'y no se escribe nada' );
	}

	/**
	 * El guardado del editor no hace nada sin nonce.
	 */
	public function test_el_guardado_sin_nonce_no_toca_nada(): void {
		\Convoca\Shifts\convoca_shifts_save_turno_meta( 42 );

		$this->assertArrayNotHasKey( 'post_meta', $GLOBALS['_wp_stores'] );
	}

	/**
	 * Ni sin permiso de edición, ni durante el autoguardado.
	 */
	public function test_el_guardado_respeta_permiso_y_autoguardado(): void {
		$this->con_nonce();
		$GLOBALS['_test_caps']['edit_post'] = false;

		\Convoca\Shifts\convoca_shifts_save_turno_meta( 42 );
		$this->assertArrayNotHasKey( 'post_meta', $GLOBALS['_wp_stores'], 'sin permiso no se escribe' );
	}

	/**
	 * Con nonce y permiso se guarda el apoyo, el estado y la asistencia.
	 */
	public function test_el_guardado_escribe_lo_del_formulario(): void {
		$this->con_nonce();
		$_POST['convoca_shifts_necesita_apoyo'] = '1';
		$_POST['convoca_shifts_estado']         = 'cerrado';
		$_POST['convoca_shifts_estado_real']    = 'no_asistio';

		\Convoca\Shifts\convoca_shifts_save_turno_meta( 42 );

		$meta = $GLOBALS['_wp_stores']['post_meta'][42];

		$this->assertSame( 1, $meta['_necesita_apoyo'] );
		$this->assertSame( 'cerrado', $meta['_estado'] );
		$this->assertSame( 'no_asistio', $meta['_estado_real'] );
	}

	/**
	 * Asignar a alguien deja el turno disponible y la asistencia pendiente, y lo registra.
	 */
	public function test_asignar_a_alguien_actualiza_estado_y_registra(): void {
		$this->con_nonce();
		$this->con_personas( array( 7 => 'Ada Lovelace' ) );
		$GLOBALS['_wp_stores']['post_meta'][42] = array( '_estado' => 'cerrado' );

		$_POST['convoca_shifts_id_responsable'] = '7';

		\Convoca\Shifts\convoca_shifts_save_turno_meta( 42 );

		$meta = $GLOBALS['_wp_stores']['post_meta'][42];

		$this->assertSame( 7, $meta['_id_responsable'] );
		$this->assertSame( 'cerrado', $meta['_estado'], 'un turno cerrado no se reabre al asignar' );
		$this->assertSame( 'pendiente', $meta['_estado_real'] );
		$this->assertSame( 'turno_asignado', $GLOBALS['_sh_registro'][0]['action'] );
	}

	/**
	 * Quitar a la persona se registra como desasignación.
	 */
	public function test_desasignar_se_registra(): void {
		$this->con_nonce();
		$GLOBALS['_wp_stores']['post_meta'][42] = array( '_id_responsable' => 7 );
		$_POST['convoca_shifts_id_responsable'] = '0';

		\Convoca\Shifts\convoca_shifts_save_turno_meta( 42 );

		$this->assertSame( 0, $GLOBALS['_wp_stores']['post_meta'][42]['_id_responsable'] );
		$this->assertSame( 'turno_desasignado', $GLOBALS['_sh_registro'][0]['action'] );
	}

	/**
	 * Si la persona no cambia, no se toca el estado ni se registra nada.
	 */
	public function test_si_la_persona_no_cambia_no_se_toca_nada(): void {
		$this->con_nonce();
		$GLOBALS['_wp_stores']['post_meta'][42] = array(
			'_id_responsable' => 7,
			'_estado'         => 'abierto_disponible',
		);
		
		$_POST['convoca_shifts_id_responsable'] = '7';

		\Convoca\Shifts\convoca_shifts_save_turno_meta( 42 );

		$this->assertArrayNotHasKey( '_sh_registro', $GLOBALS );
	}

	/**
	 * Las columnas propias se colocan justo antes de la fecha.
	 */
	public function test_las_columnas_propias_van_antes_de_la_fecha(): void {
		$columnas = \Convoca\Shifts\convoca_shifts_set_custom_edit_centro_turno_columns(
			array(
				'cb'    => 'casilla',
				'title' => 'Título',
				'date'  => 'Fecha',
			)
		);

		$this->assertSame( array( 'cb', 'title', 'responsable', 'estado_real', 'date' ), array_keys( $columnas ) );
	}

	/**
	 * Pone el nonce bueno y el permiso de edición.
	 */
	/**
	 * Aporta la lista de personas asignables (el contrato lo hace por filtro).
	 *
	 * @param array<int, string> $nombres Personas por identificador.
	 */
	private function con_personas( array $nombres ): void {
		$personas = array();
		foreach ( $nombres as $id => $nombre ) {
			$personas[] = (object) array(
				'ID'           => $id,
				'display_name' => $nombre,
			);
		}

		$GLOBALS['__shifts_filtros'][ Personas_Asignables::FILTRO ] = static function () use ( $personas ) {
			return $personas;
		};

		Personas_Asignables::limpiar_cache();
	}

	/**
	 * Pone el nonce bueno y el permiso de edición.
	 */
	private function con_nonce(): void {
		$_POST['convoca_shifts_turno_meta_nonce'] = 'bueno';
		$GLOBALS['_test_caps']['edit_post']       = true;
	}

	/**
	 * Deja la base de mentira devolviendo (o no) un conflicto.
	 *
	 * @param int|null $id Turno en conflicto.
	 */
	private function devolver_conflicto( ?int $id ): void {
		$GLOBALS['_sh_db']['get_var'] = $id;
	}

	/**
	 * Título con el que se creó el turno en el arnés.
	 *
	 * @return string
	 */
	private function titulo_insertado(): string {
		foreach ( ( $GLOBALS['_wp_stores']['insertados'] ?? array() ) as $insertado ) {
			if ( isset( $insertado['post_title'] ) ) {
				return (string) $insertado['post_title'];
			}
		}

		return '';
	}
}
