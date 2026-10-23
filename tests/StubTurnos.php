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

if ( ! function_exists( 'get_term_meta' ) ) {
	/**
	 * Meta de un término.
	 *
	 * @param int    $term_id Término.
	 * @param string $clave   Clave.
	 * @param bool   $single  Un solo valor.
	 * @return mixed
	 */
	function get_term_meta( $term_id, $clave = '', $single = false ) {
		$valor = $GLOBALS['_sh_term_meta'][ (int) $term_id ][ $clave ] ?? '';

		return $single ? $valor : (array) $valor;
	}
}

if ( ! function_exists( 'update_term_meta' ) ) {
	/**
	 * Guarda la meta de un término.
	 *
	 * @param int    $term_id Término.
	 * @param string $clave   Clave.
	 * @param mixed  $valor   Valor.
	 * @return bool
	 */
	function update_term_meta( $term_id, $clave, $valor ) {
		$GLOBALS['_sh_term_meta'][ (int) $term_id ][ $clave ] = $valor;
		$GLOBALS['_sh_term_meta_updates'][]                   = array(
			'term'  => (int) $term_id,
			'clave' => $clave,
			'valor' => $valor,
		);

		return true;
	}
}

if ( ! function_exists( 'get_term_by' ) ) {
	/**
	 * Término por campo (nombre, slug o id).
	 *
	 * @param string $campo   Campo.
	 * @param mixed  $valor   Valor.
	 * @param string $tax     Taxonomía.
	 * @return object|null
	 */
	function get_term_by( $campo, $valor, $tax = '' ) {
		foreach ( $GLOBALS['_sh_terminos_existentes'] ?? array() as $termino ) {
			$termino = (object) $termino;
			if ( 'name' === $campo && (string) $termino->name === (string) $valor ) {
				return $termino;
			}
			if ( 'slug' === $campo && (string) $termino->slug === (string) $valor ) {
				return $termino;
			}
			if ( 'id' === $campo && (int) $termino->term_id === (int) $valor ) {
				return $termino;
			}
		}

		return null;
	}
}

if ( ! function_exists( 'is_email' ) ) {
	/**
	 * ¿Es un correo?
	 *
	 * @param string $valor Valor.
	 * @return bool
	 */
	function is_email( $valor ) {
		return (bool) filter_var( (string) $valor, FILTER_VALIDATE_EMAIL );
	}
}
