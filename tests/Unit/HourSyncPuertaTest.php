<?php
/**
 * La puerta del libro de horas, antes de acreditar.
 *
 * @package Convoca\Shifts
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Sin quien acredite horas no se acreditan: y eso tiene que pasar por la puerta del núcleo.
 *
 * Esta comprobación lee el fuente a propósito. `Hour_Sync` trabaja dentro de una transacción de `$wpdb`
 * y con `get_userdata()`, así que probar el comportamiento de punta a punta exige WordPress de verdad; lo
 * que sí se puede blindar aquí, y es lo que se rompió antes, es **el orden**: que la puerta se consulte
 * ANTES de escribir en el libro, y que Shifts no vuelva a decidir quién es voluntario leyendo roles.
 *
 * @since 2.5.33
 */
class HourSyncPuertaTest extends TestCase {

	/**
	 * Fuente del sincronizador de horas.
	 *
	 * @return string
	 */
	private function fuente(): string {
		$ruta = dirname( __DIR__, 2 ) . '/includes/Hour_Sync.php';
		$this->assertFileExists( $ruta );

		return (string) file_get_contents( $ruta );
	}

	public function test_la_puerta_se_consulta_antes_de_acreditar(): void {
		$fuente = $this->fuente();

		$puerta = strpos( $fuente, 'Hour_Ledger::puede_acreditar_horas(' );
		$credit = strpos( $fuente, 'Hour_Ledger::credit(' );

		$this->assertNotFalse( $puerta, 'Hour_Sync no consulta la puerta del libro: acreditaría en sitios sin gestión de personas.' );
		$this->assertNotFalse( $credit, 'No se encuentra la llamada a credit(): ¿ha cambiado la forma de acreditar?' );
		$this->assertLessThan( $credit, $puerta, 'La puerta se consulta DESPUÉS de acreditar: no sirve de nada.' );
	}

	public function test_revocar_no_pasa_por_la_puerta(): void {
		// Revocar es limpieza: si un turno pasa a «no asistió», las horas que hubiera hay que quitarlas
		// aunque la puerta esté cerrada. Si algún día se pone detrás de la puerta, este test lo dice.
		$fuente = $this->fuente();

		$this->assertMatchesRegularExpression(
			"/'no_asistio' !== \\\$status/",
			$fuente,
			'La condición de la puerta ya no distingue el caso de «no asistió»: revocar podría quedar bloqueado.'
		);
	}

	public function test_shifts_no_vuelve_a_decidir_quien_es_voluntario(): void {
		$fuente = $this->fuente();

		$this->assertStringNotContainsString( 'is_volunteer', $fuente, 'Shifts vuelve a decidir quién es voluntario leyendo roles: eso es de la gestión de personas.' );
		$this->assertStringNotContainsString( "'voluntario_aprobado'", $fuente, 'Shifts vuelve a nombrar el rol de voluntario.' );
		$this->assertStringNotContainsString( 'gestionar_mis_turnos', $fuente, 'Shifts vuelve a decidir por la capacidad en vez de por la puerta.' );
	}
}
