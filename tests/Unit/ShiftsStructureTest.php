<?php
/**
 * Unit tests for Convoca Shifts — structural tests (no WP needed).
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

class ShiftsStructureTest extends TestCase
{
    private string $includesDir;

    protected function setUp(): void
    {
        $this->includesDir = dirname(__DIR__, 2) . '/includes';
    }

    private function loadFile(string $file): void
    {
        $path = "{$this->includesDir}/$file";
        if (file_exists($path)) {
            require_once $path;
        }
    }

    public function test_cpt_turno_functions_exist(): void
    {
        $this->loadFile('cpt-turno.php');
        $this->assertTrue(function_exists('Convoca\Shifts\convoca_shifts_register_cpt_centro_turno'), 'CPT registration should be defined');
        $this->assertTrue(function_exists('Convoca\Shifts\convoca_shifts_sync_turno_on_save'), 'Sync on save should be defined');
    }

    public function test_admin_turno_editor_class_loads(): void
    {
        $this->loadFile('class-admin-turno-editor.php');
        $this->assertTrue(class_exists('Convoca\Shifts\Admin_Turno_Editor'));
    }

    public function test_admin_turnos_list_class_loads(): void
    {
        $this->loadFile('class-admin-turnos-list.php');
        $this->assertTrue(class_exists('Convoca\Shifts\Admin_Turnos_List'));
    }

    public function test_admin_turnos_list_page_class_loads(): void
    {
        $this->loadFile('class-admin-turnos-list-page.php');
        $this->assertTrue(class_exists('Convoca\Shifts\Admin_Turnos_List_Page'));
    }

    public function test_hour_sync_class_loads(): void
    {
        $this->loadFile('class-hour-sync.php');
        $this->assertTrue(class_exists('Convoca\Shifts\Hour_Sync'));
    }

    public function test_pdf_certificado_class_loads(): void
    {
        $this->loadFile('class-pdf-certificado.php');
        $this->assertTrue(class_exists('Convoca\Shifts\PDF_Certificado'));
    }

    public function test_upgrade_manager_class_loads(): void
    {
        $this->loadFile('Convoca_Shifts_Upgrade_Manager.php');
        $this->assertTrue(class_exists('Convoca\Shifts\Convoca_Shifts_Upgrade_Manager'));
    }

    public function test_rest_api_functions_exist(): void
    {
        $this->loadFile('rest-api.php');
        // REST API should be registered
        $this->assertTrue(function_exists('Convoca\Shifts\convoca_shifts_register_rest_routes'));
    }
}
