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
	wp_register_style( 'convoca-shifts-style', CONVOCA_SHIFTS_URL . 'assets/css/estilo.css', array(), CONVOCA_SHIFTS_VERSION );
	wp_enqueue_style( 'dashicons' ); // Needed for buttons/icons.
	wp_register_script( 'fullcalendar-core', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js', array(), '6.1.15', true );
	wp_register_script( 'convoca-shifts-calendario', CONVOCA_SHIFTS_URL . 'assets/js/calendario.js', array( 'fullcalendar-core', 'jquery' ), CONVOCA_SHIFTS_VERSION, true );

	wp_localize_script(
		'convoca-shifts-calendario',
		'convocaShiftsData',
		array(
			'restUrl'        => esc_url_raw( rest_url( 'centro/v1/turnos' ) ),
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
		$access_url = get_option( 'convoca_shifts_access_page_url', '#' );
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
			if (cstData.confirmSignup && !confirm('<?php echo esc_js( __( '¿Te apuntas a este turno? Serás responsable de abrir el centro.', 'convoca-shifts' ) ); ?>')) {
				return;
			}

			var $btn = $(this);
			var $msg = $('#convoca-shifts-proximo-msg');
			
			$btn.prop('disabled', true).text('Buscando turno...');
			
			$.ajax({
				url: cstData.restUrl + '/apuntarse-proximo',
				type: 'POST',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', cstData.nonce);
				},
				success: function(response) {
					$btn.hide();
					$msg.removeClass('convoca-shifts-error').addClass('convoca-shifts-success').text(response.message).show();
					// if calendar is on page, refetch.
					if (window.cstCalendarInstance) {
						window.cstCalendarInstance.refetchEvents();
					}
				},
				error: function(xhr) {
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-calendar-alt"></span> Me apunto al siguiente turno libre');
					var res = xhr.responseJSON;
					$msg.removeClass('convoca-shifts-success').addClass('convoca-shifts-error').text((res && res.message) ? res.message : 'Error.').show();
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
	</div>
	<?php
	return ob_get_clean();
}

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
				$nombre_mostrar    = ! empty( $user->first_name ) ? $user->first_name : $user->display_name;
				$responsable_texto = ' — 👤 ' . $nombre_mostrar;
			}
		} else {
			$responsable_texto = ' — <span style="color:#d35400; font-weight:bold;">⚠️ ' . __( 'Sin cubrir', 'convoca-shifts' ) . '</span>';
		}

		echo '<li style="margin-bottom:8px; padding-bottom:8px; border-bottom:1px solid #eee;">';
		echo '📅 <strong>' . esc_html( $date ) . '</strong> a las ' . esc_html( $time );
		echo esc_html( $responsable_texto );
		echo '</li>';
	}
	echo '</ul>';
	return ob_get_clean();
}

// Clear transient when a turno is saved/assigned.
add_action( 'save_post_centro_turno', 'Convoca\Shifts\convoca_shifts_clear_resumen_transient' );
function convoca_shifts_clear_resumen_transient() {
	delete_transient( 'convoca_shifts_resumen_turnos_semana' );
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
			delete_transient( 'convoca_shifts_resumen_turnos_semana' );
		}
	},
	10,
	4
);
