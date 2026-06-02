<?php
/**
 * Hour synchronization for volunteers in Centro Social.
 *
 * @package Convoca\Shifts
 */

namespace Convoca\Shifts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hour_Sync {

	/**
	 * Synchronizes shift hours to the volunteer's global hour pool.
	 * Called when a shift's attendance status changes.
	 *
	 * @param int    $post_id Shift (turno) ID.
	 * @param int    $user_id Responsible user ID.
	 * @param string $status New status ('realizado', 'no_asistio', 'pendiente').
	 */
	public static function sync_hours_to_volunteer_global( int $post_id, int $user_id, string $status ) {
		global $wpdb;
		if ( $user_id <= 0 ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		// Check if user is a volunteer.
		$is_volunteer = in_array( 'voluntario_aprobado', (array) $user->roles ) || $user->has_cap( 'gestionar_mis_turnos' ) || get_user_meta( $user_id, '_conv_es_voluntario', true );
		if ( ! $is_volunteer ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== 'centro_turno' ) {
			return;
		}

		$should_log     = false;
		$should_hook    = false;
		$diff_hours     = 0;
		$log_entry_data = null;

		$wpdb->query( 'START TRANSACTION' );

		try {
			// Lock the shift post to ensure atomic processing of this shift's hours.
			$wpdb->query( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID = %d FOR UPDATE", $post_id ) );

			// Get hours already counted, using DB directly to bypass WP Cache within transaction.
			$horas_contabilizadas = (float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_cst_horas_contabilizadas'",
					$post_id
				)
			);

			// If newly marked as 'realizado' and NOT yet counted.
			if ( $status === 'realizado' && $horas_contabilizadas <= 0 ) {

				$fecha_inicio = get_post_meta( $post_id, '_fecha_inicio', true );
				$hora_fin     = get_post_meta( $post_id, '_hora_fin', true );

				if ( $fecha_inicio && $hora_fin ) {
					$start_timestamp = strtotime( $fecha_inicio );
					$fecha_date      = wp_date( 'Y-m-d', $start_timestamp );
					$end_timestamp   = strtotime( $fecha_date . ' ' . $hora_fin . ':00' );
				} else {
					$start_timestamp = $fecha_inicio ? strtotime( $fecha_inicio ) : strtotime( $post->post_date );
					$end_timestamp   = $hora_fin ? strtotime( $hora_fin ) : 0;
				}

				if ( $start_timestamp && $end_timestamp && $end_timestamp > $start_timestamp ) {
					$hours = round( ( $end_timestamp - $start_timestamp ) / 3600, 2 );

					if ( $hours > 0 ) {
						self::update_global_hours_locked( $user_id, $hours );
						update_post_meta( $post_id, '_cst_horas_contabilizadas', $hours );

						// Prepare log entry data (will be created after commit).
						$log_entry_data = array(
							'post_id' => $post_id,
							'user_id' => $user_id,
							'hours'   => $hours,
						);

						$should_hook = true;
						$diff_hours  = $hours;
					}
				}
			}
			// If it was already counted but status changed to something else, we should subtract the hours.
			elseif ( $status !== 'realizado' && $horas_contabilizadas > 0 ) {
				self::update_global_hours_locked( $user_id, -$horas_contabilizadas );
				delete_post_meta( $post_id, '_cst_horas_contabilizadas' );

				$should_hook = true;
				$diff_hours  = -$horas_contabilizadas;
			}

			$wpdb->query( 'COMMIT' );

			// Create log entry after successful commit.
			if ( $log_entry_data ) {
				self::create_log_entry( $log_entry_data['post_id'], $log_entry_data['user_id'], $log_entry_data['hours'] );
			}

			// Fire hook only after successful commit.
			if ( $should_hook ) {
				\Convoca\Core\Utils::do_action( 'conv_after_horas_voluntario_actualizadas', 'conv_horas_voluntario_actualizadas', $user_id, $diff_hours );
			}
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			\Convoca\Core\Logger::error( 'Error en sincronización de horas: ' . $e->getMessage(), 'Turnos/Sync', $post_id );
		}
	}

	/**
	 * Atomically adds or subtracts hours from the global pool (caller must handle transaction)
	 */
	private static function update_global_hours_locked( int $user_id, float $hours ) {
		global $wpdb;
		$meta_key = '_conv_horas_voluntariado_total';

		// Atomic UPSERT: handles both insert and update in one query, avoiding SELECT+INSERT race.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->usermeta} (user_id, meta_key, meta_value) 
             VALUES (%d, %s, %f)
             ON DUPLICATE KEY UPDATE meta_value = CAST(meta_value AS DECIMAL(10,2)) + %f",
				$user_id,
				$meta_key,
				$hours,
				$hours
			)
		);
	}

	/**
	 * Creates an entry in CPT_Registro_Hora
	 */
	private static function create_log_entry( int $post_id, int $user_id, float $hours ) {
		if ( ! post_type_exists( 'registro_hora' ) ) {
			\Convoca\Core\Logger::warning(
				"Horas no registradas: el CPT 'registro_hora' no está disponible. Activa convoca-members.",
				'Turnos/HourSync',
				$post_id
			);
			return;
		}

		$user = get_userdata( $user_id );
		$post = get_post( $post_id );

		$log_id = wp_insert_post(
			array(
				'post_type'   => 'registro_hora',
				'post_title'  => sprintf( 'Horas Turno CS #%d - %s', $post_id, $user->display_name ),
				'post_status' => 'publish',
				'post_author' => $user_id,
			)
		);

		if ( ! is_wp_error( $log_id ) ) {
			// Check member.
			$members = get_posts(
				array(
					'post_type'      => 'miembro',
					'meta_key'       => '_conv_email',
					'meta_value'     => $user->user_email,
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);

			if ( ! empty( $members ) ) {
				update_post_meta( $log_id, ' _conv_miembro_id', $members[0] );
			}

			update_post_meta( $log_id, '_conv_usuario_id', $user_id );
			update_post_meta( $log_id, '_conv_fecha', wp_date( 'Y-m-d' ) );
			update_post_meta( $log_id, '_conv_horas', $hours );
			// We use 'turno' as activity or just project ID 0. Turno post ID is not an actividad, but we link it here.
			update_post_meta( $log_id, '_conv_actividad_id', 0 );
			update_post_meta( $log_id, '_conv_estado', 'aprobada' );
			update_post_meta( $log_id, '_conv_tareas', 'Turno en Centro Social: ' . $post->post_title );
		}
	}
}
