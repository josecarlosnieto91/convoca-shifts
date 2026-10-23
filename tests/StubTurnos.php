<?php
/**
 * Dobles de WordPress que necesitan los turnos: argumentos, términos, publicación y el registro de
 * actividad (que vive en el arranque del plugin y aquí se apunta).
 *
 * @package Convoca\Shifts\Tests
 */

if ( ! function_exists( 'wp_parse_args' ) ) {
	/**
	 * Junta argumentos con sus valores por defecto.
	 *
	 * @param array|object $args     Argumentos.
	 * @param array        $defaults Valores por defecto.
	 * @return array
	 */
	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( (array) $defaults, (array) $args );
	}
}

if ( ! function_exists( 'wp_publish_post' ) ) {
	/**
	 * Publica una entrada.
	 *
	 * @param int $post_id Entrada.
	 * @return void
	 */
	function wp_publish_post( $post_id ) {
		$GLOBALS['_sh_publicados'][] = (int) $post_id;
	}
}

if ( ! function_exists( 'wp_set_post_terms' ) ) {
	/**
	 * Asigna términos a una entrada.
	 *
	 * @param int    $post_id Entrada.
	 * @param array  $terms   Términos.
	 * @param string $tax     Taxonomía.
	 * @return bool
	 */
	function wp_set_post_terms( $post_id, $terms = array(), $tax = 'post_tag' ) {
		$GLOBALS['_sh_terminos'][ (int) $post_id ][ $tax ] = $terms;

		return true;
	}
}

if ( ! function_exists( 'wp_set_object_terms' ) ) {
	/**
	 * Asigna términos a un objeto.
	 *
	 * @param int    $object_id Objeto.
	 * @param mixed  $terms     Términos.
	 * @param string $tax       Taxonomía.
	 * @return array
	 */
	function wp_set_object_terms( $object_id, $terms, $tax = 'post_tag' ) {
		$GLOBALS['_sh_terminos'][ (int) $object_id ][ $tax ] = (array) $terms;

		return (array) $terms;
	}
}

if ( ! function_exists( 'get_term' ) ) {
	/**
	 * Un término de taxonomía (los que siembra la prueba).
	 *
	 * @param int    $term_id Término.
	 * @param string $tax     Taxonomía.
	 * @return object|\WP_Error|null
	 */
	function get_term( $term_id, $tax = '' ) {
		$terminos = $GLOBALS['_sh_terminos_existentes'][ (int) $term_id ] ?? null;

		return null === $terminos ? null : (object) $terminos;
	}
}

if ( ! function_exists( 'get_post_type' ) ) {
	/**
	 * Tipo de contenido de una entrada.
	 *
	 * @param int|null $post_id Entrada.
	 * @return string|false
	 */
	function get_post_type( $post_id = null ) {
		$post_id = (int) ( $post_id ?? 0 );
		$tipo    = $GLOBALS['_wp_stores']['post_types'][ $post_id ] ?? null;

		if ( null !== $tipo ) {
			return $tipo;
		}

		return $GLOBALS['_wp_stores']['posts'][ $post_id ]->post_type ?? false;
	}
}
