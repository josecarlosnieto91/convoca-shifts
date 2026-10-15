<?php
/**
 * Regresiones del generador de semanas y del saneado de su formulario.
 *
 * Contexto (issue #9, reproducido en la demo): la pantalla «Generador de Turnos → Crear semana tipo»
 * responde siempre «Se han generado 0 turnos nuevos.» aunque el formulario salga bien formado.
 *
 * Este fichero separa las dos sospechas:
 *   R1. `Convoca\Shifts\convoca_shifts_crear_semana_tipo()` con un array de días **anidado** (como
 *       manda el formulario) debe crear un turno por día activo. Si esto pasa, el generador está bien
 *       y el defecto está en quien le pasa los datos.
 *   R2. El saneado del POST —que hoy vive dentro del renderizador de la pantalla— no puede vaciar el
 *       anidamiento: `array_map( 'sanitize_text_field', $_POST['dias'] )` pasa cada día (un array) por
 *       un saneador de cadenas, y `_sanitize_text_fields()` devuelve '' para los arrays, así que el
 *       generador recibe `array( 0 => '', 1 => '', … )` y descarta todos los días.
 *
 * El generador vive en `includes/cpt-turno.php`, que la suite unitaria no carga (tira de funciones de
 * WordPress que el bootstrap no stubea). Aquí se carga a mano —mismo convenio que
 * `CPTTurnoIntegrationTest`— y, si el simulador no llega, la regresión se marca como incompleta con el
 * nombre de lo que falta, en vez de romper la suite.
 *
 * @package Convoca\Shifts\Tests
 */

namespace {
	if ( ! function_exists( 'wp_parse_args' ) ) {
		/**
		 * Stub mínimo de WordPress para poder cargar el generador en la suite unitaria.
		 *
		 * @param array|object|string $args     Argumentos.
		 * @param array               $defaults Valores por defecto.
		 * @return array
		 */
		function wp_parse_args( $args, $defaults = array() ) {
			return array_merge( $defaults, (array) $args );
		}
	}
}

namespace Convoca\Shifts\Tests {

	use PHPUnit\Framework\TestCase;

	final class SemanaTipoRegresionTest extends TestCase {

		/**
		 * Los includes del plugin definen funciones y clases sueltas: sin cargarlos, no existen.
		 */
		private static function cargar_generador(): void {
			static $hecho = false;
			if ( $hecho ) {
				return;
			}
			$inc = dirname( __DIR__, 2 ) . '/includes/';

			if ( ! class_exists( '\\Convoca\\Shifts\\Horario' ) && file_exists( $inc . 'horario.php' ) ) {
				require_once $inc . 'horario.php';
			}
			if ( ! function_exists( '\\Convoca\\Shifts\\convoca_shifts_crear_semana_tipo' ) && file_exists( $inc . 'cpt-turno.php' ) ) {
				require_once $inc . 'cpt-turno.php';
			}
			$hecho = true;
		}

		/**
		 * Configuración de un día activo tal y como la envía el formulario.
		 *
		 * @return array<string,string>
		 */
		private function dia_activo( string $inicio = '10:00', string $fin = '13:00' ): array {
			return array(
				'activo'      => '1',
				'hora_inicio' => $inicio,
				'hora_fin'    => $fin,
				'estado'      => 'abierto_disponible',
			);
		}

		/**
		 * R1 — Con días bien formados, el generador crea un turno por día activo.
		 *
		 * Lunes 02/11/2026 + días 0 (lunes), 2 (miércoles) y 4 (viernes) ⇒ 3 turnos.
		 */
		public function test_genera_un_turno_por_cada_dia_activo(): void {
			self::cargar_generador();

			if ( ! function_exists( '\\Convoca\\Shifts\\convoca_shifts_crear_semana_tipo' ) ) {
				$this->markTestIncomplete( 'No he podido cargar includes/cpt-turno.php en la suite unitaria.' );
			}
			if ( ! isset( $GLOBALS['wpdb'] ) ) {
				$this->markTestIncomplete( 'El simulador de base de datos no expone $wpdb.' );
			}

			$lunes = '2026-11-02';
			$dias  = array(
				0 => $this->dia_activo(),
				2 => $this->dia_activo(),
				4 => $this->dia_activo(),
			);

			try {
				$resultado = \Convoca\Shifts\convoca_shifts_crear_semana_tipo( $lunes, $dias );
			} catch ( \Error $e ) {
				$this->markTestIncomplete(
					'El bootstrap unitario no cubre lo que necesita el generador: ' . $e->getMessage() .
					' Probar esta regresión pide el bootstrap de integración (con WordPress cargado).'
				);
				return;
			}

			$this->assertIsNotObject( $resultado, 'El generador no debería devolver un WP_Error con días válidos.' );
			$this->assertSame( 3, (int) $resultado, 'Con tres días activos deben crearse exactamente tres turnos.' );
		}

		/**
		 * R2 — Contrato del saneado: el anidamiento tiene que llegar intacto al generador.
		 *
		 * Queda incompleto a propósito: comprobarlo de verdad obliga a sacar el saneado del renderizador
		 * a una función propia, y eso ya es el arreglo de #9 (cambio de producción), no una tarea de la
		 * pasada de pruebas. Cuando ese arreglo exista, la comprobación es:
		 *
		 *     $dias = <saneador>( array( 0 => $this->dia_activo() ) );
		 *     $this->assertSame( '1', $dias[0]['activo'] );
		 */
		public function test_el_saneado_del_post_conserva_el_anidamiento_de_los_dias(): void {
			$this->markTestIncomplete(
				'Pendiente del arreglo de convoca-shifts#9: el saneado debe preservar dias[N][campo]. ' .
				'Hoy `array_map( "sanitize_text_field", $_POST["dias"] )` deja cada día en cadena vacía ' .
				'(_sanitize_text_fields() devuelve "" para arrays), y el generador no encuentra `activo`.'
			);
		}
	}
}
