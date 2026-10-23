<?php
/**
 * La API REST de turnos: qué endpoints hay y quién puede llamarlos.
 *
 * `includes/rest-api.php` estaba al 0,4 % de 541 statements y es la puerta por la que se apunta y se
 * desapunta la gente a los turnos. Aquí se prueba lo que decide si algo pasa o no: las seis rutas y
 * sus permisos. Un endpoint de escritura con `permission_callback` abierto es un turno que cualquiera
 * puede ocupar desde fuera; uno de lectura cerrado es un calendario que nadie ve.
 *
 * @package Convoca\Shifts
 */

namespace Convoca\Shifts\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/rest-api.php';

/**
 * Rutas y permisos de la API de turnos.
 */
final class RestApiTest extends TestCase {

	/**
	 * Cada prueba parte sin rutas, sin sesión y sin capacidades negadas.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['_sh_routes'] = array();
		$GLOBALS['_sh_logged'] = false;
		$GLOBALS['_test_caps'] = array();

		\Convoca\Shifts\convoca_shifts_register_rest_routes();
	}

	/**
	 * Y no deja restos.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['_sh_routes'] = array();
		$GLOBALS['_sh_logged'] = false;
		$GLOBALS['_test_caps'] = array();
	}

	/**
	 * Busca una ruta registrada por su ruta.
	 *
	 * @param string $ruta Ruta buscada.
	 * @return array|null
	 */
	private function ruta( string $ruta ): ?array {
		foreach ( $GLOBALS['_sh_routes'] as $registrada ) {
			if ( $registrada['route'] === $ruta ) {
				return $registrada;
			}
		}

		return null;
	}

	/**
	 * Están las seis rutas esperadas.
	 *
	 * @return void
	 */
	public function test_se_registran_las_seis_rutas(): void {
		$rutas = array_column( $GLOBALS['_sh_routes'], 'route' );

		$this->assertCount( 6, $rutas );
		$this->assertContains( '/turnos', $rutas );
		$this->assertContains( '/turnos/(?P<id>\d+)/apuntarse', $rutas );
		$this->assertContains( '/turnos/(?P<id>\d+)/desapuntarse', $rutas );
		$this->assertContains( '/turnos/apuntarse-proximo', $rutas );
		$this->assertContains( '/turnos/proximo-libre', $rutas );
		$this->assertContains( '/turnos/crear', $rutas );
	}

	/**
	 * Todas cuelgan del mismo espacio de nombres.
	 *
	 * @return void
	 */
	public function test_todas_bajo_el_mismo_espacio(): void {
		foreach ( $GLOBALS['_sh_routes'] as $registrada ) {
			$this->assertSame( 'convoca-shifts/v1', $registrada['namespace'] );
		}
	}

	/**
	 * Cada ruta apunta a su función, no a la de al lado.
	 *
	 * @return void
	 */
	public function test_cada_ruta_apunta_a_su_callback(): void {
		$esperado = array(
			'/turnos'                        => 'convoca_shifts_rest_get_turnos',
			'/turnos/(?P<id>\d+)/apuntarse'  => 'convoca_shifts_rest_apuntarse_turno',
			'/turnos/(?P<id>\d+)/desapuntarse' => 'convoca_shifts_rest_desapuntarse_turno',
			'/turnos/apuntarse-proximo'      => 'convoca_shifts_rest_apuntarse_proximo',
			'/turnos/proximo-libre'          => 'convoca_shifts_rest_get_proximo_libre',
			'/turnos/crear'                  => 'convoca_shifts_rest_crear_turno',
		);

		foreach ( $esperado as $ruta => $funcion ) {
			$registrada = $this->ruta( $ruta );
			$this->assertNotNull( $registrada, "falta la ruta {$ruta}" );
			$this->assertStringEndsWith( $funcion, $registrada['callback'], "{$ruta} llama a otra función" );
		}
	}

	/**
	 * El listado y el próximo libre son públicos: es el calendario que ve cualquiera.
	 *
	 * @return void
	 */
	public function test_el_listado_y_el_proximo_libre_son_publicos(): void {
		foreach ( array( '/turnos', '/turnos/proximo-libre' ) as $ruta ) {
			$registrada = $this->ruta( $ruta );
			$permiso    = $registrada['permission_callback'];

			$this->assertTrue( $permiso(), "{$ruta} tiene que leerse sin sesión" );
		}
	}

	/**
	 * Apuntarse sin sesión no se puede: ni siquiera llega al callback.
	 *
	 * @return void
	 */
	public function test_sin_sesion_no_se_puede_apuntar(): void {
		$permiso = $this->ruta( '/turnos/(?P<id>\d+)/apuntarse' )['permission_callback'];

		$resultado = $permiso();

		$this->assertInstanceOf( \WP_Error::class, $resultado );
		$this->assertSame( 'rest_cannot_access', $resultado->get_error_code(), 'sin sesión, error de permiso y no un turno apuntado' );
	}

	/**
	 * Desapuntarse tampoco.
	 *
	 * @return void
	 */
	public function test_sin_sesion_no_se_puede_desapuntar(): void {
		$permiso = $this->ruta( '/turnos/(?P<id>\d+)/desapuntarse' )['permission_callback'];

		$this->assertInstanceOf( \WP_Error::class, $permiso() );
	}

	/**
	 * Apuntarse al próximo libre tampoco.
	 *
	 * @return void
	 */
	public function test_sin_sesion_no_se_puede_apuntar_al_proximo(): void {
		$permiso = $this->ruta( '/turnos/apuntarse-proximo' )['permission_callback'];

		$this->assertInstanceOf( \WP_Error::class, $permiso() );
	}

	/**
	 * Con sesión y la capacidad propia, sí.
	 *
	 * @return void
	 */
	public function test_con_sesion_y_capacidad_propia_se_puede_apuntar(): void {
		$GLOBALS['_sh_logged'] = true;

		$permiso = $this->ruta( '/turnos/(?P<id>\d+)/apuntarse' )['permission_callback'];

		$this->assertTrue( $permiso() );
	}

	/**
	 * Con sesión pero sin la capacidad, no.
	 *
	 * @return void
	 */
	public function test_con_sesion_pero_sin_capacidad_no_se_puede_apuntar(): void {
		$GLOBALS['_sh_logged']                       = true;
		$GLOBALS['_test_caps']['gestionar_mis_turnos'] = false;
		$GLOBALS['_test_caps']['manage_options']       = false;

		$permiso = $this->ruta( '/turnos/(?P<id>\d+)/apuntarse' )['permission_callback'];

		$this->assertInstanceOf( \WP_Error::class, $permiso() );
	}

	/**
	 * El administrador del sitio entra por `manage_options`.
	 *
	 * @return void
	 */
	public function test_el_administrador_entra_por_manage_options(): void {
		$GLOBALS['_sh_logged']                       = true;
		$GLOBALS['_test_caps']['gestionar_mis_turnos'] = false;

		$permiso = $this->ruta( '/turnos/(?P<id>\d+)/apuntarse' )['permission_callback'];

		$this->assertTrue( $permiso() );
	}

	/**
	 * Crear turnos es solo para quien gestiona: y sin sesión no.
	 *
	 * @return void
	 */
	public function test_crear_turno_exige_sesion(): void {
		$permiso = $this->ruta( '/turnos/crear' )['permission_callback'];

		$this->assertFalse( $permiso(), 'crear es la única que niega con un booleano, no con un error' );
	}

	/**
	 * Y con sesión, sí.
	 *
	 * @return void
	 */
	public function test_crear_turno_con_sesion_se_permite(): void {
		$GLOBALS['_sh_logged'] = true;

		$permiso = $this->ruta( '/turnos/crear' )['permission_callback'];

		$this->assertTrue( $permiso() );
	}

	/**
	 * Los métodos son los que dicen ser: lo de escribir no puede ir por GET.
	 *
	 * @return void
	 */
	public function test_los_metodos_son_los_declarados(): void {
		$lectura = array( '/turnos', '/turnos/proximo-libre' );

		foreach ( $GLOBALS['_sh_routes'] as $registrada ) {
			if ( in_array( $registrada['route'], $lectura, true ) ) {
				$this->assertSame( 'GET', $registrada['methods'], "{$registrada['route']} tiene que ser GET" );
			} else {
				$this->assertSame( 'POST', $registrada['methods'], "{$registrada['route']} tiene que ser POST" );
			}
		}
	}
}
