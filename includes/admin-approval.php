<?php
namespace Convoca\Shifts;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Add menu page.
add_action( 'admin_menu', 'Convoca\Shifts\convoca_shifts_add_admin_menu', 10 );
function convoca_shifts_add_admin_menu() {
	add_submenu_page(
		'edit.php?post_type=centro_turno',
		__( 'Gestionar Voluntarios', 'convoca-shifts' ),
		__( 'Gestionar Voluntarios', 'convoca-shifts' ),
		'convoca_shifts_manage_turnos',
		'convoca_shifts_voluntarios_pendientes',
		'convoca_shifts_voluntarios_pendientes_page'
	);
}

function convoca_shifts_voluntarios_pendientes_page() {
	// Handle approval.
	if ( isset( $_GET['action'] ) && $_GET['action'] === 'approve' && isset( $_GET['user'] ) && check_admin_referer( 'convoca_shifts_approve_user_' . $_GET['user'] ) ) {
		$user_id = intval( $_GET['user'] );
		$user    = get_userdata( $user_id );
		if ( $user ) {
			$user->set_role( 'voluntario_aprobado' );
			delete_user_meta( $user_id, '_convoca_shifts_aprobado' );
			echo '<div class="convoca-alert convoca-alert--success" style="display:block;margin-bottom:20px;"><p>' . sprintf( __( 'Usuario %s aprobado como voluntario.', 'convoca-shifts' ), $user->display_name ) . '</p></div>';
			do_action( 'convoca_voluntario_aprobado', $user_id );
			$attachments = apply_filters( 'convoca_voluntario_aprobado_attachments', array(), $user_id );
			wp_mail( $user->user_email, __( '¡Solicitud de voluntariado aprobada!', 'convoca-shifts' ), __( 'Hola, ya puedes acceder y gestionar turnos en el centro social. Adjunto a este correo encontrarás tu Acuerdo de Incorporación si procede.', 'convoca-shifts' ), '', $attachments );

			// Log activity.
			if ( function_exists('Convoca\Shifts\convoca_shifts_log_activity') ) {
				convoca_shifts_log_activity( get_current_user_id(), 0, 'voluntario_aprobado', array( 'voluntario_id' => $user_id ) );
			}
		}
	}

	// Handle revocation (Remove role).
	if ( isset( $_GET['action'] ) && $_GET['action'] === 'revoke' && isset( $_GET['user'] ) && check_admin_referer( 'convoca_shifts_revoke_user_' . $_GET['user'] ) ) {
		$user_id = intval( $_GET['user'] );

		if ( $user_id === get_current_user_id() ) {
			echo '<div class="convoca-alert convoca-alert--danger" style="display:block;margin-bottom:20px;"><p>' . __( 'No puedes revocarte tus propios permisos.', 'convoca-shifts' ) . '</p></div>';
		} else {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$user->set_role( 'subscriber' );
				update_user_meta( $user_id, '_convoca_shifts_aprobado', -1 ); // Mark as revoked/rejected but keep meta for history.

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
				if ( function_exists('Convoca\Shifts\convoca_shifts_log_activity') ) {
					convoca_shifts_log_activity( get_current_user_id(), 0, 'voluntario_revocado', array( 'voluntario_id' => $user_id ) );
				}

				echo '<div class="convoca-alert convoca-alert--success" style="display:block;margin-bottom:20px;"><p>' . sprintf( __( 'Permisos de voluntario revocados para %s y turnos futuros liberados.', 'convoca-shifts' ), $user->display_name ) . '</p></div>';
			}
		}
	}

	// Get pending users.
	$pending_args  = array(
		'meta_key'   => '_convoca_shifts_aprobado',
		'meta_value' => '0',
	);
	$pending_query = new WP_User_Query( $pending_args );
	$pending_users = $pending_query->get_results();

	// Get active volunteers.
	$active_args  = array(
		'role' => 'voluntario_aprobado',
	);
	$active_query = new WP_User_Query( $active_args );
	$active_users = $active_query->get_results();

	echo '<div class="wrap">';
	echo '<h1>' . __( 'Gestión de Voluntariado', 'convoca-shifts' ) . '</h1>';

	// --- SECTION: PENDING ---.
	echo '<h2 class="title">' . __( 'Solicitudes Pendientes', 'convoca-shifts' ) . '</h2>';
	if ( ! empty( $pending_users ) ) {
		echo '<table class="wp-list-table widefat fixed striped table-view-list users">';
		echo '<thead><tr><th>' . __( 'Nombre', 'convoca-shifts' ) . '</th><th>' . __( 'Email', 'convoca-shifts' ) . '</th><th>' . __( 'Teléfono', 'convoca-shifts' ) . '</th><th>' . __( 'Motivación', 'convoca-shifts' ) . '</th><th>' . __( 'Acciones', 'convoca-shifts' ) . '</th></tr></thead>';
		echo '<tbody id="the-list">';
		foreach ( $pending_users as $user ) {
			$telefono    = get_user_meta( $user->ID, '_convoca_shifts_telefono', true );
			$motivacion  = get_user_meta( $user->ID, '_convoca_shifts_motivacion', true );
			$approve_url = wp_nonce_url( admin_url( 'edit.php?post_type=centro_turno&page=convoca_shifts_voluntarios_pendientes&action=approve&user=' . $user->ID ), 'convoca_shifts_approve_user_' . $user->ID );

			echo '<tr>';
			echo '<td><strong>' . esc_html( ! empty( $user->first_name ) ? $user->first_name : $user->display_name ) . '</strong></td>';
			echo '<td><a href="mailto:' . esc_attr( $user->user_email ) . '">' . esc_html( $user->user_email ) . '</a></td>';
			echo '<td>' . esc_html( $telefono ) . '</td>';
			echo '<td>' . nl2br( esc_html( $motivacion ) ) . '</td>';
			echo '<td><a href="' . esc_url( $approve_url ) . '" class="convoca-btn convoca-btn-primary">' . __( 'Aprobar Voluntario', 'convoca-shifts' ) . '</a></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	} else {
		echo '<p>' . __( 'No hay solicitudes pendientes.', 'convoca-shifts' ) . '</p>';
	}

	echo '<hr style="margin: 40px 0;">';

	// --- SECTION: ACTIVE ---.
	echo '<h2 class="title">' . __( 'Voluntarios Activos', 'convoca-shifts' ) . '</h2>';
	if ( ! empty( $active_users ) ) {
		echo '<table class="wp-list-table widefat fixed striped table-view-list users">';
		echo '<thead><tr><th>' . __( 'Nombre', 'convoca-shifts' ) . '</th><th>' . __( 'Email', 'convoca-shifts' ) . '</th><th>' . __( 'Teléfono', 'convoca-shifts' ) . '</th><th>' . __( 'Acciones', 'convoca-shifts' ) . '</th></tr></thead>';
		echo '<tbody>';
		foreach ( $active_users as $user ) {
			$telefono   = get_user_meta( $user->ID, '_convoca_shifts_telefono', true );
			$revoke_url = wp_nonce_url( admin_url( 'edit.php?post_type=centro_turno&page=convoca_shifts_voluntarios_pendientes&action=revoke&user=' . $user->ID ), 'convoca_shifts_revoke_user_' . $user->ID );

			echo '<tr>';
			echo '<td><strong>' . esc_html( ! empty( $user->first_name ) ? $user->first_name : $user->display_name ) . '</strong></td>';
			echo '<td>' . esc_html( $user->user_email ) . '</td>';
			echo '<td>' . esc_html( $telefono ) . '</td>';
			echo '<td>
                <a href="' . esc_url( $revoke_url ) . '" class="convoca-btn convoca-btn-outline convoca-btn--danger" onclick="return confirm(\'¿Estás seguro de que quieres revocar los permisos a este voluntario?\');">' . __( 'Revocar Permisos', 'convoca-shifts' ) . '</a>
              </td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	} else {
		echo '<p>' . __( 'No hay voluntarios activos registrados.', 'convoca-shifts' ) . '</p>';
	}

	echo '</div>';
}
