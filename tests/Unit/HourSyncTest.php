<?php
/**
 * Real behavioral tests for Convoca Shifts — Hour_Sync class.
 * Tests sync_hours_to_volunteer_global decision logic.
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;
use Convoca\Shifts\Hour_Sync;

class HourSyncTest extends TestCase
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

        $path = dirname(__DIR__, 2) . '/includes/class-hour-sync.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }

    // ── Edge cases ──────────────────────────────────────

    public function test_sync_with_invalid_user_does_nothing(): void
    {
        // user_id = 0 should be rejected immediately
        Hour_Sync::sync_hours_to_volunteer_global(1, 0, 'realizado');

        // No error should have been logged (early return)
        $this->assertEmpty(\Convoca\Core\Logger::$logs);
    }

    public function test_sync_with_nonexistent_user_does_nothing(): void
    {
        // user_id = -1 returns false from get_userdata
        Hour_Sync::sync_hours_to_volunteer_global(1, -1, 'realizado');

        $this->assertEmpty(\Convoca\Core\Logger::$logs);
    }

    // ── Class existence ─────────────────────────────────

    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists('Convoca\Shifts\Hour_Sync'));
    }

    public function test_has_sync_method(): void
    {
        $this->assertTrue(method_exists('Convoca\Shifts\Hour_Sync', 'sync_hours_to_volunteer_global'));
    }

    public function test_handles_non_turno_post_type(): void
    {
        // Post type is not centro_turno
        $p = new \stdClass();
        $p->ID = 99;
        $p->post_title = 'Not a shift';
        $p->post_type = 'page';
        $p->post_status = 'publish';
        $p->post_date = '2026-06-15 10:00:00';
        $GLOBALS['_wp_stores']['test_posts'][99] = $p;

        Hour_Sync::sync_hours_to_volunteer_global(99, 1, 'realizado');

        // Should not log errors (just returns early)
        $this->assertEmpty(\Convoca\Core\Logger::$logs);
    }
}
