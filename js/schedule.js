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
    });
})(jQuery);
