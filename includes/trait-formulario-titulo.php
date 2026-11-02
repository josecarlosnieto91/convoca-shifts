<?php
/**
 * El formulario de un solo campo (el título), compartido por tres widgets.
 *
 * Calendario, Botón y Resumen tenían el mismo `form()` y el mismo `update()` copiados. El de
 * «Próximos» no: lleva además la cantidad, y por eso no usa este trait.
 *
 * @package Convoca\Shifts
 */

namespace Convoca\Shifts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formulario de título.
 */
trait Formulario_Titulo {

	/**
	 * Pinta el formulario del widget: solo el título.
	 *
	 * @param array<string, mixed> $instance Valores guardados.
	 */
	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Título:', 'convoca-shifts' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<?php
	}

	/**
	 * Guarda la configuración del widget.
	 *
	 * @param array<string, mixed> $new_instance Valores nuevos.
	 * @param array<string, mixed> $old_instance Valores anteriores.
	 * @return array<string, mixed>
	 */
	public function update( $new_instance, $old_instance ): array {
		$instance          = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? sanitize_text_field( $new_instance['title'] ) : '';
		return $instance;
	}
}
