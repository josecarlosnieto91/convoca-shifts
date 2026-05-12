<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Force post status to 'publish' for centro_turno, even if dated in the future.
 * This prevents shifts from disappearing into "Scheduled" status.
 */
/**
 * Centralized logic to sync shift Title and Status on every save.
 * This handles Full Edit, Quick Edit, and REST API updates.
 */
/**
 * Centralized logic to sync shift Title and Status on every save.
 */
add_action( 'save_post_centro_turno', 'cst_sync_turno_on_save', 20, 3 );
function cst_sync_turno_on_save( $post_id, $post, $update ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    if ( $post->post_type !== 'centro_turno' ) return;

    // Remove action to prevent infinite loop
    remove_action( 'save_post_centro_turno', 'cst_sync_turno_on_save', 20 );

    try {
        // 1. Identify the Responsible ID
        $id_responsable = (int) get_post_meta( $post_id, '_id_responsable', true );

        // 2. Sync Title
        $estado = get_post_meta( $post_id, '_estado', true );
        $new_title = '';

        if ( $estado === 'cerrado' ) {
            $new_title = '🔴 Centro Cerrado';
        } elseif ( $estado === 'abierto_ocupado' ) {
            $actividad_name = '';
            $terms = wp_get_post_terms( $post_id, 'cst_actividad' );
            if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                $actividad_name = $terms[0]->name;
            }

            $monitor_name = '';
            $monitor_user_id = (int) get_post_meta( $post_id, '_monitor', true );
            if ( $monitor_user_id > 0 ) {
                $monitor_user = get_userdata( $monitor_user_id );
                $monitor_name = $monitor_user ? $monitor_user->display_name : '';
            }
            
            if ( ! empty( $actividad_name ) ) {
                $new_title = '🔵 ' . $actividad_name;
            } else {
                $new_title = '🔵 Ocupado' . ( ! empty( $monitor_name ) ? ' - ' . $monitor_name : '' );
            }
        } elseif ( $id_responsable > 0 ) {
            $u = get_userdata( $id_responsable );
            $nombre = ( $u && ! empty( $u->first_name ) ) ? $u->first_name : ( $u ? $u->display_name : 'Usuario' );
            $new_title = '🟢 Cubierto - ' . $nombre;
        } else {
            $new_title = '🟡 Pendiente';
        }

        // 3. Update Title and Status if needed
        $update_data = [];
        if ( $post->post_title !== $new_title ) {
            $update_data['post_title'] = $new_title;
        }
        
        // Force Publish Status (Avoid "Scheduled" bug)
        if ( $post->post_status === 'future' ) {
            $update_data['post_status'] = 'publish';
        }

        if ( ! empty( $update_data ) ) {
            $update_data['ID'] = $post_id;
            wp_update_post( $update_data );
        }
    } finally {
        // Re-add action regardless of errors
        add_action( 'save_post_centro_turno', 'cst_sync_turno_on_save', 20, 3 );
    }
}

function cst_register_cpt_centro_turno() {
    $labels = array(
        'name'                  => _x( 'Turnos', 'Post Type General Name', 'convoca-shifts' ),
        'singular_name'         => _x( 'Turno', 'Post Type Singular Name', 'convoca-shifts' ),
        'menu_name'             => __( 'Turnos Centro', 'convoca-shifts' ),
        'name_admin_bar'        => __( 'Turno', 'convoca-shifts' ),
        'archives'              => __( 'Archivo de Turnos', 'convoca-shifts' ),
        'attributes'            => __( 'Atributos de Turno', 'convoca-shifts' ),
        'parent_item_colon'     => __( 'Turno Padre:', 'convoca-shifts' ),
        'all_items'             => __( 'Todos los Turnos', 'convoca-shifts' ),
        'add_new_item'          => __( 'Añadir Nuevo Turno', 'convoca-shifts' ),
        'add_new'               => __( 'Añadir Nuevo', 'convoca-shifts' ),
        'new_item'              => __( 'Nuevo Turno', 'convoca-shifts' ),
        'edit_item'             => __( 'Editar Turno', 'convoca-shifts' ),
        'update_item'           => __( 'Actualizar Turno', 'convoca-shifts' ),
        'view_item'             => __( 'Ver Turno', 'convoca-shifts' ),
        'view_items'            => __( 'Ver Turnos', 'convoca-shifts' ),
        'search_items'          => __( 'Buscar Turno', 'convoca-shifts' ),
        'not_found'             => __( 'No encontrado', 'convoca-shifts' ),
        'not_found_in_trash'    => __( 'No encontrado en la Papelera', 'convoca-shifts' ),
    );
    $args = array(
        'label'                 => __( 'Turno', 'convoca-shifts' ),
        'description'           => __( 'Turnos de apertura del centro social', 'convoca-shifts' ),
        'labels'                => $labels,
        'supports'              => array( 'title', 'editor', 'custom-fields' ),
        'hierarchical'          => false,
        'public'                => false, // We use it via REST/FullCalendar mainly
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 25,
        'menu_icon'             => 'dashicons-calendar-alt',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => false,
        'can_export'            => true,
        'has_archive'           => false,
        'exclude_from_search'   => true,
        'publicly_queryable'    => false,
        'show_in_rest'          => true,
        'capability_type'       => 'post',
    );
    register_post_type( 'centro_turno', $args );

    // Register Taxonomies
    register_taxonomy( 'cst_actividad', 'centro_turno', array(
        'label'        => __( 'Actividades', 'convoca-shifts' ),
        'rewrite'      => array( 'slug' => 'actividad' ),
        'hierarchical' => true,
        'show_in_rest' => true,
    ));

    // Taxonomy Meta for Actividades
    add_action('cst_actividad_add_form_fields', 'cst_actividad_add_meta_fields', 10, 2);
    add_action('cst_actividad_edit_form_fields', 'cst_actividad_edit_meta_fields', 10, 2);
    add_action('created_cst_actividad', 'cst_save_actividad_meta', 10, 2);
    add_action('edited_cst_actividad', 'cst_save_actividad_meta', 10, 2);

    // Monitor: Ahora se usa el rol monitor_actividad (WP user), no taxonomía.

    // Register Meta Fields
    register_post_meta( 'centro_turno', '_hora_fin', array(
        'show_in_rest' => true,
        'single'       => true,
        'type'         => 'string', // format: Y-m-d H:i:s
    ));
    register_post_meta( 'centro_turno', '_estado', array(
        'show_in_rest' => true,
        'single'       => true,
        'type'         => 'string',
        'default'      => 'abierto_disponible', // abierto_disponible, abierto_ocupado, cerrado
    ));
    register_post_meta( 'centro_turno', '_id_responsable', array(
        'show_in_rest' => true,
        'single'       => true,
        'type'         => 'integer',
        'default'      => 0,
    ));
    register_post_meta( 'centro_turno', '_notas', array(
        'show_in_rest' => true,
        'single'       => true,
        'type'         => 'string',
    ));
    register_post_meta( 'centro_turno', '_necesita_apoyo', array(
        'show_in_rest' => true,
        'single'       => true,
        'type'         => 'boolean',
        'default'      => false,
    ));
    register_post_meta( 'centro_turno', '_estado_real', array(
        'show_in_rest' => true,
        'single'       => true,
        'type'         => 'string',
        'default'      => 'pendiente', // pendiente, realizado, no_asistio
    ));
    register_post_meta( 'centro_turno', '_actividad', array(
        'show_in_rest' => true,
        'single'       => true,
        'type'         => 'string',
    ));
    register_post_meta( 'centro_turno', '_monitor', array(
        'show_in_rest' => true,
        'single'       => true,
        'type'         => 'string',
    ));
}
add_action( 'init', 'cst_register_cpt_centro_turno' );

// Add metabox for UI configuration of Necesita Apoyo
add_action('add_meta_boxes', 'cst_add_turno_metaboxes');
function cst_add_turno_metaboxes() {
    add_meta_box(
        'cst_turno_opciones',
        __( 'Opciones de Turno', 'convoca-shifts' ),
        'cst_turno_opciones_html',
        'centro_turno',
        'side',
        'default'
    );
}

function cst_turno_opciones_html($post) {
    $apoyo = get_post_meta($post->ID, '_necesita_apoyo', true);
    $estado_real = get_post_meta($post->ID, '_estado_real', true);
    $estado = get_post_meta($post->ID, '_estado', true);
    
    // Get current taxonomy terms
    $term_actividad = wp_get_post_terms($post->ID, 'cst_actividad', array('fields' => 'ids'));
    $current_actividad = !empty($term_actividad) ? $term_actividad[0] : 0;
    
    $current_monitor = (int) get_post_meta($post->ID, '_monitor', true);

    $id_responsable = (int) get_post_meta($post->ID, '_id_responsable', true);
    if (!$estado_real) $estado_real = 'pendiente';
    
    // Get only approved volunteers for the selector
    $voluntarios = get_users(array(
        'role'    => 'voluntario_aprobado',
        'orderby' => 'display_name'
    ));
    
    wp_nonce_field('cst_turno_meta_action', 'cst_turno_meta_nonce');
    ?>
    <p>
        <label for="cst_id_responsable"><strong><?php _e('👤 Responsable Asignado:', 'convoca-shifts'); ?></strong></label><br>
        <select name="cst_id_responsable" id="cst_id_responsable" style="width:100%; margin-top:5px;">
            <option value="0"><?php _e('— Sin asignar —', 'convoca-shifts'); ?></option>
            <?php foreach ($voluntarios as $v) : 
                $nombre = !empty($v->first_name) ? $v->first_name : $v->display_name;
                ?>
                <option value="<?php echo $v->ID; ?>" <?php selected($id_responsable, $v->ID); ?>>
                    <?php echo esc_html($nombre . ' (@' . $v->user_login . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
    <hr>
    <p>
        <label for="cst_estado"><strong><?php _e('Estado del Centro:', 'convoca-shifts'); ?></strong></label><br>
        <select name="cst_estado" id="cst_estado" style="width:100%; margin-top:5px;">
            <option value="abierto_disponible" <?php selected($estado, 'abierto_disponible'); ?>><?php _e('🟡 Pendiente (Abierto)', 'convoca-shifts'); ?></option>
            <option value="abierto_ocupado" <?php selected($estado, 'abierto_ocupado'); ?>><?php _e('🔵 Ocupado (Actividad)', 'convoca-shifts'); ?></option>
            <option value="cerrado" <?php selected($estado, 'cerrado'); ?>><?php _e('🔴 Cerrado', 'convoca-shifts'); ?></option>
        </select>
    </p>
    <div id="cst_ocupado_fields" style="<?php echo ($estado === 'abierto_ocupado') ? '' : 'display:none;'; ?> background:#f0f7ff; padding:10px; border-radius:4px; margin-top:5px;">
        <label for="cst_actividad_term"><strong><?php _e('Actividad:', 'convoca-shifts'); ?></strong></label>
        <?php 
        wp_dropdown_categories(array(
            'show_option_none' => __('— Seleccionar Actividad —', 'convoca-shifts'),
            'taxonomy'         => 'cst_actividad',
            'name'             => 'cst_actividad_term',
            'selected'         => $current_actividad,
            'orderby'          => 'name',
            'hierarchical'     => true,
            'hide_empty'       => false,
            'class'            => 'widefat'
        ));
        ?>
        <p class="description"><?php _e('Gestiona actividades desde el menú lateral.', 'convoca-shifts'); ?></p>
        
        <label for="cst_monitor_select"><strong><?php _e('Monitor/a:', 'convoca-shifts'); ?></strong></label>
        <select id="cst_monitor_select" name="cst_monitor_user" style="width:100%;">
            <option value="0"><?php _e('— Sin monitor —', 'convoca-shifts'); ?></option>
            <?php
            $monitor_users = get_users(array('role__in' => array('administrator', 'monitor_actividad'), 'orderby' => 'display_name'));
            foreach ($monitor_users as $mu):
                $sel = $current_monitor == $mu->ID ? 'selected="selected"' : '';
            ?>
                <option value="<?php echo esc_attr($mu->ID); ?>" <?php echo $sel; ?>>
                    <?php echo esc_html($mu->display_name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php _e('Asigna un usuario con rol monitor_actividad.', 'convoca-shifts'); ?></p>
    </p>
    <?php
}

add_action('save_post_centro_turno', 'cst_save_turno_meta');
function cst_save_turno_meta($post_id) {
    if (!isset($_POST['cst_turno_meta_nonce']) || !wp_verify_nonce($_POST['cst_turno_meta_nonce'], 'cst_turno_meta_action')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $apoyo = isset($_POST['cst_necesita_apoyo']) ? 1 : 0;
    update_post_meta($post_id, '_necesita_apoyo', $apoyo);

    if (isset($_POST['cst_estado_real'])) {
        update_post_meta($post_id, '_estado_real', sanitize_text_field($_POST['cst_estado_real']));
    }

    if (isset($_POST['cst_estado'])) {
        update_post_meta($post_id, '_estado', sanitize_text_field($_POST['cst_estado']));
    }

    if (isset($_POST['cst_actividad_term'])) {
        $term_id = (int) $_POST['cst_actividad_term'];
        wp_set_post_terms($post_id, $term_id > 0 ? array($term_id) : array(), 'cst_actividad');
    }

    if (isset($_POST['cst_monitor_user'])) {
        $user_id = (int) $_POST['cst_monitor_user'];
        update_post_meta($post_id, '_monitor', $user_id > 0 ? $user_id : '');
    }

    if (isset($_POST['cst_id_responsable'])) {
        $new_id = (int) $_POST['cst_id_responsable'];
        $old_id = (int) get_post_meta($post_id, '_id_responsable', true);
        
        if ($new_id !== $old_id) {
            update_post_meta($post_id, '_id_responsable', $new_id);
            
            // If we assign a volunteer, and it was "disponible", keep it "disponible" (it will be green now)
            $current_state = get_post_meta($post_id, '_estado', true);
            if ($current_state !== 'cerrado' && $current_state !== 'abierto_ocupado') {
                update_post_meta($post_id, '_estado', 'abierto_disponible');
            }
            
            update_post_meta($post_id, '_estado_real', 'pendiente');

            if ( function_exists( 'cst_log_activity' ) ) {
                $action = ($new_id > 0) ? 'turno_asignado' : 'turno_desasignado';
                cst_log_activity( get_current_user_id(), $post_id, $action, array('voluntario_id' => $new_id) );
            }
        }
    }
}

// --- Admin List Enhancements ---

add_filter( 'manage_centro_turno_posts_columns', 'cst_set_custom_edit_centro_turno_columns' );
function cst_set_custom_edit_centro_turno_columns($columns) {
    $new_columns = array();
    foreach($columns as $key => $value) {
        if($key === 'date') {
            $new_columns['responsable'] = __( 'Responsable', 'convoca-shifts' );
            $new_columns['estado_real'] = __( 'Asistencia', 'convoca-shifts' );
        }
        $new_columns[$key] = $value;
    }
    return $new_columns;
}

add_action( 'manage_centro_turno_posts_custom_column' , 'cst_custom_centro_turno_column', 10, 2 );
function cst_custom_centro_turno_column( $column, $post_id ) {
    switch ( $column ) {
        case 'responsable' :
            $id = get_post_meta( $post_id , '_id_responsable' , true );
            if ( $id ) {
                $user = get_userdata($id);
                if ($user) {
                    $nombre = !empty($user->first_name) ? $user->first_name : $user->display_name;
                    echo '<strong>👤 ' . esc_html($nombre) . '</strong>';
                } else {
                    echo '—';
                }
            } else {
                echo '<span style="color:#94a3b8;">' . __('Sin asignar', 'convoca-shifts') . '</span>';
            }
            break;
        case 'estado_real' :
            $estado = get_post_meta( $post_id , '_estado_real' , true );
            if (!$estado) $estado = 'pendiente';
            
            $badges = array(
                'pendiente'  => '<span class="badge-cst badge-pending">⏳ Pendiente</span>',
                'realizado'  => '<span class="badge-cst badge-success">✅ Realizado</span>',
                'no_asistio' => '<span class="badge-cst badge-danger">❌ No asistió</span>'
            );
            
            echo '<div style="margin-bottom:8px;">' . (isset($badges[$estado]) ? $badges[$estado] : esc_html($estado)) . '</div>';
            
            $id_responsable = (int) get_post_meta( $post_id , '_id_responsable' , true );
            
            // Quick Actions only if someone is assigned
            if ( $id_responsable > 0 ) {
                $base_url = admin_url('edit.php?post_type=centro_turno&cst_action=mark_attendance&post=' . $post_id);
                $nonce = wp_create_nonce('cst_attendance_' . $post_id);
                
                echo '<div class="cst-row-actions" style="font-size:11px; margin-top: 6px;">';
                echo '<a href="'.esc_url(add_query_arg(array('status'=>'realizado', '_wpnonce'=>$nonce), $base_url)).'" style="color:var(--wp--preset--color--verde, #166534); text-decoration:none;">[Marcar OK]</a> | ';
                echo '<a href="'.esc_url(add_query_arg(array('status'=>'no_asistio', '_wpnonce'=>$nonce), $base_url)).'" style="color:var(--wp--preset--color--granate, #991b1b); text-decoration:none;">[No asistió]</a>';
                

                
                echo '</div>';
            }

            // Hidden fields for Quick Edit
            echo '<div class="cst-quick-edit-data" style="display:none;">';
            echo '<div class="cst_id_responsable">' . $id_responsable . '</div>';
            echo '<div class="cst_estado_real">' . esc_attr($estado) . '</div>';
            echo '</div>';
            break;
    }
}

add_action('admin_head', 'cst_admin_list_styles');
function cst_admin_list_styles() {
    $screen = get_current_screen();
    if ($screen && $screen->id === 'edit-centro_turno') {
        wp_enqueue_style('cst-estilo');
    }
}

add_action('admin_init', 'cst_handle_admin_attendance_action');
function cst_handle_admin_attendance_action() {
    $get_data = wp_unslash($_GET);
    if (isset($get_data['cst_action']) && $get_data['cst_action'] === 'mark_attendance' && isset($get_data['post'])) {
        $post_id = intval($get_data['post']);
        check_admin_referer('cst_attendance_' . $post_id);
        
        if (!current_user_can('edit_post', $post_id)) {
            wp_die(__('No tienes permisos para editar este turno.', 'convoca-shifts'));
        }

        $status = sanitize_text_field($get_data['status']);
        $id_responsable = (int) get_post_meta( $post_id , '_id_responsable' , true );

        if ( $status === 'realizado' && $id_responsable === 0 ) {
            // Cannot mark as done if nobody is assigned
            wp_die( __('No se puede marcar como realizado un turno sin responsable asignado.', 'convoca-shifts') );
        }

        global $wpdb;
        $wpdb->query('START TRANSACTION');

        try {
            update_post_meta($post_id, '_estado_real', $status);
            
            // Sync hours if applicable
            if (class_exists('\\Biodevas\\CentroSocialTurnos\\Hour_Sync')) {
                \Convoca\Shifts\Hour_Sync::sync_hours_to_volunteer_global($post_id, $id_responsable, $status);
            }

            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            \Convoca\Core\Logger::error("Error al marcar asistencia: " . $e->getMessage(), 'Turnos/Admin', $post_id);
            wp_die(__('Error al procesar la asistencia.', 'convoca-shifts'));
        }

        // Log activity
        if ( function_exists( 'cst_log_activity' ) ) {
            $action = ($status === 'realizado') ? 'asistencia_ok' : 'asistencia_no';
            cst_log_activity( get_current_user_id(), $post_id, $action );
        }

        wp_redirect(remove_query_arg(array('cst_action', 'status', 'post', '_wpnonce')));
        exit;
}
}

// --- Quick Edit Logic ---

add_action( 'quick_edit_custom_box', 'cst_display_quick_edit_turno', 10, 2 );
function cst_display_quick_edit_turno( $column_name, $post_type ) {
    if ( $post_type !== 'centro_turno' || $column_name !== 'responsable' ) return;
    
    // Get all users who can manage turns
    $voluntarios = get_users(array(
        'role__in' => array('administrator', 'voluntario_aprobado'),
        'orderby'  => 'display_name'
    ));
    ?>
    <fieldset class="inline-edit-col-right">
      <div class="inline-edit-col">
        <label>
            <span class="title"><?php _e( 'Responsable', 'convoca-shifts' ); ?></span>
            <select name="cst_id_responsable" class="cst-quick-responsable">
                <option value="0"><?php _e('— Sin asignar —', 'convoca-shifts'); ?></option>
                <?php foreach ($voluntarios as $v) : 
                    $nombre = !empty($v->first_name) ? $v->first_name : $v->display_name;
                    ?>
                    <option value="<?php echo $v->ID; ?>"><?php echo esc_html($nombre . ' (@' . $v->user_login . ')'); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span class="title"><?php _e( 'Asistencia', 'convoca-shifts' ); ?></span>
            <select name="cst_estado_real" class="cst-quick-asistencia">
                <option value="pendiente"><?php _e('⏳ Pendiente', 'convoca-shifts'); ?></option>
                <option value="realizado"><?php _e('✅ Realizado', 'convoca-shifts'); ?></option>
                <option value="no_asistio"><?php _e('❌ No asistió', 'convoca-shifts'); ?></option>
            </select>
        </label>
      </div>
    </fieldset>
    <?php
}

add_action( 'admin_footer', 'cst_quick_edit_javascript' );
function cst_quick_edit_javascript() {
    $current_screen = get_current_screen();
    if ( ! $current_screen || $current_screen->id != 'edit-centro_turno' ) return;
    ?>
    <script type="text/javascript">
    jQuery(function($){
        const wp_inline_edit = inlineEditPost.edit;
        inlineEditPost.edit = function( id ) {
            wp_inline_edit.apply( this, arguments );
            
            const post_id = ( typeof( id ) === 'object' ) ? $( id ).parents( 'tr' ).attr( 'id' ).replace( 'post-', '' ) : id;
            const $row = $( '#post-' + post_id );
            const $edit_row = $( '#edit-' + post_id );
            
            if ( post_id > 0 ) {
                const responsable = $row.find('.cst_id_responsable').text();
                const asistencia = $row.find('.cst_estado_real').text();
                
                // Fill our custom selects
                $edit_row.find( 'select[name="cst_id_responsable"]' ).val( responsable );
                $edit_row.find( 'select[name="cst_estado_real"]' ).val( asistencia );

                // FIX STATUS SELECT: Ensure 'Published' is visible and selected
                const $statusSelect = $edit_row.find( 'select[name="_status"]' );
                if ( $statusSelect.length ) {
                    // If the post is published, WP shows "Published" as selected.
                    // If it's "Scheduled" (future), we force it to "Published"
                    if ( $statusSelect.val() === 'future' ) {
                        $statusSelect.val( 'publish' );
                    }
                }
            }
        };
    });
    </script>
    <?php
}

// Reuse the existing cst_save_turno_meta logic but ensure it works with Quick Edit
// Quick edit doesn't send the same nonce as the full editor, so we need to check for Quick Edit action.
add_action('save_post_centro_turno', 'cst_save_turno_quick_edit', 10, 2);
function cst_save_turno_quick_edit($post_id, $post) {
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( ! current_user_can('edit_post', $post_id) ) return;
    
    // Quick edit uses 'inline-save' action and check_admin_referer('inlineeditnonce', '_inline_edit')
    if ( ! isset($_POST['action']) || $_POST['action'] !== 'inline-save' ) return;

    if ( isset($_POST['cst_id_responsable']) ) {
        $new_id = (int) $_POST['cst_id_responsable'];
        $old_id = (int) get_post_meta($post_id, '_id_responsable', true);
        
        if ($new_id !== $old_id) {
            update_post_meta($post_id, '_id_responsable', $new_id);
            
            // Sync internal state meta
            $current_state = get_post_meta($post_id, '_estado', true);
            if ($current_state !== 'cerrado' && $current_state !== 'abierto_ocupado') {
                update_post_meta($post_id, '_estado', 'abierto_disponible');
            }
            
            update_post_meta($post_id, '_estado_real', 'pendiente');
            
            // Log activity
            if ( function_exists( 'cst_log_activity' ) ) {
                $action = ($new_id > 0) ? 'turno_asignado' : 'turno_desasignado';
                cst_log_activity( get_current_user_id(), $post_id, $action, array('voluntario_id' => $new_id) );
            }
        }
    }

    if ( isset($_POST['cst_estado_real']) ) {
        $new_status = sanitize_text_field($_POST['cst_estado_real']);
        $old_status = get_post_meta($post_id, '_estado_real', true);
        if ($new_status !== $old_status) {
            update_post_meta($post_id, '_estado_real', $new_status);
            
            // Sync hours if applicable
            $id_responsable = (int) get_post_meta( $post_id , '_id_responsable' , true );
            if (class_exists('\\Biodevas\\CentroSocialTurnos\\Hour_Sync')) {
                \Convoca\Shifts\Hour_Sync::sync_hours_to_volunteer_global($post_id, $id_responsable, $new_status);
            }

            // Log activity
            if ( function_exists( 'cst_log_activity' ) ) {
                $action = ($new_status === 'realizado') ? 'asistencia_ok' : 'asistencia_no';
                cst_log_activity( get_current_user_id(), $post_id, $action );
            }
        }
    }
}

// --- Exportador CSV ---
add_action( 'admin_init', 'cst_exportar_csv_turnos' );
function cst_exportar_csv_turnos() {
    if ( isset( $_POST['cst_action'] ) && $_POST['cst_action'] === 'exportar_csv' && check_admin_referer( 'cst_exportar_csv_action' ) ) {
        if ( ! current_user_can( 'cst_manage_turnos' ) ) return;

        $filename = 'turnos-centro-social-' . wp_date( 'Y-m-d' ) . '.csv';

        // Set headers for CSV download
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );
        // Add UTF-8 BOM for Excel compatibility
        fprintf( $output, chr(0xEF).chr(0xBB).chr(0xBF) );
        fputcsv( $output, array( 'ID', 'Título', 'Fecha Inicio', 'Fecha Fin', 'Estado', 'Responsable', 'Necesita Apoyo', 'Asistencia', 'Notas' ) );

        $args = array(
            'post_type'      => 'centro_turno',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC'
        );
        
        $turnos = get_posts( $args );

        foreach ( $turnos as $turno ) {
            $estado = get_post_meta( $turno->ID, '_estado', true );
            $hora_fin = get_post_meta( $turno->ID, '_hora_fin', true );
            $id_responsable = get_post_meta( $turno->ID, '_id_responsable', true );
            $necesita_apoyo = get_post_meta( $turno->ID, '_necesita_apoyo', true ) ? 'Sí' : 'No';
            $notas = get_post_meta( $turno->ID, '_notas', true );

            $responsable_nombre = 'Nadie';
            if ( $id_responsable ) {
                $user = get_userdata( $id_responsable );
                if ( $user ) {
                    $responsable_nombre = $user->display_name . ' (' . $user->user_email . ')';
                }
            }
            $estado_real = get_post_meta( $turno->ID, '_estado_real', true );
            if (!$estado_real) $estado_real = 'pendiente';

            fputcsv( $output, array(
                $turno->ID,
                $turno->post_title,
                get_the_date( 'Y-m-d H:i', $turno ),
                $hora_fin,
                $estado,
                $responsable_nombre,
                $necesita_apoyo,
                $estado_real,
                $notas
            ) );
        }

        fclose( $output );
        exit;
    }
}

// --- Generador de turnos ---

add_action( 'admin_menu', 'cst_add_generar_turnos_menu', 2 );
function cst_add_generar_turnos_menu() {
    add_submenu_page(
        'edit.php?post_type=centro_turno',
        __( 'Generar Turnos', 'convoca-shifts' ),
        __( 'Generar Turnos', 'convoca-shifts' ),
        'cst_manage_turnos',
        'cst_generar_turnos',
        'cst_generar_turnos_page'
    );
}

function cst_generar_turnos_page() {
    if ( isset( $_POST['cst_action'] ) && check_admin_referer( 'cst_generar_turnos_action' ) ) {
        if ( $_POST['cst_action'] === 'duplicar_semana' ) {
            $result = cst_duplicar_semana();
            if ( is_wp_error( $result ) ) {
                echo '<div class="biodevas-alert biodevas-alert--danger" style="display:block;margin-bottom:20px;"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
            } else {
                echo '<div class="biodevas-alert biodevas-alert--success" style="display:block;margin-bottom:20px;"><p>' . sprintf( __( 'Se han duplicado %d turnos.', 'convoca-shifts' ), $result ) . '</p></div>';
            }
        } elseif ( $_POST['cst_action'] === 'generar_semana' ) {
            $result = cst_crear_semana_tipo();
            if ( is_wp_error( $result ) ) {
                echo '<div class="biodevas-alert biodevas-alert--danger" style="display:block;margin-bottom:20px;"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
            } else {
                echo '<div class="biodevas-alert biodevas-alert--success" style="display:block;margin-bottom:20px;"><p>' . sprintf( __( 'Se han generado %d turnos nuevos.', 'convoca-shifts' ), $result ) . '</p></div>';
            }
        }
    }
    
    ?>
    <div class="wrap">
        <h1><?php _e( 'Generador de Turnos', 'convoca-shifts' ); ?></h1>
        
        <div class="card" style="max-width: 600px; margin-top: 20px;">
            <h2><?php _e( 'Duplicar semana anterior', 'convoca-shifts' ); ?></h2>
            <p><?php _e( 'Copia todos los turnos de los últimos 7 días y los reprograma exactamente una semana después, dejándolos pendientes.', 'convoca-shifts' ); ?></p>
            <form method="post" action="">
                <?php wp_nonce_field( 'cst_generar_turnos_action' ); ?>
                <input type="hidden" name="cst_action" value="duplicar_semana">
                <button type="submit" class="biodevas-btn biodevas-btn-primary"><?php _e( 'Duplicar Semana Anterior', 'convoca-shifts' ); ?></button>
            </form>
        </div>

        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2><?php _e( 'Crear semana tipo', 'convoca-shifts' ); ?></h2>
            <form method="post" action="">
                <?php wp_nonce_field( 'cst_generar_turnos_action' ); ?>
                <input type="hidden" name="cst_action" value="generar_semana">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="fecha_inicio"><?php _e( 'Fecha del Lunes', 'convoca-shifts' ); ?></label></th>
                        <td><input type="date" id="fecha_inicio" name="fecha_inicio" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e( 'Configuración diaria', 'convoca-shifts' ); ?></th>
                        <td>
                            <?php
                            $dias_semana = array( 0 => 'Lunes', 1 => 'Martes', 2 => 'Miércoles', 3 => 'Jueves', 4 => 'Viernes', 5 => 'Sábado', 6 => 'Domingo' );
                            foreach ( $dias_semana as $index => $nombre ) {
                                echo '<div style="margin-bottom: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; display: flex; flex-direction: column; gap: 5px;">';
                                echo '<label><input type="checkbox" name="dias[' . $index . '][activo]" value="1"> <strong>' . $nombre . '</strong></label>';
                                echo '<div style="margin-left: 20px; display: flex; align-items: center; gap: 10px;">';
                                echo '<input type="time" name="dias[' . $index . '][hora_inicio]" placeholder="17:00">';
                                echo '<span>a</span>';
                                echo '<input type="time" name="dias[' . $index . '][hora_fin]" placeholder="21:00">';
                                echo '<select name="dias[' . $index . '][estado]" class="cst-estado-selector">';
                                echo '<option value="abierto_disponible">🟡 Pendiente (Disponible)</option>';
                                echo '<option value="abierto_ocupado">🔵 Ocupado (Actividad)</option>';
                                echo '<option value="cerrado">🔴 Cerrado</option>';
                                echo '</select>';
                                echo '<div class="cst-ocupado-extra" style="display:none; flex-direction: column; gap: 5px; margin-top: 5px; background: #fff; padding: 5px; border: 1px solid #eee;">';
                                wp_dropdown_categories(array(
                                    'show_option_none' => __('— Actividad —', 'convoca-shifts'),
                                    'taxonomy'         => 'cst_actividad',
                                    'name'             => 'dias[' . $index . '][actividad_id]',
                                    'orderby'          => 'name',
                                    'hierarchical'     => true,
                                    'hide_empty'       => false,
                                ));
                                wp_dropdown_categories(array(
                                    'show_option_none' => __('— Monitor/a —', 'convoca-shifts'),
                                    'id'               => 'cst_monitor_select',
                                    'name'             => 'dias[' . $index . '][monitor_id]',
                                    'orderby'          => 'name',
                                    'hierarchical'     => true,
                                    'hide_empty'       => false,
                                ));
                                echo '</div>';
                                echo '<label><input type="checkbox" name="dias[' . $index . '][apoyo]" value="1"> 🛟 Necesita apoyo</label>';
                                echo '</div></div>';
                            }
                            ?>
                        </td>
                    </tr>
                </table>
                <script>
                jQuery(document).ready(function($){
                    $('.cst-estado-selector').on('change', function(){
                        var $extra = $(this).siblings('.cst-ocupado-extra');
                        if ($(this).val() === 'abierto_ocupado') {
                            $extra.css('display', 'flex');
                        } else {
                            $extra.hide();
                        }
                    });
                });
                </script>
                <p class="submit">
                    <button type="submit" class="biodevas-btn biodevas-btn-primary"><?php _e( 'Generar Semana', 'convoca-shifts' ); ?></button>
                </p>
            </form>
        </div>

        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2><?php _e( 'Exportar datos (Copias de Seguridad / Estadísticas)', 'convoca-shifts' ); ?></h2>
            <p><?php _e( 'Descarga un archivo CSV con el historial completo de turnos, estados y voluntarios asignados. Ideal para guardar estadísticas antes de desinstalar el plugin o limpiar el calendario.', 'convoca-shifts' ); ?></p>
            <form method="post" action="">
                <?php wp_nonce_field( 'cst_exportar_csv_action' ); ?>
                <input type="hidden" name="cst_action" value="exportar_csv">
                <button type="submit" class="biodevas-btn biodevas-btn-outline"><?php _e( 'Exportar historial a CSV', 'convoca-shifts' ); ?></button>
            </form>
        </div>
    </div>
    <?php
}

function cst_duplicar_semana() {
    // Lock to prevent concurrent executions
    if (!\Convoca\Core\Utils::acquire_lock('cst_duplicating_week', 60)) {
        return 0;
    }

    try {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        $now = time();
    $start_last_week = $now - ( 7 * DAY_IN_SECONDS );
    $end_last_week = $now;
    
    $args = array(
        'post_type'      => 'centro_turno',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'date_query'     => array(
            array(
                'after'     => wp_date( 'Y-m-d 00:00:00', $start_last_week ),
                'before'    => wp_date( 'Y-m-d 23:59:59', $end_last_week ),
                'inclusive' => true,
            ),
        ),
    );
    
    $turnos = get_posts( $args );
    $count = 0;
    
    foreach ( $turnos as $turno ) {
        $old_date_ts = get_post_timestamp( $turno );
        $new_date_ts = $old_date_ts + ( 7 * DAY_IN_SECONDS );
        
        $old_hora_fin_val = get_post_meta( $turno->ID, '_hora_fin', true );

        $h_start = wp_date( 'H:i', $old_date_ts );
        $h_end = wp_date( 'H:i', strtotime( $old_hora_fin_val ) );

        // Business hours enforcement
        $limit_open = get_option('cst_hora_apertura', '09:00');
        $limit_close = get_option('cst_hora_cierre', '22:00');

        if ( $h_start < $limit_open ) $h_start = $limit_open;
        if ( $h_end > $limit_close ) $h_end = $limit_close;
        if ( $h_start >= $h_end ) {
            $h_start = $limit_open;
            $h_end = $limit_close;
        }

        $original_estado = get_post_meta( $turno->ID, '_estado', true );
        $actividad_ids = wp_get_post_terms($turno->ID, 'cst_actividad', array('fields' => 'ids'));
        $monitor_ids = (int) get_post_meta($turno->ID, '_monitor', true) ? [(int) get_post_meta($turno->ID, '_monitor', true)] : [];
        $apoyo = get_post_meta( $turno->ID, '_necesita_apoyo', true );

        // Prevent duplicate shifts: skip if a turno already exists on the target date+time
        $target_date = wp_date( 'Y-m-d', $new_date_ts );
        $existing = get_posts([
            'post_type' => 'centro_turno',
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_hora_fin',
                    'value' => $target_date . ' ' . $h_end . ':00',
                    'compare' => '=',
                ],
            ],
            'date_query' => [
                [
                    'after' => $target_date . ' 00:00:00',
                    'before' => $target_date . ' 23:59:59',
                    'inclusive' => true,
                ],
            ],
            'posts_per_page' => 1,
            'fields' => 'ids',
        ]);
        if (!empty($existing)) continue;

        $new_post_id = cst_insert_turno( array(
            'date'           => wp_date( 'Y-m-d', $new_date_ts ),
            'h_start'        => $h_start,
            'h_end'          => $h_end,
            'estado'         => $original_estado,
            'necesita_apoyo' => $apoyo,
            'actividad_id'   => !empty($actividad_ids) ? $actividad_ids[0] : 0,
            'monitor_id'     => !empty($monitor_ids) ? $monitor_ids[0] : 0,
        ) );
        
        if ( ! is_wp_error( $new_post_id ) ) {
            // Additional meta not handled by insert_turno
            update_post_meta( $new_post_id, '_notas', get_post_meta( $turno->ID, '_notas', true ) );

            // Log activity
            if ( function_exists( 'cst_log_activity' ) ) {
                cst_log_activity( get_current_user_id(), $new_post_id, 'turno_creado', array('origen' => 'duplicar_semana') );
            }
            $count++;
        }
    }
    
    $wpdb->query('COMMIT');
    delete_transient( 'cst_resumen_turnos_semana' );
        return $count;
    } catch (\Throwable $e) {
        $wpdb->query('ROLLBACK');
        throw $e;
    } finally {
        \Convoca\Core\Utils::release_lock('cst_duplicating_week');
    }
}

function cst_crear_semana_tipo( $fecha_inicio, $dias ) {
    if ( empty( $fecha_inicio ) ) return new WP_Error( 'no_date', 'Fecha de inicio requerida.' );

    // Lock to prevent concurrent week generation
    if (!\Convoca\Core\Utils::acquire_lock('cst_creating_week', 120)) {
        return 0;
    }

    try {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        $start_timestamp = strtotime( $fecha_inicio );
    if (!$start_timestamp) return new WP_Error( 'invalid_date', 'Fecha de inicio inválida.' );
    
    $count = 0;
    
    foreach ( $dias as $offset => $conf ) {
        if ( ! isset( $conf['activo'] ) || $conf['activo'] != '1' ) continue;
        
        $hora_inicio = !empty($conf['hora_inicio']) ? sanitize_text_field($conf['hora_inicio']) : '17:00';
        $hora_fin = !empty($conf['hora_fin']) ? sanitize_text_field($conf['hora_fin']) : '21:00';
        $estado = !empty($conf['estado']) ? sanitize_text_field($conf['estado']) : 'abierto_disponible';
        $apoyo = !empty($conf['apoyo']) ? 1 : 0;

        // Basic time format validation (HH:MM)
        if ( !preg_match('/^\d{2}:\d{2}$/', $hora_inicio) ) $hora_inicio = '17:00';
        if ( !preg_match('/^\d{2}:\d{2}$/', $hora_fin) ) $hora_fin = '21:00';
        
        $dia_timestamp = $start_timestamp + ( $offset * DAY_IN_SECONDS );
        $date_day = wp_date( 'Y-m-d', $dia_timestamp );
        $post_date = $date_day . ' ' . $hora_inicio . ':00';
        $meta_hora_fin = $date_day . ' ' . $hora_fin . ':00';

        // Business hours enforcement
        $limit_open = get_option('cst_hora_apertura', '09:00');
        $limit_close = get_option('cst_hora_cierre', '22:00');

        if ( $hora_inicio < $limit_open ) $hora_inicio = $limit_open;
        if ( $hora_fin > $limit_close ) $hora_fin = $limit_close;
        if ( $hora_inicio >= $hora_fin ) {
            $hora_inicio = $limit_open;
            $hora_fin = $limit_close;
        }

        $actividad_id = !empty($conf['actividad_id']) ? (int) $conf['actividad_id'] : 0;
        $monitor_id = !empty($conf['monitor_id']) ? (int) $conf['monitor_id'] : 0;

        // Prevent duplicate shifts on same date+time
        $existing = get_posts([
            'post_type' => 'centro_turno',
            'post_status' => 'publish',
            'meta_query' => [[
                'key' => '_hora_fin',
                'value' => $meta_hora_fin,
                'compare' => '=',
            ]],
            'date_query' => [[
                'after' => $date_day . ' 00:00:00',
                'before' => $date_day . ' 23:59:59',
                'inclusive' => true,
            ]],
            'posts_per_page' => 1,
            'fields' => 'ids',
        ]);
        if (!empty($existing)) continue;

        $new_post_id = cst_insert_turno( array(
            'date'           => $date_day,
            'h_start'        => $hora_inicio,
            'h_end'          => $hora_fin,
            'estado'         => $estado,
            'necesita_apoyo' => $apoyo,
            'actividad_id'   => $actividad_id,
            'monitor_id'     => $monitor_id,
        ) );
        
        if ( ! is_wp_error( $new_post_id ) ) {
            // Log activity
            if ( function_exists( 'cst_log_activity' ) ) {
                cst_log_activity( get_current_user_id(), $new_post_id, 'turno_creado', array('origen' => 'generador_semana') );
            }
            $count++;
        }
    }
    $wpdb->query('COMMIT');
    delete_transient( 'cst_resumen_turnos_semana' );
        return $count;
    } catch (\Throwable $e) {
        $wpdb->query('ROLLBACK');
        throw $e;
    } finally {
        \Convoca\Core\Utils::release_lock('cst_creating_week');
    }
}

/**
 * Actividad Taxonomy Meta Fields
 */
function cst_actividad_add_meta_fields() {
    wp_nonce_field( 'cst_actividad_meta_nonce', 'cst_actividad_meta_nonce_field' );
    ?>
    <div class="form-field">
        <label for="cst_url_info"><?php _e( 'URL de Información', 'convoca-shifts' ); ?></label>
        <input type="url" name="cst_url_info" id="cst_url_info" value="">
        <p><?php _e( 'Enlace a la entrada del blog o página con más información e inscripciones.', 'convoca-shifts' ); ?></p>
    </div>
    <?php
}

function cst_actividad_edit_meta_fields( $term ) {
    wp_nonce_field( 'cst_actividad_meta_nonce', 'cst_actividad_meta_nonce_field' );
    $url = get_term_meta( $term->term_id, 'cst_url_info', true );
    ?>
    <tr class="form-field">
        <th scope="row" valign="top"><label for="cst_url_info"><?php _e( 'URL de Información', 'convoca-shifts' ); ?></label></th>
        <td>
            <input type="url" name="cst_url_info" id="cst_url_info" value="<?php echo esc_url( $url ); ?>">
            <p class="description"><?php _e( 'Enlace a la entrada del blog o página con más información e inscripciones.', 'convoca-shifts' ); ?></p>
        </td>
    </tr>
    <?php
}

function cst_save_actividad_meta( $term_id ) {
    if ( ! isset( $_POST['cst_actividad_meta_nonce_field'] ) || ! wp_verify_nonce( $_POST['cst_actividad_meta_nonce_field'], 'cst_actividad_meta_nonce' ) ) {
        return;
    }
    if ( ! current_user_can( 'manage_categories' ) ) {
        return;
    }

    if ( isset( $_POST['cst_url_info'] ) ) {
        update_term_meta( $term_id, 'cst_url_info', esc_url_raw( $_POST['cst_url_info'] ) );
    }
}
/**
 * Helper to centralize turno creation logic.
 */
function cst_insert_turno( $args ) {
    $defaults = array(
        'date'           => '',
        'h_start'        => '09:00',
        'h_end'          => '22:00',
        'estado'         => 'abierto_disponible',
        'id_responsable' => 0,
        'necesita_apoyo' => 0,
        'actividad_id'   => 0,
        'monitor_id'     => 0,
    );
    $a = wp_parse_args( $args, $defaults );

    // Enforce business hours
    $limit_open = get_option('cst_hora_apertura', '09:00');
    $limit_close = get_option('cst_hora_cierre', '22:00');
    if ( $a['h_start'] < $limit_open ) $a['h_start'] = $limit_open;
    if ( $a['h_end'] > $limit_close ) $a['h_end'] = $limit_close;
    if ( $a['h_start'] >= $a['h_end'] ) { $a['h_start'] = $limit_open; $a['h_end'] = $limit_close; }

    $post_date = $a['date'] . ' ' . $a['h_start'] . ':00';
    $meta_hora_fin = $a['date'] . ' ' . $a['h_end'] . ':00';

    // Generate Title
    $title = '🟡 Pendiente';
    if ( $a['estado'] === 'cerrado' ) {
        $title = '🔴 Cerrado';
    } elseif ( $a['estado'] === 'abierto_ocupado' ) {
        $title = '🔵 Ocupado';
        if ( $a['actividad_id'] ) {
            $term = get_term( $a['actividad_id'], 'cst_actividad' );
            if ( $term && !is_wp_error($term) ) $title = '🔵 ' . $term->name;
        }
    } elseif ( $a['id_responsable'] > 0 ) {
        $user = get_userdata( $a['id_responsable'] );
        if ( $user ) {
            $nombre = ! empty( $user->first_name ) ? $user->first_name : $user->display_name;
            $title = '🟢 Cubierto - ' . $nombre;
        }
    }

    $post_id = wp_insert_post( array(
        'post_type'    => 'centro_turno',
        'post_title'   => $title,
        'post_status'  => 'publish',
        'post_date'    => $post_date,
    ) );

    if ( ! is_wp_error( $post_id ) ) {
        wp_publish_post( $post_id );
        update_post_meta( $post_id, '_estado', $a['estado'] );
        update_post_meta( $post_id, '_id_responsable', $a['id_responsable'] );
        update_post_meta( $post_id, '_hora_fin', $meta_hora_fin );
        update_post_meta( $post_id, '_necesita_apoyo', $a['necesita_apoyo'] );
        
        if ( $a['actividad_id'] ) {
            wp_set_object_terms( $post_id, (int) $a['actividad_id'], 'cst_actividad' );
        }
        if ( $a['monitor_id'] ) {
            update_post_meta( $post_id, '_monitor', (int) $a['monitor_id'] > 0 ? (int) $a['monitor_id'] : '' );
        }
    }

    return $post_id;
}
/**
 * Check if user has any overlapping shifts.
 * A conflict exists if existing_start < requested_end AND existing_end > requested_start.
 *
 * @param int $user_id User ID to check.
 * @param string|int $start_time Start time of requested shift.
 * @param string|int $end_time End time of requested shift.
 * @param int $exclude_post_id Post ID to exclude from check (usually the current shift).
 * @param bool $for_update Whether to lock rows with FOR UPDATE.
 * @return int|false Conflict post ID or false if no conflict.
 */
function cst_check_user_overlap( $user_id, $start_time, $end_time, $exclude_post_id = 0, $for_update = false ) {
    global $wpdb;
    
    // Normalize to Y-m-d H:i:s without shifting timezones
    try {
        $dt_start = is_numeric($start_time) ? new \DateTime('@' . $start_time) : new \DateTime($start_time);
        $dt_end = is_numeric($end_time) ? new \DateTime('@' . $end_time) : new \DateTime($end_time);
        
        // If it was a timestamp, it's UTC, so we should convert to local if needed, 
        // but here we assume the input string is already local or both are consistent.
        // To be safe, we just use the format that the DB uses.
        $start_str = $dt_start->format('Y-m-d H:i:s');
        $end_str = $dt_end->format('Y-m-d H:i:s');
    } catch (\Exception $e) {
        return 0;
    }

    $for_update_clause = $for_update ? 'FOR UPDATE' : '';

    // Conflict exists if existing_start < requested_end AND existing_end > requested_start
    $sql = $wpdb->prepare(
        "SELECT p.ID 
         FROM {$wpdb->posts} p
         JOIN {$wpdb->postmeta} pm_ini ON p.ID = pm_ini.post_id AND pm_ini.meta_key = '_fecha_inicio'
         JOIN {$wpdb->postmeta} pm_fin ON p.ID = pm_fin.post_id AND pm_fin.meta_key = '_hora_fin'
         JOIN {$wpdb->postmeta} pm_resp ON p.ID = pm_resp.post_id AND pm_resp.meta_key = '_id_responsable'
         LEFT JOIN {$wpdb->postmeta} pm_est ON p.ID = pm_est.post_id AND pm_est.meta_key = '_estado'
         LEFT JOIN {$wpdb->postmeta} pm_real ON p.ID = pm_real.post_id AND pm_real.meta_key = '_estado_real'
         WHERE p.post_type = 'centro_turno' 
           AND p.post_status = 'publish'
           AND pm_resp.meta_value = %d
           AND p.ID != %d
           AND pm_ini.meta_value < %s
           AND pm_fin.meta_value > %s
           AND (pm_est.meta_value IS NULL OR pm_est.meta_value != 'cerrado')
           AND (pm_real.meta_value IS NULL OR pm_real.meta_value != 'no_asistio')
         LIMIT 1
         $for_update_clause",
        $user_id,
        $exclude_post_id,
        $end_str,
        $start_str
    );

    $conflict_id = $wpdb->get_var( $sql );
    
    if ( $conflict_id && function_exists('cst_log_activity') ) {
        // Log the details of the conflict for debugging
        $existing_start = get_post_meta( $conflict_id, '_fecha_inicio', true ) ?: get_post($conflict_id)->post_date;
        $existing_end = get_post_meta($conflict_id, '_hora_fin', true);
        cst_log_activity(get_current_user_id(), $conflict_id, 'log_debug_conflict', [
            'requested_start' => $start_str,
            'requested_end' => $end_str,
            'existing_start' => $existing_start,
            'existing_end' => $existing_end,
            'user_id' => $user_id
        ]);
    }

    return $conflict_id;
}

