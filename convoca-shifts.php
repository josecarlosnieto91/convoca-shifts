<?php
/**
 * Plugin Name: Convoca Shifts
 * Plugin URI: https://convoca.org
 * Description: Volunteer shift management for community centers.
 * Version: 2.5.0
 * Author: Jose Carlos Nieto Ramos
 * Text Domain: convoca-shifts
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Tested up to: 6.5
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins: convoca-core, convoca-members
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! defined( 'CST_PLUGIN_DIR' ) ) {
	define( 'CST_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'CST_PLUGIN_URL' ) ) {
	define( 'CST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'CST_PLUGIN_VERSION' ) ) {
	define( 'CST_PLUGIN_VERSION', '2.5.0' );
}

// Includes.
require_once CST_PLUGIN_DIR . 'includes/cpt-turno.php';
require_once CST_PLUGIN_DIR . 'includes/rest-api.php';
require_once CST_PLUGIN_DIR . 'includes/shortcodes.php';
require_once CST_PLUGIN_DIR . 'includes/admin-approval.php';
require_once CST_PLUGIN_DIR . 'includes/admin-turno-rapido.php';
require_once CST_PLUGIN_DIR . 'includes/admin-estadisticas.php';
require_once CST_PLUGIN_DIR . 'includes/admin-settings.php';
require_once CST_PLUGIN_DIR . 'includes/class-admin-turno-editor.php';
require_once CST_PLUGIN_DIR . 'includes/class-admin-turnos-list.php';
require_once CST_PLUGIN_DIR . 'includes/class-admin-turnos-list-page.php';
if ( is_admin() ) {
	new CST_Admin_Turno_Editor();
	new Admin_Turnos_List_Page();
}
require_once CST_PLUGIN_DIR . 'includes/cron.php';
require_once CST_PLUGIN_DIR . 'includes/widgets.php';
require_once CST_PLUGIN_DIR . 'includes/blocks.php';

/* ── Dependency Check ──────────────────────────────────── */
if ( ! class_exists( '\\Convoca\\Core\\Utils' ) ) {
	add_action(
		'admin_notices',
		function (): void {
			printf(
				'<div class="notice notice-error"><p><strong>Centro Social Turnos:</strong> Este plugin requiere el plugin <strong>Biodevas Common Utilities</strong> activo.</p></div>'
			);
		}
	);
	return;
}

/* ── Enqueue Styles ──────────────────────────────────── */
add_action(
	'admin_enqueue_scripts',
	function ( string $hook ): void {
		wp_register_style( 'cst-estilo', CST_PLUGIN_URL . 'assets/css/estilo.css', array(), CST_PLUGIN_VERSION );
		$screen = get_current_screen();
		if ( $screen && in_array( $screen->post_type, array( 'centro_turno' ), true ) ) {
			wp_enqueue_style( 'cst-estilo' );
		}
		if ( strpos( $hook, 'cst_' ) !== false || strpos( $hook, 'centro_turno' ) !== false ) {
			wp_enqueue_style( 'cst-estilo' );
		}
	}
);


spl_autoload_register(
	function ( string $class ): void {
		$prefix = 'Assoc\\Shifts\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}
		$relative = str_replace( $prefix, '', $class );
		$relative = strtolower( str_replace( '_', '-', $relative ) );

		$file = CST_PLUGIN_DIR . 'includes/class-' . $relative . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

/* ── Activation Hook ──────────────────────────────────────── */

/**
 * Activation hook
 */
register_activation_hook( __FILE__, 'cst_activate_plugin' );
function cst_activate_plugin() {
	cst_register_cpt_centro_turno();
	cst_create_log_table();
	flush_rewrite_rules();
	cst_schedule_cron();

	// Defensive: check if the required role exists.
	if ( ! get_role( 'voluntario_aprobado' ) ) {
		error_log( 'CST Warning: The role "voluntario_aprobado" is missing. It should be created by Biodevas Members.' );
	}
}

function cst_create_log_table() {
	global $wpdb;
	$table_name      = $wpdb->prefix . 'cst_activity_log';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        turno_id bigint(20) NOT NULL,
        action varchar(50) NOT NULL,
        data longtext DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY action (action)
    ) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
	$wpdb->query( '// Index already created by CREATE TABLE' );
}

/**
 * Log an activity to the custom table.
 */
function cst_log_activity( $user_id, $turno_id, $action, $data = array() ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'cst_activity_log';

	// Cache existence of table to avoid SHOW TABLES on every log.
	if ( false === get_transient( 'cst_log_table_exists' ) ) {
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
			cst_create_log_table();
		}
		set_transient( 'cst_log_table_exists', 1, DAY_IN_SECONDS );
	}

	$wpdb->insert(
		$table_name,
		array(
			'user_id'    => $user_id,
			'turno_id'   => $turno_id,
			'action'     => $action,
			'data'       => ! empty( $data ) ? json_encode( $data ) : null,
			'created_at' => current_time( 'mysql' ),
		)
	);
}

/**
 * Deactivation hook
 */
register_deactivation_hook( __FILE__, 'cst_deactivate_plugin' );
function cst_deactivate_plugin() {
	cst_clear_cron();
}

/**
 * Helper: Format a timestamp to Spanish readable date (locale-independent).
 *
 * @param int $timestamp Unix timestamp.
 * @return string Date formatted as "lunes 5 de mayo a las 14:00"
 */
function cst_fecha_legible( int $timestamp ): string {
	static $dias  = array( 'domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado' );
	static $meses = array( '', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre' );

	$dia_semana = $dias[ (int) wp_date( 'w', $timestamp ) ];
	$dia_mes    = (int) wp_date( 'j', $timestamp );
	$mes        = $meses[ (int) wp_date( 'n', $timestamp ) ];
	$hora       = wp_date( 'H:i', $timestamp );

	return "$dia_semana $dia_mes de $mes a las $hora";
}

/**
 * Helper: Short Spanish date (day + month).
 *
 * @param int $timestamp Unix timestamp.
 * @return string e.g. "lunes 5 de mayo"
 */
function cst_fecha_corta( int $timestamp ): string {
	static $dias  = array( 'domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado' );
	static $meses = array( '', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre' );

	$dia_semana = $dias[ (int) wp_date( 'w', $timestamp ) ];
	$dia_mes    = (int) wp_date( 'j', $timestamp );
	$mes        = $meses[ (int) wp_date( 'n', $timestamp ) ];

	return "$dia_semana $dia_mes de $mes";
}

require_once CST_PLUGIN_DIR . 'includes/class-cst-upgrade-manager.php';

/**
 * Initialize Upgrade Manager
 */
add_action(
	'plugins_loaded',
	function () {
		if ( class_exists( '\\Biodevas\\CST\\CST_Upgrade_Manager' ) ) {
			$upgrade_manager = new \Convoca\Shifts\CST_Upgrade_Manager();
			$upgrade_manager->init();
		}
	},
	20
); // Priority 20 to ensure common is loaded.

/**
 * Admin notice if the required role is missing
 */
add_action( 'admin_notices', 'cst_check_required_role' );
function cst_check_required_role() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Allow user to dismiss this notice for 7 days.
	$dismissed = get_user_meta( get_current_user_id(), '_cst_role_notice_dismissed', true );
	if ( $dismissed && time() - (int) $dismissed < DAY_IN_SECONDS * 7 ) {
		return;
	}

	if ( ! get_role( 'voluntario_aprobado' ) ) {
		?>
		<div class="convoca-alert convoca-alert--warning" style="display:block;margin-bottom:20px;">
			<p><?php _e( '<strong>Atención:</strong> El rol "Voluntario Aprobado" no existe. Este rol es necesario para el funcionamiento de Centro Social Turnos y debería ser creado por el plugin Biodevas Members. Por favor, asegúrate de que Biodevas Members está activo y ha sido reactivado recientemente.', 'convoca-shifts' ); ?>
			<a href="?cst_dismiss_role_notice=1" style="float:right;text-decoration:none;color:#999;">✕</a></p>
		</div>
		<?php
	}
}

// Handle notice dismissal.
add_action(
	'admin_init',
	function () {
		if ( isset( $_GET['cst_dismiss_role_notice'] ) && current_user_can( 'manage_options' ) ) {
			update_user_meta( get_current_user_id(), '_cst_role_notice_dismissed', time() );
			wp_safe_redirect( remove_query_arg( 'cst_dismiss_role_notice' ) );
			exit;
		}
	}
);
