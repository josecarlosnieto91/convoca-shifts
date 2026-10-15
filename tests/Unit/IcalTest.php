<?php
/**
 * El feed de turnos para el calendario (issue #13).
 *
 * La web prometía «Exportación a iCal, Google Calendar» y no existía nada: cero apariciones de `ics` en el
 * plugin. Esta prueba cubre las dos partes que se pueden romper en silencio: el **escapado/plegado** del
 * formato (un texto con una coma, o una línea larga partida por la mitad de un acento, y el calendario
 * enseña basura o no lee el turno) y el **contrato** del feed (que exista la ruta, el tipo de contenido y
 * los campos obligatorios de un evento).
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

class IcalTest extends TestCase {

	private function codigo(): string {
		$ruta = dirname( __DIR__, 2 ) . '/includes/ical.php';
		$this->assertFileExists( $ruta );

		return file_get_contents( $ruta );
	}

	/**
	 * Llama a un método estático de la clase del feed.
	 *
	 * @param string $metodo     Método.
	 * @param array  $argumentos Argumentos.
	 * @return mixed
	 */
	private function llamar( string $metodo, array $argumentos ) {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/wp/' );
		}
		require_once dirname( __DIR__, 2 ) . '/includes/ical.php';

		return call_user_func_array( array( \Convoca\Shifts\Ical::class, $metodo ), $argumentos );
	}

	public function test_escapa_lo_que_rompe_el_formato(): void {
		$this->assertSame( 'Taller de huerto\, sábado', $this->llamar( 'escapar', array( 'Taller de huerto, sábado' ) ) );
		$this->assertSame( 'a\;b', $this->llamar( 'escapar', array( 'a;b' ) ) );
		$this->assertSame( 'C:' . chr( 92 ) . chr( 92 ) . 'ruta', $this->llamar( 'escapar', array( 'C:' . chr( 92 ) . 'ruta' ) ) );
		$this->assertSame( 'dos' . chr( 92 ) . 'nlíneas', $this->llamar( 'escapar', array( "dos\nlíneas" ) ) );
	}

	public function test_pliega_las_lineas_largas_sin_partir_acentos(): void {
		$larga   = str_repeat( 'Turno de tarde con acentos: áéíóúñ ', 6 );
		$plegada = $this->llamar( 'plegar', array( $larga ) );

		foreach ( explode( "\r\n ", $plegada ) as $linea ) {
			$this->assertLessThanOrEqual( 75, strlen( $linea ), 'El RFC 5545 no admite líneas de más de 75 octetos' );
		}

		// Y al desplegar tiene que salir el texto original: ni un byte perdido ni un carácter roto.
		$this->assertSame( $larga, str_replace( "\r\n ", '', $plegada ) );
		$this->assertStringNotContainsString( "\xEF\xBF\xBD", $plegada, 'No puede quedar ningún carácter roto' );
	}

	public function test_pliega_cada_linea_por_su_cuenta(): void {
		// Un evento llega unido con CRLF. Si se plegara el bloque entero, el corte cae donde toque: medido en
		// la demo, un DTSTAMP de 24 caracteres salió partido en dos y el resumen se rompió por la mitad.
		$evento  = str_repeat( 'x', 60 ) . "\r\nSUMMARY:Turno corto\r\n" . str_repeat( 'y', 60 );
		$plegado = $this->llamar( 'plegar', array( $evento ) );

		$logicas = array_values( array_filter( explode( "\r\n", $plegado ), static fn( $l ) => '' !== $l && ' ' !== substr( $l, 0, 1 ) ) );

		$this->assertContains( 'SUMMARY:Turno corto', $logicas, 'Una línea corta no puede partirse al plegar el bloque' );
	}

	public function test_una_linea_corta_no_se_toca(): void {
		$this->assertSame( 'SUMMARY:Turno', $this->llamar( 'plegar', array( 'SUMMARY:Turno' ) ) );
	}

	public function test_el_feed_tiene_la_ruta_el_tipo_y_los_campos_obligatorios(): void {
		$codigo = $this->codigo();
		$this->assertStringContainsString( "'convoca_shifts_ics'", $codigo, 'Falta el parámetro del feed' );
		$this->assertStringContainsString( 'text/calendar', $codigo, 'Sin el tipo de contenido, el calendario no lo reconoce' );
		$this->assertStringContainsString( 'BEGIN:VCALENDAR', $codigo );
		$this->assertStringContainsString( 'BEGIN:VEVENT', $codigo );
		$this->assertStringContainsString( 'DTSTART:', $codigo );
		$this->assertStringContainsString( 'DTEND:', $codigo );
		$this->assertStringContainsString( 'UID:', $codigo );
	}

	public function test_el_testigo_no_se_puede_adivinar(): void {
		$codigo = $this->codigo();
		$this->assertStringContainsString( 'hash_hmac', $codigo, 'El testigo tiene que ir firmado con la sal del sitio' );
		$this->assertStringContainsString( 'hash_equals', $codigo, 'Y compararse en tiempo constante' );
	}

	public function test_el_feed_esta_enganchado_y_se_ofrece_al_voluntario(): void {
		$principal = file_get_contents( dirname( __DIR__, 2 ) . '/convoca-shifts.php' );
		$this->assertStringContainsString( 'includes/ical.php', $principal );
		$this->assertStringContainsString( 'Ical::init()', $principal );

		// Y tiene que estar FUERA de `is_admin()`: el feed lo pide el calendario del voluntario desde fuera,
		// y dentro de ese bloque no se registra nunca en la portada (pasó: la dirección devolvía la página).
		$pos_init  = strpos( $principal, 'Ical::init()' );
		$pos_admin = strpos( $principal, 'if ( is_admin() ) {' );
		$this->assertNotFalse( $pos_admin );
		$this->assertLessThan(
			$pos_admin,
			$pos_init,
			'El feed de calendario no puede registrarse dentro del bloque de administración'
		);

		$shortcodes = file_get_contents( dirname( __DIR__, 2 ) . '/includes/shortcodes.php' );
		$this->assertStringContainsString( 'Ical::url_de(', $shortcodes, 'El voluntario tiene que poder ver su dirección' );
		$this->assertStringContainsString( 'Suscribirse desde una URL', $shortcodes, 'Y saber qué hacer con ella en Google Calendar' );
	}
}
