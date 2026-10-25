<?php
/**
 * El registro del tipo de contenido «turno» y sus metas.
 *
 * Aquí se decide qué campos se pueden escribir **por REST** y con qué permiso, así que se prueba lo que
 * puede abrir una puerta: que el tipo no sea público (se sirve por la API, no como página), que las
 * metas que se escriben desde fuera tengan su comprobación, y que la taxonomía de actividades quede con
 * su enganche para guardar la meta.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tipo de contenido, taxonomía y metas.
 */
final class TurnoRegistroTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();

		require_once dirname( __DIR__, 2 ) . '/includes/horario.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-personas-asignables.php';
		require_once dirname( __DIR__, 2 ) . '/includes/cpt-turno.php';

		\Convoca\Shifts\convoca_shifts_register_cpt_centro_turno();
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
		foreach ( array( '_sh_metas', '_sh_taxonomias', '_wp_stores', '_test_caps', '_cc_cpt' ) as $clave ) {
			unset( $GLOBALS[ $clave ] );
		}

		$GLOBALS['_wp_stores'] = array();
		$GLOBALS['_test_caps'] = array();
	}

	/**
	 * El tipo se registra con sus etiquetas y sin ser público: se sirve por la API, no como página.
	 */
	public function test_el_tipo_se_registra(): void {
		$tipo = $GLOBALS['_wp_stores']['cpt']['centro_turno'] ?? null;

		$this->assertNotNull( $tipo, 'el tipo queda registrado' );
		$this->assertFalse( $tipo['public'], 'no es público: se sirve por REST/FullCalendar' );
		$this->assertTrue( $tipo['show_in_rest'], 'pero sí está en la API' );
		$this->assertFalse( $tipo['has_archive'] );
		$this->assertSame( 'dashicons-calendar-alt', $tipo['menu_icon'] );
		$this->assertSame( 'Turno', $tipo['label'] );
		$this->assertSame( 'Turnos Centro', $tipo['labels']['menu_name'] );
	}

	/**
	 * La taxonomía de actividades cuelga del turno y se puede editar en la API.
	 */
	public function test_la_taxonomia_de_actividades(): void {
		$tax = $GLOBALS['_sh_taxonomias']['convoca_shifts_actividad'];

		$this->assertSame( 'centro_turno', $tax['tipos'] );
		$this->assertTrue( $tax['args']['hierarchical'] );
		$this->assertTrue( $tax['args']['show_in_rest'] );
	}

	/**
	 * Las metas del contrato: fecha completa, hora de fin, estado, persona, apoyo y asistencia.
	 */
	public function test_las_metas_del_contrato(): void {
		$esperadas = array( '_hora_fin', '_estado', '_id_responsable', '_notas', '_necesita_apoyo', '_estado_real', '_convoca_fecha_aviso', '_convoca_ausencia_motivo', '_actividad', '_monitor' );

		foreach ( $esperadas as $clave ) {
			$this->assertArrayHasKey( $clave, $GLOBALS['_sh_metas'], 'falta la meta ' . $clave );
			$this->assertSame( 'centro_turno', $GLOBALS['_sh_metas'][ $clave ]['tipo'] );
		}
	}

	/**
	 * `_fecha_inicio` se registra como las demás, como `_hora_fin`.
	 *
	 * Antes no: la API la leía con `get_post_meta()` a mano, así que el calendario funcionaba y la asimetría
	 * pasaba desapercibida. Pero quien mirase el campo `meta` de `/wp/v2/centro_turno` no veía la fecha, que es
	 * justo el dato que necesita para ordenar (shifts#26).
	 *
	 * El contrato completo —las once metas y sus tipos— vive en `MetasDelTurnoTest`.
	 */
	public function test_la_fecha_de_inicio_se_registra(): void {
		$this->assertArrayHasKey( '_fecha_inicio', $GLOBALS['_sh_metas'], 'se registra' );
		$this->assertTrue( $GLOBALS['_sh_metas']['_fecha_inicio']['args']['show_in_rest'], 'y se ve por REST' );
	}

	/**
	 * La asistencia arranca en «pendiente» y el apoyo en «no»: sin meta, esos son los valores.
	 */
	public function test_los_valores_por_defecto(): void {
		$this->assertSame( 'pendiente', $GLOBALS['_sh_metas']['_estado_real']['args']['default'] );
		$this->assertFalse( $GLOBALS['_sh_metas']['_necesita_apoyo']['args']['default'] );
		$this->assertSame( 'abierto_disponible', $GLOBALS['_sh_metas']['_estado']['args']['default'] );
		$this->assertSame( 0, $GLOBALS['_sh_metas']['_id_responsable']['args']['default'] );
	}

	/**
	 * Las metas que apuntan a una persona **no** se escriben desde fuera sin permiso.
	 */
	public function test_la_persona_no_se_escribe_sin_permiso(): void {
		$autoriza = $GLOBALS['_sh_metas']['_id_responsable']['args']['auth_callback'];

		$this->assertIsCallable( $autoriza );

		$GLOBALS['_test_caps']['convoca_shifts_manage_turnos'] = false;
		$GLOBALS['_test_caps']['manage_options']               = false;

		$this->assertFalse( (bool) $autoriza(), 'sin permiso, no' );

		$GLOBALS['_test_caps']['convoca_shifts_manage_turnos'] = true;

		$this->assertTrue( (bool) $autoriza(), 'con permiso de edición, sí' );
	}

	/**
	 * El enganche de la meta de actividad queda puesto en los dos sentidos (crear y editar).
	 */
	public function test_la_meta_de_actividad_se_guarda_en_creacion_y_edicion(): void {
		$acciones = $GLOBALS['_wp_stores']['acciones'] ?? array();

		$this->assertArrayHasKey( 'created_convoca_shifts_actividad', $acciones );
		$this->assertArrayHasKey( 'edited_convoca_shifts_actividad', $acciones );
	}
}
