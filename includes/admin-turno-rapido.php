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
 * Quick Turno Addition Interface (Backend Calendar).
 *
 * @package CentroSocialTurnos
 */

namespace Convoca\Shifts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'Convoca\Shifts\convoca_shifts_add_turno_rapido_menu', 1 );
function convoca_shifts_add_turno_rapido_menu() {
	// 1. Add our custom page.
	add_submenu_page(
		'edit.php?post_type=centro_turno',
		__( 'Añadir Turno Rápido', 'convoca-shifts' ),
		__( 'Añadir Turno Rápido', 'convoca-shifts' ),
		'convoca_shifts_manage_turnos',
		'convoca_shifts_turno_rapido',
		'convoca_shifts_turno_rapido_page'
	);

	// 2. Remove the standard "Add New" submenu to avoid confusion.
	remove_submenu_page( 'edit.php?post_type=centro_turno', 'post-new.php?post_type=centro_turno' );
}

/**
 * Filter admin URLs to point "Add New" links to our custom page.
 * This affects the button next to the title in the list and the admin bar.
 */
add_filter( 'admin_url', 'Convoca\Shifts\convoca_shifts_redirect_add_new_url', 10, 2 );
function convoca_shifts_redirect_add_new_url( $url, $path ) {
	if ( $path === 'post-new.php?post_type=centro_turno' ) {
		return admin_url( 'admin.php?page=convoca_shifts_turno_rapido' );
	}
	return $url;
}

/**
 * Force redirect if anyone accesses the standard post-new.php page directly via URL.
 */
add_action( 'admin_init', 'Convoca\Shifts\convoca_shifts_force_redirect_standard_editor' );
function convoca_shifts_force_redirect_standard_editor() {
	global $pagenow;
	if ( $pagenow === 'post-new.php' && isset( $_GET['post_type'] ) && $_GET['post_type'] === 'centro_turno' ) {
		wp_redirect( admin_url( 'admin.php?page=convoca_shifts_turno_rapido' ) );
		exit;
	}
}

/**
 * Handle the form submission for Quick Add Turno via admin-post.php.
 */
add_action( 'admin_post_convoca_shifts_quick_add_turno', 'Convoca\Shifts\convoca_shifts_process_quick_add_turno' );
function convoca_shifts_process_quick_add_turno() {
	if ( ! isset( $_POST['convoca_shifts_quick_add_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['convoca_shifts_quick_add_nonce'] ), 'convoca_shifts_quick_add_action' ) ) {
		wp_safe_redirect( admin_url( 'edit.php?post_type=centro_turno&convoca_shifts_msg=error&convoca_shifts_err=' . urlencode( __( 'Nonce inválido.', 'convoca-shifts' ) ) ) );
		exit;
	}

	if ( ! current_user_can( 'convoca_shifts_manage_turnos' ) ) {
		wp_die( esc_html__( 'No tienes permisos para realizar esta acción.', 'convoca-shifts' ) );
	}

	$date    = sanitize_text_field( wp_unslash( $_POST['convoca_shifts_date'] ) );
	$h_start = sanitize_text_field( wp_unslash( $_POST['convoca_shifts_h_start'] ) );
	$h_end   = sanitize_text_field( wp_unslash( $_POST['convoca_shifts_h_end'] ) );
	$estado  = sanitize_text_field( wp_unslash( $_POST['convoca_shifts_estado'] ) );
	$apoyo   = isset( $_POST['convoca_shifts_apoyo'] ) ? 1 : 0;

	$post_id = convoca_shifts_insert_turno(
		array(
			'date'           => $date,
			'h_start'        => $h_start,
			'h_end'          => $h_end,
			'estado'         => $estado,
			'necesita_apoyo' => $apoyo,
		)
	);

	if ( ! is_wp_error( $post_id ) ) {
		if ( function_exists( 'Convoca\Shifts\convoca_shifts_log_activity' ) ) {
			convoca_shifts_log_activity( get_current_user_id(), $post_id, 'turno_creado', array( 'origen' => 'admin_rapido' ) );
		}

		if ( class_exists( 'Convoca\\Core\\Logger' ) ) {
			\Convoca\Core\Logger::info( "Turno rápido creado para el día $date ($h_start - $h_end)", 'Turnos', $post_id );
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=centro_turno&convoca_shifts_msg=created' ) );
		exit;
	} else {
		wp_safe_redirect( admin_url( 'edit.php?post_type=centro_turno&convoca_shifts_msg=error&convoca_shifts_err=' . urlencode( $post_id->get_error_message() ) ) );
		exit;
	}
}

/**
 * Handle success message in the main list.
 */
add_action( 'admin_notices', 'Convoca\Shifts\convoca_shifts_turno_rapido_notices' );
function convoca_shifts_turno_rapido_notices() {
	$screen = get_current_screen();
	if ( $screen && $screen->id === 'edit-centro_turno' && isset( $_GET['convoca_shifts_msg'] ) ) {
		if ( $_GET['convoca_shifts_msg'] === 'created' ) {
			echo '<div class="convoca-alert convoca-alert--success" style="display:block;margin-bottom:20px;"><p>' . esc_html__( 'Turno creado correctamente.', 'convoca-shifts' ) . '</p></div>';
		} elseif ( $_GET['convoca_shifts_msg'] === 'error' ) {
			$err = isset( $_GET['convoca_shifts_err'] ) ? sanitize_text_field( wp_unslash( $_GET['convoca_shifts_err'] ) ) : esc_html__( 'Error desconocido', 'convoca-shifts' );
			echo '<div class="convoca-alert convoca-alert--danger" style="display:block;margin-bottom:20px;"><p><strong>Error:</strong> ' . esc_html( $err ) . '</p></div>';
		}
	}
}

/**
 * Render the Quick Add page.
 */
function convoca_shifts_turno_rapido_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Añadir Turno Rápido', 'convoca-shifts' ); ?></h1>
		<div class="convoca-alert convoca-alert--info" style="display:block;margin-bottom:20px;">
			<p><?php esc_html_e( 'Selecciona un día en el calendario para crear un turno sin pasar por la pantalla de edición estándar.', 'convoca-shifts' ); ?></p>
		</div>
		
		<div id="convoca-shifts-admin-calendar-container" class="card" style="padding: 20px; max-width: 800px;">
<div id="convoca-shifts-calendar-controls" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
					<button type="button" id="convoca-shifts-prev-month" class="convoca-btn convoca-btn-outline">&laquo; Mes anterior</button>
					<h2 id="convoca-shifts-calendar-month-year" style="margin:0;"></h2>
					<button type="button" id="convoca-shifts-next-month" class="convoca-btn convoca-btn-outline">Mes siguiente &raquo;</button>
				</div>
			<div id="convoca-shifts-calendar-grid"></div>
		</div>

		</div>
		<?php
		add_action( 'admin_footer', 'Convoca\Shifts\convoca_shifts_render_quick_add_modal' );
		?>
</div>
		<?php
		add_action( 'admin_footer', 'Convoca\Shifts\convoca_shifts_render_quick_add_modal' );
		?>
	</div>

	<script>
	document.addEventListener('DOMContentLoaded', function() {
		const grid = document.getElementById('convoca-shifts-calendar-grid');
		const monthYearLabel = document.getElementById('convoca-shifts-calendar-month-year');
		const modal = document.getElementById('convoca-shifts-quick-modal');
		const closeBtn = document.querySelector('.convoca-shifts-close');
		const cancelBtn = document.querySelector('.convoca-shifts-cancel-btn');
		const dateInput = document.getElementById('convoca_shifts_modal_date');
		const modalTitle = document.getElementById('convoca-shifts-modal-title');

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
				html += '<td class="convoca-shifts-empty"></td>';
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

				if (isToday) classes.push('convoca-shifts-today');
				else if (isPast) classes.push('convoca-shifts-past-day');
				else if (isFuture) classes.push('convoca-shifts-future-day');
				
				if (isWeekend) classes.push('convoca-shifts-weekend');
				
				const isClickable = isToday || isFuture;
				
				html += `<td class="${classes.join(' ')}" data-date="${dateStr}" ${ !isClickable ? 'style="pointer-events:none;"' : '' }>
							<span class="convoca-shifts-day-num">${day}</span>
							<div class="convoca-shifts-day-content"></div>
						</td>`;

				if ((day + firstDay) % 7 === 0) html += '</tr>';
			}
			
			html += '</tbody></table>';
			grid.innerHTML = html;

			// Add Click listeners.
			grid.querySelectorAll('td:not(.convoca-shifts-empty)').forEach(td => {
				td.onclick = function() {
					const date = this.dataset.date;
					dateInput.value = date;
					const d = new Date(date);
					modalTitle.innerText = `Añadir Turno: ${d.toLocaleDateString('es-ES', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}`;
					
					// Reset presets.
					document.querySelectorAll('.convoca-shifts-preset-btn').forEach(b => b.classList.remove('active'));
					const morningBtn = document.querySelector('.convoca-shifts-preset-btn[data-start="10:00"]');
					if (morningBtn) morningBtn.classList.add('active');
					
					document.getElementById('convoca-shifts-custom-time-fields').style.display = 'none';
					document.getElementById('fe_h_start').value = '10:00';
					document.getElementById('fe_h_end').value = '13:00';

					modal.classList.add('is-active');
				};
			});

			// Preset logic (Admin).
			document.querySelectorAll('.convoca-shifts-preset-btn').forEach(btn => {
				btn.addEventListener('click', function() {
					document.querySelectorAll('.convoca-shifts-preset-btn').forEach(b => b.classList.remove('active'));
					this.classList.add('active');

					const isCustom = this.dataset.custom;
					const customFields = document.getElementById('convoca-shifts-custom-time-fields');
					if (isCustom) {
						customFields.style.display = 'block';
					} else {
						customFields.style.display = 'none';
						let start = this.dataset.start;
						let end = this.dataset.end;
						const limitOpen = '<?php echo esc_js( get_option( 'convoca_shifts_hora_apertura', '09:00' ) ); ?>';
						const limitClose = '<?php echo esc_js( get_option( 'convoca_shifts_hora_cierre', '22:00' ) ); ?>';
						
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

		document.getElementById('convoca-shifts-prev-month').onclick = () => {
			viewDate.setMonth(viewDate.getMonth() - 1);
			renderCalendar();
		};
		document.getElementById('convoca-shifts-next-month').onclick = () => {
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
			const limitOpen = '<?php echo esc_js( get_option( 'convoca_shifts_hora_apertura', '09:00' ) ); ?>';
			const limitClose = '<?php echo esc_js( get_option( 'convoca_shifts_hora_cierre', '22:00' ) ); ?>';
			
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
	add_action( 'admin_footer', 'Convoca\Shifts\convoca_shifts_render_quick_add_modal' );
}

/**
 * Render the Quick Add modal in the footer.
 */
function convoca_shifts_render_quick_add_modal() {
	?>
	<!-- Modal Form (Moved to footer) -->
	<div id="convoca-shifts-quick-modal" class="convoca-shifts-modal">
		<div class="convoca-shifts-modal-content">
			<span class="convoca-shifts-close">&times;</span>
			<h2 id="convoca-shifts-modal-title"><?php esc_html_e( 'Crear Nuevo Turno', 'convoca-shifts' ); ?></h2>
			<hr>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'convoca_shifts_quick_add_action', 'convoca_shifts_quick_add_nonce' ); ?>
				<input type="hidden" name="action" value="convoca_shifts_quick_add_turno">
				<input type="hidden" id="convoca_shifts_modal_date" name="convoca_shifts_date">
				
				<div class="convoca-shifts-form-group">
					<label><?php esc_html_e( 'Seleccionar Horario', 'convoca-shifts' ); ?></label>
					<div class="convoca-shifts-presets">
						<button type="button" class="convoca-shifts-preset-btn" data-start="10:00" data-end="13:00">☀️ <?php esc_html_e( 'Mañana (10-13h)', 'convoca-shifts' ); ?></button>
						<button type="button" class="convoca-shifts-preset-btn" data-start="17:00" data-end="20:00">🌇 <?php esc_html_e( 'Tarde (17-20h)', 'convoca-shifts' ); ?></button>
						<button type="button" class="convoca-shifts-preset-btn" data-custom="1">⚙️ <?php esc_html_e( 'Personalizado', 'convoca-shifts' ); ?></button>
					</div>
				</div>

				<div id="convoca-shifts-custom-time-fields" style="display:none; margin-top:15px; padding:15px; background:var(--wp--preset--color--blanco, #ffffff); border-radius:8px; border:1px solid var(--wp--preset--color--gris-medio, #e0e0e0);">
					<div class="convoca-shifts-form-row" style="margin-bottom:0;">
						<div class="convoca-shifts-form-group" style="margin-bottom:0;">
							<label><?php esc_html_e( 'Hora Inicio', 'convoca-shifts' ); ?></label>
							<input type="time" name="convoca_shifts_h_start" id="fe_h_start" value="10:00" step="900">
						</div>
						<div class="convoca-shifts-form-group" style="margin-bottom:0;">
							<label><?php esc_html_e( 'Hora Fin', 'convoca-shifts' ); ?></label>
							<input type="time" name="convoca_shifts_h_end" id="fe_h_end" value="13:00" step="900">
						</div>
					</div>
				</div>

				<div class="convoca-shifts-form-group">
					<label><?php esc_html_e( 'Estado Inicial', 'convoca-shifts' ); ?></label>
					<select name="convoca_shifts_estado" style="width: 100%;">
						<option value="abierto_disponible">🟡 Pendiente (Disponible para voluntarios)</option>
						<option value="abierto_ocupado">🔵 Ocupado (Actividad interna / No inscribible)</option>
						<option value="cerrado">🔴 Cerrado (Festivo / Sin apertura)</option>
					</select>
				</div>

				<div class="convoca-shifts-form-group" style="margin-top: 15px;">
					<label>
						<input type="checkbox" name="convoca_shifts_apoyo" value="1">
						<strong><?php esc_html_e( '🛟 Necesita apoyo', 'convoca-shifts' ); ?></strong>
					</label>
					<p class="description"><?php esc_html_e( 'Marca esto si el voluntario necesita acompañamiento (p.ej. no tiene llaves).', 'convoca-shifts' ); ?></p>
				</div>

<div class="convoca-shifts-modal-footer">
					<button type="button" class="convoca-btn convoca-btn-outline convoca-shifts-cancel-btn"><?php esc_html_e( 'Cancelar', 'convoca-shifts' ); ?></button>
					<button type="submit" class="convoca-btn convoca-btn-primary"><?php esc_html_e( 'Guardar Turno', 'convoca-shifts' ); ?></button>
				</div>
			</form>
		</div>
	</div>
	<?php
}

