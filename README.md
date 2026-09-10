# Convoca Shifts

Gestión de turnos de voluntariado para organizaciones y centros.

## Requirements

- WordPress 6.4+
- PHP 8.1+
- convoca-core activo
- convoca-members (recomendado)

## Main Features

- Turno CPT con estados
- Calendario visual
- Añadir Turno Rápido (admin_post_, no admin_init)
- Duplicar semana
- Export CSV
- REST API (`convoca-shifts/v1`)
- Cron recordatorios
- Shortcodes: `convoca_calendario`, `convoca_proximos_turnos`, `convoca_resumen_turnos`, `convoca_boton_apuntarse`
- Auditoría de horas de voluntariado
- Penalización de ausencias (no-show)
- Estadísticas
- Widgets


## 📖 Documentación

La documentación completa (manual de usuario, API REST, hooks, instalación) vive en la wiki:

👉 **[Convoca shifts](https://docs.getconvoca.app/plugins/convoca-shifts/)**

## Dependencies

convoca-core (obligatorio), convoca-members (recomendado), WordPress 6.4+, PHP 8.1+

## Version

2.5.2

## Changelog

El historial completo de versiones está en [CHANGELOG.md](CHANGELOG.md).

## Hooks

| Hook | Tipo | Descripción |
|------|------|-------------|
| `convoca_shifts_hourly_event` | action | Envío de recordatorios de turnos (cron) |
| `convoca_shifts_daily_event` | action | Limpieza de meta antiguo (cron) |
| `convoca_after_horas_voluntario_actualizadas` | action | Horas de voluntariado actualizadas (fallback `convoca_horas_voluntario_actualizadas`) |
| `convoca_shifts_force_enqueue_assets` | filter | Forzar carga de assets en el frontend |
| `convoca_shifts_confirm_signup` | filter | Activar/desactivar confirmación al apuntarse |
| `convoca_shifts_no_show_email_socio` | filter | Email de ausencia enviado al socio |
| `convoca_shifts_no_show_email_admin` | filter | Email de ausencia enviado al admin |

También escucha `convoca_voluntario_aprobado` y `convoca_voluntario_revocado` (de Convoca Members) para liberar turnos.

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

