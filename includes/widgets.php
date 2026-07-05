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
 * Convoca Shifts - widgets
 *
 * @package Convoca_Shifts
 */

namespace Convoca\Shifts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register all widgets for Convoca Shifts
 */
function convoca_shifts_register_widgets() {
	register_widget( 'Convoca\Shifts\Convoca_Shifts_Calendario_Widget' );
	register_widget( 'Convoca\Shifts\Convoca_Shifts_Boton_Apuntarse_Widget' );
	register_widget( 'Convoca\Shifts\Convoca_Shifts_Resumen_Turnos_Widget' );
	register_widget( 'Convoca\Shifts\Convoca_Shifts_Proximos_Turnos_Widget' );
}
add_action( 'widgets_init', 'Convoca\Shifts\convoca_shifts_register_widgets' );

/**
 * Widget: Calendario Centro
 */
class Convoca_Shifts_Calendario_Widget extends \WP_Widget {
	public function __construct() {
		parent::__construct(
			'convoca_shifts_calendario_widget',
			__( 'CST: Calendario', 'convoca-shifts' ),
			array( 'description' => __( 'Muestra el calendario de turnos.', 'convoca-shifts' ) )
		);
	}

	public function widget( $args, $instance ) {
		echo wp_kses_post( $args['before_widget'] );
		if ( ! empty( $instance['title'] ) ) {
			echo wp_kses_post( $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'] );
		}
		echo wp_kses_post( convoca_shifts_calendario_centro() );
		echo wp_kses_post( $args['after_widget'] );
	}

	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Título:', 'convoca-shifts' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$instance          = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? sanitize_text_field( $new_instance['title'] ) : '';
		return $instance;
	}
}

/**
 * Widget: Botón Apuntarse
 */
class Convoca_Shifts_Boton_Apuntarse_Widget extends \WP_Widget {
	public function __construct() {
		parent::__construct(
			'convoca_shifts_boton_apuntarse_widget',
			__( 'CST: Botón Apuntarse', 'convoca-shifts' ),
			array( 'description' => __( 'Botón rápido para apuntarse al siguiente turno libre.', 'convoca-shifts' ) )
		);
	}

	public function widget( $args, $instance ) {
		echo wp_kses_post( $args['before_widget'] );
		if ( ! empty( $instance['title'] ) ) {
			echo wp_kses_post( $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'] );
		}
		echo wp_kses_post( convoca_shifts_boton_apuntarse() );
		echo wp_kses_post( $args['after_widget'] );
	}

	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Título:', 'convoca-shifts' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$instance          = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? sanitize_text_field( $new_instance['title'] ) : '';
		return $instance;
	}
}

/**
 * Widget: Resumen Turnos
 */
class Convoca_Shifts_Resumen_Turnos_Widget extends \WP_Widget {
	public function __construct() {
		parent::__construct(
			'convoca_shifts_resumen_turnos_widget',
			__( 'CST: Resumen Semanal', 'convoca-shifts' ),
			array( 'description' => __( 'Muestra un resumen de los turnos de la semana.', 'convoca-shifts' ) )
		);
	}

	public function widget( $args, $instance ) {
		echo wp_kses_post( $args['before_widget'] );
		if ( ! empty( $instance['title'] ) ) {
			echo wp_kses_post( $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'] );
		}
		echo wp_kses_post( convoca_shifts_resumen_turnos() );
		echo wp_kses_post( $args['after_widget'] );
	}

	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Título:', 'convoca-shifts' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$instance          = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? sanitize_text_field( $new_instance['title'] ) : '';
		return $instance;
	}
}

/**
 * Widget: Próximos Turnos
 */
class Convoca_Shifts_Proximos_Turnos_Widget extends \WP_Widget {
	public function __construct() {
		parent::__construct(
			'convoca_shifts_proximos_turnos_widget',
			__( 'CST: Próximos Turnos', 'convoca-shifts' ),
			array( 'description' => __( 'Muestra una lista de los próximos turnos.', 'convoca-shifts' ) )
		);
	}

	public function widget( $args, $instance ) {
		$cantidad = ! empty( $instance['cantidad'] ) ? $instance['cantidad'] : 5;

		echo wp_kses_post( $args['before_widget'] );
		if ( ! empty( $instance['title'] ) ) {
			echo wp_kses_post( $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'] );
		}
		echo wp_kses_post( convoca_shifts_proximos_turnos( array( 'cantidad' => $cantidad ) ) );
		echo wp_kses_post( $args['after_widget'] );
	}

	public function form( $instance ) {
		$title    = ! empty( $instance['title'] ) ? $instance['title'] : '';
		$cantidad = ! empty( $instance['cantidad'] ) ? $instance['cantidad'] : 5;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Título:', 'convoca-shifts' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'cantidad' ) ); ?>"><?php esc_html_e( 'Cantidad de turnos:', 'convoca-shifts' ); ?></label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'cantidad' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'cantidad' ) ); ?>" type="number" step="1" min="1" value="<?php echo esc_attr( $cantidad ); ?>" size="3">
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$instance             = array();
		$instance['title']    = ( ! empty( $new_instance['title'] ) ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['cantidad'] = ( ! empty( $new_instance['cantidad'] ) ) ? absint( $new_instance['cantidad'] ) : 5;
		return $instance;
	}
}
