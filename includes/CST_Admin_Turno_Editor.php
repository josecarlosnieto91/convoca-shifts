<?php
/**
 * Custom editor for centro_turno (shifts).
 *
 * Replaces the WordPress standard editor with a custom form

 * using convoca-common CSS classes.
 *
 * @package CentroSocialTurnos
 */
namespace Convoca\Shifts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CST_Admin_Turno_Editor {

	const SLUG = 'cst-editar-turno';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_post_cst_save_turno', array( $this, 'handle_save' ) );
		add_action( 'load-post-new.php', array( $this, 'redirect_from_default' ) );
		add_action( 'load-post.php', array( $this, 'redirect_from_default' ) );
		add_action( 'add_meta_boxes', array( $this, 'remove_metabox' ), 20 );
	}

	public function register_page(): void {
		add_submenu_page(
			null, // Hidden from menu.
			__( 'Editar Turno', 'convoca-shifts' ),
			__( 'Editar Turno', 'convoca-shifts' ),
			'cst_manage_turnos',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Remove the default metabox from the classic editor.
	 */
	public function remove_metabox(): void {
		remove_meta_box( 'cst_turno_opciones', 'centro_turno', 'normal' );
	}

	/**
	 * Redirect from the WordPress default editor to our custom page.
	 */
	public function redirect_from_default(): void {
		global $typenow;
		if ( $typenow !== 'centro_turno' ) {
			return;
		}
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		if ( $post_id > 0 ) {
			wp_safe_redirect( admin_url( 'edit.php?post_type=centro_turno&page=' . self::SLUG . '&id=' . $post_id ) );
		} else {
			wp_safe_redirect( admin_url( 'edit.php?post_type=centro_turno&page=cst_turno_rapido' ) );
		}
		exit;
	}

	/**
	 * Render the custom editor.
	 */
	public function render(): void {
		$post_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$is_edit = $post_id > 0 && get_post_type( $post_id ) === 'centro_turno';

		if ( $is_edit && ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( __( 'No tienes permisos para editar este turno.', 'convoca-shifts' ) );
		}

		$post = $is_edit ? get_post( $post_id ) : null;

		$fecha_inicio_meta = $is_edit ? get_post_meta( $post_id, '_fecha_inicio', true ) : '';
		$fecha             = $is_edit ? ( $fecha_inicio_meta ? wp_date( 'Y-m-d', strtotime( $fecha_inicio_meta ) ) : wp_date( 'Y-m-d', strtotime( $post->post_date ) ) ) : wp_date( 'Y-m-d' );
		$hora_ini          = $is_edit ? ( $fecha_inicio_meta ? wp_date( 'H:i', strtotime( $fecha_inicio_meta ) ) : wp_date( 'H:i', strtotime( $post->post_date ) ) ) : wp_date( 'H:i' );
		$hora_fin          = $is_edit ? ( get_post_meta( $post_id, '_hora_fin', true ) ?: '' ) : '';

		$estado         = $is_edit ? get_post_meta( $post_id, '_estado', true ) : 'abierto_disponible';
		$estado_real    = $is_edit ? get_post_meta( $post_id, '_estado_real', true ) : 'pendiente';
		$apoyo          = $is_edit ? (int) get_post_meta( $post_id, '_necesita_apoyo', true ) : 0;
		$id_responsable = $is_edit ? (int) get_post_meta( $post_id, '_id_responsable', true ) : 0;

		// Get current taxonomy terms.
		$term_actividad    = $is_edit ? wp_get_post_terms( $post_id, 'cst_actividad', array( 'fields' => 'ids' ) ) : array();
		$current_actividad = ! empty( $term_actividad ) ? $term_actividad[0] : 0;
		$current_monitor   = $is_edit ? (int) get_post_meta( $post_id, '_monitor', true ) : 0;

		// Get approved volunteers.
		$voluntarios = get_users(
			array(
				'role'    => 'voluntario_aprobado',
				'orderby' => 'display_name',
			)
		);

		$all_actividades = get_terms(
			array(
				'taxonomy'   => 'cst_actividad',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		$all_monitores   = get_users(
			array(
				'role__in' => array( 'administrator', 'monitor_actividad' ),
				'orderby'  => 'display_name',
				'fields'   => array( 'ID', 'display_name' ),
			)
		);

		wp_nonce_field( 'cst_save_turno_' . $post_id, '_cst_nonce' );
		?>
		<div class="wrap" style="max-width: 800px; margin: 20px auto;">
			<h1><?php echo $is_edit ? esc_html__( 'Editar Turno', 'convoca-shifts' ) : esc_html__( 'Nuevo Turno', 'convoca-shifts' ); ?></h1>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="convoca-box" style="background:#fff;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,0.05);padding:40px;margin-top:20px;">
				<input type="hidden" name="action" value="cst_save_turno">
				<input type="hidden" name="post_id" value="<?php echo $is_edit ? $post_id : 0; ?>">

				<div class="convoca-grid-2">

					<h3 style="grid-column:1/-1;margin:0 0 1rem 0;padding-bottom:.5rem;border-bottom:1px solid var(--bde-border,#ccc);">
						<?php esc_html_e( 'Datos del Turno', 'convoca-shifts' ); ?>
					</h3>

					<div class="convoca-field">
						<label for="cst_fecha"><?php esc_html_e( 'Fecha', 'convoca-shifts' ); ?></label>
						<input type="date" id="cst_fecha" name="cst_fecha" value="<?php echo esc_attr( $fecha ); ?>" required>
					</div>

					<div class="convoca-field">
						<label for="cst_hora_ini"><?php esc_html_e( 'Hora inicio', 'convoca-shifts' ); ?></label>
						<input type="time" id="cst_hora_ini" name="cst_hora_ini" value="<?php echo esc_attr( $hora_ini ); ?>" required>
					</div>

					<div class="convoca-field">
						<label for="cst_hora_fin"><?php esc_html_e( 'Hora fin', 'convoca-shifts' ); ?></label>
						<input type="time" id="cst_hora_fin" name="cst_hora_fin" value="<?php echo esc_attr( $hora_fin ); ?>">
					</div>

					<div class="convoca-field">
						<label for="cst_id_responsable"><?php esc_html_e( 'Responsable', 'convoca-shifts' ); ?></label>
						<select id="cst_id_responsable" name="cst_id_responsable">
							<option value="0"><?php esc_html_e( '— Sin asignar —', 'convoca-shifts' ); ?></option>
							<?php
							foreach ( $voluntarios as $v ) :
								$nombre = ! empty( $v->first_name ) ? $v->first_name : $v->display_name;
								?>
								<option value="<?php echo (int) $v->ID; ?>" <?php selected( $id_responsable, $v->ID ); ?>>
									<?php echo esc_html( $nombre . ' (@' . $v->user_login . ')' ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<h3 style="grid-column:1/-1;margin-top:1rem;padding-bottom:.5rem;border-bottom:1px solid var(--bde-border,#ccc);">
						<?php esc_html_e( 'Estado y Actividad', 'convoca-shifts' ); ?>
					</h3>

					<div class="convoca-field">
						<label for="cst_estado"><?php esc_html_e( 'Estado del centro', 'convoca-shifts' ); ?></label>
						<select id="cst_estado" name="cst_estado" onchange="document.getElementById('cst_ocupado_fields').style.display=this.value==='abierto_ocupado'?'':'none';">
							<option value="abierto_disponible" <?php selected( $estado, 'abierto_disponible' ); ?>><?php esc_html_e( '🟡 Pendiente (Abierto)', 'convoca-shifts' ); ?></option>
							<option value="abierto_ocupado" <?php selected( $estado, 'abierto_ocupado' ); ?>><?php esc_html_e( '🔵 Ocupado (Actividad)', 'convoca-shifts' ); ?></option>
							<option value="cerrado" <?php selected( $estado, 'cerrado' ); ?>><?php esc_html_e( '🔴 Cerrado', 'convoca-shifts' ); ?></option>
						</select>
					</div>

					<div></div>

					<div id="cst_ocupado_fields" style="grid-column:1/-1;<?php echo $estado === 'abierto_ocupado' ? '' : 'display:none;'; ?> background:var(--wp--preset--color--gris-piedra,#f4f4f4);padding:20px;border-radius:8px;">
						<div class="convoca-grid-2">
							<div class="convoca-field">
								<label for="cst_actividad_term"><?php esc_html_e( 'Actividad', 'convoca-shifts' ); ?></label>
								<select id="cst_actividad_term" name="cst_actividad_term">
									<option value="0"><?php esc_html_e( '— Seleccionar —', 'convoca-shifts' ); ?></option>
									<?php foreach ( $all_actividades as $term ) : ?>
										<option value="<?php echo (int) $term->term_id; ?>" <?php selected( $current_actividad, $term->term_id ); ?>>
											<?php echo esc_html( $term->name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="convoca-field">
								<label for="cst_monitor_select"><?php esc_html_e( 'Monitor/a', 'convoca-shifts' ); ?></label>
								<select id="cst_monitor_select" name="cst_monitor_user">
									<option value="0"><?php esc_html_e( '— Sin monitor —', 'convoca-shifts' ); ?></option>
									<?php foreach ( $all_monitores as $mu ) : ?>
										<option value="<?php echo (int) $mu->ID; ?>" <?php selected( $current_monitor, $mu->ID ); ?>>
											<?php echo esc_html( $mu->display_name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
					</div>

					<div class="convoca-field" style="grid-column:1/-1;">
						<div class="convoca-check-group">
							<input type="checkbox" id="cst_necesita_apoyo" name="cst_necesita_apoyo" value="1" <?php checked( $apoyo, 1 ); ?>>
							<label for="cst_necesita_apoyo"><?php esc_html_e( '🛟 Necesita apoyo (sin llaves / acompañamiento)', 'convoca-shifts' ); ?></label>
						</div>
					</div>

					<h3 style="grid-column:1/-1;margin-top:1rem;padding-bottom:.5rem;border-bottom:1px solid var(--bde-border,#ccc);">
						<?php esc_html_e( 'Seguimiento', 'convoca-shifts' ); ?>
					</h3>

					<div class="convoca-field" style="grid-column:1/-1;">
						<label for="cst_estado_real"><?php esc_html_e( 'Estado de asistencia', 'convoca-shifts' ); ?></label>
						<select id="cst_estado_real" name="cst_estado_real">
							<option value="pendiente" <?php selected( $estado_real, 'pendiente' ); ?>><?php esc_html_e( '⏳ Pendiente', 'convoca-shifts' ); ?></option>
							<option value="realizado" <?php selected( $estado_real, 'realizado' ); ?>><?php esc_html_e( '✅ Realizado', 'convoca-shifts' ); ?></option>
							<option value="no_asistio" <?php selected( $estado_real, 'no_asistio' ); ?>><?php esc_html_e( '❌ No asistió', 'convoca-shifts' ); ?></option>
						</select>
					</div>
				</div>

				<div style="margin-top:40px;display:flex;justify-content:flex-end;gap:15px;align-items:center;">
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=centro_turno' ) ); ?>" class="convoca-btn convoca-btn-outline">
						&larr; <?php esc_html_e( 'Volver al listado', 'convoca-shifts' ); ?>
					</a>
					<button type="submit" class="convoca-btn convoca-btn-primary">
						<?php echo $is_edit ? esc_html__( 'Guardar cambios', 'convoca-shifts' ) : esc_html__( 'Crear turno', 'convoca-shifts' ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Process the form submission.
	 */
	public function handle_save(): void {
		$data = wp_unslash( $_POST );

		$post_id = (int) ( $data['post_id'] ?? 0 );
		$is_edit = $post_id > 0;

		if ( ! isset( $data['_cst_nonce'] ) ) {
			wp_die( __( 'Acceso denegado.', 'convoca-shifts' ) );
		}
		if ( ! wp_verify_nonce( $data['_cst_nonce'], 'cst_save_turno_' . $post_id ) ) {
			wp_die( __( 'Nonce inválido.', 'convoca-shifts' ) );
		}

		if ( $is_edit && ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( __( 'No tienes permisos para editar este turno.', 'convoca-shifts' ) );
		} elseif ( ! $is_edit && ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'No tienes permisos para crear turnos.', 'convoca-shifts' ) );
		}

		$fecha          = sanitize_text_field( $data['cst_fecha'] ?? '' );
		$hora_ini       = sanitize_text_field( $data['cst_hora_ini'] ?? '' );
		$hora_fin       = sanitize_text_field( $data['cst_hora_fin'] ?? '' );
		$estado         = sanitize_text_field( $data['cst_estado'] ?? 'abierto_disponible' );
		$estado_real    = sanitize_text_field( $data['cst_estado_real'] ?? 'pendiente' );
		$id_responsable = (int) ( $data['cst_id_responsable'] ?? 0 );
		$necesita_apoyo = isset( $data['cst_necesita_apoyo'] ) ? 1 : 0;

		$datetime_str = $fecha . ' ' . $hora_ini . ':00';

		if ( $is_edit ) {
			// Update existing post.
			wp_update_post(
				array(
					'ID'            => $post_id,
					'post_date'     => $datetime_str,
					'post_date_gmt' => get_gmt_from_date( $datetime_str ),
				)
			);
		} else {
			// Create new.
			$title   = '🟡 Pendiente';
			$post_id = wp_insert_post(
				array(
					'post_type'     => 'centro_turno',
					'post_title'    => $title,
					'post_date'     => $datetime_str,
					'post_date_gmt' => get_gmt_from_date( $datetime_str ),
					'post_status'   => 'publish',
				)
			);
			if ( is_wp_error( $post_id ) ) {
				wp_die( __( 'Error al crear el turno.', 'convoca-shifts' ) );
			}
		}

		// Save meta.
		update_post_meta( $post_id, '_hora_fin', $hora_fin );
		update_post_meta( $post_id, '_estado', $estado );
		update_post_meta( $post_id, '_estado_real', $estado_real );
		update_post_meta( $post_id, '_necesita_apoyo', $necesita_apoyo );
		update_post_meta( $post_id, '_id_responsable', $id_responsable );

		// Save taxonomies.
		$actividad_term = (int) ( $data['cst_actividad_term'] ?? 0 );
		wp_set_post_terms( $post_id, $actividad_term > 0 ? array( $actividad_term ) : array(), 'cst_actividad' );

		$monitor_user = (int) ( $data['cst_monitor_user'] ?? 0 );
		update_post_meta( $post_id, '_monitor', $monitor_user > 0 ? $monitor_user : '' );

		// Sync the post (title, status) using existing function.
		if ( function_exists( 'cst_sync_turno_on_save' ) ) {
			$post = get_post( $post_id );
			cst_sync_turno_on_save( $post_id, $post, $is_edit );
		}

		// Log the activity.
		if ( function_exists( 'cst_log_activity' ) ) {
			$action_type = $is_edit ? 'turno_actualizado' : 'turno_creado';
			cst_log_activity(
				get_current_user_id(),
				$post_id,
				$action_type,
				array(
					'origen' => 'editor_custom',
					'estado' => $estado,
				)
			);
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=centro_turno&page=' . self::SLUG . '&id=' . $post_id ) );
		exit;
	}
}


