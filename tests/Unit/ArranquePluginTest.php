<?php
/**
 * El arranque de Convoca Shifts: sus funciones y sus ganchos.
 *
 * `convoca-shifts.php` estaba al 0 % (144 statements). Desde shifts#28 el arranque REAL se carga en el
 * bootstrap (antes que sus dobles, que van con `function_exists` y se saltan solos), así que aquí se prueban
 * las piezas que expone: fechas en español, registro de actividad y el aviso de rol.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

class ArranquePluginTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_stores']              = array( 'options' => array(), 'post_meta' => array(), 'transients' => array(), 'acciones' => array() );
		$GLOBALS['_sh_registro']            = array();
		$GLOBALS['db_inserts']              = array();
		$GLOBALS['convoca_test_is_admin']   = false;
		$GLOBALS['_test_caps']              = array( 'manage_options' => true );
		$GLOBALS['_ddl']                    = array();
		$_GET                               = array();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$acciones = isset( $GLOBALS['_wp_stores']['acciones'] ) ? $GLOBALS['_wp_stores']['acciones'] : array();

		$GLOBALS['_wp_stores']             = array( 'post_meta' => array() );
		$GLOBALS['_wp_stores']['acciones'] = $acciones;

		unset( $GLOBALS['_sh_registro'], $GLOBALS['db_inserts'], $GLOBALS['convoca_test_is_admin'], $GLOBALS['_test_caps'], $GLOBALS['_ddl'] );
		$_GET = array();
	}

	/**
	 * El arranque deja enganchado lo que el plugin necesita.
	 */
	public function test_los_enganches_del_arranque(): void {
		$acciones = (array) ( $GLOBALS['_sh_hooks_registrados'] ?? array() );

		foreach ( array( 'init', 'admin_enqueue_scripts', 'plugins_loaded', 'admin_notices', 'admin_init' ) as $hook ) {
			$this->assertNotEmpty( $acciones[ $hook ] ?? array(), "falta el enganche {$hook}" );
		}

		$this->assertNotEmpty( $GLOBALS['convoca_test_desactivacion'] ?? null, 'sin desactivación queda el cron colgado' );
	}

	/**
	 * Las fechas se escriben en español sin depender del idioma del sitio.
	 */
	public function test_las_fechas_en_espanol(): void {
		$sello = gmmktime( 12, 0, 0, 11, 14, 2023 ); // 14/11/2023 12:00 UTC.

		$this->assertSame(
			'martes 14 de noviembre a las ' . \wp_date( 'H:i', $sello ),
			\Convoca\Shifts\convoca_shifts_fecha_legible( $sello ),
			'la fecha larga lleva día de la semana, día, mes y hora'
		);
		$this->assertSame( 'martes 14 de noviembre', \Convoca\Shifts\convoca_shifts_fecha_corta( $sello ) );
	}

	/**
	 * El registro de actividad crea la tabla si no existe y escribe la acción con su detalle.
	 */
	public function test_el_registro_de_actividad(): void {
		\Convoca\Shifts\convoca_shifts_log_activity( 7, 33, 'turno_asignado', array( 'detalle' => 'x' ) );

		$registro = (array) ( $GLOBALS['_sh_registro'][0] ?? array() );
		$this->assertSame( 7, $registro['user_id'] );
		$this->assertSame( 33, $registro['turno_id'] );
		$this->assertSame( 'turno_asignado', $registro['action'] );
		$this->assertSame( 'x', $registro['data']['detalle'] ?? '', 'el detalle se guarda como JSON en la base' );

		// La tabla se comprueba una vez y se cachea: si no existía, se crea.
		$this->assertNotEmpty( get_transient( 'convoca_shifts_log_table_exists' ), 'la existencia se cachea para no preguntar en cada registro' );
	}

	/**
	 * Sin permiso de gestión no se avisa de nada.
	 */
	public function test_el_aviso_de_rol_sin_permiso_no_sale(): void {
		$GLOBALS['_test_caps']['manage_options'] = false;

		ob_start();
		\Convoca\Shifts\convoca_shifts_check_required_role();
		$salida = (string) ob_get_clean();

		$this->assertSame( '', $salida );
	}

	/**
	 * Si el rol existe, tampoco hay aviso.
	 */
	public function test_sin_falta_de_rol_no_hay_aviso(): void {
		$GLOBALS['_test_caps']['manage_options'] = true;
		$GLOBALS['_wp_stores']['roles']['voluntario_aprobado'] = array( 'name' => 'Voluntario aprobado' );

		ob_start();
		\Convoca\Shifts\convoca_shifts_check_required_role();
		$salida = (string) ob_get_clean();

		$this->assertSame( '', $salida );
	}

	/**
	 * Si falta el rol, se avisa y se ofrece descartar el aviso.
	 */
	public function test_si_falta_el_rol_se_avisa(): void {
		$GLOBALS['_test_caps']['manage_options'] = true;

		ob_start();
		\Convoca\Shifts\convoca_shifts_check_required_role();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( 'Voluntario Aprobado', $salida, 'dice qué rol falta' );
		$this->assertStringContainsString( 'convoca_shifts_dismiss_role_notice', $salida, 'y ofrece descartarlo' );
	}

	/**
	 * La actividad del plugin se puede crear: la tabla se pide con su DDL.
	 */
	public function test_la_tabla_de_actividad(): void {
		\Convoca\Shifts\convoca_shifts_create_log_table();

		$ddl = implode( "\n", (array) ( $GLOBALS['_ddl'] ?? array() ) );
		$this->assertStringContainsString( 'convoca_shifts_activity_log', $ddl, 'la tabla del registro de actividad' );
	}
}
