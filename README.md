# Convoca Shifts

Gestión de turnos de voluntariado para organizaciones y centros.

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


## 📖 Documentación

La documentación completa (manual de usuario, API REST, hooks, instalación) vive en la wiki:

👉 **[Convoca shifts](https://docs.getconvoca.app/plugins/convoca-shifts/)**

## Dependencies

WordPress 6.4+, PHP 8.1+, convoca-core (optional but recommended)

## Version

2.4.0

## Changelog

### 2.5.1
- docs: add MANUAL_USUARIO.md with calendar and shifts guide
- refactor: remove 7 duplicate/dead include files
- dev: update phpstan.neon to level 5

### 2.4.0
- Seguridad: cst_process_quick_add_turno movido de admin_init a admin_post_cst_quick_add_turno
- Formulario envía a admin-post.php con action oculto
- Nonce fallido redirige con error en lugar de ignorar

### 2.3.1
- Bugfix: asset enqueue detection for AJAX-loaded content
- Added convoca_shifts_force_enqueue_assets filter

### 2.3.0
- Added Duplicar Semana tool
- Added Export CSV
- Mobile-first calendar views with FullCalendar
- Volunteer approval/revocation workflow

### 2.2.0
- Added visual calendar with FullCalendar
- Quick-add turno via admin_post_
- Cron reminders for upcoming shifts
## 🧪 Demo

Prueba Convoca sin instalar nada:

👉 **[demo.getconvoca.app](https://demo.getconvoca.app)**

## 📸 Capturas

| Socios | Actividades | Turnos | Inscripciones |
|--------|-------------|--------|---------------|
| ![Socios](https://getconvoca.app/wp-content/uploads/2026/06/convoca-miembros-v4.png) | ![Actividades](https://getconvoca.app/wp-content/uploads/2026/06/convoca-actividades-v4.png) | ![Turnos](https://getconvoca.app/wp-content/uploads/2026/06/convoca-turnos-v4.png) | ![Inscripciones](https://getconvoca.app/wp-content/uploads/2026/06/convoca-inscripciones-v4.png) |

## 🔗 Ecosistema

- [Convoca Core](https://github.com/josecarlosnieto91/convoca-core)
- [Convoca Members](https://github.com/josecarlosnieto91/convoca-members)
- [Convoca Enroll](https://github.com/josecarlosnieto91/convoca-enroll)
- [Convoca Gateway](https://github.com/josecarlosnieto91/convoca-gateway)
- [Convoca Shifts](https://github.com/josecarlosnieto91/convoca-shifts)
- [Convoca Publisher](https://github.com/josecarlosnieto91/convoca-publisher)

