<?php
/**
 * Importación de turnos desde plantilla (issue #13).
 *
 * La web prometía «Importa plantillas desde Excel para alta masiva de turnos» y no existía: el alta masiva
 * era rellenar una semana en el panel, que no es un fichero. Lo que se rompe aquí sin avisar es la lectura:
 * el separador que usa Excel en español (punto y coma), el BOM que mete al guardar, y la fecha —`05/11/2026`
 * es el 5 de noviembre, no el 11 de mayo, y confundirlo da turnos en el mes equivocado.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

class ImportadorTest extends TestCase {

	/**
	 * Llama a un método estático del importador.
	 *
	 * @param string $metodo     Método.
	 * @param array  $argumentos Argumentos.
	 * @return mixed
	 */
	private function llamar( string $metodo, array $argumentos ) {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/wp/' );
		}
		require_once dirname( __DIR__, 2 ) . '/includes/importador.php';

		return \Convoca\Shifts\Importador::{$metodo}( ...$argumentos );
	}

	public function test_lee_una_plantilla_sencilla(): void {
		$csv  = "fecha,hora_inicio,hora_fin,actividad,monitor,responsable,estado,apoyo\n"
			. "05/11/2026,17:00,21:00,Huerto,jose.nieto,,abierto_disponible,0\n"
			. "2026-11-06,10:00,13:00,,,jose.nieto,cerrado,1\n";
		$leido = $this->llamar( 'parsear', array( $csv ) );

		$this->assertSame( array(), $leido['errores'] );
		$this->assertCount( 2, $leido['filas'] );

		// `05/11/2026` es el 5 de noviembre: en España el día va primero.
		$this->assertSame( '2026-11-05', $leido['filas'][0]['fecha'] );
		$this->assertSame( '17:00', $leido['filas'][0]['hora_inicio'] );
		$this->assertSame( '21:00', $leido['filas'][0]['hora_fin'] );
		$this->assertSame( '2026-11-06', $leido['filas'][1]['fecha'] );
	}

	public function test_lee_lo_que_guarda_excel_en_espanol(): void {
		// Excel en español guarda con punto y coma, y con BOM al principio.
		$csv  = "\xEF\xBB\xBFfecha;hora_inicio;hora_fin;actividad;monitor;responsable;estado;apoyo\n"
			. "05/11/2026;9:00;13:00;;;jose.nieto;;0\n";
		$leido = $this->llamar( 'parsear', array( $csv ) );

		$this->assertSame( array(), $leido['errores'], 'El BOM no puede comerse la primera columna' );
		$this->assertCount( 1, $leido['filas'] );
		$this->assertSame( '09:00', $leido['filas'][0]['hora_inicio'], 'La hora se normaliza a dos dígitos' );
	}

	public function test_una_fila_mala_no_se_lleva_por_delante_las_buenas(): void {
		$csv  = "fecha,hora_inicio,hora_fin\n"
			. "05/11/2026,17:00,21:00\n"
			. "no es una fecha,17:00,21:00\n"
			. "2026-11-07,25:00,21:00\n";
		$leido = $this->llamar( 'parsear', array( $csv ) );

		$this->assertCount( 1, $leido['filas'], 'La fila buena se importa' );
		$this->assertCount( 2, $leido['errores'], 'Y las dos malas se explican' );
		$this->assertStringContainsString( 'Línea 3', $leido['errores'][0], 'Diciendo en qué línea' );
	}

	public function test_sin_las_columnas_necesarias_lo_dice(): void {
		$leido = $this->llamar( 'parsear', array( "fecha,hora_inicio\n05/11/2026,17:00\n" ) );

		$this->assertSame( array(), $leido['filas'] );
		$this->assertStringContainsString( 'hora_fin', $leido['errores'][0] );
	}

	public function test_un_fichero_vacio_no_revienta(): void {
		$leido = $this->llamar( 'parsear', array( "\n\n" ) );

		$this->assertSame( array(), $leido['filas'] );
		$this->assertNotEmpty( $leido['errores'] );
	}

	public function test_la_plantilla_que_damos_se_importa_ella_sola(): void {
		// Si la plantilla que se descarga no se puede importar, el primer usuario que la use tropieza.
		$leido = $this->llamar( 'parsear', array( (string) $this->llamar( 'plantilla', array() ) ) );

		$this->assertSame( array(), $leido['errores'] );
		$this->assertCount( 2, $leido['filas'] );
	}

	public function test_acepta_las_fechas_que_la_gente_escribe(): void {
		$this->assertSame( '2026-11-05', $this->llamar( 'fecha', array( '05/11/2026' ) ) );
		$this->assertSame( '2026-11-05', $this->llamar( 'fecha', array( '5-11-2026' ) ) );
		$this->assertSame( '2026-11-05', $this->llamar( 'fecha', array( '2026-11-05' ) ) );
		$this->assertSame( '', $this->llamar( 'fecha', array( 'mañana' ) ) );
	}

	public function test_el_si_de_una_hoja_de_calculo(): void {
		// En una casilla se escribe 1, x, sí, SI… Todas valen como sí; «0» y vacío, no.
		foreach ( array( '1', 'x', 'sí', 'SI', 'SÍ', 'Sí', 'true', ' y ', 'Verdadero' ) as $si ) {
			$this->assertTrue( $this->llamar( 'si', array( $si ) ), '«' . $si . '» debería contar como sí'  );
		}
		foreach ( array( '0', '', 'no', 'quizá' ) as $no ) {
			$this->assertFalse( $this->llamar( 'si', array( $no ) ), '«' . $no . '» no debería contar como sí'  );
		}
	}

	public function test_el_importador_esta_enganchado_y_con_su_pagina(): void {
		$principal = file_get_contents( dirname( __DIR__, 2 ) . '/convoca-shifts.php' );
		$this->assertStringContainsString( 'includes/importador.php', $principal );
		$this->assertStringContainsString( 'Importador::init()', $principal );

		$codigo = file_get_contents( dirname( __DIR__, 2 ) . '/includes/importador.php' );
		$this->assertStringContainsString( 'convoca_shifts_insert_turno(', $codigo, 'La creación de turnos se delega en la del generador: horario y título en un solo sitio' );
		$this->assertStringContainsString( 'check_admin_referer', $codigo, 'La subida lleva nonce' );
		$this->assertStringContainsString( 'current_user_can', $codigo );
		$this->assertStringContainsString( 'add_submenu_page', $codigo, 'Y su página en el menú de turnos' );
		$this->assertStringContainsString(
			"add_action( 'admin_init', array( self::class, 'quizas_descargar_plantilla' ) )",
			$codigo,
			'La descarga de la plantilla va en `admin_init`: dentro del render, `header()` llega tarde y el fichero sale envuelto en HTML'
		);
	}
}
