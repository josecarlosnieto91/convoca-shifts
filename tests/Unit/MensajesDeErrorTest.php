<?php
/**
 * Los errores del turno rápido dicen QUÉ HACER, no «Error desconocido».
 *
 * Defecto del gauntlet (pasada 8): cuando el guardado no devolvía detalle, el aviso al usuario era
 * «Error desconocido» a secas, sin decir qué ha pasado ni qué hacer. Ahora nombra lo que se puede
 * intentar.
 *
 * El código propio se lee SIN comentarios (tokenizador): el comentario que explica el defecto nombra
 * el literal prohibido y un test que lee fuente sin quitarlos falla contra él.
 *
 * Prueba en negativo: con el literal puesto, falla y nombra el fichero.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

class MensajesDeErrorTest extends TestCase
{
	/** Textos que no pueden volver al código de este repo. */
	private const PROHIBIDOS = array(
		'Error desconocido',
	);

	private function raiz(): string
	{
		return dirname( __DIR__, 2 );
	}

	/**
	 * @return array<string, string> ruta relativa => código sin comentarios.
	 */
	private function ficheros(): array
	{
		$raiz   = $this->raiz();
		$salida = array();

		foreach ( array( 'includes', 'admin', 'assets/js' ) as $dir ) {
			$ruta = $raiz . '/' . $dir;
			if ( ! is_dir( $ruta ) ) {
				continue;
			}
			foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $ruta, \FilesystemIterator::SKIP_DOTS ) ) as $f ) {
				if ( ! $f->isFile() ) {
					continue;
				}
				if ( ! in_array( $f->getExtension(), array( 'php', 'js' ), true ) ) {
					continue;
				}

				$src = (string) file_get_contents( $f->getPathname() );
				if ( 'php' === $f->getExtension() ) {
					$limpio = '';
					foreach ( token_get_all( $src ) as $token ) {
						if ( is_array( $token ) ) {
							if ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
								continue;
							}
							$limpio .= $token[1];
						} else {
							$limpio .= $token;
						}
					}
					$src = $limpio;
				} else {
					$src = (string) preg_replace( '#/\*.*?\*/#s', '', $src );
					$src = (string) preg_replace( '#(^|\s)//[^\n]*#', '$1', $src );
				}

				$rel            = ltrim( str_replace( $raiz, '', $f->getPathname() ), '/' );
				$salida[ $rel ] = $src;
			}
		}

		$this->assertNotEmpty( $salida, 'No se ha encontrado código propio que revisar.' );

		return $salida;
	}

	public function test_ningun_mensaje_prohibido_en_codigo_propio(): void
	{
		foreach ( $this->ficheros() as $rel => $codigo ) {
			foreach ( self::PROHIBIDOS as $prohibido ) {
				$this->assertStringNotContainsString(
					$prohibido,
					$codigo,
					"El fichero {$rel} sigue con «{$prohibido}»: el aviso tiene que decir qué hacer."
				);
			}
		}
	}

	public function test_el_aviso_del_turno_rapido_explica_que_hacer(): void
	{
		$admin = $this->raiz() . '/includes/admin-turno-rapido.php';
		$this->assertFileExists( $admin );
		$codigo = (string) file_get_contents( $admin );

		$this->assertStringContainsString(
			'Vuelve a intentarlo',
			$codigo,
			'El aviso del turno rápido ha dejado de decir qué hacer.'
		);
	}
}
