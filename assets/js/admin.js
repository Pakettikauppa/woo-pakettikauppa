// phpcs:disable PEAR.Functions.FunctionCallSignature
/* admin js */
jQuery(function( $ ) {
  window.pakettikauppa_meta_box_submit = function(obj) {
    $('#woo-pakettikauppa').block({
      message: null,
      overlayCSS: {
        background: '#fff',
        opacity: 0.6
      }
    });

    var data = {
      action: 'pakettikauppa_meta_box',
      post_id: woocommerce_admin_meta_boxes.post_id,
      security: $('#pakettikauppa_metabox_nonce').val()
    };

    if ($("#wc_pakettikauppa_shipping_method").is(':visible')) {
      $("#wc_pakettikauppa_custom_shipping_method").html('');

      $('.pakettikauppa_metabox_values').each(function (i, obj) {
        var name = $(obj).attr('name');
        data[name] = $(this).val();
      });
    } else {
      $("#wc_pakettikauppa_shipping_method").html('');

      var shipping_method = $('#pakettikauppa-service').val();

      data['wc_pakettikauppa_service_id'] = shipping_method;
      data['additional_services'] = [];
      data['custom_method'] = 1;

      $('#pk-admin-additional-services-' + shipping_method + ' .pakettikauppa_metabox_values').each(function (i, obj) {
        var name = $(obj).attr('name');
        data[name] = $(this).val();
      });

      $('#pk-admin-additional-services-' + shipping_method + ' .pakettikauppa_metabox_array_values').each(function (i, obj) {
        if ($(this).prop("checked")) {
          data['additional_services'].push($(this).val());
        }
      });
    }

    data[$(obj).attr('name')] = $(obj).val();

    $.post(woocommerce_admin_meta_boxes.ajax_url, data, function(response) {
      $("#woo-pakettikauppa .inside").html(response);
      $('#woo-pakettikauppa').unblock();
    }).fail(function() {
      location.reload();
    });
  };

  window.pakettikauppa_change_method = function(obj) {
    if ($("#wc_pakettikauppa_shipping_method").is(':visible')) {
      $("#wc_pakettikauppa_shipping_method").hide();
      $("#wc_pakettikauppa_custom_shipping_method").show();
    } else {
      $("#wc_pakettikauppa_custom_shipping_method").hide();
      $("#wc_pakettikauppa_shipping_method").show();
    }

    pakettikauppa_change_shipping_method();
  };

  window.pakettikauppa_change_shipping_method = function() {
    var selectedService = $('#pakettikauppa-service').val();
    $(".pk-admin-additional-services").each(function (i, obj) {
      $(this).hide();
    });

    $("#pk-admin-additional-services-" + selectedService).show();
  };
});
