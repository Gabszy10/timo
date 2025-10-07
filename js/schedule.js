(function ($) {
    'use strict';

    $(document).ready(function () {
        const buttons = $('.filter_buttons .boxed-btn3');
        const items = $('#schedule-events .schedule_item');

        function filterEvents(filter) {
            if (filter === 'all') {
                items.show();
                return;
            }

            items.each(function () {
                const item = $(this);
                const type = item.data('type');
                if (type === filter) {
                    item.show();
                } else {
                    item.hide();
                }
            });
        }

        buttons.on('click', function (event) {
            event.preventDefault();
            const button = $(this);
            buttons.removeClass('active');
            button.addClass('active');
            filterEvents(button.data('filter'));
        });

        filterEvents('all');
    });
})(jQuery);
