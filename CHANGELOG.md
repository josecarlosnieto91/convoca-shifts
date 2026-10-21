## [2.5.32] — 2026-10-21

### Añadido

- **La asignación se valida al guardar, no sólo al pintar.** El selector ya sólo ofrecía personas de la
  lista, pero un POST a mano podía traer cualquier id. Ahora los siete sitios que escriben `_monitor` y
  `_id_responsable` pasan por `Personas_Asignables::id_a_guardar()`: se guarda el id sólo si está en la
  lista, y si no se **conserva lo que había** (un rechazo no borra una asignación anterior).
- **Liberar un turno se puede siempre**, también sin gestión de personas: quitar a alguien no es
  asignarlo, y bloquearlo dejaría turnos con un monitor que ya no debería estar. Los dos caminos que
  ponen el responsable a 0 no se tocan.

### Pruebas

- 3 casos nuevos: el id colado se rechaza, el rechazo no borra la asignación previa y liberar funciona
  en los dos contextos.

## [2.5.31] — 2026-10-21

### Cambiado

- **Shifts deja de administrar miembros.** No crea roles ni les añade capacidades: el vocabulario de
  personas (`voluntario_aprobado`, `monitor_actividad`) lo define y lo crea **Convoca Core**, y quién es
  voluntario o monitor lo dicen sus fichas y aprobaciones. Antes Shifts creaba el rol «por si faltaba», y
  eso era lo que permitía asignar gente a los turnos en un sitio **sin** gestión de personas.
- **Las personas se piden, no se buscan.** Los ocho sitios que hacían `get_users()` por rol o por
  capacidad (tres selectores de voluntario, dos de monitor, la auditoría de horas y las dos pantallas de
  estadísticas) usan ahora el contrato `convoca_shifts_personas_asignables` (clase
  `Personas_Asignables`), con **valor por defecto vacío**. Sin nadie que lo aporte no hay selector, no se
  puede asignar y no hay horas que auditar, que es la regla de producto.
- La nota en la pantalla del turno explica por qué no hay a nadie a quien asignar en vez de mostrar un
  desplegable vacío, y el aviso del rol manda a Convoca Core, que es quien lo mantiene.

### Pruebas

- 8 casos nuevos en `PersonasAsignablesTest`: por defecto no hay nadie, dos contextos que no se mezclan,
  `permitida()` sólo acepta lo que está en la lista, admite ids sueltos y objetos, no duplica, y lo que
  devuelva el filtro no puede romper la pantalla.

## [2.5.30] — 2026-10-21

### Cambiado

- **Aporta sus comprobaciones de diagnóstico** al núcleo por el filtro `convoca_system_checks`.

## [2.5.29] — 2026-10-20

### Arreglado

- **La detección de shortcodes usaba nombres que no existen.** La lista era
  `calendario_centro, boton_apuntarse, resumen_turnos, proximos_turnos`; los shortcodes reales
  llevan prefijo `convoca_`. `has_shortcode()` devolvía siempre `false`, así que una página con
  `[convoca_calendario]` dependía del encolado tardío del shortcode y su hoja acababa en el footer.
- **El editor enseñaba el calendario sin estilos.** En `wp_enqueue_scripts` había una rama
  `if ( is_admin() )` que no se ejecuta nunca (ese hook solo dispara en el front) y los bloques no
  declaraban `style`: la previsualización SSR se pintaba desnuda. Ahora `$common_args` declara el
  estilo y la rama muerta está fuera.
- **`dashicons` viajaba en todas las páginas del sitio.** Se encolaba en `init`; el único icono del
  front es el del botón de apuntarse, que ya lo encola.
- **Tres clases del front sin ninguna regla**: `convoca-shifts-suscripcion`, su `__url` y
  `convoca-shifts-proximos-lista` (la URL del `.ics` salía como texto suelto).

### Cambiado

- La hoja depende ya del handle `convoca-core` (la capa común no llegaba por dependencia).
- Los títulos de bloque dejan de llamarse `CST:` y pasan a `Convoca:` (se veían así en el insertador
  de cualquier instalación).
- `Requires Plugins` deja de exigir `convoca-members`: el código no usa ni una clase ni una función
  de Members.
- El CSV de ejemplo de la prueba del importador usa nombres neutros.

## [2.5.28] — 2026-10-20

### Cambiado
- La plantilla CSV de importación ya no trae un identificador de persona real en las columnas de
  monitor y responsable: usa marcadores neutros (`monitor-ejemplo`, `responsable-ejemplo`).

## [2.5.27] — 2026-10-16

### Arreglado
- **El aviso salía sin fecha ni hora: «¡Te has apuntado al turno del  a las !» (issue #20).** El turno se
  guardaba como `2026-10-23 :00` cuando la hora iba vacía —no es una fecha válida—, y el aviso hacía
  `wp_date()` sobre el resultado de `strtotime()` **sin comprobarlo**: `false` entra, cadena vacía sale.
  Ahora un turno sin hora se guarda como fecha completa con `_sin_hora` marcado, y el aviso dice el día
  y **no inventa una hora**; si la fecha viene mal formada, cae al título del turno en vez de imprimir
  espacios en blanco.
- **El modal de crear turno quedaba claro sobre claro en modo oscuro (~1,08:1, issue #21).** Usaba
  `--wp--preset--color--blanco`, un preset que el tema no remapea en `.dark-mode`, con el texto en
  `antracita`, que en oscuro vale #F4F6F8. Ahora el modal, sus botones de preset, las etiquetas, los
  campos y el bloque de hora personalizada se pintan con los tokens oscuros del tema.

## [2.5.26] — 2026-10-16

### Arreglado
- **El calendario era ilegible con el tema en modo oscuro.** El plugin fijaba colores claros a mano: la
  leyenda en `#f9f9f9` con texto `#f4f6f8` (un contraste de ~1:1, texto sobre su propio color), los fines
  de semana en `#fffcf8` con `!important`, los días futuros en blanco y el «hoy» en amarillo. Sobre el
  fondo oscuro del tema no se leía nada. Ahora usa los tokens del tema (`--dark-surface`, `--dark-elevated`,
  `--dark-bg`, `--dark-text`) en un bloque `dark-mode`, sin fijar ningún color claro. Medido en las tres
  vistas (mes, semana y lista): cero fondos claros.
### Añadido
- `tests/Unit/ModoOscuro*`: vigila que el estilo no vuelva a fijar colores claros para el modo oscuro.

## [2.5.25] — 2026-10-16

### Arreglado
- **El botón «Me apunto al siguiente turno libre» no hacía nada (issue #17).** Su JS usaba `cstData`, un objeto
  que **no existe**: el script localiza `convocaShiftsData` (y es el que usa `calendario.js`, por eso el calendario
  sí funcionaba). Al pulsar, el navegador lanzaba un `ReferenceError`: ni llamada al endpoint —que existe y
  funciona— ni mensaje en pantalla, así que el voluntario se quedaba creyendo que se había apuntado. Ahora usa el
  objeto que se localiza de verdad, **avisa si falta la configuración** en vez de callarse, y el refresco del
  calendario busca la instancia con su nombre real (`convocaShiftsCalendarInstance`).
- **El listado de próximos turnos mostraba el código a la vista (issue #18).** El estado se imprimía con
  `esc_html()` y el estado lleva HTML: en la página se leían las etiquetas (`<span style="color:#d35400…">⚠ Sin
  cubrir</span>`). Se escapa el nombre del responsable al construir la cadena y se interpreta el marcado al
  pintarlo (`wp_kses_post`). Y un turno creado **sin horario** ya no se anuncia «a las 00:00», que parece una hora
  y no lo es: dice «sin hora fijada».

## [2.5.23] — 2026-10-15

## [2.5.24] — 2026-10-15

### Cambiado
- **Los dos exportadores de CSV usan la ceremonia del núcleo (issue convoca-core#16).** El listado de turnos y las
  estadísticas de voluntariado vuelcan ya por `\Convoca\Core\Utils::stream_csv()`, con generador para las filas.
- **Contrato `UnaSolaCeremoniaCsvTest`** (probado en negativo): nadie abre `php://output` ni llama a `fputcsv` en este
  plugin, y los dos exportadores declarados usan el ayudante.

### Corregido
- **El listado de turnos no escapaba NINGUNA celda.** Las notas de un turno que empezaran por `=`, `+`, `-` o `@`
  viajaban tal cual a la hoja de cálculo de quien abre el CSV (inyección de fórmulas).
- **Los dos salían con el separador por omisión de PHP (`,`)**, que Excel en español no reconoce.
- **Las estadísticas dejan de perder los ceros:** un voluntario con 0 faltas o 0 turnos realizados salía con la celda
  en blanco, y el escapado dependía de un `class_exists()` (si el núcleo no estaba cargado, no protegía nada).

### Corregido
- **Los assets se servían con `?ver=2.5.10` yendo el plugin por la 2.5.22 (issue #15).** La constante de
  versión hacía **dos trabajos con requisitos opuestos**: cache-buster de los assets —que sube en cada
  release— y fingerprint de las migraciones (`Convoca_Shifts_Upgrade_Manager::get_db_version()`), que no tiene
  por qué moverse cuando no cambia ninguna migración. Fundidas, se quedó atrás **doce releases**.

### Cambiado
- Las dos versiones quedan **separadas**, como ya estaban en gateway: `CONVOCA_SHIFTS_VERSION` es la del
  plugin (derivada de la cabecera, no un literal) y `CONVOCA_SHIFTS_DB_VERSION` es la de la base de datos,
  con **el valor que ya tenía** (2.5.10) para no disparar ninguna migración que no tocaba. `get_db_version()`
  lee la nueva.
- Contrato nuevo (`tests/Unit/VersionYFingerprintTest.php`), probado en negativo contra el fichero anterior.

## [2.5.22] — 2026-10-15

### Arreglado
- **La desinstalación no borraba tres opciones del plugin (issue #10).** `convoca_shifts_aviso_socio_en`,
  `convoca_shifts_aviso_admin_cada` y `convoca_shifts_ventana_dias` se guardaban y se quedaban en la base para
  siempre. Las ha encontrado la **prueba de contrato** que lee las claves del propio código.

### Pruebas
- `tests/Unit/OpcionesSeBorranAlDesinstalarTest.php`. **Negativo probado**: quitando una opción, la prueba cae y
  la nombra.

## [2.5.21] — 2026-10-15

### Corregido
- **Un «SÍ» con tilde y en mayúsculas no contaba como sí.** `strtolower()` es byte a byte y no baja las vocales
  acentuadas, así que `SÍ` —tal cual se escribe muchas veces en una hoja de cálculo— se quedaba fuera de la
  lista y la columna «apoyo» salía en blanco. Ahora se quitan los acentos antes de bajar a minúsculas y se
  aceptan también `Verdadero` y `V`. Lo destapó el arnés de verificación, no la suite.

### Pruebas
- El caso del «sí» cubre ahora las dos cajas y las tildes. **Negativo probado**: volviendo al `strtolower`
  simple, la prueba cae.
- Suite 87 en verde, PHPCS 0, PHPStan `[OK]`.

## [2.5.20] — 2026-10-15

### Arreglado
- **El exportador de turnos a CSV no tenía botón.** Existía desde hace tiempo —`convoca_shifts_exportar_csv_turnos()`,
  enganchado a `admin_init`, con su nonce y sus columnas— pero **ninguna pantalla lo lanzaba**: la promesa de la
  web estaba en el código y no había forma de usarla. Ahora tiene su botón en el listado de turnos, que es donde
  se busca («📥 Exportar a CSV»).
- Prueba nueva `AccionesConBotonTest`: recorre las acciones que el plugin sabe atender y exige que cada una
  aparezca en algo que la dispare. Una función que sólo se puede llamar por correo no existe para quien usa el
  plugin. **Negativo probado**: quitando el botón, la prueba cae.

### Pruebas
- Suite 85 en verde, PHPCS 0, PHPStan `[OK]`.

## [2.5.19] — 2026-10-15

### Corregido
- **La descarga de la plantilla devolvía HTML.** Medido en la demo: `header()` dentro del render de la página
  llega tarde —WordPress ya ha mandado la cabecera y pintado el marco del panel—, así que el botón de descarga
  entregaba la plantilla envuelta en la página. Se atiende en `admin_init`, antes de que exista salida.

### Pruebas
- La prueba de contrato exige ahora que la descarga esté enganchada en `admin_init`, no en el render.
- Suite 84 en verde, PHPCS 0, PHPStan `[OK]`.

## [2.5.18] — 2026-10-15

### Añadido
- **Importación de turnos desde plantilla (issue #13).** La web prometía «Importa plantillas desde Excel para
  alta masiva de turnos» y no existía: el alta masiva era rellenar una semana en el panel, que no es un
  fichero. Ahora hay una página **«Importar turnos»** en el menú de turnos, con la plantilla descargable y la
  subida del fichero relleno.
  - Se lee **CSV**, que es lo que Excel abre y guarda de serie, sin meter una librería de hojas de cálculo en el
    plugin. Se entienden las dos costumbres: la coma y el **punto y coma** de Excel en español, y el BOM que
    mete al guardar.
  - Las fechas se leen como se escriben aquí: **`05/11/2026` es el 5 de noviembre**, no el 11 de mayo; también
    valen `5-11-2026` y `2026-11-05`. Las horas se normalizan (`9:00` → `09:00`).
  - Cada fila mala se explica con **su número de línea** y no se lleva por delante las buenas. La actividad y
    las personas se resuelven por nombre, usuario, correo o número.
  - **No duplica**: un turno que ya existe ese día a esa hora se cuenta como repetido y se deja en paz, con el
    mismo criterio que el generador de semanas.
  - La creación de cada turno se delega en `convoca_shifts_insert_turno()`, la del generador: horario del
    centro, estado y título se calculan en un solo sitio.

### Pruebas
- `tests/Unit/ImportadorTest.php`: la plantilla que se descarga **se importa a sí misma** sin errores, el
  separador de Excel, el BOM, que una fila mala no arrastre a las buenas y diga en qué línea, y que `05/11`
  sea noviembre. **Negativos probados**: poniendo el orden `m/d/Y` primero caen dos pruebas, y volviendo a
  `array_shift` cae la de los números de línea.
- Suite 84 en verde, PHPCS 0, PHPStan `[OK]`. Sin deprecaciones (`str_getcsv` con el escape explícito).

## [2.5.17] — 2026-10-15

### Corregido
- **El feed partía líneas cortas por la mitad.** Medido en la demo, con el feed ya sirviendo: el evento llega
  unido con CRLF y se plegaba como si fuera **una sola línea**, así que el corte caía donde tocara —un
  `DTSTAMP` de 24 caracteres salió partido en dos y el resumen quedó «SUMMARY:🟢 C» / «ubierto»—. Ahora se pliega
  **cada línea por su cuenta**, y la prueba lo deja por escrito: una línea corta no puede partirse.

### Pruebas
- Caso nuevo en `IcalTest` que coloca una línea corta justo en el punto donde caería el corte del bloque.
  **Negativo probado**: volviendo a plegar el bloque entero, la prueba cae («no contiene SUMMARY:Turno corto»).
- Suite 75 en verde, PHPCS 0, PHPStan `[OK]`.

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
