<?php
/**
 * El volcado del CSV a turnos de verdad.
 *
 * `ImportadorTest` cubre la lectura (separador, BOM, fechas). Aquí va la otra mitad, que es la que toca la
 * base de datos y la que no avisa cuando se equivoca: **no importar dos veces el mismo turno**, resolver la
 * actividad y la persona por nombre / correo / número, crear el turno aunque falte la persona (avisando), y
 * no pintar la página a quien no puede importar.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/importador.php';

/**
 * Importación efectiva de las filas y pantalla del importador.
 */
final class ImportadorVolcadoTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();

		foreach ( array( 'horario', 'class-personas-asignables', 'cpt-turno' ) as $fichero ) {
			require_once dirname( __DIR__, 2 ) . '/includes/' . $fichero . '.php';
		}
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$this->limpiar();
		unset( $_GET['page'], $_GET['plantilla'] );
	}

	/**
	 * Deja el arnés como estaba.
	 */
	private function limpiar(): void {
		foreach ( array( '_wp_stores', '_test_caps', '_sh_db', '_sh_registro', '_sh_insert_error', '_sh_terminos_existentes', '_sh_admin_paginas' ) as $clave ) {
			unset( $GLOBALS[ $clave ] );
		}

		$GLOBALS['_wp_stores'] = array();
		$GLOBALS['_test_caps'] = array();
	}

	/**
	 * Las horas: se normalizan a HH:MM y lo imposible se rechaza (hora 25 y minuto 60).
	 */
	public function test_las_horas(): void {
		$this->assertSame( '07:05', \Convoca\Shifts\Importador::hora( '7:05' ) );
		$this->assertSame( '17:00', \Convoca\Shifts\Importador::hora( '17:00:30' ), 'los segundos se tiran' );
		$this->assertSame( '', \Convoca\Shifts\Importador::hora( '25:00' ), 'no hay hora 25' );
		$this->assertSame( '', \Convoca\Shifts\Importador::hora( '17:60' ), 'ni minuto 60' );
	}

	/**
	 * Importar crea el turno con su estado, su apoyo y su registro de origen.
	 */
	public function test_importar_crea_el_turno(): void {
		$r = \Convoca\Shifts\Importador::importar(
			array(
				array(
					'fecha'       => '2026-11-05',
					'hora_inicio' => '17:00',
					'hora_fin'    => '21:00',
					'estado'      => 'ocupado',
					'apoyo'       => 'sí',
				),
			)
		);

		$this->assertSame( 1, $r['creados'] );
		$this->assertSame( 0, $r['repetidos'] );
		$this->assertSame( array(), $r['errores'] );

		$meta = $GLOBALS['_wp_stores']['post_meta'][ $this->ultimo_id() ];
		$this->assertSame( 'abierto_ocupado', $meta['_estado'], '«ocupado» se traduce al estado interno' );
		$this->assertSame( '2026-11-05 17:00:00', $meta['_fecha_inicio'] );
		$this->assertSame( '21:00', $meta['_hora_fin'] );
		$this->assertSame( 1, (int) $meta['_necesita_apoyo'] );
		$this->assertSame( 'turno_creado', $GLOBALS['_sh_registro'][0]['action'] );
		$this->assertSame( 'importacion', $GLOBALS['_sh_registro'][0]['data']['origen'] );
	}

	/**
	 * Un estado que no se reconoce cae en «disponible», que es el de por defecto.
	 */
	public function test_un_estado_desconocido(): void {
		\Convoca\Shifts\Importador::importar(
			array(
				array(
					'fecha'       => '2026-11-05',
					'hora_inicio' => '17:00',
					'hora_fin'    => '21:00',
					'estado'      => 'lo que sea',
				),
			)
		);

		$this->assertSame( 'abierto_disponible', $GLOBALS['_wp_stores']['post_meta'][ $this->ultimo_id() ]['_estado'] );
	}

	/**
	 * Un turno que ya existe en ese día y hora **no** se importa dos veces.
	 */
	public function test_no_importa_dos_veces_el_mismo_turno(): void {
		$GLOBALS['_wp_stores']['posts'][3] = (object) array(
			'ID'          => 3,
			'post_type'   => 'centro_turno',
			'post_status' => 'publish',
			'post_title'  => 'Ya está',
		);
		$GLOBALS['_wp_stores']['post_meta'][3] = array( '_fecha_inicio' => '2026-11-05 17:00:00' );

		$r = \Convoca\Shifts\Importador::importar(
			array(
				array(
					'fecha'       => '2026-11-05',
					'hora_inicio' => '17:00',
					'hora_fin'    => '21:00',
				),
			)
		);

		$this->assertSame( 0, $r['creados'] );
		$this->assertSame( 1, $r['repetidos'], 'se cuenta como repetido, no como error' );
		$this->assertSame( array(), $r['errores'] );
	}

	/**
	 * Dos filas idénticas en el mismo fichero: la segunda es repetida, no otro turno.
	 *
	 * Es el caso real de quien pega dos veces la misma línea en la hoja, y solo se ve si el turno recién
	 * creado entra en la consulta de duplicados (el doble de `wp_insert_post` lo registraba solo en las
	 * metas, así que la segunda fila pasaba por nueva).
	 */
	public function test_dos_filas_identicas_en_el_mismo_fichero(): void {
		$fila = array(
			'fecha'       => '2026-11-09',
			'hora_inicio' => '17:00',
			'hora_fin'    => '21:00',
		);

		$r = \Convoca\Shifts\Importador::importar( array( $fila, $fila ) );

		$this->assertSame( 1, $r['creados'], 'un turno, no dos' );
		$this->assertSame( 1, $r['repetidos'] );
		$this->assertSame( array(), $r['errores'] );
	}

	/**
	 * El turno que ya existe cuenta aunque esté en **borrador**: `post_status => 'any'`.
	 */
	public function test_un_borrador_tambien_cuenta_como_existente(): void {
		$GLOBALS['_wp_stores']['posts'][4] = (object) array(
			'ID'          => 4,
			'post_type'   => 'centro_turno',
			'post_status' => 'draft',
			'post_title'  => 'A medias',
		);
		$GLOBALS['_wp_stores']['post_meta'][4] = array( '_fecha_inicio' => '2026-11-05 17:00:00' );

		$r = \Convoca\Shifts\Importador::importar(
			array(
				array(
					'fecha'       => '2026-11-05',
					'hora_inicio' => '17:00',
					'hora_fin'    => '21:00',
				),
			)
		);

		$this->assertSame( 0, $r['creados'] );
		$this->assertSame( 1, $r['repetidos'] );
	}

	/**
	 * Una actividad que no existe se avisa y esa fila no se importa.
	 */
	public function test_actividad_que_no_existe(): void {
		$r = \Convoca\Shifts\Importador::importar(
			array(
				array(
					'fecha'       => '2026-11-05',
					'hora_inicio' => '17:00',
					'hora_fin'    => '21:00',
					'actividad'   => 'Huerto lunar',
				),
			)
		);

		$this->assertSame( 0, $r['creados'] );
		$this->assertStringContainsString( 'Huerto lunar', $r['errores'][0] );
	}

	/**
	 * La actividad se resuelve por nombre y por número.
	 */
	public function test_actividad_por_nombre_o_numero(): void {
		$GLOBALS['_sh_terminos_existentes'] = array(
			1 => array(
				'term_id' => 1,
				'name'    => 'Huerto comunitario',
				'slug'    => 'huerto-comunitario',
			),
		);

		$r = \Convoca\Shifts\Importador::importar(
			array(
				array(
					'fecha'       => '2026-11-05',
					'hora_inicio' => '17:00',
					'hora_fin'    => '21:00',
					'actividad'   => 'Huerto comunitario',
				),
				array(
					'fecha'       => '2026-11-06',
					'hora_inicio' => '17:00',
					'hora_fin'    => '21:00',
					'actividad'   => '1',
				),
			)
		);

		$this->assertSame( 2, $r['creados'] );
		$this->assertSame( array(), $r['errores'] );
	}

	/**
	 * Una persona que no existe se avisa, y el turno se crea igual y sin responsable.
	 */
	public function test_persona_que_no_existe(): void {
		$r = \Convoca\Shifts\Importador::importar(
			array(
				array(
					'fecha'       => '2026-11-05',
					'hora_inicio' => '17:00',
					'hora_fin'    => '21:00',
					'responsable' => 'nadie@example.test',
				),
			)
		);

		$this->assertSame( 1, $r['creados'], 'el turno se crea igual' );
		$this->assertStringContainsString( 'nadie@example.test', $r['errores'][0] );
		$this->assertSame( 0, (int) $GLOBALS['_wp_stores']['post_meta'][ $this->ultimo_id() ]['_id_responsable'] );
	}

	/**
	 * La persona se resuelve por número, por usuario y por correo.
	 */
	public function test_persona_por_numero_usuario_o_correo(): void {
		$GLOBALS['_wp_stores']['usuarios'][7] = (object) array(
			'ID'           => 7,
			'user_login'   => 'ada',
			'user_email'   => 'ada@example.test',
			'display_name' => 'Ada Lovelace',
			'first_name'   => 'Ada',
		);
		$this->aportar_persona( 7 );

		$dia = 4;
		foreach ( array( '7', 'ada', 'ada@example.test' ) as $leido ) {
			// Un día distinto por vuelta: el turno creado ya se ve en la consulta de duplicados.
			$r = \Convoca\Shifts\Importador::importar(
				array(
					array(
						'fecha'       => '2026-11-' . sprintf( '%02d', ++$dia ),
						'hora_inicio' => '17:00',
						'hora_fin'    => '21:00',
						'responsable' => $leido,
					),
				)
			);

			$this->assertSame( 1, $r['creados'], 'leído: ' . $leido );
			$this->assertSame(
				7,
				(int) $GLOBALS['_wp_stores']['post_meta'][ $this->ultimo_id() ]['_id_responsable'],
				'leído: ' . $leido
			);
		}
	}

	/**
	 * Si el alta falla, se avisa con la fecha y no se cuenta como creado.
	 */
	public function test_si_el_alta_falla(): void {
		$GLOBALS['_sh_insert_error'] = new \WP_Error( 'boom', 'no se pudo' );

		$r = \Convoca\Shifts\Importador::importar(
			array(
				array(
					'fecha'       => '2026-11-05',
					'hora_inicio' => '17:00',
					'hora_fin'    => '21:00',
				),
			)
		);

		$this->assertSame( 0, $r['creados'] );
		$this->assertStringContainsString( '2026-11-05', $r['errores'][0] );
		$this->assertNotContains( 'turno_creado', array_column( $GLOBALS['_sh_registro'] ?? array(), 'action' ) );
	}

	/**
	 * Los enganches se registran de verdad: menú, descarga y formulario.
	 */
	public function test_los_enganches(): void {
		\Convoca\Shifts\Importador::init();
		\Convoca\Shifts\Importador::menu();

		$acciones = array_keys( $GLOBALS['_wp_stores']['acciones'] ?? array() );
		$this->assertContains( 'admin_menu', $acciones );
		$this->assertContains( 'admin_init', $acciones );
		$this->assertContains( 'admin_post_convoca_shifts_importar', $acciones );

		$submenus = $GLOBALS['_sh_admin_paginas'] ?? array();
		$this->assertNotEmpty( $submenus, 'el importador cuelga del menú de turnos' );
		$this->assertSame( 'edit.php?post_type=centro_turno', $submenus[0]['padre'] );
		$this->assertSame( 'convoca_shifts_manage_turnos', $submenus[0]['cap'] );
	}

	/**
	 * La pantalla no se pinta para quien no puede importar.
	 */
	public function test_la_pantalla_sin_permiso(): void {
		$GLOBALS['_test_caps']['convoca_shifts_manage_turnos'] = false;

		$this->expectException( \RuntimeException::class );

		ob_start();
		try {
			\Convoca\Shifts\Importador::pagina();
		} finally {
			ob_end_clean();
		}
	}

	/**
	 * Con permiso, la pantalla trae el formulario y el enlace de la plantilla.
	 */
	public function test_la_pantalla(): void {
		$GLOBALS['_test_caps']['convoca_shifts_manage_turnos'] = true;

		ob_start();
		\Convoca\Shifts\Importador::pagina();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'convoca_shifts_importar', $html );
		$this->assertStringContainsString( 'plantilla=1', $html, 'el enlace para descargar la plantilla' );
		$this->assertStringContainsString( 'type="file"', $html, 'y el selector de fichero' );
	}

	/**
	 * La descarga de la plantilla se atiende solo en su página, y solo con permiso.
	 */
	public function test_la_descarga_de_la_plantilla(): void {
		$_GET = array(
			'page'      => 'otra_pagina',
			'plantilla' => '1',
		);

		ob_start();
		\Convoca\Shifts\Importador::quizas_descargar_plantilla();
		$this->assertSame( '', (string) ob_get_clean(), 'en otra página no imprime nada' );

		$_GET = array(
			'page'      => 'convoca_shifts_importar',
			'plantilla' => '1',
		);
		$GLOBALS['_test_caps']['convoca_shifts_manage_turnos'] = false;

		$this->expectException( \RuntimeException::class );

		ob_start();
		try {
			\Convoca\Shifts\Importador::quizas_descargar_plantilla();
		} finally {
			ob_end_clean();
		}
	}

	/**
	 * El id del último turno creado.
	 *
	 * @return int
	 */
	private function ultimo_id(): int {
		$ids = array();
		foreach ( $GLOBALS['_wp_stores']['post_meta'] ?? array() as $id => $metas ) {
			if ( isset( $metas['_wp_insert_data'] ) ) {
				$ids[] = (int) $id;
			}
		}

		return (int) end( $ids );
	}

	/**
	 * Aporta una persona por el filtro del contrato.
	 *
	 * @param int $id Persona.
	 */
	private function aportar_persona( int $id ): void {
		$GLOBALS['__shifts_filtros'][ \Convoca\Shifts\Personas_Asignables::FILTRO ] = static function () use ( $id ) {
			return array( (object) array( 'ID' => $id ) );
		};
		\Convoca\Shifts\Personas_Asignables::limpiar_cache();
	}
}
