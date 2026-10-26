<?php
/**
 * Convoca Shifts — Personas asignables
 *
 * @package    Convoca\Shifts
 * @subpackage Includes
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 */

namespace Convoca\Shifts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Las personas que se pueden asignar a un turno: las aporta quien las gestiona.
 *
 * Shifts NO administra miembros y no sabe quién es voluntario ni quién es monitor: pregunta por una
 * lista y trabaja con lo que le den. El valor por defecto es **vacío**, así que en un sitio donde nadie
 * aporte personas no hay selector, no se puede asignar y no hay a quién acreditar horas — que es la
 * regla de producto (JC, 21/10/2026: sin la gestión de personas no hay personas asignables ni horas).
 *
 * Quien contesta es Convoca Members, con sus fichas y sus aprobaciones, no con un `get_users()` por rol.
 *
 * @since 2.5.31
 */
class Personas_Asignables {

	/**
	 * Nombre del filtro que aporta las personas.
	 *
	 * @var string
	 */
	public const FILTRO = 'convoca_shifts_personas_asignables';

	/**
	 * Caché por petición: el filtro se llama hasta seis veces en una misma pantalla y la respuesta no
	 * cambia dentro de la misma carga.
	 *
	 * @var array<string, array<int, object{ID:int, display_name:string, first_name:string, user_login:string, user_email:string}>>
	 */
	private static $cache = array();

	/**
	 * Personas asignables para un contexto.
	 *
	 * Devuelve objetos con `ID` y `display_name` para que las plantillas sigan igual que cuando esto
	 * venía de `get_users()`.
	 *
	 * @param string $contexto 'voluntario' o 'monitor'.
	 * @return array<int, object{ID:int, display_name:string, first_name:string, user_login:string, user_email:string}> Lista de personas; vacía si nadie las aporta.
	 */
	public static function lista( string $contexto = 'voluntario' ): array {
		if ( isset( self::$cache[ $contexto ] ) ) {
			return self::$cache[ $contexto ];
		}

		/**
		 * Personas que se pueden asignar a un turno.
		 *
		 * @param array<int, array{id:int, nombre:string}> $personas Lista; por defecto vacía.
		 * @param string                                   $contexto 'voluntario' o 'monitor'.
		 */
		$aportadas = apply_filters( self::FILTRO, array(), $contexto );

		$salida = array();
		foreach ( (array) $aportadas as $persona ) {
			$id = self::id_de( $persona );
			if ( $id <= 0 || isset( $salida[ $id ] ) ) {
				continue;
			}

			$nombre = self::nombre_de( $persona );
			if ( '' === $nombre ) {
				$nombre = '#' . $id;
			}

			$salida[ $id ] = (object) array(
				'ID'           => $id,
				'display_name' => $nombre,
				// Las plantillas venían de `get_users()` y leen estas: se declaran vacías para no cambiar
				// el modo de error de PHP 8 al pintar (y `user_login` sólo se pinta si existe).
				'first_name'   => '',
				'user_login'   => '',
				'user_email'   => self::email_de( $persona, $id ),
			);
		}

		self::$cache[ $contexto ] = array_values( $salida );
		return self::$cache[ $contexto ];
	}

	/**
	 * ¿Hay alguien a quien asignar en este contexto?
	 *
	 * @param string $contexto 'voluntario' o 'monitor'.
	 * @return bool
	 */
	public static function hay( string $contexto = 'voluntario' ): bool {
		return array() !== self::lista( $contexto );
	}

	/**
	 * ¿Está esta persona en la lista? Es la comprobación que impide colar un id por POST.
	 *
	 * @param int    $user_id  Usuario de WordPress.
	 * @param string $contexto 'voluntario' o 'monitor'.
	 * @return bool
	 */
	public static function permitida( int $user_id, string $contexto = 'voluntario' ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		foreach ( self::lista( $contexto ) as $persona ) {
			if ( (int) $persona->ID === $user_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Id que se puede guardar en un meta de asignación.
	 *
	 * Es la guarda de los escritores de `_monitor` y `_id_responsable`: aunque el selector sólo ofrezca
	 * personas de la lista, un POST a mano podría traer cualquier id. **Liberar siempre se puede**
	 * (quitar a alguien no es asignarlo: bloquearlo dejaría turnos con un monitor que ya no debería
	 * estar); asignar, sólo si la persona está en la lista.
	 *
	 * @param int    $user_id  Id propuesto.
	 * @param string $contexto 'voluntario' o 'monitor'.
	 * @param int    $actual   Lo que ya estaba guardado; se conserva si el propuesto no vale.
	 * @return int Id que se guarda.
	 */
	public static function id_a_guardar( int $user_id, string $contexto, int $actual = 0 ): int {
		if ( $user_id <= 0 ) {
			return 0;
		}

		return self::permitida( $user_id, $contexto ) ? $user_id : $actual;
	}

	/**
	 * Texto para la pantalla cuando nadie aporta personas.
	 *
	 * @return string
	 */
	public static function nota_sin_personas(): string {
		return __( 'No hay personas asignables: ningún módulo las aporta. La gestión de personas y las horas las aporta Convoca Members.', 'convoca-shifts' );
	}

	/**
	 * Vacía la caché (pruebas y después de cambiar quién aporta).
	 */
	public static function limpiar_cache(): void {
		self::$cache = array();
	}

	/**
	 * Id de una persona aportada, en cualquiera de las formas razonables.
	 *
	 * @param mixed $persona Lo que devuelva el filtro.
	 * @return int
	 */
	private static function id_de( $persona ): int {
		if ( is_object( $persona ) ) {
			return (int) ( $persona->ID ?? $persona->id ?? 0 );
		}

		if ( is_array( $persona ) ) {
			return (int) ( $persona['id'] ?? $persona['ID'] ?? 0 );
		}

		return (int) $persona;
	}

	/**
	 * Nombre visible de una persona aportada.
	 *
	 * @param mixed $persona Lo que devuelva el filtro.
	 * @return string
	 */
	private static function nombre_de( $persona ): string {
		if ( is_object( $persona ) ) {
			return (string) ( $persona->display_name ?? $persona->nombre ?? '' );
		}

		if ( is_array( $persona ) ) {
			return (string) ( $persona['nombre'] ?? $persona['display_name'] ?? '' );
		}

		return '';
	}

	/**
	 * El correo de una persona, si quien la aporta lo trae.
	 *
	 * La auditoría de horas pinta una columna *Email* por fila y estaba saliendo **siempre vacía**: la lista
	 * declaraba `user_email => ''` para callar el aviso de PHP 8, pero nunca lo rellenaba (shifts#27). Se busca en
	 * la persona aportada —cada proveedor usa su forma— y, si no viene, se pregunta a WordPress por el id, que es
	 * de donde salía antes.
	 *
	 * @param mixed $persona Persona tal como la devolvió el filtro.
	 * @param int   $id      Identificador ya normalizado.
	 * @return string Correo, o cadena vacía si no hay.
	 */
	private static function email_de( $persona, int $id ): string {
		$email = '';

		if ( is_object( $persona ) ) {
			$email = (string) ( $persona->user_email ?? $persona->email ?? '' );
		} elseif ( is_array( $persona ) ) {
			$email = (string) ( $persona['user_email'] ?? $persona['email'] ?? '' );
		}

		if ( '' !== $email ) {
			return $email;
		}

		$usuario = get_userdata( $id );

		return $usuario ? (string) ( $usuario->user_email ?? '' ) : '';
	}
}
