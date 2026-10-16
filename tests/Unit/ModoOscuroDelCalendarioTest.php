<?php
/**
 * El calendario en modo oscuro (convoca-shifts).
 *
 * El tema pone la clase `.dark-mode` en <html> y el calendario se pintaba con colores claros fijos:
 * en modo oscuro quedaban trozos en blanco y la leyenda con tinta clara encima (~1,05:1, ilegible).
 * Este contrato impide que el bloque de modo oscuro desaparezca sin que nadie se entere, que es
 * exactamente lo que pasó hasta que se midió en la demo.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

final class ModoOscuroDelCalendarioTest extends TestCase {

	private function css(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/estilo.css' );
	}

	/** El bloque tiene que estar, y estar marcado para poder localizarlo. */
	public function test_el_calendario_trae_bloque_de_modo_oscuro(): void {
		$css = $this->css();
		self::assertStringContainsString( 'BLOQUE: Modo oscuro del calendario', $css, 'Falta el bloque marcado de modo oscuro.' );
		self::assertStringContainsString( '.dark-mode', $css, 'No hay ninguna regla para el modo oscuro.' );
	}

	/** Y cada pieza que en claro es clara necesita su versión oscura. */
	public function test_las_piezas_claras_tienen_su_version_oscura(): void {
		$css = $this->css();
		$piezas = array(
			'convoca-shifts-leyenda'  => 'la leyenda (fondo #f9f9f9 con tinta clara)',
			'fc-day-sat'              => 'las cabeceras de sábado',
			'fc-day-sun'              => 'las cabeceras de domingo',
			'fc-day-past'             => 'los días pasados',
			'fc-daygrid-day'          => 'las casillas de día (los futuros quedaban en blanco)',
			'fc-day-today'            => 'el día de hoy',
			'fc-daygrid-day-number'   => 'la insignia del número del día',
			'convoca-shifts-weekend'  => 'las casillas de fin de semana',
		);
		$sin = array();
		foreach ( $piezas as $selector => $que ) {
			$patron = '/\.dark-mode[^{]*' . preg_quote( $selector, '/' ) . '[^{]*\{[^}]*background/';
			if ( ! preg_match( $patron, $css ) ) {
				$sin[] = $selector . ' (' . $que . ')';
			}
		}
		self::assertSame( array(), $sin, "Piezas del calendario sin versión oscura:\n  " . implode( "\n  ", $sin ) );
	}

	/**
	 * Control negativo: con el bloque quitado, la comprobación de arriba TIENE que fallar.
	 *
	 * Una prueba que no puede fallar no vale: si esto pasara sin el bloque, la anterior estaría
	 * midiendo otra cosa.
	 */
	public function test_sin_el_bloque_la_comprobacion_falla(): void {
		$sin_bloque = (string) preg_replace( '/\/\* ═+.*?════+ \*\/.*$/s', '', $this->css() );
		self::assertNotSame( $this->css(), $sin_bloque, 'El recorte del bloque no hizo nada: la prueba no prueba.' );
		self::assertSame(
			0,
			(int) preg_match( '/\.dark-mode[^{]*fc-daygrid-day[^{]*\{[^}]*background/', $sin_bloque ),
			'Sin el bloque, la comprobación tiene que fallar: si pasa, la prueba no está midiendo el bloque.'
		);
	}
}
