<?php
/**
 * El feed de calendario de cada voluntario (issue #13).
 *
 * La suscripción es una **credencial de lectura**: quien tenga la URL ve los turnos de esa persona. Por eso
 * lo primero que se prueba es el testigo —que no se pueda fabricar para otro usuario ni valga recortado— y
 * después el calendario: que salgan sus turnos (como responsable **o** como monitor), que un turno sin
 * fecha no rompa el fichero, que el fin cruce la medianoche cuando toca, y que el plegado a 75 octetos no
 * parta un acento por la mitad.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/ical.php';

/**
 * Feed iCal: testigo y calendario.
 */
final class IcalFeedTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$this->limpiar();
	}

	/**
	 * Deja el arnés como estaba.
	 */
	private function limpiar(): void {
		foreach ( array( '_wp_stores', '_sh_sitio', '_sh_enlaces', '_sh_estados' ) as $clave ) {
			unset( $GLOBALS[ $clave ] );
		}

		$GLOBALS['_wp_stores'] = array();
	}

	/**
	 * El testigo lleva el usuario a la vista y un HMAC que no se puede fabricar.
	 */
	public function test_el_testigo(): void {
		$siete = \Convoca\Shifts\Ical::token( 7 );

		$this->assertMatchesRegularExpression( '/^[0-9a-f]{32}-7$/', $siete, 'HMAC recortado y el usuario detrás' );
		$this->assertSame( $siete, \Convoca\Shifts\Ical::token( 7 ), 'estable: una suscripción que caduca no sirve' );
		$this->assertNotSame( $siete, \Convoca\Shifts\Ical::token( 8 ), 'cada persona tiene el suyo' );
	}

	/**
	 * La URL de suscripción, y vacía para quien no es nadie.
	 */
	public function test_la_url_de_suscripcion(): void {
		$this->assertSame( '', \Convoca\Shifts\Ical::url_de( 0 ), 'sin usuario no hay URL' );
		$this->assertSame( '', \Convoca\Shifts\Ical::url_de( -3 ) );

		$url = \Convoca\Shifts\Ical::url_de( 7 );

		$this->assertStringContainsString( 'convoca_shifts_ics=', $url );
		$this->assertStringContainsString( \Convoca\Shifts\Ical::token( 7 ), $url );
	}

	/**
	 * Un testigo vale solo para su usuario: el de otro, recortado o inventado, no abre nada.
	 */
	public function test_el_testigo_no_se_puede_falsificar(): void {
		$valido   = \Convoca\Shifts\Ical::token( 7 );
		$el_otro  = \Convoca\Shifts\Ical::token( 8 );
		$partido  = explode( '-', $valido )[0] . '-8';

		$metodo = new \ReflectionMethod( \Convoca\Shifts\Ical::class, 'usuario_del_token' );

		$this->assertSame( 7, $metodo->invoke( null, $valido ), 'el suyo sí' );
		$this->assertSame( 0, $metodo->invoke( null, $partido ), 'el HMAC de 7 con el id de 8 no vale' );
		$this->assertSame( 0, $metodo->invoke( null, $el_otro . 'x' ), 'un carácter de más lo invalida' );
		$this->assertSame( 0, $metodo->invoke( null, 'basura' ) );
		$this->assertSame( 0, $metodo->invoke( null, '-0' ) );
	}

	/**
	 * El calendario: cabecera, un evento por turno y cierre, con CRLF.
	 */
	public function test_el_calendario(): void {
		$this->turno( 1, '2026-11-05 17:00:00', '21:00', 7 );
		$this->turno( 2, '2026-11-06 10:00:00', '13:00', 0, 7 );

		$ics = \Convoca\Shifts\Ical::feed( 7 );

		$this->assertStringContainsString( "BEGIN:VCALENDAR\r\n", $ics );
		$this->assertStringContainsString( 'VERSION:2.0', $ics );
		$this->assertStringContainsString( 'X-WR-CALNAME:', $ics );
		$this->assertSame( 2, substr_count( $ics, 'BEGIN:VEVENT' ), 'uno como responsable y otro como monitor' );
		$this->assertStringEndsWith( "END:VCALENDAR\r\n", $ics );
		$this->assertStringNotContainsString( "\n\n", str_replace( "\r\n", "\n", $ics ), 'nada de líneas vacías sueltas' );
	}

	/**
	 * El calendario sale plegado a 75 octetos: un lector estricto no lee líneas más largas.
	 */
	public function test_el_calendario_va_plegado(): void {
		$this->turno( 1, '2026-11-05 17:00:00', '21:00', 7 );
		$GLOBALS['_wp_stores']['posts'][1]->post_title = 'Turno con un título larguísimo ' . str_repeat( 'de prueba ', 12 );

		$ics = \Convoca\Shifts\Ical::feed( 7 );

		foreach ( explode( "\r\n", rtrim( $ics, "\r\n" ) ) as $linea ) {
			$this->assertLessThanOrEqual( 75, strlen( $linea ), 'línea demasiado larga: ' . substr( $linea, 0, 40 ) );
		}
		$this->assertStringContainsString( "\r\n ", $ics, 'y hay continuación plegada' );
	}

	/**
	 * El nombre del calendario lleva el del sitio y el de la persona.
	 */
	public function test_el_nombre_del_calendario(): void {
		$GLOBALS['_sh_sitio']['name']                  = 'Centro &amp; Huerto &quot;El Pumarín&quot;';
		$GLOBALS['_wp_stores']['usuarios'][7] = (object) array(
			'ID'           => 7,
			'display_name' => 'Ada Lovelace',
			'first_name'   => 'Ada',
		);

		$ics = \Convoca\Shifts\Ical::feed( 7 );

		$this->assertStringContainsString( 'Centro & Huerto', $ics, 'las entidades se deshacen' );
		$this->assertStringContainsString( '"El Pumarín"', $ics, 'y las comillas también' );
		$this->assertStringContainsString( 'Ada Lovelace', $ics );
	}

	/**
	 * Un turno sin fecha no llega al calendario, y los de fuera de la ventana tampoco.
	 *
	 * La guarda de `feed()` que descarta un evento vacío es **redundante** con este filtro: no hay forma de
	 * llegar a ella desde el feed (un turno sin `_fecha_inicio` no pasa de aquí). Se deja donde está, pero
	 * no se finge que esté probada: lo que se prueba es el efecto, que es que no aparezca ningún evento.
	 */
	public function test_los_turnos_que_no_entran(): void {
		$this->turno( 1, '', '21:00', 7 );
		$this->turno( 2, gmdate( 'Y-m-d H:i:s', time() - ( 3 * 365 * 86400 ) ), '21:00', 7 );
		$this->turno( 3, gmdate( 'Y-m-d H:i:s', time() + ( 3 * 365 * 86400 ) ), '21:00', 7 );
		$this->turno( 4, '2026-11-05 17:00:00', '21:00', 9 );

		$ics = \Convoca\Shifts\Ical::feed( 7 );

		$this->assertSame( 0, substr_count( $ics, 'BEGIN:VEVENT' ), 'ni sin fecha, ni fuera de la ventana, ni de otra persona' );
		$this->assertStringNotContainsString( "\r\n\r\n", $ics, 'un turno sin evento no deja un renglón en blanco' );
	}

	/**
	 * Un turno sin fecha no genera evento, aunque se le llame directamente.
	 *
	 * Por el feed no se llega a esa guarda —la consulta ya descarta los turnos sin `_fecha_inicio`—, así que
	 * se prueba en el método: es la defensa de una llamada directa y no puede quedarse sin cubrir.
	 */
	public function test_un_turno_sin_fecha_no_genera_evento(): void {
		$evento = new \ReflectionMethod( \Convoca\Shifts\Ical::class, 'evento' );

		$sin_fecha = new \WP_Post(
			array(
				'ID'         => 5,
				'post_title' => 'Sin fecha',
			)
		);
		$GLOBALS['_wp_stores']['post_meta'][5] = array( '_hora_fin' => '21:00' );

		$this->assertSame( '', $evento->invoke( null, $sin_fecha ) );

		$con_fecha = new \WP_Post(
			array(
				'ID'         => 6,
				'post_title' => 'Con fecha',
			)
		);
		$GLOBALS['_wp_stores']['post_meta'][6] = array(
			'_fecha_inicio' => '2026-11-05 17:00:00',
			'_hora_fin'     => '21:00',
		);

		$this->assertStringContainsString( 'BEGIN:VEVENT', $evento->invoke( null, $con_fecha ) );
	}

	/**
	 * El fin del turno: su hora, al día siguiente si cruza la medianoche, o cuatro horas después.
	 */
	public function test_el_fin_del_turno(): void {
		$fin = new \ReflectionMethod( \Convoca\Shifts\Ical::class, 'fin_de' );

		$this->assertSame( '2026-11-05 21:00:00', $fin->invoke( null, '2026-11-05 17:00:00', '21:00' ) );

		$cruza = $fin->invoke( null, '2026-11-05 22:00:00', '02:00' );
		$this->assertStringStartsWith( '2026-11-06', $cruza, 'un turno de noche acaba al día siguiente' );

		$sin_hora = $fin->invoke( null, '2026-11-05 17:00:00', '' );
		$this->assertStringContainsString( '21:00:00', $sin_hora, 'sin hora de fin, cuatro horas' );
	}

	/**
	 * El evento lleva la actividad y su enlace en la descripción.
	 */
	public function test_la_descripcion_del_evento(): void {
		$this->turno( 1, '2026-11-05 17:00:00', '21:00', 7 );
		$GLOBALS['_wp_stores']['post_meta'][1]['_actividad'] = 12;
		$GLOBALS['_wp_stores']['posts'][12] = new \WP_Post(
			array(
				'ID'         => 12,
				'post_title' => 'Huerto comunitario',
			)
		);
		$GLOBALS['_sh_enlaces'][12] = 'https://example.test/actividad/huerto';

		$ics = \Convoca\Shifts\Ical::feed( 7 );

		$this->assertStringContainsString( 'DESCRIPTION:', $ics );
		$this->assertStringContainsString( 'Huerto comunitario', $ics );
		$this->assertStringContainsString( 'https://example.test/actividad/huerto', $ics );
	}

	/**
	 * Escapado del RFC 5545: barras, punto y coma, comas y saltos de línea.
	 */
	public function test_el_escapado(): void {
		$this->assertSame( 'a\\\\b', \Convoca\Shifts\Ical::escapar( 'a\\b' ), 'la barra primero' );
		$this->assertSame( 'a\\;b', \Convoca\Shifts\Ical::escapar( 'a;b' ) );
		$this->assertSame( 'a\\,b', \Convoca\Shifts\Ical::escapar( 'a,b' ) );
		$this->assertSame( 'a\\nb', \Convoca\Shifts\Ical::escapar( "a\nb" ) );
		$this->assertSame( 'a\\nb', \Convoca\Shifts\Ical::escapar( "a\r\nb" ) );
	}

	/**
	 * El plegado: hasta 75 octetos no toca nada; más largo, se pliega con espacio y sin partir acentos.
	 */
	public function test_el_plegado(): void {
		$corta = str_repeat( 'a', 75 );
		$this->assertSame( $corta, \Convoca\Shifts\Ical::plegar( $corta ), 'cabe, no se toca' );

		$larga    = str_repeat( 'b', 100 );
		$plegada  = \Convoca\Shifts\Ical::plegar( $larga );
		$renglones = explode( "\r\n", $plegada );

		$this->assertCount( 2, $renglones );
		$this->assertStringStartsWith( ' ', $renglones[1], 'la continuación empieza con un espacio' );
		$this->assertLessThanOrEqual( 75, strlen( $renglones[0] ) );
		$this->assertSame( $larga, str_replace( "\r\n ", '', $plegada ), 'y se puede recomponer' );

		// Un texto con acentos no se parte por la mitad de un carácter: cada renglón sigue siendo UTF-8
		// válido (medio carácter multibyte lo rompería) y se recompone igual.
		$con_acentos = 'SUMMARY:' . str_repeat( 'ñ', 60 );
		$plegado     = \Convoca\Shifts\Ical::plegar( $con_acentos );

		$this->assertSame( $con_acentos, str_replace( "\r\n ", '', $plegado ), 'se recompone igual' );
		foreach ( explode( "\r\n", $plegado ) as $renglon ) {
			$this->assertSame( 1, preg_match( '//u', $renglon ), 'cada renglón es UTF-8 válido: no hay caracteres partidos' );
		}
	}

	/**
	 * Un texto de varias líneas se pliega línea a línea (el evento entero llega con CRLF dentro).
	 */
	public function test_el_plegado_por_lineas(): void {
		$dos = 'DTSTAMP:20261105T170000Z' . "\r\n" . 'SUMMARY:' . str_repeat( 'x', 90 );

		$plegado = \Convoca\Shifts\Ical::plegar( $dos );

		$this->assertStringStartsWith( 'DTSTAMP:20261105T170000Z' . "\r\n", $plegado, 'la primera no se toca' );
		$this->assertStringContainsString( "\r\n ", $plegado, 'y la larga se pliega' );
	}

	/**
	 * La fecha va en UTC con su Z.
	 */
	public function test_la_fecha_en_utc(): void {
		$utc = \Convoca\Shifts\Ical::utc( '2026-11-05 17:00:00' );

		$this->assertMatchesRegularExpression( '/^\d{8}T\d{6}Z$/', $utc );
	}

	/**
	 * El enganche del feed.
	 */
	public function test_el_enganche(): void {
		\Convoca\Shifts\Ical::init();

		$this->assertContains( 'init', array_keys( $GLOBALS['_wp_stores']['acciones'] ?? array() ) );
	}

	/**
	 * Sin el parámetro en la URL no se sirve nada (y no se corta la ejecución).
	 */
	public function test_sin_parametro_no_sirve_nada(): void {
		ob_start();
		\Convoca\Shifts\Ical::quizas_servir_feed();
		$salida = (string) ob_get_clean();

		$this->assertSame( '', $salida );
		$this->assertSame( array(), $GLOBALS['_sh_estados'] ?? array(), 'no se manda ningún estado' );
	}

	/**
	 * Deja un turno en el arnés.
	 *
	 * @param int    $id          Turno.
	 * @param string $fecha       Inicio (Y-m-d H:i:s) o vacío.
	 * @param string $hora_fin    Hora de fin.
	 * @param int    $responsable Persona que lo cubre.
	 * @param int    $monitor     Monitor, si lo hay.
	 */
	private function turno( int $id, string $fecha, string $hora_fin, int $responsable, int $monitor = 0 ): void {
		$GLOBALS['_wp_stores']['posts'][ $id ] = new \WP_Post(
			array(
				'ID'          => $id,
				'post_type'   => 'centro_turno',
				'post_status' => 'publish',
				'post_title'  => 'Turno ' . $id,
				'post_date'   => $fecha,
			)
		);

		$GLOBALS['_wp_stores']['post_meta'][ $id ] = array(
			'_fecha_inicio'   => $fecha,
			'_hora_fin'       => $hora_fin,
			'_id_responsable' => $responsable,
			'_monitor'        => $monitor,
		);
	}
}
