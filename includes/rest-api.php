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
 * Convoca Shifts - rest-api
 *
 * @package Convoca_Shifts
 */

namespace Convoca\Shifts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'Convoca\Shifts\convoca_shifts_register_rest_routes' );

function convoca_shifts_register_rest_routes() {
	register_rest_route(
		'centro/v1',
		'/turnos',
		array(
			'methods'             => 'GET',
			'callback' => 'Convoca\Shifts\convoca_shifts_rest_get_turnos',
			'permission_callback' => '__return_true', // Publicly readable.
		)
	);

	register_rest_route(
		'centro/v1',
		'/turnos/(?P<id>\d+)/apuntarse',
		array(
			'methods'             => 'POST',
			'callback' => 'Convoca\Shifts\convoca_shifts_rest_apuntarse_turno',
			'permission_callback' => function () {
				if ( is_user_logged_in() && ( current_user_can( 'gestionar_mis_turnos' ) || current_user_can( 'manage_options' ) ) ) {
					return true;
				}
				return new \WP_Error( 'rest_cannot_access', __( 'Lo siento, no tienes permisos para realizar esta acción.', 'convoca-shifts' ), array( 'status' => 403 ) );
			},
		)
	);

	register_rest_route(
		'centro/v1',
		'/turnos/(?P<id>\d+)/desapuntarse',
		array(
			'methods'             => 'POST',
			'callback' => 'Convoca\Shifts\convoca_shifts_rest_desapuntarse_turno',
			'permission_callback' => function () {
				if ( is_user_logged_in() && ( current_user_can( 'gestionar_mis_turnos' ) || current_user_can( 'manage_options' ) ) ) {
					return true;
				}
				return new \WP_Error( 'rest_cannot_access', __( 'Lo siento, no tienes permisos para realizar esta acción.', 'convoca-shifts' ), array( 'status' => 403 ) );
			},
		)
	);

	register_rest_route(
		'centro/v1',
		'/turnos/apuntarse-proximo',
		array(
			'methods'             => 'POST',
			'callback' => 'Convoca\Shifts\convoca_shifts_rest_apuntarse_proximo',
			'permission_callback' => function () {
				if ( is_user_logged_in() && ( current_user_can( 'gestionar_mis_turnos' ) || current_user_can( 'manage_options' ) ) ) {
					return true;
				}
				return new \WP_Error( 'rest_cannot_access', __( 'Lo siento, no tienes permisos para realizar esta acción.', 'convoca-shifts' ), array( 'status' => 403 ) );
			},
		)
	);

	register_rest_route(
		'centro/v1',
		'/turnos/proximo-libre',
		array(
			'methods'             => 'GET',
			'callback' => 'Convoca\Shifts\convoca_shifts_rest_get_proximo_libre',
			'permission_callback' => '__return_true', // Public.
		)
	);

	register_rest_route(
		'centro/v1',
		'/turnos/crear',
		array(
			'methods'             => 'POST',
			'callback' => 'Convoca\Shifts\convoca_shifts_rest_crear_turno',
			'permission_callback' => function () {
				return is_user_logged_in() && ( current_user_can( 'gestionar_mis_turnos' ) || current_user_can( 'manage_options' ) );
			},
		)
	);
}

function convoca_shifts_rest_get_turnos( WP_REST_Request $request ) {
	if ( class_exists( '\\Convoca\\Core\\Utils' ) && ! \Convoca\Core\Utils::check_rate_limit( 'convoca_shifts_get_turnos', 30, 60 ) ) {
		return new \WP_Error( 'rest_rate_limit', 'Demasiadas peticiones. Inténtalo de nuevo en un minuto.', array( 'status' => 429 ) );
	}
	$start = $request->get_param( 'start' ); // Format: YYYY-MM-DD...
	$end   = $request->get_param( 'end' );

	$args = array(
		'post_type'      => 'centro_turno',
		'posts_per_page' => 200,
		'no_found_rows'  => true,
		'post_status'    => 'publish',
	);

	// If start and end are provided, filter by _fecha_inicio meta (fecha real del turno).
	if ( $start && $end ) {
		try {
			$tz       = new DateTimeZone( wp_timezone_string() );
			$dt_start = new \DateTime( $start );
			$dt_start->setTimezone( $tz );
			$dt_end = new \DateTime( $end );
			$dt_end->setTimezone( $tz );

			$args['meta_query'] = array(
				array(
					'key'     => '_fecha_inicio',
					'value'   => array( $dt_start->format( 'Y-m-d H:i:s' ), $dt_end->format( 'Y-m-d H:i:s' ) ),
					'compare' => 'BETWEEN',
					'type'    => 'DATETIME',
				),
			);
		} catch ( Exception $e ) {
			$args['meta_query'] = array(
				array(
					'key'     => '_fecha_inicio',
					'value'   => array( $start, $end ),
					'compare' => 'BETWEEN',
					'type'    => 'DATETIME',
				),
			);
		}
	}

	$turnos = new \WP_Query( $args );
	$events = array();

	if ( $turnos->have_posts() ) {
		while ( $turnos->have_posts() ) {
			$turnos->the_post();
			$post_id        = get_the_ID();
			$estado         = get_post_meta( $post_id, '_estado', true );
			$hora_fin       = get_post_meta( $post_id, '_hora_fin', true );
			$responsable_id = (int) get_post_meta( $post_id, '_id_responsable', true );
			$necesita_apoyo = get_post_meta( $post_id, '_necesita_apoyo', true );

			$title = get_the_title();
			$color = '#f1c40f'; // Default yellow (Pendiente).

			if ( $estado === 'cerrado' ) {
				$color = '#e74c3c'; // Red.
				$title = '🔴 Centro Cerrado';
			} elseif ( $estado === 'abierto_ocupado' ) {
				$color          = '#3498db'; // Blue.
				$actividad_obj  = wp_get_post_terms( $post_id, 'convoca_shifts_actividad' );
				$actividad_name = ( ! empty( $actividad_obj ) && ! is_wp_error( $actividad_obj ) ) ? $actividad_obj[0]->name : 'Ocupado';
				$title          = '🔵 ' . $actividad_name;
			} elseif ( $estado === 'abierto_disponible' ) {
				if ( $responsable_id > 0 ) {
					$color = '#2ecc71'; // Green (Cubierto).
					$user  = get_userdata( $responsable_id );
					if ( $user ) {
						$nombre_mostrar = ! empty( $user->first_name ) ? $user->first_name : $user->display_name;
						$title          = '🟢 Cubierto - ' . $nombre_mostrar;
					}
				} else {
					$title = '🟡 Pendiente';
				}
			}

			$responsable_nombre = '';
			if ( $responsable_id > 0 && $estado !== 'abierto_ocupado' ) {
				$user = get_userdata( $responsable_id );
				if ( $user ) {
					$responsable_nombre = ! empty( $user->first_name ) ? $user->first_name : $user->display_name;
				}
			}

			$fecha_inicio = get_post_meta( $post_id, '_fecha_inicio', true );
			$event_start  = get_the_date( 'Y-m-d\TH:i:s' ); // fallback.
			$event_end    = $event_start;

			if ( $fecha_inicio ) {
				try {
					$tz          = new DateTimeZone( wp_timezone_string() );
					$dt          = new \DateTime( $fecha_inicio, $tz );
					$event_start = $dt->format( 'Y-m-d\TH:i:s' );

					if ( $hora_fin ) {
						$event_end = $dt->format( 'Y-m-d' ) . 'T' . $hora_fin . ':00';
					} else {
						$dt->modify( '+2 hours' );
						$event_end = $dt->format( 'Y-m-d\TH:i:s' );
					}
				} catch ( Exception $e ) {
					$event_start = get_the_date( 'Y-m-d\TH:i:s' );
				}
			}

			$events[] = array(
				'id'            => $post_id,
				'title'         => html_entity_decode( $title ),
				'start'         => $event_start,
				'end'           => $event_end,
				'color'         => $color,
				'extendedProps' => array(
					'estado'             => $estado,
					'responsable_id'     => $responsable_id,
					'responsable_nombre' => $responsable_nombre,
					'notas'              => get_post_meta( $post_id, '_notas', true ),
					'necesita_apoyo'     => (int) get_post_meta( $post_id, '_necesita_apoyo', true ),
					'actividad'          => ( function ( $pid ) {
						$terms = wp_get_post_terms( $pid, 'convoca_shifts_actividad' );
						return ( ! empty( $terms ) && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
					} )( $post_id ),
					'actividad_url'      => ( function ( $pid ) {
						$terms = wp_get_post_terms( $pid, 'convoca_shifts_actividad' );
						if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
							return get_term_meta( $terms[0]->term_id, 'convoca_shifts_url_info', true );
						}
						return '';
					} )( $post_id ),
					'monitor'            => ( function ( $pid ) {
						$monitor_id = (int) get_post_meta( $pid, '_monitor', true );
						if ( $monitor_id > 0 ) {
							$mu = get_userdata( $monitor_id );
							return $mu ? $mu->display_name : '';
						}
						return '';
					} )( $post_id ),
				),
			);
		}
		wp_reset_postdata();
	}

	return rest_ensure_response( $events );
}

function convoca_shifts_rest_apuntarse_turno( WP_REST_Request $request ) {
	if ( class_exists( '\\Convoca\\Core\\Utils' ) && ! \Convoca\Core\Utils::check_rate_limit( 'convoca_shifts_apuntarse', 10, 3600 ) ) {
		return new \WP_Error( 'rest_rate_limit', 'Demasiados intentos. Inténtalo de nuevo en una hora.', array( 'status' => 429 ) );
	}
	$post_id = $request->get_param( 'id' );
	$user_id = get_current_user_id();

	$post = get_post( $post_id );
	if ( ! $post || $post->post_type !== 'centro_turno' ) {
		return new \WP_Error( 'not_found', 'Turno no encontrado', array( 'status' => 404 ) );
	}

	$estado = get_post_meta( $post_id, '_estado', true );

	if ( $estado !== 'abierto_disponible' ) {
		return new \WP_Error( 'not_available', 'El turno ya está ocupado. Por favor, elige otro horario.', array( 'status' => 400 ) );
	}

	// Prevención: No permitir apuntarse a turnos pasados (usar _fecha_inicio, no post_date).
	$fecha_inicio_turno = get_post_meta( $post_id, '_fecha_inicio', true );
	if ( $fecha_inicio_turno ) {
		$fecha_inicio_ts = strtotime( $fecha_inicio_turno );
		if ( $fecha_inicio_ts < time() ) {
			return new \WP_Error( 'past_turno', 'No puedes apuntarte a un turno que ya ha pasado.', array( 'status' => 400 ) );
		}
	}

	// Transactional block to prevent race conditions on overlap and assignment.
	global $wpdb;
	$wpdb->query( 'START TRANSACTION' );

	// Lock the shift record to prevent other concurrent assignments.
	$current_responsable = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_id_responsable' FOR UPDATE",
			$post_id
		)
	);

	if ( $current_responsable !== null && (int) $current_responsable !== 0 ) {
		$wpdb->query( 'ROLLBACK' );
		return new \WP_Error( 'ya_cubierto', 'Alguien se ha adelantado 😅 Este turno ya está cubierto.', array( 'status' => 409 ) );
	}

	// Lock user's existing shifts to prevent concurrent overlaps.
	$hora_fin        = get_post_meta( $post_id, '_hora_fin', true );
	$user_shift_lock = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_id_responsable'
         INNER JOIN {$wpdb->postmeta} pmi ON p.ID = pmi.post_id AND pmi.meta_key = '_fecha_inicio'
         INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_hora_fin'
         WHERE p.post_type = 'centro_turno' AND p.post_status = 'publish'
         AND pm.meta_value = %d
         AND pmi.meta_value < %s
         AND pm2.meta_value > %s
         AND p.ID != %d
         FOR UPDATE",
			$user_id,
			$fecha_inicio_turno,
			$hora_fin,
			$post_id
		)
	);

	// Check for overlap INSIDE the transaction (with FOR UPDATE to lock conflicting rows).
	$conflict_id = convoca_shifts_check_user_overlap( $user_id, $fecha_inicio_turno, $hora_fin, $post_id, true );
	if ( $conflict_id ) {
		$wpdb->query( 'ROLLBACK' );
		if ( function_exists('Convoca\Shifts\convoca_shifts_log_activity') ) {
			convoca_shifts_log_activity( $user_id, $post_id, 'conflicto_horario_detectado', array( 'conflict_id' => $conflict_id ) );
		}
		return new \WP_Error( 'conflicto_horario', 'Ya tienes un turno asignado que se solapa con este horario.', array( 'status' => 400 ) );
	}

	// Atomic assignment: UPDATE si existe, INSERT si no existe.
	// La transacción con FOR UPDATE ya nos protege de condiciones de carrera.
	$rows = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->postmeta} SET meta_value = %d
         WHERE post_id = %d AND meta_key = '_id_responsable'
           AND meta_value = '0'",
			$user_id,
			$post_id
		)
	);

	if ( ! $rows || $rows === 0 ) {
		// Si no existía la meta o no se pudo actualizar, intentar INSERT.
		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
             SELECT %d, '_id_responsable', %d
             WHERE NOT EXISTS (
               SELECT 1 FROM {$wpdb->postmeta}
               WHERE post_id = %d AND meta_key = '_id_responsable'
             )",
				$post_id,
				$user_id,
				$post_id
			)
		);

		if ( ! $inserted ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'ya_cubierto', 'Alguien se ha adelantado 😅 Este turno ya está cubierto.', array( 'status' => 409 ) );
		}
	}

	// Sync internal state meta.
	update_post_meta( $post_id, '_estado', 'abierto_disponible' );

	// Commit early to release locks before heavy operations like email.
	$wpdb->query( 'COMMIT' );

	// Update Post Title (Non-critical if it fails after COMMIT, but we want it consistent).
	$user_info = get_userdata( $user_id );
	$nombre    = ! empty( $user_info->first_name ) ? $user_info->first_name : $user_info->display_name;
	wp_update_post(
		array(
			'ID'          => $post_id,
			'post_title'  => '🟢 Cubierto - ' . $nombre,
			'post_status' => 'publish',
			'edit_date'   => true,
		)
	);
	wp_publish_post( $post_id );

	$message = 'Turno asignado con éxito.';

	// Log activity.
	if ( function_exists('Convoca\Shifts\convoca_shifts_log_activity') ) {
		convoca_shifts_log_activity( $user_id, $post_id, 'turno_cubierto' );
	}

	// Notify admin.
	$admin_email = get_option( 'admin_email' );
	$user_info   = get_userdata( $user_id );
	if ( $fecha_inicio_turno ) {
		try {
			$tz               = new DateTimeZone( wp_timezone_string() );
			$dt               = new \DateTime( $fecha_inicio_turno, $tz );
			$fecha_para_email = $dt->format( 'd/m/Y H:i' );
		} catch ( Exception $e ) {
			$fecha_para_email = $fecha_inicio_turno;
		}
	} else {
		$fecha_para_email = $post->post_date;
	}
	wp_mail( $admin_email, 'Turno cubierto: ' . $post->post_title, 'El voluntario ' . $user_info->display_name . ' ha cubierto el turno del ' . $fecha_para_email . '.' );

	return rest_ensure_response(
		array(
			'success' => true,
			'message' => $message,
		)
	);
}

function convoca_shifts_rest_desapuntarse_turno( WP_REST_Request $request ) {
	if ( class_exists( '\\Convoca\\Core\\Utils' ) && ! \Convoca\Core\Utils::check_rate_limit( 'convoca_shifts_desapuntarse', 10, 3600 ) ) {
		return new \WP_Error( 'rest_rate_limit', 'Demasiados intentos. Inténtalo de nuevo en una hora.', array( 'status' => 429 ) );
	}
	$post_id = $request->get_param( 'id' );
	$user_id = get_current_user_id();

	$post = get_post( $post_id );
	if ( ! $post || $post->post_type !== 'centro_turno' ) {
		return new \WP_Error( 'not_found', 'Turno no encontrado', array( 'status' => 404 ) );
	}

	$estado = get_post_meta( $post_id, '_estado', true );
	if ( $estado === 'cerrado' ) {
		return new \WP_Error( 'not_available', 'El turno está cerrado.', array( 'status' => 400 ) );
	}

	// Atomic release with transaction to prevent race conditions.
	global $wpdb;
	$wpdb->query( 'START TRANSACTION' );

	// Lock the responsible meta row to prevent concurrent assignment.
	$current_responsable = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_id_responsable' FOR UPDATE",
			$post_id
		)
	);

	if ( (int) $current_responsable !== $user_id && ! current_user_can( 'manage_options' ) && ! current_user_can( 'convoca_shifts_manage_turnos' ) ) {
		$wpdb->query( 'ROLLBACK' );
		return new \WP_Error( 'not_yours', 'No eres el responsable de este turno.', array( 'status' => 400 ) );
	}

	// Release the turn.
	update_post_meta( $post_id, '_id_responsable', 0 );
	update_post_meta( $post_id, '_estado', 'abierto_disponible' );

	// Restore title to Pendiente.
	wp_update_post(
		array(
			'ID'          => $post_id,
			'post_title'  => '🟡 Pendiente',
			'post_status' => 'publish',
			'edit_date'   => true,
		)
	);
	wp_publish_post( $post_id );

	$wpdb->query( 'COMMIT' );

	// Log activity.
	if ( function_exists('Convoca\Shifts\convoca_shifts_log_activity') ) {
		convoca_shifts_log_activity( $user_id, $post_id, 'turno_liberado' );
	}

	return rest_ensure_response(
		array(
			'success' => true,
			'message' => __( 'Turno liberado con éxito.', 'convoca-shifts' ),
		)
	);
}

function convoca_shifts_rest_apuntarse_proximo( WP_REST_Request $request ) {
	if ( class_exists( '\\Convoca\\Core\\Utils' ) && ! \Convoca\Core\Utils::check_rate_limit( 'convoca_shifts_apuntarse_proximo', 10, 3600 ) ) {
		return new \WP_Error( 'rest_rate_limit', 'Demasiados intentos. Inténtalo de nuevo en una hora.', array( 'status' => 429 ) );
	}
	$user_id = get_current_user_id();
	$now     = wp_date( 'Y-m-d H:i:s' );

	// Start transaction BEFORE any selection to prevent race conditions.
	global $wpdb;
	$wpdb->query( 'START TRANSACTION' );

	// Single atomic select with FOR UPDATE to lock the row.
	$turno_id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
         JOIN {$wpdb->postmeta} pm_fecha ON p.ID = pm_fecha.post_id AND pm_fecha.meta_key = '_fecha_inicio'
         LEFT JOIN {$wpdb->postmeta} pm_estado ON p.ID = pm_estado.post_id AND pm_estado.meta_key = '_estado'
         LEFT JOIN {$wpdb->postmeta} pm_responsable ON p.ID = pm_responsable.post_id AND pm_responsable.meta_key = '_id_responsable'
         WHERE p.post_type = 'centro_turno'
           AND p.post_status = 'publish'
           AND pm_fecha.meta_value > %s
           AND (pm_estado.meta_value = 'abierto_disponible' OR pm_estado.meta_value IS NULL)
           AND (CAST(pm_responsable.meta_value AS UNSIGNED) = 0 OR pm_responsable.meta_value IS NULL)
         ORDER BY pm_fecha.meta_value ASC
         LIMIT 1
         FOR UPDATE",
			$now
		)
	);

	if ( ! $turno_id ) {
		$wpdb->query( 'ROLLBACK' );
		return new \WP_Error( 'no_disponible', 'No hay turnos disponibles.', array( 'status' => 404 ) );
	}

	// Lock user's future shifts to prevent overlap race conditions.
	$wpdb->query(
		$wpdb->prepare(
			"SELECT pm.post_id FROM {$wpdb->postmeta} pm
         JOIN {$wpdb->postmeta} pm_fecha ON pm.post_id = pm_fecha.post_id AND pm_fecha.meta_key = '_fecha_inicio'
         JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE pm.meta_key = '_id_responsable'
           AND pm.meta_value = %d
           AND pm_fecha.meta_value > %s
           AND p.post_type = 'centro_turno'
           AND p.post_status = 'publish'
         FOR UPDATE",
			$user_id,
			$now
		)
	);

	// Check if user already has a shift that overlaps.
	$hora_fin_candidate     = get_post_meta( $turno_id, '_hora_fin', true );
	$fecha_inicio_candidate = get_post_meta( $turno_id, '_fecha_inicio', true );
	$conflict_id            = convoca_shifts_check_user_overlap( $user_id, $fecha_inicio_candidate, $hora_fin_candidate );

	if ( $conflict_id ) {
		$wpdb->query( 'ROLLBACK' );
		if ( function_exists('Convoca\Shifts\convoca_shifts_log_activity') ) {
			convoca_shifts_log_activity(
				$user_id,
				$turno_id,
				'conflicto_horario_detectado',
				array(
					'automatico'  => true,
					'conflict_id' => $conflict_id,
				)
			);
		}
		$conflict_title = get_the_title( $conflict_id );
		return new \WP_Error( 'conflicto_horario', 'Ya tienes un turno asignado que se solapa con este (' . $conflict_title . ').', array( 'status' => 400 ) );
	}

	// Atomic assignment: UPDATE si existe, INSERT si no existe.
	$rows = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->postmeta} SET meta_value = %d
         WHERE post_id = %d AND meta_key = '_id_responsable' AND meta_value = '0'",
			$user_id,
			$turno_id
		)
	);

	if ( ! $rows || $rows === 0 ) {
		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
             SELECT %d, '_id_responsable', %d
             WHERE NOT EXISTS (
               SELECT 1 FROM {$wpdb->postmeta}
               WHERE post_id = %d AND meta_key = '_id_responsable'
             )",
				$turno_id,
				$user_id,
				$turno_id
			)
		);

		if ( ! $inserted ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'ya_cubierto', 'Alguien se ha adelantado 😅', array( 'status' => 409 ) );
		}
	}

	// Update state.
	update_post_meta( $turno_id, '_estado', 'abierto_disponible' );

	// Commit.
	$wpdb->query( 'COMMIT' );

	// Get turn details.
	$post_id     = $turno_id;
	$turno_post  = get_post( $post_id );
	$user_info   = get_userdata( $user_id );
	$nombre      = ! empty( $user_info->first_name ) ? $user_info->first_name : $user_info->display_name;
	$title       = $turno_post->post_title;
	$fecha_turno = get_post_meta( $turno_id, '_fecha_inicio', true );
	$date        = $fecha_turno ? wp_date( 'd/m/Y', strtotime( $fecha_turno ) ) . ' ' . __( 'a las', 'convoca-shifts' ) . ' ' . wp_date( 'H:i', strtotime( $fecha_turno ) ) : $turno_post->post_date;
	wp_update_post(
		array(
			'ID'          => $post_id,
			'post_title'  => '🟢 Cubierto - ' . $nombre,
			'post_status' => 'publish',
			'edit_date'   => true,
		)
	);
	wp_publish_post( $post_id );

	// Log activity.
	if ( function_exists('Convoca\Shifts\convoca_shifts_log_activity') ) {
		convoca_shifts_log_activity( $user_id, $post_id, 'turno_cubierto', array( 'automatico' => true ) );
	}

	wp_reset_postdata();

	return rest_ensure_response(
		array(
			'success' => true,
			'message' => sprintf( __( '¡Te has apuntado al turno del %s!', 'convoca-shifts' ), $date ),
		)
	);
}

function convoca_shifts_rest_get_proximo_libre( WP_REST_Request $request ) {
	if ( class_exists( '\\Convoca\\Core\\Utils' ) && ! \Convoca\Core\Utils::check_rate_limit( 'convoca_shifts_proximo_libre', 30, 60 ) ) {
		return new \WP_Error( 'rest_rate_limit', 'Demasiadas peticiones. Inténtalo de nuevo en un minuto.', array( 'status' => 429 ) );
	}
	$now = wp_date( 'Y-m-d H:i:s' );

	$args = array(
		'post_type'      => 'centro_turno',
		'posts_per_page' => 1,
		'post_status'    => 'publish',
		'orderby'        => 'meta_value',
		'meta_key'       => '_fecha_inicio',
		'order'          => 'ASC',
		'meta_query'     => array(
			'relation' => 'AND',
			array(
				'key'     => '_fecha_inicio',
				'value'   => $now,
				'compare' => '>',
				'type'    => 'DATETIME',
			),
			array(
				'key'     => '_estado',
				'value'   => 'abierto_disponible',
				'compare' => '=',
			),
			array(
				'relation' => 'OR',
				array(
					'key'     => '_id_responsable',
					'value'   => 0,
					'type'    => 'NUMERIC',
					'compare' => '=',
				),
				array(
					'key'     => '_id_responsable',
					'compare' => 'NOT EXISTS',
				),
			),
		),
	);

	$turnos = new \WP_Query( $args );
	if ( $turnos->have_posts() ) {
		$turnos->the_post();

		$fecha_inicio = get_post_meta( get_the_ID(), '_fecha_inicio', true );
		if ( $fecha_inicio ) {
			try {
				$tz    = new DateTimeZone( wp_timezone_string() );
				$dt    = new \DateTime( $fecha_inicio, $tz );
				$fecha = $dt->format( 'd/m/Y H:i' );
			} catch ( Exception $e ) {
				$fecha = $fecha_inicio;
			}
		} else {
			$fecha = convoca_shifts_fecha_legible( get_post_timestamp() );
		}
		wp_reset_postdata();

		return rest_ensure_response(
			array(
				'encontrado' => true,
				'texto'      => 'Próximo turno sin cubrir: ' . $fecha,
			)
		);
	}

	return rest_ensure_response(
		array(
			'encontrado' => false,
			'texto'      => '¡Todos los turnos están cubiertos! 🎉',
		)
	);
}

function convoca_shifts_rest_crear_turno( WP_REST_Request $request ) {
	if ( class_exists( '\\Convoca\\Core\\Utils' ) && ! \Convoca\Core\Utils::check_rate_limit( 'convoca_shifts_crear_turno', 10, 3600 ) ) {
		return new \WP_Error( 'rest_rate_limit', 'Demasiados intentos. Inténtalo de nuevo en una hora.', array( 'status' => 429 ) );
	}
	$date    = sanitize_text_field( $request->get_param( 'date' ) );
	$h_start = sanitize_text_field( $request->get_param( 'h_start' ) );
	$h_end   = sanitize_text_field( $request->get_param( 'h_end' ) );
	$estado  = sanitize_text_field( $request->get_param( 'estado' ) );

	// Safety check: Only admins can create "Ocupado" or "Cerrado".
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'convoca_shifts_manage_turnos' ) ) {
		$estado = 'abierto_disponible';

		// Safety check: Don't allow creating past turns for non-admins.
		$fecha_actual    = wp_date( 'Y-m-d' );
		$ahora_mysql     = wp_date( 'Y-m-d H:i:s' );
		$requested_start = $date . ' ' . $h_start . ':00';

		if ( $date < $fecha_actual || ( $date === $fecha_actual && $requested_start < $ahora_mysql ) ) {
			return new \WP_Error( 'past_date', 'No puedes crear un turno en el pasado.', array( 'status' => 400 ) );
		}
	}

	$apoyo_raw = $request->get_param( 'apoyo' );
	$apoyo     = ( $apoyo_raw === 'true' || $apoyo_raw === true || $apoyo_raw === 1 || $apoyo_raw === '1' );

	$post_id = convoca_shifts_insert_turno(
		array(
			'date'           => $date,
			'h_start'        => $h_start,
			'h_end'          => $h_end,
			'estado'         => $estado,
			'necesita_apoyo' => $apoyo,
		)
	);

	if ( is_wp_error( $post_id ) ) {
		return new \WP_Error( 'create_failed', 'No se pudo crear el turno.', array( 'status' => 500 ) );
	}

	// Log activity.
	if ( function_exists('Convoca\Shifts\convoca_shifts_log_activity') ) {
		convoca_shifts_log_activity( get_current_user_id(), $post_id, 'turno_creado', array( 'origen' => 'frontend' ) );
	}

	return rest_ensure_response(
		array(
			'success' => true,
			'message' => __( 'Turno creado correctamente.', 'convoca-shifts' ),
			'id'      => $post_id,
		)
	);
}

/**
 * Format date helper for apuntarse_proximo response.
 */
function apuntarse_proximo_format_date( $fecha_turno, $turno_post ): string {
	if ( $fecha_turno ) {
		try {
			$tz = new DateTimeZone( wp_timezone_string() );
			$dt = new \DateTime( $fecha_turno, $tz );
			return $dt->format( 'd/m/Y' ) . ' ' . __( 'a las', 'convoca-shifts' ) . ' ' . $dt->format( 'H:i' );
		} catch ( Exception $e ) {
			return $fecha_turno;
		}
	}
	return $turno_post->post_date;
}
