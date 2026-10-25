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

	public function test_id_a_guardar_no_deja_asignar_a_quien_no_esta_en_la_lista(): void {
		$this->aportar(
			static function () {
				return array( array( 'id' => 7, 'nombre' => 'Ana' ) );
			}
		);

		$this->assertSame( 7, Personas_Asignables::id_a_guardar( 7, 'voluntario' ), 'quien está en la lista, entra' );
		$this->assertSame( 0, Personas_Asignables::id_a_guardar( 8, 'voluntario' ), 'un id colado por POST se rechaza' );
	}

	public function test_id_a_guardar_conserva_lo_que_habia_si_el_nuevo_no_vale(): void {
		$this->aportar(
			static function () {
				return array( array( 'id' => 7, 'nombre' => 'Ana' ) );
			}
		);

		$this->assertSame( 7, Personas_Asignables::id_a_guardar( 8, 'voluntario', 7 ), 'el rechazo no borra la asignación anterior' );
	}

	public function test_liberar_el_turno_se_puede_siempre(): void {
		// Sin nadie que aporte personas —o sea, en un sitio sin gestión de personas— quitar a alguien del
		// turno tiene que seguir siendo posible: bloquearlo dejaría turnos con un monitor que no toca.
		$this->assertSame( 0, Personas_Asignables::id_a_guardar( 0, 'voluntario', 7 ) );
		$this->assertSame( 0, Personas_Asignables::id_a_guardar( 0, 'monitor', 3 ) );
	}

	public function test_la_nota_dice_por_que_no_hay_nadie(): void {
		$this->assertStringContainsString( 'No hay personas asignables', Personas_Asignables::nota_sin_personas() );
	}

	/**
	 * El correo viaja con la persona cuando quien la aporta lo trae.
	 *
	 * La auditoría de horas pinta una columna *Email* por fila: si la lista no lo transporta, la columna sale
	 * vacía siempre (shifts#27).
	 */
	public function test_el_correo_viene_con_la_persona(): void {
		$this->aportar(
			static function () {
				return array(
					array( 'id' => 7, 'nombre' => 'Ana', 'email' => 'ana@example.test' ),
					array( 'id' => 9, 'nombre' => 'Luis', 'user_email' => 'luis@example.test' ),
				);
			}
		);

		$lista = Personas_Asignables::lista( 'voluntario' );

		$this->assertSame( 'ana@example.test', $lista[0]->user_email, 'acepta la clave `email`' );
		$this->assertSame( 'luis@example.test', $lista[1]->user_email, 'y la clave `user_email`' );
	}

	/**
	 * Si la persona no trae correo, se pregunta a WordPress por el id.
	 */
	public function test_sin_correo_en_la_persona_se_busca_en_el_usuario(): void {
		$GLOBALS['_wp_stores']['usuarios'][7] = (object) array(
			'ID'           => 7,
			'display_name' => 'Ana',
			'user_email'   => 'ana@example.test',
		);

		$this->aportar(
			static function () {
				return array( array( 'id' => 7, 'nombre' => 'Ana' ) );
			}
		);

		$lista = Personas_Asignables::lista( 'voluntario' );

		$this->assertSame( 'ana@example.test', $lista[0]->user_email );
	}

	/**
	 * Y si no hay ni una cosa ni la otra, la clave existe vacía: nada de avisos al pintar.
	 */
	public function test_sin_correo_en_ningun_sitio_la_clave_queda_vacia(): void {
		// El doble de `get_userdata()` inventa un usuario para cualquier id positivo; para simular que el id no
		// tiene cuenta se siembra explícitamente como `false`.
		$GLOBALS['_wp_stores']['usuarios'][4242] = false;

		$this->aportar(
			static function () {
				return array( array( 'id' => 4242, 'nombre' => 'Sin cuenta' ) );
			}
		);

		$lista = Personas_Asignables::lista( 'voluntario' );

		$this->assertTrue( property_exists( $lista[0], 'user_email' ), 'la clave existe siempre' );
		$this->assertSame( '', $lista[0]->user_email );
	}
}
