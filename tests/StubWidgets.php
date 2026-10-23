<?php
/**
 * Dobles de la API de widgets de WordPress.
 *
 * `WP_Widget` no es un no-op: guarda el identificador y el nombre, y genera los `id`/`name` de los campos
 * como el de verdad (`widget-<id_base>__<number>__<campo>`). Sin eso no se puede afirmar que el formulario
 * del widget guarde donde toca.
 *
 * @package Convoca\Shifts\Tests
 */

if ( ! class_exists( 'WP_Widget' ) ) {
	/**
	 * Widget de WordPress.
	 */
	class WP_Widget {

		/** @var string Identificador base. */
		public $id_base = '';

		/** @var string Nombre. */
		public $name = '';

		/** @var array Opciones. */
		public $widget_options = array();

		/** @var int Número de instancia. */
		public $number = 2;

		/**
		 * Construye el widget.
		 *
		 * @param string $id_base Identificador.
		 * @param string $name    Nombre.
		 * @param array  $opciones Opciones.
		 */
		public function __construct( $id_base = '', $name = '', $opciones = array() ) {
			$this->id_base        = (string) $id_base;
			$this->name           = (string) $name;
			$this->widget_options = (array) ( $opciones['classname'] ?? $opciones );
			$this->widget_options['description'] = $opciones['description'] ?? '';
		}

		/**
		 * Identificador del campo en el formulario.
		 *
		 * @param string $campo Campo.
		 * @return string
		 */
		public function get_field_id( $campo ) {
			return 'widget-' . $this->id_base . '__' . $this->number . '__' . $campo;
		}

		/**
		 * Nombre del campo en el formulario.
		 *
		 * @param string $campo Campo.
		 * @return string
		 */
		public function get_field_name( $campo ) {
			return 'widget-' . $this->id_base . '[' . $this->number . '][' . $campo . ']';
		}
	}
}

if ( ! function_exists( 'register_widget' ) ) {
	/**
	 * Registra un widget.
	 *
	 * @param string $clase Clase del widget.
	 */
	function register_widget( $clase ) {
		$GLOBALS['_sh_widgets_registrados'][] = $clase;
	}
}
