# Guía de Instalación y Configuración

## 1. Requisitos
- WordPress 6.0+
- PHP 7.4+

## 2. Instalación
1. Sube la carpeta `biodevas-centro-social` a `/wp-content/plugins/`.
2. Activa el plugin desde el panel de WordPress.
3. El plugin creará automáticamente:
   - El rol **Voluntario Aprobado**.
   - La tabla de logs de actividad.
   - Los endpoints de la API REST.

## 3. Configuración de Páginas
Para que el sistema sea funcional, debes crear dos páginas:

### A. Página de Calendario
- Crea una página (ej. "Cuadrante de Turnos").
- Pega el shortcode: `[calendario_centro]`
- (Opcional) Añade `[resumen_turnos]` arriba para un resumen rápido.

### B. Página de Registro (Members)
- Crea una página (ej. "Registro de Voluntariado").
- Pega el shortcode de Biodevas Members: `[biodevas_voluntariado]`
- **IMPORTANTE**: Asegúrate de que el plugin `biodevas-members` esté activo.

## 4. Flujo de Trabajo Recomendado
1. **Paso 1**: Publica las páginas con los shortcodes.
2. **Paso 2**: Ve a **Turnos Centro > Generar Turnos** y crea los turnos para el próximo mes.
3. **Paso 3**: Invita a los voluntarios a registrarse.
4. **Paso 4**: Ve a **Turnos Centro > Gestionar Voluntarios** para activar a los usuarios que se registren.

## 5. Shortcodes Detallados
- `[calendario_centro]`: El componente principal.
- `[biodevas_voluntariado]` (Plugin Members): Imprescindible para el flujo de alta.
- `[boton_apuntarse]`: Ideal para la barra lateral o páginas de inicio.
- `[resumen_turnos]`: Muestra visualmente cuántos huecos quedan esta semana.
- `[proximos_turnos]`: Lista simple de fechas y horas.

## 6. Resolución de Problemas
- **El calendario no carga**: Asegúrate de que los Permalinks (Ajustes > Enlaces permanentes) NO estén en "Simple". Usa "Nombre de la entrada".
- **Error 401/403 en API**: Revisa que no tengas plugins de seguridad bloqueando la REST API de WordPress.
