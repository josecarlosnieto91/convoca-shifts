<?php
/**
 * Custom list page for centro_turno using WP_List_Table.
 *
 * @package CentroSocialTurnos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Turnos_List_Page {

	public function __construct() {
		add_action( 'load-edit.php', array( $this, 'redirect_native_list' ) );
		add_filter( 'admin_bar_menu', array( $this, 'customize_admin_bar' ), 80 );
		add_action( 'admin_menu', array( $this, 'register_page' ), 20 );
	}

	/**
	 * Registrar la página personalizada fuera del menú lateral (sin entrada visible).
	 */
	public function register_page(): void {
		add_submenu_page(
			null, // null = página oculta, sin entrada en el menú.
			__( 'Turnos', 'convoca-shifts' ),
			__( 'Turnos', 'convoca-shifts' ),
			'convoca_shifts_manage_turnos',
			'cst-turnos-list',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Redirect from native edit.php?post_type=centro_turno to our custom page.
	 */
	public function redirect_native_list(): void {
		global $typenow;
		if ( $typenow === 'centro_turno' && ! isset( $_GET['page'] ) ) {
			wp_safe_redirect( admin_url( 'edit.php?post_type=centro_turno&page=cst-turnos-list' ) );
			exit;
		}
	}

	/**
	 * Fix the admin bar "All Turnos" link.
	 */
	public function customize_admin_bar( \WP_Admin_Bar $wp_admin_bar ): void {
		$node = $wp_admin_bar->get_node( 'edit-post_type-archives' );
		if ( ! $node ) {
			$node = $wp_admin_bar->get_node( 'archive' );
		}
		$list_node = $wp_admin_bar->get_node( 'edit-centro_turno' );
		if ( $list_node ) {
			$list_node->href = admin_url( 'edit.php?post_type=centro_turno&page=cst-turnos-list' );
			$wp_admin_bar->add_node( $list_node );
		}
	}

	/**
	 * Render the custom list page.
	 */
	public function render_page(): void {
		$table = new Admin_Turnos_List();
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php _e( 'Turnos', 'convoca-shifts' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=centro_turno&page=convoca_shifts_turno_rapido' ) ); ?>" class="convoca-btn convoca-btn-primary" style="margin-left:10px;">
				+ <?php _e( 'Añadir Turno Rápido', 'convoca-shifts' ); ?>
			</a>
			<hr class="wp-header-end">

			<form method="get">
				<input type="hidden" name="post_type" value="centro_turno">
				<input type="hidden" name="page" value="cst-turnos-list">
				<?php $table->search_box( __( 'Buscar turno...', 'convoca-shifts' ), 'search_id' ); ?>
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}
}
