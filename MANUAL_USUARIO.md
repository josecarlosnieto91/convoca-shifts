# MANUAL_USUARIO.md — Convoca Shifts v2.5.1

> Guía para administradores: gestión de turnos de voluntariado.

## 1. Introducción

Convoca Shifts gestiona turnos de voluntariado para centros sociales y asociaciones. Incluye un calendario visual con FullCalendar, creación rápida de turnos, duplicado de semanas completas, y exportación CSV.

**Requiere:** convoca-core (recomendado).

**Integración en cualquier sitio:** Perfecto para centros sociales, asociaciones o espacios comunitarios. El shortcode `[convoca_calendario]` muestra el calendario en cualquier página.

## 2. Crear un turno

### Método 1: Añadir nuevo

1. Ve a **Turnos → Añadir nuevo**
2. Rellena el título (ej: "Recepción — Lunes mañana")
3. En el panel lateral **Datos del turno**:

| Campo | Descripción |
|-------|-------------|
| **Fecha y hora de inicio** | Cuándo empieza el turno |
| **Fecha y hora de fin** | Cuándo termina |
| **Voluntario asignado** | Selecciona del listado de voluntarios |
| **Estado** | Abierto, Asignado, Completado, Cancelado |

4. Haz clic en **Publicar**

### Método 2: Añadir rápido

1. Ve a **Turnos → Añadir rápido**
2. Selecciona fecha, hora inicio, duración
3. Escribe título y selecciona voluntario (opcional)
4. Haz clic en **Crear turno**

Ideal para crear varios turnos seguidos sin recargar la página.

## 3. Calendario visual

Accede desde **Turnos → Calendario**:

- Vista mensual, semanal y diaria
- Colores por tipo de turno (mañana, tarde, noche)
- Click en un turno para ver detalles
- Drag & drop para cambiar fecha (próximamente)

### Shortcode

```
[convoca_calendario]
```

Atributos: `vista="mes"`, `categoria="recepcion"`.

Otros shortcodes disponibles:

```
[convoca_proximos_turnos]   Lista de los próximos turnos
[convoca_resumen_turnos]    Resumen de turnos
[convoca_boton_apuntarse]   Botón de apuntarse a un turno
```

## 4. Duplicar semana

Si tienes una semana tipo con turnos recurrentes:

1. Ve a **Turnos → Duplicar semana**
2. Selecciona la semana origen (lunes a domingo)
3. Selecciona la semana destino
4. Haz clic en **Duplicar**
5. Todos los turnos de la semana origen se copian a la destino

⚠️ Los turnos duplicados heredan el estado "Abierto" (no el voluntario asignado).

## 5. Gestión de voluntarios en turnos

- **Asignar**: Edita el turno y selecciona un voluntario del desplegable
- **Aprobar**: El voluntario puede confirmar su asistencia desde el frontend
- **Revocar**: Un administrador puede quitar la asignación

## 6. Exportar CSV

Desde **Turnos → Exportar**:

- Selecciona rango de fechas
- Elige columnas a incluir
- Descarga el CSV

## 7. Sincronización de horas

Cuando un turno se marca como "Completado", las horas trabajadas se registran automáticamente en Convoca Members (si está activo).

## 8. Certificados de turnos

Genera un PDF con el historial de turnos de un voluntario desde **Turnos → [voluntario] → Certificado**.

## 9. API REST

Namespace: `convoca-shifts/v1`.

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/turnos` | Lista de turnos (filtros: fecha, estado) |
| GET | `/turnos/{id}` | Detalle de un turno |
| POST | `/turnos/{id}/apuntarse` | Inscribir voluntario en un turno |
| POST | `/turnos/{id}/desapuntarse` | Desinscribir voluntario de un turno |
| POST | `/turnos/apuntarse-proximo` | Inscribirse en el próximo turno disponible |
| POST | `/turnos/crear` | Crear un turno (admin) |
| GET | `/turnos/proximo-libre` | Próximo turno con plazas libres |

## 10. Problemas comunes

| Problema | Solución |
|----------|----------|
| **Turno no aparece en calendario** | Verifica que el estado no es "Borrador" ni la fecha pasada |
| **Error al duplicar semana** | Asegúrate de que hay turnos en la semana origen |
| **Horas no se sincronizan con Members** | Verifica que Convoca Members está activo |
