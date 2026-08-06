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

namespace Convoca\Shifts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function convoca_shifts_schedule_cron() {
	if ( ! wp_next_scheduled( 'convoca_shifts_hourly_event' ) ) {
		wp_schedule_event( time(), 'hourly', 'convoca_shifts_hourly_event' );
	}
	if ( ! wp_next_scheduled( 'convoca_shifts_daily_event' ) ) {
		wp_schedule_event( time(), 'daily', 'convoca_shifts_daily_event' );
	}
}

function convoca_shifts_clear_cron() {
	$hourly_timestamp = wp_next_scheduled( 'convoca_shifts_hourly_event' );
	if ( $hourly_timestamp ) {
		wp_unschedule_event( $hourly_timestamp, 'convoca_shifts_hourly_event' );
	}
	$daily_timestamp = wp_next_scheduled( 'convoca_shifts_daily_event' );
	if ( $daily_timestamp ) {
		wp_unschedule_event( $daily_timestamp, 'convoca_shifts_daily_event' );
	}
}

add_action( 'convoca_shifts_hourly_event', 'Convoca\Shifts\convoca_shifts_send_reminders' );
add_action( 'convoca_shifts_daily_event', 'Convoca\Shifts\convoca_shifts_cleanup_old_meta' );

function convoca_shifts_cleanup_old_meta() {
	global $wpdb;
	$two_days_ago = wp_date( 'Y-m-d H:i:s', time() - ( 2 * DAY_IN_SECONDS ) );

	// Delete _convoca_shifts_reminder_sent for old posts to keep db clean.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE pm FROM {$wpdb->postmeta} pm
         JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE pm.meta_key = '_convoca_shifts_reminder_sent'
           AND p.post_date < %s",
			$two_days_ago
		)
	);
}

function convoca_shifts_send_reminders() {
	if ( ! \Convoca\Core\Utils::acquire_lock( 'convoca_shifts_reminder_cron', 120 ) ) {
		return;
	}
	try {
		// Find turnos starting in the next 2 hours.
		$now          = time();
		$in_two_hours = $now + ( 2 * HOUR_IN_SECONDS );

		$args = array(
			'post_type'      => 'centro_turno',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'date_query'     => array(
				array(
					'after'     => wp_date( 'Y-m-d H:i:s', $now ),
					'before'    => wp_date( 'Y-m-d H:i:s', $in_two_hours ),
					'inclusive' => true,
				),
			),
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => '_id_responsable',
					'value'   => 0,
					'compare' => '>',
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => '_estado',
						'value'   => 'abierto_disponible',
						'compare' => '=',
					),
					array(
						'key'     => '_estado',
						'value'   => 'abierto_ocupado',
						'compare' => '=',
					),
				),
				array(
					'key'     => '_estado_real',
					'value'   => array( 'realizado', 'no_asistio' ),
					'compare' => 'NOT IN',
				),
			),
		);

		$turnos = new \WP_Query( $args );

		if ( $turnos->have_posts() ) {
			while ( $turnos->have_posts() ) {
				$turnos->the_post();
				$post_id = get_the_ID();

				// Avoid sending multiple reminders for the same turno.
				if ( get_post_meta( $post_id, '_convoca_shifts_reminder_sent', true ) ) {
					continue;
				}

				// Re-verify state: turno might have been cancelled or reassigned after the query.
				$current_estado = get_post_meta( $post_id, '_estado', true );
				if ( $current_estado === 'cerrado' || empty( $current_estado ) ) {
					continue;
				}
				$current_responsable = (int) get_post_meta( $post_id, '_id_responsable', true );
				if ( $current_responsable <= 0 ) {
					continue;
				}

				$user = get_userdata( $current_responsable );

				if ( $user ) {
					$subject = __( 'Recordatorio de turno en Centro Social', 'convoca-shifts' );
					/* translators: 1: first name, 2: turn title, 3: start time */
					$reminder_text = __( 'Hola %1$s,\n\nTe recordamos que tienes un turno asignado para abrir el centro pronto:\n\nTurno: %2$s\nHora de inicio: %3$s\n\n¡Gracias por tu voluntariado!', 'convoca-shifts' );
					$message       = sprintf(
						$reminder_text,
						$user->first_name,
						get_the_title(),
						get_the_date( 'Y-m-d H:i' )
					);

					wp_mail( $user->user_email, $subject, $message );

					// Mark as sent.
					update_post_meta( $post_id, '_convoca_shifts_reminder_sent', 1 );
				}
			}
			wp_reset_postdata();
		}
	} finally {
		\Convoca\Core\Utils::release_lock( 'convoca_shifts_reminder_cron' );
	}
}
