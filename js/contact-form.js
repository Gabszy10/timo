(function ($) {
    'use strict';

    $(document).ready(function () {
        const form = $('#contact-form');
        const success = $('#contact-success');

        if (!form.length) {
            return;
        }

        form.on('submit', function (event) {
            event.preventDefault();
            if (this.checkValidity()) {
                success.removeClass('d-none');
                success.focus();
                this.reset();
            }
        });
    });
})(jQuery);
