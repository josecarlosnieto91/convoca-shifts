<?php
/**
 * Respuesta y utilidades REST que faltan en el arnés.
 *
 * @package Convoca\Shifts\Tests
 */

if ( ! class_exists( 'WP_REST_Response' ) ) {
	/**
	 * Respuesta REST (doble que guarda datos y estado).
	 */
	class WP_REST_Response {

		/**
		 * Datos.
		 *
		 * @var mixed
		 */
		public $data;

		/**
		 * Estado HTTP.
		 *
		 * @var int
		 */
		public $status;

		/**
		 * Construye la respuesta.
		 *
		 * @param mixed $data   Datos.
		 * @param int   $status Estado.
		 */
		public function __construct( $data = null, $status = 200 ) {
			$this->data   = $data;
			$this->status = (int) $status;
		}

		/**
		 * Datos de la respuesta.
		 *
		 * @return mixed
		 */
		public function get_data() {
			return $this->data;
		}

		/**
		 * Estado de la respuesta.
		 *
		 * @return int
		 */
		public function get_status() {
			return $this->status;
		}
	}
}

if ( ! function_exists( 'rest_ensure_response' ) ) {
	/**
	 * Envuelve lo que devuelve un endpoint en una respuesta REST.
	 *
	 * @param mixed $respuesta Respuesta.
	 * @return WP_REST_Response
	 */
	function rest_ensure_response( $respuesta ) {
		return $respuesta instanceof WP_REST_Response ? $respuesta : new WP_REST_Response( $respuesta );
	}
}

if ( ! function_exists( 'wp_timezone_string' ) ) {
	/**
	 * Zona horaria del sitio.
	 *
	 * @return string
	 */
	function wp_timezone_string() {
		return 'Europe/Madrid';
	}
}
