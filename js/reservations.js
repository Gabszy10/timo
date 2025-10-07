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

        const today = new Date();
        const baseYear = today.getFullYear();
        const baseMonth = today.getMonth();

        function toISO(year, month, day) {
            const date = new Date(year, month, day);
            return date.toISOString().split('T')[0];
        }

        const sampleBookings = {};
        sampleBookings[toISO(baseYear, baseMonth, 5)] = { status: statuses.booked, label: 'Wedding - Smith &amp; Lee' };
        sampleBookings[toISO(baseYear, baseMonth, 12)] = { status: statuses.pending, label: 'Baptism - Garcia Family' };
        sampleBookings[toISO(baseYear, baseMonth, 18)] = { status: statuses.booked, label: 'Funeral Mass - Johnson' };
        sampleBookings[toISO(baseYear, baseMonth, 24)] = { status: statuses.available, label: 'Available for morning' };

        const nextMonthDate = new Date(baseYear, baseMonth + 1, 1);
        sampleBookings[toISO(nextMonthDate.getFullYear(), nextMonthDate.getMonth(), 2)] = { status: statuses.booked, label: 'Confirmation Mass' };
        sampleBookings[toISO(nextMonthDate.getFullYear(), nextMonthDate.getMonth(), 15)] = { status: statuses.pending, label: 'Wedding - Chen &amp; Rivera' };
        sampleBookings[toISO(nextMonthDate.getFullYear(), nextMonthDate.getMonth(), 28)] = { status: statuses.booked, label: 'Quinceañera - Martinez' };

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
                const isoDate = date.toISOString().split('T')[0];
                const booking = sampleBookings[isoDate];

                const cell = document.createElement('td');
                const cellWrapper = document.createElement('div');
                cellWrapper.className = 'calendar_day';
                cellWrapper.innerHTML = `<span class="day_number">${day}</span>`;

                if (booking) {
                    cellWrapper.classList.add(`status_${booking.status}`);
                    const label = document.createElement('small');
                    label.className = 'day_label';
                    label.innerHTML = booking.label;
                    cellWrapper.appendChild(label);
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
