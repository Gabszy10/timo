(function () {
    'use strict';

    function activateLoadingState(form) {
        if (!form || form.getAttribute('data-loading-active') === 'true') {
            return;
        }

        form.setAttribute('data-loading-active', 'true');

        const buttons = form.querySelectorAll('[data-loading-button]');
        if (!buttons.length) {
            return;
        }

        buttons.forEach(function (button) {
            if (!button) {
                return;
            }

            button.setAttribute('disabled', 'disabled');

            const spinner = button.querySelector('[data-loading-spinner]');
            if (spinner) {
                spinner.classList.remove('d-none');
            }
        });
    }

    function handleSubmit(event) {
        const form = event.currentTarget;
        if (!form) {
            return;
        }

        if (form.getAttribute('data-loading-active') === 'true') {
            event.preventDefault();
            return;
        }

        activateLoadingState(form);
    }

    function initLoadingForms() {
        const forms = document.querySelectorAll('[data-loading-form]');
        if (!forms.length) {
            return;
        }

        forms.forEach(function (form) {
            form.addEventListener('submit', handleSubmit, false);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLoadingForms);
    } else {
        initLoadingForms();
    }
})();
