<?php
/**
 * Dos versiones que no son la misma: la del plugin y la de la base de datos (issue #15).
 *
 * `CONVOCA_SHIFTS_VERSION` hacía dos trabajos a la vez y con requisitos opuestos:
 *
 *  - **cache-buster de los assets**, que tiene que subir en CADA release (si se queda atrás, el navegador y
 *    la CDN sirven JS/CSS viejos bajo la misma URL);
 *  - **fingerprint de las migraciones** (`Convoca_Shifts_Upgrade_Manager::get_db_version()`), que no tiene
 *    por qué moverse cuando no cambia ninguna migración.
 *
 * Fundidas, el resultado medido fue el peor de los dos: la constante se quedó en **2.5.10** mientras el
 * plugin iba por la **2.5.22**, así que durante doce releases los assets se sirvieron con `?ver=2.5.10`. Y
 * quien la subiera «para arreglar el cache-buster» habría movido el fingerprint de las migraciones sin que
 * ninguna migración hubiera cambiado.
 *
 * Separadas: `CONVOCA_SHIFTS_VERSION` es la del plugin (derivada de la cabecera, como en el resto del
 * ecosistema) y `CONVOCA_SHIFTS_DB_VERSION` es la de la base de datos, con el valor que ya tenía (2.5.10)
 * para no disparar ninguna migración que no tocaba — igual que en gateway, donde ya son dos constantes.
 *
 * Probado en negativo sobre el fichero de HEAD: allí la constante era un literal y `get_db_version()` leía
 * la versión del plugin, así que las aserciones caen.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

/**
 * Coherencia entre las versiones del plugin y de la base de datos.
 */
class VersionYFingerprintTest extends \PHPUnit\Framework\TestCase {

	/**
	 * Fichero principal del plugin.
	 *
	 * @return string
	 */
	private function principal(): string {
		$ruta = dirname( __DIR__, 2 ) . '/convoca-shifts.php';
		$this->assertFileExists( $ruta );

		return (string) file_get_contents( $ruta );
	}

	/**
	 * El gestor de migraciones.
	 *
	 * @return string
	 */
	private function upgrade_manager(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Convoca_Shifts_Upgrade_Manager.php' );
	}

	/**
	 * La versión del plugin se deriva de la cabecera; no es un literal.
	 */
	public function test_la_version_del_plugin_se_deriva_de_la_cabecera(): void {
		$fuente = $this->principal();

		$this->assertSame(
			0,
			preg_match( "/define\(\s*'CONVOCA_SHIFTS_VERSION',\s*'[0-9]/", $fuente ),
			'CONVOCA_SHIFTS_VERSION está clavada con un literal: al subir la versión se queda atrás y los assets ' .
			'se sirven con un ?ver= viejo (pasó: 2.5.10 durante doce releases). Tiene que derivarse de la cabecera.'
		);

		$this->assertMatchesRegularExpression(
			"/get_file_data\(\s*__FILE__.*\n.*CONVOCA_SHIFTS_VERSION/s",
			$fuente,
			'La versión del plugin tiene que salir de la cabecera con get_file_data().'
		);
	}

	/**
	 * El fingerprint de las migraciones es OTRA constante.
	 */
	public function test_la_version_de_la_base_de_datos_es_otra_constante(): void {
		$this->assertStringContainsString(
			"define( 'CONVOCA_SHIFTS_DB_VERSION'",
			$this->principal(),
			'Falta CONVOCA_SHIFTS_DB_VERSION: la versión de la base de datos tiene que estar separada de la del plugin.'
		);

		$this->assertStringContainsString(
			'CONVOCA_SHIFTS_DB_VERSION',
			$this->upgrade_manager(),
			'El gestor de migraciones tiene que leer la versión de la BD, no la del plugin: si no, cada release mueve el fingerprint.'
		);

		$this->assertDoesNotMatchRegularExpression(
			"/get_db_version.*CONVOCA_SHIFTS_VERSION/s",
			$this->upgrade_manager(),
			'get_db_version() sigue leyendo CONVOCA_SHIFTS_VERSION (la del plugin): son dos cosas distintas.'
		);
	}

	/**
	 * Los assets llevan la versión del plugin, que es lo que rompía el cache-buster.
	 */
	public function test_los_assets_van_con_la_version_del_plugin(): void {
		$raiz = dirname( __DIR__, 2 );
		foreach ( array( '/includes/shortcodes.php', '/includes/blocks.php' ) as $fichero ) {
			$fuente = (string) file_get_contents( $raiz . $fichero );
			if ( false !== strpos( $fuente, 'convoca-shifts-style' ) || false !== strpos( $fuente, 'convoca-shifts-calendario' ) ) {
				$this->assertStringContainsString(
					'CONVOCA_SHIFTS_VERSION',
					$fuente,
					"{$fichero} encola assets con la versión del plugin (cache-buster)."
				);
			}
		}
	}

	/**
	 * Cabecera, `Stable tag` y `.pot`, con la misma versión.
	 */
	public function test_las_tres_versiones_coinciden(): void {
		$raiz = dirname( __DIR__, 2 );
		$this->assertSame(
			1,
			preg_match( '/^ \* Version:\s+([0-9][0-9A-Za-z.\-]*)/m', $this->principal(), $m ),
			'La cabecera del plugin no declara Version:.'
		);
		$version = $m[1];

		$this->assertMatchesRegularExpression(
			'/^Stable tag:\s*' . preg_quote( $version, '/' ) . '\s*$/m',
			(string) file_get_contents( $raiz . '/readme.txt' ),
			"El «Stable tag» del readme.txt no coincide con la cabecera ({$version})."
		);

		$this->assertStringContainsString(
			$version,
			(string) file_get_contents( $raiz . '/languages/convoca-shifts.pot' ),
			"El .pot no lleva la versión {$version}."
		);
	}
}
