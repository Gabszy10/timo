(function ($) {
    'use strict';

    function initDatepicker() {
        const $dateInput = $('#reservation-date');
        if (!$dateInput.length) {
            return;
        }

        const inputType = $dateInput.attr('type');
        if (inputType === 'hidden') {
            return;
        }

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
        const formToggleTargets = modalElement
            ? Array.prototype.slice.call(modalElement.querySelectorAll('[data-reservation-form-toggle-target]'))
            : [];
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

            if (formToggleTargets.length) {
                formToggleTargets.forEach(function (target) {
                    if (!target) {
                        return;
                    }

                    target.hidden = !showForm;
                });
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

        function formatSingleTimeString(value) {
            if (typeof value !== 'string') {
                return '';
            }

            const trimmed = value.trim();
            if (!trimmed) {
                return '';
            }

            const amPmMatch = trimmed.match(/^(\d{1,2})(?::(\d{2}))?(?::\d{2})?\s*([ap]m)$/i);
            if (amPmMatch) {
                let hours = parseInt(amPmMatch[1], 10);
                if (Number.isNaN(hours)) {
                    return trimmed;
                }

                const minutesComponent = typeof amPmMatch[2] === 'string' ? amPmMatch[2] : '00';
                const paddedMinutes = minutesComponent.padStart(2, '0');
                const suffix = amPmMatch[3].toUpperCase();

                hours = ((hours - 1) % 12 + 12) % 12 + 1;

                return hours + ':' + paddedMinutes + ' ' + suffix;
            }

            const timeParts = trimmed.split(':');
            if (timeParts.length < 2) {
                return trimmed;
            }

            const hours = parseInt(timeParts[0], 10);
            const minutes = parseInt(timeParts[1], 10);

            if (Number.isNaN(hours) || Number.isNaN(minutes)) {
                return trimmed;
            }

            const suffix = hours >= 12 ? 'PM' : 'AM';
            const normalizedHours = ((hours % 12) || 12);
            const paddedMinutes = minutes.toString().padStart(2, '0');

            return normalizedHours + ':' + paddedMinutes + ' ' + suffix;
        }

        function formatTimeForDisplay(timeValue) {
            if (typeof timeValue !== 'string') {
                return '';
            }

            const trimmedValue = timeValue.trim();
            if (!trimmedValue) {
                return '';
            }

            if (trimmedValue.indexOf('-') !== -1) {
                const parts = trimmedValue.split('-').map(function (part) {
                    return part.trim();
                }).filter(function (part) {
                    return part !== '';
                });

                if (parts.length >= 2) {
                    const start = formatSingleTimeString(parts[0]);
                    const end = formatSingleTimeString(parts[1]);
                    if (start && end) {
                        return start + ' – ' + end;
                    }
                }
            }

            return formatSingleTimeString(trimmedValue);
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
            } else {
                const parts = dateKey.split('-');
                if (parts.length === 3) {
                    dateInput.value = `${parts[1]}/${parts[2]}/${parts[0]}`;
                } else {
                    dateInput.value = dateKey;
                }
            }

            let changeEvent = null;
            if (typeof Event === 'function') {
                changeEvent = new Event('change', { bubbles: true });
            } else if (document.createEvent) {
                changeEvent = document.createEvent('Event');
                changeEvent.initEvent('change', true, false);
            }

            if (changeEvent) {
                dateInput.dispatchEvent(changeEvent);
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
                const isPastDate = date.getTime() < today.getTime();
                const cell = document.createElement('td');
                const cellWrapper = document.createElement('div');
                const dayNumber = document.createElement('span');
                dayNumber.className = 'day_number';
                dayNumber.textContent = day;

                cellWrapper.className = 'calendar_day';
                cellWrapper.appendChild(dayNumber);

                const reservationsForDay = bookedLookup[isoDate] || [];
                let status = statuses.available;
                if (reservationsForDay.length) {
                    status = statuses.booked;
                }

                cellWrapper.classList.add(`status_${status}`);

                if (status === statuses.booked) {
                    const reservationCount = reservationsForDay.length;
                    const label = document.createElement('small');
                    label.className = 'day_label';
                    label.textContent = reservationCount === 1
                        ? '1 booking'
                        : `${reservationCount} bookings`;
                    label.setAttribute('aria-label', reservationCount === 1
                        ? 'One booking is already scheduled on this date'
                        : `${reservationCount} bookings are already scheduled on this date`);
                    cellWrapper.setAttribute('title', reservationCount === 1
                        ? '1 booking is already scheduled on this date'
                        : `${reservationCount} bookings are already scheduled on this date`);
                    cellWrapper.appendChild(label);
                }

                if (isPastDate) {
                    cellWrapper.classList.add('is_disabled');
                    cellWrapper.setAttribute('aria-disabled', 'true');
                    cellWrapper.title = 'Past dates cannot be reserved.';
                } else {
                    attachDayInteraction(cellWrapper, isoDate);
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

    function initTimeSlotSelector() {
        const modalElement = document.getElementById('reservationDayModal');
        if (!modalElement) {
            return;
        }

        const dateInput = modalElement.querySelector('#reservation-date');
        const timeSelect = modalElement.querySelector('#reservation-time');
        const helpText = modalElement.querySelector('[data-reservation-time-help]');
        const submitButton = modalElement.querySelector('[data-reservation-submit]');
        const unavailableNotice = modalElement.querySelector('[data-reservation-time-warning]');
        const eventTypeRadios = modalElement.querySelectorAll('input[name="reservation-type"]');

        if (!dateInput || !timeSelect || !eventTypeRadios.length) {
            return;
        }

        const UNKNOWN_SLOT = '__unknown__';
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const usageSummary = (typeof window.reservationUsage === 'object' && window.reservationUsage !== null)
            ? window.reservationUsage
            : {};

        let lastSelectedValue = timeSelect.getAttribute('data-initial-value') || '';
        let lastComputedResult = { slots: [], reason: '' };

        function getSelectedEventType() {
            let selectedType = '';
            Array.prototype.forEach.call(eventTypeRadios, function (radio) {
                if (radio.checked) {
                    selectedType = radio.value;
                }
            });
            return selectedType;
        }

        function parseDateValue() {
            const rawValue = (dateInput.value || '').trim();
            if (!rawValue) {
                return null;
            }

            if (dateInput.type === 'date') {
                const parts = rawValue.split('-');
                if (parts.length === 3) {
                    const year = parseInt(parts[0], 10);
                    const month = parseInt(parts[1], 10) - 1;
                    const day = parseInt(parts[2], 10);
                    if (!Number.isNaN(year) && !Number.isNaN(month) && !Number.isNaN(day)) {
                        const parsed = new Date(year, month, day);
                        if (!Number.isNaN(parsed.getTime())) {
                            return parsed;
                        }
                    }
                }
            } else {
                const isoParts = rawValue.split('-');
                if (isoParts.length === 3) {
                    const year = parseInt(isoParts[0], 10);
                    const month = parseInt(isoParts[1], 10) - 1;
                    const day = parseInt(isoParts[2], 10);
                    if (!Number.isNaN(year) && !Number.isNaN(month) && !Number.isNaN(day)) {
                        const parsedIso = new Date(year, month, day);
                        if (!Number.isNaN(parsedIso.getTime())) {
                            return parsedIso;
                        }
                    }
                }

                const slashParts = rawValue.split('/');
                if (slashParts.length === 3) {
                    const month = parseInt(slashParts[0], 10) - 1;
                    const day = parseInt(slashParts[1], 10);
                    const year = parseInt(slashParts[2], 10);
                    if (!Number.isNaN(year) && !Number.isNaN(month) && !Number.isNaN(day)) {
                        const parsedSlash = new Date(year, month, day);
                        if (!Number.isNaN(parsedSlash.getTime())) {
                            return parsedSlash;
                        }
                    }
                }

                const fallback = new Date(rawValue);
                if (!Number.isNaN(fallback.getTime())) {
                    return fallback;
                }
            }

            return null;
        }

        function getDateKey(date) {
            if (!(date instanceof Date)) {
                return '';
            }

            const year = date.getFullYear();
            const month = (date.getMonth() + 1).toString().padStart(2, '0');
            const day = date.getDate().toString().padStart(2, '0');

            return year + '-' + month + '-' + day;
        }

        function getUsageFor(dateKey, eventType) {
            if (!dateKey || !eventType) {
                return [];
            }

            const byDate = usageSummary[dateKey];
            if (!byDate || typeof byDate !== 'object') {
                return [];
            }

            const slots = byDate[eventType];
            if (!Array.isArray(slots)) {
                return [];
            }

            return slots.slice();
        }

        function computeAvailableSlots(eventType, selectedDate) {
            if (!eventType || !(selectedDate instanceof Date)) {
                return { slots: [], reason: '' };
            }

            const day = selectedDate.getDay();
            const eventKey = eventType.toLowerCase();
            const dateKey = getDateKey(selectedDate);
            const usage = getUsageFor(dateKey, eventType);
            const takenSet = new Set(usage);
            const result = { slots: [], reason: '' };

            const normalizedSelected = new Date(selectedDate.getTime());
            normalizedSelected.setHours(0, 0, 0, 0);

            if (normalizedSelected.getTime() < today.getTime()) {
                result.reason = 'date_in_past';
                return result;
            }

            if (eventKey === 'wedding') {
                if (day === 0) {
                    result.reason = 'day_not_allowed';
                    return result;
                }

                if (takenSet.has(UNKNOWN_SLOT)) {
                    result.reason = 'fully_booked';
                    return result;
                }

                const morningSlot = { value: '7:30 AM - 10:00 AM', label: '7:30 AM – 10:00 AM' };
                const afternoonSlot = { value: '3:00 PM - 5:00 PM', label: '3:00 PM – 5:00 PM' };

                if (!takenSet.has(morningSlot.value)) {
                    result.slots.push(morningSlot);
                }

                if (takenSet.has(morningSlot.value) && !takenSet.has(afternoonSlot.value)) {
                    result.slots.push(afternoonSlot);
                }

                if (result.slots.length === 0) {
                    result.reason = usage.length > 0 ? 'fully_booked' : 'day_not_allowed';
                }

                return result;
            }

            if (eventKey === 'baptism') {
                if (day !== 0 && day !== 6) {
                    result.reason = 'day_not_allowed';
                    return result;
                }

                if (takenSet.has(UNKNOWN_SLOT)) {
                    result.reason = 'fully_booked';
                    return result;
                }

                const slotValue = '11:00 AM - 12:00 PM';
                if (!takenSet.has(slotValue)) {
                    result.slots.push({ value: slotValue, label: '11:00 AM – 12:00 PM' });
                } else {
                    result.reason = 'fully_booked';
                }

                if (result.slots.length === 0 && result.reason === '') {
                    result.reason = 'fully_booked';
                }

                return result;
            }

            if (eventKey === 'funeral') {
                if (takenSet.has(UNKNOWN_SLOT)) {
                    result.reason = 'fully_booked';
                    return result;
                }

                const baseSlots = (day === 0 || day === 1)
                    ? ['1:00 PM', '2:00 PM']
                    : ['8:00 AM', '9:00 AM', '10:00 AM'];

                baseSlots.forEach(function (slot) {
                    if (!takenSet.has(slot)) {
                        result.slots.push({ value: slot, label: slot });
                    }
                });

                if (result.slots.length === 0) {
                    result.reason = 'fully_booked';
                }

                return result;
            }

            return result;
        }

        function getHelpMessage(eventType, hasDate, result) {
            if (!eventType) {
                return 'Select an event type to view available times.';
            }

            if (!hasDate) {
                return 'Choose a date to see available times.';
            }

            if (result.slots.length > 0) {
                return 'Select from the available times below.';
            }

            if (result.reason === 'day_not_allowed') {
                if (eventType === 'Wedding') {
                    return 'Weddings may be scheduled Monday through Saturday.';
                }
                if (eventType === 'Baptism') {
                    return 'Baptisms are celebrated on Saturdays and Sundays only.';
                }
                return 'The selected event type is not available on that day.';
            }

            if (result.reason === 'date_in_past') {
                return 'Past dates cannot be reserved. Please choose a future date.';
            }

            if (result.reason === 'fully_booked') {
                if (eventType === 'Wedding') {
                    return 'Both wedding slots are already reserved for this date.';
                }
                if (eventType === 'Baptism') {
                    return 'The baptism schedule is fully booked for this date.';
                }
                return 'All funeral times for this date are booked.';
            }

            return 'No time slots are available for the selected date.';
        }

        function renderOptions(result, eventType) {
            const currentValue = timeSelect.value || lastSelectedValue;
            let placeholderText = 'Select a time';

            if (result.slots.length === 0) {
                if (result.reason === 'day_not_allowed') {
                    if (eventType === 'Baptism') {
                        placeholderText = 'Baptisms are available on weekends only.';
                    } else if (eventType === 'Wedding') {
                        placeholderText = 'Weddings are unavailable on Sundays.';
                    } else {
                        placeholderText = 'No times are available for this date.';
                    }
                } else if (result.reason === 'fully_booked') {
                    placeholderText = 'All times for this date are booked.';
                } else if (result.reason === 'date_in_past') {
                    placeholderText = 'Past dates cannot be reserved.';
                } else {
                    placeholderText = 'No available times.';
                }
            }

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = placeholderText;
            placeholder.disabled = result.slots.length === 0;

            timeSelect.innerHTML = '';
            timeSelect.appendChild(placeholder);

            let hasSelection = false;

            result.slots.forEach(function (slot) {
                if (!slot || typeof slot.value !== 'string') {
                    return;
                }

                const option = document.createElement('option');
                option.value = slot.value;
                option.textContent = slot.label || slot.value;
                if (!hasSelection && currentValue === slot.value) {
                    option.selected = true;
                    hasSelection = true;
                    placeholder.selected = false;
                }
                timeSelect.appendChild(option);
            });

            if (!hasSelection) {
                timeSelect.value = '';
                lastSelectedValue = '';
                placeholder.selected = true;
            } else {
                lastSelectedValue = timeSelect.value;
            }

            timeSelect.disabled = result.slots.length === 0;
        }

        function updateSubmitState(result) {
            if (!result || typeof result !== 'object') {
                result = { slots: [], reason: '' };
            }

            const hasSelection = typeof timeSelect.value === 'string' && timeSelect.value.trim() !== '';
            const hasAvailableSlots = Array.isArray(result.slots) && result.slots.length > 0;
            const shouldDisableSubmit = !hasSelection || !hasAvailableSlots;

            if (submitButton instanceof HTMLButtonElement) {
                submitButton.disabled = shouldDisableSubmit;

                if (shouldDisableSubmit) {
                    submitButton.setAttribute('aria-disabled', 'true');
                } else {
                    submitButton.removeAttribute('aria-disabled');
                }
            }

            if (unavailableNotice instanceof HTMLElement) {
                let message = '';

                if (Array.isArray(result.slots) && result.slots.length === 0) {
                    if (result.reason === 'fully_booked') {
                        message = 'This date is fully booked. Please choose another available date or time.';
                    } else if (result.reason === 'day_not_allowed') {
                        message = 'Reservations are not available for this event type on the selected date.';
                    } else if (result.reason === 'date_in_past') {
                        message = 'Past dates cannot be reserved. Please select a future date.';
                    }
                }

                if (message) {
                    unavailableNotice.textContent = message;
                    unavailableNotice.classList.remove('d-none');
                } else {
                    unavailableNotice.textContent = '';
                    unavailableNotice.classList.add('d-none');
                }
            }
        }

        function updateTimeOptions() {
            const eventType = getSelectedEventType();
            const parsedDate = parseDateValue();
            const result = computeAvailableSlots(eventType, parsedDate);
            const hasDate = parsedDate instanceof Date;

            lastComputedResult = result;
            renderOptions(result, eventType);

            if (helpText) {
                helpText.textContent = getHelpMessage(eventType, hasDate, result);
            }

            updateSubmitState(result);
        }

        timeSelect.addEventListener('change', function () {
            lastSelectedValue = timeSelect.value;
            updateSubmitState(lastComputedResult);
        });

        Array.prototype.forEach.call(eventTypeRadios, function (radio) {
            radio.addEventListener('change', updateTimeOptions);
        });

        dateInput.addEventListener('change', updateTimeOptions);
        dateInput.addEventListener('input', updateTimeOptions);

        modalElement.addEventListener('reservation:reset', function () {
            lastSelectedValue = timeSelect.getAttribute('data-initial-value') || '';
            updateTimeOptions();
        });

        modalElement.addEventListener('reservation:show-form', updateTimeOptions);

        updateTimeOptions();
    }

    function initEventTypeToggle() {
        const modalElement = document.getElementById('reservationDayModal');
        if (!modalElement) {
            return;
        }

        const formElement = modalElement.querySelector('[data-reservation-form]');
        const weddingDetailsBox = modalElement.querySelector('#wedding-details');
        const weddingRequiredFields = modalElement.querySelectorAll('[data-wedding-required="true"]');
        const weddingCheckboxes = modalElement.querySelectorAll('[data-wedding-checkbox="true"]');
        const weddingSeminarInput = modalElement.querySelector('#wedding-seminar-date');
        const $weddingSeminarInput = weddingSeminarInput ? $(weddingSeminarInput) : null;
        const canUseSeminarDatepicker = Boolean(
            $weddingSeminarInput && typeof $weddingSeminarInput.datepicker === 'function'
        );
        let seminarDatepickerInitialized = false;
        const reservationDateInput = modalElement.querySelector('#reservation-date');
        const funeralDetailsBox = modalElement.querySelector('#funeral-details');
        const funeralRequiredFields = modalElement.querySelectorAll('[data-funeral-required="true"]');
        const funeralMaritalSelect = modalElement.querySelector('[data-funeral-marital-select="true"]');
        const attachmentSections = modalElement.querySelectorAll('[data-attachment-section]');
        const eventTypeRadios = modalElement.querySelectorAll('input[name="reservation-type"]');
        const conditionalFieldNames = [];

        Array.prototype.forEach.call(attachmentSections, function (section) {
            if (!(section instanceof HTMLElement)) {
                return;
            }

            const conditionalGroups = section.querySelectorAll('[data-attachment-conditional-field]');
            Array.prototype.forEach.call(conditionalGroups, function (group) {
                const fieldName = group.getAttribute('data-attachment-conditional-field');
                if (fieldName && conditionalFieldNames.indexOf(fieldName) === -1) {
                    conditionalFieldNames.push(fieldName);
                }
            });
        });

        function clearField(field) {
            if (!(field instanceof HTMLElement)) {
                return;
            }

            if (field instanceof HTMLInputElement) {
                if (field.type === 'checkbox' || field.type === 'radio') {
                    field.checked = false;
                } else {
                    field.value = '';
                }
            } else if (field instanceof HTMLTextAreaElement) {
                field.value = '';
            } else if (typeof HTMLSelectElement !== 'undefined' && field instanceof HTMLSelectElement) {
                field.selectedIndex = 0;
            }
        }

        function getConditionalFieldValue(fieldName) {
            if (typeof fieldName !== 'string' || fieldName === '') {
                return '';
            }

            const controls = modalElement.querySelectorAll('[name="' + fieldName + '"]');
            let value = '';

            Array.prototype.forEach.call(controls, function (control) {
                if (control instanceof HTMLInputElement) {
                    if (control.type === 'radio' || control.type === 'checkbox') {
                        if (control.checked) {
                            value = control.value;
                        }
                    } else {
                        value = control.value;
                    }
                } else if (typeof HTMLSelectElement !== 'undefined' && control instanceof HTMLSelectElement) {
                    value = control.value;
                } else if (control instanceof HTMLTextAreaElement) {
                    value = control.value;
                }
            });

            return typeof value === 'string' ? value : '';
        }

        function formatDateForInput(date) {
            if (!(date instanceof Date)) {
                return '';
            }

            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        function parseIsoDate(value) {
            if (typeof value !== 'string') {
                return null;
            }

            const parts = value.trim().split('-');
            if (parts.length !== 3) {
                return null;
            }

            const year = parseInt(parts[0], 10);
            const month = parseInt(parts[1], 10) - 1;
            const day = parseInt(parts[2], 10);

            if (Number.isNaN(year) || Number.isNaN(month) || Number.isNaN(day)) {
                return null;
            }

            const parsed = new Date(year, month, day);
            if (Number.isNaN(parsed.getTime())) {
                return null;
            }

            parsed.setHours(0, 0, 0, 0);
            return parsed;
        }

        function getSelectedEventType() {
            let selectedType = '';
            Array.prototype.forEach.call(eventTypeRadios, function (radio) {
                if (radio.checked) {
                    selectedType = radio.value;
                }
            });
            return selectedType;
        }

        if (!canUseSeminarDatepicker && weddingSeminarInput instanceof HTMLInputElement) {
            try {
                if (weddingSeminarInput.type !== 'date') {
                    weddingSeminarInput.setAttribute('type', 'date');
                }
            } catch (error) {
                // Ignore failures when the browser does not allow switching the input type.
            }
        }

        function getWeddingSeminarRange() {
            const result = { min: null, max: null };

            if (!(reservationDateInput instanceof HTMLInputElement)) {
                return result;
            }

            const weddingDate = parseIsoDate(reservationDateInput.value || '');
            if (!(weddingDate instanceof Date)) {
                return result;
            }

            const latestSeminarDate = new Date(weddingDate.getTime());
            latestSeminarDate.setDate(latestSeminarDate.getDate() - 1);

            const earliestSeminarDate = new Date(weddingDate.getTime());
            earliestSeminarDate.setDate(earliestSeminarDate.getDate() - 5);

            result.min = earliestSeminarDate;
            result.max = latestSeminarDate;
            return result;
        }

        function ensureSeminarDatepicker() {
            if (!canUseSeminarDatepicker || !$weddingSeminarInput) {
                return false;
            }

            if (!seminarDatepickerInitialized) {
                const initialValue = weddingSeminarInput.value || '';
                $weddingSeminarInput.datepicker({
                    uiLibrary: 'bootstrap4',
                    iconsLibrary: 'fontawesome',
                    format: 'yyyy-mm-dd',
                    showRightIcon: true,
                    value: initialValue ? initialValue : undefined,
                    minDate: function () {
                        const range = getWeddingSeminarRange();
                        if (range.min instanceof Date) {
                            return formatDateForInput(range.min);
                        }
                        return undefined;
                    },
                    maxDate: function () {
                        const range = getWeddingSeminarRange();
                        if (range.max instanceof Date) {
                            return formatDateForInput(range.max);
                        }
                        return undefined;
                    }
                });
                seminarDatepickerInitialized = true;
            }

            return true;
        }

        function setSeminarInputDisabled(disabled, hasDatepicker) {
            if (!(weddingSeminarInput instanceof HTMLInputElement)) {
                return;
            }

            if (disabled) {
                weddingSeminarInput.setAttribute('aria-disabled', 'true');
            } else {
                weddingSeminarInput.removeAttribute('aria-disabled');
            }

            if (hasDatepicker && $weddingSeminarInput) {
                $weddingSeminarInput.prop('disabled', disabled);
                const wrapper = $weddingSeminarInput.parent('[role="wrapper"]');
                if (wrapper.length) {
                    const button = wrapper.find('[role="right-icon"] button');
                    if (button.length) {
                        button.prop('disabled', disabled);
                        if (disabled) {
                            button.attr('aria-disabled', 'true');
                        } else {
                            button.removeAttr('aria-disabled');
                        }
                    }
                }
            } else if (disabled) {
                weddingSeminarInput.setAttribute('disabled', 'disabled');
            } else {
                weddingSeminarInput.removeAttribute('disabled');
            }
        }

        function clearSeminarValue() {
            if (!(weddingSeminarInput instanceof HTMLInputElement)) {
                return;
            }

            weddingSeminarInput.value = '';
            if ($weddingSeminarInput) {
                $weddingSeminarInput.val('');
            }
        }

        function updateWeddingSeminarLimits(isWeddingSelected) {
            if (!(weddingSeminarInput instanceof HTMLInputElement)) {
                return;
            }

            const hasDatepicker = ensureSeminarDatepicker();

            if (!isWeddingSelected) {
                if (!hasDatepicker) {
                    weddingSeminarInput.removeAttribute('min');
                    weddingSeminarInput.removeAttribute('max');
                }
                clearSeminarValue();
                setSeminarInputDisabled(true, hasDatepicker);
                return;
            }

            const range = getWeddingSeminarRange();
            const hasRange = range.min instanceof Date && range.max instanceof Date;

            if (!hasRange) {
                if (!hasDatepicker) {
                    weddingSeminarInput.removeAttribute('min');
                    weddingSeminarInput.removeAttribute('max');
                }
                clearSeminarValue();
                setSeminarInputDisabled(true, hasDatepicker);
                return;
            }

            setSeminarInputDisabled(false, hasDatepicker);

            if (!hasDatepicker) {
                weddingSeminarInput.setAttribute('min', formatDateForInput(range.min));
                weddingSeminarInput.setAttribute('max', formatDateForInput(range.max));
            }

            const currentValue = parseIsoDate(weddingSeminarInput.value || '');
            if (!(currentValue instanceof Date) || currentValue < range.min || currentValue > range.max) {
                clearSeminarValue();
            }
        }

        function isFormVisible() {
            if (!(formElement instanceof HTMLElement)) {
                return true;
            }

            return !formElement.classList.contains('d-none');
        }

        function updateAttachmentSections(selectedType) {
            const shouldDisplayAttachments = isFormVisible();

            Array.prototype.forEach.call(attachmentSections, function (section) {
                if (!(section instanceof HTMLElement)) {
                    return;
                }

                const sectionType = section.getAttribute('data-attachment-section');
                const isActiveSection = shouldDisplayAttachments && sectionType === selectedType;
                section.style.display = isActiveSection ? '' : 'none';

                const attachmentGroups = section.querySelectorAll('[data-attachment-field]');
                Array.prototype.forEach.call(attachmentGroups, function (group) {
                    if (!(group instanceof HTMLElement)) {
                        return;
                    }

                    const fileInput = group.querySelector('input[type="file"]');
                    if (!(fileInput instanceof HTMLInputElement)) {
                        return;
                    }

                    if (!isActiveSection) {
                        fileInput.removeAttribute('required');
                        fileInput.value = '';
                        group.style.display = 'none';
                        return;
                    }

                    const conditionalField = group.getAttribute('data-attachment-conditional-field');
                    const conditionalValue = group.getAttribute('data-attachment-conditional-value');
                    let shouldShowGroup = true;

                    if (conditionalField) {
                        const currentValue = getConditionalFieldValue(conditionalField);
                        if (conditionalValue === null || conditionalValue === '') {
                            shouldShowGroup = currentValue !== '';
                        } else {
                            shouldShowGroup = currentValue === conditionalValue;
                        }
                    }

                    if (shouldShowGroup) {
                        group.style.display = '';
                        fileInput.setAttribute('required', 'required');
                    } else {
                        group.style.display = 'none';
                        fileInput.removeAttribute('required');
                        fileInput.value = '';
                    }
                });
            });
        }

        function updateVisibility() {
            let selectedType = getSelectedEventType();
            if (!selectedType) {
                selectedType = 'Baptism';
            }

            const isWedding = selectedType === 'Wedding';
            const isFuneral = selectedType === 'Funeral';

            if (weddingDetailsBox) {
                weddingDetailsBox.style.display = isWedding ? '' : 'none';
            }

            Array.prototype.forEach.call(weddingRequiredFields, function (field) {
                if (!(field instanceof HTMLElement)) {
                    return;
                }

                if (isWedding) {
                    field.setAttribute('required', 'required');
                } else {
                    field.removeAttribute('required');
                    clearField(field);
                }
            });

            if (!isWedding) {
                Array.prototype.forEach.call(weddingCheckboxes, function (checkbox) {
                    if (checkbox instanceof HTMLInputElement && checkbox.type === 'checkbox') {
                        checkbox.checked = false;
                    }
                });
            }

            updateWeddingSeminarLimits(isWedding);

            if (funeralDetailsBox) {
                funeralDetailsBox.style.display = isFuneral ? '' : 'none';
            }

            Array.prototype.forEach.call(funeralRequiredFields, function (field) {
                if (!(field instanceof HTMLElement)) {
                    return;
                }

                if (isFuneral) {
                    field.setAttribute('required', 'required');
                } else {
                    field.removeAttribute('required');
                    clearField(field);
                }
            });

            if (!isFuneral && funeralMaritalSelect) {
                clearField(funeralMaritalSelect);
            }

            updateAttachmentSections(selectedType);
        }

        Array.prototype.forEach.call(eventTypeRadios, function (radio) {
            radio.addEventListener('change', updateVisibility);
        });

        if (reservationDateInput instanceof HTMLInputElement) {
            const syncSeminarLimits = function () {
                updateWeddingSeminarLimits(getSelectedEventType() === 'Wedding');
            };

            reservationDateInput.addEventListener('change', syncSeminarLimits);
            reservationDateInput.addEventListener('input', syncSeminarLimits);
        }

        if (funeralMaritalSelect) {
            funeralMaritalSelect.addEventListener('change', updateVisibility);
        }

        for (let index = 0; index < conditionalFieldNames.length; index += 1) {
            const fieldName = conditionalFieldNames[index];
            const controls = modalElement.querySelectorAll('[name="' + fieldName + '"]');
            Array.prototype.forEach.call(controls, function (control) {
                control.addEventListener('change', updateVisibility);
            });
        }

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

    function displayReservationNotifications() {
        if (typeof Swal === 'undefined' || typeof Swal.fire !== 'function') {
            return;
        }

        const notifications = Array.isArray(window.reservationNotifications)
            ? window.reservationNotifications.slice()
            : [];

        if (!notifications.length) {
            return;
        }

        const allowedIcons = {
            success: true,
            error: true,
            warning: true,
            info: true,
            question: true
        };

        function showNext(index) {
            if (index >= notifications.length) {
                return;
            }

            const notification = notifications[index];
            if (!notification || typeof notification !== 'object') {
                showNext(index + 1);
                return;
            }

            const icon = (typeof notification.icon === 'string' && allowedIcons[notification.icon])
                ? notification.icon
                : 'info';
            const title = typeof notification.title === 'string' ? notification.title.trim() : '';
            const text = typeof notification.text === 'string' ? notification.text.trim() : '';

            if (!title && !text) {
                showNext(index + 1);
                return;
            }

            Swal.fire({
                icon: icon,
                title: title || undefined,
                text: text || undefined,
                confirmButtonText: 'OK'
            }).then(function () {
                showNext(index + 1);
            });
        }

        showNext(0);
    }

    $(document).ready(function () {
        initDatepicker();
        initEventTypeToggle();
        initCalendar();
        initTimeSlotSelector();
        initFormHandler();
        displayReservationNotifications();
    });
})(jQuery);
