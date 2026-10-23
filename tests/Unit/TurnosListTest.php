<?php
/**
 * El listado de turnos del panel.
 *
 * Cada columna de esta tabla es una decisión que alguien toma de un vistazo: si el turno está cubierto,
 * si la persona apareció, si hace falta apoyo. Y las acciones en bloque son las que escriben la
 * asistencia de veinticinco turnos de una vez, así que sus guardas importan.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use Convoca\Shifts\Admin_Turnos_List;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

require_once dirname( __DIR__, 2 ) . '/includes/class-admin-turnos-list.php';

/**
 * Columnas, acciones en bloque y preparación del listado.
 */
final class TurnosListTest extends TestCase {

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
		foreach ( array( '_wp_stores', '_test_caps', '_sh_registro', '_sh_pagenum', '_sh_admin_nonces_comprobados' ) as $clave ) {
			unset( $GLOBALS[ $clave ] );
		}

		$GLOBALS['_wp_stores'] = array();
		$GLOBALS['_test_caps'] = array();
		$_GET                  = array();
		$_REQUEST              = array();
	}

	/**
	 * Las columnas del listado, en su orden.
	 */
	public function test_las_columnas(): void {
		$columnas = $this->lista()->get_columns();

		$this->assertSame(
			array( 'cb', 'fecha', 'hora', 'estado', 'responsable', 'apoyo', 'estado_real', 'acciones' ),
			array_keys( $columnas )
		);
	}

	/**
	 * Se puede ordenar por fecha y por estado.
	 */
	public function test_las_columnas_ordenables(): void {
		$ordenables = $this->lista()->get_sortable_columns();

		$this->assertArrayHasKey( 'fecha', $ordenables );
		$this->assertArrayHasKey( 'estado', $ordenables );
	}

	/**
	 * Las cuatro acciones en bloque de asistencia.
	 */
	public function test_las_acciones_en_bloque(): void {
		$this->assertSame(
			array( 'mark_realizado', 'mark_no_asistio', 'mark_justificada', 'mark_cancelada_aviso' ),
			array_keys( $this->lista()->get_bulk_actions() )
		);
	}

	/**
	 * La fecha sale del metadato; sin él, un guion (no una fecha inventada).
	 */
	public function test_la_columna_de_fecha(): void {
		$this->meta( 1, '_fecha_inicio', '2026-10-26 10:00:00' );

		$this->assertSame( '26/10/2026', $this->columna( 'fecha', 1 ) );
		$this->assertSame( '—', $this->columna( 'fecha', 2 ), 'sin fecha, guion' );
	}

	/**
	 * La hora: con hora de fin, el rango; sin ella, solo el inicio.
	 */
	public function test_la_columna_de_hora(): void {
		$this->meta( 1, '_fecha_inicio', '2026-10-26 10:00:00' );
		$this->meta( 1, '_hora_fin', '13:00' );
		$this->meta( 2, '_fecha_inicio', '2026-10-26 17:00:00' );

		$this->assertSame( '10:00 - 13:00', $this->columna( 'hora', 1 ) );
		$this->assertSame( '17:00', $this->columna( 'hora', 2 ) );
		$this->assertSame( '—', $this->columna( 'hora', 3 ) );
	}

	/**
	 * El estado se pinta con su etiqueta; uno desconocido se muestra tal cual, no pierde el dato.
	 */
	public function test_la_columna_de_estado(): void {
		$this->meta( 1, '_estado', 'abierto_disponible' );
		$this->meta( 2, '_estado', 'abierto_ocupado' );
		$this->meta( 3, '_estado', 'cerrado' );
		$this->meta( 4, '_estado', 'inventado' );

		$this->assertStringContainsString( 'Pendiente', $this->columna( 'estado', 1 ) );
		$this->assertStringContainsString( 'Ocupado', $this->columna( 'estado', 2 ) );
		$this->assertStringContainsString( 'Cerrado', $this->columna( 'estado', 3 ) );
		$this->assertStringContainsString( 'inventado', $this->columna( 'estado', 4 ) );
	}

	/**
	 * El responsable: sin persona, un guion; con persona, su nombre; con un id que ya no existe, guion.
	 *
	 * La salida temprana de «sin persona» es redundante con el ternario final (`get_userdata(0)` ya
	 * devuelve falso), así que quitarla no cambia el resultado y no se puede distinguir con una mutación.
	 * Se cubre la línea y se deja dicho, en vez de inventar una diferencia que no existe.
	 */
	public function test_la_columna_de_responsable(): void {
		$this->meta( 1, '_id_responsable', 7 );
		$this->meta( 2, '_id_responsable', 0 );
		$this->meta( 3, '_id_responsable', 404 );
		$GLOBALS['_wp_stores']['usuarios'][7]   = (object) array(
			'ID'           => 7,
			'display_name' => 'Ada Lovelace',
		);
		$GLOBALS['_wp_stores']['usuarios'][404] = false;

		$this->assertSame( 'Ada Lovelace', $this->columna( 'responsable', 1 ) );
		$this->assertStringContainsString( '—', $this->columna( 'responsable', 2 ) );
		$this->assertStringContainsString( '—', $this->columna( 'responsable', 3 ), 'una persona borrada no deja el hueco vacío' );
	}

	/**
	 * El apoyo se ve de un vistazo.
	 */
	public function test_la_columna_de_apoyo(): void {
		$this->meta( 1, '_necesita_apoyo', 1 );
		$this->meta( 2, '_necesita_apoyo', 0 );

		$this->assertStringContainsString( 'Sí', $this->columna( 'apoyo', 1 ) );
		$this->assertSame( '<span style="color:#475569;">No</span>', $this->columna( 'apoyo', 2 ) );
	}

	/**
	 * La asistencia: sin dato se lee «pendiente», y los estados conocidos traen su etiqueta.
	 */
	public function test_la_columna_de_asistencia(): void {
		$this->meta( 1, '_estado_real', 'realizado' );
		$this->meta( 2, '_estado_real', 'no_asistio' );
		$this->meta( 3, '_estado_real', 'justificada' );
		$this->meta( 4, '_estado_real', 'cancelada_aviso' );

		$this->assertStringContainsString( 'Realizado', $this->columna( 'estado_real', 1 ) );
		$this->assertStringContainsString( 'No asistió', $this->columna( 'estado_real', 2 ) );
		$this->assertStringContainsString( 'Ausencia justificada', $this->columna( 'estado_real', 3 ) );
		$this->assertStringContainsString( 'Cancelada con aviso', $this->columna( 'estado_real', 4 ) );
		$this->assertStringContainsString( 'Pendiente', $this->columna( 'estado_real', 5 ), 'sin dato, pendiente' );
	}

	/**
	 * La columna de acciones lleva al editor de ese turno.
	 */
	public function test_la_columna_de_acciones(): void {
		$html = $this->columna( 'acciones', 42 );

		$this->assertStringContainsString( 'id=42', $html, 'el enlace apunta al turno de esa fila' );
		$this->assertStringContainsString( 'Editar', $html );
	}

	/**
	 * Sin acción pedida, las acciones en bloque no hacen nada.
	 */
	public function test_sin_accion_no_se_hace_nada(): void {
		$this->lista()->process_bulk_action();

		$this->assertArrayNotHasKey( 'post_meta', $GLOBALS['_wp_stores'] );
	}

	/**
	 * Una acción que no es de asistencia tampoco.
	 */
	public function test_una_accion_desconocida_no_hace_nada(): void {
		$_REQUEST['action'] = 'borrar_todo';
		$_GET['turno']      = array( 1 );

		$this->lista()->process_bulk_action();

		$this->assertArrayNotHasKey( 'post_meta', $GLOBALS['_wp_stores'] );
		$this->assertArrayNotHasKey( '_sh_admin_nonces_comprobados', $GLOBALS, 'ni se comprueba el nonce' );
	}

	/**
	 * Con acción válida se comprueba el nonce aunque no haya turnos seleccionados.
	 */
	public function test_con_accion_valida_se_comprueba_el_nonce(): void {
		$_REQUEST['action'] = 'mark_realizado';

		$this->lista()->process_bulk_action();

		$this->assertContains( 'bulk-turnos', $GLOBALS['_sh_admin_nonces_comprobados'] ?? array() );
		$this->assertArrayNotHasKey( 'post_meta', $GLOBALS['_wp_stores'], 'sin turnos no se escribe nada' );
	}

	/**
	 * `prepare_items()` deja las filas que devuelve la consulta y su total.
	 */
	public function test_prepare_items_carga_las_filas(): void {
		$GLOBALS['_wp_stores']['posts'] = array(
			1 => (object) array(
				'ID'          => 1,
				'post_type'   => 'centro_turno',
				'post_status' => 'publish',
			),
			2 => (object) array(
				'ID'          => 2,
				'post_type'   => 'centro_turno',
				'post_status' => 'publish',
			),
		);

		$lista = $this->lista();
		$lista->prepare_items();

		$this->assertCount( 2, $lista->items );
		$this->assertSame( 25, $lista->paginacion['per_page'], 'la tabla va de 25 en 25' );
		$this->assertSame( 2, $lista->paginacion['total_items'] );
	}

	/**
	 * Los filtros de la barra llegan a la consulta como meta_query.
	 */
	public function test_los_filtros_llegan_a_la_consulta(): void {
		$GLOBALS['_wp_stores']['posts'] = array(
			1 => (object) array(
				'ID'          => 1,
				'post_type'   => 'centro_turno',
				'post_status' => 'publish',
			),
		);
		$this->meta( 1, '_estado', 'cerrado' );

		$lista = $this->lista();
		$_GET['filter_estado'] = 'cerrado';
		$lista->prepare_items();

		$this->assertCount( 1, $lista->items );

		$_GET['filter_estado'] = 'abierto_disponible';
		$lista->prepare_items();

		$this->assertCount( 0, $lista->items, 'el filtro deja fuera lo que no coincide' );
	}

	/**
	 * Crea el listado.
	 *
	 * @return Admin_Turnos_List
	 */
	private function lista(): Admin_Turnos_List {
		return new Admin_Turnos_List();
	}

	/**
	 * Pinta una columna protegida de una fila.
	 *
	 * @param string $columna Columna.
	 * @param int    $id      Turno.
	 * @return string
	 */
	private function columna( string $columna, int $id ): string {
		// Desde PHP 8.1 no hace falta `setAccessible()`: llamar al método protegido funciona igual.
		$metodo = new ReflectionMethod( Admin_Turnos_List::class, 'column_' . $columna );

		$item = (object) array( 'ID' => $id );

		return (string) $metodo->invoke( $this->lista(), $item );
	}

	/**
	 * Escribe un metadato del turno.
	 *
	 * @param int    $id    Turno.
	 * @param string $clave Clave.
	 * @param mixed  $valor Valor.
	 */
	private function meta( int $id, string $clave, $valor ): void {
		$GLOBALS['_wp_stores']['post_meta'][ $id ][ $clave ] = $valor;
	}
}
