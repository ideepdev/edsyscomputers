jQuery(document).ready(function (a) {
    "use strict";
    
    a("#woocommerce_wf_australia_post_contracted_rates").click(function () {
        if (this.checked) {
            a('.contract').closest('tr').show();
        } else {
            a('.contract').closest('tr').hide();

        }
    });

    if (elex_ausmypost_custom.contracted_rates != 1) {
        a('.contract').closest('tr').hide();
    };
    a('#wf_australia_post_estimated_delivery_date_enabled').click(function(){
        if (this.checked) {
            a('.australila_post_estimated_delivery').show();
        } else {
            a('.australila_post_estimated_delivery').hide();

        }
    });

    if (a('#wf_australia_post_estimated_delivery_date_enabled').is(':checked')) {
        a('.australila_post_estimated_delivery').show();
    } else {
        a('.australila_post_estimated_delivery').hide();
    }
    
});