<?php
/**
 * Uninstall handler for Convoca Shifts.
 *
 * @package CentroSocialTurnos
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// ─── Keep data mode ───
// Define CONVOCA_KEEP_DATA_ON_UNINSTALL in wp-config.php to preserve all data
// when uninstalling. Useful for temporary deactivation + reactivation.
if ( defined( 'CONVOCA_KEEP_DATA_ON_UNINSTALL' ) && CONVOCA_KEEP_DATA_ON_UNINSTALL ) {
	return;
}

// 1. Delete all 'centro_turno' posts (including their meta automatically deleted by WP core).
$turnos = get_posts(
	array(
		'post_type'   => 'centro_turno',
		'numberposts' => -1,
		'post_status' => 'any',
	)
);

foreach ( $turnos as $turno ) {
	wp_delete_post( $turno->ID, true ); // Force delete, skip trash.
}

// 1.1 Clean up taxonomy terms.
$taxonomies = array( 'convoca_shifts_actividad' );
foreach ( $taxonomies as $tax ) {
	$terms = get_terms(
		array(
			'taxonomy'   => $tax,
			'hide_empty' => false,
		)
	);
	if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			wp_delete_term( $term->term_id, $tax );
		}
	}
}

// 3. Delete transients and options.
delete_transient( 'convoca_shifts_resumen_turnos_semana' );
delete_option( 'convoca_shifts_version' );
delete_option( 'convoca_shifts_hora_apertura' );
delete_option( 'convoca_shifts_hora_cierre' );
delete_option( 'convoca_shifts_calendar_page_url' );
delete_option( 'convoca_shifts_access_page_url' );

// 4. Note: User meta with _convoca_shifts_ prefix (_convoca_shifts_aprobado, _convoca_shifts_telefono, _convoca_shifts_motivacion).
// is created by Convoca Members plugin, NOT by Centro Social Turnos.
// Therefore, we do NOT delete it here to preserve Member functionality.

// 5. Clear scheduled CRON events.
$timestamp = wp_next_scheduled( 'convoca_shifts_hourly_event' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'convoca_shifts_hourly_event' );
}

// 6. Clean activity log table.
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}convoca_shifts_activity_log" );
