<?php
/**
 * La lectura de turnos por la API: lo que se sirve y a quién.
 *
 * Esta ruta es **pública**, y por ella viajó el responsable, las notas y el monitor de cada turno a
 * quien preguntara sin sesión (issue #4). Lo que se prueba aquí es esa frontera: sin permiso, solo el
 * estado y la actividad; con permiso, el detalle interno. Y que el calendario reciba lo que necesita
 * para pintar: título, colores y fin del turno.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/rest-api.php';

/**
 * Qué se sirve en `/turnos` y con qué permiso.
 */
final class RestApiTurnosTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();

		if ( ! class_exists( \Convoca\Shifts\Horario::class ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/horario.php';
		}
		if ( ! class_exists( \Convoca\Shifts\Personas_Asignables::class ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/class-personas-asignables.php';
		}
		if ( ! function_exists( 'Convoca\Shifts\convoca_shifts_log_activity' ) ) {
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
		foreach ( array( '_wp_stores', '_test_caps', '_sh_db', '_sh_rate_limit', '_sh_term_meta', '_sh_terminos' ) as $clave ) {
			unset( $GLOBALS[ $clave ] );
		}

		$GLOBALS['_wp_stores'] = array();
		$GLOBALS['_test_caps'] = array();
	}

	/**
	 * Sin permiso se sirve lo público y **nada** del detalle interno.
	 */
	public function test_sin_permiso_no_se_sirve_el_detalle_interno(): void {
		$this->turno( 1, 'abierto_disponible', 0 );
		$this->meta( 1, '_notas', 'Llevar las llaves' );
		$this->meta( 1, '_monitor', 9);
		$this->meta( 1, '_necesita_apoyo', 1 );
		$this->sin_permisos();

		$props = $this->evento( 0 )['extendedProps'];

		$this->assertArrayHasKey( 'estado', $props );
		$this->assertArrayHasKey( 'actividad', $props, 'la actividad sí: es información pública del centro' );
		$this->assertArrayNotHasKey( 'responsable_id', $props, 'quién lo lleva no viaja sin sesión' );
		$this->assertArrayNotHasKey( 'responsable_nombre', $props );
		$this->assertArrayNotHasKey( 'notas', $props );
		$this->assertArrayNotHasKey( 'monitor', $props );
		$this->assertArrayNotHasKey( 'necesita_apoyo', $props );
	}

	/**
	 * Con permiso de gestión, sí viene el detalle: es para quien organiza.
	 */
	public function test_con_permiso_se_sirve_el_detalle(): void {
		$this->turno( 1, 'abierto_disponible', 7 );
		$this->meta( 1, '_notas', 'Llevar las llaves' );
		$this->meta( 1, '_monitor', 9 );
		$this->meta( 1, '_necesita_apoyo', 1 );
		$this->meta( 1, '_entregado', 1 );
		$GLOBALS['_wp_stores']['usuarios'][7] = (object) array(
			'ID'           => 7,
			'display_name' => 'Ada Lovelace',
			'first_name'   => 'Ada',
		);
		$GLOBALS['_wp_stores']['usuarios'][9] = (object) array(
			'ID'           => 9,
			'display_name' => 'Grace Hopper',
			'first_name'   => 'Grace',
		);
		$this->con_permisos();

		$props = $this->evento( 0 )['extendedProps'];

		$this->assertSame( 7, $props['responsable_id'] );
		$this->assertSame( 'Ada', $props['responsable_nombre'], 'se saluda por el nombre de pila' );
		$this->assertSame( 'Llevar las llaves', $props['notas'] );
		$this->assertSame( 1, $props['necesita_apoyo'] );
		$this->assertSame( 'Grace Hopper', $props['monitor'] );
	}

	/**
	 * Quien administra el sitio también ve el detalle, sin tener la capacidad del módulo.
	 */
	public function test_con_solo_administracion_tambien_se_sirve_el_detalle(): void {
		$this->turno( 1, 'abierto_disponible', 0 );
		$this->meta( 1, '_notas', 'Llevar las llaves' );
		$GLOBALS['_test_caps']['convoca_shifts_manage_turnos'] = false;
		$GLOBALS['_test_caps']['manage_options']               = true;

		$props = $this->evento( 0 )['extendedProps'];

		$this->assertArrayHasKey( 'notas', $props, 'administrar el sitio basta' );
	}

	/**
	 * Los cuatro estados del calendario, con su color y su título.
	 */
	public function test_los_colores_y_titulos_del_calendario(): void {
		$this->turno( 1, 'cerrado', 0 );
		$this->turno( 2, 'abierto_ocupado', 0 );
		$this->turno( 3, 'abierto_disponible', 7 );
		$this->turno( 4, 'abierto_disponible', 0 );
		$GLOBALS['_wp_stores']['usuarios'][7] = (object) array(
			'ID'           => 7,
			'display_name' => 'Ada Lovelace',
			'first_name'   => 'Ada',
		);
		$this->con_permisos();

		$eventos = $this->eventos();

		$this->assertSame( '#e74c3c', $eventos[0]['color'], 'cerrado: rojo' );
		$this->assertStringContainsString( 'Centro Cerrado', $eventos[0]['title'] );
		$this->assertSame( '#3498db', $eventos[1]['color'], 'ocupado: azul' );
		$this->assertStringContainsString( 'Actividad Test', $eventos[1]['title'], 'el nombre de la actividad' );
		$this->assertSame( '#2ecc71', $eventos[2]['color'], 'cubierto: verde' );
		$this->assertStringContainsString( 'Cubierto - Ada', $eventos[2]['title'] );
		$this->assertSame( '#f1c40f', $eventos[3]['color'], 'pendiente: amarillo' );
		$this->assertStringContainsString( '🟡 Pendiente', $eventos[3]['title'] );
	}

	/**
	 * El fin del turno sale de su hora de fin; sin ella, dos horas después del inicio.
	 */
	public function test_el_fin_del_turno(): void {
		$this->turno( 1, 'abierto_disponible', 0, '2026-10-26 10:00:00' );
		$this->meta( 1, '_hora_fin', '13:00' );
		$this->turno( 2, 'abierto_disponible', 0, '2026-10-27 10:00:00' );
		$this->con_permisos();

		$eventos = $this->eventos();

		$this->assertSame( '2026-10-26T13:00:00', $eventos[0]['end'], 'con hora de fin, la suya' );
		$this->assertSame( '2026-10-27T12:00:00', $eventos[1]['end'], 'sin hora de fin, dos horas' );
	}

	/**
	 * Se puede pedir un rango de fechas y solo vienen los turnos de dentro.
	 */
	public function test_el_rango_de_fechas(): void {
		$this->turno( 1, 'abierto_disponible', 0, '2026-10-26 10:00:00' );
		$this->turno( 2, 'abierto_disponible', 0, '2026-11-15 10:00:00' );
		$this->con_permisos();

		$eventos = $this->eventos(
			array(
				'start' => '2026-10-20T00:00:00',
				'end'   => '2026-10-31T23:59:59',
			)
		);

		$this->assertCount( 1, $eventos, 'solo el de la semana pedida' );
		$this->assertSame( 1, $eventos[0]['id'] );
	}

	/**
	 * Con el límite de peticiones pasado, 429 en vez de trabajo.
	 */
	public function test_el_limite_de_peticiones(): void {
		$GLOBALS['_sh_rate_limit'] = false;

		$r = \Convoca\Shifts\convoca_shifts_rest_get_turnos( new \WP_REST_Request() );

		$this->assertSame( 'rest_rate_limit', $r->get_error_code() );
	}

	/**
	 * Sin turnos, una lista vacía (el calendario se pinta sin eventos, no revienta).
	 */
	public function test_sin_turnos_devuelve_lista_vacia(): void {
		$GLOBALS['_wp_stores']['posts'] = array();

		$this->assertSame( array(), $this->eventos() );
	}

	/**
	 * Llama a la ruta y devuelve sus eventos.
	 *
	 * @param array $params Parámetros.
	 * @return array
	 */
	private function eventos( array $params = array() ): array {
		return \Convoca\Shifts\convoca_shifts_rest_get_turnos( new \WP_REST_Request( $params ) )->get_data();
	}

	/**
	 * El primer evento.
	 *
	 * @param int $indice Índice.
	 * @return array
	 */
	private function evento( int $indice ): array {
		return $this->eventos()[ $indice ];
	}

	/**
	 * Deja un turno con su estado, su persona y su fecha.
	 *
	 * @param int    $id     Identificador.
	 * @param string $estado Estado.
	 * @param int    $persona Persona que lo cubre.
	 * @param string $fecha  Fecha de inicio.
	 */
	private function turno( int $id, string $estado, int $persona, string $fecha = '2026-10-26 10:00:00' ): void {
		$GLOBALS['_wp_stores']['posts'][ $id ] = (object) array(
			'ID'          => $id,
			'post_type'   => 'centro_turno',
			'post_status' => 'publish',
			'post_title'  => 'Turno ' . $id,
			'post_date'   => $fecha,
		);

		$this->meta( $id, '_fecha_inicio', $fecha );
		$this->meta( $id, '_estado', $estado );
		$this->meta( $id, '_id_responsable', $persona );
	}

	/**
	 * Escribe un metadato.
	 *
	 * @param int    $id    Turno.
	 * @param string $clave Clave.
	 * @param mixed  $valor Valor.
	 */
	private function meta( int $id, string $clave, $valor ): void {
		$GLOBALS['_wp_stores']['post_meta'][ $id ][ $clave ] = $valor;
	}

	/**
	 * Sin capacidades de gestión.
	 */
	private function sin_permisos(): void {
		$GLOBALS['_test_caps']['convoca_shifts_manage_turnos'] = false;
		$GLOBALS['_test_caps']['manage_options']               = false;
	}

	/**
	 * Con capacidad de gestión de turnos.
	 */
	private function con_permisos(): void {
		$GLOBALS['_test_caps']['convoca_shifts_manage_turnos'] = true;
	}
}
