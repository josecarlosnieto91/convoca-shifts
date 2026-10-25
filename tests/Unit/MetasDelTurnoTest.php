<?php
/**
 * El contrato de metas del turno.
 *
 * `centro_turno` registra diez metas con `register_post_meta()`. Durante mucho tiempo fueron nueve: `_fecha_inicio`
 * se quedó fuera (shifts#26), y es justo la que ordena el listado del panel y el calendario, la que se usa para
 * detectar solapamientos y la que impide apuntarse a un turno pasado.
 *
 * El fallo era **latente**: el calendario funcionaba porque `includes/rest-api.php` lee la meta a mano con
 * `get_post_meta()`. Pero cualquier consumidor que mirase el campo `meta` de `/wp/v2/centro_turno` no veía la fecha,
 * y la mitad del contrato estaba declarada y la otra no.
 *
 * Esta prueba existe para que no vuelva a pasar: la lista es explícita y se compara entera.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

class MetasDelTurnoTest extends TestCase {

	/**
	 * Las metas del turno, con el tipo que declaran.
	 *
	 * @var array<string, string>
	 */
	private const CONTRATO = array(
		'_fecha_inicio'              => 'string',
		'_hora_fin'                  => 'string',
		'_estado'                    => 'string',
		'_id_responsable'            => 'integer',
		'_notas'                     => 'string',
		'_necesita_apoyo'            => 'boolean',
		'_estado_real'               => 'string',
		'_convoca_fecha_aviso'       => 'string',
		'_convoca_ausencia_motivo'   => 'string',
		'_actividad'                 => 'string',
		'_monitor'                   => 'string',
	);

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['_sh_metas'] = array();

		\Convoca\Shifts\convoca_shifts_register_cpt_centro_turno();
	}

	/**
	 * Las metas del contrato están todas registradas.
	 */
	public function test_el_contrato_esta_completo(): void {
		$registradas = array_keys( $GLOBALS['_sh_metas'] );

		foreach ( array_keys( self::CONTRATO ) as $meta ) {
			$this->assertContains( $meta, $registradas, sprintf( '`%s` está registrada', $meta ) );
		}
	}

	/**
	 * `_fecha_inicio` en particular: registrada, del turno y expuesta en REST.
	 *
	 * Es la que faltaba. Sin `show_in_rest`, quien lea el campo `meta` del endpoint no ve la fecha —el dato que
	 * necesita para ordenar—.
	 */
	public function test_fecha_inicio_esta_registrada_y_expuesta(): void {
		$this->assertArrayHasKey( '_fecha_inicio', $GLOBALS['_sh_metas'], 'existe' );

		$registro = $GLOBALS['_sh_metas']['_fecha_inicio'];

		$this->assertSame( 'centro_turno', $registro['tipo'] );
		$this->assertTrue( $registro['args']['show_in_rest'], 'se ve por REST' );
		$this->assertTrue( $registro['args']['single'], 'es un valor único, no una lista' );
		$this->assertSame( 'string', $registro['args']['type'], 'Y-m-d H:i:s' );
	}

	/**
	 * Y el tipo declarado es el que dice el contrato para cada una.
	 */
	public function test_los_tipos_son_los_del_contrato(): void {
		foreach ( self::CONTRATO as $meta => $tipo ) {
			$this->assertSame(
				$tipo,
				$GLOBALS['_sh_metas'][ $meta ]['args']['type'] ?? null,
				sprintf( '`%s` se declara como %s', $meta, $tipo )
			);
		}
	}
}
