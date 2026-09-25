# Changelog — convoca-shifts

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
- Textos genéricos sin referencias a «Centro Social Turnos»

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
