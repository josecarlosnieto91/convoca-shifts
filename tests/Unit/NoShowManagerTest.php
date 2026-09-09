<?php
/**
 * Tests for Convoca Shifts — No_Show_Manager (D11 penalización suave + D12 ausencias).
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;
use Convoca\Shifts\No_Show_Manager;

class NoShowManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Convoca\Core\Logger::clear();
        \Convoca\Core\Utils::clear_fired();
        $GLOBALS['_wp_stores']['post_meta']   = [];
        $GLOBALS['_wp_stores']['transients']  = [];
        $GLOBALS['_wp_stores']['options']     = [];
        $GLOBALS['_wp_stores']['user_meta']   = [];
        $GLOBALS['_wp_stores']['test_posts']  = [];
        $GLOBALS['_wp_stores']['emails']      = [];

        $path = dirname(__DIR__, 2) . '/includes/No_Show_Manager.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }

    // ── D11: registro idempotente de faltas ──────────────────

    public function test_record_no_show_is_idempotent_per_turno(): void
    {
        $this->assertTrue(No_Show_Manager::record_no_show(1, 10));
        $this->assertFalse(No_Show_Manager::record_no_show(1, 10), 'Same turno should not double-record');
        $this->assertTrue(No_Show_Manager::record_no_show(1, 11), 'Different turno should record');
    }

    public function test_record_no_show_ignores_invalid_ids(): void
    {
        $this->assertFalse(No_Show_Manager::record_no_show(0, 10));
        $this->assertFalse(No_Show_Manager::record_no_show(1, 0));
    }

    // ── D11: ventana de 90 días ──────────────────────────────

    public function test_count_in_window_filters_by_days(): void
    {
        No_Show_Manager::record_no_show(1, 10, '2026-01-01');
        No_Show_Manager::record_no_show(1, 11, '2026-01-02');
        No_Show_Manager::record_no_show(1, 12, '2025-01-01'); // fuera de ventana

        $count = No_Show_Manager::count_in_window(1, 90, strtotime('2026-01-15'));
        $this->assertSame(2, $count);
    }

    public function test_count_in_window_empty(): void
    {
        $this->assertSame(0, No_Show_Manager::count_in_window(99, 90));
    }

    // ── D11: aviso al socio en el umbral ─────────────────────

    public function test_no_show_triggers_socio_notice_at_threshold(): void
    {
        update_option('convoca_shifts_aviso_socio_en', 2);
        update_option('convoca_shifts_aviso_admin_cada', 3);
        update_option('convoca_shifts_ventana_dias', 90);

        $sent1 = No_Show_Manager::handle_attendance_change(10, 1, 'no_asistio');
        $this->assertEmpty($sent1, 'First falta should not notify');

        $sent2 = No_Show_Manager::handle_attendance_change(11, 1, 'no_asistio');
        $this->assertSame(['socio'], $sent2);
        $this->assertCount(1, $GLOBALS['_wp_stores']['emails']);
        $this->assertSame('user1@example.com', $GLOBALS['_wp_stores']['emails'][0]['to']);
    }

    // ── D11: aviso al admin cada N faltas ────────────────────

    public function test_admin_notice_at_third_and_sixth(): void
    {
        update_option('admin_email', 'admin@example.com');

        for ($i = 1; $i <= 6; $i++) {
            No_Show_Manager::handle_attendance_change(100 + $i, 1, 'no_asistio');
        }

        $admin_emails = array_values(array_filter(
            $GLOBALS['_wp_stores']['emails'],
            static fn($e) => $e['to'] === 'admin@example.com'
        ));

        $this->assertCount(2, $admin_emails, 'Admin notified at 3rd and 6th falta only');
    }

    public function test_admin_notice_skipped_when_no_admin_email(): void
    {
        // Sin admin_email configurado: no debe emitir aviso de admin.
        for ($i = 1; $i <= 3; $i++) {
            No_Show_Manager::handle_attendance_change(200 + $i, 1, 'no_asistio');
        }

        $admin_emails = array_values(array_filter(
            $GLOBALS['_wp_stores']['emails'],
            static fn($e) => $e['to'] === ''
        ));
        $this->assertEmpty($admin_emails);
    }

    // ── D12: ausencias justificadas NO cuentan como falta ────

    public function test_justified_states_do_not_count_as_falta(): void
    {
        $sent1 = No_Show_Manager::handle_attendance_change(10, 1, 'justificada');
        $this->assertEmpty($sent1);

        $sent2 = No_Show_Manager::handle_attendance_change(11, 1, 'cancelada_aviso');
        $this->assertEmpty($sent2);

        $this->assertSame(0, No_Show_Manager::count_in_window(1, 90));
        $this->assertEmpty($GLOBALS['_wp_stores']['emails']);
    }

    public function test_is_justified(): void
    {
        $this->assertTrue(No_Show_Manager::is_justified('justificada'));
        $this->assertTrue(No_Show_Manager::is_justified('cancelada_aviso'));
        $this->assertFalse(No_Show_Manager::is_justified('no_asistio'));
        $this->assertFalse(No_Show_Manager::is_justified('realizado'));
        $this->assertFalse(No_Show_Manager::is_justified('pendiente'));
    }

    public function test_flipping_to_justified_clears_falta(): void
    {
        No_Show_Manager::handle_attendance_change(10, 1, 'no_asistio');
        $this->assertSame(1, No_Show_Manager::count_in_window(1, 90));

        No_Show_Manager::handle_attendance_change(10, 1, 'justificada');
        $this->assertSame(0, No_Show_Manager::count_in_window(1, 90));
    }

    // ── D11: settings defaults ───────────────────────────────

    public function test_settings_defaults(): void
    {
        $settings = No_Show_Manager::get_settings();
        $this->assertSame(2, $settings['aviso_socio_en']);
        $this->assertSame(3, $settings['aviso_admin_cada']);
        $this->assertSame(90, $settings['ventana_dias']);
    }

    public function test_settings_custom_values(): void
    {
        update_option('convoca_shifts_aviso_socio_en', 4);
        update_option('convoca_shifts_aviso_admin_cada', 5);
        update_option('convoca_shifts_ventana_dias', 30);

        $settings = No_Show_Manager::get_settings();
        $this->assertSame(4, $settings['aviso_socio_en']);
        $this->assertSame(5, $settings['aviso_admin_cada']);
        $this->assertSame(30, $settings['ventana_dias']);
    }

    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists('Convoca\\Shifts\\No_Show_Manager'));
    }
}
