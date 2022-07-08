jQuery(document).ready(function (a) {
    "use strict";

    a("#woocommerce_wf_australia_post_contracted_rates").click(function () {
        if (this.checked) {
            a('.contract').closest('tr').show();
        } else {
            a('.contract').closest('tr').hide();

        }
    });

    if (xa_auspost_custom.contracted_rates != 1) {
        a('.contract').closest('tr').hide();
    };

});