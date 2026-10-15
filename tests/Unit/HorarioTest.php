<?php
/**
 * El horario del centro no puede quedarse sin valor.
 *
 * Defecto real (issue #6, visto en la demo): `get_option( 'convoca_shifts_hora_apertura', '09:00' )` NO
 * aplica el valor por defecto cuando la opción existe **vacía** —devuelve ''—. Y guardarla en blanco
 * desde la pantalla de Ajustes es lo normal. Con las dos vacías, el turno rápido no guardaba nada y los
 * turnos nacían sin hora.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use Convoca\Shifts\Horario;
use PHPUnit\Framework\TestCase;

class HorarioTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['_wp_stores']['options'] = [];
        require_once dirname(__DIR__, 2) . '/includes/horario.php';
    }

    public function test_una_opcion_vacia_usa_el_horario_por_defecto(): void
    {
        $GLOBALS['_wp_stores']['options']['convoca_shifts_hora_apertura'] = '';
        $GLOBALS['_wp_stores']['options']['convoca_shifts_hora_cierre']   = '   ';

        $this->assertSame( Horario::APERTURA_POR_DEFECTO, Horario::apertura() );
        $this->assertSame( Horario::CIERRE_POR_DEFECTO, Horario::cierre() );
    }

    public function test_una_opcion_configurada_se_respeta(): void
    {
        $GLOBALS['_wp_stores']['options']['convoca_shifts_hora_apertura'] = '08:30';
        $GLOBALS['_wp_stores']['options']['convoca_shifts_hora_cierre']   = '21:15';

        $this->assertSame( '08:30', Horario::apertura() );
        $this->assertSame( '21:15', Horario::cierre() );
    }
}
