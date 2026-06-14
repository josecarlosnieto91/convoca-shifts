<?php
/**
 * Upgrade Manager for Centro Social Turnos.
 *
 * @package CentroSocialTurnos
 */

namespace Convoca\Shifts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Convoca_Shifts_Upgrade_Manager
 *
 * Extends the common Upgrade_Manager to handle CST-specific updates.
 */
class Convoca_Shifts_Upgrade_Manager extends \Convoca\Core\Upgrade_Manager {

	/**
	 * Get the current database version constant for this plugin.
	 */
	protected function get_db_version(): string {
		return defined( 'CONVOCA_SHIFTS_VERSION' ) ? CONVOCA_SHIFTS_VERSION : '0.0.0';
	}

	/**
	 * Get the wp_options key where the DB version is stored.
	 */
	protected function get_option_name(): string {
		return 'convoca_shifts_version';
	}

	/**
	 * Get the prefix for transients used by this plugin.
	 */
	protected function get_transient_prefix(): string {
		return 'convoca_shifts_upgrade';
	}

	/**
	 * Get an array of upgrade callbacks keyed by version.
	 */
	protected function get_upgrade_callbacks(): array {
		return array(
			'1.6.3' => array( $this, 'upgrade_to_1_6_3' ),
			'2.3.0' => array( $this, 'upgrade_to_2_3_0' ),
		);
	}

	/**
	 * Upgrade to 1.6.3: Ensure log table exists.
	 */
	protected function upgrade_to_1_6_3(): void {
		if ( function_exists( 'convoca_shifts_create_log_table' ) ) {
			convoca_shifts_create_log_table();
		}
	}

	/**
	 * Upgrade to 2.3.0: Perform any necessary maintenance for the current version.
	 * (Placeholder for future idempotent logic if needed).
	 */
	protected function upgrade_to_2_3_0(): void {
		// Version 2.3.0 specific maintenance.
		\Convoca\Core\Logger::info( 'CST Upgrade to 2.3.0 executed.', 'CST/Upgrade' );
	}
}
