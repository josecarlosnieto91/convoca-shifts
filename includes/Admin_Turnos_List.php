<?php
/**
 * Custom WP_List_Table for centro_turno with Convoca styling.
 *
 * @package Convoca\Shifts

 */
namespace Convoca\Shifts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Admin_Turnos_List extends \WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'turno',
				'plural'   => 'turnos',
				'ajax'     => false,
				'screen'   => 'convoca-shifts-turnos-list',
			)
		);
	}

	public function get_columns(): array {
		return array(
			'cb'          => '<input type="checkbox">',
			'fecha'       => __( 'Fecha', 'convoca-shifts' ),
			'hora'        => __( 'Horario', 'convoca-shifts' ),
			'estado'      => __( 'Estado', 'convoca-shifts' ),
			'responsable' => __( 'Responsable', 'convoca-shifts' ),
			'apoyo'       => __( 'Apoyo', 'convoca-shifts' ),
			'estado_real' => __( 'Asistencia', 'convoca-shifts' ),
			'acciones'    => __( 'Acciones', 'convoca-shifts' ),
		);
	}

	public function get_sortable_columns(): array {
		return array(
			'fecha'  => array( '_fecha_inicio', true ),
			'estado' => array( 'estado', false ),
		);
	}

	protected function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="turno[]" value="%d">', $item->ID );
	}

	protected function column_fecha( $item ): string {
		$fecha = get_post_meta( $item->ID, '_fecha_inicio', true );
		if ( $fecha ) {
			return esc_html( wp_date( 'd/m/Y', strtotime( $fecha ) ) );
		}
		return '—';
	}

	protected function column_hora( $item ): string {
		$fecha_ini = get_post_meta( $item->ID, '_fecha_inicio', true );
		$hora_fin  = get_post_meta( $item->ID, '_hora_fin', true );
		if ( $fecha_ini ) {
			$hora_ini = wp_date( 'H:i', strtotime( $fecha_ini ) );
			return $hora_fin ? esc_html( "$hora_ini - $hora_fin" ) : esc_html( $hora_ini );
		}
		return '—';
	}

	protected function column_estado( $item ): string {
		$estado = get_post_meta( $item->ID, '_estado', true );
		$badges = array(
			'abierto_disponible' => 'convoca-badge--warning',
			'abierto_ocupado'    => 'convoca-badge--info',
			'cerrado'            => 'convoca-badge--error',
		);
		$labels = array(
			'abierto_disponible' => '🟡 Pendiente',
			'abierto_ocupado'    => '🔵 Ocupado',
			'cerrado'            => '🔴 Cerrado',
		);
		$class  = $badges[ $estado ] ?? 'convoca-badge--info';
		$label  = $labels[ $estado ] ?? $estado;
		return '<span class="convoca-badge ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
	}

	protected function column_responsable( $item ): string {
		$user_id = (int) get_post_meta( $item->ID, '_id_responsable', true );
		if ( ! $user_id ) {
			return '<span style="color:#94a3b8;">—</span>';
		}
		$user = get_userdata( $user_id );
		return $user ? esc_html( $user->display_name ) : '<span style="color:#94a3b8;">—</span>';
	}

	protected function column_apoyo( $item ): string {
		$apoyo = (int) get_post_meta( $item->ID, '_necesita_apoyo', true );
		return $apoyo ? '<span class="convoca-badge convoca-badge--warning">🛟 Sí</span>' : '<span style="color:#94a3b8;">No</span>';
	}

	protected function column_estado_real( $item ): string {
		$estado  = get_post_meta( $item->ID, '_estado_real', true ) ?: 'pendiente';
		$classes = array(
			'pendiente'  => 'convoca-badge--warning',
			'realizado'  => 'convoca-badge--success',
			'no_asistio' => 'convoca-badge--error',
		);
		$labels  = array(
			'pendiente'  => '⏳ Pendiente',
			'realizado'  => '✅ Realizado',
			'no_asistio' => '❌ No asistió',
		);
		$class   = $classes[ $estado ] ?? 'convoca-badge--warning';
		return '<span class="convoca-badge ' . esc_attr( $class ) . '">' . esc_html( $labels[ $estado ] ?? $estado ) . '</span>';
	}

	protected function column_acciones( $item ): string {
		$edit_url = admin_url( 'edit.php?post_type=centro_turno&page=convoca-shifts-editar-turno&id=' . $item->ID );

		$actions   = array();
		$actions[] = '<a href="' . esc_url( $edit_url ) . '" class="convoca-btn convoca-btn-outline" style="padding:3px 8px;font-size:12px;">✏️ ' . __( 'Editar', 'convoca-shifts' ) . '</a>';

		return '<div style="display:flex;gap:5px;">' . implode( '', $actions ) . '</div>';
	}

	public function get_bulk_actions(): array {
		return array(
			'mark_realizado'  => __( '✅ Marcar Realizado', 'convoca-shifts' ),
			'mark_no_asistio' => __( '❌ Marcar No asistió', 'convoca-shifts' ),
		);
	}

	public function prepare_items(): void {
		$per_page     = 25;
		$current_page = $this->get_pagenum();

		// Ordenación: por _fecha_inicio por defecto.
		$orderby = sanitize_text_field( $_GET['orderby'] ?? '_fecha_inicio' );
		$order   = strtoupper( sanitize_text_field( $_GET['order'] ?? 'DESC' ) ) === 'ASC' ? 'ASC' : 'DESC';

		$args = array(
			'post_type'      => 'centro_turno',
			'posts_per_page' => $per_page,
			'paged'          => $current_page,
			'post_status'    => 'any',
			'order'          => $order,
		);

		if ( $orderby === '_fecha_inicio' || $orderby === 'meta_value' ) {
			$args['meta_key'] = '_fecha_inicio';
			$args['orderby']  = 'meta_value';
		} elseif ( $orderby === 'estado' ) {
			$args['meta_key'] = '_estado';
			$args['orderby']  = 'meta_value';
		} else {
			$args['orderby'] = $orderby;
		}

		// Filters.
		$filter_estado     = $_GET['filter_estado'] ?? '';
		$filter_asistencia = $_GET['filter_asistencia'] ?? '';
		$filter_apoyo      = $_GET['filter_apoyo'] ?? '';
		$s                 = $_GET['s'] ?? '';

		$meta_query = array();
		if ( $filter_estado ) {
			$meta_query[] = array(
				'key'   => '_estado',
				'value' => $filter_estado,
			);
		}
		if ( $filter_asistencia ) {
			$meta_query[] = array(
				'key'   => '_estado_real',
				'value' => $filter_asistencia,
			);
		}
		if ( $filter_apoyo !== '' ) {
			$meta_query[] = array(
				'key'   => '_necesita_apoyo',
				'value' => $filter_apoyo,
			);
		}
		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = $meta_query;
		}

		if ( $s ) {
			$args['s'] = $s;
		}

		$query       = new \WP_Query( $args );
		$this->items = $query->posts;

		$this->set_pagination_args(
			array(
				'total_items' => $query->found_posts,
				'per_page'    => $per_page,
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}

	public function extra_tablenav( $which ): void {
		if ( $which !== 'top' ) {
			return;
		}
		$filter_estado     = $_GET['filter_estado'] ?? '';
		$filter_asistencia = $_GET['filter_asistencia'] ?? '';
		$filter_apoyo      = $_GET['filter_apoyo'] ?? '';
		?>
		<div class="alignleft actions" style="display:flex;gap:10px;align-items:center;">
			<select name="filter_estado">
				<option value=""><?php _e( 'Todos los estados', 'convoca-shifts' ); ?></option>
				<option value="abierto_disponible" <?php selected( $filter_estado, 'abierto_disponible' ); ?>><?php _e( '🟡 Pendiente', 'convoca-shifts' ); ?></option>
				<option value="abierto_ocupado" <?php selected( $filter_estado, 'abierto_ocupado' ); ?>><?php _e( '🔵 Ocupado', 'convoca-shifts' ); ?></option>
				<option value="cerrado" <?php selected( $filter_estado, 'cerrado' ); ?>><?php _e( '🔴 Cerrado', 'convoca-shifts' ); ?></option>
			</select>

			<select name="filter_asistencia">
				<option value=""><?php _e( 'Todas asistencias', 'convoca-shifts' ); ?></option>
				<option value="pendiente" <?php selected( $filter_asistencia, 'pendiente' ); ?>><?php _e( '⏳ Pendiente', 'convoca-shifts' ); ?></option>
				<option value="realizado" <?php selected( $filter_asistencia, 'realizado' ); ?>><?php _e( '✅ Realizado', 'convoca-shifts' ); ?></option>
				<option value="no_asistio" <?php selected( $filter_asistencia, 'no_asistio' ); ?>><?php _e( '❌ No asistió', 'convoca-shifts' ); ?></option>
			</select>

			<select name="filter_apoyo">
				<option value=""><?php _e( 'Apoyo: Todos', 'convoca-shifts' ); ?></option>
				<option value="1" <?php selected( $filter_apoyo, '1' ); ?>><?php _e( '🛟 Necesita apoyo', 'convoca-shifts' ); ?></option>
				<option value="0" <?php selected( $filter_apoyo, '0' ); ?>><?php _e( 'Sin apoyo', 'convoca-shifts' ); ?></option>
			</select>

			<?php submit_button( __( 'Filtrar', 'convoca-shifts' ), 'convoca-btn convoca-btn-outline', 'filter_action', false ); ?>
		</div>
		<?php
	}
}


