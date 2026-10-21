<?php
/**
 * El contrato de personas asignables: por defecto vacío.
 *
 * @package Convoca\Shifts
 */

namespace Convoca\Shifts\Tests;

use Convoca\Shifts\Personas_Asignables;
use PHPUnit\Framework\TestCase;

/**
 * Shifts no administra miembros: pregunta por una lista y trabaja con lo que le den.
 *
 * Lo que se prueba aquí es la regla de producto (JC, 21/10/2026): **sin quien aporte personas no hay
 * personas asignables**. El valor por defecto del filtro es una lista vacía, y eso es lo que hace que en
 * un sitio sin gestión de personas no haya selector, no se pueda asignar y no haya horas que acreditar.
 */
class PersonasAsignablesTest extends TestCase {

	protected function setUp(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/class-personas-asignables.php';
		Personas_Asignables::limpiar_cache();
		$GLOBALS['__shifts_filtros'] = array();
	}

	protected function tearDown(): void {
		$GLOBALS['__shifts_filtros'] = array();
		Personas_Asignables::limpiar_cache();
	}

	/**
	 * Aporta una lista al filtro del contrato.
	 *
	 * @param callable $devolver Qué devuelve el filtro.
	 */
	private function aportar( callable $devolver ): void {
		$GLOBALS['__shifts_filtros'][ Personas_Asignables::FILTRO ] = $devolver;
	}

	public function test_sin_quien_aporte_no_hay_personas(): void {
		$this->assertSame( array(), Personas_Asignables::lista( 'voluntario' ) );
		$this->assertSame( array(), Personas_Asignables::lista( 'monitor' ) );
		$this->assertFalse( Personas_Asignables::hay( 'voluntario' ) );
		$this->assertFalse( Personas_Asignables::permitida( 7 ) );
	}

	public function test_quien_aporta_personas_las_recibe_shifts(): void {
		$this->aportar(
			static function ( $personas, $contexto ) {
				return 'voluntario' === $contexto
					? array(
						array( 'id' => 7, 'nombre' => 'Ana' ),
						array( 'id' => 9, 'nombre' => 'Luis' ),
					)
					: array( array( 'id' => 3, 'nombre' => 'Marta' ) );
			}
		);

		$voluntarios = Personas_Asignables::lista( 'voluntario' );
		$this->assertCount( 2, $voluntarios );
		$this->assertSame( 7, $voluntarios[0]->ID );
		$this->assertSame( 'Ana', $voluntarios[0]->display_name );
		$this->assertSame( 1, count( Personas_Asignables::lista( 'monitor' ) ) );
	}

	public function test_los_contextos_no_se_mezclan(): void {
		$this->aportar(
			static function ( $personas, $contexto ) {
				return 'monitor' === $contexto ? array( array( 'id' => 3, 'nombre' => 'Marta' ) ) : array();
			}
		);

		$this->assertFalse( Personas_Asignables::hay( 'voluntario' ) );
		$this->assertTrue( Personas_Asignables::hay( 'monitor' ) );
	}

	public function test_permitida_es_la_lista_y_nada_mas(): void {
		$this->aportar(
			static function () {
				return array( array( 'id' => 7, 'nombre' => 'Ana' ) );
			}
		);

		$this->assertTrue( Personas_Asignables::permitida( 7 ) );
		$this->assertFalse( Personas_Asignables::permitida( 8 ), 'un id que no está en la lista no vale' );
		$this->assertFalse( Personas_Asignables::permitida( 0 ) );
	}

	public function test_admite_ids_sueltos_y_objetos_sin_romper_la_pantalla(): void {
		$this->aportar(
			static function () {
				return array( 7, (object) array( 'ID' => 9, 'display_name' => 'Luis' ) );
			}
		);

		$lista = Personas_Asignables::lista( 'voluntario' );
		$this->assertCount( 2, $lista );
		$this->assertSame( '#7', $lista[0]->display_name, 'sin nombre se pinta el id, no un hueco' );
		$this->assertSame( 'Luis', $lista[1]->display_name );
		$this->assertSame( '', $lista[1]->user_login, 'user_login vacío: la plantilla no pinta el @' );
	}

	public function test_no_se_duplica_una_persona_aportada_dos_veces(): void {
		$this->aportar(
			static function () {
				return array( array( 'id' => 7, 'nombre' => 'Ana' ), array( 'id' => 7, 'nombre' => 'Ana (duplicada)' ) );
			}
		);

		$this->assertCount( 1, Personas_Asignables::lista( 'voluntario' ) );
	}

	public function test_lo_que_devuelva_el_filtro_no_rompe_shifts(): void {
		$this->aportar(
			static function () {
				return 'esto no es una lista';
			}
		);

		$this->assertSame( array(), Personas_Asignables::lista( 'voluntario' ) );
	}

	public function test_la_nota_dice_por_que_no_hay_nadie(): void {
		$this->assertStringContainsString( 'No hay personas asignables', Personas_Asignables::nota_sin_personas() );
	}
}
