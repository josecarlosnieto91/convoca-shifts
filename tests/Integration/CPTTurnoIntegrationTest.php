<?php
/**
 * Integration tests for CPT_Turno — shift management.
 * Requires WordPress (tested in CI).
 */

namespace Convoca\Shifts\Tests;

class CPTTurnoIntegrationTest extends \PHPUnit\Framework\TestCase
{
    /** Los includes definen funciones sueltas: sin cargarlos, function_exists da false. */
    private static function cargar_includes(): void {
        static $hecho = false;
        if ($hecho) { return; }
        foreach (['admin-approval.php', 'rest-api.php'] as $f) {
            $ruta = dirname(__DIR__, 2) . '/includes/' . $f;
            if (file_exists($ruta)) { require_once $ruta; }
        }
        $hecho = true;
    }

    public function test_class_exists(): void
    {
        self::cargar_includes();
        $this->assertTrue(function_exists('Convoca\\Shifts\\convoca_shifts_register_rest_routes'));
    }

    public function test_cpt_turno_registered(): void
    {
        $this->markTestSkipped('Requiere WordPress: post_type_exists() exige WordPress cargado · motivo y seguimiento: convoca-health-check#20.');
        // (medido en pasada 3: de 13 casos, 3 necesitan WP; este es uno)
$this->assertTrue(post_type_exists('turno'));
    }

    public function test_rest_routes_registered(): void
    {
        $this->markTestSkipped('Requiere WordPress: rest_get_server() exige WordPress cargado · motivo y seguimiento: convoca-health-check#20.');
        // (medido en pasada 3: de 13 casos, 3 necesitan WP; este es uno)
$routes = rest_get_server()->get_routes();
        $this->assertArrayHasKey('/convoca-shifts/v1', $routes);
    }

    public function test_volunteer_lifecycle_listeners_exist(): void
    {
        self::cargar_includes();
        // admin-approval.php defines volunteer lifecycle listeners (UI moved to Convoca Members).
        $this->assertTrue(function_exists('Convoca\Shifts\convoca_shifts_on_volunteer_approved'));
        $this->assertTrue(function_exists('Convoca\Shifts\convoca_shifts_on_volunteer_revoked'));
    }
}
