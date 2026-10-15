<?php
/**
 * Los dos defectos del front de turnos: el botón que no hacía nada (#17) y el listado que
 * imprimía el código a la vista (#18).
 *
 * DEFECTOS QUE FIJAN ESTAS PRUEBAS (medidos en la demo, 15-16/10/2026):
 *
 *   #17 · El JS del botón «Me apunto al siguiente turno libre» usaba `cstData`, un objeto que **no
 *         existe**: el script localiza `convocaShiftsData` (y es el que usa calendario.js, por eso el
 *         calendario sí funcionaba). Al pulsar, el navegador lanzaba un `ReferenceError` y no pasaba
 *         nada: ni petición al endpoint (que existe y funciona) ni mensaje en pantalla. El voluntario
 *         se quedaba creyendo que se había apuntado. De paso, el refresco del calendario buscaba
 *         `window.cstCalendarInstance`, cuando el nombre publicado es `convocaShiftsCalendarInstance`.
 *
 *   #18 · El listado de `[convoca_proximos_turnos]` imprimía el estado con `esc_html()`, y el estado
 *         lleva HTML: en la página se leían las etiquetas (`<span style="color:#d35400...">⚠ Sin
 *         cubrir</span>`). Y el nombre del responsable se concatenaba **sin escapar**, así que la
 *         solución no podía ser «no escapar»: se escapa el nombre y se interpreta el marcado.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

class BotonYListadoTurnosTest extends TestCase {

	/** El fichero de shortcodes, que es donde viven el botón y el listado. */
	private function shortcodes(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/shortcodes.php' );
	}

	/** El JS del calendario, que es el que define el nombre real del objeto y de la instancia. */
	private function calendario_js(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/calendario.js' );
	}

	/** #17 · el objeto que se localiza y el que usa el JS tienen que ser EL MISMO. */
	public function test_el_js_del_boton_usa_el_objeto_que_se_localiza(): void {
		$src = $this->shortcodes();

		preg_match( '/wp_localize_script\(\s*\'[^\']+\',\s*\'([^\']+)\'/', $src, $m );
		$objeto = $m[1] ?? '';

		$this->assertSame( 'convocaShiftsData', $objeto, 'El objeto localizado es el contrato: si cambia, cambia también el JS.' );
		// Se comprueba el USO, no la mención: el comentario del fichero cuenta que antes estaba mal, y
		// buscarlo como texto daba un falso fallo (medido: la prueba caía por su propio comentario).
		$this->assertDoesNotMatchRegularExpression(
			'/\bcstData\s*[\.\[]/',
			$src,
			'`cstData` no existe en ninguna parte: usarlo es un ReferenceError y el botón no hace nada (#17).'
		);

		foreach ( array( 'confirmSignup', 'restUrl', 'nonce' ) as $clave ) {
			$this->assertStringContainsString(
				$objeto . '.' . $clave,
				$src,
				"El botón necesita $objeto.$clave para poder apuntar."
			);
		}
	}

	/** #17 · si la configuración no está, el botón lo DICE (antes: silencio absoluto). */
	public function test_el_boton_avisa_si_falta_la_configuracion(): void {
		$src = $this->shortcodes();

		$this->assertStringContainsString(
			"typeof convocaShiftsData === 'undefined'",
			$src,
			'Sin la configuración del script hay que avisar, no fallar en silencio.'
		);
		$this->assertMatchesRegularExpression(
			'/convoca-shifts-error[^;]*show\(\)/s',
			$src,
			'El aviso tiene que llegar a la pantalla, no solo al registro.'
		);
	}

	/** #17 · refrescar el calendario usa el nombre que de verdad publica `calendario.js`. */
	public function test_el_refresco_del_calendario_usa_el_nombre_real(): void {
		$js = $this->calendario_js();

		preg_match( '/window\.([A-Za-z_][A-Za-z0-9_]*)\s*=\s*calendar\s*;/', $js, $m );
		$instancia = $m[1] ?? '';
		$this->assertNotEmpty( $instancia, 'calendario.js publica su instancia en `window`.' );

		$this->assertStringContainsString(
			'window.' . $instancia,
			$this->shortcodes(),
			"El botón refresca el calendario con `window.$instancia`, que es el nombre que existe."
		);
	}

	/** #18 · el estado se interpreta como marcado y el nombre se escapa. */
	public function test_el_estado_del_listado_se_interpreta_y_el_nombre_se_escapa(): void {
		$src = $this->shortcodes();

		$this->assertStringContainsString(
			'wp_kses_post( $responsable_texto )',
			$src,
			'El estado lleva HTML (la etiqueta de «Sin cubrir»): hay que interpretarlo, no escaparlo.'
		);
		$this->assertStringNotContainsString(
			'esc_html( $responsable_texto )',
			$src,
			'Escapar la cadena entera era el defecto #18: el código salía a la vista.'
		);
		$this->assertStringContainsString(
			'esc_html( $nombre_mostrar )',
			$src,
			'El nombre del responsable viene del usuario: se escapa al construir.'
		);
	}

	/** #18 · un turno sin horario lo dice, en vez de inventarse las 00:00. */
	public function test_un_turno_sin_horario_no_dice_00_00(): void {
		$src = $this->shortcodes();

		$this->assertStringContainsString( '_hora_fin', $src, 'La ausencia de horario se mide con su meta.' );
		$this->assertStringContainsString(
			"'sin hora fijada'",
			$src,
			'Un turno sin horario se anuncia como tal: un 00:00 parece una hora y no lo es.'
		);
	}
}
