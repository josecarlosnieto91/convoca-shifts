<?php
/**
 * PHPStan stubs for Convoca\Core classes.
 *
 * This plugin extends/uses classes provided by the separate convoca-core plugin
 * at runtime, which is NOT present in this repo's CI. Signature-accurate stubs
 * keep PHPStan able to resolve the class hierarchy without depending on
 * ../convoca-core.
 *
 * Signatures mirror the real implementations (read from convoca-core/includes)
 * so PHPStan catches real type errors instead of false "class not found" noise.
 *
 * Analysis-only: never loaded at runtime (namespace-guarded under PHPStan).
 */

namespace Convoca\Core {
	abstract class Upgrade_Manager {
		public function init(): void {}
		public function maybe_upgrade(): void {}
		public function force_version_check(): void {}
		protected function run_upgrades( string $from, string $to ): void {}
		protected function run_data_migration( callable $callback, string $description ): void {}
		protected function update_db_version( string $version ): void {}
		protected function get_saved_version(): string {
			return '';
		}
		protected function is_cached(): bool {
			return false;
		}
		protected function set_cache(): void {}
		protected function clear_cache(): void {}

		abstract protected function get_db_version(): string;
		abstract protected function get_option_name(): string;
		abstract protected function get_transient_prefix(): string;
		abstract protected function get_upgrade_callbacks(): array;
	}

	final class Logger {
		public static function log( string $message, string $level = 'info', string $context = 'General', ?int $object_id = null ): void {}
		public static function info( string $message, string $context = 'General', ?int $object_id = null ): void {}
		public static function warning( string $message, string $context = 'General', ?int $object_id = null ): void {}
		public static function error( string $message, string $context = 'General', ?int $object_id = null ): void {}
		public static function debug( string $message, string $context = 'General' ): void {}
	}

	final class Utils {
		public static function do_action( string $new_hook, string $old_hook = '', ...$args ): void {}
		public static function is_plugin_active_safe( string $plugin ): bool { return true; }
		public static function acquire_lock( string $key, int $ttl = 60 ): bool { return true; }
		public static function release_lock( string $key ): bool { return true; }
		public static function check_rate_limit( string $action, int $max = 10, int $window = 300 ): bool { return true; }
		public static function escape_csv_field( $field ): string { return ''; }
	}

	final class License_Manager {
		public static function has_pro( string $feature ): bool { return true; }
	}
}
