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

use Convoca\Core\Admin\Diagnostico;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Estado del sistema de Shifts.
 *
 * El pintado y las comprobaciones genéricas viven en Core (`Diagnostico`). Aquí queda lo
 * que es de este plugin: qué página necesita y qué tablas y eventos son suyos.
 */
class Convoca_Shifts_Admin_Status {

	const SEVERITY_OK      = Diagnostico::SEVERITY_OK;
	const SEVERITY_WARNING = Diagnostico::SEVERITY_WARNING;
	const SEVERITY_ERROR   = Diagnostico::SEVERITY_ERROR;

	/**
	 * Ejecuta todas las comprobaciones.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function run_all(): array {
		return array(
			self::check_php_version(),
			self::check_pages(),
			self::check_tables(),
			self::check_cron(),
		);
	}

	/**
	 * Versión de PHP.
	 *
	 * @return array<string, mixed>
	 */
	public static function check_php_version(): array {
		return Diagnostico::php( '8.0' );
	}

	/**
	 * Página pública del calendario.
	 *
	 * @return array<string, mixed>
	 */
	public static function check_pages(): array {
		$slug = 'calendario';
		$page = get_page_by_path( $slug );
		if ( $page && $page->post_status === 'publish' ) {
			$has = has_shortcode( $page->post_content, 'calendario_centro' );
			return Diagnostico::con_arreglo(
				Diagnostico::resultado(
					'page_calendario',
					'Página Calendario',
					$has ? 'Existe con shortcode [calendario_centro]' : 'Existe pero sin el shortcode',
					$has ? self::SEVERITY_OK : self::SEVERITY_WARNING
				),
				'Añade el shortcode [calendario_centro] a la página.'
			);
		}
		return Diagnostico::con_arreglo(
			Diagnostico::resultado(
				'page_calendario',
				'Página Calendario',
				'No encontrada',
				self::SEVERITY_ERROR
			),
			'Crea una página con el shortcode [calendario_centro]'
		);
	}

	/**
	 * Tablas compartidas que necesita el plugin.
	 *
	 * @return array<string, mixed>
	 */
	public static function check_tables(): array {
		global $wpdb;

		return Diagnostico::tablas(
			array( "{$wpdb->prefix}convoca_logs", "{$wpdb->prefix}convoca_locks" ),
			'Activa convoca-common para crear las tablas compartidas.'
		);
	}

	/**
	 * Eventos cron del plugin.
	 *
	 * @return array<string, mixed>
	 */
	public static function check_cron(): array {
		return Diagnostico::cron(
			array( 'convoca_shifts_hourly_event', 'convoca_shifts_daily_event' ),
			'Los eventos se programan automáticamente al activar el plugin.'
		);
	}

	/**
	 * Pinta la pantalla de estado.
	 */
	public static function render_page(): void {
		Diagnostico::pintar(
			self::run_all(),
			__( 'Estado del Sistema - Turnos', 'convoca-shifts' )
		);
	}
}
