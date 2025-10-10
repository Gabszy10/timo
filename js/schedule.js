(function ($) {
    'use strict';

    $(document).ready(function () {
        const buttons = $('.filter_buttons .filter_btn');
        const items = $('#schedule-events .schedule_item');

        function filterEvents(filter) {
            if (filter === 'all') {
                items.stop(true, true).fadeIn(180);
                return;
            }

            items.each(function () {
                const item = $(this);
                const type = item.data('type');
                if (type === filter) {
                    item.stop(true, true).fadeIn(180);
                } else {
                    item.stop(true, true).fadeOut(150);
                }
            });
        }

        buttons.on('click', function (event) {
            event.preventDefault();
            const button = $(this);
            buttons.removeClass('active').attr('aria-pressed', 'false');
            button.addClass('active').attr('aria-pressed', 'true');
            filterEvents(button.data('filter'));
        });

        filterEvents('all');

        const calendarContainer = $('[data-calendar]');

        if (calendarContainer.length) {
            const monthLabel = $('[data-calendar-month]');
            const grid = $('[data-calendar-grid]');
            const selectedDateLabel = $('[data-calendar-date]');
            const selectedDateHint = $('[data-calendar-date-hint]');
            const eventList = $('[data-calendar-event-list]');
            const availabilityList = $('[data-calendar-availability-list]');
            const monthNames = [
                'January',
                'February',
                'March',
                'April',
                'May',
                'June',
                'July',
                'August',
                'September',
                'October',
                'November',
                'December'
            ];
            const dayNames = [
                'Sunday',
                'Monday',
                'Tuesday',
                'Wednesday',
                'Thursday',
                'Friday',
                'Saturday'
            ];
            const typeModifiers = {
                Wedding: 'wedding',
                Baptism: 'baptism',
                Funeral: 'funeral'
            };
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const displayDate = new Date();
            displayDate.setDate(1);

            const sampleBookingsConfig = [
                {
                    day: 4,
                    type: 'Wedding',
                    time: '7:30 AM – 10:00 AM',
                    title: 'Santos – Dela Cruz Nuptial Mass',
                    description: 'Morning wedding confirmed with the parish liturgy team.'
                },
                {
                    day: 11,
                    type: 'Baptism',
                    time: '11:00 AM – 12:00 PM',
                    title: 'Infant Baptism Group Celebration',
                    description: 'Family catechesis begins 30 minutes before the liturgy.'
                },
                {
                    day: 15,
                    type: 'Funeral',
                    time: '1:00 PM',
                    title: 'Memorial Mass for the Ramirez Family',
                    description: 'Coordinated with St. Helena Bereavement Ministry.'
                },
                {
                    day: 22,
                    type: 'Wedding',
                    time: '3:00 PM – 5:00 PM',
                    title: 'Garcia – Mendoza Wedding Celebration',
                    description: 'Afternoon slot opened after morning reservations filled.'
                },
                {
                    day: 27,
                    type: 'Funeral',
                    time: '9:00 AM',
                    title: 'Thanksgiving Mass for Maria Cruz',
                    description: 'Weekday funeral liturgy followed by cemetery rites.'
                }
            ];

            const sampleBookings = sampleBookingsConfig
                .map(function (event) {
                    const eventDate = new Date(displayDate.getFullYear(), displayDate.getMonth() + (event.monthOffset || 0), event.day);
                    return $.extend({}, event, { dateKey: formatDateKey(eventDate) });
                });

            const eventsByDate = sampleBookings.reduce(function (accumulator, event) {
                if (!accumulator[event.dateKey]) {
                    accumulator[event.dateKey] = [];
                }
                accumulator[event.dateKey].push(event);
                return accumulator;
            }, {});

            function getAvailabilityForDay(dayIndex) {
                if (dayIndex === 0) {
                    return [
                        {
                            type: 'Baptism',
                            time: '11:00 AM – 12:00 PM',
                            note: 'Weekend baptisms take place right after the late morning Mass.'
                        },
                        {
                            type: 'Funeral',
                            time: '1:00 PM or 2:00 PM',
                            note: 'Coordinate afternoon funeral liturgies with the parish office.'
                        }
                    ];
                }

                if (dayIndex === 1) {
                    return [
                        {
                            type: 'Wedding',
                            time: '7:30 AM – 10:00 AM',
                            note: 'Primary morning window for nuptial Masses and ceremonies.'
                        },
                        {
                            type: 'Wedding',
                            time: '3:00 PM – 5:00 PM',
                            note: 'Overflow afternoon slot opens when the morning schedule fills.'
                        },
                        {
                            type: 'Funeral',
                            time: '1:00 PM or 2:00 PM',
                            note: 'Afternoon funeral Masses are available with prior coordination.'
                        }
                    ];
                }

                if (dayIndex === 6) {
                    return [
                        {
                            type: 'Wedding',
                            time: '7:30 AM – 10:00 AM',
                            note: 'Morning weddings welcome once documents and canonical interviews are complete.'
                        },
                        {
                            type: 'Wedding',
                            time: '3:00 PM – 5:00 PM',
                            note: 'Afternoon wedding slot opens when needed for additional couples.'
                        },
                        {
                            type: 'Baptism',
                            time: '11:00 AM – 12:00 PM',
                            note: 'Catechesis begins 30 minutes before the communal baptism.'
                        },
                        {
                            type: 'Funeral',
                            time: '8:00 AM, 9:00 AM, or 10:00 AM',
                            note: 'Morning funeral liturgies help families proceed to the cemetery on schedule.'
                        }
                    ];
                }

                return [
                    {
                        type: 'Wedding',
                        time: '7:30 AM – 10:00 AM',
                        note: 'Morning weddings are available Monday through Saturday.'
                    },
                    {
                        type: 'Wedding',
                        time: '3:00 PM – 5:00 PM',
                        note: 'Afternoon weddings open once the morning schedule is full.'
                    },
                    {
                        type: 'Funeral',
                        time: '8:00 AM, 9:00 AM, or 10:00 AM',
                        note: 'Weekday funeral liturgies are celebrated during these morning slots.'
                    }
                ];
            }

            function formatDateKey(date) {
                return [
                    date.getFullYear(),
                    String(date.getMonth() + 1).padStart(2, '0'),
                    String(date.getDate()).padStart(2, '0')
                ].join('-');
            }

            function createTag(type) {
                var modifier = typeModifiers[type] || 'general';
                return $('<span />', {
                    'class': 'calendar_cell_tag calendar_cell_tag--' + modifier,
                    text: type
                });
            }

            function createTypeBadge(type) {
                var modifier = typeModifiers[type] || 'general';
                return $('<span />', {
                    'class': 'calendar_event_type calendar_event_type--' + modifier,
                    text: type
                });
            }

            function isSameDay(dateA, dateB) {
                return dateA.getFullYear() === dateB.getFullYear() &&
                    dateA.getMonth() === dateB.getMonth() &&
                    dateA.getDate() === dateB.getDate();
            }

            function createCellLabel(date, eventsForDay, availability) {
                var label = dayNames[date.getDay()] + ', ' + monthNames[date.getMonth()] + ' ' + date.getDate() + '.';

                if (eventsForDay.length) {
                    label += ' ' + eventsForDay.length + (eventsForDay.length === 1 ? ' reservation is ' : ' reservations are ') + 'recorded.';
                    if (eventsForDay[0] && eventsForDay[0].time) {
                        label += ' First reservation at ' + eventsForDay[0].time + '.';
                    }
                } else if (availability.length) {
                    label += ' No reservations recorded. Standard windows include ' + availability[0].time + '.';
                } else {
                    label += ' No reservations recorded for this date.';
                }

                return label;
            }

            function renderCalendar() {
                var monthIndex = displayDate.getMonth();
                var year = displayDate.getFullYear();
                monthLabel.text(monthNames[monthIndex] + ' ' + year);
                grid.empty();

                var firstDayIndex = new Date(year, monthIndex, 1).getDay();
                var totalDays = new Date(year, monthIndex + 1, 0).getDate();

                for (var blank = 0; blank < firstDayIndex; blank += 1) {
                    grid.append('<div class="calendar_cell calendar_cell--empty" aria-hidden="true"></div>');
                }

                for (var day = 1; day <= totalDays; day += 1) {
                    var cellDate = new Date(year, monthIndex, day);
                    var dateKey = formatDateKey(cellDate);
                    var eventsForDay = eventsByDate[dateKey] || [];
                    var cell = $('<button type="button" class="calendar_cell"></button>');
                    cell.attr('data-date', dateKey);
                    cell.attr('aria-pressed', 'false');
                    cell.append('<span class="calendar_cell_day">' + day + '</span>');

                    var availability = getAvailabilityForDay(cellDate.getDay());
                    cell.attr('aria-label', createCellLabel(cellDate, eventsForDay, availability));

                    if (eventsForDay.length) {
                        cell.addClass('calendar_cell--has-event');
                        var eventTypes = [];
                        eventsForDay.forEach(function (event) {
                            if (eventTypes.indexOf(event.type) === -1) {
                                eventTypes.push(event.type);
                            }
                        });

                        if (eventTypes.length) {
                            var tagsList = $('<div class="calendar_cell_tags"></div>');
                            eventTypes.forEach(function (type) {
                                tagsList.append(createTag(type));
                            });
                            cell.append(tagsList);
                        }

                        var noteText = eventsForDay.length === 1 ? eventsForDay[0].time : eventsForDay.length + ' reservations';
                        cell.append('<span class="calendar_cell_note">' + noteText + '</span>');
                    } else {
                        var availabilityTypes = [];

                        availability.forEach(function (slot) {
                            if (availabilityTypes.indexOf(slot.type) === -1) {
                                availabilityTypes.push(slot.type);
                            }
                        });

                        if (availabilityTypes.length) {
                            var availabilityTags = $('<div class="calendar_cell_tags"></div>');
                            availabilityTypes.slice(0, 2).forEach(function (type) {
                                availabilityTags.append(createTag(type));
                            });
                            cell.append(availabilityTags);
                        }

                        cell.append('<span class="calendar_cell_note">View time windows</span>');
                    }

                    if (isSameDay(cellDate, today)) {
                        cell.addClass('calendar_cell--today');
                    }

                    cell.on('click', function () {
                        selectDate($(this).data('date'));
                    });

                    grid.append(cell);
                }
            }

            function buildEventItem(event) {
                var item = $('<li class="calendar_event_item"></li>');
                var header = $('<div class="calendar_event_header"></div>');
                header.append(createTypeBadge(event.type));
                header.append($('<span class="calendar_event_time"></span>').text(event.time));
                item.append(header);

                var description = $('<p class="calendar_event_description"></p>');
                description.append($('<strong></strong>').text(event.title));
                if (event.description) {
                    description.append('<br>' + event.description);
                }
                item.append(description);
                return item;
            }

            function buildAvailabilityItem(slot) {
                var item = $('<li class="calendar_availability_item"></li>');
                var header = $('<div class="calendar_event_header"></div>');
                header.append(createTypeBadge(slot.type));
                header.append($('<span class="calendar_event_time"></span>').text(slot.time));
                item.append(header);

                if (slot.note) {
                    item.append($('<p class="calendar_event_description calendar_availability_note"></p>').text(slot.note));
                }

                return item;
            }

            function selectDate(dateKey) {
                if (!dateKey) {
                    return;
                }

                var parts = dateKey.split('-');
                var date = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
                var formattedDate = dayNames[date.getDay()] + ', ' + monthNames[date.getMonth()] + ' ' + date.getDate();
                var eventsForDay = eventsByDate[dateKey] || [];
                var availability = getAvailabilityForDay(date.getDay());

                grid.find('.calendar_cell').removeClass('calendar_cell--selected').attr('aria-pressed', 'false');
                grid.find('.calendar_cell[data-date="' + dateKey + '"]').addClass('calendar_cell--selected').attr('aria-pressed', 'true');

                selectedDateLabel.text(formattedDate);

                if (eventsForDay.length) {
                    selectedDateHint.text('This date already has confirmed reservations. Review the details below.');
                } else {
                    selectedDateHint.text('No reservations are recorded for this date yet. Review the parish time windows to confirm your request.');
                }

                eventList.empty();
                if (eventsForDay.length) {
                    eventsForDay.forEach(function (event) {
                        eventList.append(buildEventItem(event));
                    });
                } else {
                    eventList.append('<li class="calendar_event_list_empty">No reservations are recorded for this date yet.</li>');
                }

                availabilityList.empty();

                if (availability.length) {
                    availability.forEach(function (slot) {
                        availabilityList.append(buildAvailabilityItem(slot));
                    });
                } else {
                    availabilityList.append('<li class="calendar_event_list_empty">No standard parish schedule is listed for this day.</li>');
                }
            }

            renderCalendar();

            var initialDateKey = null;

            if (today.getMonth() === displayDate.getMonth() && today.getFullYear() === displayDate.getFullYear()) {
                initialDateKey = formatDateKey(today);
            } else if (sampleBookings.length) {
                initialDateKey = sampleBookings[0].dateKey;
            } else {
                initialDateKey = formatDateKey(new Date(displayDate.getFullYear(), displayDate.getMonth(), 1));
            }

            selectDate(initialDateKey);
        }
    });
})(jQuery);
