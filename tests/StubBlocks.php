<?php
/**
 * Dobles para los bloques: contenido de la entrada, widgets del sitio y registro de bloques.
 *
 * Detecta si la página «necesita» los assets del plugin; sin estos dobles no hay forma de comprobar
 * que una página con `[convoca_calendario]` en un widget recibe su hoja de estilos.
 *
 * @package Convoca\Shifts\Tests
 */

if ( ! function_exists( 'has_block' ) ) {
	/**
	 * ¿La entrada contiene ese bloque?
	 *
	 * @param string $nombre Nombre del bloque.
	 * @param object $post   Entrada.
	 * @return bool
	 */
	function has_block( $nombre, $post = null ) {
		$contenido = is_object( $post ) ? (string) ( $post->post_content ?? '' ) : '';

		return false !== strpos( $contenido, 'wp:' . $nombre );
	}
}

if ( ! function_exists( 'has_shortcode' ) ) {
	/**
	 * ¿El contenido lleva ese shortcode?
	 *
	 * @param string $contenido Contenido.
	 * @param string $tag       Shortcode.
	 * @return bool
	 */
	function has_shortcode( $contenido, $tag ) {
		return (bool) preg_match( '/\[\/?' . preg_quote( (string) $tag, '/' ) . '(\s|\]|\/)/', (string) $contenido );
	}
}

if ( ! function_exists( 'wp_get_sidebars_widgets' ) ) {
	/**
	 * Widgets por barra lateral.
	 *
	 * @return array
	 */
	function wp_get_sidebars_widgets() {
		return (array) ( $GLOBALS['_sh_sidebars'] ?? array() );
	}
}

if ( ! function_exists( 'register_block_type' ) ) {
	/**
	 * Registra un bloque (apuntado con su callback, para poder afirmarlo).
	 *
	 * @param string $nombre Nombre.
	 * @param array  $args   Argumentos.
	 * @return bool
	 */
	function register_block_type( $nombre, $args = array() ) {
		$GLOBALS['_sh_bloques'][ $nombre ] = (array) $args;

		return true;
	}
}

if ( ! function_exists( 'is_singular' ) ) {
	/**
	 * ¿Es la página de un contenido suelto? (controlable desde `$GLOBALS['_sh_singular']`).
	 *
	 * @param string|array $tipos Tipos.
	 * @return bool
	 */
	function is_singular( $tipos = '' ) {
		return (bool) ( $GLOBALS['_sh_singular'] ?? false );
	}
}
