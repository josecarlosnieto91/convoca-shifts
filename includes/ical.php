<?php
/**
 * Feed de calendario con los turnos de cada voluntario (issue #13).
 *
 * La web del módulo prometía «Exportación a iCal, Google Calendar» y no existía nada: cero apariciones de
 * `ics`, `iCal` o `webcal` en todo el plugin. En vez de un botón que descargue un fichero —que hay que
 * volver a descargar cada vez que cambia un turno— se sirve un **feed suscribible**: el voluntario pega la
 * URL en su calendario y sus turnos se mantienen al día solos. Google Calendar también sabe suscribirse a
 * una URL `.ics` (Otros calendarios → Suscribirse desde una URL), así que esto cubre las dos promesas.
 *
 * La URL lleva un testigo por usuario: **privado pero estable**. Una suscripción que caduca no sirve, así que
 * no es un nonce; es un HMAC con la sal del sitio, que no se puede adivinar ni reutilizar entre usuarios.
 *
 * @package Convoca\Shifts
 */

namespace Convoca\Shifts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Feed iCal de turnos.
 */
class Ical {

	/** Parámetro de la URL del feed. */
	const QUERY_VAR = 'convoca_shifts_ics';

	/**
	 * Engancha el feed.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'quizas_servir_feed' ) );
	}

	/**
	 * URL de suscripción de un voluntario.
	 *
	 * @param int $user_id Usuario.
	 * @return string
	 */
	public static function url_de( int $user_id ): string {
		if ( $user_id <= 0 ) {
			return '';
		}

		return add_query_arg( self::QUERY_VAR, self::token( $user_id ), home_url( '/' ) );
	}

	/**
	 * Testigo del feed: usuario + HMAC del sitio.
	 *
	 * Va el identificador a la vista porque no aporta nada al que no tenga el HMAC (`hash_equals` compara el
	 * HMAC completo), y lo simplifica mucho para poder decir de quién es el feed.
	 *
	 * @param int $user_id Usuario.
	 * @return string
	 */
	public static function token( int $user_id ): string {
		$hmac = hash_hmac( 'sha256', 'convoca_shifts_ics_' . $user_id, wp_salt( 'auth' ) );

		return substr( $hmac, 0, 32 ) . '-' . $user_id;
	}

	/**
	 * Usuario al que pertenece un testigo, o 0 si no vale.
	 *
	 * @param string $token Testigo recibido.
	 * @return int
	 */
	private static function usuario_del_token( string $token ): int {
		$partes  = explode( '-', $token );
		$user_id = (int) array_pop( $partes );
		if ( $user_id <= 0 ) {
			return 0;
		}

		// Comparación en tiempo constante: el testigo es una credencial de lectura.
		return hash_equals( self::token( $user_id ), $token ) ? $user_id : 0;
	}

	/**
	 * Sirve el feed si la petición lo pide.
	 */
	public static function quizas_servir_feed(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- es un feed para un calendario
		// externo: no hay nonce que un lector de calendario pueda mandar. La credencial es el testigo.
		if ( empty( $_GET[ self::QUERY_VAR ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token   = sanitize_text_field( wp_unslash( (string) $_GET[ self::QUERY_VAR ] ) );
		$user_id = self::usuario_del_token( $token );

		if ( ! $user_id ) {
			status_header( 403 );
			nocache_headers();
			exit;
		}

		$feed = self::feed( $user_id );

		nocache_headers();
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: inline; filename="convoca-turnos.ics"' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text/calendar no lleva HTML: el
		// contenido va escapado según el RFC 5545 (comas, puntos y coma, barras y saltos) antes de salir.
		echo $feed;
		exit;
	}

	/**
	 * Construye el calendario de un voluntario.
	 *
	 * @param int $user_id Usuario.
	 * @return string
	 */
	public static function feed( int $user_id ): string {
		$lineas = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Convoca//Turnos//ES',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'X-WR-CALNAME:' . self::escapar( self::nombre_calendario( $user_id ) ),
		);

		foreach ( self::turnos_de( $user_id ) as $turno ) {
			$evento = self::evento( $turno );
			if ( '' !== $evento ) {
				$lineas[] = $evento;
			}
		}

		$lineas[] = 'END:VCALENDAR';

		// El RFC 5545 pide CRLF y líneas de 75 octetos como mucho, plegadas con un espacio al principio.
		$salida = '';
		foreach ( $lineas as $linea ) {
			$salida .= self::plegar( $linea ) . "\r\n";
		}

		return $salida;
	}

	/**
	 * Nombre del calendario tal y como lo verá el voluntario.
	 *
	 * @param int $user_id Usuario.
	 * @return string
	 */
	private static function nombre_calendario( int $user_id ): string {
		$sitio = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$user  = get_userdata( $user_id );
		$quien = $user ? $user->display_name : '';

		return trim( sprintf( 'Turnos · %s%s', $sitio, $quien ? ' · ' . $quien : '' ) );
	}

	/**
	 * Turnos de un voluntario: donde figura como responsable o como monitor.
	 *
	 * Se incluye el último año ya pasado además de lo que viene: así el calendario del voluntario no pierde
	 * los turnos que acaba de hacer (y que muchas veces quiere consultar) al suscribirse.
	 *
	 * @param int $user_id Usuario.
	 * @return \WP_Post[]
	 */
	private static function turnos_de( int $user_id ): array {
		$turnos = get_posts(
			array(
				'post_type'      => 'centro_turno',
				'post_status'    => 'publish',
				'posts_per_page' => 300,
				'orderby'        => 'meta_value',
				'meta_key'       => '_fecha_inicio',
				'order'          => 'ASC',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'   => '_id_responsable',
						'value' => (string) $user_id,
					),
					array(
						'key'   => '_monitor',
						'value' => (string) $user_id,
					),
				),
			)
		);

		$desde = (int) strtotime( '-1 year' );
		$hasta = (int) strtotime( '+2 years' );

		return array_values(
			array_filter(
				$turnos,
				static function ( $turno ) use ( $desde, $hasta ) {
					$inicio = strtotime( (string) get_post_meta( $turno->ID, '_fecha_inicio', true ) );

					return $inicio && $inicio >= $desde && $inicio <= $hasta;
				}
			)
		);
	}

	/**
	 * Un VEVENT a partir de un turno, o '' si el turno no tiene fecha.
	 *
	 * @param \WP_Post $turno Turno.
	 * @return string
	 */
	private static function evento( \WP_Post $turno ): string {
		$inicio_local = (string) get_post_meta( $turno->ID, '_fecha_inicio', true );
		if ( '' === $inicio_local || ! strtotime( $inicio_local ) ) {
			return '';
		}

		$fin_local = self::fin_de( $inicio_local, (string) get_post_meta( $turno->ID, '_hora_fin', true ) );
		$resumen   = $turno->post_title;
		$detalles  = self::descripcion( $turno->ID );

		$evento   = array();
		$evento[] = 'BEGIN:VEVENT';
		$evento[] = 'UID:convoca-turno-' . $turno->ID . '@' . self::host();
		$evento[] = 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' );
		$evento[] = 'DTSTART:' . self::utc( $inicio_local );
		$evento[] = 'DTEND:' . self::utc( $fin_local );
		$evento[] = 'SUMMARY:' . self::escapar( $resumen );
		if ( '' !== $detalles ) {
			$evento[] = 'DESCRIPTION:' . self::escapar( $detalles );
		}
		$evento[] = 'STATUS:CONFIRMED';
		$evento[] = 'SEQUENCE:0';
		$evento[] = 'END:VEVENT';

		return implode( "\r\n", $evento );
	}

	/**
	 * Fin del turno: la hora de fin sobre el día de inicio, o cuatro horas si no consta.
	 *
	 * Si la hora de fin es anterior (o igual) a la de inicio se entiende que el turno cruza la medianoche
	 * —una noche de fin de semana, por ejemplo— y termina al día siguiente. Sin esto, el calendario pintaría
	 * turnos que acaban antes de empezar.
	 *
	 * @param string $inicio_local Inicio (Y-m-d H:i:s).
	 * @param string $hora_fin     Hora de fin (H:i) o vacío.
	 * @return string Fin (Y-m-d H:i:s).
	 */
	private static function fin_de( string $inicio_local, string $hora_fin ): string {
		$dia = substr( $inicio_local, 0, 10 );

		if ( preg_match( '/^\d{1,2}:\d{2}$/', $hora_fin ) ) {
			$hora_fin = strlen( $hora_fin ) === 4 ? '0' . $hora_fin : $hora_fin;
			$fin      = $dia . ' ' . $hora_fin . ':00';
			if ( strtotime( $fin ) <= strtotime( $inicio_local ) ) {
				$fin = wp_date( 'Y-m-d H:i:s', strtotime( $fin . ' +1 day' ) );
			}

			return $fin;
		}

		return wp_date( 'Y-m-d H:i:s', strtotime( $inicio_local . ' +4 hours' ) );
	}

	/**
	 * Descripción: la actividad a la que pertenece el turno, con su enlace si se puede resolver.
	 *
	 * @param int $turno_id Turno.
	 * @return string
	 */
	private static function descripcion( int $turno_id ): string {
		$actividad_id = (int) get_post_meta( $turno_id, '_actividad', true );
		if ( $actividad_id <= 0 ) {
			return '';
		}

		$actividad = get_post( $actividad_id );
		if ( ! $actividad ) {
			return '';
		}

		$enlace = get_permalink( $actividad_id );

		return $enlace ? $actividad->post_title . " \n" . $enlace : $actividad->post_title;
	}

	/**
	 * Fecha local → sello UTC del RFC 5545.
	 *
	 * Se manda en UTC y no con `TZID` a propósito: así el calendario pinta el turno a la hora correcta en
	 * cualquier país, sin depender de que quien lo lea conozca la zona del sitio.
	 *
	 * @param string $fecha_local Fecha local (Y-m-d H:i:s).
	 * @return string
	 */
	public static function utc( string $fecha_local ): string {
		$gmt = get_gmt_from_date( $fecha_local );

		return gmdate( 'Ymd\THis\Z', (int) strtotime( $gmt . ' UTC' ) );
	}

	/**
	 * Host para el UID de los eventos.
	 *
	 * @return string
	 */
	private static function host(): string {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		return $host ? (string) $host : 'convoca.local';
	}

	/**
	 * Escapa un texto según el RFC 5545.
	 *
	 * La barra primero: si se escapara después habría que escapar también las que introduce el propio escape.
	 *
	 * @param string $texto Texto.
	 * @return string
	 */
	public static function escapar( string $texto ): string {
		$texto = str_replace( '\\', '\\\\', $texto );
		$texto = str_replace( array( ';', ',' ), array( '\\;', '\\,' ), $texto );

		return str_replace( array( "\r\n", "\r", "\n" ), '\\n', $texto );
	}

	/**
	 * Pliega a 75 octetos por línea, por caracteres completos.
	 *
	 * Se cuenta en octetos (lo que pide el RFC) y se corta por carácter, que si no se parte un acento en dos
	 * y el calendario enseña un rombo.
	 *
	 * @param string $linea Línea.
	 * @return string
	 */
	public static function plegar( string $linea ): string {
		// Un texto puede traer VARIAS líneas (un evento completo, que llega ya unido con CRLF). Se pliega cada
		// línea por su cuenta: plegando el conjunto, los cortes caen dentro de los propios saltos y parten
		// palabras por la mitad —medido en la demo: «SUMMARY:🟢 C» / «ubierto», y un DTSTAMP de 24 caracteres
		// partido en dos—. Un calendario estricto no lee eso.
		$partes = preg_split( '/\r\n|\r|\n/', $linea );
		if ( ! is_array( $partes ) ) {
			return $linea;
		}

		return implode( "\r\n", array_map( array( self::class, 'plegar_linea' ), $partes ) );
	}

	/**
	 * Pliega UNA línea (sin saltos dentro).
	 *
	 * @param string $linea Línea.
	 * @return string
	 */
	private static function plegar_linea( string $linea ): string {
		if ( strlen( $linea ) <= 75 ) {
			return $linea;
		}

		$salida     = '';
		$actual     = '';
		$longitud   = 0;
		$caracteres = preg_split( '//u', $linea, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $caracteres ) ) {
			return $linea;
		}

		foreach ( $caracteres as $caracter ) {
			$bytes = strlen( $caracter );
			if ( $longitud + $bytes > 74 && '' !== $actual ) {
				$salida  .= $actual . "\r\n ";
				$actual   = '';
				$longitud = 0;
			}
			$actual   .= $caracter;
			$longitud += $bytes;
		}

		return $salida . $actual;
	}
}
