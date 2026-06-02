# Changelog - Centro Social Turnos

## 2.4.1
- **Fix:** Calendario REST: fechas se mostraban en UTC+0 en vez de Europe/Madrid (`wp_date(strtotime)`).
- **Fix:** Asignación de turnos: `_id_responsable` meta no existía para la mayoría de turnos → TypeError en el UPDATE atómico.
- **Fix:** `proximo-libre` y emails de asignación mostraban hora incorrecta por timezone.
- **Fix:** `Hour_Sync` calculaba horas desde `post_date` (fecha de creación) y no desde `_fecha_inicio`.
- **Fix:** Límite de 2 líneas en eventos del calendario (vista mes) con `-webkit-line-clamp: 2`.
- **Fix:** Vista semana ahora muestra texto con wrapping hasta 3 líneas.
- **Nuevo:** WP_List_Table con screen ID explícito (`cst-turnos-list`).
- **Nuevo:** Eliminado duplicado de "Todos los Turnos" en el menú lateral.

## 2.4.0
- Seguridad: cst_process_quick_add_turno movido de admin_init a admin_post_cst_quick_add_turno
- El formulario ahora envía a admin-post.php con action oculto
- Nonce fallido redirige con error en lugar de ignorar
- Nuevo: Clase Hour_Sync para sincronización automática de horas con el contador global de voluntarios
- Corrección: Handler de generar_semana faltante en cst_generar_turnos_page
- Actualización: Documentación sincronizada (versión 2.4.0)

## 2.3.1
- **Seguridad:** Added dependency check for `convoca-core` plugin (previously missing).
- **Fix:** Race condition in shift title sync - hook now re-added in `finally` block if `wp_update_post()` fails.
- **Fix:** Trackability in hour sync - `registro_hora` created before COMMIT to prevent data loss on errors.

## 2.3.0
- **Mejora:** Rediseño de la página de ajustes con sistema de pestañas para una mejor organización.
- **Nuevo:** Panel de **Estado** para diagnóstico de dependencias y configuración de shortcodes.

## 2.2.0
- **Mejora:** Reorganización de los bloques en su propia sección "Convoca: Centro Social" del insertador.
- **Mejora:** Añadidas comprobaciones de seguridad para evitar errores de renderizado de `serverSideRender`.
- **Mejora:** Actualizado el requisito mínimo de PHP a 8.1 para consistencia.

## 1.9.1
- **Fix:** FullCalendar ya solo se carga en páginas que contienen bloques CST.
- **Fix:** Migración completa de `date()` → `wp_date()` para consistencia de zona horaria.
- **Fix:** Nombre de archivo de exportación CSV corregido con fecha local.

## 1.8.0
- **Nuevo:** Implementación completa de widgets para todos los shortcodes.
- **Nuevo:** El calendario guarda automáticamente la vista seleccionada (Mes, Semana, Lista).
- **Mejora:** Responsive inteligente (fuerza lista en móviles, recupera preferencia en escritorio).
