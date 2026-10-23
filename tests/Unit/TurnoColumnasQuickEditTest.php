<?php
/**
 * Las columnas del listado de turnos, la edición rápida y los campos de la actividad.
 *
 * Aquí está la puerta por la que se marca la asistencia de un voluntario desde el listado: el enlace
 * «Marcar OK» / «No asistió» y el guardado de la edición rápida. Si esa puerta no comprueba el nonce o el
 * permiso, cualquiera con el enlace marca faltas de asistencia ajenas, así que se prueba cada guarda por su
 * efecto observable: sin permiso no se escribe **nada**.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/cpt-turno.php';

/**
 * Columnas, edición rápida y meta de la actividad.
 */
final class TurnoColumnasQuickEditTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();
		require_once dirname( __DIR__, 2 ) . '/includes/class-personas-asignables.php';
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$this->limpiar();
		unset( $_POST['action'], $_POST['convoca_shifts_id_responsable'], $_POST['convoca_shifts_estado_real'], $_POST['convoca_shifts_actividad_meta_nonce_field'], $_POST['convoca_shifts_url_info'] );
	}

	/**
	 * Deja el arnés como estaba.
	 */
	private function limpiar(): void {
		foreach ( array( '_wp_stores', '_test_caps', '_sh_db', '_sh_registro', '_sh_term_meta', '_sh_term_meta_updates' ) as $clave ) {
			unset( $GLOBALS[ $clave ] );
		}

		$GLOBALS['_wp_stores'] = array();
		$GLOBALS['_test_caps'] = array();
	}

	/**
	 * La columna de responsable va delante de la fecha, que es donde se mira.
	 */
	public function test_las_columnas_nuevas_van_donde_toca(): void {
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
	 * Columna de responsable: nombre de pila, guion si la persona ya no existe y «sin asignar» si no hay.
	 */
	public function test_la_columna_de_responsable(): void {
		$GLOBALS['_wp_stores']['usuarios'][7] = (object) array(
			'ID'           => 7,
			'display_name' => 'Ada Lovelace',
			'first_name'   => 'Ada',
		);
		$GLOBALS['_wp_stores']['usuarios'][8] = (object) array(
			'ID'           => 8,
			'display_name' => 'Grace Hopper',
			'first_name'   => '',
		);

		$GLOBALS['_wp_stores']['post_meta'][1] = array( '_id_responsable' => 7 );
		$GLOBALS['_wp_stores']['usuarios'][99] = false; // persona borrada
		$GLOBALS['_wp_stores']['post_meta'][2]  = array( '_id_responsable' => 99 );
		$GLOBALS['_wp_stores']['post_meta'][3] = array( '_id_responsable' => 0 );
		$GLOBALS['_wp_stores']['post_meta'][4] = array( '_id_responsable' => 8 );

		$this->assertStringContainsString( '<strong>👤 Ada</strong>', $this->columna( 'responsable', 1 ), 'el nombre de pila, no el de pantalla' );
		$this->assertSame( '—', trim( $this->columna( 'responsable', 2 ) ), 'persona borrada' );
		$this->assertStringContainsString( 'Sin asignar', $this->columna( 'responsable', 3 ) );
		$this->assertStringContainsString( '👤 Grace Hopper', $this->columna( 'responsable', 4 ), 'sin nombre de pila, el de pantalla' );
	}

	/**
	 * Columna de asistencia: los cinco estados con su distintivo, y uno desconocido tal cual.
	 */
	public function test_la_columna_de_asistencia(): void {
		$GLOBALS['_wp_stores']['post_meta'][1] = array( '_estado_real' => 'realizado' );
		$GLOBALS['_wp_stores']['post_meta'][2] = array( '_estado_real' => 'no_asistio' );
		$GLOBALS['_wp_stores']['post_meta'][3] = array( '_estado_real' => 'justificada' );
		$GLOBALS['_wp_stores']['post_meta'][4] = array( '_estado_real' => 'cancelada_aviso' );
		$GLOBALS['_wp_stores']['post_meta'][5] = array( '_estado_real' => 'inventado' );
		$GLOBALS['_wp_stores']['post_meta'][6] = array();

		$this->assertStringContainsString( 'Realizado', $this->columna( 'estado_real', 1 ) );
		$this->assertStringContainsString( 'No asistió', $this->columna( 'estado_real', 2 ) );
		$this->assertStringContainsString( 'Ausencia justificada', $this->columna( 'estado_real', 3 ) );
		$this->assertStringContainsString( 'Cancelada con aviso', $this->columna( 'estado_real', 4 ) );
		$this->assertStringContainsString( '<div style="margin-bottom:8px;">inventado</div>', $this->columna( 'estado_real', 5 ), 'lo raro se enseña, no se esconde' );
		$this->assertStringContainsString( 'badge-pending', $this->columna( 'estado_real', 6 ), 'por defecto, pendiente' );
	}

	/**
	 * Los accesos rápidos solo salen si el turno tiene a alguien asignado.
	 */
	public function test_los_accesos_rapidos(): void {
		$GLOBALS['_wp_stores']['post_meta'][1] = array(
			'_estado_real'    => 'pendiente',
			'_id_responsable' => 7,
		);
		$GLOBALS['_wp_stores']['post_meta'][2] = array(
			'_estado_real'    => 'pendiente',
			'_id_responsable' => 0,
		);

		$con = $this->columna( 'estado_real', 1 );
		$this->assertStringContainsString( 'Marcar OK', $con );
		$this->assertStringContainsString( 'No asistió', $con );
		$this->assertStringContainsString( 'mark_attendance', $con, 'el enlace lleva la acción' );
		$this->assertStringContainsString(
			'_wpnonce=' . wp_create_nonce( 'convoca_shifts_attendance_1' ),
			$con,
			'el enlace lleva el nonce del turno: sin él la acción no se puede autorizar'
		);
		$this->assertStringContainsString( 'convoca_shifts_estado_real', $con, 'y los datos para la edición rápida' );

		$this->assertStringNotContainsString( 'Marcar OK', $this->columna( 'estado_real', 2 ), 'sin responsable no hay nada que marcar' );
	}

	/**
	 * Sin permiso, la edición rápida no escribe nada.
	 */
	public function test_la_edicion_rapida_sin_permiso_no_escribe(): void {
		$GLOBALS['_wp_stores']['post_meta'][5] = array(
			'_id_responsable' => 0,
			'_estado_real'    => 'pendiente',
		);
		$GLOBALS['_wp_stores']['usuarios'][9] = (object) array(
			'ID'           => 9,
			'display_name' => 'Ada',
			'first_name'   => 'Ada',
		);
		$this->aportar_persona( 9 );
		$_POST                                 = array(
			'action'                        => 'inline-save',
			'convoca_shifts_id_responsable' => '9',
		);
		$GLOBALS['_test_caps']['edit_post'] = false;

		\Convoca\Shifts\convoca_shifts_save_turno_quick_edit( 5, (object) array( 'ID' => 5 ) );

		$this->assertSame( 0, $GLOBALS['_wp_stores']['post_meta'][5]['_id_responsable'], 'sin permiso no se toca' );
	}

	/**
	 * Fuera de la edición rápida (un guardado normal) tampoco.
	 */
	public function test_solo_se_activa_en_la_edicion_rapida(): void {
		$GLOBALS['_wp_stores']['post_meta'][5] = array(
			'_id_responsable' => 0,
			'_estado_real'    => 'pendiente',
		);
		$GLOBALS['_wp_stores']['usuarios'][9]         = (object) array(
			'ID'           => 9,
			'display_name' => 'Ada',
			'first_name'   => 'Ada',
		);
		$GLOBALS['_test_caps']['edit_post'] = true;
		$this->aportar_persona( 9 );

		$_POST = array( 'convoca_shifts_id_responsable' => '9' );
		\Convoca\Shifts\convoca_shifts_save_turno_quick_edit( 5, (object) array( 'ID' => 5 ) );
		$this->assertSame( 0, $GLOBALS['_wp_stores']['post_meta'][5]['_id_responsable'], 'sin action de edición rápida' );

		$_POST = array(
			'action'                        => 'editpost',
			'convoca_shifts_id_responsable' => '9',
		);
		\Convoca\Shifts\convoca_shifts_save_turno_quick_edit( 5, (object) array( 'ID' => 5 ) );
		$this->assertSame( 0, $GLOBALS['_wp_stores']['post_meta'][5]['_id_responsable'], 'otra action' );
	}

	/**
	 * Asignar a alguien desde la edición rápida deja el turno disponible y la asistencia pendiente.
	 */
	public function test_edicion_rapida_asigna(): void {
		$GLOBALS['_wp_stores']['post_meta'][5] = array(
			'_id_responsable' => 0,
			'_estado'         => 'abierto_ocupado',
			'_estado_real'    => 'realizado',
		);
		$GLOBALS['_wp_stores']['usuarios'][9] = (object) array(
			'ID'           => 9,
			'display_name' => 'Ada',
			'first_name'   => 'Ada',
		);
		$this->aportar_persona( 9 );
		$GLOBALS['_test_caps']['edit_post'] = true;
		$_POST                               = array(
			'action'                        => 'inline-save',
			'convoca_shifts_id_responsable' => '9',
		);

		\Convoca\Shifts\convoca_shifts_save_turno_quick_edit( 5, (object) array( 'ID' => 5 ) );

		$this->assertSame( 9, (int) $GLOBALS['_wp_stores']['post_meta'][5]['_id_responsable'] );
		// Un turno marcado como ocupado **sigue ocupado**: no se pisa su estado al asignar.
		$this->assertSame( 'abierto_ocupado', $GLOBALS['_wp_stores']['post_meta'][5]['_estado'] );
		$this->assertSame( 'pendiente', $GLOBALS['_wp_stores']['post_meta'][5]['_estado_real'] );
		$this->assertSame( 'turno_asignado', $GLOBALS['_sh_registro'][0]['action'] );
	}

	/**
	 * Asignar a un turno que estaba «disponible» lo deja disponible (y no toca el estado real ya puesto).
	 */
	public function test_edicion_rapida_sincroniza_el_estado(): void {
		$GLOBALS['_wp_stores']['post_meta'][5] = array(
			'_id_responsable' => 0,
			'_estado'         => 'pendiente_de_revisar',
			'_estado_real'    => 'realizado',
		);
		$GLOBALS['_wp_stores']['usuarios'][9] = (object) array(
			'ID'           => 9,
			'display_name' => 'Ada',
			'first_name'   => 'Ada',
		);
		$this->aportar_persona( 9 );
		$GLOBALS['_test_caps']['edit_post'] = true;
		$_POST                               = array(
			'action'                        => 'inline-save',
			'convoca_shifts_id_responsable' => '9',
		);

		\Convoca\Shifts\convoca_shifts_save_turno_quick_edit( 5, (object) array( 'ID' => 5 ) );

		$this->assertSame( 'abierto_disponible', $GLOBALS['_wp_stores']['post_meta'][5]['_estado'] );
	}

	/**
	 * Un turno cerrado no se reabre al asignarle a alguien.
	 */
	public function test_edicion_rapida_no_reabre_un_turno_cerrado(): void {
		$GLOBALS['_wp_stores']['post_meta'][5] = array(
			'_id_responsable' => 0,
			'_estado'         => 'cerrado',
			'_estado_real'    => 'pendiente',
		);
		$GLOBALS['_wp_stores']['usuarios'][9] = (object) array(
			'ID'           => 9,
			'display_name' => 'Ada',
			'first_name'   => 'Ada',
		);
		$this->aportar_persona( 9 );
		$GLOBALS['_test_caps']['edit_post'] = true;
		$_POST                               = array(
			'action'                        => 'inline-save',
			'convoca_shifts_id_responsable' => '9',
		);

		\Convoca\Shifts\convoca_shifts_save_turno_quick_edit( 5, (object) array( 'ID' => 5 ) );

		$this->assertSame( 'cerrado', $GLOBALS['_wp_stores']['post_meta'][5]['_estado'], 'un turno cerrado sigue cerrado' );
	}

	/**
	 * Si la persona es la misma que ya estaba, no se escribe ni se registra nada.
	 */
	public function test_edicion_rapida_sin_cambio_de_persona(): void {
		$GLOBALS['_wp_stores']['post_meta'][5] = array(
			'_id_responsable' => 9,
			'_estado'         => 'abierto_disponible',
			'_estado_real'    => 'pendiente',
		);
		$GLOBALS['_wp_stores']['usuarios'][9] = (object) array(
			'ID'           => 9,
			'display_name' => 'Ada',
			'first_name'   => 'Ada',
		);
		$this->aportar_persona( 9 );
		$GLOBALS['_test_caps']['edit_post'] = true;
		$_POST                               = array(
			'action'                        => 'inline-save',
			'convoca_shifts_id_responsable' => '9',
		);

		\Convoca\Shifts\convoca_shifts_save_turno_quick_edit( 5, (object) array( 'ID' => 5 ) );

		$this->assertSame( array(), $GLOBALS['_sh_registro'] ?? array(), 'la misma persona no genera suceso' );
	}

	/**
	 * Un id que ningún módulo aporta no se guarda: el POST a mano no asigna a cualquiera.
	 */
	public function test_edicion_rapida_no_asigna_a_quien_no_se_aporta(): void {
		$GLOBALS['_wp_stores']['post_meta'][5] = array(
			'_id_responsable' => 0,
			'_estado'         => 'abierto_disponible',
			'_estado_real'    => 'pendiente',
		);
		$GLOBALS['_test_caps']['edit_post'] = true;
		$_POST                               = array(
			'action'                        => 'inline-save',
			'convoca_shifts_id_responsable' => '77',
		);

		\Convoca\Shifts\convoca_shifts_save_turno_quick_edit( 5, (object) array( 'ID' => 5 ) );

		$this->assertSame( 0, (int) $GLOBALS['_wp_stores']['post_meta'][5]['_id_responsable'], 'no está en la lista' );
		$this->assertSame( array(), $GLOBALS['_sh_registro'] ?? array(), 'ni se registra' );
	}

	/**
	 * Quitar a la persona deja el turno sin responsable y lo registra como desasignado.
	 */
	public function test_edicion_rapida_quita_al_responsable(): void {
		$GLOBALS['_wp_stores']['post_meta'][5] = array(
			'_id_responsable' => 9,
			'_estado'         => 'abierto_disponible',
			'_estado_real'    => 'realizado',
		);
		$GLOBALS['_test_caps']['edit_post'] = true;
		$_POST                               = array(
			'action'                        => 'inline-save',
			'convoca_shifts_id_responsable' => '0',
		);

		\Convoca\Shifts\convoca_shifts_save_turno_quick_edit( 5, (object) array( 'ID' => 5 ) );

		$this->assertSame( 0, (int) $GLOBALS['_wp_stores']['post_meta'][5]['_id_responsable'] );
		$this->assertSame( 'turno_desasignado', $GLOBALS['_sh_registro'][0]['action'] );
	}

	/**
	 * Marcar la asistencia desde la edición rápida: se guarda y se registra.
	 */
	public function test_edicion_rapida_marca_asistencia(): void {
		$GLOBALS['_wp_stores']['post_meta'][5] = array(
			'_id_responsable' => 9,
			'_estado_real'    => 'pendiente',
		);
		$GLOBALS['_test_caps']['edit_post'] = true;
		$_POST                               = array(
			'action'                      => 'inline-save',
			'convoca_shifts_estado_real'  => 'realizado',
		);

		\Convoca\Shifts\convoca_shifts_save_turno_quick_edit( 5, (object) array( 'ID' => 5 ) );

		$this->assertSame( 'realizado', $GLOBALS['_wp_stores']['post_meta'][5]['_estado_real'] );
		$this->assertContains( 'asistencia_ok', array_column( $GLOBALS['_sh_registro'], 'action' ) );
	}

	/**
	 * Y «no asistió» se registra como tal.
	 */
	public function test_edicion_rapida_marca_ausencia(): void {
		$GLOBALS['_wp_stores']['post_meta'][5] = array(
			'_id_responsable' => 9,
			'_estado_real'    => 'pendiente',
		);
		$GLOBALS['_test_caps']['edit_post'] = true;
		$_POST                               = array(
			'action'                     => 'inline-save',
			'convoca_shifts_estado_real' => 'no_asistio',
		);

		\Convoca\Shifts\convoca_shifts_save_turno_quick_edit( 5, (object) array( 'ID' => 5 ) );

		$this->assertContains( 'asistencia_no', array_column( $GLOBALS['_sh_registro'], 'action' ) );
	}

	/**
	 * Si la asistencia no cambia, no se registra nada (ni se avisa a nadie).
	 */
	public function test_sin_cambio_no_se_registra(): void {
		$GLOBALS['_wp_stores']['post_meta'][5] = array(
			'_id_responsable' => 9,
			'_estado_real'    => 'realizado',
		);
		$GLOBALS['_test_caps']['edit_post'] = true;
		$_POST                               = array(
			'action'                     => 'inline-save',
			'convoca_shifts_estado_real' => 'realizado',
		);

		\Convoca\Shifts\convoca_shifts_save_turno_quick_edit( 5, (object) array( 'ID' => 5 ) );

		$this->assertSame( array(), $GLOBALS['_sh_registro'] ?? array(), 'mismo estado, ningún suceso' );
	}

	/**
	 * Los campos de la actividad: el alta lleva el nonce, la edición además el enlace guardado.
	 */
	public function test_los_campos_de_la_actividad(): void {
		$GLOBALS['_sh_term_meta'][12]['convoca_shifts_url_info'] = 'https://ejemplo.example.test/info';

		ob_start();
		\Convoca\Shifts\convoca_shifts_actividad_add_meta_fields();
		$alta = ob_get_clean();

		ob_start();
		\Convoca\Shifts\convoca_shifts_actividad_edit_meta_fields( (object) array( 'term_id' => 12 ) );
		$edicion = ob_get_clean();

		$this->assertStringContainsString( 'convoca_shifts_actividad_meta_nonce_field', $alta );
		$this->assertStringContainsString( 'convoca_shifts_url_info', $alta );
		$this->assertStringContainsString( 'https://ejemplo.example.test/info', $edicion, 'la edición trae lo guardado' );
	}

	/**
	 * Guardar la actividad sin nonce no escribe nada.
	 */
	public function test_guardar_la_actividad_sin_nonce(): void {
		$_POST = array( 'convoca_shifts_url_info' => 'https://ejemplo.example.test/x' );

		\Convoca\Shifts\convoca_shifts_save_actividad_meta( 12 );

		$this->assertArrayNotHasKey( 'convoca_shifts_url_info', $GLOBALS['_sh_term_meta'][12] ?? array() );
	}

	/**
	 * Con nonce pero sin permiso, tampoco.
	 */
	public function test_guardar_la_actividad_sin_permiso(): void {
		$_POST = array(
			'convoca_shifts_actividad_meta_nonce_field' => 'nonce-bueno',
			'convoca_shifts_url_info'                   => 'https://ejemplo.example.test/x',
		);
		$GLOBALS['_test_caps']['manage_categories'] = false;

		\Convoca\Shifts\convoca_shifts_save_actividad_meta( 12 );

		$this->assertArrayNotHasKey( 'convoca_shifts_url_info', $GLOBALS['_sh_term_meta'][12] ?? array() );
	}

	/**
	 * Con nonce y permiso se guarda la URL ya limpia.
	 */
	public function test_guardar_la_actividad(): void {
		$_POST = array(
			'convoca_shifts_actividad_meta_nonce_field' => 'nonce-bueno',
			'convoca_shifts_url_info'                   => 'javascript:alert(1)',
		);
		$GLOBALS['_test_caps']['manage_categories'] = true;

		\Convoca\Shifts\convoca_shifts_save_actividad_meta( 12 );

		$this->assertSame( 12, $GLOBALS['_sh_term_meta_updates'][0]['term'] );
		$this->assertSame( 'convoca_shifts_url_info', $GLOBALS['_sh_term_meta_updates'][0]['clave'] );
		$this->assertSame( '', $GLOBALS['_sh_term_meta_updates'][0]['valor'], 'el esquema peligroso no se guarda' );
	}

	/**
	 * Aporta una persona por el filtro del contrato (es como lo hace Convoca Members).
	 *
	 * @param int $id Persona.
	 */
	private function aportar_persona( int $id ): void {
		$GLOBALS['__shifts_filtros'][ \Convoca\Shifts\Personas_Asignables::FILTRO ] = static function () use ( $id ) {
			return array( (object) array( 'ID' => $id ) );
		};
		\Convoca\Shifts\Personas_Asignables::limpiar_cache();
	}

	/**
	 * Pinta una columna y devuelve lo pintado.
	 *
	 * @param string $columna Columna.
	 * @param int    $id      Turno.
	 * @return string
	 */
	private function columna( string $columna, int $id ): string {
		ob_start();
		\Convoca\Shifts\convoca_shifts_custom_centro_turno_column( $columna, $id );

		return (string) ob_get_clean();
	}
}
