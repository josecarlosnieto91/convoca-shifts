<?php
/**
 * El nonce de «Editar Turno» tiene que viajar dentro del formulario.
 *
 * Defecto real (issue #10, visto en la demo): `wp_nonce_field()` se imprimía ANTES de abrir el `<form>`,
 * así que el campo quedaba fuera del formulario y no viajaba en el POST. El manejador cortaba en la
 * primera comprobación con «Acceso denegado.» (HTTP 500) y el turno nunca se guardaba.
 *
 * El orden no lo vigila ninguna herramienta —ni PHPCS ni PHPStan ven la relación entre dos líneas
 * separadas—, así que se vigila aquí: es un contrato de plantilla.
 *
 * @package Convoca\Shifts\Tests
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

class EditorNonceTest extends TestCase
{
    private function fuente(): string
    {
        return (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-admin-turno-editor.php' );
    }

    public function test_el_nonce_va_dentro_del_formulario(): void
    {
        $fuente = $this->fuente();

        $abre     = strpos( $fuente, '<form method="post"' );
        $nonce    = strpos( $fuente, 'wp_nonce_field(' );
        $cierra   = strpos( $fuente, '</form>' );

        $this->assertNotFalse( $abre, 'No se encontró la apertura del formulario.' );
        $this->assertNotFalse( $nonce, 'No se encontró el campo nonce.' );
        $this->assertNotFalse( $cierra, 'No se encontró el cierre del formulario.' );

        $this->assertGreaterThan(
            $abre,
            $nonce,
            'El campo nonce se imprime ANTES de abrir el <form>: no viajaría en el POST y el manejador respondería «Acceso denegado.» (issue #10).'
        );
        $this->assertLessThan(
            $cierra,
            $nonce,
            'El campo nonce queda después de cerrar el <form>: no viajaría en el POST.'
        );
    }

    /** Y el manejador tiene que comprobarlo: si no, el nonce sería decorativo. */
    public function test_el_manejador_comprueba_el_nonce(): void
    {
        $fuente = $this->fuente();

        $this->assertStringContainsString( 'wp_verify_nonce(', $fuente );
        $this->assertStringContainsString( 'convoca_shifts_save_turno_', $fuente );
        $this->assertStringContainsString( "current_user_can( 'edit_post'", $fuente );
    }
}
