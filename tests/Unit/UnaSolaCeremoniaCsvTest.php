<?php
/**
 * Comprobación de contrato: en convoca-shifts, el CSV se escribe en UN solo sitio (issue convoca-core#11
 * / convoca-core#16).
 *
 * Por qué existe: los dos exportadores de este plugin abrían el CSV a mano —cuatro `header()`, el BOM,
 * `fopen( 'php://output' )`, `fputcsv`—. Medido el 15/10/2026: los dos escribían con el separador por
 * omisión de PHP (`,`), que Excel en español no reconoce; el de turnos no escapaba **ninguna** celda
 * (las notas de un turno con `=cmd|…` viajaban tal cual a la hoja de quien la abre) y el de estadísticas
 * escapaba a mano con un `class_exists` de por medio, que dejaba la protección al azar de si el núcleo
 * estaba cargado. Las columnas de cero (`Faltas`, `Realizados`) salían en blanco.
 *
 * El contrato: la ceremonia vive en `\Convoca\Core\Utils::stream_csv()`. Aquí se comprueba que los
 * dos exportadores la usan y que nadie ha vuelto a abrir el flujo por su cuenta.
 *
 * Prueba en negativo (ejecutada el 15/10/2026): devolviendo a `includes/cpt-turno.php` su
 * `fopen( 'php://output', 'w' )` + `fputcsv`, las dos primeras comprobaciones caen nombrando el fichero.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

class UnaSolaCeremoniaCsvTest extends TestCase {

	/**
	 * Exportadores del plugin: fichero => motivo por el que declara su propia ceremonia.
	 *
	 * Están los tres que descargan un CSV. Si aparece un cuarto, entra aquí (y el contrato lo pedirá
	 * en cuanto la primera comprobación vea su `php://output`).
	 *
	 * @var array<string, string>
	 */
	private const EXPORTADORES = array(
		'includes/cpt-turno.php'         => 'Listado de turnos del centro social.',
		'includes/admin-estadisticas.php' => 'Estadísticas de voluntariado (turnos, faltas y horas por persona).',
	);

	private function raiz(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Ficheros PHP del repositorio, sin dependencias, pruebas ni paquetes generados.
	 *
	 * @return array<int, string> Rutas relativas a la raíz.
	 */
	private function ficherosPhp(): array {
		$raiz   = $this->raiz();
		$omitir = array( 'vendor', 'node_modules', 'tests', 'build', 'build-dir', '.git', 'languages', 'dist' );
		$salida = array();

		$iterador = new \RecursiveIteratorIterator(
			new \RecursiveCallbackFilterIterator(
				new \RecursiveDirectoryIterator( $raiz, \FilesystemIterator::SKIP_DOTS ),
				static function ( \SplFileInfo $actual ) use ( $omitir ): bool {
					return ! in_array( $actual->getFilename(), $omitir, true );
				}
			)
		);

		foreach ( $iterador as $f ) {
			if ( $f instanceof \SplFileInfo && $f->isFile() && 'php' === strtolower( $f->getExtension() ) ) {
				$salida[] = ltrim( str_replace( $raiz, '', $f->getPathname() ), '/' );
			}
		}
		sort( $salida );

		return $salida;
	}

	/**
	 * Código sin comentarios, con el tokenizador de PHP: un `// fputcsv(...)` no es una llamada.
	 *
	 * @param string $codigo Fuente PHP.
	 * @return string Fuente sin comentarios.
	 */
	private function sinComentarios( string $codigo ): string {
		$limpio = '';
		foreach ( token_get_all( $codigo ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$limpio .= is_array( $token ) ? $token[1] : $token;
		}

		return $limpio;
	}

	/**
	 * Ficheros del repositorio que contienen el patrón.
	 *
	 * @param string $patron Expresión regular.
	 * @return array<int, string>
	 */
	private function quienes( string $patron ): array {
		$quienes = array();
		foreach ( $this->ficherosPhp() as $relativo ) {
			$codigo = $this->sinComentarios( (string) file_get_contents( $this->raiz() . '/' . $relativo ) );
			if ( preg_match( $patron, $codigo ) ) {
				$quienes[] = $relativo;
			}
		}

		return $quienes;
	}

	/** Nadie abre el flujo de salida para CSV en este plugin. */
	public function test_nadie_abre_php_output(): void {
		$quienes = $this->quienes( '/php:\/\/output/' );

		$this->assertSame(
			array(),
			$quienes,
			'La ceremonia CSV vive en \Convoca\Core\Utils::stream_csv(). Abren php://output: ' . implode( ', ', $quienes )
		);
	}

	/** Nadie escribe filas por su cuenta: el escapado por celda es del ayudante. */
	public function test_nadie_llama_a_fputcsv(): void {
		$quienes = $this->quienes( '/[^_a-zA-Z]fputcsv\s*\(/' );

		$this->assertSame(
			array(),
			$quienes,
			'Escribir filas a mano se salta la protección contra fórmulas. Lo hacen: ' . implode( ', ', $quienes )
		);
	}

	/**
	 * Los exportadores declarados usan el ayudante compartido (si alguien borra el volcado, esto lo
	 * dice: un refactor a medias no pasa por aquí).
	 */
	public function test_los_exportadores_declarados_usan_el_ayudante(): void {
		foreach ( self::EXPORTADORES as $fichero => $motivo ) {
			$this->assertFileExists( $this->raiz() . '/' . $fichero, $motivo );
			$this->assertStringContainsString(
				'Utils::stream_csv(',
				(string) file_get_contents( $this->raiz() . '/' . $fichero ),
				$fichero . ' tiene que volcar por Utils::stream_csv() (' . $motivo . ')'
			);
		}
	}
}
