( function( blocks, element, serverSideRender, blockEditor, components ) {
    var el = element.createElement;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var RangeControl = components.RangeControl;

    // Helper to register simple dynamic blocks (no attributes)
    function registerCSTBlock( name, title, icon ) {
        blocks.registerBlockType( 'centro-social/' + name, {
            apiVersion: 3,
            title: title,
            icon: icon,
            category: 'convoca-turnos',
            edit: function( props ) {
                if ( ! serverSideRender ) {
                    return el( 'div', { className: 'cst-block-editor-preview' }, 'Error: wp.serverSideRender no disponible.' );
                }
                return el( 'div', { className: 'cst-block-editor-preview' },
                    el( serverSideRender, {
                        block: 'centro-social/' + name,
                        attributes: props.attributes,
                    } )
                );
            },
            save: function() {
                return null; // Rendered by PHP
            },
        } );
    }

    // Register simple blocks
    registerCSTBlock( 'calendario', 'CST: Calendario', 'calendar-alt' );
    registerCSTBlock( 'boton-apuntarse', 'CST: Botón Apuntarse', 'plus-alt' );

    // Resumen Semanal — with week selector
    blocks.registerBlockType( 'centro-social/resumen', {
        apiVersion: 3,
        title: 'CST: Resumen Semanal',
        icon: 'chart-bar',
        category: 'convoca-turnos',
        attributes: {
            semana: {
                type: 'string',
                default: 'this'
            }
        },
        edit: function( props ) {
            var semana = props.attributes.semana;
            return el( element.Fragment, {},
                el( InspectorControls, {},
                    el( PanelBody, { title: 'Configuración', initialOpen: true },
                        el( components.RadioControl, {
                            label: 'Semana a mostrar',
                            selected: semana,
                            options: [
                                { label: 'Semana actual', value: 'this' },
                                { label: 'Próxima semana', value: 'next' }
                            ],
                            onChange: function( val ) {
                                props.setAttributes( { semana: val } );
                            }
                        } )
                    )
                ),
                el( 'div', { className: 'cst-block-editor-preview' },
                    serverSideRender ?
                        el( serverSideRender, {
                            block: 'centro-social/resumen',
                            attributes: props.attributes,
                        } ) :
                        'Error: wp.serverSideRender no disponible.'
                )
            );
        },
        save: function() {
            return null;
        }
    } );

    // Próximos Turnos — with InspectorControls for 'cantidad'
    blocks.registerBlockType( 'centro-social/proximos-turnos', {
        apiVersion: 3,
        title: 'CST: Próximos Turnos',
        icon: 'list-view',
        category: 'convoca-turnos',
        attributes: {
            cantidad: {
                type: 'number',
                default: 5
            }
        },
        edit: function( props ) {
            var cantidad = props.attributes.cantidad;

            return el( element.Fragment, {},
                el( InspectorControls, {},
                    el( PanelBody, { title: 'Configuración', initialOpen: true },
                        el( RangeControl, {
                            label: 'Cantidad de turnos a mostrar',
                            value: cantidad,
                            onChange: function( val ) {
                                props.setAttributes( { cantidad: val } );
                            },
                            min: 1,
                            max: 20,
                            step: 1
                        } )
                    )
                ),
                el( 'div', { className: 'cst-block-editor-preview' },
                    serverSideRender ?
                        el( serverSideRender, {
                            block: 'centro-social/proximos-turnos',
                            attributes: props.attributes,
                        } ) :
                        'Error: wp.serverSideRender no disponible.'
                )
            );
        },
        save: function() {
            return null;
        }
    } );

} )( window.wp.blocks, window.wp.element, window.wp.serverSideRender, window.wp.blockEditor, window.wp.components );
