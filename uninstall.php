<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package CentroSocialTurnos
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// 1. Delete all 'centro_turno' posts (including their meta automatically deleted by WP core)
$turnos = get_posts( array(
    'post_type'   => 'centro_turno',
    'numberposts' => -1,
    'post_status' => 'any'
) );

foreach ( $turnos as $turno ) {
    wp_delete_post( $turno->ID, true ); // Force delete, skip trash
}

// 1.1 Clean up taxonomy terms
$taxonomies = array( 'cst_actividad' );
foreach ( $taxonomies as $tax ) {
    $terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
    if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
        foreach ( $terms as $term ) {
            wp_delete_term( $term->term_id, $tax );
        }
    }
}

// 3. Delete transients and options
delete_transient( 'cst_resumen_turnos_semana' );
delete_option( 'cst_plugin_version' );

// 4. Note: User meta with _cst_ prefix (_cst_aprobado, _cst_telefono, _cst_motivacion)
//    is created by Biodevas Members plugin, NOT by Centro Social Turnos.
//    Therefore, we do NOT delete it here to preserve Member functionality.

// 5. Clear scheduled CRON events
$timestamp = wp_next_scheduled( 'cst_hourly_event' );
if ( $timestamp ) {
    wp_unschedule_event( $timestamp, 'cst_hourly_event' );
}

// 6. Clean activity log table
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cst_activity_log" );
