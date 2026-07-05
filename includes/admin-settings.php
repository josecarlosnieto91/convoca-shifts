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
 * Register settings and menu page.
 */
add_action( 'admin_menu', 'Convoca\Shifts\convoca_shifts_add_settings_menu', 25 );
function convoca_shifts_add_settings_menu() {
	add_submenu_page(
		'edit.php?post_type=centro_turno',
		__( 'Ajustes del Sistema', 'convoca-shifts' ),
		__( 'Ajustes', 'convoca-shifts' ),
		'manage_inscripciones',
		'convoca_shifts_settings',
		'convoca_shifts_settings_page'
	);

	add_submenu_page(
		'edit.php?post_type=centro_turno',
		__( 'Estado del Sistema', 'convoca-shifts' ),
		__( 'Estado', 'convoca-shifts' ),
		'manage_options',
		'convoca_shifts_status',
		'convoca_shifts_status_page'
	);
}

function convoca_shifts_status_page() {
	if ( ! class_exists('Convoca\Shifts$1') ) {
		require_once __DIR__ . '/class-admin-status.php';
	}
	Convoca_Shifts_Admin_Status::render_page();
}

/**
 * Register settings.
 */
add_action( 'admin_init', 'Convoca\Shifts\convoca_shifts_register_plugin_settings' );
function convoca_shifts_register_plugin_settings() {
	register_setting( 'convoca_shifts_settings_group', 'convoca_shifts_calendar_page_url', 'sanitize_text_field' );
	register_setting( 'convoca_shifts_settings_group', 'convoca_shifts_hora_apertura', 'sanitize_text_field' );
	register_setting( 'convoca_shifts_settings_group', 'convoca_shifts_hora_cierre', 'sanitize_text_field' );
}

/**
 * Settings page HTML.
 */
/**
 * Settings page HTML.
 */
function convoca_shifts_settings_page() {
	$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
	?>
	<div class="wrap convoca-shifts-settings-wrap">
		<div class="convoca-shifts-admin-header" style="display: flex; align-items: center; gap: 20px; margin-bottom: 20px;">
			<img src="<?php echo esc_url( CONVOCA_IMAGES_URL . 'logo.png' ); ?>" alt="Convoca Shifts" style="width: 80px; height: 80px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
			<div>
				<h1 style="margin: 0; padding: 0;"><?php esc_html_e( 'Ajustes de Centro Social Turnos', 'convoca-shifts' ); ?></h1>
				<p style="margin: 5px 0 0; color: #666; font-size: 1.1em;"><?php esc_html_e( 'Configuración del sistema de turnos y apertura', 'convoca-shifts' ); ?></p>
			</div>
		</div>

		<h2 class="nav-tab-wrapper">
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=centro_turno&page=convoca_shifts_settings&tab=general' ) ); ?>" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Ajustes', 'convoca-shifts' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=centro_turno&page=convoca_shifts_settings&tab=status' ) ); ?>" class="nav-tab <?php echo $active_tab == 'status' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Estado', 'convoca-shifts' ); ?></a>
		</h2>

		<?php if ( $active_tab == 'general' ) : ?>
			<p><?php esc_html_e( 'Configura las URLs de las páginas que contienen los shortcodes para que el plugin pueda generar enlaces correctos.', 'convoca-shifts' ); ?></p>

			<form method="post" action="options.php" class="convoca-box" style="background:#fff;border-radius:12px;padding:30px;max-width:700px;margin-top:20px;">
				<?php settings_fields( 'convoca_shifts_settings_group' ); ?>
				<?php do_settings_sections( 'convoca_shifts_settings_group' ); ?>

				<div class="convoca-field">
					<label for="convoca_shifts_calendar_page_url"><?php esc_html_e( 'URL de la página del Calendario', 'convoca-shifts' ); ?></label>
					<input type="url" id="convoca_shifts_calendar_page_url" name="convoca_shifts_calendar_page_url" value="<?php echo esc_attr( get_option( 'convoca_shifts_calendar_page_url' ) ); ?>" placeholder="https://tuweb.com/calendario">.
					<small class="convoca-small"><?php esc_html_e( 'URL donde has pegado el shortcode [calendario_centro].', 'convoca-shifts' ); ?></small>
				</div>

				<div class="convoca-field">
					<label for="convoca_shifts_hora_apertura"><?php esc_html_e( 'Horario del Centro', 'convoca-shifts' ); ?></label>
					<div style="display:flex; align-items:center; gap:10px;">
						<input type="time" id="convoca_shifts_hora_apertura" name="convoca_shifts_hora_apertura" value="<?php echo esc_attr( get_option( 'convoca_shifts_hora_apertura', '09:00' ) ); ?>">
						<span><?php esc_html_e( 'hasta las', 'convoca-shifts' ); ?></span>
						<input type="time" id="convoca_shifts_hora_cierre" name="convoca_shifts_hora_cierre" value="<?php echo esc_attr( get_option( 'convoca_shifts_hora_cierre', '22:00' ) ); ?>">
					</div>
					<small class="convoca-small"><?php esc_html_e( 'Restringe la creación de turnos fuera de este horario.', 'convoca-shifts' ); ?></small>
				</div>

				<div style="margin-top:30px;">
						<button type="submit" class="convoca-btn convoca-btn-primary"><?php esc_html_e( 'Guardar ajustes', 'convoca-shifts' ); ?></button>
					</div>
				</form>

				<!-- PRO Features Section -->
				<div class="conv-pro-section" style="margin-top:40px;padding:25px;background:#fefce8;border:2px dashed #eab308;border-radius:12px;">
					<h2 style="margin-top:0;color:#a16207;">✨ Funcionalidades PRO</h2>
					<p style="color:#713f12;">Las siguientes funcionalidades están disponibles con una licencia PRO. <a href="<?php echo esc_url( admin_url( 'admin.php?page=convoca-license' ) ); ?>">Activa tu licencia</a> para desbloquearlas.</p>
					<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;margin-top:15px;">
						<div style="background:#fff;border-radius:8px;padding:15px;border:1px solid #e2e8f0;opacity:0.7;">
							<span style="font-size:1.2rem;">📊</span>
							<span style="font-weight:600;margin-left:8px;">Analytics Avanzados</span>
							<span style="display:block;font-size:11px;color:#94a3b8;margin-top:4px;">Gráficos, tendencias y exportación avanzada</span>
						</div>
					</div>
				</div>

				<div class="convoca-alert convoca-alert--info" style="display:block;margin-top:20px;margin-bottom:20px;">
				<p><strong>💡 <?php esc_html_e( '¡Novedad!', 'convoca-shifts' ); ?></strong>: <?php esc_html_e( 'Ahora tienes dos nuevas formas de insertar el contenido: mediante **Bloques de Gutenberg** (recomendado para el editor moderno) o mediante **Widgets** (para barras laterales y pie de página). Ambos son más fáciles de configurar que los shortcodes.', 'convoca-shifts' ); ?></p>
			</div>

			<h2><?php esc_html_e( 'Guía de Bloques / Widgets / Shortcodes', 'convoca-shifts' ); ?></h2>
			<p><?php esc_html_e( 'Puedes usar estos elementos como bloques de Gutenberg, widgets o mediante shortcodes:', 'convoca-shifts' ); ?></p>
			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th>Nombre / Bloque</th>
						<th>Shortcode</th>
						<th>Descripción</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'CST: Calendario', 'convoca-shifts' ); ?></strong><br><small><code>centro-social/calendario</code></small></td>
						<td><code>[calendario_centro]</code></td>
						<td><?php esc_html_e( 'Muestra el calendario interactivo (FullCalendar).', 'convoca-shifts' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'CST: Próximos Turnos', 'convoca-shifts' ); ?></strong><br><small><code>centro-social/proximos-turnos</code></small></td>
						<td><code>[proximos_turnos cantidad="5"]</code></td>
						<td><?php esc_html_e( 'Lista simple de los próximos turnos programados.', 'convoca-shifts' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'CST: Resumen Semanal', 'convoca-shifts' ); ?></strong><br><small><code>centro-social/resumen</code></small></td>
						<td><code>[resumen_turnos]</code></td>
						<td><?php esc_html_e( 'Resumen semanal con contador de huecos libres y botón de acción rápida.', 'convoca-shifts' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'CST: Botón Apuntarse', 'convoca-shifts' ); ?></strong><br><small><code>centro-social/boton-apuntarse</code></small></td>
						<td><code>[boton_apuntarse]</code></td>
						<td><?php esc_html_e( 'Botón directo "Me apunto al siguiente turno libre".', 'convoca-shifts' ); ?></td>
					</tr>
				</tbody>
			</table>
		<?php else : ?>
			<?php convoca_shifts_render_status_tab(); ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render Status/Diagnostic tab.
 */
function convoca_shifts_render_status_tab() {
	$checks       = convoca_shifts_get_system_checks();
	$has_errors   = array_filter( $checks, fn( $c ) => $c['status'] === 'error' );
	$has_warnings = array_filter( $checks, fn( $c ) => $c['status'] === 'warning' );

	if ( $has_errors ) {
		$summary_icon  = '✗';
		$summary_class = 'error';
		$summary_text  = __( 'Se han detectado problemas críticos que impiden el funcionamiento.', 'convoca-shifts' );
		$summary_title = __( 'Estado: Errores detectados', 'convoca-shifts' );
	} elseif ( $has_warnings ) {
		$summary_icon  = '⚠';
		$summary_class = 'warning';
		$summary_text  = __( 'Hay advertencias que podrían afectar el funcionamiento.', 'convoca-shifts' );
		$summary_title = __( 'Estado: Advertencias', 'convoca-shifts' );
	} else {
		$summary_icon  = '✓';
		$summary_class = 'success';
		$summary_text  = __( 'Todos los componentes están configurados correctamente.', 'convoca-shifts' );
		$summary_title = __( 'Estado: Todo correcto', 'convoca-shifts' );
	}
	?>

	<div class="convoca-shifts-diagnostic-wrapper">
		<div class="convoca-shifts-summary">
			<div class="convoca-shifts-summary-icon convoca-shifts-badge--<?php echo esc_attr( $summary_class ); ?>">
				<?php echo esc_html( $summary_icon ); ?>
			</div>
			<div class="convoca-shifts-summary-text">
				<h3><?php echo esc_html( $summary_title ); ?></h3>
				<p><?php echo esc_html( $summary_text ); ?></p>
			</div>
		</div>

		<div class="convoca-shifts-diagnostic-results">
			<?php foreach ( $checks as $check ) : ?>
				<div class="convoca-shifts-diagnostic-row">
					<span class="convoca-shifts-severity-icon convoca-shifts-severity-<?php echo esc_attr( $check['status'] === 'error' ? 'error' : ( $check['status'] === 'warning' ? 'warning' : 'ok' ) ); ?>">
						<?php echo $check['status'] === 'error' ? '✗' : ( $check['status'] === 'warning' ? '⚠' : '✓' ); ?>
					</span>
					<strong><?php echo esc_html( $check['title'] ); ?></strong>
					<div>
						<span class="convoca-shifts-message"><?php echo esc_html( $check['message'] ); ?></span>
						<?php if ( ! empty( $check['fix'] ) ) : ?>
							<span class="convoca-shifts-fix-info">💡 <?php echo esc_html( $check['fix'] ); ?></span>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}

/**
 * Get system checks for Turnos.
 */
function convoca_shifts_get_system_checks( bool $force = false ) {
	$checks = array();

	// 1. Plugins.
	$required_plugins = array(
		'convoca-common/convoca-common.php'   => 'Convoca Core',
		'convoca-members/convoca-members.php' => 'Convoca Members',
	);

	foreach ( $required_plugins as $path => $name ) {
		$is_active = \Convoca\Core\Utils::is_plugin_active_safe( $path );
		$checks[]  = array(
			/* translators: %s: plugin name */
			'title'   => sprintf( __( 'Plugin: %s', 'convoca-shifts' ), $name ),
			'status'  => $is_active ? 'ok' : 'warning',
			'message' => $is_active ? __( 'Activo y funcionando.', 'convoca-shifts' ) : __( 'Plugin no detectado o inactivo.', 'convoca-shifts' ),
			/* translators: %s: plugin name */
			'fix'     => ! $is_active ? sprintf( __( 'Se recomienda instalar y activar el plugin %s.', 'convoca-shifts' ), $name ) : '',
		);
	}

	// 2. Pages.
	$required_pages = array(
		'calendario_centro' => array(
			'title'     => __( 'Página: Calendario', 'convoca-shifts' ),
			'shortcode' => '[calendario_centro]',
			'fix'       => __( 'Crea una página con el shortcode [calendario_centro].', 'convoca-shifts' ),
		),
	);

	foreach ( $required_pages as $slug => $data ) {
		$page     = convoca_shifts_find_page_by_shortcode( $data['shortcode'] );
		$checks[] = array(
			'title'   => $data['title'],
			'status'  => $page ? 'ok' : 'error',
			'message' => $page
		/* translators: %s: page title */
		? sprintf( __( 'Detectada: %s', 'convoca-shifts' ), get_the_title( $page ) )
		: __( 'No se ha encontrado ninguna página con este shortcode.', 'convoca-shifts' ),
			'fix'     => ! $page ? $data['fix'] : '',
		);
	}

	return $checks;
}

/**
 * Helper to find page by shortcode.
 */
function convoca_shifts_find_page_by_shortcode( string $shortcode ) {
	global $wpdb;

	$tag   = trim( $shortcode, '[]' );
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $query is prepared below.
	$query = $wpdb->prepare(
		"SELECT ID, post_content FROM $wpdb->posts 
         WHERE post_content LIKE %s 
         AND post_status = 'publish' 
         AND post_type = 'page' 
         LIMIT 1",
		'%' . $wpdb->esc_like( $shortcode ) . '%'
	);

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $query is prepared above.
	$results = $wpdb->get_results( $query );

	if ( empty( $results ) ) {
		return null;
	}

	foreach ( $results as $page ) {
		if ( has_shortcode( $page->post_content, $tag ) ) {
			return $page->ID;
		}
	}

	return null;
}
