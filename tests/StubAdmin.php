<?php
/**
 * Dobles de WordPress para las pantallas de administración (menú, nonces, redirecciones, formatos).
 *
 * Apuntan lo que se les pide en `$GLOBALS['_sh_admin_*']`: una pantalla se prueba por lo que registra
 * y por lo que pinta, no por no reventar.
 *
 * @package Convoca\Shifts\Tests
 */

if ( ! function_exists( 'add_submenu_page' ) ) {
	/**
	 * Añade una página al menú del panel.
	 *
	 * @param string   $padre    Menú padre.
	 * @param string   $titulo   Título.
	 * @param string   $menu     Texto del menú.
	 * @param string   $cap      Capacidad.
	 * @param string   $slug     Identificador.
	 * @param callable $callback Callback.
	 * @return string
	 */
	function add_submenu_page( $padre, $titulo, $menu, $cap, $slug, $callback = '' ) {
		$GLOBALS['_sh_admin_paginas'][] = array(
			'padre'    => $padre,
			'titulo'   => $titulo,
			'menu'     => $menu,
			'cap'      => $cap,
			'slug'     => $slug,
			'callback' => $callback,
		);

		return 'hook-' . $slug;
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	/**
	 * Imprime el campo de nonce.
	 *
	 * @param string $accion Acción.
	 * @param string $nombre Nombre del campo.
	 * @return void
	 */
	function wp_nonce_field( $accion = '-1', $nombre = '_wpnonce' ) {
		$GLOBALS['_sh_admin_nonces'][] = $accion;

		echo '<input type="hidden" name="' . htmlspecialchars( (string) $nombre, ENT_QUOTES ) . '" value="nonce-' . htmlspecialchars( (string) $accion, ENT_QUOTES ) . '">';
	}
}

if ( ! function_exists( 'check_admin_referer' ) ) {
	/**
	 * Comprueba el nonce. La prueba decide el resultado con `$GLOBALS['_sh_admin_nonce_ok']`.
	 *
	 * @param string $accion Acción.
	 * @return bool
	 */
	function check_admin_referer( $accion = '-1' ) {
		$GLOBALS['_sh_admin_nonces_comprobados'][] = $accion;

		$ok = (bool) ( $GLOBALS['_sh_admin_nonce_ok'] ?? false );

		// En WordPress esta función no devuelve falso: **mata** la petición (`wp_nonce_ays` → `wp_die`).
		// Con `_sh_admin_nonce_mata` el doble hace lo mismo, para poder probar los manejadores que no
		// comprueban el valor de vuelta y confían en esa muerte.
		if ( ! $ok && ! empty( $GLOBALS['_sh_admin_nonce_mata'] ) ) {
			throw new RuntimeException( 'wp_die: nonce no válido' );
		}

		return $ok;
	}
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	/**
	 * Redirección segura (apuntada, sin salir).
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	function wp_safe_redirect( $url ) {
		$GLOBALS['_sh_admin_redirecciones'][] = $url;

		return true;
	}
}

if ( ! function_exists( 'nocache_headers' ) ) {
	/**
	 * Cabeceras de no caché (no hace nada, pero existe).
	 *
	 * @return void
	 */
	function nocache_headers() {
		$GLOBALS['_sh_admin_sin_cache'] = true;
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	/**
	 * Número con formato del sitio (aquí: sin separadores, para poder afirmarlo).
	 *
	 * @param float $numero   Número.
	 * @param int   $decimales Decimales.
	 * @return string
	 */
	function number_format_i18n( $numero, $decimales = 0 ) {
		return number_format( (float) $numero, (int) $decimales );
	}
}

if ( ! function_exists( 'date_i18n' ) ) {
	/**
	 * Fecha con formato del sitio.
	 *
	 * @param string $formato Formato.
	 * @param int    $ts      Marca de tiempo.
	 * @return string
	 */
	function date_i18n( $formato, $ts = null ) {
		return gmdate( (string) $formato, (int) ( $ts ?? time() ) );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * JSON de WordPress.
	 *
	 * @param mixed $datos     Datos.
	 * @param int   $opciones  Opciones.
	 * @param int   $profundidad Profundidad.
	 * @return string
	 */
	function wp_json_encode( $datos, $opciones = 0, $profundidad = 512 ) {
		return (string) json_encode( $datos, (int) $opciones, (int) $profundidad );
	}
}

if ( ! function_exists( 'wp_get_current_user' ) ) {
	/**
	 * Persona con la sesión abierta (la del arnés).
	 *
	 * @return object
	 */
	function wp_get_current_user() {
		return get_userdata( (int) get_current_user_id() );
	}
}

if ( ! function_exists( 'remove_meta_box' ) ) {
	/**
	 * Quita una caja de edición (apuntada, para poder afirmar que se quita la nuestra).
	 *
	 * @param string $id      Identificador.
	 * @param string $pantalla Pantalla.
	 * @param string $contexto Contexto.
	 * @return void
	 */
	function remove_meta_box( $id, $pantalla = '', $contexto = 'advanced' ) {
		$GLOBALS['_sh_metaboxes_quitadas'][] = array(
			'id'       => $id,
			'pantalla' => $pantalla,
			'contexto' => $contexto,
		);
	}
}

if ( ! function_exists( 'get_gmt_from_date' ) ) {
	/**
	 * Fecha en GMT a partir de una local.
	 *
	 * @param string $fecha Fecha local.
	 * @return string
	 */
	function get_gmt_from_date( $fecha ) {
		$ts = strtotime( (string) $fecha );

		return false === $ts ? '' : gmdate( 'Y-m-d H:i:s', $ts );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Sanea una clave (minúsculas, guiones y barras bajas).
	 *
	 * @param string $clave Clave.
	 * @return string
	 */
	function sanitize_key( $clave ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $clave ) );
	}
}

if ( ! function_exists( 'register_setting' ) ) {
	/**
	 * Registra un ajuste (apuntado con su grupo y su saneador).
	 *
	 * @param string   $grupo    Grupo.
	 * @param string   $nombre   Nombre.
	 * @param callable $sanear   Saneador.
	 * @param array    $args     Argumentos.
	 * @return void
	 */
	function register_setting( $grupo, $nombre, $sanear = '', $args = array() ) {
		$GLOBALS['_sh_settings'][] = array(
			'grupo'  => $grupo,
			'nombre' => $nombre,
			'sanear' => $sanear,
		);
	}
}

if ( ! function_exists( 'remove_submenu_page' ) ) {
	/**
	 * Quita una página del menú (apuntada, para poder afirmar que se quita).
	 *
	 * @param string $padre Menú padre.
	 * @param string $slug  Identificador.
	 * @return void
	 */
	function remove_submenu_page( $padre, $slug ) {
		$GLOBALS['_sh_submenus_quitados'][] = array(
			'padre' => $padre,
			'slug'  => $slug,
		);
	}
}
