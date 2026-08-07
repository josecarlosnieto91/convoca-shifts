<?php
/**
 * Unit test bootstrap — standalone, no WordPress needed.
 *
 * Shifts depende de Convoca Core (Logger, Utils). Reutilizamos el
 * bootstrap-unit de Core (mocks WP) y cargamos ambos autoloaders.
 */
define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$core_bootstrap = dirname( __DIR__, 2 ) . '/convoca-core/tests/bootstrap-unit.php';
if ( file_exists( $core_bootstrap ) ) {
	require_once $core_bootstrap;
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
