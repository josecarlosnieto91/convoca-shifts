<?php
/**
 * Convoca Shifts - shortcodes
 *
 * @package Convoca_Shifts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Hook to register scripts properly.
add_action( 'init', 'cst_register_scripts' );

function cst_register_scripts() {
	wp_register_style( 'cst-estilo', CST_PLUGIN_URL . 'assets/css/estilo.css', array(), CST_PLUGIN_VERSION );
	wp_enqueue_style( 'dashicons' ); // Needed for buttons/icons.
	wp_register_script( 'fullcalendar-core', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js', array(), '6.1.15', true ); .
	wp_register_script( 'cst-calendario', CST_PLUGIN_URL . 'assets/js/calendario.js', array( 'fullcalendar-core', 'jquery' ), CST_PLUGIN_VERSION, true );

	wp_localize_script(
		'cst-calendario',
		'cstData',
		array(
			'restUrl'        => esc_url_raw( rest_url( 'centro/v1/turnos' ) ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'isLogged'       => is_user_logged_in(),
			'userId'         => get_current_user_id(),
			'canManage'      => current_user_can( 'gestionar_mis_turnos' ) || current_user_can( 'manage_options' ),
			'msgConfirm'     => __( '¿Quieres cubrir este turno?', 'convoca-shifts' ),
			'msgCancel'      => __( '¿Quieres dejar este turno libre?', 'convoca-shifts' ),
			'msgError'       => __( 'Hubo un error, por favor recarga la página.', 'convoca-shifts' ),
			'confirmLiberar' => __( '¿Quieres liberar este turno? Si lo haces, otra persona podrá cubrirlo.', 'convoca-shifts' ),
			'errorCrear'     => __( 'Error al crear el turno.', 'convoca-shifts' ),
			'confirmSignup'  => apply_filters( 'cst_confirm_signup', true ),
			'horaApertura'   => get_option( 'cst_hora_apertura', '09:00' ),
			'horaCierre'     => get_option( 'cst_hora_cierre', '22:00' ),
			'msgAdjusted'    => __( 'Este horario se sale del margen permitido del centro y ha sido ajustado.', 'convoca-shifts' ),
			'msgNoEvents'    => __( 'Aún no hay turnos definidos para esta vista.', 'convoca-shifts' ),
		)
	);
}

// Shortcode: [calendario_centro].
add_shortcode( 'convoca_calendario', 'cst_shortcode_calendario_centro' );
function cst_shortcode_calendario_centro() {
	wp_enqueue_style( 'cst-estilo' );
	wp_enqueue_script( 'cst-calendario' );

	ob_start();

	if ( ! is_user_logged_in() ) {
		$access_url = get_option( 'cst_access_page_url', '#' );
		echo '<div class="cst-public-notice">';
		echo '<p>💡 ' . __( 'Este es el cuadrante actual. Si eres voluntario/a, inicia sesión para apuntarte a un turno.', 'convoca-shifts' ) . ' <a href="' . esc_url( $access_url ) . '">' . __( 'Ir a Acceso', 'convoca-shifts' ) . '</a></p>';
		echo '</div>';
	} elseif ( is_user_logged_in() && ( ! current_user_can( 'gestionar_mis_turnos' ) && ! current_user_can( 'manage_options' ) ) ) {
		echo '<div class="cst-public-notice warning">';
		echo '<p>⏳ ' . __( 'Tu cuenta está pendiente de aprobación. Podrás apuntarte a turnos en cuanto un administrador te active.', 'convoca-shifts' ) . '</p>';
		echo '</div>';
	}
	?>
	<div id="cst-calendario-container" class="cst-calendario-wrapper">
		<div class="cst-leyenda">
			<span class="cst-leyenda-item"><span class="cst-dot cst-dot-green"></span> Cubierto</span>
			<span class="cst-leyenda-item"><span class="cst-dot cst-dot-yellow"></span> Pendiente</span>
			<span class="cst-leyenda-item"><span class="cst-dot cst-dot-blue"></span> Actividad interna</span>
			<span class="cst-leyenda-item"><span class="cst-dot cst-dot-red"></span> Cerrado</span>
		</div>
		<div id="cst-calendario"></div>
	</div>
	<?php
	// Register footer modal output only once.
	static $modal_added = false;
	if ( ! $modal_added ) {
		$modal_added = true;
		add_action( 'wp_footer', 'cst_render_frontend_modal' );
	}

	return ob_get_clean();
}

/**
 * Render the modal in the footer to avoid transform/containment issues.
 */
function cst_render_frontend_modal() {
	if ( ! is_user_logged_in() || ( ! current_user_can( 'gestionar_mis_turnos' ) && ! current_user_can( 'manage_options' ) ) ) {
		return;
	}
	?>
	<!-- Modal for Frontend Creation (Moved to footer for proper fixed positioning) -->
	<div id="cst-frontend-modal" class="cst-modal" style="display:none;">
		<div class="cst-modal-content">
			<span class="cst-close-frontend" style="float:right; cursor:pointer; font-size:24px;">&times;</span>
			<h2 id="cst-fe-modal-title"><?php _e( 'Crear Nuevo Turno', 'convoca-shifts' ); ?></h2>
			<hr>
			<div class="cst-form-group">
				<label><?php _e( 'Seleccionar Horario', 'convoca-shifts' ); ?></label>
				<div class="cst-presets">
					<button type="button" class="cst-preset-btn" data-start="10:00" data-end="13:00">☀️ <?php _e( 'Mañana (10-13h)', 'convoca-shifts' ); ?></button>
					<button type="button" class="cst-preset-btn" data-start="17:00" data-end="20:00">🌇 <?php _e( 'Tarde (17-20h)', 'convoca-shifts' ); ?></button>
					<button type="button" class="cst-preset-btn" data-custom="1">⚙️ <?php _e( 'Personalizado', 'convoca-shifts' ); ?></button>
				</div>
			</div>

			<div id="cst-custom-time-fields" style="display:none; margin-top:15px; padding:15px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0;">
				<div class="cst-form-row" style="margin-bottom:0;">
					<div class="cst-form-group" style="margin-bottom:0;">
						<label><?php _e( 'Hora Inicio', 'convoca-shifts' ); ?></label>
						<input type="time" id="fe_h_start" value="10:00" step="900">
					</div>
					<div class="cst-form-group" style="margin-bottom:0;">
						<label><?php _e( 'Hora Fin', 'convoca-shifts' ); ?></label>
						<input type="time" id="fe_h_end" value="13:00" step="900">
					</div>
				</div>
			</div>

			<div class="cst-form-group">
				<label><?php _e( 'Estado', 'convoca-shifts' ); ?></label>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<select id="fe_estado" style="width:100%;">
					<option value="abierto_disponible">🟡 <?php _e( 'Pendiente', 'convoca-shifts' ); ?></option>
					<option value="cerrado">🔴 <?php _e( 'Cerrado', 'convoca-shifts' ); ?></option>
				</select>
				<?php else : ?>
				<select id="fe_estado" style="width:100%; pointer-events: none; background: #f1f5f9;">
					<option value="abierto_disponible">🟡 <?php _e( 'Pendiente (Disponible para voluntarios)', 'convoca-shifts' ); ?></option>
				</select>
				<?php endif; ?>
			</div>
			<div class="cst-form-group">
				<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
					<input type="checkbox" id="fe_apoyo"> 
					<span>🛟 <?php _e( 'Necesita apoyo', 'convoca-shifts' ); ?></span>
				</label>
			</div>
			<div class="cst-modal-footer">
				<button type="button" class="cst-fe-cancel biodevas-btn biodevas-btn-outline"><?php _e( 'Cancelar', 'convoca-shifts' ); ?></button>
				<button type="button" id="cst-fe-save" class="biodevas-btn biodevas-btn-primary"><?php _e( 'Guardar Turno', 'convoca-shifts' ); ?></button>
			</div>
		</div>
	</div>
	<?php
}

// Shortcode: [boton_apuntarse].
add_shortcode( 'convoca_boton_apuntarse', 'cst_shortcode_boton_apuntarse' );
function cst_shortcode_boton_apuntarse() {
	if ( ! is_user_logged_in() || ( ! current_user_can( 'gestionar_mis_turnos' ) && ! current_user_can( 'manage_options' ) ) ) {
		return '';
	}

	wp_enqueue_script( 'cst-calendario' ); // reuse the script for data.
	wp_enqueue_style( 'cst-estilo' );

	ob_start();
	?>
	<div class="cst-boton-apuntarse-container">
		<button id="cst-btn-proximo" class="cst-btn-proximo">
			<span class="dashicons dashicons-calendar-alt"></span>
			<?php _e( 'Me apunto al siguiente turno libre', 'convoca-shifts' ); ?>
		</button>
		<div id="cst-proximo-msg" class="cst-msg" style="display:none;"></div>
	</div>
	<script>
	jQuery(document).ready(function($) {
		$('#cst-btn-proximo').on('click', function(e) {
			e.preventDefault();
			if (cstData.confirmSignup && !confirm('¿Te apuntas a este turno? Serás responsable de abrir el centro.')) {
				return;
			}

			var $btn = $(this);
			var $msg = $('#cst-proximo-msg');
			
			$btn.prop('disabled', true).text('Buscando turno...');
			
			$.ajax({
				url: cstData.restUrl + '/apuntarse-proximo',
				type: 'POST',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', cstData.nonce);
				},
				success: function(response) {
					$btn.hide();
					$msg.removeClass('cst-error').addClass('cst-success').text(response.message).show();
					// if calendar is on page, refetch.
					if (window.cstCalendarInstance) {
						window.cstCalendarInstance.refetchEvents();
					}
				},
				error: function(xhr) {
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-calendar-alt"></span> Me apunto al siguiente turno libre');
					var res = xhr.responseJSON;
					$msg.removeClass('cst-success').addClass('cst-error').text((res && res.message) ? res.message : 'Error.').show();
				}
			});
		});
	});
	</script>
	<?php
	return ob_get_clean();
}

// Shortcode: [resumen_turnos].
add_shortcode( 'convoca_resumen_turnos', 'cst_shortcode_resumen_turnos' );
function cst_shortcode_resumen_turnos( $atts = array() ) {
	$atts = shortcode_atts( array( 'semana' => 'this' ), $atts );
	wp_enqueue_style( 'cst-estilo' );
	wp_enqueue_script( 'cst-calendario' );

	$week_modifier = $atts['semana'] === 'next' ? 'next week' : 'this week';
	$transient_key = 'cst_resumen_turnos_' . ( $atts['semana'] === 'next' ? 'next' : 'this' );
	$resumen       = get_transient( $transient_key );

	if ( false === $resumen ) {
		$start_of_week = wp_date( 'Y-m-d 00:00:00', strtotime( 'monday ' . $week_modifier ) );
		$end_of_week   = wp_date( 'Y-m-d 23:59:59', strtotime( 'sunday ' . $week_modifier ) );

		$args = array(
			'post_type'      => 'centro_turno',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'date_query'     => array(
				array(
					'after'     => $start_of_week,
					'before'    => $end_of_week,
					'inclusive' => true,
				),
			),
			'meta_query'     => array(
				array(
					'key'     => '_estado',
					'value'   => 'cerrado',
					'compare' => '!=',
				),
			),
		);

		$turnos     = get_posts( $args );
		$total      = count( $turnos );
		$sin_cubrir = 0;

		foreach ( $turnos as $turno ) {
			$responsable_id = (int) get_post_meta( $turno->ID, '_id_responsable', true );
			if ( $responsable_id === 0 ) {
				++$sin_cubrir;
			}
		}

		$resumen = array(
			'total'      => $total,
			'sin_cubrir' => $sin_cubrir,
		);

		set_transient( $transient_key, $resumen, HOUR_IN_SECONDS );
	}

	ob_start();
	?>
	<div class="cst-resumen-wrapper">
		<div class="cst-resumen-turnos">
			<?php if ( $resumen['total'] > 0 ) : ?>
				📅 Esta semana hay <strong><?php echo $resumen['sin_cubrir']; ?> turnos sin cubrir</strong> de <?php echo $resumen['total']; ?> en total.
			<?php else : ?>
				📅 No hay turnos definidos para esta semana.
			<?php endif; ?>
		</div>
		
		<?php
		// Mini list of gaps for the current week.
		$start_of_week = wp_date( 'Y-m-d 00:00:00', strtotime( 'monday this week' ) );
		$end_of_week   = wp_date( 'Y-m-d 23:59:59', strtotime( 'sunday this week' ) );
		$gaps          = get_posts(
			array(
				'post_type'      => 'centro_turno',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'date_query'     => array(
					array(
						'after'     => $start_of_week,
						'before'    => $end_of_week,
						'inclusive' => true,
					),
				),
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => '_id_responsable',
						'value'   => 0,
						'compare' => '=',
					),
					array(
						'key'     => '_estado',
						'value'   => 'abierto_disponible',
						'compare' => '=',
					),
				),
				'orderby'        => 'date',
				'order'          => 'ASC',
			)
		);

		if ( ! empty( $gaps ) ) :
			echo '<div class="cst-resumen-gaps"><strong>' . __( 'Huecos libres:', 'convoca-shifts' ) . '</strong> ';
			$gap_strings = array();
			foreach ( $gaps as $gap ) {
				$gap_strings[] = cst_fecha_corta( get_post_timestamp( $gap ) ) . ' (' . esc_html( get_the_time( 'H:i', $gap ) ) . ')';
			}
			echo implode( ', ', $gap_strings );
			echo '</div>';
		endif;
		?>

		<div class="cst-proximo-libre">
			<!-- Populated via AJAX on load -->
			Cargando próximo turno...
		</div>
	</div>
	<?php
	return ob_get_clean();
}

// Shortcode: [proximos_turnos cantidad="5"].
add_shortcode( 'convoca_proximos_turnos', 'cst_shortcode_proximos_turnos' );
function cst_shortcode_proximos_turnos( $atts ) {
	$atts = shortcode_atts(
		array(
			'cantidad' => 5,
		),
		$atts
	);

	$now  = current_time( 'mysql' );
	$args = array(
		'post_type'      => 'centro_turno',
		'posts_per_page' => $atts['cantidad'],
		'post_status'    => 'publish',
		'orderby'        => 'date',
		'order'          => 'ASC',
		'date_query'     => array(
			array(
				'after' => $now,
			),
		),
		'meta_query'     => array(
			array(
				'key'     => '_estado',
				'value'   => 'cerrado',
				'compare' => '!=',
			),
		),
	);

	$turnos = get_posts( $args );
	if ( empty( $turnos ) ) {
		return '<p>' . __( 'No hay turnos próximos programados.', 'convoca-shifts' ) . '</p>';
	}

	ob_start();
	echo '<ul class="cst-proximos-lista">';
	foreach ( $turnos as $turno ) {
		$responsable_id = (int) get_post_meta( $turno->ID, '_id_responsable', true );
		$ts             = get_post_timestamp( $turno );
		$date           = cst_fecha_corta( $ts );
		$time           = get_the_time( 'H:i', $turno );

		$responsable_texto = '';
		if ( $responsable_id > 0 ) {
			$user = get_userdata( $responsable_id );
			if ( $user ) {
				$nombre_mostrar    = ! empty( $user->first_name ) ? $user->first_name : $user->display_name;
				$responsable_texto = ' — 👤 ' . $nombre_mostrar;
			}
		} else {
			$responsable_texto = ' — <span style="color:#d35400; font-weight:bold;">⚠️ ' . __( 'Sin cubrir', 'convoca-shifts' ) . '</span>';
		}

		echo '<li style="margin-bottom:8px; padding-bottom:8px; border-bottom:1px solid #eee;">';
		echo '📅 <strong>' . esc_html( $date ) . '</strong> a las ' . esc_html( $time );
		echo esc_html( $responsable_texto );
		echo '</li>';
	}
	echo '</ul>';
	return ob_get_clean();
}

// Clear transient when a turno is saved/assigned.
add_action( 'save_post_centro_turno', 'cst_clear_resumen_transient' );
function cst_clear_resumen_transient() {
	delete_transient( 'cst_resumen_turnos_semana' );
}

// Clear transient on assign/unassign API calls.
add_action(
	'rest_api_init',
	function () {
		add_action( 'rest_after_insert_centro_turno', 'cst_clear_resumen_transient' );
	}
);
// Since we manually update meta in REST, let's hook onto the update meta action to clear transient.
add_action(
	'updated_post_meta',
	function ( $meta_id, $post_id, $meta_key, $meta_value ) {
		if ( ( '_id_responsable' === $meta_key || '_estado' === $meta_key ) && get_post_type( $post_id ) === 'centro_turno' ) {
			delete_transient( 'cst_resumen_turnos_semana' );
		}
	},
	10,
	4
);
