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
      data['custom_pickup'] = null;

      if ($("#pickup-changer-" + shipping_method).length) {
        data['custom_pickup'] = $("#pickup-changer-" + shipping_method + " .pakettikauppa-pickup-select").find(':selected').data('id');
      }

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
    var btn_txt = $("#pakettikauppa_metabtn_change").data("txt1");
    if ($("#wc_pakettikauppa_shipping_method").is(':visible')) {
      $("#wc_pakettikauppa_shipping_method").slideUp("slow");
      $("#wc_pakettikauppa_custom_shipping_method").slideDown("slow");
      btn_txt = $("#pakettikauppa_metabtn_change").data("txt2");
    } else {
      $("#wc_pakettikauppa_custom_shipping_method").slideUp("slow");
      $("#wc_pakettikauppa_shipping_method").slideDown("slow");
    }
    $("#pakettikauppa_metabtn_change").html(btn_txt);

    pakettikauppa_change_shipping_method();
  };

  window.pakettikauppa_change_shipping_method = function() {
    var selectedService = $('#pakettikauppa-service').val();
    $(".pk-admin-additional-services").each(function (i, obj) {
      $(this).hide();
    });
    $(".pakettikauppa-pickup-changer").each(function (i, obj) {
      $(this).hide();
    });

    $("#pk-admin-additional-services-" + selectedService).show();
    $("#pickup-changer-" + selectedService).show();
    pakettikauppa_trigger_pickup_list(selectedService);
  };

  window.pakettikauppa_pickup_points_by_custom_address = function(values) {
    var address = $("#"+values.container_id).find(".pakettikauppa-pickup-search-field").val();
    var method = $("#"+values.container_id).find(".pakettikauppa-pickup-method").val();
    var select_field = $("#"+values.container_id).find(".pakettikauppa-pickup-select");

    $("#"+values.container_id).find(".error-pickup-search").hide();

    $(select_field).empty();
    $(select_field).append($('<option>', { value: "__NULL__", text : "..." }));

    var data = {
      action: 'get_pickup_point_by_custom_address',
      security: $("#pakettikauppa_metabox_nonce").val(),
      address: address,
      method: method
    }

    jQuery.post(ajaxurl, data, function(response) {
      $(select_field).empty();
      var selected_value = $(select_field).data("selected");
      if (response == "error-zip") {
        $("#"+values.container_id).find(".error-pickup-search").show();
        console.log("Search error: Postcode is required.");
        var option = $('<option>', { text : "---" });
        $(select_field).append(option);
        pakettikauppa_change_selected_pickup_point(select_field);
      } else {
        var pickup_points = JSON.parse(response);
        $.each(pickup_points, function (i, point) {
          var option_value = "" + point.provider + ": " + point.name + " (#" + point.pickup_point_id + ")";
          var option_name = "" + point.provider + ": " + point.name + " (" + point.street_address + ")";
          var option = $('<option>', { 
            value: option_value,
            text : option_name
          });
          if (selected_value == option_value) {
            $(option).attr('selected','selected');
          }
          $(option).data("id",point.pickup_point_id);
          $(select_field).append(option);
        });
      }
    });
  };

  window.pakettikauppa_change_element_value = function(element,value) {
    $(element).val(value);
  };
  window.pakettikauppa_change_element_html = function(element,html) {
    $(element).html(html);
  };
  window.pakettikauppa_change_element_text = function(element,text) {
    $(element).text(text);
  };

  window.pakettikauppa_trigger_pickup_list = function(id) {
    var changer_id = "pickup-changer-" + id;
    $("#"+changer_id+" .btn-search").trigger("click");
  };

  window.pakettikauppa_change_selected_pickup_point = function(select_field) {
    if ($(select_field).val() != "__NULL__" || $(select_field).val() != "") {
      var value = $(select_field).val();
      $(select_field).data("selected",value);
    } else {
      $(select_field).data("selected",null);
    }
  };
});
