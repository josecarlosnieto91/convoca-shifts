<?php
/**
 * Convoca Shifts - widgets
 *
 * @package Convoca_Shifts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register all widgets for Centro Social Turnos
 */
function cst_register_widgets() {
	register_widget( 'CST_Calendario_Widget' );
	register_widget( 'CST_Boton_Apuntarse_Widget' );
	register_widget( 'CST_Resumen_Turnos_Widget' );
	register_widget( 'CST_Proximos_Turnos_Widget' );
}
add_action( 'widgets_init', 'cst_register_widgets' );

/**
 * Widget: Calendario Centro
 */
class CST_Calendario_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct(
			'cst_calendario_widget',
			__( 'CST: Calendario', 'convoca-shifts' ),
			array( 'description' => __( 'Muestra el calendario de turnos.', 'convoca-shifts' ) )
		);
	}

	public function widget( $args, $instance ) {
		echo $args['before_widget'];
		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
		}
		echo cst_shortcode_calendario_centro();
		echo $args['after_widget'];
	}

	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Título:', 'convoca-shifts' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
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
class CST_Boton_Apuntarse_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct(
			'cst_boton_apuntarse_widget',
			__( 'CST: Botón Apuntarse', 'convoca-shifts' ),
			array( 'description' => __( 'Botón rápido para apuntarse al siguiente turno libre.', 'convoca-shifts' ) )
		);
	}

	public function widget( $args, $instance ) {
		echo $args['before_widget'];
		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
		}
		echo cst_shortcode_boton_apuntarse();
		echo $args['after_widget'];
	}

	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Título:', 'convoca-shifts' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
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
class CST_Resumen_Turnos_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct(
			'cst_resumen_turnos_widget',
			__( 'CST: Resumen Semanal', 'convoca-shifts' ),
			array( 'description' => __( 'Muestra un resumen de los turnos de la semana.', 'convoca-shifts' ) )
		);
	}

	public function widget( $args, $instance ) {
		echo $args['before_widget'];
		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
		}
		echo cst_shortcode_resumen_turnos();
		echo $args['after_widget'];
	}

	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Título:', 'convoca-shifts' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
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
class CST_Proximos_Turnos_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct(
			'cst_proximos_turnos_widget',
			__( 'CST: Próximos Turnos', 'convoca-shifts' ),
			array( 'description' => __( 'Muestra una lista de los próximos turnos.', 'convoca-shifts' ) )
		);
	}

	public function widget( $args, $instance ) {
		$cantidad = ! empty( $instance['cantidad'] ) ? $instance['cantidad'] : 5;

		echo $args['before_widget'];
		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
		}
		echo cst_shortcode_proximos_turnos( array( 'cantidad' => $cantidad ) );
		echo $args['after_widget'];
	}

	public function form( $instance ) {
		$title    = ! empty( $instance['title'] ) ? $instance['title'] : '';
		$cantidad = ! empty( $instance['cantidad'] ) ? $instance['cantidad'] : 5;
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Título:', 'convoca-shifts' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'cantidad' ); ?>"><?php _e( 'Cantidad de turnos:', 'convoca-shifts' ); ?></label>
			<input class="tiny-text" id="<?php echo $this->get_field_id( 'cantidad' ); ?>" name="<?php echo $this->get_field_name( 'cantidad' ); ?>" type="number" step="1" min="1" value="<?php echo esc_attr( $cantidad ); ?>" size="3">
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
