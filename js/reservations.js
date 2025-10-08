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
            pending: 'pending',
            available: 'available'
        };

        const statusLabels = {
            [statuses.booked]: 'Booked',
            [statuses.pending]: 'Pending',
            [statuses.available]: 'Available'
        };

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const baseYear = today.getFullYear();
        const baseMonth = today.getMonth();

        function toLocalKey(year, month, day) {
            const paddedMonth = String(month + 1).padStart(2, '0');
            const paddedDay = String(day).padStart(2, '0');
            return `${year}-${paddedMonth}-${paddedDay}`;
        }

        const sampleBookings = {};
        sampleBookings[toLocalKey(baseYear, baseMonth, 5)] = { status: statuses.booked };
        sampleBookings[toLocalKey(baseYear, baseMonth, 12)] = { status: statuses.pending };
        sampleBookings[toLocalKey(baseYear, baseMonth, 18)] = { status: statuses.booked };
        sampleBookings[toLocalKey(baseYear, baseMonth, 24)] = { status: statuses.available };

        const nextMonthDate = new Date(baseYear, baseMonth + 1, 1);
        const nextYear = nextMonthDate.getFullYear();
        const nextMonth = nextMonthDate.getMonth();
        sampleBookings[toLocalKey(nextYear, nextMonth, 2)] = { status: statuses.booked };
        sampleBookings[toLocalKey(nextYear, nextMonth, 15)] = { status: statuses.pending };
        sampleBookings[toLocalKey(nextYear, nextMonth, 28)] = { status: statuses.booked };

        const monthNames = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];

        const weekdayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

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
                currentRow.appendChild(document.createElement('td'));
            }

            for (let day = 1; day <= lastDay.getDate(); day += 1) {
                const date = new Date(year, month, day);
                const isoDate = toLocalKey(date.getFullYear(), date.getMonth(), day);
                const booking = sampleBookings[isoDate];

                const cell = document.createElement('td');
                const cellWrapper = document.createElement('div');
                cellWrapper.className = 'calendar_day';
                cellWrapper.innerHTML = `<span class="day_number">${day}</span>`;

                if (booking) {
                    const status = booking.status || statuses.available;
                    cellWrapper.classList.add(`status_${status}`);

                    if (status !== statuses.available) {
                        const label = document.createElement('small');
                        label.className = 'day_label';
                        label.textContent = statusLabels[status] || '';
                        cellWrapper.appendChild(label);
                    }
                } else {
                    cellWrapper.classList.add('status_available');
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
                    currentRow.appendChild(document.createElement('td'));
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
