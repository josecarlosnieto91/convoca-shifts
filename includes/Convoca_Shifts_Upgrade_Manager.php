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
 * Upgrade Manager for Convoca Shifts.
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
			'2.5.2' => array( $this, 'upgrade_to_2_5_2_member_meta_key' ),
			'2.5.3' => array( $this, 'upgrade_to_2_5_3_hour_ledger_links' ),
		);
	}

	/**
	 * Upgrade 2.5.3: enlaza los registros de horas de turnos con su turno de origen.
	 *
	 * Hasta 2.5.2 el `registro_hora` de un turno no guardaba de qué turno procedía, así que
	 * desmarcar el turno no podía invalidar sus horas (seguían contando) y volver a marcarlo
	 * creaba otro registro (se duplicaban). La corrección escribe el vínculo
	 * (`_convoca_origen` / `_convoca_origen_id`) en los registros nuevos; esta migración se lo
	 * añade a los históricos **solo cuando se puede demostrar** que el turno existe y que su
	 * responsable es el voluntario del registro. No borra nada ni cambia estados.
	 *
	 * Idempotente: solo toca los registros sin vínculo.
	 */
	protected function upgrade_to_2_5_3_hour_ledger_links(): void {
		global $wpdb;

		$registros = $wpdb->get_results(
			"SELECT p.ID, p.post_title, u.meta_value AS usuario_id
			 FROM {$wpdb->posts} p
			 JOIN {$wpdb->postmeta} a ON a.post_id = p.ID AND a.meta_key = '_convoca_actividad_id' AND a.meta_value = '0'
			 LEFT JOIN {$wpdb->postmeta} u ON u.post_id = p.ID AND u.meta_key = '_convoca_usuario_id'
			 LEFT JOIN {$wpdb->postmeta} oi ON oi.post_id = p.ID AND oi.meta_key = '_convoca_origen_id'
			 WHERE p.post_type = 'registro_hora'
			   AND oi.meta_id IS NULL
			   AND p.post_title LIKE 'Horas Turno CS #%'"
		);

		$enlazados = 0;
		$omitidos  = 0;

		foreach ( $registros as $registro ) {
			if ( ! preg_match( '/^Horas Turno CS #(\d+)/', (string) $registro->post_title, $coincidencias ) ) {
				$omitidos++;
				continue;
			}

			$turno_id   = (int) $coincidencias[1];
			$turno      = get_post( $turno_id );
			$usuario_id = (int) $registro->usuario_id;

			if ( ! $turno || 'centro_turno' !== $turno->post_type || $usuario_id <= 0 ) {
				$omitidos++;
				continue;
			}

			// Solo se enlaza si el turno es de ese voluntario: sin esa certeza no se toca.
			if ( (int) get_post_meta( $turno_id, '_id_responsable', true ) !== $usuario_id ) {
				$omitidos++;
				continue;
			}

			update_post_meta( (int) $registro->ID, '_convoca_origen', \Convoca\Core\Hour_Ledger::ORIGEN_TURNO );
			update_post_meta( (int) $registro->ID, '_convoca_origen_id', $turno_id );
			$enlazados++;
		}

		\Convoca\Core\Logger::info(
			sprintf(
				'Upgrade 2.5.3: vínculo turno↔registro_hora — enlazados %d, omitidos %d (de %d registros sin vínculo).',
				$enlazados,
				$omitidos,
				count( $registros )
			),
			'Turnos/Upgrade'
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

	/**
	 * Upgrade to 2.5.2 (E2E-11): Normalize the member link key on registro_hora.
	 *
	 * Hour_Sync used to write '_convoca_miembro_id' (with "i"), which
	 * Voluntariado_Manager / Certificate_Generator never read (they use
	 * '_convoca_member_id'). Migrate historical rows so past shift hours
	 * count towards volunteers again.
	 */
	protected function upgrade_to_2_5_2_member_meta_key(): void {
		global $wpdb;

		$updated = $wpdb->query(
			"UPDATE {$wpdb->postmeta}
			SET meta_key = '_convoca_member_id'
			WHERE meta_key = '_convoca_miembro_id'"
		);

		\Convoca\Core\Logger::info( 'CST Upgrade 2.5.2: claves _convoca_miembro_id migradas a _convoca_member_id (filas: ' . (int) $updated . ').', 'CST/Upgrade' );
	}
}
