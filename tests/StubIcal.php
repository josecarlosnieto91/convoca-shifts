<?php
/**
 * Dobles de WordPress que necesita el feed iCal.
 *
 * No son no-ops: `get_bloginfo` devuelve el nombre que ponga la prueba, `get_permalink` un enlace de
 * verdad y `status_header` apunta el código. Un doble mudo dejaría pasar un calendario sin nombre o sin
 * enlaces y la prueba se daría por buena.
 *
 * @package Convoca\Shifts\Tests
 */

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Entrada de WordPress.
	 */
	class WP_Post {

		/** @var int Identificador. */
		public $ID = 0;

		/** @var string Tipo. */
		public $post_type = 'post';

		/** @var string Estado. */
		public $post_status = 'publish';

		/** @var string Título. */
		public $post_title = '';

		/** @var string Fecha. */
		public $post_date = '';

		/**
		 * Construye la entrada a partir de un arreglo.
		 *
		 * @param array $datos Campos.
		 */
		public function __construct( array $datos = array() ) {
			foreach ( $datos as $campo => $valor ) {
				$this->$campo = $valor;
			}
		}
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	/**
	 * Datos del sitio.
	 *
	 * @param string $campo Qué se pide.
	 * @return string
	 */
	function get_bloginfo( $campo = '' ) {
		$sitio = $GLOBALS['_sh_sitio'] ?? array();

		return (string) ( $sitio[ $campo ] ?? ( 'name' === $campo ? 'Centro de pruebas' : '' ) );
	}
}

if ( ! function_exists( 'wp_specialchars_decode' ) ) {
	/**
	 * Deshace las entidades de un texto.
	 *
	 * @param string $texto Texto.
	 * @param int    $comillas Comillas a convertir.
	 * @return string
	 */
	function wp_specialchars_decode( $texto, $comillas = ENT_NOQUOTES ) {
		return htmlspecialchars_decode( (string) $texto, $comillas );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * Descompone una URL.
	 *
	 * @param string $url    URL.
	 * @param int    $parte  Parte que se quiere.
	 * @return mixed
	 */
	function wp_parse_url( $url, $parte = -1 ) {
		return -1 === $parte ? parse_url( (string) $url ) : parse_url( (string) $url, $parte );
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	/**
	 * Enlace permanente de una entrada.
	 *
	 * @param int $id Entrada.
	 * @return string|false
	 */
	function get_permalink( $id = 0 ) {
		$enlaces = $GLOBALS['_sh_enlaces'] ?? array();

		return $enlaces[ (int) $id ] ?? 'https://example.test/?p=' . (int) $id;
	}
}

if ( ! function_exists( 'status_header' ) ) {
	/**
	 * Apunta el código de estado que se manda.
	 *
	 * @param int $codigo Código.
	 */
	function status_header( $codigo ) {
		$GLOBALS['_sh_estados'][] = (int) $codigo;
	}
}
