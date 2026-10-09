<?php
/**
 * Doble de WP_Query para las pruebas unitarias de convoca-shifts.
 *
 * El recordatorio del cron (includes/cron.php) recorre turnos con WP_Query y, por
 * tanto, su comportamiento depende de como WP_Query filtra. Un doble que lo hiciera
 * "de mentira" (devolver todo sin mirar post_status) escondería justo el fallo que
 * aqui se prueba. Este doble es fiel a lo que importa:
 *
 *   - filtra por post_type y por post_status (string o array, como WP);
 *   - aplica date_query (after/before + inclusive) sobre post_date;
 *   - evalua meta_query (relaciones AND/OR anidadas y los compare habituales).
 *
 * No pretende ser WP entero: solo lo suficiente para que una consulta que no
 * encuentra lo que deberia lo demuestre.
 *
 * Vive fuera del namespace (global) porque el codigo bajo prueba usa \WP_Query.
 */

if ( ! class_exists( 'WP_Query' ) ) {

	class WP_Query {

		/** @var array Argumentos recibidos. */
		public $args = array();

		/** @var array Entradas que cumplen la consulta. */
		public $posts = array();

		/** @var int Numero de entradas encontradas. */
		public $post_count = 0;

		/** @var int Total encontrado (sin paginacion). */
		public $found_posts = 0;

		/** @var object|null Entrada actual del bucle. */
		public $post = null;

		/** @var int Indice del bucle. */
		public $current_post = -1;

		/** @var int Cursor interno del bucle. */
		private $cursor = 0;

		public function __construct( $args = array() ) {
			$this->args        = is_array( $args ) ? $args : array();
			$this->posts       = convoca_test_wp_query_match( $this->args );
			$this->post_count  = count( $this->posts );
			$this->found_posts = $this->post_count;
		}

		public function have_posts() {
			return $this->cursor < $this->post_count;
		}

		public function the_post() {
			$this->post                     = $this->posts[ $this->cursor ];
			$this->current_post             = $this->cursor;
			$GLOBALS['convoca_test_current_post'] = $this->post;
			++$this->cursor;
		}

		public function rewind_posts() {
			$this->cursor       = 0;
			$this->current_post = -1;
			$GLOBALS['convoca_test_current_post'] = null;
		}

		public function get_posts() {
			return $this->posts;
		}
	}
}

if ( ! function_exists( 'convoca_test_wp_query_match' ) ) {

	/**
	 * Devuelve las entradas de la tienda de pruebas que cumplen $args.
	 *
	 * @param array $args Argumentos de WP_Query.
	 * @return array
	 */
	function convoca_test_wp_query_match( $args ) {
		$all    = isset( $GLOBALS['_wp_stores']['test_posts'] ) ? $GLOBALS['_wp_stores']['test_posts'] : array();
		$out    = array();
		$type   = isset( $args['post_type'] ) ? $args['post_type'] : 'post';
		$status = isset( $args['post_status'] ) ? $args['post_status'] : 'publish';

		if ( ! is_array( $status ) ) {
			$status = array_map( 'trim', explode( ',', (string) $status ) );
		}

		foreach ( $all as $p ) {
			if ( ! is_object( $p ) || ! isset( $p->ID ) ) {
				continue;
			}

			$ptype = isset( $p->post_type ) ? $p->post_type : 'post';
			if ( $ptype !== $type ) {
				continue;
			}

			// Filtro de estado: es el nucleo del fallo de la issue #1. WordPress
			// guarda como 'future' lo publicado con fecha futura; una consulta que
			// exige 'publish' no lo ve.
			$pstatus = isset( $p->post_status ) ? $p->post_status : 'publish';
			if ( ! in_array( $pstatus, $status, true ) ) {
				continue;
			}

			if ( ! convoca_test_wp_query_date_match( $p, isset( $args['date_query'] ) ? $args['date_query'] : array() ) ) {
				continue;
			}

			if ( ! convoca_test_wp_query_meta_match( $p->ID, isset( $args['meta_query'] ) ? $args['meta_query'] : array() ) ) {
				continue;
			}

			$out[] = $p;
		}

		return $out;
	}
}

if ( ! function_exists( 'convoca_test_wp_query_date_match' ) ) {

	/**
	 * Evalua un date_query simple (after/before, inclusive) sobre post_date.
	 *
	 * @param object $post Entrada.
	 * @param array  $dq   date_query.
	 * @return bool
	 */
	function convoca_test_wp_query_date_match( $post, $dq ) {
		if ( empty( $dq ) || ! isset( $post->post_date ) ) {
			return true;
		}

		$ts = strtotime( $post->post_date );

		foreach ( $dq as $clause ) {
			if ( ! is_array( $clause ) ) {
				continue;
			}

			$inclusive = ! empty( $clause['inclusive'] );

			if ( isset( $clause['after'] ) ) {
				$after = strtotime( (string) $clause['after'] );
				if ( $inclusive ? ( $ts < $after ) : ( $ts <= $after ) ) {
					return false;
				}
			}

			if ( isset( $clause['before'] ) ) {
				$before = strtotime( (string) $clause['before'] );
				if ( $inclusive ? ( $ts > $before ) : ( $ts >= $before ) ) {
					return false;
				}
			}
		}

		return true;
	}
}

if ( ! function_exists( 'convoca_test_wp_query_meta_match' ) ) {

	/**
	 * Evalua un meta_query (relations anidadas) contra el store de post_meta.
	 *
	 * @param int   $post_id Id de la entrada.
	 * @param array $mq      meta_query.
	 * @return bool
	 */
	function convoca_test_wp_query_meta_match( $post_id, $mq ) {
		if ( empty( $mq ) ) {
			return true;
		}

		$relation = isset( $mq['relation'] ) ? strtoupper( (string) $mq['relation'] ) : 'AND';
		unset( $mq['relation'] );

		$results = array();

		foreach ( $mq as $clause ) {
			if ( ! is_array( $clause ) ) {
				continue;
			}

			if ( isset( $clause['key'] ) ) {
				$results[] = convoca_test_wp_query_meta_clause( $post_id, $clause );
			} else {
				$results[] = convoca_test_wp_query_meta_match( $post_id, $clause );
			}
		}

		if ( empty( $results ) ) {
			return true;
		}

		return ( 'OR' === $relation ) ? in_array( true, $results, true ) : ! in_array( false, $results, true );
	}
}

if ( ! function_exists( 'convoca_test_wp_query_meta_clause' ) ) {

	/**
	 * Evalua un unico meta_query clause.
	 *
	 * @param int   $post_id Id de la entrada.
	 * @param array $clause  Clause con key/value/compare.
	 * @return bool
	 */
	function convoca_test_wp_query_meta_clause( $post_id, $clause ) {
		$key     = (string) $clause['key'];
		$value   = get_post_meta( $post_id, $key, true );
		$compare = strtoupper( isset( $clause['compare'] ) ? (string) $clause['compare'] : '=' );
		$target  = isset( $clause['value'] ) ? $clause['value'] : '';

		$target_list = array_map( 'strval', (array) $target );

		switch ( $compare ) {
			case '=':
				return (string) $value === (string) $target;
			case '!=':
				return (string) $value !== (string) $target;
			case '>':
				return (float) $value > (float) $target;
			case '>=':
				return (float) $value >= (float) $target;
			case '<':
				return (float) $value < (float) $target;
			case '<=':
				return (float) $value <= (float) $target;
			case 'IN':
				return in_array( (string) $value, $target_list, true );
			case 'NOT IN':
				return ! in_array( (string) $value, $target_list, true );
			case 'LIKE':
				return false !== strpos( (string) $value, (string) $target );
			case 'EXISTS':
				return '' !== (string) $value;
			default:
				return (string) $value === (string) $target;
		}
	}
}

// ─── Funciones del bucle de WordPress usadas dentro de the_post() ────────

if ( ! function_exists( 'get_the_ID' ) ) {
	function get_the_ID() {
		$cur = isset( $GLOBALS['convoca_test_current_post'] ) ? $GLOBALS['convoca_test_current_post'] : null;
		return ( $cur && isset( $cur->ID ) ) ? $cur->ID : false;
	}
}

if ( ! function_exists( 'get_the_date' ) ) {
	function get_the_date( $format = '', $post = null ) {
		$cur  = $post ? $post : ( isset( $GLOBALS['convoca_test_current_post'] ) ? $GLOBALS['convoca_test_current_post'] : null );
		$date = ( is_object( $cur ) && isset( $cur->post_date ) ) ? $cur->post_date : '';
		if ( '' === $date ) {
			return '';
		}
		return date( $format ? $format : 'Y-m-d', strtotime( $date ) );
	}
}

if ( ! function_exists( 'wp_reset_postdata' ) ) {
	function wp_reset_postdata() {
		$GLOBALS['convoca_test_current_post'] = null;
	}
}

if ( ! function_exists( 'get_post_timestamp' ) ) {
	function get_post_timestamp( $post = null, $field = 'date' ) {
		$cur = is_object( $post ) ? $post : ( isset( $GLOBALS['convoca_test_current_post'] ) ? $GLOBALS['convoca_test_current_post'] : null );
		if ( is_numeric( $post ) ) {
			$obj = get_post( (int) $post );
			$cur = $obj ? $obj : $cur;
		}
		return ( is_object( $cur ) && isset( $cur->post_date ) ) ? strtotime( $cur->post_date ) : false;
	}
}
