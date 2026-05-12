# Centro Social Turnos

Gestión de turnos de voluntariado para el Centro Social.

## Requirements

- WordPress 6.4+
- PHP 8.1+

## Main Features

- Turno CPT con estados
- Calendario visual
- Añadir Turno Rápido (admin_post_, no admin_init)
- Duplicar semana
- Export CSV
- REST API
- Cron recordatorios

## Dependencies

WordPress 6.4+, PHP 8.1+, biodevas-common (optional but recommended)

## Version

2.4.0

## Changelog

### 2.4.0
- Seguridad: cst_process_quick_add_turno movido de admin_init a admin_post_cst_quick_add_turno
- Formulario envía a admin-post.php con action oculto
- Nonce fallido redirige con error en lugar de ignorar

### 2.3.1
- Bugfix: asset enqueue detection for AJAX-loaded content
- Added cst_force_enqueue_assets filter

### 2.3.0
- Added Duplicar Semana tool
- Added Export CSV
- Mobile-first calendar views with FullCalendar
- Volunteer approval/revocation workflow

### 2.2.0
- Added visual calendar with FullCalendar
- Quick-add turno via admin_post_
- Cron reminders for upcoming shifts
