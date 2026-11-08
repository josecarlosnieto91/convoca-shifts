<?php
/**
 * El cron de turnos se asegura en cada carga, no solo en la activación (issue #33).
 *
 * Antes, `convoca_shifts_schedule_cron()` solo se llamaba desde el enganche de activación: un sitio que
 * ya estaba activo —o al que se le vacía la tabla de cron— se quedaba sin recordatorios ni limpieza, y
 * nadie se enteraba. Estas pruebas fijan el contrato: `init` lo llama y la función no duplica eventos.
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

class CronIdempotenteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['_sh_cron']              = array();
        $GLOBALS['_sh_cron_recurrencias'] = array();
        // No se vacía `_sh_hooks_registrados`: cron.php pudo cargarse en una prueba anterior del mismo
        // proceso y `require_once` no volvería a registrar los enganches.
        require_once dirname(__DIR__, 2) . '/includes/cron.php';
    }

    public function test_init_asegura_el_cron(): void
    {
        $enganches = $GLOBALS['_sh_hooks_registrados']['init'] ?? array();

        $this->assertContains(
            'Convoca\\Shifts\\convoca_shifts_schedule_cron',
            $enganches,
            'El cron tiene que asegurarse en init: la activación no basta en un sitio ya activo.'
        );
    }

    public function test_sin_eventos_los_programa_con_su_recurrencia(): void
    {
        \Convoca\Shifts\convoca_shifts_schedule_cron();

        $this->assertArrayHasKey( 'convoca_shifts_hourly_event', $GLOBALS['_sh_cron'] );
        $this->assertArrayHasKey( 'convoca_shifts_daily_event', $GLOBALS['_sh_cron'] );
        $this->assertSame( 'hourly', $GLOBALS['_sh_cron_recurrencias']['convoca_shifts_hourly_event'] );
        $this->assertSame( 'daily', $GLOBALS['_sh_cron_recurrencias']['convoca_shifts_daily_event'] );
    }

    public function test_llamarla_dos_veces_no_duplica_ni_mueve_la_fecha(): void
    {
        \Convoca\Shifts\convoca_shifts_schedule_cron();
        $primera = $GLOBALS['_sh_cron'];

        \Convoca\Shifts\convoca_shifts_schedule_cron();

        $this->assertSame( $primera, $GLOBALS['_sh_cron'], 'Un evento ya programado no se toca.' );
        $this->assertCount( 2, $GLOBALS['_sh_cron'] );
    }
}
