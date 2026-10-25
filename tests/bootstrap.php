<?php
/**
 * Bootstrap for convoca-shifts unit tests.
 * Provides comprehensive WordPress function stubs plus Convoca Core stubs.
 */

namespace Convoca\Core {

    if (!class_exists('Utils')) {
        class Utils {
            public static $actions_fired = [];
            public static function do_action(string $native_hook, string $backcompat_hook, ...$args): void {
                self::$actions_fired[] = ['hook' => $native_hook, 'callback' => $backcompat_hook, 'args' => $args];
                if (\function_exists('\\do_action')) {
                    \do_action($native_hook, ...$args);
                }
            }
            public static function format_date(string $modify, string $format = 'Y-m-d'): string {
                return \date($format, \strtotime($modify));
            }
            // Bloqueos del nucleo: sin esto, cualquier flujo que use acquire_lock()
            // (p. ej. el recordatorio del cron) revienta con "undefined method".
            public static function acquire_lock(string $key, int $ttl = 60): bool {
                // La prueba puede decir que el cerrojo está ocupado por otro proceso.
                if ( isset( $GLOBALS['_sh_lock'] ) && false === $GLOBALS['_sh_lock'] ) {
                    $GLOBALS['_sh_locks_denegados'][] = $key;

                    return false;
                }

                $GLOBALS['_wp_stores']['locks'][$key] = \time() + $ttl;
                $GLOBALS['_sh_locks'][]               = $key;

                return true;
            }
            public static function release_lock(string $key): void {
                unset($GLOBALS['_wp_stores']['locks'][$key]);
            }
            /**
             * Límite de peticiones: por defecto pasa, y la prueba puede cerrarlo.
             *
             * El producto pregunta por este método cuando la clase existe; si el doble no lo tuviera,
             * cualquier endpoint de la API reventaría al llamarlo (y ninguno se podía probar).
             */
            /** ¿Está activo ese plugin? (lista blanca de la prueba; por defecto todos). */
            public static function is_plugin_active_safe(string $plugin): bool {
                $activos = $GLOBALS['_sh_plugins_activos'] ?? null;
                return null === $activos ? true : \in_array($plugin, (array) $activos, true);
            }
            public static function check_rate_limit(string $action, int $max = 10, int $window = 300, string $identity = ''): bool {
                $GLOBALS['_sh_rate_limit_consultas'][] = $action;
                return (bool) ($GLOBALS['_sh_rate_limit'] ?? true);
            }
            public static function clear_fired(): void { self::$actions_fired = []; }
        }
    }

    if (!class_exists('Logger')) {
        class Logger {
            public static $logs = [];
            public static function info(string $msg, string $context = '', int $oid = 0): void {
                self::$logs[] = ['level' => 'info', 'msg' => $msg, 'context' => $context];
            }
            public static function warning(string $msg, string $context = '', int $oid = 0): void {
                self::$logs[] = ['level' => 'warning', 'msg' => $msg, 'context' => $context];
            }
            public static function error(string $msg, string $context = '', int $oid = 0): void {
                self::$logs[] = ['level' => 'error', 'msg' => $msg, 'context' => $context];
            }
            public static function clear(): void { self::$logs = []; }
            public static function get_logs(): array { return self::$logs; }
        }
    }

    if (!class_exists('Upgrade_Manager')) {
        class Upgrade_Manager {
            protected function get_db_version(): string { return '1.0.0'; }
            protected function get_option_key(): string { return 'convoca_db_version'; }
            protected function get_upgrades(): array { return []; }
            public function run(): void {}
            public function get_version(): string { return $this->get_db_version(); }
        }
    }
}

namespace Convoca\Members {

    if (!class_exists('CPT_Registro_Hora')) {
        class CPT_Registro_Hora {}
    }
}

namespace {

    \define('WP_DEBUG', true);
    // `ABSPATH` apunta a un WordPress DE MENTIRA con `wp-admin/includes/upgrade.php`: el plugin lo exige
    // antes de `dbDelta()`, y sin él cualquier camino que cree tablas revienta en la suite.
    \define('ABSPATH', \dirname(__DIR__) . '/tests/fixtures/wp/');
    \define('OBJECT', 'OBJECT');
    if (!\defined('DAY_IN_SECONDS')) { \define('DAY_IN_SECONDS', 86400); }
    if (!\defined('HOUR_IN_SECONDS')) { \define('HOUR_IN_SECONDS', 3600); }

    $GLOBALS['_wp_stores'] = [
        'options'     => [],
        'post_meta'   => [],
        'transients'  => [],
        'user_meta'   => [],
        'test_posts'  => [],
        'emails'      => [],
    ];

    if (!\function_exists('get_option')) {
        function get_option($key, $default = false) {
            // Sin ninguna opción guardada, el almacén no existe: antes esto reventaba con un TypeError
            // y por eso media docena de funciones del plugin no se podían ni llamar desde una prueba.
            $s = $GLOBALS['_wp_stores']['options'] ?? array();
            return \array_key_exists($key, $s) ? $s[$key] : $default;
        }
        function update_option($key, $value, $autoload = null) {
            $GLOBALS['_wp_stores']['options'][$key] = $value;
            return true;
        }
        function wp_delete_post($id, $force = false) {
            $GLOBALS['_sh_borrados'][] = array('tipo' => 'post', 'id' => (int) $id, 'force' => (bool) $force);
            return true;
        }
        function get_terms($args = array()) {
            return $GLOBALS['_sh_terms'] ?? array();
        }
        function wp_delete_term($term_id, $taxonomy) {
            $GLOBALS['_sh_borrados'][] = array('tipo' => 'term', 'id' => (int) $term_id, 'tax' => $taxonomy);
            return true;
        }
        function wp_clear_scheduled_hook($hook, $args = array()) {
            $GLOBALS['_sh_hooks_limpiados'][] = $hook;
            return true;
        }
        function delete_option($key) {
            unset($GLOBALS['_wp_stores']['options'][$key]);
            return true;
        }
    }

    if (!\function_exists('get_post_meta')) {
        function get_post_meta($id, $key, $single = false) {
            $s = &$GLOBALS['_wp_stores']['post_meta'];
            $v = $s[$id][$key] ?? null;
            if ($v === null) return $single ? '' : [];
            if ($single) return $v;
            return \is_array($v) ? $v : [$v];
        }
        function update_post_meta($id, $key, $value) {
            $GLOBALS['_wp_stores']['post_meta'][$id][$key] = $value;
            return true;
        }
        function delete_post_meta($id, $key) {
            unset($GLOBALS['_wp_stores']['post_meta'][$id][$key]);
            return true;
        }
    }

    if (!\function_exists('get_userdata')) {
        function get_userdata($id) {
            if ($id <= 0) return false;
            // Si la prueba ha puesto la persona en el almacén, manda el almacén: es la única forma de
            // probar una pantalla que pinta nombres y correos.
            if (array_key_exists( (int) $id, $GLOBALS['_wp_stores']['usuarios'] ?? array() )) {
                return $GLOBALS['_wp_stores']['usuarios'][ (int) $id ];
            }
            $u = new \stdClass();
            $u->ID = $id;
            $u->display_name = "User $id";
            $u->first_name = "First$id";
            $u->user_email = "user$id@example.com";
            $u->roles = ['voluntario_aprobado', 'monitor_actividad'];
            $u->user_login = "user$id";
            return $u;
        }
    }

    if (!\function_exists('get_user_meta')) {
        function get_user_meta($id, $key, $single = false) {
            // Puede no haber ninguna meta de usuario guardada: `?? []` evita el aviso y el TypeError.
            $s = $GLOBALS['_wp_stores']['user_meta'] ?? array();
            $v = $s[$id][$key] ?? '';
            return $single ? $v : ($v !== '' ? [$v] : []);
        }
        function update_user_meta($id, $key, $value) {
            $GLOBALS['_wp_stores']['user_meta'][$id][$key] = $value;
            return true;
        }
        function delete_user_meta($id, $key) {
            unset($GLOBALS['_wp_stores']['user_meta'][$id][$key]);
            return true;
        }
    }

    if (!\function_exists('wp_mail')) {
        function wp_mail($to, $subject, $message, $headers = '', $attachments = []) {
            $GLOBALS['_wp_stores']['emails'][] = [
                'to'      => $to,
                'subject' => $subject,
                'message' => $message,
            ];
            return true;
        }
    }

    if (!\function_exists('current_time')) {
        function current_time($type = 'mysql') {
            if ($type === 'mysql') return \date('Y-m-d H:i:s');
            if ($type === 'Y-m-d') return \date('Y-m-d');
            if ($type === 'timestamp') return \time();
            return \date($type);
        }
    }

    if (!\function_exists('wp_date')) {
        function wp_date($format, $ts = null) { return \date($format, $ts ?? \time()); }
    }

    if (!\function_exists('get_the_title')) {
        // Dentro de un bucle de WP_Query se llama get_the_title() sin argumentos:
        // debe devolver el titulo de la entrada actual, no exigir el id.
        function get_the_title($post = 0) {
            if ($post) {
                // Si la prueba ha sembrado la entrada, manda su título: es lo que se pinta en la pestaña.
                $id = \is_object($post) ? (int) ($post->ID ?? 0) : (int) $post;
                foreach (array('posts', 'test_posts') as $almacen) {
                    $sembrada = $GLOBALS['_wp_stores'][$almacen][$id] ?? null;
                    if ($sembrada && isset($sembrada->post_title)) { return (string) $sembrada->post_title; }
                }
                return "Title #$post";
            }
            $cur = $GLOBALS['convoca_test_current_post'] ?? null;
            return ($cur && isset($cur->post_title)) ? $cur->post_title : '';
        }
    }

    if (!\function_exists('get_post')) {
        function get_post($id = null) {
            // Sin argumentos, WordPress devuelve la entrada del bucle (el global `$post`): una detección
            // que mira «la entrada actual» se quedaba en null y no veía nada nunca.
            if ($id === null) { return $GLOBALS['post'] ?? null; }
            // WordPress no devuelve entrada para un identificador no positivo: el arnés inventaba una
            // («Post 0 — Default Title») y eso hacía creer que el detalle de un registro tenía título.
            if ((int) $id <= 0) return null;
            // `posts` es el almacén que usa `get_posts`; `test_posts` se conserva por compatibilidad.
            $posts = &$GLOBALS['_wp_stores']['posts'];
            if (isset($posts[$id])) return $posts[$id];
            $viejos = &$GLOBALS['_wp_stores']['test_posts'];
            if (isset($viejos[$id])) return $viejos[$id];
            if (isset($GLOBALS['_wp_stores']['no_existe'][(int) $id])) return null;
            $p = new \stdClass();
            $p->ID = $id;
            $p->post_title = "Post $id — Default Title";
            $p->post_type = 'centro_turno';
            $p->post_status = 'publish';
            $p->post_date = '2026-06-15 10:00:00';
            return $p;
        }
    }

    if (!\function_exists('get_post_status')) {
        function get_post_status($id) { return 'publish'; }
    }

    if (!\function_exists('wp_insert_post')) {
        static $wp_insert_counter = 500;
        function wp_insert_post($data, $wp_error = false) {
            // Como WordPress: sin `$wp_error` un fallo devuelve 0; con `$wp_error`, un WP_Error.
            if (!empty($GLOBALS['_sh_insert_error'])) {
                return $wp_error ? $GLOBALS['_sh_insert_error'] : 0;
            }
            global $wp_insert_counter;
            // El id de un alta NUNCA pisa uno existente: si el contador va por detrás de lo sembrado,
            // la prueba acaba leyendo el post que puso ella y da por bueno lo que no se escribió.
            $sembrados = array_keys($GLOBALS['_wp_stores']['posts'] ?? []);
            $maximo    = $sembrados ? \max($sembrados) : 0;
            if ((int) $wp_insert_counter <= (int) $maximo) {
                $wp_insert_counter = (int) $maximo;
            }
            $wp_insert_counter++;
            $GLOBALS['_wp_stores']['insertados'][] = $data;
            update_post_meta($wp_insert_counter, '_wp_insert_data', $data);

            // El post creado entra en el almacén: si no, una consulta posterior no lo ve y un flujo que
            // importa dos veces la misma fila se cree que solo ha creado una.
            $GLOBALS['_wp_stores']['posts'][ $wp_insert_counter ] = (object) array(
                'ID'          => $wp_insert_counter,
                'post_type'   => $data['post_type'] ?? 'post',
                'post_status' => $data['post_status'] ?? 'draft',
                'post_title'  => $data['post_title'] ?? '',
                'post_date'   => $data['post_date'] ?? '',
            );

            return $wp_insert_counter;
        }
    }

    if (!\function_exists('wp_update_post')) {
        function wp_update_post($data) {
            if (isset($data['ID'])) {
                update_post_meta($data['ID'], '_wp_update_data', $data);
                return $data['ID'];
            }
            return wp_insert_post($data);
        }
    }

    if (!\function_exists('current_user_can')) {
        function current_user_can($cap, ...$a) { return $GLOBALS['_test_caps'][$cap] ?? true; }
    }

    if (!\function_exists('get_current_user_id')) {
        function get_current_user_id() { return 1; }
    }

    if (!\function_exists('wp_verify_nonce')) {
        function wp_verify_nonce($n, $a) { return (bool) ($GLOBALS['_sh_nonce_ok'] ?? true); }
    }

    if (!\function_exists('__')) { function __($s, $d = 'default') { return $s; } }
    if (!\function_exists('_x')) { function _x($s, $c, $d = 'default') { return $s; } }
    if (!\function_exists('esc_html__')) { function esc_html__($s, $d = 'default') { return $s; } }
    if (!\function_exists('esc_attr__')) { function esc_attr__($s, $d = 'default') { return $s; } }
    if (!\function_exists('esc_attr_e')) { function esc_attr_e($s, $d = 'default') { echo \esc_attr($s); } }
    if (!\function_exists('esc_html')) { function esc_html($s) { return \htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
    if (!\function_exists('esc_attr')) { function esc_attr($s) { return \htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
    if (!\function_exists('esc_url')) { function esc_url($s) { return $s; } }
    if (!\function_exists('sanitize_text_field')) { function sanitize_text_field($s) { return \is_string($s) ? \trim($s) : ''; } }
    if (!\function_exists('sanitize_textarea_field')) { function sanitize_textarea_field($s) { return \is_string($s) ? \trim($s) : ''; } }
    if (!\function_exists('absint')) { function absint($v) { return \abs((int)$v); } }
    if (!\function_exists('wp_unslash')) { function wp_unslash($s) { return \is_string($s) ? \stripslashes($s) : $s; } }
    if (!\function_exists('wp_kses_post')) { function wp_kses_post($s) { return $s; } }
    if (!\function_exists('home_url')) { function home_url($p = '') { return "https://example.com$p"; } }
    if (!\function_exists('admin_url')) { function admin_url($p = '') { return "/wp-admin/$p"; } }

    if (!\function_exists('do_action')) {
        function do_action($hook, ...$args) {
            \Convoca\Core\Utils::$actions_fired[] = ['hook' => $hook, 'args' => $args];
        }
    }

    // Funciones de WordPress que usa el ARRANQUE del plugin.
    if (!\function_exists('get_role')) {
        /** Rol del sitio: se lee del almacén de la prueba. Sin él, el aviso de rol del arranque revienta. */
        function get_role($rol) {
            return $GLOBALS['_wp_stores']['roles'][ $rol ] ?? null;
        }
    }
    if (!\function_exists('wp_roles')) {
        /** Contenedor de roles. */
        function wp_roles() {
            return (object) array( 'roles' => (array) ($GLOBALS['_wp_stores']['roles'] ?? array()) );
        }
    }
    if (!\function_exists('is_admin')) { function is_admin() { return (bool) ($GLOBALS['convoca_test_is_admin'] ?? false); } }
    if (!\function_exists('get_file_data')) {
        function get_file_data($file, $headers = array(), $context = '') {
            unset($context);
            $salida = array();
            if (isset($headers['Version'])) {
                $contenido = (string) file_get_contents($file);
                preg_match('/^ \* Version: (.+)$/m', $contenido, $m);
                $salida['Version'] = trim($m[1] ?? '');
            }
            return $salida;
        }
    }
    if (!\function_exists('plugin_dir_url')) { function plugin_dir_url($file) { unset($file); return 'https://example.test/wp-content/plugins/convoca-shifts/'; } }
    if (!\function_exists('register_deactivation_hook')) { function register_deactivation_hook($f, $cb) { unset($f); $GLOBALS['convoca_test_desactivacion'] = $cb; } }
    if (!\function_exists('register_activation_hook')) { function register_activation_hook($f, $cb) { unset($f); $GLOBALS['convoca_test_activacion'] = $cb; } }

    // Los enganches se APUNTAN: una prueba tiene que poder decir qué queda enganchado y con qué. Antes
    // eran un no-op y eso dejaba sin comprobar medio arranque del plugin (el modal, la limpieza de caché).
    if (!\function_exists('add_action')) {
        function add_action($h, $c, $p = 10, $a = 1) {
            $GLOBALS['_wp_stores']['acciones'][$h][] = $c;
            // Registro PERMANENTE, aparte del almacén de la prueba: los enganches son propiedad del plugin ya
            // cargado, y hay pruebas que limpian `_wp_stores` entero entre casos.
            $GLOBALS['_sh_hooks_registrados'][$h][] = $c;
            return true;
        }
    }
    // Los FILTROS también se apuntan (en su propio almacén): con el no-op de antes, ni un solo
    // `add_filter` del plugin era comprobable — ni el título del documento, ni la barra, ni los
    // checks de salud. Cualquier prueba sobre un filtro pasaba sin comprobar nada.
    if (!\function_exists('add_filter')) {
        function add_filter($h, $c, $p = 10, $a = 1) {
            $GLOBALS['_wp_stores']['filtros'][$h][] = $c;
            return true;
        }
    }
    // El libro de horas vive en Convoca Core, que estos tests unitarios no cargan. Se dobla con la PUERTA
    // CERRADA por defecto, que es el valor real por defecto del núcleo: así un test que acredite sin
    // abrirla se está engañando. Para abrirla: $GLOBALS['__horas_puerta'] = true.
    // El libro de horas vive en Core, que estos tests no cargan: doble con la puerta CERRADA por
    // defecto (el valor real del núcleo). Se abre con $GLOBALS['__horas_puerta'] = true.
    require_once __DIR__ . '/dobles/hour-ledger.php';

    // Los filtros se pueden registrar desde los tests con $GLOBALS['__shifts_filtros'][ $hook ] = callable,
    // para poder probar los contratos (p. ej. las personas asignables) sin WordPress.
    if (!\function_exists('apply_filters')) {
        function apply_filters($h, $v, ...$a) {
            if (isset($GLOBALS['__shifts_filtros'][$h]) && is_callable($GLOBALS['__shifts_filtros'][$h])) {
                return call_user_func($GLOBALS['__shifts_filtros'][$h], $v, ...$a);
            }
            return $v;
        }
    }
    // has_action() se usa para no apilar hooks duplicados; sin stub, el motor
    // de sincronización de turnos revienta en los tests.
    if (!\function_exists('has_action')) { function has_action($h, $c = false) { return false; } }
    // El tipo registrado se apunta: hay que poder afirmar qué se registra y con qué visibilidad.
    if (!\function_exists('register_post_type')) {
        function register_post_type($slug, $args) {
            $GLOBALS['_wp_stores']['cpt'][$slug] = (array) $args;
            return null;
        }
    }
    // Metas y taxonomías APUNTADAS: lo que hay que poder afirmar es qué se registra, con qué tipo y con
    // qué permiso (el `auth_callback` de una meta que se escribe por REST es una puerta de seguridad).
    if (!\function_exists('register_post_meta')) {
        function register_post_meta($tipo, $clave, $args) {
            $GLOBALS['_sh_metas'][$clave] = array('tipo' => $tipo, 'args' => (array) $args);
            return true;
        }
    }
    if (!\function_exists('register_taxonomy')) {
        function register_taxonomy($slug, $tipos, $args) {
            $GLOBALS['_sh_taxonomias'][$slug] = array('tipos' => $tipos, 'args' => (array) $args);
            return null;
        }
    }
    if (!\function_exists('__return_true')) {
        /**
         * Devuelve verdadero: es lo que usa WordPress para los endpoints públicos.
         */
        function __return_true() {
            return true;
        }
    }

    if (!\function_exists('is_user_logged_in')) {
        /**
         * Sesión de mentira, que la prueba abre y cierra a voluntad: los permisos de la API se
         * deciden mirando quién entra, así que sin poder mover esto no hay nada que probar.
         */
        function is_user_logged_in() {
            return (bool) ($GLOBALS['_sh_logged'] ?? false);
        }
    }

    if (!\function_exists('register_rest_route')) {
        /**
         * Apunta las rutas en vez de descartarlas: una prueba tiene que poder decir qué endpoints hay,
         * con qué método y con qué permiso.
         */
        function register_rest_route($namespace, $route, $args = []) {
            $GLOBALS['_sh_routes'][] = \array_merge(['namespace' => $namespace, 'route' => $route], (array) $args);
            return true;
        }
    }
    if (!\function_exists('register_activation_hook')) { function register_activation_hook($f, $c) { return true; } }
    if (!\function_exists('flush_rewrite_rules')) { function flush_rewrite_rules() {} }
    if (!\function_exists('wp_redirect')) { function wp_redirect($u) {} }
    // `wp_die` CORTA: en WordPress termina la petición. Un no-op dejaba que el código siguiera hasta el
    // `exit` de después y se llevaba por delante el proceso de PHPUnit (sin mensaje, sin resultado).
    if (!\function_exists('wp_die')) {
        function wp_die($m = '', $t = '', $a = []) {
            throw new \RuntimeException('wp_die: ' . (is_scalar($m) ? (string) $m : '?'));
        }
    }
    if (!\function_exists('wp_cache_delete')) { function wp_cache_delete($k, $g = '') { return true; } }

    if (!\function_exists('wp_get_post_terms')) {
        function wp_get_post_terms($id, $tax, $args = []) {
            if ($tax !== 'convoca_shifts_actividad') { return []; }

            $terminos = [ (object)['name' => 'Actividad Test', 'term_id' => 1] ];

            // `fields => 'ids'` devuelve ids, no objetos: el código que pide ids castea a int y con
            // objetos avisa («Object of class stdClass could not be converted to int») y guarda un 0.
            if ('ids' === ( $args['fields'] ?? '' )) {
                return \array_map( static fn( $t ) => $t->term_id, $terminos );
            }

            return $terminos;
        }
    }

    if (!\function_exists('post_type_exists')) {
        function post_type_exists($t) {
            return \in_array($t, ['centro_turno', 'registro_hora', 'miembro', 'post', 'page'], true);
        }
    }

    // `get_posts` de verdad, aunque sea de mentira: filtra el almacén por tipo, estado y meta. Antes
    // devolvía SIEMPRE vacío (salvo un caso especial), así que cualquier consulta del plugin —el
    // resumen de turnos, los próximos turnos— se probaba contra el vacío y no medía nada.
    if (!\function_exists('get_posts')) {
        function get_posts($args) {
            $args = (array) $args;

            if (isset($args['meta_value']) && $args['meta_value'] === 'exists@example.com') {
                return [ (object)['ID' => 42] ];
            }

            $posts  = $GLOBALS['_wp_stores']['posts'] ?? [];
            $tipos  = (array) ($args['post_type'] ?? 'post');
            $estado = (array) ($args['post_status'] ?? ['publish']);
            $limite = (int) ($args['posts_per_page'] ?? 5);

            $salida = [];
            foreach ($posts as $id => $p) {
                if (!\in_array($p->post_type ?? 'post', $tipos, true)) { continue; }
                $estado_post = $p->post_status ?? 'publish';
                if (\in_array('any', $estado, true)) {
                    if (\in_array($estado_post, ['trash', 'auto-draft', 'inherit'], true)) { continue; }
                } elseif (!\in_array($estado_post, $estado, true)) {
                    continue;
                }
                if (!\_sh_meta_encaja((int) $id, $args['meta_query'] ?? [])) { continue; }

                // date_query: duplicar la semana pide «los turnos de la semana pasada» y sin esto el
                // arnés devuelve TODOS, así que la prueba se ponía a duplicar turnos de otras fechas.
                $dq = $args['date_query'][0] ?? null;
                if (\is_array($dq)) {
                    // Un post sin fecha no se filtra: hay pruebas que siembran turnos sin `post_date` y
                    // las fechas las llevan solo en metas.
                    $post_date = (string) ($p->post_date ?? '');
                    if ('' !== $post_date) {
                        if (!empty($dq['after']) && \strtotime($post_date) < \strtotime((string) $dq['after'])) { continue; }
                        if (!empty($dq['before']) && \strtotime($post_date) > \strtotime((string) $dq['before'])) { continue; }
                    }
                }

                $salida[] = $p;
                if ($limite > 0 && \count($salida) >= $limite) { break; }
            }

            if ('DESC' === \strtoupper((string) ($args['order'] ?? ''))) { $salida = \array_reverse($salida); }

            return $salida;
        }

        /**
         * ¿La meta de una entrada cumple la consulta? Se entiende `=`, `!=`, `EXISTS` y `NOT EXISTS`,
         * que es lo que usa el plugin; lo que no se entiende, no descarta (mejor de más que de menos).
         *
         * @param int   $id Entrada.
         * @param array $mq Consulta de meta.
         * @return bool
         */
        function _sh_meta_encaja($id, $mq) {
            $mq = (array) $mq;

            // `relation => OR`: el feed iCal pide los turnos donde la persona es responsable O monitor.
            // Tratándolo como AND no devolvía ninguno y la prueba pasaba sin comprobar nada.
            $relacion = strtoupper((string) ($mq['relation'] ?? 'AND'));
            if ('OR' === $relacion) {
                $alguno = false;
                foreach ($mq as $clave => $clausula) {
                    if ('relation' === $clave || !\is_array($clausula)) { continue; }
                    if (_sh_meta_encaja($id, array($clausula))) { $alguno = true; break; }
                }
                return $alguno;
            }

            foreach ((array) $mq as $clausula) {
                if (!\is_array($clausula) || empty($clausula['key'])) { continue; }

                $clave   = $clausula['key'];
                $compara = \strtoupper((string) ($clausula['compare'] ?? '='));
                $meta    = $GLOBALS['_wp_stores']['post_meta'][ $id ] ?? [];
                $tiene   = \array_key_exists($clave, $meta);

                if ('NOT EXISTS' === $compara) { if ($tiene) { return false; } continue; }
                if ('EXISTS' === $compara) { if (!$tiene) { return false; } continue; }
                if (!$tiene) { return false; }

                if ('=' === $compara && (string) $meta[ $clave ] !== (string) ($clausula['value'] ?? '')) { return false; }
                if ('LIKE' === $compara && false === \strpos((string) $meta[ $clave ], (string) ($clausula['value'] ?? ''))) { return false; }
                if ('!=' === $compara && (string) $meta[ $clave ] === (string) ($clausula['value'] ?? '')) { return false; }
            }

            return true;
        }
    }

    if (!\function_exists('register_shutdown_function')) { function register_shutdown_function($c) {} }
    if (!\function_exists('deactivate_plugins')) { function deactivate_plugins($p, $s = false) {} }
    if (!\function_exists('plugin_basename')) { function plugin_basename($f) { return \basename($f); } }
    if (!\function_exists('plugin_dir_path')) { function plugin_dir_path($f) { return \dirname($f) . '/'; } }
    if (!\function_exists('plugin_dir_url')) { function plugin_dir_url($f) { return 'https://example.com/wp-content/plugins/' . \basename(\dirname($f)) . '/'; } }
    // La pantalla actual la fija la prueba: sin esto, cualquier rama que dependa de la pantalla quedaba
    // inalcanzable (avisos del listado, columnas por pantalla…).
    if (!\function_exists('get_current_screen')) { function get_current_screen() { return $GLOBALS['_sh_pantalla'] ?? null; } }
    // La cola de estilos vive en tests/StubAssets.php: ahí se apunta lo que se encola, que es lo que
    // hay que poder afirmar de los shortcodes.
    if (!\function_exists('remove_action')) { function remove_action($h, $c, $p = 10) { return true; } }
    if (!\function_exists('get_user_by')) {
        function get_user_by($field, $value) {
            // Como WordPress: busca por id, login o correo en el almacén de personas.
            foreach ($GLOBALS['_wp_stores']['usuarios'] ?? [] as $id => $u) {
                if (!\is_object($u)) { continue; }
                if ('id' === $field && (int) $id === (int) $value) { return $u; }
                if ('login' === $field && (string) ($u->user_login ?? '') === (string) $value) { return $u; }
                if ('email' === $field && (string) ($u->user_email ?? '') === (string) $value) { return $u; }
            }

            return false;
        }
    }
    if (!\function_exists('wp_next_scheduled')) {
        function wp_next_scheduled($h) {
            // Como WordPress: la marca de tiempo del evento, o falso si no está programado.
            return $GLOBALS['_sh_cron'][$h] ?? false;
        }
    }
    if (!\function_exists('wp_schedule_event')) { function wp_schedule_event($ts, $r, $h, $a = []) { return true; } }
    if (!\function_exists('wp_set_script_translations')) { function wp_set_script_translations($h, $d, $p) {} }
    if (!\function_exists('load_plugin_textdomain')) { function load_plugin_textdomain($d, $dep, $p) {} }
    if (!\function_exists('delete_post_meta_by_key')) { function delete_post_meta_by_key($key) { return true; } }
    if (!\function_exists('get_page_by_path')) {
        function get_page_by_path($slug, $output = OBJECT, $post_type = 'page') {
            // Como WordPress: busca por slug entre las páginas publicadas o en borrador del almacén.
            foreach ($GLOBALS['_wp_stores']['pages'] ?? [] as $pagina) {
                if (($pagina->post_name ?? '') === $slug && ($pagina->post_type ?? 'page') === $post_type) {
                    return $pagina;
                }
            }
            return null;
        }
    }
    if (!\function_exists('get_users')) { function get_users($args = []) { return []; } }
    if (!\function_exists('submit_button')) { function submit_button($t = 'Save', $c = 'primary', $n = 'submit', $w = true, $a = []) { echo '<button type="submit">' . \esc_html($t) . '</button>'; } }
    if (!\function_exists('wp_list_pluck')) {
        function wp_list_pluck($list, $field) {
            $r = [];
            foreach ($list as $item) {
                if (\is_object($item) && isset($item->$field)) $r[] = $item->$field;
            }
            return $r;
        }
    }

    if (!\function_exists('is_wp_error')) {
        function is_wp_error($thing) { return $thing instanceof \WP_Error; }
    }

    // ─── WP_List_Table stub (for admin list class) ───────

    if (!\class_exists('WP_List_Table')) {
        class WP_List_Table {
            /** Filas de la tabla (las pone prepare_items). */
            public $items = [];
            /** Cabeceras de columna. */
            public $_column_headers = [];
            /** Argumentos de paginación recibidos. */
            public $paginacion = null;

            public function __construct($args = []) {}
            public function prepare_items() {}
            public function display() {}
            public function get_columns() { return []; }
            public function get_sortable_columns() { return []; }
            public function column_default($item, $col) { return ''; }

            /**
             * Caja de búsqueda de la tabla. WordPress la trae y las pantallas la llaman: sin ella
             * la pantalla de turnos no se podía ni pintar.
             *
             * @param string $texto     Texto del botón.
             * @param string $input_id  Identificador del campo.
             * @param bool   $echo      Si imprime o devuelve.
             * @return string
             */
            public function search_box($texto = '', $input_id = '', $echo = true) {
                $html = '<p class="search-box"><input type="search" id="' . \htmlspecialchars((string) $input_id, ENT_QUOTES) . '" name="s">'
                    . '<label class="screen-reader-text" for="' . \htmlspecialchars((string) $input_id, ENT_QUOTES) . '">' . \htmlspecialchars((string) $texto, ENT_QUOTES) . '</label></p>';
                if ($echo) { echo $html; return ''; }
                return $html;
            }
            protected function get_table_classes() { return ['wp-list-table']; }

            /** Página actual: la fija la prueba con `$GLOBALS['_sh_pagenum']`. */
            public function get_pagenum() { return (int) ($GLOBALS['_sh_pagenum'] ?? 1); }

            /** WordPress apunta aquí el total para pintar la paginación. */
            public function set_pagination_args($args) { $this->paginacion = $args; }

            /** Acción en bloque pedida, como en WordPress. */
            public function current_action() {
                if (!empty($_REQUEST['action']) && '-1' !== $_REQUEST['action']) { return (string) $_REQUEST['action']; }
                if (!empty($_REQUEST['action2']) && '-1' !== $_REQUEST['action2']) { return (string) $_REQUEST['action2']; }
                return '';
            }
        }
    }

    // ─── WP_Admin_Bar stub (barra de administración) ──────

    // La barra del panel es un objeto con nodos: la página de turnos reescribe el enlace «Todos los
    // turnos». Sin doble, ese método no se podía ni llamar y quedaba sin medir.
    if (!\class_exists('WP_Admin_Bar')) {
        class WP_Admin_Bar {
            /** Nodos de la barra, por identificador. */
            public $nodos = [];

            /**
             * Devuelve un nodo por identificador (false si no está, como WordPress).
             *
             * @param string $id Identificador del nodo.
             * @return object|false
             */
            public function get_node($id) {
                return $this->nodos[ $id ] ?? false;
            }

            /**
             * Añade (o sustituye) un nodo.
             *
             * @param object $nodo Nodo con `id`.
             * @return void
             */
            public function add_node($nodo) {
                $this->nodos[ (string) $nodo->id ] = $nodo;
            }
        }
    }

    // ─── WP_Error class ───────────────────────────────────

    if (!\class_exists('WP_Error')) {
        class WP_Error {
            private $errors = []; private $error_data = [];
            public function __construct($code = '', $message = '', $data = '') {
                if ($code) { $this->errors[$code] = [$message]; $this->error_data[$code] = $data; }
            }
            public function get_error_code() { return \key($this->errors); }
            public function get_error_message($code = '') {
                if (!$code) $code = $this->get_error_code();
                return $this->errors[$code][0] ?? '';
            }
            /** Datos del error (estado HTTP incluido): sin esto no se puede afirmar un 429. */
            public function get_error_data($code = '') {
                if (!$code) $code = $this->get_error_code();
                return $this->error_data[$code] ?? null;
            }
        }
    }

    // ─── $wpdb global ────────────────────────────────────

    if (!isset($GLOBALS['wpdb'])) {
        $GLOBALS['wpdb'] = new class {
            public $prefix = 'wp_';
            public $posts = 'wp_posts';
            public $postmeta = 'wp_postmeta';
            public $options = 'wp_options';
            public $usermeta = 'wp_usermeta';
            public $insert_id = 42;

            public function esc_like($texto) { return addcslashes((string) $texto, '_%\\'); }

            public function get_row($q = null, $o = 'OBJECT', $y = 0) {
                return $GLOBALS['_sh_db']['get_row'] ?? null;
            }

            public function get_col($q = null, $x = 0) {
                if (isset($GLOBALS['_sh_db']['get_col'])) { return (array) $GLOBALS['_sh_db']['get_col']; }
                return [];
            }

            public function get_var($q = null, $x = 0, $y = 0) {
                $qs = (string)$q;
                // El diagnóstico de esquema se sirve aparte del resto de consultas: la pantalla pregunta
                // «¿existe la tabla?» y «¿cuántas filas hay?» a la misma función.
                if (\strpos($qs, 'SHOW TABLES') !== false) {
                    // Si la prueba da la lista de tablas que existen, se contesta por tabla.
                    if (isset($GLOBALS['_sh_db']['tablas_existentes'])) {
                        foreach ((array) $GLOBALS['_sh_db']['tablas_existentes'] as $tabla) {
                            if (false !== \strpos($qs, (string) $tabla)) {
                                return (string) $tabla;
                            }
                        }
                        return '';
                    }

                    return $GLOBALS['_sh_db']['tabla'] ?? 'wp_convoca_member_sequence';
                }
                $GLOBALS['_sh_db']['consultas'][] = $qs;

                // Cola: un mismo flujo hace VARIAS consultas con `get_var` (el cerrojo del turno y luego
                // la comprobación de solapamiento) y hay que poder darle a cada una su respuesta.
                if ( ! empty( $GLOBALS['_sh_db']['get_var_cola'] ) ) {
                    return \array_shift( $GLOBALS['_sh_db']['get_var_cola'] );
                }

                if (\array_key_exists( 'get_var', $GLOBALS['_sh_db'] ?? array() )) { return $GLOBALS['_sh_db']['get_var']; }
                if (\strpos($qs, 'SELECT meta_value') !== false) {
                    // Check if it's for horas_contabilizadas - return 0 for tests
                    if (\strpos($qs, 'horas_contabilizadas') !== false) return '0';
                    return '';
                }
                if (\strpos($qs, 'SELECT ID FROM') !== false) return '1';
                return '10';
            }

            public function get_results($q = null, $o = 'OBJECT') {
                // Una misma pantalla hace DOS consultas distintas; el arnés tiene que poder servirlas
                // por separado o la pantalla recibe la respuesta de la otra (y con ella, avisos).
                foreach ((array) ($GLOBALS['_sh_db']['por_sql'] ?? []) as $aguja => $valor) {
                    if (\strpos((string) $q, (string) $aguja) !== false) { return $valor; }
                }
                if (isset($GLOBALS['_sh_db']['get_results'])) { return $GLOBALS['_sh_db']['get_results']; }
                return [];
            }
            public function query($q) {
                $GLOBALS['_sh_db']['consultas'][] = (string) $q;

                // Fallo provocado a mitad de una transacción: así se prueba el catch (ROLLBACK + soltar
                // el cerrojo) sin inventar un turno corrupto.
                if ( isset( $GLOBALS['_sh_db']['lanza_en'] ) && false !== \strpos( (string) $q, (string) $GLOBALS['_sh_db']['lanza_en'] ) ) {
                    throw new \RuntimeException( 'fallo de base de datos a mitad de la transacción' );
                }

                // El UPDATE del alta devuelve filas afectadas: 0 hace que el código intente el INSERT.
                // La cola se consume EN EL GLOBAL (una copia no avanza y todas las llamadas devolvían lo mismo).
                if (isset($GLOBALS['_sh_db']['query_rc']) && \is_array($GLOBALS['_sh_db']['query_rc'])) {
                    return \array_shift($GLOBALS['_sh_db']['query_rc']);
                }
                return $GLOBALS['_sh_db']['query_rc'] ?? 1;
            }
            /**
             * `insert()`: apunta la llamada y, si es el REGISTRO DE ACTIVIDAD, la proyecta en `_sh_registro`
             * con la forma que afirman las pruebas.
             *
             * Antes esto era un no-op y el registro lo fabricaba un doble de `convoca_shifts_log_activity()`.
             * Ahora el arranque REAL está cargado y escribe por aquí, así que lo que se afirma es lo que el
             * producto manda a la base de datos, no lo que un doble inventaba.
             */
            public function insert($t, $d, $f = []) {
                $this->insert_id = 42;
                $GLOBALS['db_inserts'][] = array('tabla' => $t, 'datos' => $d);

                if (false !== strpos((string) $t, 'activity_log')) {
                    $datos = json_decode((string) ($d['data'] ?? '{}'), true);
                    $GLOBALS['_sh_registro'][] = array(
                        'user_id'  => (int) ($d['user_id'] ?? 0),
                        'turno_id' => (int) ($d['turno_id'] ?? 0),
                        'action'   => (string) ($d['action'] ?? ''),
                        'data'     => is_array($datos) ? $datos : array(),
                    );
                }

                return 1;
            }

            public function prepare($q, ...$args) {
                if (empty($args)) return $q;
                $sql = $q;
                foreach ($args as $arg) {
                    $p = \strpos($sql, '%');
                    if ($p !== false) $sql = \substr_replace($sql, (string)$arg, $p, 2);
                }
                return $sql;
            }

            public function get_charset_collate() { return 'DEFAULT CHARSET=utf8mb4'; }
        };
    }

    $autoload = \dirname(__DIR__) . '/vendor/autoload.php';
    if (\file_exists($autoload)) {
        require_once $autoload;
    }

	// Doble del nucleo para el correo: entrega por wp_mail, que es donde las pruebas
	// lo capturan (ver tests/StubMailer.php).
	require_once __DIR__ . '/StubMailer.php';
 require_once __DIR__ . '/StubIcal.php';
 require_once __DIR__ . '/StubWidgets.php';

	// Doble de WP_Query y de las funciones del bucle (get_the_ID, get_the_date,
	// wp_reset_postdata...). Necesario para probar el recordatorio del cron, que es
	// el unico sitio donde el plugin usa WP_Query en bucle.
	require_once __DIR__ . '/Support/WpQueryDouble.php';

	// Doble de la cola de recursos (registrar, encolar y localizar estilos y guiones): lo
	// necesitan los shortcodes, que hasta ahora no se probaban.
	require_once __DIR__ . '/StubAssets.php';

	// Arranque REAL antes que sus dobles: van con `function_exists`, así que se saltan solos y no hay
	// `Cannot redeclare`.
	require_once dirname( __DIR__ ) . '/convoca-shifts.php';

	require_once __DIR__ . '/StubFechaCorta.php';
	require_once __DIR__ . '/StubAdmin.php';
	require_once __DIR__ . '/StubLicenseManager.php';
	require_once __DIR__ . '/StubTurnos.php';
	require_once __DIR__ . '/StubLogActivity.php';
	require_once __DIR__ . '/StubRestRequest.php';
	require_once __DIR__ . '/StubRest.php';
	require_once __DIR__ . '/StubFecha.php';
	require_once __DIR__ . '/StubBlocks.php';
}
