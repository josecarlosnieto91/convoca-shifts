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

/**
 * Admin page: volunteer hours audit.
 *
 * @package Convoca\Shifts
 */
class Admin_Auditoria_Horas {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 25 );
	}

	/**
	 * Add submenu page under Centro Turno.
	 */
	public static function add_menu(): void {
		add_submenu_page(
			'edit.php?post_type=centro_turno',
			__( 'Auditoría de Horas', 'convoca-shifts' ),
			__( 'Auditoría Horas', 'convoca-shifts' ),
			'convoca_shifts_audit_hours',
			'convoca_shifts_auditoria_horas',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render the audit page.
	 */
	public static function render_page(): void {
		global $wpdb;

		if ( isset( $_POST['convoca_shifts_action'] ) && $_POST['convoca_shifts_action'] === 'recalcular' && isset( $_POST['user_id'] ) ) {
			check_admin_referer( 'convoca_shifts_recalcular_' . (int) $_POST['user_id'] );
			self::recalcular_horas_usuario( (int) $_POST['user_id'] );
			echo '<div class="convoca-alert convoca-alert--success" style="display:block;margin-bottom:20px;"><p>' . esc_html__( 'Horas recalculadas correctamente.', 'convoca-shifts' ) . '</p></div>';
		}

		$users = get_users( array( 'role__in' => array( 'voluntario_aprobado', 'administrator' ) ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Auditoría de Horas - Voluntariado', 'convoca-shifts' ); ?></h1>
			<p><?php esc_html_e( 'Listado de voluntarios con el total de horas acumuladas desde turnos realizados.', 'convoca-shifts' ); ?></p>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Usuario', 'convoca-shifts' ); ?></th>
						<th><?php esc_html_e( 'Email', 'convoca-shifts' ); ?></th>
						<th><?php esc_html_e( 'Horas Totales (meta)', 'convoca-shifts' ); ?></th>
						<th><?php esc_html_e( 'Turnos Realizados', 'convoca-shifts' ); ?></th>
						<th><?php esc_html_e( 'Acciones', 'convoca-shifts' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $users as $u ) :
						$horas_meta        = (float) get_user_meta( $u->ID, '_convoca_horas_voluntariado_total', true );
						$turnos_realizados = $wpdb->get_var(
							$wpdb->prepare(
								"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
								INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'centro_turno'
								WHERE pm.meta_key = '_id_responsable' AND pm.meta_value = %d
								AND p.ID IN (
									SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_estado_real' AND meta_value = 'realizado'
								)",
								$u->ID
							)
						);
						?>
						<tr>
							<td><strong><?php echo esc_html( $u->display_name ); ?></strong></td>
							<td><?php echo esc_html( $u->user_email ); ?></td>
							<td><?php echo number_format( $horas_meta, 2 ); ?>h</td>
							<td><?php echo (int) $turnos_realizados; ?></td>
							<td>
								<form method="post" style="display:inline;">
									<?php wp_nonce_field( 'convoca_shifts_recalcular_' . $u->ID ); ?>
									<input type="hidden" name="user_id" value="<?php echo esc_attr( $u->ID ); ?>">
									<input type="hidden" name="convoca_shifts_action" value="recalcular">
									<button type="submit" class="button button-small" onclick="return confirm('<?php esc_attr_e( '¿Recalcular horas? Esta acción escaneará todos los turnos realizados y actualizará el contador.', 'convoca-shifts' ); ?>')"><?php esc_html_e( 'Recalcular', 'convoca-shifts' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Recalculate hours for a single user from completed shifts.
	 */
	private static function recalcular_horas_usuario( int $user_id ): void {
		global $wpdb;

		$turnos = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm_resp ON p.ID = pm_resp.post_id AND pm_resp.meta_key = '_id_responsable' AND pm_resp.meta_value = %d
				INNER JOIN {$wpdb->postmeta} pm_est ON p.ID = pm_est.post_id AND pm_est.meta_key = '_estado_real' AND pm_est.meta_value = 'realizado'
				WHERE p.post_type = 'centro_turno'",
				$user_id
			)
		);

		$total_horas = 0;
		foreach ( $turnos as $t ) {
			$fecha_inicio = get_post_meta( $t->ID, '_fecha_inicio', true );
			$hora_fin     = get_post_meta( $t->ID, '_hora_fin', true );
			if ( $fecha_inicio && $hora_fin ) {
				$start_ts   = strtotime( $fecha_inicio );
				$fecha_date = wp_date( 'Y-m-d', $start_ts );
				$end_ts     = strtotime( $fecha_date . ' ' . $hora_fin . ':00' );
				if ( $end_ts > $start_ts ) {
					$total_horas += round( ( $end_ts - $start_ts ) / 3600, 2 );
				}
			}
		}

		update_user_meta( $user_id, '_convoca_horas_voluntariado_total', $total_horas );

		\Convoca\Core\Logger::info(
			"Horas recalculadas para usuario #$user_id: $total_horas horas en " . count( $turnos ) . ' turnos.',
			'Turnos',
			$user_id
		);
	}
}
