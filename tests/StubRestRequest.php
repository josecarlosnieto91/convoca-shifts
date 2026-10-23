<?php
/**
 * Petición REST de mentira, con parámetros que la prueba pone.
 *
 * Los endpoints de la API de turnos leen todo de aquí; sin esto no hay forma de llamarlos.
 *
 * @package Convoca\Shifts\Tests
 */

if ( ! class_exists( 'WP_REST_Request' ) ) {
	/**
	 * Petición REST (doble).
	 */
	class WP_REST_Request {

		/**
		 * Parámetros de la petición.
		 *
		 * @var array<string, mixed>
		 */
		private $params = array();

		/**
		 * Construye la petición con sus parámetros.
		 *
		 * @param array $params Parámetros.
		 */
		public function __construct( array $params = array() ) {
			$this->params = $params;
		}

		/**
		 * Devuelve un parámetro.
		 *
		 * @param string $clave   Nombre.
		 * @param mixed  $defecto Valor por defecto.
		 * @return mixed
		 */
		public function get_param( $clave, $defecto = null ) {
			return $this->params[ $clave ] ?? $defecto;
		}

		/**
		 * Todos los parámetros.
		 *
		 * @return array
		 */
		public function get_params() {
			return $this->params;
		}

		/**
		 * Cambia un parámetro.
		 *
		 * @param string $clave Nombre.
		 * @param mixed  $valor Valor.
		 * @return void
		 */
		public function set_param( $clave, $valor ) {
			$this->params[ $clave ] = $valor;
		}
	}
}
