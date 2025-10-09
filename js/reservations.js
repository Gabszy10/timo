(function ($) {
    'use strict';

    function initDatepicker() {
        if ($('#reservation-date').length) {
            $('#reservation-date').datepicker({
                uiLibrary: 'bootstrap4',
                iconsLibrary: 'fontawesome',
                minDate: function () {
                    const today = new Date();
                    today.setDate(today.getDate() + 1);
                    return today;
                }
            });
        }
    }

    function buildCalendar(container) {
        const statuses = {
            booked: 'booked',
            available: 'available'
        };

        const statusLabels = {
            [statuses.booked]: 'Booked'
        };

        const approvedReservations = Array.isArray(window.approvedReservations)
            ? window.approvedReservations
            : [];

        const bookedLookup = approvedReservations.reduce(function (accumulator, value) {
            if (value && typeof value === 'object' && typeof value.date === 'string') {
                const trimmedDate = value.date.trim();
                if (trimmedDate) {
                    const reservations = Array.isArray(value.reservations)
                        ? value.reservations.reduce(function (list, item) {
                            if (!item || typeof item !== 'object') {
                                return list;
                            }

                            const name = typeof item.name === 'string' ? item.name.trim() : '';
                            const eventType = typeof item.eventType === 'string' ? item.eventType.trim() : '';
                            const preferredTime = typeof item.preferredTime === 'string' ? item.preferredTime.trim() : '';

                            list.push({
                                name: name,
                                eventType: eventType,
                                preferredTime: preferredTime
                            });
                            return list;
                        }, [])
                        : [];

                    accumulator[trimmedDate] = reservations;
                }
            }

            return accumulator;
        }, {});

        const modalElement = document.getElementById('reservationDayModal');
        const modalTitle = modalElement ? modalElement.querySelector('.modal-title') : null;
        const modalBody = modalElement ? modalElement.querySelector('.modal-body') : null;
        const modalFooter = modalElement ? modalElement.querySelector('.modal-footer') : null;
        const modalInstance = typeof $ === 'function' && modalElement ? $(modalElement) : null;

        function formatDisplayDate(isoDate) {
            const parts = typeof isoDate === 'string' ? isoDate.split('-') : [];
            if (parts.length !== 3) {
                return isoDate;
            }

            const year = parseInt(parts[0], 10);
            const monthIndex = parseInt(parts[1], 10) - 1;
            const day = parseInt(parts[2], 10);

            if (Number.isNaN(year) || Number.isNaN(monthIndex) || Number.isNaN(day)) {
                return isoDate;
            }

            const displayDate = new Date(year, monthIndex, day);
            if (Number.isNaN(displayDate.getTime())) {
                return isoDate;
            }

            return displayDate.toLocaleDateString(undefined, {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        function buildReservationDetail(reservation) {
            const wrapper = document.createElement('article');
            wrapper.className = 'reservation_detail_item';

            const header = document.createElement('div');
            header.className = 'reservation_detail_header';
            wrapper.appendChild(header);

            const badge = document.createElement('span');
            badge.className = 'reservation_detail_badge';
            badge.textContent = reservation.eventType || 'Reserved';
            header.appendChild(badge);

            if (reservation.preferredTime) {
                const time = document.createElement('span');
                time.className = 'reservation_detail_time';

                const timeIcon = document.createElement('i');
                timeIcon.className = 'ti-time';
                timeIcon.setAttribute('aria-hidden', 'true');
                time.appendChild(timeIcon);

                const timeText = document.createElement('span');
                timeText.textContent = reservation.preferredTime;
                time.appendChild(timeText);

                header.appendChild(time);
            }

            if (reservation.name) {
                const nameRow = document.createElement('p');
                nameRow.className = 'reservation_detail_name';

                const nameIcon = document.createElement('i');
                nameIcon.className = 'ti-user';
                nameIcon.setAttribute('aria-hidden', 'true');
                nameRow.appendChild(nameIcon);

                const nameText = document.createElement('span');
                nameText.textContent = reservation.name;
                nameRow.appendChild(nameText);

                wrapper.appendChild(nameRow);
            }

            return wrapper;
        }

        function populateModal(dateKey) {
            if (!modalElement || !modalTitle || !modalBody || !modalFooter) {
                return;
            }

            const reservationsForDate = bookedLookup[dateKey] || [];

            modalTitle.textContent = formatDisplayDate(dateKey);
            modalBody.innerHTML = '';

            const summary = document.createElement('div');
            summary.className = 'reservation_modal_summary';
            const summaryTitle = document.createElement('strong');
            const summaryMessage = document.createElement('p');

            if (reservationsForDate.length > 0) {
                summary.classList.add('is_booked');
                summaryTitle.textContent = 'Currently booked';
                summaryMessage.textContent = 'The parish calendar already includes the approved reservations below. Please reach out if you need assistance coordinating another time.';
            } else {
                summaryTitle.textContent = 'Open for requests';
                summaryMessage.textContent = 'We are currently accepting reservation requests for this day. Submit the form below to get started.';
            }

            summary.appendChild(summaryTitle);
            summary.appendChild(summaryMessage);
            modalBody.appendChild(summary);

            if (reservationsForDate.length > 0) {
                const list = document.createElement('div');
                list.className = 'reservation_detail_list';
                reservationsForDate.forEach(function (reservation) {
                    list.appendChild(buildReservationDetail(reservation));
                });
                modalBody.appendChild(list);
            }

            modalFooter.innerHTML = '';
            const closeButton = document.createElement('button');
            closeButton.type = 'button';
            closeButton.className = 'btn btn-light';
            closeButton.setAttribute('data-dismiss', 'modal');
            closeButton.textContent = 'Close';
            modalFooter.appendChild(closeButton);

            const reserveButton = document.createElement('a');
            reserveButton.href = '#reservation-form';
            reserveButton.className = 'btn btn-primary';
            reserveButton.setAttribute('data-dismiss', 'modal');
            reserveButton.textContent = 'Make a Reservation';
            reserveButton.addEventListener('click', function () {
                if (modalInstance) {
                    modalInstance.modal('hide');
                }
            });
            modalFooter.appendChild(reserveButton);

            if (modalInstance) {
                modalInstance.modal('show');
            }
        }

        function attachDayInteraction(dayElement, dateKey) {
            if (!dayElement) {
                return;
            }

            dayElement.classList.add('is_clickable');
            dayElement.setAttribute('role', 'button');
            dayElement.setAttribute('tabindex', '0');
            dayElement.dataset.date = dateKey;

            dayElement.addEventListener('click', function () {
                populateModal(dateKey);
            });

            dayElement.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar') {
                    event.preventDefault();
                    populateModal(dateKey);
                }
            });
        }

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const state = {
            year: today.getFullYear(),
            month: today.getMonth()
        };

        const navigation = document.createElement('div');
        navigation.className = 'calendar_navigation';

        const previousButton = document.createElement('button');
        previousButton.type = 'button';
        previousButton.className = 'calendar_nav_button calendar_nav_previous';
        previousButton.setAttribute('aria-label', 'Previous month');
        previousButton.innerHTML = '<i class="ti-angle-left" aria-hidden="true"></i>';

        const nextButton = document.createElement('button');
        nextButton.type = 'button';
        nextButton.className = 'calendar_nav_button calendar_nav_next';
        nextButton.setAttribute('aria-label', 'Next month');
        nextButton.innerHTML = '<i class="ti-angle-right" aria-hidden="true"></i>';

        const monthLabel = document.createElement('div');
        monthLabel.className = 'calendar_nav_label';
        monthLabel.setAttribute('aria-live', 'polite');

        navigation.appendChild(previousButton);
        navigation.appendChild(monthLabel);
        navigation.appendChild(nextButton);

        const monthContainer = document.createElement('div');
        monthContainer.className = 'calendar_month_container';

        container.appendChild(navigation);
        container.appendChild(monthContainer);

        function toLocalKey(year, month, day) {
            const paddedMonth = String(month + 1).padStart(2, '0');
            const paddedDay = String(day).padStart(2, '0');
            return `${year}-${paddedMonth}-${paddedDay}`;
        }

        const monthNames = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];

        const weekdayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        function appendEmptyCell(row) {
            const emptyCell = document.createElement('td');
            const emptyWrapper = document.createElement('div');
            emptyWrapper.className = 'calendar_day is_empty';
            emptyCell.appendChild(emptyWrapper);
            row.appendChild(emptyCell);
        }

        function renderMonth(baseDate) {
            const year = baseDate.getFullYear();
            const month = baseDate.getMonth();

            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);

            const monthWrapper = document.createElement('div');
            monthWrapper.className = 'calendar_month';

            const table = document.createElement('table');
            table.className = 'calendar_table';

            const thead = document.createElement('thead');
            const headerRow = document.createElement('tr');
            weekdayNames.forEach(function (weekday) {
                const th = document.createElement('th');
                th.textContent = weekday;
                headerRow.appendChild(th);
            });
            thead.appendChild(headerRow);
            table.appendChild(thead);

            const tbody = document.createElement('tbody');
            let currentRow = document.createElement('tr');

            for (let i = 0; i < firstDay.getDay(); i += 1) {
                appendEmptyCell(currentRow);
            }

            for (let day = 1; day <= lastDay.getDate(); day += 1) {
                const date = new Date(year, month, day);
                const isoDate = toLocalKey(date.getFullYear(), date.getMonth(), day);
                const cell = document.createElement('td');
                const cellWrapper = document.createElement('div');
                const dayNumber = document.createElement('span');
                dayNumber.className = 'day_number';
                dayNumber.textContent = day;

                cellWrapper.className = 'calendar_day';
                cellWrapper.appendChild(dayNumber);

                let status = statuses.available;
                if (bookedLookup[isoDate]) {
                    status = statuses.booked;
                }

                cellWrapper.classList.add(`status_${status}`);

                if (status === statuses.booked) {
                    const label = document.createElement('small');
                    label.className = 'day_label';
                    label.textContent = statusLabels[status];
                    cellWrapper.appendChild(label);
                }

                attachDayInteraction(cellWrapper, isoDate);
                cell.appendChild(cellWrapper);
                currentRow.appendChild(cell);

                if (date.getDay() === 6) {
                    tbody.appendChild(currentRow);
                    currentRow = document.createElement('tr');
                }
            }

            if (currentRow.children.length) {
                while (currentRow.children.length < 7) {
                    appendEmptyCell(currentRow);
                }
                tbody.appendChild(currentRow);
            }

            table.appendChild(tbody);
            monthWrapper.appendChild(table);
            return monthWrapper;
        }

        function updateNavigationControls() {
            const isAtYearStart = state.month === 0;
            const isAtYearEnd = state.month === 11;

            previousButton.disabled = isAtYearStart;
            nextButton.disabled = isAtYearEnd;

            monthLabel.textContent = `${monthNames[state.month]} ${state.year}`;
        }

        function render() {
            monthContainer.innerHTML = '';
            const monthDate = new Date(state.year, state.month, 1);
            monthContainer.appendChild(renderMonth(monthDate));
            updateNavigationControls();
        }

        previousButton.addEventListener('click', function () {
            if (state.month > 0) {
                state.month -= 1;
                render();
            }
        });

        nextButton.addEventListener('click', function () {
            if (state.month < 11) {
                state.month += 1;
                render();
            }
        });

        render();
    }

    function initCalendar() {
        const calendarContainer = document.getElementById('availability-calendar');
        if (calendarContainer) {
            calendarContainer.innerHTML = '';
            buildCalendar(calendarContainer);
        }
    }

    function initFormHandler() {
        const form = document.getElementById('reservation-form');
        if (!form) {
            return;
        }

        if (form.hasAttribute('data-server-handled')) {
            return;
        }

        const confirmation = document.getElementById('reservation-confirmation');

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (form.checkValidity()) {
                if (confirmation) {
                    confirmation.classList.remove('d-none');
                    confirmation.focus();
                }
                form.reset();
                initDatepicker();
            }
        });
    }

    $(document).ready(function () {
        initDatepicker();
        initCalendar();
        initFormHandler();
    });
})(jQuery);
