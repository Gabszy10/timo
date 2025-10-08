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

    function buildCalendar(container, monthsToRender) {
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
            if (typeof value === 'string') {
                const trimmed = value.trim();
                if (trimmed) {
                    accumulator[trimmed] = true;
                }
            }

            return accumulator;
        }, {});

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const baseYear = today.getFullYear();
        const baseMonth = today.getMonth();

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

            const header = document.createElement('div');
            header.className = 'calendar_month_header';
            header.innerHTML = `<h5>${monthNames[month]} ${year}</h5>`;
            monthWrapper.appendChild(header);

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
            container.appendChild(monthWrapper);
        }

        for (let i = 0; i < monthsToRender; i += 1) {
            const monthDate = new Date(today.getFullYear(), today.getMonth() + i, 1);
            renderMonth(monthDate);
        }
    }

    function initCalendar() {
        const calendarContainer = document.getElementById('availability-calendar');
        if (calendarContainer) {
            calendarContainer.innerHTML = '';
            buildCalendar(calendarContainer, 2);
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
