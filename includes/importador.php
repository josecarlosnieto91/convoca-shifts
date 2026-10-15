<?php
/**
 * Importación de turnos desde una plantilla (issue #13).
 *
 * La web del módulo prometía «Importa plantillas desde Excel para alta masiva de turnos» y no existía nada. Se
 * importa **CSV**, que es lo que Excel abre y guarda de serie (y lo que exporta al darle a «Guardar como»),
 * sin meter una librería de hojas de cálculo dentro del plugin: la plantilla se descarga desde aquí, se
 * rellena en Excel y se vuelve a subir.
 *
 * La creación de cada turno se delega en `convoca_shifts_insert_turno()`, que es la misma que usa el generador
 * de semanas: el horario del centro, el estado y el título se calculan en un solo sitio. Lo que aporta este
 * fichero es leer el fichero, decírselo claro a quien lo sube cuando una fila no vale, y **no duplicar** un
 * turno que ya existe (misma fecha y misma hora de inicio), como hace el generador.
 *
 * @package Convoca\Shifts
 */

namespace Convoca\Shifts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Importador de turnos por plantilla.
 */
class Importador {

	/** Capacidad que hace falta para importar. */
	const CAP = 'convoca_shifts_manage_turnos';

	/** Acción del formulario. */
	const ACCION = 'convoca_shifts_importar';

	/**
	 * Columnas de la plantilla, con un ejemplo para que se vea de un vistazo.
	 *
	 * @return string CSV.
	 */
	public static function plantilla(): string {
		return implode(
			"\n",
			array(
				'fecha,hora_inicio,hora_fin,actividad,monitor,responsable,estado,apoyo',
				'05/11/2026,17:00,21:00,Huerto comunitario,jose.nietovoluntario414,,abierto_disponible,0',
				'2026-11-06,10:00,13:00,,,jose.nietovoluntario414,cerrado,1',
			)
		) . "\n";
	}

	/**
	 * Engancha el importador.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_init', array( self::class, 'quizas_descargar_plantilla' ) );
		add_action( 'admin_post_' . self::ACCION, array( self::class, 'handle' ) );
	}

	/**
	 * Descarga la plantilla (antes de que se pinte la página).
	 *
	 * `header()` dentro del render de la página llega tarde: WordPress ya ha mandado la cabecera y pintado el
	 * marco del panel, así que el fichero salía envuelto en HTML —medido en la demo: la descarga devolvía
	 * `<!DOCTYPE html>`—. Se atiende en `admin_init`, que es antes de que exista salida.
	 */
	public static function quizas_descargar_plantilla(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- descarga de una plantilla vacía: no cambia nada.
		$pagina = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'convoca_shifts_importar' !== $pagina || ! isset( $_GET['plantilla'] ) ) {
			return;
		}

		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'No tienes permiso para importar turnos.', 'convoca-shifts' ) );
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="plantilla-turnos.csv"' );
		echo self::plantilla(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- es un CSV, no HTML.
		exit;
	}

	/**
	 * Cuelga la página de importación del menú de turnos.
	 */
	public static function menu(): void {
		add_submenu_page(
			'edit.php?post_type=centro_turno',
			__( 'Importar turnos', 'convoca-shifts' ),
			__( 'Importar turnos', 'convoca-shifts' ),
			self::CAP,
			'convoca_shifts_importar',
			array( self::class, 'pagina' )
		);
	}

	/**
	 * Lee la plantilla y devuelve las filas válidas y los problemas encontrados.
	 *
	 * Sin llamadas a WordPress a propósito: se puede probar sin arrancar el sitio, que es donde de verdad se
	 * rompen estas cosas (un separador, un BOM, una coma decimal).
	 *
	 * @param string $csv Contenido del fichero.
	 * @return array{filas: array<int, array<string, string>>, errores: string[]}
	 */
	public static function parsear( string $csv ): array {
		$csv = str_replace( array( "\r\n", "\r" ), "\n", $csv );
		$csv = preg_replace( '/^\xEF\xBB\xBF/', '', $csv ); // BOM de Excel.

		$lineas = array();
		foreach ( explode( "\n", (string) $csv ) as $numero => $linea ) {
			$limpia = trim( $linea );
			if ( '' !== $limpia ) {
				$lineas[ $numero + 1 ] = $limpia;
			}
		}

		if ( empty( $lineas ) ) {
			return array(
				'filas'   => array(),
				'errores' => array( 'El fichero está vacío.' ),
			);
		}

		// La cabecera se aparta SIN `array_shift`: esa función reindexa y los números de línea que se le
		// dicen a quien sube el fichero dejarían de ser los del fichero (avisaba de la «línea 1» para un
		// problema en la 3, que no ayuda a nadie).
		$numeros  = array_keys( $lineas );
		$cabecera = (string) $lineas[ $numeros[0] ];
		unset( $lineas[ $numeros[0] ] );

		$separador = self::separador( $cabecera );
		$cabeceras = self::normalizar_cabeceras( (array) str_getcsv( $cabecera, $separador, '"', '' ) );

		foreach ( array( 'fecha', 'hora_inicio', 'hora_fin' ) as $obligatoria ) {
			if ( ! in_array( $obligatoria, $cabeceras, true ) ) {
				return array(
					'filas'   => array(),
					'errores' => array(
						sprintf(
							/* translators: %s: nombre de la columna que falta. */
							__( 'A la plantilla le falta la columna «%s». Descarga la plantilla de esta página y rellénala.', 'convoca-shifts' ),
							$obligatoria
						),
					),
				);
			}
		}

		$filas   = array();
		$errores = array();

		foreach ( $lineas as $numero => $linea ) {
			$valores = array_pad( (array) str_getcsv( $linea, $separador, '"', '' ), count( $cabeceras ), '' );
			$fila    = array();
			foreach ( $cabeceras as $i => $columna ) {
				$fila[ $columna ] = trim( (string) ( $valores[ $i ] ?? '' ) );
			}

			$fecha = self::fecha( $fila['fecha'] );
			if ( '' === $fecha ) {
				$errores[] = sprintf(
					/* translators: 1: número de línea, 2: valor leído. */
					__( 'Línea %1$d: «%2$s» no es una fecha válida (usa 05/11/2026 o 2026-11-05).', 'convoca-shifts' ),
					$numero,
					$fila['fecha']
				);
				continue;
			}

			$inicio = self::hora( $fila['hora_inicio'] );
			$fin    = self::hora( $fila['hora_fin'] );
			if ( '' === $inicio || '' === $fin ) {
				$errores[] = sprintf(
					/* translators: 1: número de línea, 2: hora de inicio, 3: hora de fin. */
					__( 'Línea %1$d: las horas tienen que ir en formato 17:00 (leído «%2$s» y «%3$s»).', 'convoca-shifts' ),
					$numero,
					$fila['hora_inicio'],
					$fila['hora_fin']
				);
				continue;
			}

			$fila['fecha']       = $fecha;
			$fila['hora_inicio'] = $inicio;
			$fila['hora_fin']    = $fin;
			$filas[]             = $fila;
		}

		return array(
			'filas'   => $filas,
			'errores' => $errores,
		);
	}

	/**
	 * Separador del fichero: Excel en español guarda con punto y coma.
	 *
	 * @param string $linea Primera línea.
	 * @return string
	 */
	public static function separador( string $linea ): string {
		return substr_count( $linea, ';' ) > substr_count( $linea, ',' ) ? ';' : ',';
	}

	/**
	 * Normaliza los nombres de columna: minúsculas, sin acentos y con la barra baja del guion.
	 *
	 * @param array $valores Cabeceras tal cual vienen.
	 * @return string[]
	 */
	private static function normalizar_cabeceras( array $valores ): array {
		$salida = array();
		foreach ( $valores as $valor ) {
			$clave    = strtolower( trim( (string) $valor ) );
			$clave    = strtr(
				$clave,
				array(
					'á' => 'a',
					'é' => 'e',
					'í' => 'i',
					'ó' => 'o',
					'ú' => 'u',
					'ñ' => 'n',
					' '  => '_',
					'-'  => '_',
				)
			);
			$salida[] = preg_replace( '/_+/', '_', $clave );
		}

		return $salida;
	}

	/**
	 * Fecha a Y-m-d, aceptando lo que la gente escribe (05/11/2026, 5-11-2026, 2026-11-05).
	 *
	 * @param string $valor Valor leído.
	 * @return string Fecha o '' si no vale.
	 */
	public static function fecha( string $valor ): string {
		$valor = trim( $valor );
		if ( '' === $valor ) {
			return '';
		}

		// `d/m/Y` primero: es lo que se escribe en España, y `strtotime` leería 05/11/2026 como 11 de mayo.
		foreach ( array( 'd/m/Y', 'd-m-Y', 'd.m.Y', 'Y-m-d', 'Y/m/d' ) as $formato ) {
			$fecha = \DateTime::createFromFormat( $formato, $valor );
			if ( $fecha && $fecha->format( $formato ) === $valor ) {
				return $fecha->format( 'Y-m-d' );
			}
		}

		$timestamp = strtotime( $valor );

		return $timestamp ? gmdate( 'Y-m-d', $timestamp ) : '';
	}

	/**
	 * Hora a H:i.
	 *
	 * @param string $valor Valor leído.
	 * @return string Hora o '' si no vale.
	 */
	public static function hora( string $valor ): string {
		$valor = trim( $valor );
		if ( ! preg_match( '/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $valor, $m ) ) {
			return '';
		}

		$hora   = (int) $m[1];
		$minuto = (int) $m[2];
		if ( $hora > 23 || $minuto > 59 ) {
			return '';
		}

		return sprintf( '%02d:%02d', $hora, $minuto );
	}

	/**
	 * Crea los turnos de las filas leídas.
	 *
	 * @param array $filas Filas ya validadas por `parsear()`.
	 * @return array{creados: int, repetidos: int, errores: string[]}
	 */
	public static function importar( array $filas ): array {
		$creados   = 0;
		$repetidos = 0;
		$errores   = array();

		foreach ( $filas as $fila ) {
			$actividad = self::resolver_actividad( (string) ( $fila['actividad'] ?? '' ) );
			if ( '' !== ( $fila['actividad'] ?? '' ) && ! $actividad ) {
				$errores[] = sprintf(
					/* translators: %s: nombre de la actividad. */
					__( 'No encuentro la actividad «%s»: revísala o déjala vacía.', 'convoca-shifts' ),
					$fila['actividad']
				);
				continue;
			}

			$responsable = self::resolver_usuario( (string) ( $fila['responsable'] ?? '' ) );
			$monitor     = self::resolver_usuario( (string) ( $fila['monitor'] ?? '' ) );
			$personas    = array(
				'responsable' => $responsable,
				'monitor'     => $monitor,
			);
			foreach ( $personas as $papel => $encontrado ) {
				$leido = (string) ( $fila[ $papel ] ?? '' );
				if ( '' !== $leido && ! $encontrado ) {
					$errores[] = sprintf(
						/* translators: 1: papel (responsable o monitor), 2: valor leído. */
						__( 'No encuentro a la persona «%2$s» para el %1$s: usa el usuario, el correo o el número.', 'convoca-shifts' ),
						$papel,
						$leido
					);
				}
			}

			if ( self::ya_existe( (string) $fila['fecha'], (string) $fila['hora_inicio'] ) ) {
				++$repetidos;
				continue;
			}

			$nuevo = convoca_shifts_insert_turno(
				array(
					'date'           => (string) $fila['fecha'],
					'h_start'        => (string) $fila['hora_inicio'],
					'h_end'          => (string) $fila['hora_fin'],
					'estado'         => self::estado( (string) ( $fila['estado'] ?? '' ) ),
					'necesita_apoyo' => self::si( (string) ( $fila['apoyo'] ?? '' ) ) ? 1 : 0,
					'actividad_id'   => $actividad,
					'id_responsable' => $responsable,
					'monitor_id'     => $monitor,
				)
			);

			if ( is_wp_error( $nuevo ) || ! $nuevo ) {
				$errores[] = sprintf(
					/* translators: %s: fecha de la fila. */
					__( 'No se ha podido crear el turno del %s.', 'convoca-shifts' ),
					(string) $fila['fecha']
				);
				continue;
			}

			++$creados;
			if ( function_exists( __NAMESPACE__ . '\\convoca_shifts_log_activity' ) ) {
				convoca_shifts_log_activity( get_current_user_id(), (int) $nuevo, 'turno_creado', array( 'origen' => 'importacion' ) );
			}
		}

		return array(
			'creados'   => $creados,
			'repetidos' => $repetidos,
			'errores'   => $errores,
		);
	}

	/**
	 * ¿Ya hay un turno ese día a esa hora? Mismo criterio que el generador de semanas.
	 *
	 * @param string $fecha  Y-m-d.
	 * @param string $inicio H:i.
	 * @return bool
	 */
	private static function ya_existe( string $fecha, string $inicio ): bool {
		$existentes = get_posts(
			array(
				'post_type'      => 'centro_turno',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => '_fecha_inicio',
						'value'   => $fecha . ' ' . $inicio,
						'compare' => 'LIKE',
					),
				),
			)
		);

		return ! empty( $existentes );
	}

	/**
	 * Interpreta el «sí» de una hoja de cálculo.
	 *
	 * En una casilla la gente escribe 1, x, sí, SI, true… Cualquiera de esas vale como sí; el resto, no.
	 *
	 * @param string $valor Valor leído.
	 * @return bool
	 */
	public static function si( string $valor ): bool {
		// Se quitan los acentos ANTES de bajar a minúsculas: `strtolower()` es byte a byte y a «SÍ» no le toca
		// la Í, así que un «SÍ» escrito en una casilla no contaba como sí (medido: la comprobación fallaba y
		// el aviso salía solo con «sí» en minúsculas). Se miran las dos cajas.
		$limpio = strtolower(
			strtr(
				trim( $valor ),
				array(
					'Á' => 'a',
					'É' => 'e',
					'Í' => 'i',
					'Ó' => 'o',
					'Ú' => 'u',
					'Ñ' => 'n',
					'á' => 'a',
					'é' => 'e',
					'í' => 'i',
					'ó' => 'o',
					'ú' => 'u',
					'ñ' => 'n',
				)
			)
		);

		return in_array( $limpio, array( '1', 'si', 's', 'x', 'yes', 'y', 'true', 'v', 'verdadero' ), true );
	}

	/**
	 * Estado válido del turno.
	 *
	 * @param string $valor Valor leído.
	 * @return string
	 */
	private static function estado( string $valor ): string {
		$valor = strtolower( trim( $valor ) );
		$mapa  = array(
			'abierto_disponible' => 'abierto_disponible',
			'abierto'            => 'abierto_disponible',
			'disponible'         => 'abierto_disponible',
			'abierto_ocupado'    => 'abierto_ocupado',
			'ocupado'            => 'abierto_ocupado',
			'cerrado'            => 'cerrado',
		);

		return $mapa[ $valor ] ?? 'abierto_disponible';
	}

	/**
	 * Actividad por nombre o por número, dentro de la taxonomía del plugin.
	 *
	 * @param string $valor Valor leído.
	 * @return int Id del término, o 0.
	 */
	private static function resolver_actividad( string $valor ): int {
		$valor = trim( $valor );
		if ( '' === $valor ) {
			return 0;
		}

		if ( ctype_digit( $valor ) ) {
			$termino = get_term( (int) $valor, 'convoca_shifts_actividad' );

			return ( $termino && ! is_wp_error( $termino ) ) ? (int) $termino->term_id : 0;
		}

		$termino = get_term_by( 'name', $valor, 'convoca_shifts_actividad' );

		return $termino ? (int) $termino->term_id : 0;
	}

	/**
	 * Persona por número, usuario o correo.
	 *
	 * @param string $valor Valor leído.
	 * @return int Id de usuario, o 0.
	 */
	private static function resolver_usuario( string $valor ): int {
		$valor = trim( $valor );
		if ( '' === $valor ) {
			return 0;
		}

		if ( ctype_digit( $valor ) ) {
			return get_userdata( (int) $valor ) ? (int) $valor : 0;
		}

		$user = get_user_by( 'login', $valor );
		if ( ! $user && is_email( $valor ) ) {
			$user = get_user_by( 'email', $valor );
		}

		return $user ? (int) $user->ID : 0;
	}

	/**
	 * Página de importación.
	 */
	public static function pagina(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'No tienes permiso para importar turnos.', 'convoca-shifts' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo pinta el resultado de la importación.
		$resultado = get_transient( 'convoca_shifts_import_result_' . get_current_user_id() );
		if ( $resultado ) {
			delete_transient( 'convoca_shifts_import_result_' . get_current_user_id() );
		}

		$url_plantilla = add_query_arg( 'plantilla', '1', admin_url( 'edit.php?post_type=centro_turno&page=convoca_shifts_importar' ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Importar turnos desde una plantilla', 'convoca-shifts' ); ?></h1>

			<?php if ( is_array( $resultado ) ) : ?>
				<div class="notice notice-success">
					<p>
						<?php
						printf(
							/* translators: 1: turnos creados, 2: turnos repetidos. */
							esc_html__( 'Importación terminada: %1$d turnos creados y %2$d repetidos (no se han duplicado).', 'convoca-shifts' ),
							(int) ( $resultado['creados'] ?? 0 ),
							(int) ( $resultado['repetidos'] ?? 0 )
						);
						?>
					</p>
				</div>
				<?php if ( ! empty( $resultado['errores'] ) ) : ?>
					<div class="notice notice-warning">
						<p><?php esc_html_e( 'Estas filas no se han podido usar:', 'convoca-shifts' ); ?></p>
						<ul>
							<?php foreach ( (array) $resultado['errores'] as $error ) : ?>
								<li><?php echo esc_html( (string) $error ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<p><?php esc_html_e( 'Descarga la plantilla, rellénala en Excel (o en una hoja de cálculo) y súbela aquí. Excel guarda en el formato que se lee aquí tal cual: «Guardar como» → CSV.', 'convoca-shifts' ); ?></p>
			<p>
				<a class="button" href="<?php echo esc_url( $url_plantilla ); ?>">
					<?php esc_html_e( 'Descargar la plantilla', 'convoca-shifts' ); ?>
				</a>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACCION ); ?>">
				<?php wp_nonce_field( self::ACCION ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="convoca_shifts_csv"><?php esc_html_e( 'Plantilla rellena', 'convoca-shifts' ); ?></label></th>
						<td>
							<input type="file" name="convoca_shifts_csv" id="convoca_shifts_csv" accept=".csv,text/csv" required>
							<p class="description">
								<?php esc_html_e( 'Columnas: fecha, hora_inicio, hora_fin, actividad, monitor, responsable, estado, apoyo. La actividad y las personas pueden ir por nombre o por número.', 'convoca-shifts' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Importar los turnos', 'convoca-shifts' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Atiende la subida.
	 */
	public static function handle(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'No tienes permiso para importar turnos.', 'convoca-shifts' ) );
		}

		check_admin_referer( self::ACCION );

		$fichero = $_FILES['convoca_shifts_csv'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- es un fichero, no un texto.
		$destino = admin_url( 'edit.php?post_type=centro_turno&page=convoca_shifts_importar' );

		if ( ! $fichero || ! isset( $fichero['tmp_name'] ) || ! is_uploaded_file( $fichero['tmp_name'] ) ) {
			set_transient(
				'convoca_shifts_import_result_' . get_current_user_id(),
				array(
					'creados'   => 0,
					'repetidos' => 0,
					'errores'   => array( __( 'No ha llegado ningún fichero. Elige la plantilla rellena antes de pulsar el botón.', 'convoca-shifts' ) ),
				),
				60
			);
			wp_safe_redirect( $destino );
			exit;
		}

		$contenido = (string) file_get_contents( $fichero['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- lectura local del fichero subido.
		$leido     = self::parsear( $contenido );
		$resultado = array(
			'creados'   => 0,
			'repetidos' => 0,
			'errores'   => $leido['errores'],
		);

		if ( ! empty( $leido['filas'] ) ) {
			$hecho                  = self::importar( $leido['filas'] );
			$resultado['creados']   = $hecho['creados'];
			$resultado['repetidos'] = $hecho['repetidos'];
			$resultado['errores']   = array_merge( $resultado['errores'], $hecho['errores'] );
		}

		set_transient( 'convoca_shifts_import_result_' . get_current_user_id(), $resultado, 60 );
		wp_safe_redirect( $destino );
		exit;
	}
}
