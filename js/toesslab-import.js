(function ($) {
    "use strict";
    $(document).on('focusout', 'a[data-file-manager-action]', function (e, fdata) {
        if (fdata === undefined) {
            $('#import_csv_go').attr('disabled', true);
        } else {
            $('#import_csv_go').attr('disabled', false);
        }
    });
}(jQuery));
