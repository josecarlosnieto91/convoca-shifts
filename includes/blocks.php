<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Enqueue block assets — with robust detection.
 *
 * In the editor: always load (SSR preview needs FullCalendar).
 * In the frontend: load if CST blocks/shortcodes are in content, widgets, or forced via filter.
 */
function cst_enqueue_block_assets()
{
    // Always load in editor context (block previews need the scripts).
    if (is_admin()) {
        wp_enqueue_style('cst-estilo');
        wp_enqueue_script('cst-calendario');
        return;
    }

    // Frontend: robust detection.
    if (cst_should_load_assets()) {
        wp_enqueue_style('cst-estilo');
        wp_enqueue_script('cst-calendario');
    }
}
add_action('wp_enqueue_scripts', 'cst_enqueue_block_assets'); // Changed from enqueue_block_assets for better frontend coverage

/**
 * Robust detection of CST presence in current page.
 * Checks: Force filter, Post blocks, Post shortcodes, Widget shortcodes/blocks.
 */
function cst_should_load_assets()
{
    // 1. Force load via filter
    if (apply_filters('cst_force_enqueue_assets', false)) {
        return true;
    }

    $cst_blocks = array(
        'centro-social/calendario',
        'centro-social/boton-apuntarse',
        'centro-social/resumen',
        'centro-social/proximos-turnos',
    );
    $cst_shortcodes = array('calendario_centro', 'boton_apuntarse', 'resumen_turnos', 'proximos_turnos');

    // 2. Check current post content (blocks and shortcodes)
    if (is_singular()) {
        $post = get_post();
        if ($post) {
            // Blocks
            foreach ($cst_blocks as $block_name) {
                if (has_block($block_name, $post))
                    return true;
            }
            // Shortcodes
            foreach ($cst_shortcodes as $sh) {
                if (has_shortcode($post->post_content, $sh))
                    return true;
            }
        }
    }

    // 3. Check active widgets (Text and Custom HTML)
    $sidebars_widgets = wp_get_sidebars_widgets();
    if (is_array($sidebars_widgets)) {
        foreach ($sidebars_widgets as $sidebar_id => $widgets) {
            if ('wp_inactive_widgets' === $sidebar_id || empty($widgets) || !is_array($widgets))
                continue;

            foreach ($widgets as $widget_id) {
                $base = preg_replace("/-\d+$/", "", $widget_id);
                $num_m = []; $number = preg_match("/-(\d+)$/", $widget_id, $num_m) ? (int)$num_m[1] : 0;
                $widget_instances = get_option('widget_' . $base);

                if (isset($widget_instances[$number])) {
                    $instance = $widget_instances[$number];
                    $content = '';

                    if ('text' === $base && isset($instance['text']))
                        $content = $instance['text'];
                    if ('custom_html' === $base && isset($instance['content']))
                        $content = $instance['content'];
                    if ('block' === $base && isset($instance['content']))
                        $content = $instance['content'];

                    if (!empty($content)) {
                        // Shortcodes
                        foreach ($cst_shortcodes as $sh) {
                            if (has_shortcode($content, $sh))
                                return true;
                        }
                        // Blocks (strings)
                        foreach ($cst_blocks as $bn) {
                            if (strpos($content, 'wp:' . $bn) !== false || strpos($content, $bn) !== false)
                                return true;
                        }
                    }
                }
            }
        }
    }

    return false;
}

/**
 * Enqueue block editor specific assets (registration)
 */
function cst_enqueue_block_editor_assets()
{
    wp_enqueue_script(
        'cst-blocks-js',
        CST_PLUGIN_URL . 'assets/js/blocks.js',
        array('wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-block-editor', 'wp-components', 'jquery'),
        CST_PLUGIN_VERSION,
        true
    );
}
add_action('enqueue_block_editor_assets', 'cst_enqueue_block_editor_assets');

/**
 * Register Gutenberg blocks for Centro Social Turnos
 */
function cst_register_blocks()
{

    $common_args = array(
        'apiVersion' => 3,
        'category' => 'biodevas-turnos',
        'editor_script' => 'cst-blocks-js', // This links the JS to the blocks
    );

    // 1. Calendario
    register_block_type('centro-social/calendario', array_merge($common_args, array(
        'render_callback' => 'cst_render_block_calendario',
        'title' => __('CST: Calendario', 'convoca-shifts'),
        'icon' => 'calendar-alt',
    )));

    // 2. Botón Apuntarse
    register_block_type('centro-social/boton-apuntarse', array_merge($common_args, array(
        'render_callback' => 'cst_render_block_boton_apuntarse',
        'title' => __('CST: Botón Apuntarse', 'convoca-shifts'),
        'icon' => 'plus-alt',
    )));

    // 4. Resumen Turnos
    register_block_type('centro-social/resumen', array_merge($common_args, array(
        'render_callback' => 'cst_render_block_resumen',
        'title' => __('CST: Resumen Semanal', 'convoca-shifts'),
        'icon' => 'chart-bar',
    )));

    // 5. Próximos Turnos (Dynamic with attributes)
    register_block_type('centro-social/proximos-turnos', array_merge($common_args, array(
        'attributes' => array(
            'cantidad' => array(
                'type' => 'number',
                'default' => 5,
            ),
        ),
        'render_callback' => 'cst_render_block_proximos_turnos',
        'title' => __('CST: Próximos Turnos', 'convoca-shifts'),
        'icon' => 'list-view',
    )));
}
add_action('init', 'cst_register_blocks');

/**
 * Render Callbacks
 */

function cst_render_block_calendario()
{
    $output = cst_shortcode_calendario_centro();
    // Fail-safe trigger for dynamic loading (Gutenberg/AJAX)
    $output .= '<script>if(window.initCSTCalendar) { window.initCSTCalendar(document); } else if(window.parent && window.parent.initCSTCalendar) { window.parent.initCSTCalendar(document); }</script>';
    return $output;
}

function cst_render_block_boton_apuntarse()
{
    return cst_shortcode_boton_apuntarse();
}

function cst_render_block_resumen()
{
    return cst_shortcode_resumen_turnos();
}

function cst_render_block_proximos_turnos($attributes)
{
    return cst_shortcode_proximos_turnos($attributes);
}
