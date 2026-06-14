jQuery(document).ready(function ($) {
    
    window.initConvocaShiftsCalendar = function(container) {
        if (typeof FullCalendar === 'undefined') {
            console.error('FullCalendar is not loaded.');
            return;
        }
        var $el = $(container).find('#convoca-shifts-calendario');

        // Overlap check helper
        function hasOverlap(newEvent) {
            var cal = window.convocaShiftsCalendarInstance;
            if (!cal) return false;
            var myEvents = cal.getEvents().filter(function(ev) {
                return parseInt(ev.extendedProps.responsable_id) === parseInt(convocaShiftsData.userId);
            });
            var newStart = newEvent.start;
            var newEnd = newEvent.end || new Date(newStart.getTime() + 2 * 60 * 60 * 1000); // Default 2h if no end
            return myEvents.some(function(ev) {
                var evStart = ev.start;
                var evEnd = ev.end || new Date(evStart.getTime() + 2 * 60 * 60 * 1000);
                return newStart < evEnd && newEnd > evStart;
            });
        }
        if ($el.length === 0) return;

        // Loop through each calendar found (in case there are multiple blocks)
        $el.each(function() {
            var calendarEl = this;
            if ($(calendarEl).hasClass('fc')) return;

            function getInitialView() {
                var savedView = localStorage.getItem('convoca_shifts_calendar_view_' + calendarEl.id);
                if (savedView && ['dayGridMonth', 'timeGridWeek', 'listWeek'].includes(savedView)) {
                    return savedView;
                }
                return window.innerWidth < 768 ? 'listWeek' : 'dayGridMonth';
            }

            var isAutoChangingView = false;

            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: getInitialView(),
                datesSet: function (dateInfo) {
                    if (!isAutoChangingView) {
                        localStorage.setItem('convoca_shifts_calendar_view_' + calendarEl.id, dateInfo.view.type);
                    }
                },
                locale: 'es',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,listWeek'
                },
                buttonText: {
                    today: 'Hoy',
                    month: 'Mes',
                    week: 'Semana',
                    list: 'Lista'
                },
                firstDay: 1,
                dayMaxEvents: 2,
                moreLinkText: function (n) { return '+ ' + n + ' más'; },
                eventDisplay: 'block',
                noEventsContent: function () {
                    var html = convocaShiftsData.msgNoEvents;
                    if (convocaShiftsData.canManage) {
                        html += ' <a href="/wp-admin/edit.php?post_type=centro_turno&page=cst_generar_turnos">¿Te animas a crear una semana tipo?</a>';
                    }
                    return { html: html };
                },
                windowResize: function (view) {
                    if (window.innerWidth < 768) {
                        if (calendar.view.type !== 'listWeek') {
                            isAutoChangingView = true;
                            calendar.changeView('listWeek');
                            isAutoChangingView = false;
                        }
                    } else {
                        if (calendar.view.type === 'listWeek') {
                            var savedView = localStorage.getItem('convoca_shifts_calendar_view_' + calendarEl.id);
                            if (savedView && savedView !== 'listWeek') {
                                isAutoChangingView = true;
                                calendar.changeView(savedView);
                                isAutoChangingView = false;
                            } else if (!savedView) {
                                isAutoChangingView = true;
                                calendar.changeView('dayGridMonth');
                                isAutoChangingView = false;
                            }
                        }
                    }
                },
                events: function (fetchInfo, successCallback, failureCallback) {
                    $.ajax({
                        url: convocaShiftsData.restUrl,
                        type: 'GET',
                        data: {
                            start: fetchInfo.startStr,
                            end: fetchInfo.endStr
                        },
                        success: function (response) {
                            successCallback(response);
                            var $resumen = $('.convoca-shifts-resumen-turnos');
                            if ($resumen.length > 0) {
                                var total = 0;
                                var sin_cubrir = 0;
                                response.forEach(function (ev) {
                                    if (ev.extendedProps.estado !== 'cerrado') {
                                        total++;
                                        if (ev.extendedProps.responsable_id === 0) {
                                            sin_cubrir++;
                                        }
                                    }
                                });
                                if (total > 0) {
                                    $resumen.html('📅 En esta vista hay <strong>' + sin_cubrir + ' turnos sin cubrir</strong> de ' + total + ' en total.');
                                } else {
                                    $resumen.html('📅 No hay turnos definidos en esta vista.');
                                }
                            }
                        },
                        error: function () {
                            failureCallback();
                        }
                    });
                },
                eventDidMount: function (info) {
                    var event = info.event;
                    var props = event.extendedProps;
                    var $el = $(info.el);
                    var isMonth = calendar.view.type === 'dayGridMonth';
                    var isList = calendar.view.type === 'listWeek';
                    var isTimeGrid = calendar.view.type === 'timeGridWeek' || calendar.view.type === 'timeGridDay';

                    if (props.necesita_apoyo === 1) {
                        var titleEl = $el.find('.fc-event-title, .fc-list-event-title');
                        if (titleEl.length) {
                            titleEl.append(' <span class="convoca-shifts-badge-apoyo" title="Este turno necesita apoyo (sin llaves / acompañamiento)">🛟 Apoyo</span>');
                        }
                    }

                    if (props.estado === 'abierto_ocupado') {
                        if (isList) {
                            var details = [];
                            if (props.actividad) details.push('<b>Actividad:</b> ' + props.actividad);
                            if (props.monitor) details.push('<b>Monitor:</b> ' + props.monitor);
                            if (details.length) {
                                $el.find('.fc-list-event-title').append(' <span class="convoca-shifts-resp-inline">(' + details.join(' | ') + ')</span>');
                            }
                        } else if (!isMonth) {
                            if (props.actividad) {
                                $el.find('.fc-event-main').append('<div class="convoca-shifts-event-resp">📝 ' + props.actividad + '</div>');
                            }
                            if (props.monitor) {
                                $el.find('.fc-event-main').append('<div class="convoca-shifts-event-resp">👤 ' + props.monitor + '</div>');
                            }
                        }
                    } else {
                        if (props.responsable_nombre) {
                            if (isList) {
                                $el.find('.fc-list-event-title').append(' <span class="convoca-shifts-resp-inline">(👤 ' + props.responsable_nombre + ')</span>');
                            } else if (!isMonth) {
                                var respHtml = '<div class="convoca-shifts-event-resp">👤 ' + props.responsable_nombre + '</div>';
                                $el.find('.fc-event-main').append(respHtml);
                            }
                        } else if (props.monitor) {
                            if (isList) {
                                $el.find('.fc-list-event-title').append(' <span class="convoca-shifts-resp-inline">(👤 ' + props.monitor + ')</span>');
                            } else if (!isMonth) {
                                var monitorHtml = '<div class="convoca-shifts-event-resp">👤 ' + props.monitor + '</div>';
                                $el.find('.fc-event-main').append(monitorHtml);
                            }
                        }
                    }

                    if (props.actividad_url) {
                        var linkHtml = '<a href="' + props.actividad_url + '" target="_blank" class="convoca-shifts-event-link" onclick="event.stopPropagation();">🌐 Más información</a>';
                        if (isList) {
                            $el.find('.fc-list-event-title').append(' ' + linkHtml);
                        } else {
                            $el.find('.fc-event-main').append(linkHtml);
                        }
                    }

                    if (props.notas) {
                        var notesHtml = '<span class="convoca-shifts-notes-icon" title="' + props.notas.replace(/"/g, '&quot;') + '"> 📝</span>';
                        $el.find('.fc-event-title, .fc-list-event-title').append(notesHtml);
                    }

                    if (!isList && !isTimeGrid) {
                        var timeStr = event.start.getHours() + ':' + (event.start.getMinutes() < 10 ? '0' : '') + event.start.getMinutes();
                        $el.find('.fc-event-main').prepend('<span class="convoca-shifts-event-time-top">' + timeStr + '</span>');
                    }

                    if (convocaShiftsData.canManage && props.estado === 'abierto_disponible' && props.responsable_id == convocaShiftsData.userId) {
                        var btnHtml = '<button class="convoca-shifts-event-action convoca-shifts-btn-liberar" title="Liberar mi turno">Liberar mi turno</button>';
                        if (isList) {
                            $el.find('.fc-list-event-title').append(btnHtml);
                        } else {
                            $el.find('.fc-event-main').append(btnHtml);
                        }

                        $el.find('.convoca-shifts-btn-liberar').on('click', function (e) {
                            e.stopPropagation(); e.preventDefault();
                            if (!confirm(convocaShiftsData.confirmLiberar)) return;
                            var $btn = $(this);
                            var originalText = $btn.text();
                            $btn.text('...');
                            $el.css('opacity', '0.5');
                            $el.css('pointer-events', 'none');
                            $.ajax({
                                url: convocaShiftsData.restUrl + '/' + event.id + '/desapuntarse',
                                type: 'POST',
                                beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', convocaShiftsData.nonce); },
                                success: function (response) {
                                    if (response.success) { calendar.refetchEvents(); } 
                                    else { alert(response.message || convocaShiftsData.msgError); $btn.text(originalText); $el.css('opacity', '1'); }
                                },
                                error: function (xhr) {
                                    var res = xhr.responseJSON;
                                    alert((res && res.message) ? res.message : convocaShiftsData.msgError);
                                    $btn.text(originalText); $el.css('opacity', '1');
                                },
                                complete: function () { $el.css('pointer-events', 'auto'); }
                            });
                        });
                    }
                },
                eventClick: function (info) {
                    var event = info.event;
                    var props = event.extendedProps;
                    console.log('CST eventClick:', event.id, props.estado, props.responsable_id, 'userId:', convocaShiftsData.userId, 'canManage:', convocaShiftsData.canManage);
                    if (props.estado === 'abierto_ocupado' && props.actividad_url) { window.open(props.actividad_url, '_blank'); return; }
                    if (!convocaShiftsData.canManage) { console.log('CST: blocked by canManage'); return; }
                    if (props.estado === 'abierto_disponible' && props.responsable_id === 0) {
                        if (convocaShiftsData.confirmSignup && !confirm('¿Te apuntas a este turno? Serás responsable de abrir el centro.')) return;
                        // Overlap check
                        if (convocaShiftsData.userId > 0 && hasOverlap(event)) {
                            if (!confirm('⚠️ Este turno se solapa con otro turno que ya tienes asignado. ¿Aún quieres apuntarte?')) return;
                        }
                        var $el = $(info.el);
                        $el.css('opacity', '0.5'); $el.css('pointer-events', 'none');
                        console.log('CST: sending AJAX to', convocaShiftsData.restUrl + '/' + event.id + '/apuntarse');
                        $.ajax({
                            url: convocaShiftsData.restUrl + '/' + event.id + '/apuntarse',
                            type: 'POST',
                            beforeSend: function (xhr) { 
                                console.log('CST: setting nonce header:', convocaShiftsData.nonce);
                                xhr.setRequestHeader('X-WP-Nonce', convocaShiftsData.nonce); 
                            },
                            success: function (response) {
                                console.log('CST: AJAX success', response);
                                if (response.success) { calendar.refetchEvents(); } 
                                else { alert(response.message || convocaShiftsData.msgError); $el.css('opacity', '1'); if (response.code === 'ya_cubierto') calendar.refetchEvents(); }
                            },
                            error: function (xhr) {
                                console.log('CST: AJAX error', xhr.status, xhr.responseJSON);
                                var res = xhr.responseJSON;
                                alert((res && res.message) ? res.message : convocaShiftsData.msgError);
                                $el.css('opacity', '1'); if (res && res.code === 'ya_cubierto') calendar.refetchEvents();
                            },
                            complete: function () { $el.css('pointer-events', 'auto'); }
                        });
                    }
                },
                dateClick: function (info) {
                    if (!convocaShiftsData.canManage) return;
                    const today = new Date(); today.setHours(0, 0, 0, 0);
                    if (info.date < today) return;
                    const modal = $('#convoca-shifts-frontend-modal');
                    const dateStr = info.dateStr;
                    $('#convoca-shifts-fe-modal-title').text('Crear Turno: ' + info.date.toLocaleDateString());
                    $('.convoca-shifts-preset-btn').removeClass('active');
                    $('.convoca-shifts-preset-btn[data-start="10:00"]').addClass('active');
                    $('#convoca-shifts-custom-time-fields').hide();
                    $('#fe_h_start').val('10:00'); $('#fe_h_end').val('13:00');
                    modal.data('date', dateStr).addClass('is-active');
                }
            });

            calendar.render();
            $(calendarEl).addClass('fc'); // Mark as initialized
            window.convocaShiftsCalendarInstance = calendar;

            // Load Proximo Libre for summary
            if ($('.convoca-shifts-proximo-libre').length > 0) {
                $.ajax({
                    url: convocaShiftsData.restUrl + '/proximo-libre',
                    type: 'GET',
                    success: function (response) { $('.convoca-shifts-proximo-libre').html(response.texto); }
                });
            }
        });
    }

    // Initial load
    window.initConvocaShiftsCalendar(document);

    // MutationObserver to detect changes in the DOM
    var observer = new MutationObserver(function(mutations) {
        window.initConvocaShiftsCalendar(document);
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

    // Frontend Modal Logic (already bound once is enough)
    $(document).on('click', '.convoca-shifts-close-frontend, .convoca-shifts-fe-cancel', function () {
        $('#convoca-shifts-frontend-modal').removeClass('is-active');
    });

    $(document).on('click', '.convoca-shifts-preset-btn', function () {
        $('.convoca-shifts-preset-btn').removeClass('active');
        $(this).addClass('active');
        const isCustom = $(this).data('custom');
        if (isCustom) { $('#convoca-shifts-custom-time-fields').slideDown(); } 
        else {
            $('#convoca-shifts-custom-time-fields').slideUp();
            let start = $(this).data('start'); let end = $(this).data('end');
            const limitOpen = convocaShiftsData.horaApertura; const limitClose = convocaShiftsData.horaCierre;
            let adjusted = false;
            if (start < limitOpen) { start = limitOpen; adjusted = true; }
            if (end > limitClose) { end = limitClose; adjusted = true; }
            if (adjusted) alert(convocaShiftsData.msgAdjusted);
            $('#fe_h_start').val(start); $('#fe_h_end').val(end);
        }
    });

    $(document).on('click', '#convoca-shifts-fe-save', function () {
        const $btn = $(this);
        const modal = $('#convoca-shifts-frontend-modal');
        let h_start = $('#fe_h_start').val(); let h_end = $('#fe_h_end').val();
        const limitOpen = convocaShiftsData.horaApertura; const limitClose = convocaShiftsData.horaCierre;
        let adjusted = false;
        if (h_start < limitOpen) { h_start = limitOpen; adjusted = true; }
        if (h_end > limitClose) { h_end = limitClose; adjusted = true; }
        if (h_start >= h_end) { alert('La hora de fin debe ser posterior a la de inicio.'); return; }
        if (adjusted) {
            alert('El horario de disponibilidad del centro es de ' + limitOpen + ' a ' + limitClose + '. Se ha ajustado el turno automáticamente.');
            $('#fe_h_start').val(h_start); $('#fe_h_end').val(h_end); return;
        }
        const data = {
            date: modal.data('date'), h_start: h_start, h_end: h_end,
            estado: $('#fe_estado').val(), apoyo: $('#fe_apoyo').is(':checked')
        };
        $btn.prop('disabled', true).text('Guardando...');
        $.ajax({
            url: convocaShiftsData.restUrl + '/crear',
            type: 'POST',
            beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', convocaShiftsData.nonce); },
            data: data,
            success: function (response) {
                modal.removeClass('is-active');
                if (window.convocaShiftsCalendarInstance) window.convocaShiftsCalendarInstance.refetchEvents();
                $('#fe_apoyo').prop('checked', false);
            },
            error: function (xhr) { alert(convocaShiftsData.errorCrear); },
            complete: function () { $btn.prop('disabled', false).text('Guardar Turno'); }
        });
    });
});
