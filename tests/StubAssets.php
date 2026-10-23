<?php
/**
 * Dobles de WordPress que necesitan los shortcodes de Shifts y que el arnés de Core no trae.
 *
 * Son las funciones de cola de recursos (registrar y encolar estilos y guiones), la localización de
 * datos hacia el guion, la URL de acceso y la de la API. Todas apuntan lo que se les pide en
 * `$GLOBALS['_sh_*']`, porque lo que hay que poder afirmar es **qué se encola y con qué datos**, no
 * solo que la llamada no reviente.
 *
 * No se toca el arnés de Convoca Core: lo comparten otros plugins.
 *
 * @package Convoca\Shifts\Tests
 */

if ( ! defined( 'CONVOCA_SHIFTS_URL' ) ) {
	define( 'CONVOCA_SHIFTS_URL', 'https://ejemplo.example.test/wp-content/plugins/convoca-shifts/' );
}
if ( ! defined( 'CONVOCA_SHIFTS_VERSION' ) ) {
	define( 'CONVOCA_SHIFTS_VERSION', '9.9.9-pruebas' );
}

if ( ! function_exists( 'wp_register_style' ) ) {
	/**
	 * Registra una hoja de estilos.
	 *
	 * @param string $handle Identificador.
	 * @param string $src    Origen.
	 * @param array  $deps   Dependencias.
	 * @param string $ver    Versión.
	 * @param string $media  Medio.
	 * @return bool
	 */
	function wp_register_style( $handle, $src = '', $deps = array(), $ver = '', $media = 'all' ) {
		unset( $media );
		$GLOBALS['_sh_registrados'][ $handle ] = array(
			'tipo' => 'estilo',
			'src'  => $src,
			'deps' => (array) $deps,
			'ver'  => $ver,
		);

		return true;
	}
}

if ( ! function_exists( 'wp_register_script' ) ) {
	/**
	 * Registra un guion.
	 *
	 * @param string $handle Identificador.
	 * @param string $src    Origen.
	 * @param array  $deps   Dependencias.
	 * @param string $ver    Versión.
	 * @param bool   $footer Va al pie.
	 * @return bool
	 */
	function wp_register_script( $handle, $src = '', $deps = array(), $ver = '', $footer = false ) {
		$GLOBALS['_sh_registrados'][ $handle ] = array(
			'tipo'   => 'guion',
			'src'    => $src,
			'deps'   => (array) $deps,
			'ver'    => $ver,
			'footer' => (bool) $footer,
		);

		return true;
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	/**
	 * Encola una hoja de estilos.
	 *
	 * @param string $handle Identificador.
	 * @param string $src    Origen.
	 * @param array  $deps   Dependencias.
	 * @param string $ver    Versión.
	 * @param string $media  Medio.
	 * @return void
	 */
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = '', $media = 'all' ) {
		unset( $src, $deps, $ver, $media );

		$GLOBALS['_sh_encolados'][] = array(
			'tipo'   => 'estilo',
			'handle' => $handle,
		);
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	/**
	 * Encola un guion.
	 *
	 * @param string $handle Identificador.
	 * @param string $src    Origen.
	 * @param array  $deps   Dependencias.
	 * @param string $ver    Versión.
	 * @param bool   $footer Va al pie.
	 * @return void
	 */
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = '', $footer = false ) {
		unset( $src, $deps, $ver, $footer );

		$GLOBALS['_sh_encolados'][] = array(
			'tipo'   => 'guion',
			'handle' => $handle,
		);
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	/**
	 * Pasa datos del servidor al guion.
	 *
	 * @param string $handle Identificador del guion.
	 * @param string $nombre Nombre del objeto que verá el guion.
	 * @param array  $datos  Datos.
	 * @return bool
	 */
	function wp_localize_script( $handle, $nombre, $datos = array() ) {
		$GLOBALS['_sh_localizados'][ $nombre ] = array(
			'handle' => $handle,
			'datos'  => (array) $datos,
		);

		return true;
	}
}

if ( ! function_exists( 'wp_login_url' ) ) {
	/**
	 * URL de acceso del sitio.
	 *
	 * @param string $redirect A dónde volver.
	 * @return string
	 */
	function wp_login_url( $redirect = '' ) {
		return 'https://ejemplo.example.test/acceso/';
	}
}

if ( ! function_exists( 'rest_url' ) ) {
	/**
	 * URL de la API REST.
	 *
	 * @param string $ruta Ruta.
	 * @return string
	 */
	function rest_url( $ruta = '' ) {
		return 'https://ejemplo.example.test/wp-json/' . ltrim( (string) $ruta, '/' );
	}
}

if ( ! function_exists( 'esc_js' ) ) {
	/**
	 * Escapa para JavaScript.
	 *
	 * @param string $texto Texto.
	 * @return string
	 */
	function esc_js( $texto ) {
		return addslashes( (string) $texto );
	}
}

if ( ! function_exists( 'get_post_timestamp' ) ) {
	/**
	 * Marca de tiempo de una entrada, si el doble la trae.
	 *
	 * @param object $post Entrada.
	 * @return int
	 */
	function get_post_timestamp( $post ) {
		return (int) ( $post->timestamp ?? 0 );
	}
}

if ( ! function_exists( 'add_shortcode' ) ) {
	/**
	 * Registra un shortcode.
	 *
	 * @param string   $tag      Nombre.
	 * @param callable $callback Callback.
	 * @return bool
	 */
	function add_shortcode( $tag, $callback ) {
		$GLOBALS['_wp_stores']['shortcodes'][ $tag ] = $callback;

		return true;
	}
}

if ( ! function_exists( 'shortcode_atts' ) ) {
	/**
	 * Junta los atributos con sus valores por defecto.
	 *
	 * @param array $pares    Valores por defecto.
	 * @param array $atts     Atributos recibidos.
	 * @param string $shortcode Nombre del shortcode.
	 * @return array
	 */
	function shortcode_atts( $pares, $atts, $shortcode = '' ) {
		$atts = (array) $atts;

		foreach ( $pares as $clave => $valor ) {
			if ( ! array_key_exists( $clave, $atts ) ) {
				$atts[ $clave ] = $valor;
			}
		}

		return $atts;
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	/**
	 * Imprime un texto escapado.
	 *
	 * @param string $texto Texto.
	 * @param string $dominio Dominio de traducción.
	 * @return void
	 */
	function esc_html_e( $texto, $dominio = '' ) {
		echo htmlspecialchars( (string) $texto, ENT_QUOTES );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * Sanea una URL para guardarla o pasarla, sin escapar para HTML.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url_raw( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	/**
	 * Nonce de mentira, pero no vacío: las pruebas comprueban que hay uno.
	 *
	 * @param string $accion Acción.
	 * @return string
	 */
	function wp_create_nonce( $accion = '' ) {
		return 'nonce-' . substr( md5( (string) $accion ), 0, 8 );
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * Lee una caché temporal.
	 *
	 * @param string $clave Clave.
	 * @return mixed
	 */
	function get_transient( $clave ) {
		return $GLOBALS['_wp_stores']['transients'][ $clave ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * Guarda una caché temporal.
	 *
	 * @param string $clave      Clave.
	 * @param mixed  $valor      Valor.
	 * @param int    $expiracion Segundos.
	 * @return bool
	 */
	function set_transient( $clave, $valor, $expiracion = 0 ) {
		$GLOBALS['_wp_stores']['transients'][ $clave ] = $valor;

		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	/**
	 * Borra una caché temporal.
	 *
	 * @param string $clave Clave.
	 * @return bool
	 */
	function delete_transient( $clave ) {
		$GLOBALS['_wp_stores']['transients_borrados'][] = $clave;

		unset( $GLOBALS['_wp_stores']['transients'][ $clave ] );

		return true;
	}
}

if ( ! function_exists( 'get_the_time' ) ) {
	/**
	 * Hora de una entrada en el formato pedido.
	 *
	 * @param string $formato Formato.
	 * @param object $post    Entrada.
	 * @return string
	 */
	function get_the_time( $formato = '', $post = null ) {
		$ts = is_object( $post ) ? (int) ( $post->timestamp ?? 0 ) : 0;

		return $ts > 0 ? gmdate( (string) $formato, $ts ) : '';
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * Añade parámetros a una URL.
	 *
	 * @param string|array $clave Clave o pares.
	 * @param string       $valor Valor.
	 * @param string       $url   URL.
	 * @return string
	 */
	function add_query_arg( $clave, $valor = '', $url = '' ) {
		if ( is_array( $clave ) ) {
			$url   = '' !== $valor ? (string) $valor : (string) $url;
			$pares = $clave;
		} else {
			$url   = '' !== $url ? (string) $url : (string) $valor;
			$pares = array( $clave => $valor );
		}

		return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . http_build_query( $pares );
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	/**
	 * Asegura la barra final.
	 *
	 * @param string $texto Texto.
	 * @return string
	 */
	function trailingslashit( $texto ) {
		return rtrim( (string) $texto, '/' ) . '/';
	}
}

if ( ! function_exists( 'wp_salt' ) ) {
	/**
	 * Sal del sitio (fija, para que el testigo del feed sea reproducible).
	 *
	 * @param string $esquema Esquema.
	 * @return string
	 */
	function wp_salt( $esquema = 'auth' ) {
		return 'sal-de-pruebas-' . $esquema;
	}
}
