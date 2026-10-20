=== Convoca Shifts ===
Contributors: josecarlosnietoramos
Tags: shifts, volunteering, calendar, scheduling, check-in
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 2.5.29
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Volunteer shift management with visual calendar and check-in.

== Description ==

Manage volunteer shifts with a visual calendar (FullCalendar), quick creation, week duplication, check-in, and CSV export.

* Visual calendar with monthly, weekly, and daily views
* Quick shift creation without page reload
* Full week duplication
* Volunteer assignment and approval
* Hour sync with Convoca Members
* `[convoca_calendario]` shortcode for the interactive shift calendar (FullCalendar)
* `[convoca_proximos_turnos]` shortcode for the next shifts list
* `[convoca_resumen_turnos]` shortcode for the shift summary
* `[convoca_boton_apuntarse]` shortcode for the sign-up button
* PDF certificates for completed shifts

= External Services =

This plugin may contact getconvoca.app to validate PRO licenses, only when a key is entered in the admin panel.

== Installation ==

1. Make sure Convoca Core and Convoca Members are active
2. Upload the `convoca-shifts` folder to `/wp-content/plugins/`
3. Activate the plugin from the Plugins menu

== Changelog ==

= 2.5.10 =
* Desinstalación: se limpian todas las tareas programadas del plugin. Antes se
  quedaban puestas y seguían disparándose tras borrarlo (medido en un WordPress limpio).
* Prueba nueva: todo gancho que el plugin programa tiene que aparecer en su desinstalación.

= 2.5.5 =
* La página de Estadísticas de voluntarios vuelve a funcionar: la consulta usaba dos alias de tabla (`pm_fecha`, `pm_fecha2`) que no estaban en ningún JOIN, así que la base de datos devolvía «Unknown column» y la pantalla salía sin datos. El error quedaba registrado en el log de producción sin que nada lo avisara en pantalla.

= 2.5.2 =
* Security: dismiss de aviso admin con nonce (anti-CSRF).
* Fix: sincronización de horas escribía la meta con espacio inicial ' _convoca_miembro_id' — el enlace horas→socio ahora se materializa.

= 2.5.1 =
* Improvement: 37 unit tests, 67 assertions
* New: Shift management, status, and CSV export tests

== Screenshots ==

1. Visual calendar with monthly view
2. Shift creation dialog
3. Volunteer assignment
4. Public shifts shortcode

== Frequently Asked Questions ==

= Does it require Convoca Core? =

Yes. Convoca Shifts requires Convoca Core to be active. It also integrates with Convoca Members for volunteer hours.

= Can I duplicate a week? =

Yes. You can duplicate a full week of shifts with a single action.

= Are certificates generated? =

Yes. PDF certificates for completed shifts are generated locally with Dompdf.

== Upgrade Notice ==

= 2.5.1 =
* Compatibility and stability improvements. Recommended update.
