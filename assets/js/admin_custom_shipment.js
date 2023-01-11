jQuery(function ($) {
    window.pakettikauppa_meta_box_bulk_submit = function (obj) {
        var ids = [];
        $('#pakettikauppa-shipments-table').find("input[name='pakettikauppa_order_id[]']").each(function () {
            ids.push($(this).val());
        });

        if (ids.length > 0) {
            $('.loader-wrapper').show();
            ids.forEach(function (id) {
                var data = {
                    action: 'pakettikauppa_meta_box_bulk',
                    post_id: id,
                    security: $('#woo-pakettikauppa_' + id + ' #pakettikauppa_metabox_nonce').val(),
                    request_id: $('#woo-pakettikauppa_' + id + ' #pakettikauppa_microtime').val(),
                };

                var shipping_method = $('#woo-pakettikauppa_' + id + ' #pakettikauppa-service').val();
                data['wc_pakettikauppa_service_id'] = shipping_method;
                data['custom_method'] = 1;

                if ($("#woo-pakettikauppa_" + id + " #pickup-changer-" + shipping_method).length) {
                    data['custom_pickup'] = $("#woo-pakettikauppa_" + id + " #pickup-changer-" + shipping_method + " .pakettikauppa-pickup-select").find(':selected').data('id');
                }

                data['additional_text'] = $('#woo-pakettikauppa_' + id + ' textarea.pakettikauppa-additional-info').val();

                data[$(obj).attr('name')] = $(obj).val();

                $.post(ajaxurl, data, function (response) {
                    $("#woo-pakettikauppa_" + id + ".inside td.current").html(response);
                }).fail(function (error) {
                    console.log(error);
                });

            });
        }
    };

    $(document).ready(function(){
        var qs = decodeURI(window.location.search).substr(1).split('&').map(function (value) {
            var data = value.split('=');
            return {
              param : data[0],
              value : data[1]
            }
        });
        $('.loader-wrapper').show();
        var ids = [];
        qs.forEach(function(p){
            if(p.param.includes("id[")){
                ids.push(p.value);
            }
        });

        ids.forEach(function(id){
            loadPickupPoints(id);
        });
    });

    function loadPickupPoints(id){
        var data = {
            action: 'pakettikauppa_get_pickup_points',
            id: id,
        };
        $.post(ajaxurl, data, function (response) {
            $('#woo-pakettikauppa_'+id+' fieldset#wc_pakettikauppa_custom_shipping_method').append(response);
        }).fail(function (error) {
            console.log(error);
        });
    }

    $(document).ajaxStop(function () {
        $('.loader-wrapper').hide();
    });
});

