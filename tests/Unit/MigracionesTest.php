<?php
/**
 * Las migraciones de la base de datos de Shifts.
 *
 * `includes/Convoca_Shifts_Upgrade_Manager.php` (56 statements) migra datos **históricos**: si se equivoca, no
 * hay vuelta atrás. Se prueba, sobre todo, la de 2.5.3, que enlaza registros de horas con su turno: qué enlaza,
 * qué **no** toca y que no borra nada.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use Convoca\Shifts\Convoca_Shifts_Upgrade_Manager;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class MigracionesTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_stores']        = array( 'options' => array(), 'post_meta' => array(), 'posts' => array() );
		$GLOBALS['_sh_db']            = array( 'por_sql' => array(), 'get_results' => array() );
		$GLOBALS['_ddl']              = array();
		\Convoca\Core\Logger::clear();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$acciones = isset( $GLOBALS['_wp_stores']['acciones'] ) ? $GLOBALS['_wp_stores']['acciones'] : array();

		$GLOBALS['_wp_stores']             = array( 'post_meta' => array() );
		$GLOBALS['_wp_stores']['acciones'] = $acciones;

		unset( $GLOBALS['_sh_db'], $GLOBALS['_ddl'] );
		\Convoca\Core\Logger::clear();
	}

	/**
	 * Llama a un método protegido del gestor.
	 *
	 * @param string $metodo Método.
	 * @param mixed  ...$args Argumentos.
	 * @return mixed
	 */
	private function llamar( string $metodo, ...$args ) {
		$gestor   = new Convoca_Shifts_Upgrade_Manager();
		$reflejar = new ReflectionMethod( Convoca_Shifts_Upgrade_Manager::class, $metodo );

		return $reflejar->invoke( $gestor, ...$args );
	}

	/**
	 * La versión de la BASE, no la del plugin: subir la versión del plugin no puede mover las migraciones.
	 */
	public function test_la_version_es_la_de_la_base(): void {
		$version = (string) $this->llamar( 'get_db_version' );

		$this->assertSame( CONVOCA_SHIFTS_DB_VERSION, $version );
		$this->assertNotSame( CONVOCA_SHIFTS_VERSION, $version, 'la versión del plugin es el cache-buster de los assets, no la del esquema' );
	}

	/**
	 * El nombre de la opción y el prefijo de los transitorios son los del plugin.
	 */
	public function test_los_nombres_de_la_migracion(): void {
		$this->assertSame( 'convoca_shifts_version', $this->llamar( 'get_option_name' ) );
		$this->assertSame( 'convoca_shifts_upgrade', $this->llamar( 'get_transient_prefix' ) );
	}

	/**
	 * Están las cuatro migraciones, enganchadas a su versión.
	 */
	public function test_las_migraciones_registradas(): void {
		$callbacks = (array) $this->llamar( 'get_upgrade_callbacks' );

		$this->assertSame( array( '1.6.3', '2.3.0', '2.5.2', '2.5.3' ), array_keys( $callbacks ) );
		foreach ( $callbacks as $version => $callback ) {
			// Los métodos son PROTEGIDOS: `is_callable()` desde fuera dice que no. Se comprueba que existen en
			// la clase, que es lo que de verdad puede romperse al renombrar.
			$metodo = is_array( $callback ) ? (string) $callback[1] : (string) $callback;
			$this->assertTrue( method_exists( Convoca_Shifts_Upgrade_Manager::class, $metodo ), "la migración {$version} apunta a un método que no existe ({$metodo})" );
		}
	}

	/**
	 * 2.5.3 enlaza SOLO cuando puede demostrar que el turno es de esa persona.
	 */
	public function test_la_migracion_25_3_enlaza_lo_demostrable(): void {
		$GLOBALS['_sh_db']['por_sql']['registro_hora'] = array(
			(object) array( 'ID' => 501, 'post_title' => 'Horas Turno CS #77', 'usuario_id' => '9' ),
			(object) array( 'ID' => 502, 'post_title' => 'Horas Turno CS #78', 'usuario_id' => '9' ),
			(object) array( 'ID' => 503, 'post_title' => 'Otra cosa', 'usuario_id' => '9' ),
			(object) array( 'ID' => 504, 'post_title' => 'Horas Turno CS #79', 'usuario_id' => '0' ),
			(object) array( 'ID' => 505, 'post_title' => 'Horas Turno CS #80', 'usuario_id' => '9' ),
		);

		// 77: turno de esta persona → se enlaza.
		$GLOBALS['_wp_stores']['posts'][77] = (object) array( 'ID' => 77, 'post_type' => 'centro_turno' );
		$GLOBALS['_wp_stores']['post_meta'][77] = array( '_id_responsable' => 9 );
		// 78: turno de OTRA persona → no se toca.
		$GLOBALS['_wp_stores']['posts'][78] = (object) array( 'ID' => 78, 'post_type' => 'centro_turno' );
		$GLOBALS['_wp_stores']['post_meta'][78] = array( '_id_responsable' => 5 );
		// 80: existe pero no es un turno → no se toca.

		$this->llamar( 'upgrade_to_2_5_3_hour_ledger_links' );

		$this->assertSame( \Convoca\Core\Hour_Ledger::ORIGEN_TURNO, get_post_meta( 501, '_convoca_origen', true ), 'el que se puede demostrar queda enlazado' );
		$this->assertSame( 77, get_post_meta( 501, '_convoca_origen_id', true ) );
		$this->assertSame( '', (string) get_post_meta( 502, '_convoca_origen', true ), 'el de otra persona NO se toca' );
		$this->assertSame( '', (string) get_post_meta( 503, '_convoca_origen', true ), 'sin título de turno no hay nada que enlazar' );
		$this->assertSame( '', (string) get_post_meta( 504, '_convoca_origen', true ), 'sin persona no hay nada que enlazar' );

		// Y nada se borra: no hay consultas de borrado en toda la migración.
		$consultas = strtolower( implode( "\n", (array) ( $GLOBALS['_sh_db']['consultas'] ?? array() ) ) );
		$this->assertStringNotContainsString( 'delete', $consultas, 'una migración histórica no borra' );
	}

	/**
	 * La migración deja en el registro cuántos enlazó y cuántos omitió (es auditable).
	 */
	public function test_la_migracion_25_3_deja_rastro(): void {
		$GLOBALS['_sh_db']['por_sql']['registro_hora'] = array(
			(object) array( 'ID' => 501, 'post_title' => 'Horas Turno CS #77', 'usuario_id' => '9' ),
			(object) array( 'ID' => 503, 'post_title' => 'Otra cosa', 'usuario_id' => '9' ),
		);
		$GLOBALS['_wp_stores']['posts'][77]      = (object) array( 'ID' => 77, 'post_type' => 'centro_turno' );
		$GLOBALS['_wp_stores']['post_meta'][77]  = array( '_id_responsable' => 9 );

		$this->llamar( 'upgrade_to_2_5_3_hour_ledger_links' );

		$logs = \Convoca\Core\Logger::get_logs();
		$this->assertNotEmpty( $logs, 'una migración de datos históricos tiene que quedar registrada' );

		$texto = (string) $logs[0]['msg'];
		$this->assertStringContainsString( 'enlazados 1', $texto );
		$this->assertStringContainsString( 'omitidos 1', $texto );
		$this->assertStringContainsString( 'de 2 registros', $texto, 'dice sobre cuántos trabajó' );
	}

	/**
	 * 2.5.2 normaliza la clave del vínculo con la persona.
	 */
	public function test_la_migracion_25_2_normaliza_la_clave(): void {
		$this->llamar( 'upgrade_to_2_5_2_member_meta_key' );

		$consultas = implode( "\n", (array) ( $GLOBALS['_sh_db']['consultas'] ?? array() ) );
		$this->assertStringContainsString( "SET meta_key = '_convoca_member_id'", $consultas );
		$this->assertStringContainsString( "WHERE meta_key = '_convoca_miembro_id'", $consultas, 'solo la clave antigua' );

		$logs = \Convoca\Core\Logger::get_logs();
		$this->assertStringContainsString( '2.5.2', (string) $logs[0]['msg'] );
	}

	/**
	 * 1.6.3 se asegura de que la tabla de registros existe.
	 */
	public function test_la_migracion_1_6_3_crea_la_tabla(): void {
		// La base no la tiene: es el caso de una instalación vieja que se actualiza.
		$GLOBALS['_sh_db']['tablas_existentes'] = array();

		$this->llamar( 'upgrade_to_1_6_3' );

		$ddl = implode( "\n", (array) ( $GLOBALS['_ddl'] ?? array() ) );
		$this->assertStringContainsString( 'convoca_shifts_activity_log', $ddl );
	}

	/**
	 * 2.3.0 deja su rastro.
	 */
	public function test_la_migracion_2_3_0_deja_rastro(): void {
		$this->llamar( 'upgrade_to_2_3_0' );

		$logs = \Convoca\Core\Logger::get_logs();
		$this->assertStringContainsString( '2.3.0', (string) $logs[0]['msg'] );
	}
}
