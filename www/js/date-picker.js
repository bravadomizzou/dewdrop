require.config({
    paths: {
        "jquery-ui": DEWDROP.bowerUrl('/jquery-ui/jquery-ui.min')
    }
});

var bsTooltip = jQuery.fn.tooltip;

require(
    ['jquery', 'jquery-ui'],
    function ($) {
        'use strict';

        // Restore Bootstrap tooltip plugin
        jQuery.fn.tooltip = bsTooltip;

        if (Modernizr.touch && Modernizr.inputtypes.date) {
            $('.input-date').attr('type', 'date');
        } else {
            $('.input-date').each(
                function (index, input) {
                    var $input = $(input),
                        content;

                    content  = '<div class="date-input-popover" data-input="' + $input.data('input') + '">';
                    content += '<a href="#" class="btn btn-link btn-close">';
                    content += '<span class="glyphicon glyphicon-remove text-muted"></span>';
                    content += '</a>';
                    content += '<div class="date-wrapper"></div>';
                    content += '</div>';

                    $input.popover({
                        container: 'body',
                        placement: 'bottom',
                        trigger:   'manual',
                        content:   content,
                        html:      true
                    });

                    $input.on(
                        'focus',
                        function () {
                            var $popover;

                            $input.popover('show');

                            $popover = $('[data-input="' + $input.data('input') + '"] .date-wrapper');

                            $('[data-input="' + $input.data('input') + '"] a').on(
                                'click',
                                function (e) {
                                    e.preventDefault();
                                    $input.popover('hide');
                                }
                            );

                            var options = {
                                changeMonth: true,
                                changeYear:  true,
                                defaultDate: moment($input.val()).toDate(),
                                onSelect: function (e) {
                                    var selected = $popover.datepicker('getDate');

                                    if (selected) {
                                        $input.val(moment(selected).format('MM/DD/YYYY'));
                                    } else {
                                        $input.val('');
                                    }

                                    $input.trigger('change');
                                    $input.popover('hide');
                                }
                            };

                            $popover.datepicker(options);
                        }
                    );

                    $('input, textarea, select').on(
                        'focus',
                        function () {
                            if (this !== $input[0]) {
                                $input.popover('hide');
                            }
                        }
                    );
                }
            );
        }
    }
);
