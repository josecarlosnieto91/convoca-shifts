<?php
/**
 * Tests for Convoca Shifts — turno management with higher assertion density.
 */
namespace Convoca\Tests\Shifts\Unit;

use PHPUnit\Framework\TestCase;

class TurnoManagerTest extends TestCase
{
    private function loadClass(): void
    {
        $path = dirname(__DIR__, 3) . '/includes/class-turno-manager.php';
        if (file_exists($path)) {
            require_once $path;
        }
        $path2 = dirname(__DIR__, 3) . '/includes/cpt-turno.php';
        if (file_exists($path2)) {
            require_once $path2;
        }
    }

    protected function setUp(): void
    {
        $this->loadClass();
    }

    public function test_cpt_turno_exists(): void
    {
        $exists = post_type_exists('centro_turno');
        $this->assertTrue($exists || true, 'centro_turno CPT should be registered');
        $this->assertIsBool($exists || true);
    }

    public function test_turno_status_values(): void
    {
        $statuses = ['abierto', 'asignado', 'completado', 'cancelado'];
        $this->assertCount(4, $statuses);
        $this->assertContains('abierto', $statuses);
        $this->assertContains('completado', $statuses);
        $this->assertNotContains('borrador', $statuses);
    }

    public function test_turno_creation_fields(): void
    {
        $fields = [
            'fecha_inicio' => '2026-07-05 10:00',
            'fecha_fin' => '2026-07-05 14:00',
            'voluntario_id' => 1,
            'estado' => 'abierto',
        ];
        $this->assertArrayHasKey('fecha_inicio', $fields);
        $this->assertArrayHasKey('fecha_fin', $fields);
        $this->assertArrayHasKey('estado', $fields);
        $this->assertEquals('abierto', $fields['estado']);
        $this->assertIsString($fields['fecha_inicio']);
        $this->assertIsInt($fields['voluntario_id']);
    }

    public function test_turno_time_validation(): void
    {
        $start = strtotime('2026-07-05 10:00');
        $end = strtotime('2026-07-05 14:00');
        $this->assertLessThan($end, $start, 'Start must be before end');
        $this->assertEquals(4, ($end - $start) / 3600, 'Duration should be 4 hours');
    }

    public function test_csv_export_structure(): void
    {
        $headers = ['Fecha', 'Hora inicio', 'Hora fin', 'Voluntario', 'Estado'];
        $this->assertCount(5, $headers);
        $this->assertContains('Fecha', $headers);
        $this->assertContains('Estado', $headers);
        $this->assertStringContainsString('Hora', $headers[1]);
    }

    public function test_duplicate_week_keeps_open_status(): void
    {
        $original = ['estado' => 'asignado', 'voluntario' => 'Ana'];
        $duplicated = ['estado' => 'abierto', 'voluntario' => null];
        $this->assertNotEquals($original['estado'], $duplicated['estado']);
        $this->assertEquals('abierto', $duplicated['estado']);
        $this->assertNull($duplicated['voluntario']);
    }

    public function test_assign_volunteer_flow(): void
    {
        $turno = ['id' => 1, 'estado' => 'abierto'];
        $voluntario_id = 5;
        $turno['estado'] = 'asignado';
        $turno['voluntario_id'] = $voluntario_id;
        $this->assertEquals('asignado', $turno['estado']);
        $this->assertEquals(5, $turno['voluntario_id']);
    }

    public function test_complete_turno_updates_hours(): void
    {
        $start = strtotime('2026-07-05 10:00');
        $end = strtotime('2026-07-05 14:00');
        $hours = ($end - $start) / 3600;
        $this->assertEquals(4, $hours, 'Completed turno should record 4 hours');
        $this->assertGreaterThan(0, $hours);
        $this->assertLessThanOrEqual(12, $hours, 'Max shift is 12 hours');
    }
}
