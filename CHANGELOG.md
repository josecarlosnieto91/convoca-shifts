# Changelog — convoca-shifts

## v2.5.4 (2026-10-09)

### Corregido
- **El recordatorio de turno no encontraba los turnos a los que avisar.** La consulta exigía estado
  `publish`, pero WordPress marca como `future` (entrada programada) cualquier entrada con fecha a
  más de un minuto en el futuro, y la ventana del recordatorio es, precisamente, futura: los turnos
  creados desde el editor del panel se quedaban sin aviso. La consulta busca ahora los dos estados.
  Se añade prueba del cron (`ReminderCronTest`), que antes no existía.
- **La desinstalación no limpiaba los widgets y moría a medias.** Los cuatro widgets guardan su
  configuración en `widget_convoca_shifts_*` y no se borraban; además el bloque nuevo usaba `$wpdb`
  antes del `global $wpdb;`, así que era nulo (fatal a medias: no se borraban ni el registro de
  actividad ni las opciones de los widgets).
- **La constante de versión vuelve a ir con el header.** `CONVOCA_SHIFTS_VERSION` es el fingerprint
  del `Upgrade_Manager` y se quedó en 2.5.2, así que la migración de la 2.5.3 (vínculo
  turno↔registro_hora) nunca se consideraba pendiente y no corría.

### Añadido
- **`Convoca\Core\Mailer` como punto único de los correos:** el recordatorio y el aviso por faltas
  salen con la identidad visual común y copia a la asociación; los avisos al administrador pasan por
  el mismo punto pero sin copia (ya van a la asociación).

### Internamente
- Doble fiel de `Convoca\Core\Mailer` para las pruebas y ajustes para dejar el CI en verde (PHPStan y
  estilo).

## v2.5.3 (2026-09-25)

### Corregido
- El mismo defecto que en Enroll: desmarcar un turno dejaba sus horas contando y volver a marcarlo
  las duplicaba. La acreditación pasa ahora por `Convoca\Core\Hour_Ledger` (un registro por turno,
  invalidado al desmarcar y reactivado al re-marcar). Se elimina el escritor duplicado de
  `registro_hora` que vivía en `Hour_Sync`.

### Migración
- `Upgrade_Manager` 2.5.3: enlaza los registros de horas de turnos con su turno cuando se puede
  demostrar (el turno existe y su responsable es el voluntario del registro). Idempotente; los
  casos no demostrables se dejan intactos y se cuentan en el log.


## v2.5.2 (2026-09-05)

### 🔐 Security
- Nonce en el dismiss de avisos + fix de meta con espacio en Hour_Sync

### ✨ Improvements
- Analíticas avanzadas (PRO)
- Textos genéricos sin referencias a «Centro de ejemplo»

### 🐛 Fixes
- Namespace REST renombrado de `centro/v1` a `convoca-shifts/v1`

### 📦 Infrastructure
- Preparación WordPress.org: PCP 0 errores, i18n completo, iconos/banners, CI plugin-check

## v2.5.1 (2026-06-24)

### 🐛 Fixes
- Corregido callback de generar turnos sin namespace (causaba fatal error en admin)
- Fix en creación rápida de turnos (turno rápido)

### 📦 Infrastructure
- Updated release ZIPs on getconvoca.app
- Demo environment synchronized

---
