<?php
/**
 * Recordatorio de turno (evento horario del cron): debe encontrar el turno REAL.
 *
 * Issue #1: la consulta exigia `post_status = 'publish'`, pero un turno con fecha
 * futura (justo la ventana del recordatorio, 2 horas) lo guarda el nucleo como
 * `future`, no como `publish`. La prueba reproduce esa semantica en el doble de
 * WP_Query (tests/Support/WpQueryDouble.php): con el codigo sin arreglar, el turno
 * programado no aparece y no se envia el aviso; con el arreglo, si.
 */

namespace Convoca\Shifts\Tests;

use PHPUnit\Framework\TestCase;

class ReminderCronTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Convoca\Core\Logger::clear();
        \Convoca\Core\Utils::clear_fired();

        foreach (['post_meta', 'transients', 'options', 'user_meta', 'test_posts', 'emails', 'locks'] as $key) {
            $GLOBALS['_wp_stores'][$key] = [];
        }
        $GLOBALS['convoca_test_current_post'] = null;

        $path = dirname(__DIR__, 2) . '/includes/cron.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }

    /**
     * Crea un turno como lo hace el editor de administracion: se guarda con fecha
     * futura, y el nucleo lo deja en estado `future` (entrada programada). El
     * responsable y el estado van por meta, como en produccion.
     */
    private function seedTurno(int $id, string $post_date, array $meta = []): void
    {
        $post = (object) [
            'ID'          => $id,
            'post_title'  => 'Turno programado',
            'post_type'   => 'centro_turno',
            'post_status' => 'future',
            'post_date'   => $post_date,
        ];

        $GLOBALS['_wp_stores']['test_posts'][$id] = $post;

        $defaults = [
            '_id_responsable' => 1,
            '_estado'         => 'abierto_disponible',
            '_estado_real'    => 'pendiente',
        ];
        foreach (array_merge($defaults, $meta) as $key => $value) {
            update_post_meta($id, $key, $value);
        }
    }

    public function test_reminder_finds_a_future_shift_in_the_window(): void
    {
        $in_one_hour = wp_date('Y-m-d H:i:s', time() + HOUR_IN_SECONDS);
        $this->seedTurno(1234, $in_one_hour);

        \Convoca\Shifts\convoca_shifts_send_reminders();

        $emails = $GLOBALS['_wp_stores']['emails'];
        $this->assertCount(
            1,
            $emails,
            'El recordatorio debe encontrar el turno programado (estado future) dentro de la ventana.'
        );
        $this->assertSame(['user1@example.com'], $emails[0]['to']);
        $this->assertSame('Recordatorio de turno', $emails[0]['subject']);
    }

    public function test_reminder_marks_the_shift_and_does_not_repeat(): void
    {
        $in_one_hour = wp_date('Y-m-d H:i:s', time() + HOUR_IN_SECONDS);
        $this->seedTurno(1234, $in_one_hour);

        \Convoca\Shifts\convoca_shifts_send_reminders();
        \Convoca\Shifts\convoca_shifts_send_reminders();

        $this->assertCount(1, $GLOBALS['_wp_stores']['emails'], 'No debe repetir el aviso para el mismo turno.');
        $this->assertNotEmpty(get_post_meta(1234, '_convoca_shifts_reminder_sent', true));
    }

    public function test_reminder_ignores_a_shift_outside_the_window(): void
    {
        $in_five_hours = wp_date('Y-m-d H:i:s', time() + (5 * HOUR_IN_SECONDS));
        $this->seedTurno(2345, $in_five_hours);

        \Convoca\Shifts\convoca_shifts_send_reminders();

        $this->assertEmpty($GLOBALS['_wp_stores']['emails'], 'Fuera de la ventana de 2 horas no hay aviso.');
    }

    public function test_reminder_ignores_a_past_shift(): void
    {
        $yesterday = wp_date('Y-m-d H:i:s', time() - DAY_IN_SECONDS);
        $this->seedTurno(3456, $yesterday);

        \Convoca\Shifts\convoca_shifts_send_reminders();

        $this->assertEmpty($GLOBALS['_wp_stores']['emails'], 'Un turno ya pasado no genera recordatorio.');
    }

    public function test_reminder_ignores_a_closed_shift(): void
    {
        $in_one_hour = wp_date('Y-m-d H:i:s', time() + HOUR_IN_SECONDS);
        $this->seedTurno(4567, $in_one_hour, ['_estado' => 'cerrado']);

        \Convoca\Shifts\convoca_shifts_send_reminders();

        $this->assertEmpty($GLOBALS['_wp_stores']['emails'], 'Un turno cerrado no genera recordatorio.');
    }

    public function test_reminder_ignores_a_shift_without_responsable(): void
    {
        $in_one_hour = wp_date('Y-m-d H:i:s', time() + HOUR_IN_SECONDS);
        $this->seedTurno(5678, $in_one_hour, ['_id_responsable' => 0]);

        \Convoca\Shifts\convoca_shifts_send_reminders();

        $this->assertEmpty($GLOBALS['_wp_stores']['emails'], 'Sin responsable asignado no hay a quien avisar.');
    }
}
