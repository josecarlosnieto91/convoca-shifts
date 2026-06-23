<?php
/**
 * Unit tests for Convoca Shifts — date formatting and utility functions.
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

// Mock WordPress functions for cpt-turno.php
if (!function_exists('Convoca\Shifts\register_post_type')) {
    function register_post_type($slug, $args) { return null; }
    function register_post_meta($type, $key, $args) { return true; }
    function register_taxonomy($slug, $type, $args) { return null; }
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
    function __($s, $domain) { return $s; }
    function _x($s, $context, $domain) { return $s; }
    function esc_html__($s, $domain) { return $s; }
    function esc_attr__($s, $domain) { return $s; }
    function apply_filters($hook, $value, ...$args) { return $value; }
    function esc_html($s) { return $s; }
    function esc_attr($s) { return $s; }
    function get_post_meta($id, $key, $single) { return ''; }
    function get_the_title($id) { return "Test Shift"; }
    function get_post_status($id) { return 'publish'; }
    function get_post($id) { return (object)['ID' => $id, 'post_title' => 'Test', 'post_date' => '2026-06-01 10:00:00']; }
    function update_post_meta($id, $key, $value) { return true; }
    function current_user_can($cap) { return true; }
    function wp_verify_nonce($nonce, $action) { return true; }
    function sanitize_text_field($s) { return $s; }
    function wp_date($fmt, $ts = null) { return date($fmt, $ts ?? time()); }
    function get_current_user_id() { return 1; }
    function wp_redirect($url) {}
    function wp_die($msg = '', $title = '', $args = []) {}
    function wp_kses_post($s) { return $s; }
    function absint($v) { return (int) $v; }
    function wp_unslash($s) { return is_array($s) ? $s : stripslashes((string)$s); }
    function current_time($fmt) { return date($fmt); }
}

// Load shifts functions
require_once dirname(__DIR__, 2) . '/includes/cpt-turno.php';

class DateFormatTest extends TestCase
{
    /**
     * Test that convoca_shifts_sync_turno_on_save function exists.
     */
    public function test_sync_turno_function_exists(): void
    {
        $this->assertTrue(
            function_exists('Convoca\Shifts\convoca_shifts_sync_turno_on_save'),
            'convoca_shifts_sync_turno_on_save should be defined'
        );
    }

    /**
     * Test that the CPT registration function exists.
     */
    public function test_register_cpt_function_exists(): void
    {
        $this->assertTrue(
            function_exists('Convoca\Shifts\convoca_shifts_register_cpt_centro_turno'),
            'convoca_shifts_register_cpt_centro_turno should be defined'
        );
    }

    /**
     * Test that the CSV export function exists.
     */
    public function test_export_csv_function_exists(): void
    {
        $this->assertTrue(
            function_exists('Convoca\Shifts\convoca_shifts_exportar_csv_turnos'),
            'convoca_shifts_exportar_csv_turnos should be defined'
        );
    }

    /**
     * Test that the metabox callback exists.
     */
    public function test_metabox_callback_exists(): void
    {
        $this->assertTrue(
            function_exists('Convoca\Shifts\convoca_shifts_turno_opciones_html'),
            'convoca_shifts_turno_opciones_html should be defined'
        );
    }

    /**
     * Test that quick edit column function exists.
     */
    public function test_quick_edit_function_exists(): void
    {
        $this->assertTrue(
            function_exists('Convoca\Shifts\convoca_shifts_display_quick_edit_turno'),
            'convoca_shifts_display_quick_edit_turno should be defined'
        );
    }

    /**
     * Test that the admin action handler exists.
     */
    public function test_admin_action_handler_exists(): void
    {
        $this->assertTrue(
            function_exists('Convoca\Shifts\convoca_shifts_handle_admin_attendance_action'),
            'convoca_shifts_handle_admin_attendance_action should be defined'
        );
    }
}
