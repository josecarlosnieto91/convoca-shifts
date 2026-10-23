<?php
/**
 * La auditoría de horas del voluntariado.
 *
 * Enseña el total de horas de cada persona y **cuántos turnos tiene marcados como realizados**, con un botón
 * para recalcular. Dos cosas pueden salir mal y ninguna da error: que el recuento de turnos no cuadre con
 * las horas (una consulta mal escrita devuelve 0 y la tabla miente), y que el recálculo se dispare **sin
 * nonce** —cualquiera con el enlace recalcularía las horas de todo el mundo—.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Admin_Auditoria_Horas.php';

/**
 * Auditoría de horas: listado y recálculo.
 */
final class AdminAuditoriaHorasTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		// Las clases primero: `limpiar()` ya toca `Personas_Asignables`.
		require_once dirname( __DIR__, 2 ) . '/includes/horario.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-personas-asignables.php';

		$this->limpiar();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$this->limpiar();
		unset( $_POST['convoca_shifts_action'], $_POST['user_id'] );
	}

	/**
	 * Deja el arnés como estaba.
	 */
	private function limpiar(): void {
		foreach ( array( '_wp_stores', '_sh_db', '_sh_admin_paginas', '_sh_admin_nonce_ok', '_sh_admin_nonce_mata', '_sh_admin_nonces_comprobados', '__shifts_filtros' ) as $clave ) {
			unset( $GLOBALS[ $clave ] );
		}

		$GLOBALS['_wp_stores'] = array();
		\Convoca\Core\Logger::clear();
		\Convoca\Shifts\Personas_Asignables::limpiar_cache();
	}

	/**
	 * El enganche queda en el menú del panel.
	 */
	public function test_el_enganche(): void {
		\Convoca\Shifts\Admin_Auditoria_Horas::init();

		$this->assertContains( 'admin_menu', array_keys( $GLOBALS['_wp_stores']['acciones'] ?? array() ) );
	}

	/**
	 * Su página cuelga de turnos y pide su propia capacidad.
	 */
	public function test_el_menu(): void {
		\Convoca\Shifts\Admin_Auditoria_Horas::add_menu();

		$paginas = $GLOBALS['_sh_admin_paginas'] ?? array();
		$this->assertNotEmpty( $paginas );
		$this->assertSame( 'edit.php?post_type=centro_turno', $paginas[0]['padre'] );
		$this->assertSame( 'convoca_shifts_audit_hours', $paginas[0]['cap'], 'no basta con ver turnos' );
		$this->assertSame( 'convoca_shifts_auditoria_horas', $paginas[0]['slug'] );
	}

	/**
	 * La tabla enseña a cada persona con sus horas, sus turnos y su botón de recalcular.
	 */
	public function test_el_listado(): void {
		$this->voluntarios(
			array(
				7 => array(
					'nombre' => 'Ada Lovelace',
					'email'  => 'ada@example.test',
					'horas'  => 4.5,
				),
				8 => array(
					'nombre' => 'Grace Hopper',
					'email'  => 'grace@example.test',
					'horas'  => 0,
				),
			)
		);
		$GLOBALS['_sh_db']['get_var'] = 3;

		$html = $this->pintar();

		$this->assertStringContainsString( 'Auditoría de Horas', $html );
		$this->assertStringContainsString( '<strong>Ada Lovelace</strong>', $html );
		$this->assertStringNotContainsString(
			'ada@example.test',
			$html,
			'la lista de personas no lleva el correo: la columna Email sale vacía (issue abierto)'
		);
		$this->assertStringContainsString( '4.50h', $html, 'las horas con dos decimales' );
		$this->assertStringContainsString( '0.00h', $html, 'y el que no tiene, también' );
		$this->assertStringContainsString( '<td>3</td>', $html, 'el recuento de turnos realizados' );
		$this->assertSame( 2, substr_count( $html, 'convoca_shifts_action' ), 'un formulario por persona' );
		$this->assertStringContainsString( 'name="user_id" value="7"', $html );
		$this->assertStringContainsString( 'Recalcular', $html );
	}

	/**
	 * Sin quien aporte personas no hay a quién auditar: tabla vacía, no una lista inventada.
	 */
	public function test_sin_personas(): void {
		$html = $this->pintar();

		$this->assertStringContainsString( 'Auditoría de Horas', $html );
		$this->assertSame( 1, substr_count( $html, '<tr>' ), 'solo la cabecera de la tabla' );
	}

	/**
	 * Recalcular con nonce: suma las horas de los turnos realizados y lo guarda.
	 */
	public function test_recalcular_con_nonce(): void {
		$this->voluntarios(
			array(
				7 => array(
					'nombre' => 'Ada',
					'email'  => 'ada@example.test',
					'horas'  => 0,
				),
			)
		);
		$GLOBALS['_sh_admin_nonce_ok'] = true;
		$_POST                         = array(
			'convoca_shifts_action' => 'recalcular',
			'user_id'               => '7',
		);
		// Dos turnos realizados: 4 h y 2,5 h.
		$GLOBALS['_sh_db']['get_results'] = array(
			(object) array( 'ID' => 1 ),
			(object) array( 'ID' => 2 ),
		);
		$GLOBALS['_wp_stores']['post_meta'][1] = array(
			'_fecha_inicio' => '2026-11-05 17:00:00',
			'_hora_fin'     => '21:00',
		);
		$GLOBALS['_wp_stores']['post_meta'][2] = array(
			'_fecha_inicio' => '2026-11-06 10:00:00',
			'_hora_fin'     => '12:30',
		);

		$html = $this->pintar();

		$this->assertStringContainsString( 'Horas recalculadas correctamente', $html );
		$this->assertSame( 6.5, (float) $GLOBALS['_wp_stores']['user_meta'][7]['_convoca_horas_voluntariado_total'] );
		$this->assertSame( array( 'convoca_shifts_recalcular_7' ), $GLOBALS['_sh_admin_nonces_comprobados'] );
		$this->assertSame( 'info', \Convoca\Core\Logger::get_logs()[0]['level'] ?? '', 'es un apunte informativo, no un aviso' );
		$this->assertSame( 'Turnos', \Convoca\Core\Logger::get_logs()[0]['context'] ?? '' );
		$this->assertStringContainsString( '6.5 horas en 2 turnos', \Convoca\Core\Logger::get_logs()[0]['msg'] ?? '' );
	}

	/**
	 * Sin nonce válido no se recalcula nada.
	 */
	public function test_recalcular_sin_nonce(): void {
		$this->voluntarios(
			array(
				7 => array(
					'nombre' => 'Ada',
					'email'  => 'ada@example.test',
					'horas'  => 2,
				),
			)
		);
		$GLOBALS['_sh_admin_nonce_ok']   = false;
		$GLOBALS['_sh_admin_nonce_mata'] = true; // como WordPress: la comprobación del nonce mata
		$_POST                           = array(
			'convoca_shifts_action' => 'recalcular',
			'user_id'               => '7',
		);

		try {
			ob_start();
			\Convoca\Shifts\Admin_Auditoria_Horas::render_page();
			$this->fail( 'debería haber muerto la petición' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( array( 'convoca_shifts_recalcular_7' ), $GLOBALS['_sh_admin_nonces_comprobados'], 'se exige el nonce de esa persona' );
		} finally {
			ob_end_clean();
		}

		$this->assertSame(
			2.0,
			(float) $GLOBALS['_wp_stores']['user_meta'][7]['_convoca_horas_voluntariado_total'],
			'y las horas se quedan como estaban'
		);
	}

	/**
	 * Un turno al que le falta la hora de fin no suma horas (ni resta).
	 */
	public function test_un_turno_sin_datos_no_suma(): void {
		$this->voluntarios(
			array(
				7 => array(
					'nombre' => 'Ada',
					'email'  => 'ada@example.test',
					'horas'  => 0,
				),
			)
		);
		$GLOBALS['_sh_admin_nonce_ok']    = true;
		$_POST                            = array(
			'convoca_shifts_action' => 'recalcular',
			'user_id'               => '7',
		);
		$GLOBALS['_sh_db']['get_results'] = array( (object) array( 'ID' => 3 ) );
		$GLOBALS['_wp_stores']['post_meta'][3] = array(
			'_fecha_inicio' => '2026-11-05 17:00:00',
			'_hora_fin'     => '',
		);

		$this->pintar();

		$this->assertSame( 0.0, (float) $GLOBALS['_wp_stores']['user_meta'][7]['_convoca_horas_voluntariado_total'] );
	}

	/**
	 * Deja voluntarios con sus horas en el arnés.
	 *
	 * @param array $personas Personas: id => nombre/email/horas.
	 */
	private function voluntarios( array $personas ): void {
		$lista = array();
		foreach ( $personas as $id => $datos ) {
			$persona = (object) array(
				'ID'           => $id,
				'nombre'       => $datos['nombre'],
				'display_name' => $datos['nombre'],
				'user_email'   => $datos['email'],
				'first_name'   => $datos['nombre'],
			);

			$GLOBALS['_wp_stores']['usuarios'][ $id ]                                     = $persona;
			$GLOBALS['_wp_stores']['user_meta'][ $id ]['_convoca_horas_voluntariado_total'] = $datos['horas'];
			$lista[]                                                                       = $persona;
		}

		$GLOBALS['__shifts_filtros'][ \Convoca\Shifts\Personas_Asignables::FILTRO ] = static function () use ( $lista ) {
			return $lista;
		};
		\Convoca\Shifts\Personas_Asignables::limpiar_cache();
	}

	/**
	 * Pinta la pantalla y devuelve el HTML.
	 *
	 * @return string
	 */
	private function pintar(): string {
		ob_start();
		\Convoca\Shifts\Admin_Auditoria_Horas::render_page();

		return (string) ob_get_clean();
	}
}
