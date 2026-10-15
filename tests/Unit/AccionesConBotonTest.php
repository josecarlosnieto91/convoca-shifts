<?php
/**
 * El exportador de turnos tiene que poder lanzarse (issue #13).
 *
 * Existía, estaba enganchado y funcionaba… y no lo podía lanzar nadie: ninguna pantalla enviaba
 * `convoca_shifts_action=exportar_csv`. Una función que sólo se puede llamar por correo no existe para quien
 * usa el plugin, y la web la estaba anunciando.
 *
 * Se comprueba el contrato entre las dos mitades —quien atiende y quien dispara— porque es justo donde se
 * rompió: el nombre de la acción y el del nonce tienen que ser los mismos en los dos sitios.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

class AccionesConBotonTest extends TestCase {

	private function leer( string $ruta ): string {
		$completa = dirname( __DIR__, 2 ) . '/' . $ruta;
		$this->assertFileExists( $completa );

		return (string) file_get_contents( $completa );
	}

	public function test_el_exportador_de_turnos_tiene_su_boton(): void {
		$listado = $this->leer( 'includes/class-admin-turnos-list-page.php' );

		$this->assertStringContainsString(
			'value="exportar_csv"',
			$listado,
			'El exportador de turnos existe pero no hay ningún botón que lo lance'
		);
		$this->assertStringContainsString(
			"wp_nonce_field( 'convoca_shifts_exportar_csv_action' )",
			$listado,
			'Y el nonce tiene que ser el mismo que comprueba el manejador'
		);
	}

	public function test_el_manejador_y_el_boton_hablan_de_lo_mismo(): void {
		$manejador = $this->leer( 'includes/cpt-turno.php' );
		$listado   = $this->leer( 'includes/class-admin-turnos-list-page.php' );

		foreach ( array( 'exportar_csv', 'convoca_shifts_exportar_csv_action' ) as $contrato ) {
			$this->assertStringContainsString( $contrato, $manejador, 'El manejador usa otro nombre: ' . $contrato );
			$this->assertStringContainsString( $contrato, $listado, 'El botón usa otro nombre: ' . $contrato );
		}
	}

	public function test_el_exportador_sigue_vivo_y_en_la_lista(): void {
		// Si alguien quita el enganche, el botón se queda sin nada detrás.
		$turnos = $this->leer( 'includes/cpt-turno.php' );
		$this->assertStringContainsString( 'convoca_shifts_exportar_csv_turnos', $turnos );
		$this->assertStringContainsString( "add_action( 'admin_init', 'Convoca\\Shifts\\convoca_shifts_exportar_csv_turnos' )", $turnos );
	}
}
