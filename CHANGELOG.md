## [2.5.16] — 2026-10-15

### Añadido
- **Feed de calendario de los turnos de cada voluntario (issue #13).** La web prometía «Exportación a iCal,
  Google Calendar» y no existía nada — cero apariciones de `ics` en el plugin. Se sirve un **feed
  suscribible**: el voluntario pega la dirección en su calendario y sus turnos se mantienen al día solos, sin
  volver a descargar nada. Google Calendar se suscribe a una URL `.ics` igual que cualquier otro, así que la
  misma dirección cubre las dos promesas.
  - La dirección es personal y **estable** (una suscripción que caduca no sirve): lleva un testigo firmado con
    la sal del sitio y comparado en tiempo constante. Sin testigo válido, 403.
  - Los turnos van en **UTC** y con las líneas plegadas a 75 octetos por carácter completo (un acento partido
    en dos rompe el calendario), con el escapado del RFC 5545. Un turno que cruza la medianoche termina al día
    siguiente en vez de acabar antes de empezar.
  - La caja con la dirección y las instrucciones para Google Calendar sale **dentro del resumen de turnos**, que
    es donde el voluntario mira, y también como shortcode `[convoca_suscripcion_turnos]`.

### Pruebas
- `tests/Unit/IcalTest.php`: escapado (comas, puntos y coma, barras y saltos), plegado a 75 octetos sin partir
  acentos y sin perder un byte, y el contrato del feed (ruta, tipo de contenido, campos obligatorios, testigo
  firmado, enganche y caja en el resumen). **Negativo probado**: desactivando el plegado caen dos pruebas.
- Suite 74 en verde, PHPCS 0, PHPStan `[OK]`.

# Changelog — convoca-shifts

## [2.5.15] - 2026-10-15

### Seguridad

- **La API pública de turnos devolvía la planificación interna a quien preguntara sin sesión** (#4): el
  responsable, su nombre, las notas, el monitor y si el turno necesita apoyo. Ahora solo se sirven a quien
  puede gestionar turnos (misma capacidad que usan las pantallas del plugin); sin sesión quedan la fecha, la
  hora, el estado y la actividad.
- Y la otra puerta llevaba al mismo sitio y además **el `auth_callback` no bastaba**: en WordPress autoriza
  las ESCRITURAS, no las lecturas, así que con `show_in_rest` a `true` las metas seguían viajando en la
  respuesta de `/wp/v2/centro_turno` (medido: `_id_responsable`, `_notas`, `_necesita_apoyo`, `_estado_real`,
  `_monitor`, el motivo de ausencia y la fecha de aviso). Se cierra la lectura con
  `rest_prepare_centro_turno`, que las quita para quien no gestiona turnos, y se deja el `auth_callback`
  que sí sirve: el de las escrituras.


## [2.5.14] - 2026-10-15

### Corregido

- **Repetir «Crear semana tipo» duplicaba los turnos** (#12): el control de existentes comparaba
  `_hora_fin` contra `'2026-11-09 13:00:00'`, pero ese metadato guarda **solo la hora** (`'13:00'`), así que
  la comparación no coincidía nunca y la segunda pasada volvía a insertar la semana entera, dejando cada
  día con dos turnos idénticos. Ahora se compara contra los formatos reales (`_hora_fin` con la hora y
  `_fecha_inicio` con la fecha y hora), y también se miran los turnos en borrador. El mismo defecto estaba
  en **duplicar semana**, y se corrige igual.

## [2.5.13] - 2026-10-15

### Corregido

- **La pantalla de Editar Turno salía sin título de documento** (#11): es la variante del #7, que ya se
  arregló en el listado. La pestaña salía como «‹ Convoca Demo — WordPress» porque la pantalla del plugin
  es oculta (padre `null`) y WordPress no le pone título. Se completa con el filtro `admin_title`, cubriendo
  las dos puertas a la pantalla: la del plugin y la de `post.php` de un turno.

## [2.5.12] - 2026-10-15

### Corregido

- **«Crear semana tipo» no generaba nada** (auditoría de navegador, #9): el saneado del POST pasaba el
  array de días —que es un array de arrays— por `sanitize_text_field()`, y esa función devuelve **cadena
  vacía** para un array, así que el generador recibía `['0' => '', '1' => '', …]`, no encontraba ningún día
  activo y respondía siempre «Se han generado 0 turnos nuevos.». El saneado se hace ahora campo a campo
  (`convoca_shifts_sanear_dias()`, en `includes/semana-tipo.php`), conservando el anidamiento.
- **Editar Turno no guardaba nunca** (#10): el campo nonce se imprimía **antes** de abrir el `<form>`, así
  que no viajaba en el POST y el manejador cortaba en la primera comprobación con «Acceso denegado.»
  (HTTP 500). El nonce va ahora dentro del formulario, con una prueba que vigila ese orden.

### Notas

- #5 (comprobación de duplicados con `get_posts()` dentro de una transacción) **no se reproduce**: las dos
  rutas que lo usan están protegidas por un bloqueo del núcleo con clave única en tabla propia
  (`convoca_lock_convoca_shifts_creating_week` / `…duplicating_week`), que serializa las peticiones antes
  de abrir la transacción. Medido: dos generaciones simultáneas de la misma semana → una crea los turnos y
  la otra no crea ninguno. Se deja escrito en el issue; el riesgo sigue latente si alguien quita el bloqueo.

## [2.5.11] - 2026-10-15

### Corregido

- **Tres defectos del calendario y del panel** (auditoría de navegador):
  - Con las horas de apertura y cierre guardadas **vacías**, el turno rápido no guardaba y los turnos
    nacían sin hora: `get_option( ..., '09:00' )` no aplica el valor por defecto cuando la opción existe
    vacía. Ahora el vacío se trata como «sin configurar» (`Horario::apertura()`/`cierre()`).
  - La pantalla «Todos los Turnos» no tenía título de documento: la pestaña salía como «‹ Convoca Demo —
    WordPress».
  - El enlace «Ir a Acceso» del calendario apuntaba a `#`: la opción de la página de acceso no se
    registraba en ningún sitio, así que no había forma de configurarla. Se registra y, si está vacía, se
    usa la página de acceso de WordPress en vez de un enlace que no lleva a ninguna parte.

## [2.5.9] - 2026-10-13

### Arreglado

- El comentario `translators:` de las casillas del listado de turnos estaba encima del `sprintf`, no de
  la propia `__()`, y Plugin Check lo rechaza (WordPress.WP.I18n.MissingTranslatorsComment). El texto
  del nombre accesible es el mismo; solo cambia dónde está el comentario.

## [2.5.8] - 2026-10-13

### Arreglado (accesibilidad, issue #2)

- **41 nodos serious/critical medidos con axe en 8 pantallas del panel**: los controles sin nombre
  accesible y los contrastes que no llegaban a AA.
  - Seis casillas de fila del listado de turnos: ahora llevan `aria-label` con la fecha del turno
    (antes el lector solo decía «casilla de verificación»).
  - Tres selects de filtro (estado, asistencia, apoyo) y **siete** selects de día de la pantalla de
    generación: ahora llevan etiqueta (visible para lectores, oculta a la vista).
  - El campo «hora de cierre» de los ajustes, que estaba rotulado solo por el texto contiguo.
  - Contraste: el gris `#94a3b8` (**2,56:1** sobre blanco) pasa a `#475569` (**7,58:1**), en el
    «No» de la columna de apoyo, en los textos del bloque PRO y en el CSS propio del plugin.

## [2.5.7] - 2026-10-13

### Arreglado

- **El aviso del turno rápido dejó de decir «Error desconocido»**: cuando el guardado no devolvía
  detalle, el usuario veía ese literal sin saber qué había pasado. Ahora explica qué intentar.

### Pruebas

- `MensajesDeErrorTest` (nuevo), probado en negativo.

## v2.5.6 (2026-10-12)

### Arreglado
- **PHPCS a 0 avisos y 0 errores** en todo el ecosistema, sin el silenciador
  `ignore_warnings_on_exit` en el CI. Entre los avisos habia defectos reales:
  un `var_dump` alcanzable por URL (`?debug=1`) que volcaba los metadatos de una
  inscripcion sin escapar, una clase `CLI_Webhooks` declarada en dos ficheros
  (fatal si se cargaban ambos) y 30 `wp_redirect` que pasan a `wp_safe_redirect`.
- 11 `@` que ocultaban errores de PHP, sustituidos por comprobaciones reales: un `@`
  no arregla nada, esconde el sintoma y deja el registro de avisos mintiendo.
- 14 `error_log()` que solo escribian en el log del servidor pasan al `Logger` del
  ecosistema, que ademas los muestra en el panel.
- `urlencode()` -> `rawurlencode()` en 4 sitios (en una query string, `urlencode`
  codifica el espacio como `+`), `in_array` con comparacion estricta, comparaciones
  `===`, `current_time('timestamp')` y `serialize()` sustituido por `wp_json_encode`.

### Notas
- Sin cambios de comportamiento en los flujos de usuario: lo arreglado son avisos
  del analizador, seguridad de redirecciones y registro de errores.

## v2.5.5 (2026-10-11)

### Corregido
- **La página de Estadísticas de voluntarios no mostraba datos y llenaba el log de errores de base de datos.** `convoca_shifts_preload_voluntario_stats()` usaba dos alias de tabla que no estaban declarados en ningún JOIN: `pm_fecha` (en el `MAX()` de la primera consulta) y `pm_fecha2` (en el `TIMESTAMPDIFF` de la segunda). MySQL devolvía `Unknown column 'pm_fecha.meta_value' in 'SELECT'`, la consulta entera fallaba y la función devolvía el array vacío; como `WP_DEBUG_DISPLAY` está en false, el error no se veía en pantalla, solo en el log, y la pantalla salía sencillamente sin datos. Reproducido en Lugg y en la demo del 11/10/2026. Se añade el `LEFT JOIN` de `_fecha_inicio` a las dos consultas y `pm_fecha2` pasa a ser `pm_fecha`.

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
