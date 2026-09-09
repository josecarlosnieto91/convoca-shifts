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
 * D11 — Penalización suave de ausencias (no-show) con avisos automáticos.
 * D12 — Estados de ausencia justificada / cancelada con aviso.
 *
 * Una falta sin justificar ('no_asistio') no suma horas (eso ya lo garantiza
 * Hour_Sync) y, además, alimenta un contador por voluntario con ventana de días.
 * Al alcanzar el umbral configurado se avisa al socio y, cada N faltas, al admin.
 * No hay bloqueo ni pérdida de puntos.
 *
 * Los estados 'justificada' y 'cancelada_aviso' representan ausencias aceptadas
 * y NO cuentan como falta para el contador.
 */
class No_Show_Manager {

	/**
	 * User meta key: mapa [post_id => 'Y-m-d'] de faltas sin justificar.
	 */
	const NO_SHOW_META = '_convoca_no_shows';

	/**
	 * Estados de asistencia que representan ausencia aceptada (no cuentan como falta).
	 */
	const JUSTIFIED_STATES = array( 'justificada', 'cancelada_aviso' );

	/**
	 * Configuración de avisos (convoca_shifts_settings) con defaults.
	 *
	 * @return array{aviso_socio_en:int,aviso_admin_cada:int,ventana_dias:int}
	 */
	public static function get_settings(): array {
		return array(
			'aviso_socio_en'   => max( 1, (int) get_option( 'convoca_shifts_aviso_socio_en', 2 ) ),
			'aviso_admin_cada' => max( 1, (int) get_option( 'convoca_shifts_aviso_admin_cada', 3 ) ),
			'ventana_dias'     => max( 1, (int) get_option( 'convoca_shifts_ventana_dias', 90 ) ),
		);
	}

	/**
	 * Registra una falta sin justificar de forma idempotente (por turno).
	 *
	 * @param int         $user_id Voluntario responsable.
	 * @param int         $post_id Turno.
	 * @param string|null $date    Fecha de la falta (Y-m-d). Por defecto hoy.
	 * @return bool True si se registró por primera vez.
	 */
	public static function record_no_show( int $user_id, int $post_id, ?string $date = null ): bool {
		if ( $user_id <= 0 || $post_id <= 0 ) {
			return false;
		}

		$date     = $date ?: wp_date( 'Y-m-d' );
		$no_shows = get_user_meta( $user_id, self::NO_SHOW_META, true );
		if ( ! is_array( $no_shows ) ) {
			$no_shows = array();
		}

		if ( isset( $no_shows[ $post_id ] ) ) {
			return false;
		}

		$no_shows[ $post_id ] = $date;
		update_user_meta( $user_id, self::NO_SHOW_META, $no_shows );

		return true;
	}

	/**
	 * Retira el registro de falta de un turno (p. ej. si se justifica después).
	 *
	 * @param int $user_id Voluntario responsable.
	 * @param int $post_id Turno.
	 * @return bool True si existía y fue retirado.
	 */
	public static function clear_no_show( int $user_id, int $post_id ): bool {
		if ( $user_id <= 0 || $post_id <= 0 ) {
			return false;
		}

		$no_shows = get_user_meta( $user_id, self::NO_SHOW_META, true );
		if ( ! is_array( $no_shows ) || ! isset( $no_shows[ $post_id ] ) ) {
			return false;
		}

		unset( $no_shows[ $post_id ] );

		if ( empty( $no_shows ) ) {
			delete_user_meta( $user_id, self::NO_SHOW_META );
		} else {
			update_user_meta( $user_id, self::NO_SHOW_META, $no_shows );
		}

		return true;
	}

	/**
	 * Cuenta las faltas sin justificar dentro de la ventana de días.
	 *
	 * @param int      $user_id    Voluntario.
	 * @param int      $window_days Ventana en días.
	 * @param int|null $now        Timestamp de referencia (por defecto time()).
	 * @return int Número de faltas en ventana.
	 */
	public static function count_in_window( int $user_id, int $window_days, ?int $now = null ): int {
		$no_shows = get_user_meta( $user_id, self::NO_SHOW_META, true );
		if ( ! is_array( $no_shows ) || empty( $no_shows ) ) {
			return 0;
		}

		$now    = $now ?? time();
		$cutoff = $now - ( $window_days * DAY_IN_SECONDS );
		$count  = 0;

		foreach ( $no_shows as $date ) {
			$ts = strtotime( (string) $date );
			if ( false !== $ts && $ts >= $cutoff ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Punto de entrada central: reacciona a un cambio de estado de asistencia.
	 *
	 * @param int         $post_id Turno.
	 * @param int         $user_id Voluntario responsable.
	 * @param string      $status  Nuevo estado de asistencia (_estado_real).
	 * @param string|null $date    Fecha de la falta (Y-m-d). Por defecto hoy.
	 * @return string[] Etiquetas de avisos enviados ('socio', 'admin').
	 */
	public static function handle_attendance_change( int $post_id, int $user_id, string $status, ?string $date = null ): array {
		if ( 'no_asistio' === $status ) {
			self::record_no_show( $user_id, $post_id, $date );

			$settings = self::get_settings();
			$count    = self::count_in_window( $user_id, $settings['ventana_dias'] );

			return self::maybe_notify( $user_id, $count );
		}

		// Cualquier otro estado (realizado, justificada, cancelada_aviso, pendiente):
		// si existía una falta registrada para este turno, se retira del contador.
		self::clear_no_show( $user_id, $post_id );

		return array();
	}

	/**
	 * Envía los avisos que correspondan según el contador.
	 *
	 * @param int $user_id Voluntario.
	 * @param int $count   Faltas en ventana.
	 * @return string[] Etiquetas de avisos enviados ('socio', 'admin').
	 */
	public static function maybe_notify( int $user_id, int $count ): array {
		$settings = self::get_settings();
		$sent     = array();

		if ( $count === $settings['aviso_socio_en'] ) {
			self::send_volunteer_notice( $user_id, $count );
			$sent[] = 'socio';
		}

		if ( $count >= $settings['aviso_admin_cada'] && 0 === ( $count % $settings['aviso_admin_cada'] ) ) {
			self::send_admin_notice( $user_id, $count );
			$sent[] = 'admin';
		}

		return $sent;
	}

	/**
	 * Indica si un estado de asistencia es una ausencia aceptada (no falta).
	 *
	 * @param string $status Estado de asistencia.
	 * @return bool
	 */
	public static function is_justified( string $status ): bool {
		return in_array( $status, self::JUSTIFIED_STATES, true );
	}

	/**
	 * Envía el aviso al voluntario.
	 *
	 * @param int $user_id Voluntario.
	 * @param int $count   Faltas en ventana.
	 */
	private static function send_volunteer_notice( int $user_id, int $count ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$settings = self::get_settings();

		/* translators: %d: número de faltas sin justificar */
		$subject = sprintf( __( 'Aviso: has acumulado %d faltas sin justificar', 'convoca-shifts' ), $count );
		$message = sprintf(
			/* translators: 1: nombre, 2: nº de faltas, 3: ventana en días */
			__( 'Hola %1$s: has acumulado %2$d faltas sin justificar en los últimos %3$d días. Si hay un motivo, contacta con la coordinación para justificarlas.', 'convoca-shifts' ),
			$user->display_name,
			$count,
			$settings['ventana_dias']
		);

		$subject = (string) apply_filters( 'convoca_shifts_no_show_email_socio_subject', $subject, $user, $count );
		$message = (string) apply_filters( 'convoca_shifts_no_show_email_socio', $message, $user, $count );

		wp_mail( $user->user_email, $subject, $message );
	}

	/**
	 * Envía el aviso al administrador.
	 *
	 * @param int $user_id Voluntario.
	 * @param int $count   Faltas en ventana.
	 */
	private static function send_admin_notice( int $user_id, int $count ): void {
		$email = (string) apply_filters( 'convoca_shifts_admin_email', get_option( 'admin_email', '' ) );
		if ( empty( $email ) ) {
			return;
		}

		$user = get_userdata( $user_id );
		$name = $user ? $user->display_name : (string) $user_id;

		/* translators: %d: número de faltas sin justificar */
		$subject = sprintf( __( 'Voluntario/a con %d faltas sin justificar', 'convoca-shifts' ), $count );
		/* translators: 1: nombre del voluntario, 2: nº de faltas */
		$message = sprintf( __( 'El voluntario/a %1$s ha acumulado %2$d faltas sin justificar.', 'convoca-shifts' ), $name, $count );

		$subject = (string) apply_filters( 'convoca_shifts_no_show_email_admin_subject', $subject, $user, $count );
		$message = (string) apply_filters( 'convoca_shifts_no_show_email_admin', $message, $user, $count );

		wp_mail( $email, $subject, $message );
	}
}
