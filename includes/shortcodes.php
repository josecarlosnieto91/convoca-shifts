<?php

/**
 * Convoca Shifts
 *
 * @package    Convoca\Shifts
 * @subpackage Includes
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

/**
 * Convoca Shifts - shortcodes
 *
 * @package Convoca_Shifts
 */

namespace Convoca\Shifts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Hook to register scripts properly.
add_action( 'init', 'Convoca\Shifts\convoca_shifts_register_scripts' );

function convoca_shifts_register_scripts() {
	wp_register_style( 'convoca-shifts-style', CONVOCA_SHIFTS_URL . 'assets/css/estilo.css', array( 'convoca-core' ), CONVOCA_SHIFTS_VERSION );
	// `dashicons` se encola donde de verdad se usa un icono: el botón de apuntarse
	// (`convoca_shifts_boton_apuntarse`). Antes se encolaba aquí, en `init`, y la hoja de
	// dashicons viajaba en TODAS las páginas del sitio aunque no hubiera nada de Convoca.
	// (El icono del menú de admin lo sirve WordPress, que ya carga dashicons allí.)
	// phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent -- FullCalendar CDN is required for the calendar feature.
	// Servido desde el propio plugin: wordpress.org no admite cargar recursos de terceros por CDN
	// (`EnqueuedResourceOffloading`), y además así el calendario no depende de que un CDN externo esté
	// disponible o siga sirviendo esa versión.
	wp_register_script( 'fullcalendar-core', CONVOCA_SHIFTS_URL . 'assets/vendor/fullcalendar/index.global.min.js', array(), '6.1.15', true );
	wp_register_script( 'convoca-shifts-calendario', CONVOCA_SHIFTS_URL . 'assets/js/calendario.js', array( 'fullcalendar-core', 'jquery' ), CONVOCA_SHIFTS_VERSION, true );

	wp_localize_script(
		'convoca-shifts-calendario',
		'convocaShiftsData',
		array(
			'restUrl'        => esc_url_raw( rest_url( 'convoca-shifts/v1/turnos' ) ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'isLogged'       => is_user_logged_in(),
			'userId'         => get_current_user_id(),
			'canManage'      => current_user_can( 'gestionar_mis_turnos' ) || current_user_can( 'manage_options' ),
			'msgConfirm'     => __( '¿Quieres cubrir este turno?', 'convoca-shifts' ),
			'msgCancel'      => __( '¿Quieres dejar este turno libre?', 'convoca-shifts' ),
			'msgError'       => __( 'Hubo un error, por favor recarga la página.', 'convoca-shifts' ),
			'confirmLiberar' => __( '¿Quieres liberar este turno? Si lo haces, otra persona podrá cubrirlo.', 'convoca-shifts' ),
			'errorCrear'     => __( 'Error al crear el turno.', 'convoca-shifts' ),
			'confirmSignup'  => apply_filters( 'convoca_shifts_confirm_signup', true ),
			'horaApertura'   => get_option( 'convoca_shifts_hora_apertura', '09:00' ),
			'horaCierre'     => get_option( 'convoca_shifts_hora_cierre', '22:00' ),
			'msgAdjusted'    => __( 'Este horario se sale del margen permitido del centro y ha sido ajustado.', 'convoca-shifts' ),
			'msgNoEvents'    => __( 'Aún no hay turnos definidos para esta vista.', 'convoca-shifts' ),
		)
	);
}

// Shortcode: [calendario_centro].
add_shortcode( 'convoca_calendario', 'Convoca\Shifts\convoca_shifts_calendario_centro' );
function convoca_shifts_calendario_centro() {
	wp_enqueue_style( 'convoca-shifts-style' );
	wp_enqueue_script( 'convoca-shifts-calendario' );

	ob_start();

	if ( ! is_user_logged_in() ) {
		// Sin configurar no se pone '#': un enlace que no lleva a ninguna parte es peor que no ponerlo.
		// El respaldo es la página de acceso de WordPress, que siempre existe.
		$access_url = (string) get_option( 'convoca_shifts_access_page_url', '' );
		if ( '' === trim( $access_url ) ) {
			$access_url = wp_login_url();
		}
		echo '<div class="convoca-shifts-public-notice">';
		echo '<p>💡 ' . esc_html__( 'Este es el cuadrante actual. Si eres voluntario/a, inicia sesión para apuntarte a un turno.', 'convoca-shifts' ) . ' <a href="' . esc_url( $access_url ) . '">' . esc_html__( 'Ir a Acceso', 'convoca-shifts' ) . '</a></p>';
		echo '</div>';
	} elseif ( is_user_logged_in() && ( ! current_user_can( 'gestionar_mis_turnos' ) && ! current_user_can( 'manage_options' ) ) ) {
		echo '<div class="convoca-shifts-public-notice warning">';
		echo '<p>⏳ ' . esc_html__( 'Tu cuenta está pendiente de aprobación. Podrás apuntarte a turnos en cuanto un administrador te active.', 'convoca-shifts' ) . '</p>';
		echo '</div>';
	}
	?>
	<div id="convoca-shifts-calendario-container" class="convoca-shifts-calendario-wrapper">
		<div class="convoca-shifts-leyenda">
			<span class="convoca-shifts-leyenda-item"><span class="convoca-shifts-dot convoca-shifts-dot-green"></span> Cubierto</span>
			<span class="convoca-shifts-leyenda-item"><span class="convoca-shifts-dot convoca-shifts-dot-yellow"></span> Pendiente</span>
			<span class="convoca-shifts-leyenda-item"><span class="convoca-shifts-dot convoca-shifts-dot-blue"></span> Actividad interna</span>
			<span class="convoca-shifts-leyenda-item"><span class="convoca-shifts-dot convoca-shifts-dot-red"></span> Cerrado</span>
		</div>
		<div id="convoca-shifts-calendario"></div>
	</div>
	<?php
	// Register footer modal output only once.
	static $modal_added = false;
	if ( ! $modal_added ) {
		$modal_added = true;
		add_action( 'wp_footer', 'Convoca\Shifts\convoca_shifts_render_frontend_modal' );
	}

	return ob_get_clean();
}

/**
 * Render the modal in the footer to avoid transform/containment issues.
 */
function convoca_shifts_render_frontend_modal() {
	if ( ! is_user_logged_in() || ( ! current_user_can( 'gestionar_mis_turnos' ) && ! current_user_can( 'manage_options' ) ) ) {
		return;
	}
	?>
	<!-- Modal for Frontend Creation (Moved to footer for proper fixed positioning) -->
	<div id="convoca-shifts-frontend-modal" class="convoca-shifts-modal" style="display:none;">
		<div class="convoca-shifts-modal-content">
			<span class="convoca-shifts-close-frontend" style="float:right; cursor:pointer; font-size:24px;">&times;</span>
			<h2 id="convoca-shifts-fe-modal-title"><?php esc_html_e( 'Crear Nuevo Turno', 'convoca-shifts' ); ?></h2>
			<hr>
			<div class="convoca-shifts-form-group">
				<label><?php esc_html_e( 'Seleccionar Horario', 'convoca-shifts' ); ?></label>
				<div class="convoca-shifts-presets">
					<button type="button" class="convoca-shifts-preset-btn" data-start="10:00" data-end="13:00">☀️ <?php esc_html_e( 'Mañana (10-13h)', 'convoca-shifts' ); ?></button>
					<button type="button" class="convoca-shifts-preset-btn" data-start="17:00" data-end="20:00">🌇 <?php esc_html_e( 'Tarde (17-20h)', 'convoca-shifts' ); ?></button>
					<button type="button" class="convoca-shifts-preset-btn" data-custom="1">⚙️ <?php esc_html_e( 'Personalizado', 'convoca-shifts' ); ?></button>
				</div>
			</div>

			<div id="convoca-shifts-custom-time-fields" style="display:none; margin-top:15px; padding:15px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0;">
				<div class="convoca-shifts-form-row" style="margin-bottom:0;">
					<div class="convoca-shifts-form-group" style="margin-bottom:0;">
						<label><?php esc_html_e( 'Hora Inicio', 'convoca-shifts' ); ?></label>
						<input type="time" id="fe_h_start" value="10:00" step="900">
					</div>
					<div class="convoca-shifts-form-group" style="margin-bottom:0;">
						<label><?php esc_html_e( 'Hora Fin', 'convoca-shifts' ); ?></label>
						<input type="time" id="fe_h_end" value="13:00" step="900">
					</div>
				</div>
			</div>

			<div class="convoca-shifts-form-group">
				<label><?php esc_html_e( 'Estado', 'convoca-shifts' ); ?></label>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<select id="fe_estado" style="width:100%;">
					<option value="abierto_disponible">🟡 <?php esc_html_e( 'Pendiente', 'convoca-shifts' ); ?></option>
					<option value="cerrado">🔴 <?php esc_html_e( 'Cerrado', 'convoca-shifts' ); ?></option>
				</select>
				<?php else : ?>
				<select id="fe_estado" style="width:100%; pointer-events: none; background: #f1f5f9;">
					<option value="abierto_disponible">🟡 <?php esc_html_e( 'Pendiente (Disponible para voluntarios)', 'convoca-shifts' ); ?></option>
				</select>
				<?php endif; ?>
			</div>
			<div class="convoca-shifts-form-group">
				<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
					<input type="checkbox" id="fe_apoyo">
					<span>🛟 <?php esc_html_e( 'Necesita apoyo', 'convoca-shifts' ); ?></span>
				</label>
			</div>
			<div class="convoca-shifts-modal-footer">
				<button type="button" class="convoca-shifts-fe-cancel convoca-btn convoca-btn-outline"><?php esc_html_e( 'Cancelar', 'convoca-shifts' ); ?></button>
				<button type="button" id="convoca-shifts-fe-save" class="convoca-btn convoca-btn-primary"><?php esc_html_e( 'Guardar Turno', 'convoca-shifts' ); ?></button>
			</div>
		</div>
	</div>
	<?php
}

// Shortcode: [boton_apuntarse].
add_shortcode( 'convoca_boton_apuntarse', 'Convoca\Shifts\convoca_shifts_boton_apuntarse' );
function convoca_shifts_boton_apuntarse() {
	if ( ! is_user_logged_in() || ( ! current_user_can( 'gestionar_mis_turnos' ) && ! current_user_can( 'manage_options' ) ) ) {
		return '';
	}

	wp_enqueue_script( 'convoca-shifts-calendario' ); // reuse the script for data.
	wp_enqueue_style( 'convoca-shifts-style' );
	wp_enqueue_style( 'dashicons' ); // El único icono de dashicons del front: el del botón.

	ob_start();
	?>
	<div class="convoca-shifts-boton-apuntarse-container">
		<button id="convoca-shifts-btn-proximo" class="convoca-shifts-btn-proximo">
			<span class="dashicons dashicons-calendar-alt"></span>
			<?php esc_html_e( 'Me apunto al siguiente turno libre', 'convoca-shifts' ); ?>
		</button>
		<div id="convoca-shifts-proximo-msg" class="convoca-shifts-msg" style="display:none;"></div>
	</div>
	<script>
	jQuery(document).ready(function($) {
		$('#convoca-shifts-btn-proximo').on('click', function(e) {
			e.preventDefault();

			var $btn = $(this);
			var $msg = $('#convoca-shifts-proximo-msg');
			// El texto original se guarda al cargar: así el error lo restaura sin duplicar el
			// marcado dentro del JS (que es como se quedó desincronizado).
			if ( ! $btn.data('htmlOriginal') ) {
				$btn.data('htmlOriginal', $btn.html());
			}

			// Si falta la configuración del script, se DICE. Antes esto era un `cstData` que no
			// existía: el navegador lanzaba un ReferenceError y el botón no hacía nada, sin
			// mensaje, sin petición — el voluntario creía que se había apuntado (#17).
			if ( typeof convocaShiftsData === 'undefined' ) {
				$msg.removeClass('convoca-shifts-success').addClass('convoca-shifts-error')
					.text('<?php echo esc_js( __( 'No se ha podido cargar el turno. Recarga la página e inténtalo de nuevo.', 'convoca-shifts' ) ); ?>').show();
				return;
			}

			if (convocaShiftsData.confirmSignup && !confirm('<?php echo esc_js( __( '¿Te apuntas a este turno? Serás responsable de abrir el centro.', 'convoca-shifts' ) ); ?>')) {
				return;
			}

			$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Buscando turno…', 'convoca-shifts' ) ); ?>');

			$.ajax({
				url: convocaShiftsData.restUrl + '/apuntarse-proximo',
				type: 'POST',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', convocaShiftsData.nonce);
				},
				success: function(response) {
					$btn.hide();
					$msg.removeClass('convoca-shifts-error').addClass('convoca-shifts-success').text(response.message).show();
					// si el calendario está en la página, se refresca (el nombre real de la instancia
					// es el que publica calendario.js: antes se buscaba otro que no existe).
					if (window.convocaShiftsCalendarInstance) {
						window.convocaShiftsCalendarInstance.refetchEvents();
					}
				},
				error: function(xhr) {
					$btn.prop('disabled', false).html($btn.data('htmlOriginal'));
					var res = xhr.responseJSON;
					$msg.removeClass('convoca-shifts-success').addClass('convoca-shifts-error').text((res && res.message) ? res.message : '<?php echo esc_js( __( 'No se ha podido apuntar el turno. Inténtalo de nuevo.', 'convoca-shifts' ) ); ?>').show();
				}
			});
		});
	});
	</script>
	<?php
	return ob_get_clean();
}

// Shortcode: [resumen_turnos].
add_shortcode( 'convoca_resumen_turnos', 'Convoca\Shifts\convoca_shifts_resumen_turnos' );
function convoca_shifts_resumen_turnos( $atts = array() ) {
	$atts = shortcode_atts( array( 'semana' => 'this' ), $atts );
	wp_enqueue_style( 'convoca-shifts-style' );
	wp_enqueue_script( 'convoca-shifts-calendario' );

	$week_modifier = $atts['semana'] === 'next' ? 'next week' : 'this week';
	$transient_key = 'convoca_shifts_resumen_turnos_' . ( $atts['semana'] === 'next' ? 'next' : 'this' );
	$resumen       = get_transient( $transient_key );

	if ( false === $resumen ) {
		$start_of_week = wp_date( 'Y-m-d 00:00:00', strtotime( 'monday ' . $week_modifier ) );
		$end_of_week   = wp_date( 'Y-m-d 23:59:59', strtotime( 'sunday ' . $week_modifier ) );

		$args = array(
			'post_type'      => 'centro_turno',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'date_query'     => array(
				array(
					'after'     => $start_of_week,
					'before'    => $end_of_week,
					'inclusive' => true,
				),
			),
			'meta_query'     => array(
				array(
					'key'     => '_estado',
					'value'   => 'cerrado',
					'compare' => '!=',
				),
			),
		);

		$turnos     = get_posts( $args );
		$total      = count( $turnos );
		$sin_cubrir = 0;

		foreach ( $turnos as $turno ) {
			$responsable_id = (int) get_post_meta( $turno->ID, '_id_responsable', true );
			if ( $responsable_id === 0 ) {
				++$sin_cubrir;
			}
		}

		$resumen = array(
			'total'      => $total,
			'sin_cubrir' => $sin_cubrir,
		);

		set_transient( $transient_key, $resumen, HOUR_IN_SECONDS );
	}

	ob_start();
	?>
	<div class="convoca-shifts-resumen-wrapper">
		<div class="convoca-shifts-resumen-turnos">
			<?php if ( $resumen['total'] > 0 ) : ?>
				<?php /* translators: 1: number of uncovered shifts, 2: total number of shifts */ ?>
				📅 <?php echo esc_html( sprintf( __( 'Esta semana hay %1$d turnos sin cubrir de %2$d en total.', 'convoca-shifts' ), $resumen['sin_cubrir'], $resumen['total'] ) ); ?>
			<?php else : ?>
				📅 No hay turnos definidos para esta semana.
			<?php endif; ?>
		</div>

		<?php
		// Mini list of gaps for the current week.
		$start_of_week = wp_date( 'Y-m-d 00:00:00', strtotime( 'monday this week' ) );
		$end_of_week   = wp_date( 'Y-m-d 23:59:59', strtotime( 'sunday this week' ) );
		$gaps          = get_posts(
			array(
				'post_type'      => 'centro_turno',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'date_query'     => array(
					array(
						'after'     => $start_of_week,
						'before'    => $end_of_week,
						'inclusive' => true,
					),
				),
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => '_id_responsable',
						'value'   => 0,
						'compare' => '=',
					),
					array(
						'key'     => '_estado',
						'value'   => 'abierto_disponible',
						'compare' => '=',
					),
				),
				'orderby'        => 'date',
				'order'          => 'ASC',
			)
		);

		if ( ! empty( $gaps ) ) :
			echo '<div class="convoca-shifts-resumen-gaps"><strong>' . esc_html__( 'Huecos libres:', 'convoca-shifts' ) . '</strong> ';
			$gap_strings = array();
			foreach ( $gaps as $gap ) {
				$gap_strings[] = convoca_shifts_fecha_corta( get_post_timestamp( $gap ) ) . ' (' . esc_html( get_the_time( 'H:i', $gap ) ) . ')';
			}
			echo esc_html( implode( ', ', $gap_strings ) );
			echo '</div>';
		endif;
		?>

		<div class="convoca-shifts-proximo-libre">
			<!-- Populated via AJAX on load -->
			Cargando próximo turno...
		</div>

		<?php
		// La suscripción va aquí, con los turnos: es donde el voluntario mira y donde tiene sentido ofrecérsela.
		echo convoca_shifts_caja_suscripcion(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML propio, ya escapado dentro.
		?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Caja de suscripción al calendario de turnos (issue #13).
 *
 * La web prometía exportar a iCal y Google Calendar y no existía: se ofrece la **suscripción**, que es lo
 * que de verdad sirve al voluntario (sus turnos se mantienen al día sin volver a descargar nada). Google
 * Calendar se suscribe a una URL `.ics` igual que cualquier otro calendario, así que la misma dirección vale
 * para los dos.
 *
 * @return string HTML de la caja, o vacío si no hay sesión.
 */
function convoca_shifts_caja_suscripcion(): string {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	$url = \Convoca\Shifts\Ical::url_de( (int) get_current_user_id() );
	if ( '' === $url ) {
		return '';
	}

	$salida  = '<div class="convoca-shifts-suscripcion convoca-box">';
	$salida .= '<h3>' . esc_html__( 'Lleva tus turnos a tu calendario', 'convoca-shifts' ) . '</h3>';
	$salida .= '<p>' . esc_html__( 'Pega esta dirección en tu calendario (en Google Calendar: «Otros calendarios» → «Suscribirse desde una URL») y tus turnos aparecerán y se actualizarán solos cuando haya cambios.', 'convoca-shifts' ) . '</p>';
	$salida .= '<p><code class="convoca-shifts-suscripcion__url">' . esc_html( $url ) . '</code></p>';
	$salida .= '<p><a class="convoca-btn" href="' . esc_url( $url ) . '">' . esc_html__( 'Descargar el calendario (.ics)', 'convoca-shifts' ) . '</a></p>';
	$salida .= '<p class="description">' . esc_html__( 'Esta dirección es personal: no la compartas. Quien la tenga puede ver tus turnos.', 'convoca-shifts' ) . '</p>';
	$salida .= '</div>';

	return $salida;
}

add_shortcode( 'convoca_suscripcion_turnos', 'Convoca\Shifts\convoca_shifts_caja_suscripcion' );

// Shortcode: [proximos_turnos cantidad="5"].
add_shortcode( 'convoca_proximos_turnos', 'Convoca\Shifts\convoca_shifts_proximos_turnos' );
function convoca_shifts_proximos_turnos( $atts ) {
	$atts = shortcode_atts(
		array(
			'cantidad' => 5,
		),
		$atts
	);

	$now  = current_time( 'mysql' );
	$args = array(
		'post_type'      => 'centro_turno',
		'posts_per_page' => $atts['cantidad'],
		'post_status'    => 'publish',
		'orderby'        => 'date',
		'order'          => 'ASC',
		'date_query'     => array(
			array(
				'after' => $now,
			),
		),
		'meta_query'     => array(
			array(
				'key'     => '_estado',
				'value'   => 'cerrado',
				'compare' => '!=',
			),
		),
	);

	$turnos = get_posts( $args );
	if ( empty( $turnos ) ) {
		return '<p>' . __( 'No hay turnos próximos programados.', 'convoca-shifts' ) . '</p>';
	}

	ob_start();
	echo '<ul class="convoca-shifts-proximos-lista">';
	foreach ( $turnos as $turno ) {
		$responsable_id = (int) get_post_meta( $turno->ID, '_id_responsable', true );
		$ts             = get_post_timestamp( $turno );
		$date           = convoca_shifts_fecha_corta( $ts );
		$time           = get_the_time( 'H:i', $turno );

		$responsable_texto = '';
		if ( $responsable_id > 0 ) {
			$user = get_userdata( $responsable_id );
			if ( $user ) {
				$nombre_mostrar = ! empty( $user->first_name ) ? $user->first_name : $user->display_name;
				// El nombre se escapa AQUÍ (viene del usuario) y el HTML de la etiqueta se añade después:
				// antes se imprimía todo con esc_html() y el resultado era el código a la vista
				// (`<span style="color:#d35400...">`), y escaparlo de otra forma habría metido el nombre sin escapar.
				$responsable_texto = ' — 👤 ' . esc_html( $nombre_mostrar );
			}
		} else {
			$responsable_texto = ' — <span style="color:#d35400; font-weight:bold;">⚠️ ' . esc_html__( 'Sin cubrir', 'convoca-shifts' ) . '</span>';
		}

		// La hora sale del post, y un turno creado sin horario queda a las 00:00: decirlo («sin hora
		// fijada») es más útil que un 00:00 que parece una hora y no lo es (medido: 2 de 17 turnos de la demo).
		// El separador va con el texto: «a las 17:00» pero «— sin hora fijada» (si no, sale «a las sin hora fijada»).
		$hora_fin = (string) get_post_meta( $turno->ID, '_hora_fin', true );
		if ( '' === $hora_fin && '00:00' === $time ) {
			$separador  = ' — ';
			$texto_hora = esc_html__( 'sin hora fijada', 'convoca-shifts' );
		} else {
			$separador  = ' ' . esc_html__( 'a las', 'convoca-shifts' ) . ' ';
			$texto_hora = esc_html( $time );
		}

		echo '<li style="margin-bottom:8px; padding-bottom:8px; border-bottom:1px solid #eee;">';
		echo '📅 <strong>' . esc_html( $date ) . '</strong>' . $separador . $texto_hora;
		// wp_kses_post y no esc_html: aquí SÍ hay que interpretar la etiqueta del estado. Lo que
		// viene de fuera (el nombre del responsable) ya va escapado arriba.
		echo wp_kses_post( $responsable_texto );
		echo '</li>';
	}
	echo '</ul>';
	return ob_get_clean();
}

// Clear transient when a turno is saved/assigned.
add_action( 'save_post_centro_turno', 'Convoca\Shifts\convoca_shifts_clear_resumen_transient' );
/**
 * Tira todas las caches del resumen de turnos.
 *
 * **Una sola funcion para las cuatro claves.** El resumen cachea por semana (`_this` y `_next`, segun el atributo
 * `semana`) y el widget tiene la suya. Las invalidaciones borraban `convoca_shifts_resumen_turnos_semana`, la clave
 * que se usaba **antes** de que existiera el atributo: no borraban nada de lo que se lee y el resumen seguia
 * ensenando lo de antes hasta una hora (shifts#24). La clave vieja se sigue borrando porque puede quedar en
 * instalaciones que actualicen.
 *
 * @return void
 */
function convoca_shifts_clear_resumen_transient() {
	foreach ( array( 'this', 'next', 'widget', 'semana' ) as $sufijo ) {
		delete_transient( 'convoca_shifts_resumen_turnos_' . $sufijo );
	}
}

// Clear transient on assign/unassign API calls.
add_action(
	'rest_api_init',
	function () {
		add_action( 'rest_after_insert_centro_turno', 'Convoca\Shifts\convoca_shifts_clear_resumen_transient' );
	}
);
// Since we manually update meta in REST, let's hook onto the update meta action to clear transient.
add_action(
	'updated_post_meta',
	function ( $meta_id, $post_id, $meta_key, $meta_value ) {
		if ( ( '_id_responsable' === $meta_key || '_estado' === $meta_key ) && get_post_type( $post_id ) === 'centro_turno' ) {
			convoca_shifts_clear_resumen_transient();
		}
	},
	10,
	4
);
