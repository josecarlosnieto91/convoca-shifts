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
 * Volunteer lifecycle listeners.
 *
 * Since 2026-09-10 the volunteer approval/revocation UI lives in Convoca
 * Members (Admin_Voluntariado). Convoca Shifts only reacts to lifecycle
 * hooks to keep shift data consistent: release future shifts when a
 * volunteer is revoked, and log approval/revocation activity.
 *
 * @package Convoca\Shifts
 */

namespace Convoca\Shifts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'convoca_voluntario_aprobado', 'Convoca\Shifts\convoca_shifts_on_volunteer_approved', 20, 1 );
add_action( 'convoca_voluntario_revocado', 'Convoca\Shifts\convoca_shifts_on_volunteer_revoked', 10, 1 );

/**
 * React to volunteer approval: log activity.
 *
 * @param int $user_id WP user ID.
 */
function convoca_shifts_on_volunteer_approved( int $user_id ): void {
	if ( function_exists( 'Convoca\Shifts\convoca_shifts_log_activity' ) ) {
		convoca_shifts_log_activity( get_current_user_id(), 0, 'voluntario_aprobado', array( 'voluntario_id' => $user_id ) );
	}
}

/**
 * React to volunteer revocation: release future shifts assigned to the user.
 *
 * @param int $user_id WP user ID.
 */
function convoca_shifts_on_volunteer_revoked( int $user_id ): void {
	if ( $user_id <= 0 ) {
		return;
	}

	// Unassign future shifts.
	$futuros = get_posts(
		array(
			'post_type'      => 'centro_turno',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => '_id_responsable',
					'value'   => $user_id,
					'compare' => '=',
				),
			),
			'date_query'     => array(
				array(
					'after'     => current_time( 'mysql' ),
					'inclusive' => true,
				),
			),
		)
	);

	foreach ( $futuros as $turno ) {
		update_post_meta( $turno->ID, '_id_responsable', 0 );
		update_post_meta( $turno->ID, '_estado', 'abierto_disponible' );
		wp_update_post(
			array(
				'ID'          => $turno->ID,
				'post_title'  => '🟡 Pendiente',
				'post_status' => 'publish',
				'edit_date'   => true,
			)
		);
		wp_publish_post( $turno->ID );
	}

	// Log activity.
	if ( function_exists( 'Convoca\Shifts\convoca_shifts_log_activity' ) ) {
		convoca_shifts_log_activity( get_current_user_id(), 0, 'voluntario_revocado', array( 'voluntario_id' => $user_id ) );
	}
}
