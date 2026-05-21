<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'cst_add_estadisticas_menu', 20 );
function cst_add_estadisticas_menu() {
	add_submenu_page(
		'edit.php?post_type=centro_turno',
		__( 'Estadísticas Voluntariado', 'convoca-shifts' ),
		__( 'Estadísticas', 'convoca-shifts' ),
		'cst_view_stats',
		'cst_estadisticas',
		'cst_estadisticas_page'
	);
}

function cst_estadisticas_page() {
	global $wpdb;

	// Diagnostic: Check if table exists.
	$table_log    = $wpdb->prefix . 'cst_activity_log';
	$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_log'" );

	if ( isset( $_GET['cst_fix_logs'] ) ) {
		cst_create_log_table();
		echo '<div class="updated"><p>' . __( 'Intento de creación de tabla realizado.', 'convoca-shifts' ) . '</p></div>';
	}

	if ( ! $table_exists ) {
		echo '<div class="error"><p>' . sprintf( __( 'La tabla de logs (%s) no existe. El registro de actividad no funcionará.', 'convoca-shifts' ), $table_log ) . ' <a href="' . admin_url( 'edit.php?post_type=centro_turno&page=cst_estadisticas&cst_fix_logs=1' ) . '" class="biodevas-btn biodevas-btn-outline">' . __( 'Intentar crear ahora', 'convoca-shifts' ) . '</a></p></div>';
	}

	// Get all users who have the volunteer role or have done turns.
	$volunteers = get_users(
		array(
			'capability' => 'gestionar_mis_turnos',
		)
	);

	// Also get users who are not "volunteers" but have responsable_id in metadata (maybe admins).
	$uids_with_turns = $wpdb->get_col( "SELECT DISTINCT meta_value FROM $wpdb->postmeta WHERE meta_key = '_id_responsable' AND meta_value > 0" );

	$all_uids = array_unique( array_merge( wp_list_pluck( $volunteers, 'ID' ), $uids_with_turns ) );

	?>
	<div class="wrap">
		<h1><?php _e( 'Estadísticas de Voluntariado', 'convoca-shifts' ); ?></h1>
		<p><?php _e( 'Resumen de actividad y horas dedicadas por cada voluntario.', 'convoca-shifts' ); ?></p>
		
		<div style="margin-bottom: 20px;">
			<form method="post" action="">
				<?php wp_nonce_field( 'cst_exportar_stats_action' ); ?>
				<input type="hidden" name="cst_action" value="exportar_stats_csv">
				<button type="submit" class="biodevas-btn biodevas-btn-outline"><?php _e( '📥 Exportar Estadísticas a CSV', 'convoca-shifts' ); ?></button>
			</form>
		</div>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php _e( 'Voluntario', 'convoca-shifts' ); ?></th>
					<th><?php _e( 'Email', 'convoca-shifts' ); ?></th>
					<th><?php _e( 'Turnos (Total)', 'convoca-shifts' ); ?></th>
					<th><?php _e( 'Realizados', 'convoca-shifts' ); ?></th>
					<th><?php _e( 'Faltas', 'convoca-shifts' ); ?></th>
					<th><?php _e( 'Horas (Aprobadas)', 'convoca-shifts' ); ?></th>
					<th><?php _e( 'Último Turno', 'convoca-shifts' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				if ( empty( $all_uids ) ) {
					echo '<tr><td colspan="5">' . __( 'No hay voluntarios registrados aún.', 'convoca-shifts' ) . '</td></tr>';
				} else {
					foreach ( $all_uids as $user_id ) {
						$user = get_userdata( $user_id );
						if ( ! $user ) {
							continue;
						}

						$stats = cst_get_voluntario_stats( $user_id );
						?>
						<tr>
							<td><strong><?php echo esc_html( $user->display_name ); ?></strong></td>
							<td><?php echo esc_html( $user->user_email ); ?></td>
							<td><?php echo esc_html( $stats['total_turnos'] ); ?></td>
							<td style="color: green; font-weight: bold;"><?php echo esc_html( $stats['realizados'] ); ?></td>
							<td style="color: <?php echo $stats['no_asistio'] > 0 ? 'red' : 'inherit'; ?>;"><?php echo esc_html( $stats['no_asistio'] ); ?></td>
							<td><strong><?php echo esc_html( $stats['total_horas'] ); ?> h</strong></td>
							<td><?php echo esc_html( $stats['ultimo_turno'] ?: '---' ); ?></td>
						</tr>
						<?php
					}
				}
				?>
			</tbody>
		</table>

		<h2 style="margin-top: 40px;"><?php _e( 'Registro de Actividad Reciente', 'convoca-shifts' ); ?></h2>
		<?php
		$table_log = $wpdb->prefix . 'cst_activity_log';

		// Paginación para logs.
		$per_page     = 50;
		$current_page = isset( $_GET['paged_logs'] ) ? max( 1, intval( $_GET['paged_logs'] ) ) : 1;
		$offset       = ( $current_page - 1 ) * $per_page;

		$total_logs  = $wpdb->get_var( "SELECT COUNT(*) FROM $table_log" );
		$total_pages = ceil( $total_logs / $per_page );

		$logs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_log ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width: 150px;"><?php _e( 'Fecha/Hora', 'convoca-shifts' ); ?></th>
					<th style="width: 150px;"><?php _e( 'Usuario', 'convoca-shifts' ); ?></th>
					<th style="width: 120px;"><?php _e( 'Acción', 'convoca-shifts' ); ?></th>
					<th><?php _e( 'Detalles', 'convoca-shifts' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $logs ) ) : ?>
					<tr><td colspan="4"><?php _e( 'No hay actividad registrada aún.', 'convoca-shifts' ); ?></td></tr>
				<?php else : ?>
					<?php
					foreach ( $logs as $log ) :
						$l_user        = get_userdata( $log->user_id );
						$l_user_name   = $l_user ? $l_user->display_name : __( 'Usuario Desconocido', 'convoca-shifts' );
						$l_turno       = get_post( $log->turno_id );
						$l_turno_title = $l_turno ? $l_turno->post_title : '';

						if ( ! $l_turno_title && $log->data ) {
							$data = json_decode( $log->data, true );
							if ( isset( $data['voluntario_id'] ) ) {
								$v = get_userdata( $data['voluntario_id'] );
								if ( $v ) {
									$l_turno_title = __( 'Voluntario: ', 'convoca-shifts' ) . $v->display_name;
								}
							}
						}
						if ( ! $l_turno_title ) {
							$l_turno_title = $log->turno_id ? 'Turno ID: ' . $log->turno_id : '---';
						}
						?>
						<tr>
							<td><?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $log->created_at ) ) ); ?></td>
							<td><strong><?php echo esc_html( $l_user_name ); ?></strong></td>
							<td><span class="cst-log-badge cst-log-<?php echo esc_attr( $log->action ); ?>"><?php echo esc_html( $log->action ); ?></span></td>
							<td><?php echo esc_html( $l_turno_title ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav" style="margin-top: 10px;">
				<div class="tablenav-pages">
					<span class="displaying-num"><?php printf( __( '%d elementos', 'convoca-shifts' ), $total_logs ); ?></span>
					<span class="pagination-links">
						<?php if ( $current_page > 1 ) : ?>
							<a class="prev-page button" href="<?php echo add_query_arg( 'paged_logs', $current_page - 1 ); ?>"><span class="screen-reader-text"><?php _e( 'Página anterior', 'convoca-shifts' ); ?></span>‹</a>
						<?php endif; ?>
						<span class="paging-input">
							<span class="tablenav-paging-text"><?php echo $current_page; ?> <?php _e( 'de', 'convoca-shifts' ); ?> <span class="total-pages"><?php echo $total_pages; ?></span></span>
						</span>
						<?php if ( $current_page < $total_pages ) : ?>
							<a class="next-page button" href="<?php echo add_query_arg( 'paged_logs', $current_page + 1 ); ?>"><span class="screen-reader-text"><?php _e( 'Página siguiente', 'convoca-shifts' ); ?></span>›</a>
						<?php endif; ?>
					</span>
				</div>
			</div>
		<?php endif; ?>

	</div>
	<?php
}

/**
 * Preload stats for all volunteers in one query to avoid N+1.
 */
function cst_preload_voluntario_stats(): array {
	global $wpdb;
	$stats = array();

	// Total hours and counts per volunteer in a single query.
	$results = $wpdb->get_results(
		"
        SELECT 
            pm_resp.meta_value AS user_id,
            COUNT(*) AS total_turnos,
            SUM(CASE WHEN pm_real.meta_value = 'realizado' THEN 1 ELSE 0 END) AS realizados,
            SUM(CASE WHEN pm_real.meta_value = 'no_asistio' THEN 1 ELSE 0 END) AS no_asistio,
            MAX(pm_fecha.meta_value) AS ultimo_turno_date
        FROM {$wpdb->posts} p
        JOIN {$wpdb->postmeta} pm_resp ON p.ID = pm_resp.post_id AND pm_resp.meta_key = '_id_responsable'
        LEFT JOIN {$wpdb->postmeta} pm_real ON p.ID = pm_real.post_id AND pm_real.meta_key = '_estado_real'
        WHERE p.post_type = 'centro_turno' AND p.post_status = 'publish'
        GROUP BY pm_resp.meta_value
    "
	);

	if ( ! $results ) {
		return $stats;
	}

	foreach ( $results as $row ) {
		$uid           = (int) $row->user_id;
		$stats[ $uid ] = array(
			'total_turnos' => (int) $row->total_turnos,
			'realizados'   => (int) $row->realizados,
			'no_asistio'   => (int) $row->no_asistio,
			'total_horas'  => 0,
			'ultimo_turno' => $row->ultimo_turno_date ? wp_date( 'd/m/Y', strtotime( $row->ultimo_turno_date ) ) : '',
		);
	}

	// Hours calculation (requires _hora_fin join) in a second aggregated query.
	$hour_results = $wpdb->get_results(
		"
        SELECT 
            pm_resp.meta_value AS user_id,
            TIMESTAMPDIFF(SECOND, pm_fecha2.meta_value, 
                COALESCE(CONCAT(DATE(pm_fecha2.meta_value), ' ', pm_fin.meta_value, ':00'), DATE_ADD(pm_fecha2.meta_value, INTERVAL 2 HOUR))
            ) AS total_seconds
        FROM {$wpdb->posts} p
        JOIN {$wpdb->postmeta} pm_resp ON p.ID = pm_resp.post_id AND pm_resp.meta_key = '_id_responsable'
        JOIN {$wpdb->postmeta} pm_real ON p.ID = pm_real.post_id AND pm_real.meta_key = '_estado_real'
        LEFT JOIN {$wpdb->postmeta} pm_fin ON p.ID = pm_fin.post_id AND pm_fin.meta_key = '_hora_fin'
        WHERE p.post_type = 'centro_turno' AND p.post_status = 'publish'
          AND pm_real.meta_value = 'realizado'
    "
	);

	if ( $hour_results ) {
		foreach ( $hour_results as $row ) {
			$uid  = (int) $row->user_id;
			$secs = max( 0, (int) $row->total_seconds );
			if ( isset( $stats[ $uid ] ) ) {
				$stats[ $uid ]['total_horas'] += round( $secs / 3600, 1 );
			}
		}
	}

	return $stats;
}

/**
 * Get volunteer stats for a single user (uses preloaded cache if available).
 */
function cst_get_voluntario_stats( $user_id ) {
	static $preloaded = null;
	if ( $preloaded === null ) {
		$preloaded = cst_preload_voluntario_stats();
	}
	return $preloaded[ $user_id ] ?? array(
		'total_turnos' => 0,
		'realizados'   => 0,
		'no_asistio'   => 0,
		'total_horas'  => 0,
		'ultimo_turno' => '',
	);
}

function cst_get_action_label( $action ) {
	$labels = array(
		'turno_creado'        => __( 'Creado', 'convoca-shifts' ),
		'turno_cubierto'      => __( 'Cubierto', 'convoca-shifts' ),
		'turno_liberado'      => __( 'Liberado', 'convoca-shifts' ),
		'turno_asignado'      => __( 'Asignado (Admin)', 'convoca-shifts' ),
		'turno_desasignado'   => __( 'Desasignado (Admin)', 'convoca-shifts' ),
		'asistencia_ok'       => __( 'Asistencia OK', 'convoca-shifts' ),
		'asistencia_no'       => __( 'No asistió', 'convoca-shifts' ),
		'voluntario_aprobado' => __( 'Voluntario Aprobado', 'convoca-shifts' ),
		'voluntario_revocado' => __( 'Voluntario Revocado', 'convoca-shifts' ),
	);
	return isset( $labels[ $action ] ) ? $labels[ $action ] : $action;
}

// --- Handler for Statistics CSV Export ---.
add_action( 'admin_init', 'cst_exportar_stats_csv_handler' );
function cst_exportar_stats_csv_handler() {
	if ( isset( $_POST['cst_action'] ) && $_POST['cst_action'] === 'exportar_stats_csv' && check_admin_referer( 'cst_exportar_stats_action' ) ) {
		if ( ! current_user_can( 'cst_view_stats' ) ) {
			return;
		}

		$filename = 'estadisticas-voluntariado-' . wp_date( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' ); .
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ); // BOM for Excel.
		fputcsv(
			$output,
			array(
				__( 'Voluntario', 'convoca-shifts' ),
				__( 'Email', 'convoca-shifts' ),
				__( 'Turnos (Total)', 'convoca-shifts' ),
				__( 'Realizados', 'convoca-shifts' ),
				__( 'Faltas', 'convoca-shifts' ),
				__( 'Horas (Aprobadas)', 'convoca-shifts' ),
				__( 'Último Turno', 'convoca-shifts' ),
			)
		);

		// Same logic as in stats page to get UIDs.
		global $wpdb;
		$volunteers      = get_users( array( 'capability' => 'gestionar_mis_turnos' ) );
		$uids_with_turns = $wpdb->get_col( "SELECT DISTINCT meta_value FROM $wpdb->postmeta WHERE meta_key = '_id_responsable' AND meta_value > 0" );
		$all_uids        = array_unique( array_merge( wp_list_pluck( $volunteers, 'ID' ), $uids_with_turns ) );

		foreach ( $all_uids as $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				continue;
			}

			$stats = cst_get_voluntario_stats( $user_id );

			$row = array(
				$user->display_name,
				$user->user_email,
				$stats['total_turnos'],
				$stats['realizados'],
				$stats['no_asistio'],
				$stats['total_horas'],
				$stats['ultimo_turno'] ?: '---',
			);

			// Escape all fields for CSV injection.
			$row = array_map(
				function ( $field ) {
					return class_exists( '\Convoca\Core\Utils' ) ? \Convoca\Core\Utils::escape_csv_field( (string) $field ) : (string) $field;
				},
				$row
			);

			fputcsv( $output, $row );
		}

		fclose( $output );
		exit;
	}
}
