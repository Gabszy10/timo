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
