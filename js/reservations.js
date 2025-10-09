(function ($) {
    'use strict';

    function initDatepicker() {
        const $dateInput = $('#reservation-date');
        if (!$dateInput.length) {
            return;
        }

        const inputType = $dateInput.attr('type');
        if (inputType === 'date') {
            const minDate = new Date();
            minDate.setDate(minDate.getDate() + 1);
            const isoMin = minDate.toISOString().split('T')[0];
            $dateInput.attr('min', isoMin);
            return;
        }

        if (typeof $dateInput.datepicker === 'function') {
            $dateInput.datepicker({
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

        const prefilledDateFromServer = typeof window.prefilledReservationDate === 'string'
            ? window.prefilledReservationDate
            : '';
        const shouldOpenFromServer = Boolean(window.shouldOpenReservationModal);
        const shouldDisplayFormFromServer = Boolean(window.shouldDisplayReservationForm);

        const modalElement = document.getElementById('reservationDayModal');
        const modalTitle = modalElement ? modalElement.querySelector('.modal-title') : null;
        const availabilityContainer = modalElement ? modalElement.querySelector('[data-reservation-availability]') : null;
        const dateInput = modalElement ? modalElement.querySelector('#reservation-date') : null;
        const formElement = modalElement ? modalElement.querySelector('[data-reservation-form]') : null;
        const startButton = modalElement ? modalElement.querySelector('[data-reservation-start]') : null;
        const messageContainer = modalElement ? modalElement.querySelector('[data-reservation-messages]') : null;
        let shouldShowFormOnOpen = shouldDisplayFormFromServer;

        function toggleFormVisibility(showForm) {
            if (formElement) {
                if (showForm) {
                    formElement.classList.remove('d-none');
                } else {
                    formElement.classList.add('d-none');
                }
            }

            if (startButton) {
                if (showForm) {
                    startButton.classList.add('d-none');
                } else {
                    startButton.classList.remove('d-none');
                }
            }
        }

        function dispatchModalEvent(eventName) {
            if (!modalElement || typeof eventName !== 'string') {
                return;
            }

            let event;
            if (typeof window.CustomEvent === 'function') {
                event = new CustomEvent(eventName);
            } else if (document.createEvent) {
                event = document.createEvent('CustomEvent');
                event.initCustomEvent(eventName, false, false, null);
            }

            if (event) {
                modalElement.dispatchEvent(event);
            }
        }

        function focusFirstFormField() {
            if (!formElement) {
                return;
            }

            const firstField = formElement.querySelector('input, select, textarea, button');
            if (firstField && typeof firstField.focus === 'function') {
                firstField.focus();
            }
        }

        toggleFormVisibility(shouldShowFormOnOpen);

        if (startButton) {
            startButton.addEventListener('click', function () {
                shouldShowFormOnOpen = true;
                toggleFormVisibility(true);
                focusFirstFormField();
                dispatchModalEvent('reservation:show-form');
            });
        }

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

        function formatTimeForDisplay(timeValue) {
            if (typeof timeValue !== 'string') {
                return '';
            }

            const trimmedValue = timeValue.trim();
            if (!trimmedValue) {
                return '';
            }

            const timeParts = trimmedValue.split(':');
            if (timeParts.length < 2) {
                return trimmedValue;
            }

            const hours = parseInt(timeParts[0], 10);
            const minutes = parseInt(timeParts[1], 10);

            if (Number.isNaN(hours) || Number.isNaN(minutes)) {
                return trimmedValue;
            }

            const suffix = hours >= 12 ? 'PM' : 'AM';
            const normalizedHours = ((hours % 12) || 12);
            const paddedMinutes = minutes.toString().padStart(2, '0');

            return normalizedHours + ':' + paddedMinutes + ' ' + suffix;
        }

        function createDetailListItem(labelText, valueText) {
            const item = document.createElement('li');
            item.className = 'reservation_detail_meta_item';

            const label = document.createElement('span');
            label.className = 'reservation_detail_meta_label';
            label.textContent = labelText;
            item.appendChild(label);
            item.appendChild(document.createTextNode(' '));

            const value = document.createElement('span');
            value.className = 'reservation_detail_meta_value';
            value.textContent = valueText;
            item.appendChild(value);

            return item;
        }

        function buildReservationDetail(reservation) {
            const wrapper = document.createElement('div');
            wrapper.className = 'reservation_detail_item';

            const title = document.createElement('h6');
            title.className = 'reservation_detail_title';
            title.textContent = reservation.eventType || 'Reserved';
            wrapper.appendChild(title);

            const detailsList = document.createElement('ul');
            detailsList.className = 'reservation_detail_meta list-unstyled mb-0';

            if (reservation.name) {
                detailsList.appendChild(createDetailListItem('Booker:', reservation.name));
            }

            const formattedTime = formatTimeForDisplay(reservation.preferredTime);
            if (formattedTime) {
                detailsList.appendChild(createDetailListItem('Time:', formattedTime));
            }

            if (detailsList.childElementCount > 0) {
                wrapper.appendChild(detailsList);
            }

            return wrapper;
        }

        function applyDateToInput(dateKey) {
            if (!dateInput || typeof dateKey !== 'string') {
                return;
            }

            if (dateInput.type === 'date') {
                dateInput.value = dateKey;
                return;
            }

            const parts = dateKey.split('-');
            if (parts.length === 3) {
                dateInput.value = `${parts[1]}/${parts[2]}/${parts[0]}`;
            } else {
                dateInput.value = dateKey;
            }
        }

        function renderAvailabilityDefault() {
            if (!availabilityContainer) {
                return;
            }

            availabilityContainer.innerHTML = '';

            const intro = document.createElement('p');
            intro.className = 'mb-2';
            intro.textContent = 'Select a date on the calendar to see existing approved reservations and prefill the request form.';
            availabilityContainer.appendChild(intro);

            const legend = document.createElement('p');
            legend.className = 'small text-muted mb-0';
            legend.innerHTML = 'Dates without a <span class="badge badge-danger">Booked</span> tag remain open for requests.';
            availabilityContainer.appendChild(legend);
        }

        function renderAvailabilityDetails(dateKey, reservationsForDate) {
            if (!availabilityContainer) {
                return;
            }

            availabilityContainer.innerHTML = '';

            const selectedDateHeading = document.createElement('p');
            selectedDateHeading.className = 'font-weight-bold';
            selectedDateHeading.textContent = formatDisplayDate(dateKey);
            availabilityContainer.appendChild(selectedDateHeading);

            if (Array.isArray(reservationsForDate) && reservationsForDate.length > 0) {
                const intro = document.createElement('p');
                intro.className = 'modal_intro';
                intro.textContent = 'Approved reservations for this day:';
                availabilityContainer.appendChild(intro);

                const list = document.createElement('div');
                list.className = 'reservation_detail_list';
                reservationsForDate.forEach(function (reservation) {
                    list.appendChild(buildReservationDetail(reservation));
                });
                availabilityContainer.appendChild(list);
            } else {
                const availableMessage = document.createElement('p');
                availableMessage.className = 'modal_no_reservations';
                availableMessage.textContent = 'No approved reservations are on the calendar for this date yet. Complete the form to request it.';
                availabilityContainer.appendChild(availableMessage);
            }
        }

        function populateModal(dateKey) {
            if (!modalElement) {
                return;
            }

            const reservationsForDate = bookedLookup[dateKey] || [];

            if (modalTitle) {
                modalTitle.textContent = 'Reserve — ' + formatDisplayDate(dateKey);
            }

            renderAvailabilityDetails(dateKey, reservationsForDate);
            applyDateToInput(dateKey);
            modalElement.setAttribute('data-selected-date', dateKey);

            if (typeof $ === 'function') {
                $(modalElement).modal('show');
            }
        }

        renderAvailabilityDefault();

        if (prefilledDateFromServer) {
            applyDateToInput(prefilledDateFromServer);
        }

        if (modalElement && typeof $ === 'function') {
            $(modalElement).on('show.bs.modal', function () {
                toggleFormVisibility(shouldShowFormOnOpen);
                const selectedDate = modalElement.getAttribute('data-selected-date');
                if (shouldShowFormOnOpen) {
                    focusFirstFormField();
                }
                if (!selectedDate) {
                    if (shouldOpenFromServer && prefilledDateFromServer) {
                        modalElement.setAttribute('data-selected-date', prefilledDateFromServer);
                        if (modalTitle) {
                            modalTitle.textContent = 'Reserve — ' + formatDisplayDate(prefilledDateFromServer);
                        }
                        renderAvailabilityDetails(prefilledDateFromServer, bookedLookup[prefilledDateFromServer] || []);
                        applyDateToInput(prefilledDateFromServer);
                    } else {
                        if (modalTitle) {
                            modalTitle.textContent = 'Start a Reservation';
                        }
                        renderAvailabilityDefault();
                    }
                }
            });

            $(modalElement).on('hidden.bs.modal', function () {
                modalElement.removeAttribute('data-selected-date');
                shouldShowFormOnOpen = false;
                toggleFormVisibility(false);
                if (formElement && typeof formElement.reset === 'function') {
                    formElement.reset();
                }
                if (messageContainer) {
                    messageContainer.innerHTML = '';
                }
                dispatchModalEvent('reservation:reset');
                renderAvailabilityDefault();
            });
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

        if (shouldOpenFromServer && modalElement && typeof $ === 'function') {
            $(modalElement).modal('show');
        }
    }

    function initCalendar() {
        const calendarContainer = document.getElementById('availability-calendar');
        if (calendarContainer) {
            calendarContainer.innerHTML = '';
            buildCalendar(calendarContainer);
        }
    }

    function initEventTypeToggle() {
        const modalElement = document.getElementById('reservationDayModal');
        if (!modalElement) {
            return;
        }

        const attachmentsBox = modalElement.querySelector('#baptism-attachments');
        const baptismRequiredFields = attachmentsBox
            ? attachmentsBox.querySelectorAll('[data-baptism-required="true"]')
            : [];
        const eventTypeRadios = modalElement.querySelectorAll('input[name="reservation-type"]');

        function updateVisibility() {
            let selectedType = 'Baptism';
            Array.prototype.forEach.call(eventTypeRadios, function (radio) {
                if (radio.checked) {
                    selectedType = radio.value;
                }
            });

            const isBaptism = selectedType === 'Baptism';

            if (attachmentsBox) {
                attachmentsBox.style.display = isBaptism ? '' : 'none';
            }

            Array.prototype.forEach.call(baptismRequiredFields, function (field) {
                if (!(field instanceof HTMLElement)) {
                    return;
                }

                if (isBaptism) {
                    field.setAttribute('required', 'required');
                } else {
                    field.removeAttribute('required');
                    if (field instanceof HTMLInputElement) {
                        field.value = '';
                    }
                }
            });
        }

        Array.prototype.forEach.call(eventTypeRadios, function (radio) {
            radio.addEventListener('change', updateVisibility);
        });

        updateVisibility();

        modalElement.addEventListener('reservation:reset', updateVisibility);
        modalElement.addEventListener('reservation:show-form', updateVisibility);
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
        initEventTypeToggle();
        initCalendar();
        initFormHandler();
    });
})(jQuery);
