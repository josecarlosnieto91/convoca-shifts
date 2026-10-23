<?php
/**
 * Los cuatro widgets del plugin.
 *
 * Son la cara pública del módulo puesta en una barra lateral. Lo que puede salir mal aquí es de dos
 * clases: que el widget **pinte fuera del envoltorio del tema** (y rompa la maquetación de cualquier
 * plantilla), o que el formulario guarde en el sitio equivocado —un `name` mal compuesto y el título se
 * pierde en cada guardado, sin ningún error a la vista—. Y en «Próximos» hay un número que se recorta: la
 * cantidad de turnos, que sin `absint` acepta un negativo y deja la lista vacía.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/widgets.php';

/**
 * Widgets: pintado, formulario y guardado.
 */
final class WidgetsTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();

		foreach ( array( 'horario', 'class-personas-asignables', 'cpt-turno', 'shortcodes' ) as $fichero ) {
			require_once dirname( __DIR__, 2 ) . '/includes/' . $fichero . '.php';
		}
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$this->limpiar();
	}

	/**
	 * Limpia el caso.
	 */
	private function limpiar(): void {
		foreach ( array( '_wp_stores', '_sh_widgets_registrados', '__shifts_filtros', '_sh_logged', '_sh_usuario_actual' ) as $clave ) {
			unset( $GLOBALS[ $clave ] );
		}

		$GLOBALS['_wp_stores'] = array();
	}

	/**
	 * Los cuatro widgets se registran, y son los cuatro.
	 */
	public function test_se_registran_los_cuatro(): void {
		\Convoca\Shifts\convoca_shifts_register_widgets();

		$registrados = $GLOBALS['_sh_widgets_registrados'] ?? array();
		$this->assertCount( 4, $registrados );
		foreach ( array( 'Calendario', 'Boton_Apuntarse', 'Resumen_Turnos', 'Proximos_Turnos' ) as $cual ) {
			$this->assertContains( 'Convoca\\Shifts\\Convoca_Shifts_' . $cual . '_Widget', $registrados );
		}
	}

	/**
	 * Cada widget se identifica con su `id_base` y se describe en el panel.
	 */
	public function test_los_identificadores(): void {
		$esperados = array(
			'Convoca_Shifts_Calendario_Widget'       => 'convoca_shifts_calendario_widget',
			'Convoca_Shifts_Boton_Apuntarse_Widget'  => 'convoca_shifts_boton_apuntarse_widget',
			'Convoca_Shifts_Resumen_Turnos_Widget'   => 'convoca_shifts_resumen_turnos_widget',
			'Convoca_Shifts_Proximos_Turnos_Widget'  => 'convoca_shifts_proximos_turnos_widget',
		);

		foreach ( $esperados as $clase => $id ) {
			$completa = '\\Convoca\\Shifts\\' . $clase;
			$widget   = new $completa();
			$this->assertSame( $id, $widget->id_base );
			$this->assertNotSame( '', (string) $widget->widget_options['description'], $clase . ': se describe' );
		}
	}

	/**
	 * El widget pinta **dentro** del envoltorio del tema y con su título.
	 */
	public function test_pinta_dentro_del_envoltorio(): void {
		$html = $this->pintar( 'Convoca_Shifts_Resumen_Turnos_Widget', array( 'title' => 'Esta semana' ) );

		$this->assertStringStartsWith( '<section class="widget">', $html, 'abre el envoltorio del tema' );
		$this->assertStringEndsWith( '</section>', $html, 'y lo cierra' );
		$this->assertStringContainsString( '<h2>Esta semana</h2>', $html );
		$this->assertStringContainsString( 'convoca-shifts-resumen-turnos', $html, 'y dentro va el resumen de verdad' );
	}

	/**
	 * Sin título no se pinta la cabecera vacía.
	 */
	public function test_sin_titulo_no_hay_cabecera(): void {
		$html = $this->pintar( 'Convoca_Shifts_Resumen_Turnos_Widget' );

		$this->assertStringNotContainsString( '<h2>', $html );
	}

	/**
	 * El título pasa por el filtro `widget_title` (los temas lo usan para adornarlo).
	 */
	public function test_el_titulo_pasa_por_el_filtro(): void {
		$GLOBALS['__shifts_filtros']['widget_title'] = static function ( $titulo ) {
			return '· ' . $titulo . ' ·';
		};

		$html = $this->pintar( 'Convoca_Shifts_Resumen_Turnos_Widget', array( 'title' => 'Turnos' ) );

		$this->assertStringContainsString( '<h2>· Turnos ·</h2>', $html );
	}

	/**
	 * Los cuatro pintan algo de verdad (no un hueco en blanco).
	 */
	public function test_los_cuatro_pintan_contenido(): void {
		$marcas = array(
			'Convoca_Shifts_Calendario_Widget'      => 'convoca-shifts-public-notice',
			'Convoca_Shifts_Resumen_Turnos_Widget'  => 'convoca-shifts-resumen-turnos',
			'Convoca_Shifts_Proximos_Turnos_Widget' => 'No hay turnos',
		);

		foreach ( $marcas as $clase => $marca ) {
			$html = $this->pintar( $clase );
			$this->assertStringContainsString( $marca, $html, $clase . ': debería pintar su contenido' );
		}
	}

	/**
	 * El botón de apuntarse solo aparece con sesión y permiso; sin ellas, el widget deja el hueco vacío
	 * (no un botón que no funciona).
	 */
	public function test_el_boton_solo_con_sesion(): void {
		$this->assertStringNotContainsString(
			'convoca-shifts-boton-apuntarse-container',
			$this->pintar( 'Convoca_Shifts_Boton_Apuntarse_Widget' ),
			'sin sesión no hay botón'
		);

		$GLOBALS['_sh_logged']                          = true;
		$GLOBALS['_sh_usuario_actual']                  = 7;
		$GLOBALS['_test_caps']['gestionar_mis_turnos']  = true;

		$this->assertStringContainsString(
			'convoca-shifts-boton-apuntarse-container',
			$this->pintar( 'Convoca_Shifts_Boton_Apuntarse_Widget' ),
			'con permiso, sí'
		);
	}

	/**
	 * El formulario guarda donde toca: el `name` lleva el `id_base` y la instancia.
	 */
	public function test_el_formulario_usa_los_nombres_del_widget(): void {
		$widget = new \Convoca\Shifts\Convoca_Shifts_Resumen_Turnos_Widget();

		ob_start();
		$widget->form( array( 'title' => 'Con "comillas"' ) );
		$form = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="widget-convoca_shifts_resumen_turnos_widget[2][title]"', $form );
		$this->assertStringContainsString( 'id="widget-convoca_shifts_resumen_turnos_widget__2__title"', $form );
		$this->assertStringContainsString( 'value="Con &quot;comillas&quot;"', $form, 'el valor va escapado' );
	}

	/**
	 * Y en «Próximos» hay un segundo campo: la cantidad, con su 5 por defecto.
	 */
	public function test_el_formulario_de_los_proximos(): void {
		$widget = new \Convoca\Shifts\Convoca_Shifts_Proximos_Turnos_Widget();

		ob_start();
		$widget->form( array() );
		$form = (string) ob_get_clean();
		$this->assertStringContainsString( '[2][cantidad]', $form );
		$this->assertStringContainsString( 'value="5"', $form, 'cinco por defecto' );

		ob_start();
		$widget->form( array( 'cantidad' => 9 ) );
		$form = (string) ob_get_clean();
		$this->assertStringContainsString( 'value="9"', $form );
	}

	/**
	 * Guardar limpia el título y, sin título, guarda vacío (no `null` ni el viejo).
	 */
	public function test_guardar_el_titulo(): void {
		$widget = new \Convoca\Shifts\Convoca_Shifts_Resumen_Turnos_Widget();

		$this->assertSame( array( 'title' => 'Hola' ), $widget->update( array( 'title' => '  Hola  ' ), array() ) );
		$this->assertSame( array( 'title' => '' ), $widget->update( array(), array( 'title' => 'viejo' ) ) );
	}

	/**
	 * La cantidad de «Próximos»: número positivo o cinco; un negativo o un texto no cuelan.
	 */
	public function test_guardar_la_cantidad(): void {
		$widget = new \Convoca\Shifts\Convoca_Shifts_Proximos_Turnos_Widget();

		$this->assertSame( 9, $widget->update( array( 'cantidad' => '9' ), array() )['cantidad'] );
		$this->assertSame( 5, $widget->update( array(), array( 'cantidad' => 9 ) )['cantidad'], 'sin dato, cinco' );
		$this->assertSame( 3, $widget->update( array( 'cantidad' => '-3' ), array() )['cantidad'], 'el signo se cae' );

		$this->assertSame( 5, ( new \Convoca\Shifts\Convoca_Shifts_Proximos_Turnos_Widget() )->update( array( 'cantidad' => '0' ), array() )['cantidad'], 'un cero es «sin dato»: vuelve el cinco' );
	}

	/**
	 * La cantidad se le pasa a la lista de próximos turnos.
	 */
	public function test_la_cantidad_llega_a_la_lista(): void {
		$manana = gmdate( 'Y-m-d', time() + 86400 );
		for ( $i = 1; $i <= 7; $i++ ) {
			$GLOBALS['_wp_stores']['posts'][ $i ] = (object) array(
				'ID'          => $i,
				'post_type'   => 'centro_turno',
				'post_status' => 'publish',
				'post_title'  => 'Turno ' . $i,
				'post_date'   => $manana . ' 0' . $i . ':00:00',
			);
			$GLOBALS['_wp_stores']['post_meta'][ $i ] = array(
				'_fecha_inicio'   => $manana . ' 0' . $i . ':00:00',
				'_hora_fin'       => '20:00',
				'_id_responsable' => 0,
				'_estado'         => 'abierto_disponible',
			);
		}

		$tres = $this->pintar( 'Convoca_Shifts_Proximos_Turnos_Widget', array( 'cantidad' => 3 ) );
		$uno  = $this->pintar( 'Convoca_Shifts_Proximos_Turnos_Widget', array( 'cantidad' => 1 ) );

		$this->assertSame( 3, substr_count( $tres, '<li' ) );
		$this->assertSame( 1, substr_count( $uno, '<li' ), 'la cantidad manda' );

		// Un cero guardado es «sin dato»: el widget vuelve al cinco. Con siete turnos se distingue
		// (con cero en crudo, la consulta no limita y saldrían los siete).
		$cero = $this->pintar( 'Convoca_Shifts_Proximos_Turnos_Widget', array( 'cantidad' => 0 ) );
		$this->assertSame( 5, substr_count( $cero, '<li' ), 'con cero, los cinco por defecto' );
	}

	/**
	 * Pinta un widget y devuelve su HTML.
	 *
	 * @param string $clase    Clase del widget.
	 * @param array  $instance Ajustes.
	 * @return string
	 */
	private function pintar( string $clase, array $instance = array() ): string {
		$completa = '\\Convoca\\Shifts\\' . $clase;
		$widget   = new $completa();

		ob_start();
		$widget->widget(
			array(
				'before_widget' => '<section class="widget">',
				'after_widget'  => '</section>',
				'before_title'  => '<h2>',
				'after_title'   => '</h2>',
			),
			$instance
		);

		return (string) ob_get_clean();
	}
}
