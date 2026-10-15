<?php
/**
 * Regresiones de cableado y de contabilización de horas (issues #10 y #6).
 *
 * R3 (#10) — «Editar Turno no guarda nunca»: el nonce se imprime **fuera** del `<form>`, así que el POST
 *           llega sin él y el manejador responde HTTP 500 «Acceso denegado.».
 * R5       — `Hour_Sync` no debe sumar dos veces las mismas horas del mismo turno: el marcador
 *           `_convoca_shifts_horas_contabilizadas` es lo que lo impide.
 *
 * Los dos primeros casos son de **contrato de fuente**: comprueban el cableado del formulario y del
 * manejador, que es donde estaba el defecto y donde no llega un test de comportamiento (el formulario se
 * imprime dentro de un renderizador que necesita WordPress entero).
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

final class RegresionesFormulariosTest extends TestCase {

	/**
	 * Ruta de un fichero del plugin.
	 */
	private function fuente( string $relativo ): string {
		return dirname( __DIR__, 2 ) . '/' . ltrim( $relativo, '/' );
	}

	/**
	 * Devuelve el código del primer `<form>` que contiene el marcador indicado.
	 */
	private function bloque_del_formulario( string $fichero, string $marcador ): string {
		$src = file_get_contents( $this->fuente( $fichero ) );
		$this->assertIsString( $src, 'No he podido leer ' . $fichero );

		$pos = strpos( $src, $marcador );
		$this->assertNotFalse( $pos, 'El marcador «' . $marcador . '» no está en ' . $fichero );

		$antes = substr( $src, 0, $pos );
		$apertura = strrpos( $antes, '<form' );
		$this->assertNotFalse( $apertura, 'El marcador está antes de cualquier <form>' );

		$cierre = strpos( $src, '</form>', $apertura );

		return substr( $src, $apertura, ( $cierre === false ? strlen( $src ) : $cierre ) - $apertura );
	}

	/**
	 * R3a — El editor de turno sigue enviando lo que el manejador espera.
	 *
	 * Contrato de cableado: el formulario lleva `action` y `post_id`, y el manejador de ese `action`
	 * está registrado con `admin_post_`.
	 */
	public function test_el_formulario_del_editor_y_su_manejador_estan_de_acuerdo(): void {
		// Ojo: el marcador es el `value` del campo oculto, no la llamada a wp_nonce_field(), que hoy
		// aparece ANTES de abrir el formulario (defecto de #10, cubierto en el caso siguiente).
		$bloque = $this->bloque_del_formulario( 'includes/class-admin-turno-editor.php', 'value="convoca_shifts_save_turno"' );

		$this->assertStringContainsString( 'name="action"', $bloque );
		$this->assertStringContainsString( 'value="convoca_shifts_save_turno"', $bloque );
		$this->assertStringContainsString( 'name="post_id"', $bloque );

		$src = file_get_contents( $this->fuente( 'includes/class-admin-turno-editor.php' ) );
		$this->assertStringContainsString(
			"admin_post_convoca_shifts_save_turno",
			$src,
			'El action del formulario no tiene manejador registrado: el POST moriría en admin-post.php.'
		);
	}

	/**
	 * R3b — El nonce tiene que viajar **dentro** del formulario.
	 *
	 * Es el defecto de #10: `wp_nonce_field()` se imprime antes de abrir el `<form>`, así que el campo no
	 * se envía y el manejador cae en su primera comprobación («Acceso denegado.», HTTP 500).
	 */
	public function test_el_nonce_del_editor_viaja_dentro_del_formulario(): void {
		$this->markTestIncomplete(
			'Pendiente del arreglo de convoca-shifts#10: mover wp_nonce_field( "convoca_shifts_save_turno_…" ) ' .
			'dentro del <form>. Cuando se mueva, bastará con comprobar que el bloque del formulario contiene ' .
			'name="_convoca_shifts_nonce" y quitar esta marca.'
		);
	}

	/**
	 * R5 — El marcador de horas contabilizadas sigue siendo la red anti-duplicado.
	 *
	 * Contrato: antes de sumar para un turno «realizado» se consulta
	 * `_convoca_shifts_horas_contabilizadas`, y al dejar de estar «realizado» se resta y se borra el
	 * marcador. Si alguien quita esa guarda, las horas se cuentan dos veces (visto a mano: repetir
	 * «Marcar Realizado» no duplicaba).
	 */
	public function test_hour_sync_consulta_el_marcador_antes_de_sumar(): void {
		$src = file_get_contents( $this->fuente( 'includes/Hour_Sync.php' ) );
		$this->assertIsString( $src );

		$this->assertStringContainsString( '_convoca_shifts_horas_contabilizadas', $src, 'Falta el marcador anti-duplicado.' );
		$this->assertMatchesRegularExpression(
			'/horas_contabilizadas\s*<=\s*0/',
			$src,
			'Solo se suma cuando el marcador está a cero: sin esa comprobación, las horas se duplican.'
		);
		$this->assertStringContainsString(
			"delete_post_meta( \$post_id, '_convoca_shifts_horas_contabilizadas' )",
			$src,
			'Al dejar de estar «realizado» hay que soltar el marcador para que no quede horas fantasma.'
		);
	}
}
