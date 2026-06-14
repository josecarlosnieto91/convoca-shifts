=== Convoca Shifts ===
Contributors: josecarlosnietoramos
Tags: shifts, volunteers, calendar, scheduling, centro social
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 2.5.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Gestión de turnos de voluntariado para centros sociales. Calendario, inscripción y control horario.

== Description ==

Plugin de gestión de turnos de voluntariado para el ecosistema Convoca:

* Calendario interactivo — Vista mensual, semanal y lista con FullCalendar
* Gestión de turnos completa — Crear, editar, apuntarse y desapuntarse
* Registro de horas — Sincronización automática de horas de voluntariado
* Shortcodes — [convoca_calendario], [convoca_resumen_turnos], [convoca_proximos_turnos]
* Bloques Gutenberg y widgets disponibles
* Exportación CSV y activity log completo
* REST API y notificaciones por email

== Installation ==

1. Asegúrate de que Convoca Core y Convoca Members están activos
2. Sube la carpeta convoca-shifts a /wp-content/plugins/
3. Activa el plugin
4. Configura horario en Ajustes > Centro Social

== Changelog ==

= 2.5.0 =
* Fix: Timezone Europe/Madrid, asignación de turnos, hour sync

= 2.4.1 =
* Fix: Vista mes/semana con límite de líneas

= 2.4.0 =
* Seguridad: Quick add turno movido a admin_post
* Nuevo: Hour_Sync para horas de voluntariado

= 2.3.1 =
* Seguridad: Dependency check para convoca-core

= 2.0.0 =
* Primera versión refactorizada para Convoca
