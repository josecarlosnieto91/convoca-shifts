<?php
/**
 * Quick Turno Addition Interface (Backend Calendar).
 *
 * @package CentroSocialTurnos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'cst_add_turno_rapido_menu', 1 );
function cst_add_turno_rapido_menu() {
	// 1. Add our custom page.
	add_submenu_page(
		'edit.php?post_type=centro_turno',
		__( 'Añadir Turno Rápido', 'convoca-shifts' ),
		__( 'Añadir Turno Rápido', 'convoca-shifts' ),
		'cst_manage_turnos',
		'cst_turno_rapido',
		'cst_turno_rapido_page'
	);

	// 2. Remove the standard "Add New" submenu to avoid confusion.
	remove_submenu_page( 'edit.php?post_type=centro_turno', 'post-new.php?post_type=centro_turno' );
}

/**
 * Filter admin URLs to point "Add New" links to our custom page.
 * This affects the button next to the title in the list and the admin bar.
 */
add_filter( 'admin_url', 'cst_redirect_add_new_url', 10, 2 );
function cst_redirect_add_new_url( $url, $path ) {
	if ( $path === 'post-new.php?post_type=centro_turno' ) {
		return admin_url( 'admin.php?page=cst_turno_rapido' );
	}
	return $url;
}

/**
 * Force redirect if anyone accesses the standard post-new.php page directly via URL.
 */
add_action( 'admin_init', 'cst_force_redirect_standard_editor' );
function cst_force_redirect_standard_editor() {
	global $pagenow;
	if ( $pagenow === 'post-new.php' && isset( $_GET['post_type'] ) && $_GET['post_type'] === 'centro_turno' ) {
		wp_redirect( admin_url( 'admin.php?page=cst_turno_rapido' ) );
		exit;
	}
}

/**
 * Handle the form submission for Quick Add Turno via admin-post.php.
 */
add_action( 'admin_post_cst_quick_add_turno', 'cst_process_quick_add_turno' );
function cst_process_quick_add_turno() {
	if ( ! isset( $_POST['cst_quick_add_nonce'] ) || ! wp_verify_nonce( $_POST['cst_quick_add_nonce'], 'cst_quick_add_action' ) ) {
		wp_safe_redirect( admin_url( 'edit.php?post_type=centro_turno&cst_msg=error&cst_err=' . urlencode( __( 'Nonce inválido.', 'convoca-shifts' ) ) ) );
		exit;
	}

	if ( ! current_user_can( 'cst_manage_turnos' ) ) {
		wp_die( __( 'No tienes permisos para realizar esta acción.', 'convoca-shifts' ) );
	}

	$date    = sanitize_text_field( $_POST['cst_date'] );
	$h_start = sanitize_text_field( $_POST['cst_h_start'] );
	$h_end   = sanitize_text_field( $_POST['cst_h_end'] );
	$estado  = sanitize_text_field( $_POST['cst_estado'] );
	$apoyo   = isset( $_POST['cst_apoyo'] ) ? 1 : 0;

	$post_id = cst_insert_turno(
		array(
			'date'           => $date,
			'h_start'        => $h_start,
			'h_end'          => $h_end,
			'estado'         => $estado,
			'necesita_apoyo' => $apoyo,
		)
	);

	if ( ! is_wp_error( $post_id ) ) {
		if ( function_exists( 'cst_log_activity' ) ) {
			cst_log_activity( get_current_user_id(), $post_id, 'turno_creado', array( 'origen' => 'admin_rapido' ) );
		}

		if ( class_exists( 'Biodevas\\Common\\Logger' ) ) {
			\Convoca\Core\Logger::info( "Turno rápido creado para el día $date ($h_start - $h_end)", 'Turnos', $post_id );
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=centro_turno&cst_msg=created' ) );
		exit;
	} else {
		wp_safe_redirect( admin_url( 'edit.php?post_type=centro_turno&cst_msg=error&cst_err=' . urlencode( $post_id->get_error_message() ) ) );
		exit;
	}
}

/**
 * Handle success message in the main list.
 */
add_action( 'admin_notices', 'cst_turno_rapido_notices' );
function cst_turno_rapido_notices() {
	$screen = get_current_screen();
	if ( $screen && $screen->id === 'edit-centro_turno' && isset( $_GET['cst_msg'] ) ) {
		if ( $_GET['cst_msg'] === 'created' ) {
			echo '<div class="biodevas-alert biodevas-alert--success" style="display:block;margin-bottom:20px;"><p>' . __( 'Turno creado correctamente.', 'convoca-shifts' ) . '</p></div>';
		} elseif ( $_GET['cst_msg'] === 'error' ) {
			$err = isset( $_GET['cst_err'] ) ? sanitize_text_field( $_GET['cst_err'] ) : __( 'Error desconocido', 'convoca-shifts' );
			echo '<div class="biodevas-alert biodevas-alert--danger" style="display:block;margin-bottom:20px;"><p><strong>Error:</strong> ' . esc_html( $err ) . '</p></div>';
		}
	}
}

/**
 * Render the Quick Add page.
 */
function cst_turno_rapido_page() {
	?>
	<div class="wrap">
		<h1><?php _e( 'Añadir Turno Rápido', 'convoca-shifts' ); ?></h1>
		<div class="biodevas-alert biodevas-alert--info" style="display:block;margin-bottom:20px;">
			<p><?php _e( 'Selecciona un día en el calendario para crear un turno sin pasar por la pantalla de edición estándar.', 'convoca-shifts' ); ?></p>
		</div>
		
		<div id="cst-admin-calendar-container" class="card" style="padding: 20px; max-width: 800px;">
<div id="cst-calendar-controls" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
					<button type="button" id="cst-prev-month" class="biodevas-btn biodevas-btn-outline">&laquo; Mes anterior</button>
					<h2 id="cst-calendar-month-year" style="margin:0;"></h2>
					<button type="button" id="cst-next-month" class="biodevas-btn biodevas-btn-outline">Mes siguiente &raquo;</button>
				</div>
			<div id="cst-calendar-grid"></div>
		</div>

		</div>
		<?php
		add_action( 'admin_footer', 'cst_render_quick_add_modal' );
		?>
</div>
		<?php
		add_action( 'admin_footer', 'cst_render_quick_add_modal' );
		?>
	</div>

	<script>
	document.addEventListener('DOMContentLoaded', function() {
		const grid = document.getElementById('cst-calendar-grid');
		const monthYearLabel = document.getElementById('cst-calendar-month-year');
		const modal = document.getElementById('cst-quick-modal');
		const closeBtn = document.querySelector('.cst-close');
		const cancelBtn = document.querySelector('.cst-cancel-btn');
		const dateInput = document.getElementById('cst_modal_date');
		const modalTitle = document.getElementById('cst-modal-title');

		let viewDate = new Date();
		viewDate.setDate(1);

		const monthNames = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

		function renderCalendar() {
			const month = viewDate.getMonth();
			const year = viewDate.getFullYear();
			monthYearLabel.innerText = `${monthNames[month]} ${year}`;

			let html = '<table><thead><tr>';
			['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'].forEach(d => html += `<th>${d}</th>`);
			html += '</tr></thead><tbody><tr>';

			const firstDay = (new Date(year, month, 1).getDay() + 6) % 7; // Monday start.
			const daysInMonth = new Date(year, month + 1, 0).getDate();
			
			const today = new Date();
			today.setHours(0, 0, 0, 0);
			const todayTime = today.getTime();

			for (let i = 0; i < firstDay; i++) {
				html += '<td class="cst-empty"></td>';
			}

			for (let day = 1; day <= daysInMonth; day++) {
				if ((day + firstDay - 1) % 7 === 0 && day > 1) html += '<tr>';
				
				const dateObj = new Date(year, month, day);
				dateObj.setHours(0, 0, 0, 0);
				const dateTime = dateObj.getTime();
				
				const dateStr = `${year}-${String(month+1).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
				
				let classes = [];
				const isToday = dateTime === todayTime;
				const isPast = dateTime < todayTime;
				const isFuture = dateTime > todayTime;
				const isWeekend = (dateObj.getDay() === 0 || dateObj.getDay() === 6); // 0=Sun, 6=Sat

				if (isToday) classes.push('cst-today');
				else if (isPast) classes.push('cst-past-day');
				else if (isFuture) classes.push('cst-future-day');
				
				if (isWeekend) classes.push('cst-weekend');
				
				const isClickable = isToday || isFuture;
				
				html += `<td class="${classes.join(' ')}" data-date="${dateStr}" ${ !isClickable ? 'style="pointer-events:none;"' : '' }>
							<span class="cst-day-num">${day}</span>
							<div class="cst-day-content"></div>
						</td>`;

				if ((day + firstDay) % 7 === 0) html += '</tr>';
			}
			
			html += '</tbody></table>';
			grid.innerHTML = html;

			// Add Click listeners.
			grid.querySelectorAll('td:not(.cst-empty)').forEach(td => {
				td.onclick = function() {
					const date = this.dataset.date;
					dateInput.value = date;
					const d = new Date(date);
					modalTitle.innerText = `Añadir Turno: ${d.toLocaleDateString('es-ES', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}`;
					
					// Reset presets.
					document.querySelectorAll('.cst-preset-btn').forEach(b => b.classList.remove('active'));
					const morningBtn = document.querySelector('.cst-preset-btn[data-start="10:00"]');
					if (morningBtn) morningBtn.classList.add('active');
					
					document.getElementById('cst-custom-time-fields').style.display = 'none';
					document.getElementById('fe_h_start').value = '10:00';
					document.getElementById('fe_h_end').value = '13:00';

					modal.classList.add('is-active');
				};
			});

			// Preset logic (Admin).
			document.querySelectorAll('.cst-preset-btn').forEach(btn => {
				btn.addEventListener('click', function() {
					document.querySelectorAll('.cst-preset-btn').forEach(b => b.classList.remove('active'));
					this.classList.add('active');

					const isCustom = this.dataset.custom;
					const customFields = document.getElementById('cst-custom-time-fields');
					if (isCustom) {
						customFields.style.display = 'block';
					} else {
						customFields.style.display = 'none';
						let start = this.dataset.start;
						let end = this.dataset.end;
						const limitOpen = '<?php echo esc_js( get_option( 'cst_hora_apertura', '09:00' ) ); ?>';
						const limitClose = '<?php echo esc_js( get_option( 'cst_hora_cierre', '22:00' ) ); ?>';
						
						let adjusted = false;
						if (start < limitOpen) { start = limitOpen; adjusted = true; }
						if (end > limitClose) { end = limitClose; adjusted = true; }
						
						if (adjusted) {
							alert('Este horario predefinido se sale del margen permitido del centro y ha sido ajustado.');
						}

						document.getElementById('fe_h_start').value = start;
						document.getElementById('fe_h_end').value = end;
					}
				});
			});
		}

		document.getElementById('cst-prev-month').onclick = () => {
			viewDate.setMonth(viewDate.getMonth() - 1);
			renderCalendar();
		};
		document.getElementById('cst-next-month').onclick = () => {
			viewDate.setMonth(viewDate.getMonth() + 1);
			renderCalendar();
		};

		const closeModal = () => modal.classList.remove('is-active');
		closeBtn.onclick = closeModal;
		cancelBtn.onclick = closeModal;
		window.onclick = (e) => { if (e.target == modal) closeModal(); };

		// Form validation (Admin Quick Add).
		const form = modal.querySelector('form');
		form.onsubmit = function(e) {
			let h_start = document.getElementById('fe_h_start').value;
			let h_end = document.getElementById('fe_h_end').value;
			const limitOpen = '<?php echo esc_js( get_option( 'cst_hora_apertura', '09:00' ) ); ?>';
			const limitClose = '<?php echo esc_js( get_option( 'cst_hora_cierre', '22:00' ) ); ?>';
			
			let adjusted = false;
			if (h_start < limitOpen) {
				h_start = limitOpen;
				adjusted = true;
			}
			if (h_end > limitClose) {
				h_end = limitClose;
				adjusted = true;
			}

			if (h_start >= h_end) {
				alert('La hora de fin debe ser posterior a la de inicio.');
				e.preventDefault();
				return;
			}

			if (adjusted) {
				alert('El horario del centro es de ' + limitOpen + 'h a ' + limitClose + 'h. Se ha ajustado el turno automáticamente.');
				document.getElementById('fe_h_start').value = h_start;
				document.getElementById('fe_h_end').value = h_end;
				e.preventDefault();
				return;
			}
		};

		renderCalendar();
	});
	</script>
	<?php
	add_action( 'admin_footer', 'cst_render_quick_add_modal' );
}

/**
 * Render the Quick Add modal in the footer.
 */
function cst_render_quick_add_modal() {
	?>
	<!-- Modal Form (Moved to footer) -->
	<div id="cst-quick-modal" class="cst-modal">
		<div class="cst-modal-content">
			<span class="cst-close">&times;</span>
			<h2 id="cst-modal-title"><?php _e( 'Crear Nuevo Turno', 'convoca-shifts' ); ?></h2>
			<hr>
			<form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
				<?php wp_nonce_field( 'cst_quick_add_action', 'cst_quick_add_nonce' ); ?>
				<input type="hidden" name="action" value="cst_quick_add_turno">
				<input type="hidden" id="cst_modal_date" name="cst_date">
				
				<div class="cst-form-group">
					<label><?php _e( 'Seleccionar Horario', 'convoca-shifts' ); ?></label>
					<div class="cst-presets">
						<button type="button" class="cst-preset-btn" data-start="10:00" data-end="13:00">☀️ <?php _e( 'Mañana (10-13h)', 'convoca-shifts' ); ?></button>
						<button type="button" class="cst-preset-btn" data-start="17:00" data-end="20:00">🌇 <?php _e( 'Tarde (17-20h)', 'convoca-shifts' ); ?></button>
						<button type="button" class="cst-preset-btn" data-custom="1">⚙️ <?php _e( 'Personalizado', 'convoca-shifts' ); ?></button>
					</div>
				</div>

				<div id="cst-custom-time-fields" style="display:none; margin-top:15px; padding:15px; background:var(--wp--preset--color--blanco, #ffffff); border-radius:8px; border:1px solid var(--wp--preset--color--gris-medio, #e0e0e0);">
					<div class="cst-form-row" style="margin-bottom:0;">
						<div class="cst-form-group" style="margin-bottom:0;">
							<label><?php _e( 'Hora Inicio', 'convoca-shifts' ); ?></label>
							<input type="time" name="cst_h_start" id="fe_h_start" value="10:00" step="900">
						</div>
						<div class="cst-form-group" style="margin-bottom:0;">
							<label><?php _e( 'Hora Fin', 'convoca-shifts' ); ?></label>
							<input type="time" name="cst_h_end" id="fe_h_end" value="13:00" step="900">
						</div>
					</div>
				</div>

				<div class="cst-form-group">
					<label><?php _e( 'Estado Inicial', 'convoca-shifts' ); ?></label>
					<select name="cst_estado" style="width: 100%;">
						<option value="abierto_disponible">🟡 Pendiente (Disponible para voluntarios)</option>
						<option value="abierto_ocupado">🔵 Ocupado (Actividad interna / No inscribible)</option>
						<option value="cerrado">🔴 Cerrado (Festivo / Sin apertura)</option>
					</select>
				</div>

				<div class="cst-form-group" style="margin-top: 15px;">
					<label>
						<input type="checkbox" name="cst_apoyo" value="1">
						<strong><?php _e( '🛟 Necesita apoyo', 'convoca-shifts' ); ?></strong>
					</label>
					<p class="description"><?php _e( 'Marca esto si el voluntario necesita acompañamiento (p.ej. no tiene llaves).', 'convoca-shifts' ); ?></p>
				</div>

<div class="cst-modal-footer">
					<button type="button" class="biodevas-btn biodevas-btn-outline cst-cancel-btn"><?php _e( 'Cancelar', 'convoca-shifts' ); ?></button>
					<button type="submit" class="biodevas-btn biodevas-btn-primary"><?php _e( 'Guardar Turno', 'convoca-shifts' ); ?></button>
				</div>
			</form>
		</div>
	</div>
	<?php
}

