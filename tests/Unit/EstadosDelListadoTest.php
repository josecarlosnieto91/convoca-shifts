<?php
/**
 * Las estadísticas y el listado de turnos tienen que contar los mismos estados.
 *
 * El defecto que vigila: el listado usa `any` (publish, future, draft, pending, private) y las
 * estadísticas filtraban solo `publish`, así que un turno en borrador se veía en la tabla y no
 * sumaba en el total del responsable.
 *
 * @package ConvocaShifts
 */

namespace Convoca\Shifts\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guardián de coherencia entre listado y estadísticas.
 */
final class EstadosDelListadoTest extends TestCase {

	/**
	 * Las estadísticas no vuelven a filtrar `publish` a mano.
	 */
	public function test_las_estadisticas_usan_la_lista_del_listado(): void {
		$fuente = (string) file_get_contents( __DIR__ . '/../../includes/admin-estadisticas.php' );

		$this->assertStringContainsString(
			'Admin_Turnos_List::ESTADOS_LISTADOS',
			$fuente,
			'Las estadísticas deben tomar los estados de Admin_Turnos_List::ESTADOS_LISTADOS.'
		);

		$this->assertStringNotContainsString(
			"p.post_status = 'publish'",
			$fuente,
			'Queda una consulta filtrando solo publish: el total no cuadraría con la tabla.'
		);
	}

	/**
	 * La lista es la que corresponde a `any` en WP_Query.
	 */
	public function test_la_lista_cubre_los_estados_de_any(): void {
		$esperados = array( 'publish', 'future', 'draft', 'pending', 'private' );

		$this->assertSame(
			$esperados,
			\Convoca\Shifts\Admin_Turnos_List::ESTADOS_LISTADOS,
			'`any` en WP_Query son estos cinco estados: fuera quedan trash, auto-draft e inherit.'
		);
	}
}
