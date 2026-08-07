<?php
/**
 * Real behavioral tests for Convoca Shifts — CPT Turno sync logic and structure.
 * Tests convoca_shifts_sync_turno_on_save title generation and CPT registration.
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

class CPTTurnoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Convoca\Core\Logger::clear();
        \Convoca\Core\Utils::clear_fired();
        $GLOBALS['_wp_stores']['post_meta'] = [];
        $GLOBALS['_wp_stores']['transients'] = [];
        $GLOBALS['_wp_stores']['options'] = [];
        $GLOBALS['_wp_stores']['user_meta'] = [];
        $GLOBALS['_wp_stores']['test_posts'] = [];

        $path = dirname(__DIR__, 2) . '/includes/cpt-turno.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }

    /**
     * Helper to invoke the private/global sync function.
     */
    private function syncTurno(\stdClass $post): void
    {
        \Convoca\Shifts\convoca_shifts_sync_turno_on_save($post->ID, $post, false);
    }

    /**
     * Helper to create a test post with custom properties.
     */
    private function makePost(int $id, array $props = []): \stdClass
    {
        $defaults = [
            'ID'          => $id,
            'post_title'  => 'Original Title',
            'post_type'   => 'centro_turno',
            'post_status' => 'publish',
            'post_date'   => '2026-06-15 10:00:00',
        ];
        $post = (object) array_merge($defaults, $props);

        // Store in test_posts so get_post() returns it
        $GLOBALS['_wp_stores']['test_posts'][$id] = $post;
        return $post;
    }

    // ── Title: cerrado ───────────────────────────────

    public function test_sync_cerrado_title(): void
    {
        $post = $this->makePost(1, ['post_status' => 'publish']);
        update_post_meta(1, '_estado', 'cerrado');

        $this->syncTurno($post);

        $data = get_post_meta(1, '_wp_update_data', true);
        $this->assertIsArray($data, 'Should have called wp_update_post');
        $this->assertStringContainsString('Centro Cerrado', $data['post_title'] ?? '');
    }

    public function test_sync_cerrado_emoji(): void
    {
        $post = $this->makePost(2);
        update_post_meta(2, '_estado', 'cerrado');

        $this->syncTurno($post);

        $data = get_post_meta(2, '_wp_update_data', true);
        $this->assertStringContainsString('🔴', $data['post_title'] ?? '');
    }

    // ── Title: abierto_ocupado ────────────────────────

    public function test_sync_abierto_ocupado_with_actividad(): void
    {
        $post = $this->makePost(10);
        update_post_meta(10, '_estado', 'abierto_ocupado');
        // Configurar el término de actividad explícitamente (mock neutral por defecto).
        $GLOBALS['_wp_stores']['post_terms'][10] = [(object) ['name' => 'Actividad Test']];

        $this->syncTurno($post);

        $data = get_post_meta(10, '_wp_update_data', true);
        $this->assertIsArray($data);
        $this->assertStringContainsString('Actividad Test', $data['post_title'] ?? '');
        $this->assertStringContainsString('🔵', $data['post_title'] ?? '');
    }

    public function test_sync_abierto_ocupado_with_monitor(): void
    {
        $post = $this->makePost(11);
        update_post_meta(11, '_estado', 'abierto_ocupado');
        update_post_meta(11, '_monitor', 5);

        $this->syncTurno($post);

        $data = get_post_meta(11, '_wp_update_data', true);
        $this->assertIsArray($data);
        $this->assertStringContainsString('🔵', $data['post_title'] ?? '');
    }

    // ── Title: cubierto (id_responsable > 0) ──────────

    public function test_sync_cubierto_title(): void
    {
        $post = $this->makePost(20);
        update_post_meta(20, '_id_responsable', 3);
        // No _estado set, so it defaults to the cubierto branch

        $this->syncTurno($post);

        $data = get_post_meta(20, '_wp_update_data', true);
        $this->assertIsArray($data);
        $this->assertStringContainsString('Cubierto', $data['post_title'] ?? '');
        $this->assertStringContainsString('🟢', $data['post_title'] ?? '');
    }

    public function test_sync_cubierto_with_user_name(): void
    {
        $post = $this->makePost(21);
        update_post_meta(21, '_id_responsable', 2);
        // User 2 has first_name = 'First2'

        $this->syncTurno($post);

        $data = get_post_meta(21, '_wp_update_data', true);
        $this->assertIsArray($data);
        $this->assertStringContainsString('First2', $data['post_title'] ?? '');
    }

    // ── Title: pendiente ──────────────────────────────

    public function test_sync_pendiente_title(): void
    {
        $post = $this->makePost(30);
        // No _estado, no _id_responsable

        $this->syncTurno($post);

        $data = get_post_meta(30, '_wp_update_data', true);
        $this->assertIsArray($data);
        $this->assertStringContainsString('Pendiente', $data['post_title'] ?? '');
        $this->assertStringContainsString('🟡', $data['post_title'] ?? '');
    }

    // ── Status: future → publish ─────────────────────

    public function test_sync_changes_future_to_publish(): void
    {
        $post = $this->makePost(40, ['post_status' => 'future']);
        update_post_meta(40, '_id_responsable', 1);

        $this->syncTurno($post);

        $data = get_post_meta(40, '_wp_update_data', true);
        $this->assertIsArray($data);
        $this->assertEquals('publish', $data['post_status'] ?? '');
    }

    // ── No title change needed ────────────────────────

    public function test_sync_no_change_when_title_same(): void
    {
        // Create post with title that matches what sync would generate
        $post = $this->makePost(50, ['post_title' => '🟡 Pendiente']);
        // No estado, no responsable, so it stays Pendiente

        $this->syncTurno($post);

        // Title is already '🟡 Pendiente', sync should not update
        $data = get_post_meta(50, '_wp_update_data', true);
        $this->assertEmpty($data, 'Should not update if title matches');
    }

    // ── Function existence ────────────────────────────

    public function test_sync_function_exists(): void
    {
        $this->assertTrue(
            function_exists('Convoca\\Shifts\\convoca_shifts_sync_turno_on_save')
        );
    }

    public function test_cpt_registration_function_exists(): void
    {
        $this->assertTrue(
            function_exists('Convoca\\Shifts\\convoca_shifts_register_cpt_centro_turno')
        );
    }

    public function test_export_csv_function_exists(): void
    {
        $this->assertTrue(
            function_exists('Convoca\\Shifts\\convoca_shifts_exportar_csv_turnos')
        );
    }

    public function test_metabox_callback_exists(): void
    {
        $this->assertTrue(
            function_exists('Convoca\\Shifts\\convoca_shifts_turno_opciones_html')
        );
    }

    public function test_quick_edit_function_exists(): void
    {
        $this->assertTrue(
            function_exists('Convoca\\Shifts\\convoca_shifts_display_quick_edit_turno')
        );
    }

    public function test_admin_action_handler_exists(): void
    {
        $this->assertTrue(
            function_exists('Convoca\\Shifts\\convoca_shifts_handle_admin_attendance_action')
        );
    }

    public function test_rest_api_function_exists(): void
    {
        // Load rest-api.php separately
        $restPath = dirname(__DIR__, 2) . '/includes/rest-api.php';
        if (file_exists($restPath)) {
            require_once $restPath;
        }
        $this->assertTrue(
            function_exists('Convoca\\Shifts\\convoca_shifts_register_rest_routes')
        );
    }
}
