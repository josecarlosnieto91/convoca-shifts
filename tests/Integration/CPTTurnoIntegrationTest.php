<?php
/**
 * Integration tests for CPT_Turno — shift management.
 * Requires WordPress (tested in CI).
 */

namespace Convoca\Shifts\Tests;

class CPTTurnoIntegrationTest extends \WP_UnitTestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(function_exists('Convoca\\Shifts\\convoca_shifts_register_rest_routes'));
    }

    public function test_cpt_turno_registered(): void
    {
        $this->assertTrue(post_type_exists('turno'));
    }

    public function test_rest_routes_registered(): void
    {
        $routes = rest_get_server()->get_routes();
        $this->assertArrayHasKey('/convoca-shifts/v1', $routes);
    }

    public function test_admin_approval_function_exists(): void
    {
        // admin-approval.php defines procedural hooks
        $this->assertTrue(function_exists('Convoca\\Shifts\\convoca_shifts_admin_approval_page'));
    }
}
