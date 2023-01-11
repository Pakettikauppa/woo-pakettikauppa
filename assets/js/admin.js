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
      security: $('#pakettikauppa_metabox_nonce').val(),
      request_id: $('#pakettikauppa_microtime').val()
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
      data['additional_text'] = $('textarea.pakettikauppa-additional-info').val();

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

    if($('#wc_pakettikauppa_add_to_manifest').is(':checked')){
      data['add_to_manifest'] = 1;
    }

    data['for_products'] = [];
    $('.prod_select_dropdown .item_cb').each(function (i, obj) {
      if ($(this).is(':checked')) {
        data['for_products'].push({
          prod: $(this).val(),
          qty: $(this).siblings('.quantity').val()
        });
      }
    });

    if ($(obj).prop('tagName') == 'A') {
      data[$(obj).attr('name')] = $(obj).data('value');
    } else {
      data[$(obj).attr('name')] = $(obj).val();
    }

    $.post(woocommerce_admin_meta_boxes.ajax_url, data, function(response) {
      $("#woo-pakettikauppa .inside").html(response);
      $('#woo-pakettikauppa').unblock();
    }).fail(function() {
      location.reload();
    });
  };

  window.pakettikauppa_meta_box_bulk_submit = function(obj) {
    $('#pakettikauppa-modal').block({
      message: null,
      overlayCSS: {
        background: '#fff',
        opacity: 0.6
      }
    });

    var ids = [];
    $('#pakettikauppa-modal').find("input[name='pakettikauppa_order_id[]']").each(function() {
        ids.push($(this).val());
    });

    if(ids.length > 0)
    {
      ids.forEach(function(id)
      {
        var data = {
          action: 'pakettikauppa_meta_box_bulk',
          post_id: id,
          security: $('#woo-pakettikauppa_'+id+' #pakettikauppa_metabox_nonce').val(),
          request_id: $('#woo-pakettikauppa_'+id+' #pakettikauppa_microtime').val(),
        };

        var shipping_method = $('#woo-pakettikauppa_'+id+' #pakettikauppa-service').val();
        data['wc_pakettikauppa_service_id'] = shipping_method;
        data['custom_method'] = 1;

        if ($("#woo-pakettikauppa_"+id+" #pickup-changer-" + shipping_method).length) {
          data['custom_pickup'] = $("#woo-pakettikauppa_"+id+" #pickup-changer-" + shipping_method + " .pakettikauppa-pickup-select").find(':selected').data('id');
        }

        data['additional_text'] = $('#woo-pakettikauppa_'+id+' textarea.pakettikauppa-additional-info').val();

        data[$(obj).attr('name')] = $(obj).val();

        $.post(woocommerce_admin_meta_boxes.ajax_url, data, function(response) {
          $("#woo-pakettikauppa_"+id+".inside td:last-child").remove();
          $("#woo-pakettikauppa_"+id+".inside td:last-child").remove();
          $("#woo-pakettikauppa_"+id+".inside").append(response);
          $('#pakettikauppa-modal').unblock();
        }).fail(function(error) {
          console.log(error);
        });

      });
    }
  };

  window.pakettikauppa_change_method = function(obj) {

    var btn_txt = $(obj).data("txt1");

    if ($($(obj).closest('div')).find('#wc_pakettikauppa_shipping_method').is(':visible')) {
      $($(obj).closest('div')).find('#wc_pakettikauppa_shipping_method').slideUp("slow");
      $($(obj).closest('div')).find('#wc_pakettikauppa_custom_shipping_method').slideDown("slow");
      btn_txt = $(obj).data("txt2");
    } else {
      $($(obj).closest('div')).find('#wc_pakettikauppa_custom_shipping_method').slideUp("slow");
      $($(obj).closest('div')).find('#wc_pakettikauppa_shipping_method').slideDown("slow");
    }
    $(obj).html(btn_txt);

    pakettikauppa_change_shipping_method($('#pakettikauppa-service'));
  };

  window.pakettikauppa_change_shipping_method = function(obj) {
    var selectedService = $(obj).val();
    $(".pk-admin-additional-services").each(function (i, obj) {
      $(this).hide();
    });
    $($(obj).closest('.pakettikauppa-metabox-fieldset')).find(".pakettikauppa-pickup-changer").each(function (i, obj) {
      $(this).hide();
    });

    $("#pk-admin-additional-services-" + selectedService).show();
    $($(obj).closest('.pakettikauppa-metabox-fieldset')).find("#pickup-changer-" + selectedService).show();
    pakettikauppa_trigger_pickup_list(selectedService);

    var element = document.querySelector('.prod_select_dropdown .content .quantity');
    if(element){
      element.dispatchEvent(new Event('input', {bubbles:true}));
    }

  };

  window.pakettikauppa_pickup_points_by_custom_address = function(values) {
    var address = $("#"+values.container_id).find(".pakettikauppa-pickup-search-field").val();
    var method = $("#"+values.container_id).find(".pakettikauppa-pickup-method").val();
    var select_field = $("#"+values.container_id).find(".pakettikauppa-pickup-select");
    var pickup_point_type = $("#"+values.container_id).find('input[name="wc_pakettikauppa_search_filter"]:checked').val();

    $("#"+values.container_id).find(".error-pickup-search").hide();

    $(select_field).empty();
    $(select_field).append($('<option>', { value: "__NULL__", text : "..." }));

    var data = {
      action: 'get_pickup_point_by_custom_address',
      security: $("#pakettikauppa_metabox_nonce").val(),
      address: address,
      method: method,
      type: pickup_point_type
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

  $(document).on("change", "ol.pk-admin-additional-services input", function() {
    var element = document.querySelector('.prod_select_dropdown .content .quantity');
    element.dispatchEvent(new Event('input', {bubbles:true}));
  });
});

/* Multiple tracking codes */
function init_prod_select() {
  var txt = document.getElementById( 'prod_select_droptxt' ),
  content = document.getElementById( 'prod_select_content' ),
  list = document.querySelectorAll( '.prod_select_dropdown .content input[type="checkbox"]' ),
  quantity = document.querySelectorAll( '.prod_select_dropdown .quantity' );

  if ( ! txt ) return;

  txt.addEventListener( 'click', function() {
    content.classList.toggle( 'show' );
  } );

  window.onclick = function( e ) {
    if ( ! e.target.closest( '.list' ) ) {
      if ( content.classList.contains( 'show' ) ) {
        content.classList.remove( 'show' );
      }
    }
  }

  list.forEach( function( item, index ) {
    item.addEventListener( 'click', function() {
      quantity[ index ].type = ( item.checked ) ? 'number' : 'hidden';
      update_prod_select(list, quantity, txt);
    } );
    item.click();
    item.addEventListener( 'click', function() {
      update_estimated_price(list, quantity);
    } );
  } );

  quantity.forEach( function( item ) {
    item.addEventListener( 'input', function() {
      var max = parseInt(this.max);
      if (parseInt(this.value) < 1) {
        this.value = 1;
      }
      if (parseInt(this.value) > max) {
        this.value = max;
      }
      update_prod_select(list, quantity, txt);
      update_estimated_price(list, quantity);
    } );
  } );
}

function update_prod_select(list, quantity, txt) {
  for ( var i = 0, arr = []; i < list.length; i++ ) {
    if ( list[ i ].checked ) arr.push( quantity[ i ].value + ' x ' + list[ i ].getAttribute('data-name') );
  }

  if (arr.length) {
    txt.value = arr.join( ', ' );
    resize_textarea(txt);
  } else {
    txt.value = '-'
    resize_textarea(txt);
  }

  update_lqweight_span(list, quantity);
}

function update_lqweight_span(list, quantity) {
  var total_weight = 0;
  for ( var i = 0; i < list.length; i++ ) {
    if ( list[ i ].checked ) {
      total_weight += list[ i ].getAttribute('data-lqweight') * quantity[ i ].value;
    }
  }

  var all_spans = document.querySelectorAll( '.pakettikauppa-services .changeable_lqweight' );
  for ( var i = 0; i < all_spans.length; i++ ) {
    all_spans[i].innerHTML = total_weight;
  }
}

function update_estimated_price(list, quantity) {
  var estimated_block = document.getElementById("estimated-shipping-price");
  if ( typeof(estimated_block) == 'undefined' || estimated_block == null ) {
    return;
  }

  var selected_products = [];
  for ( var i = 0; i < list.length; i++ ) {
    var qty = quantity[i].value;
    if ( ! list[i].checked ) {
      qty = 0;
    }
    selected_products.push({
      prod: list[i].value,
      qty: qty
    });
  }

  var selected_method = estimated_block.dataset.service;
  var additional_services = [];
  if ( ! jQuery("#wc_pakettikauppa_shipping_method").is(':visible') ) {
    selected_method = document.getElementById("pakettikauppa-service").value;

    var all_services = document.getElementById("pk-admin-additional-services-" + selected_method).getElementsByTagName("input");
    for ( var i = 0; i < all_services.length; i++ ) {
      if ( all_services[i].checked ) {
        additional_services.push({
          key: all_services[i].value,
          param: ""
        });
      }
      if ( all_services[i].type == 'number' ) {
        additional_services.push({
          key: all_services[i].name,
          param: all_services[i].value
        });
      }
    }
  }

  var selected_point = "";
  var select_field = document.querySelector("#pickup-changer-" + selected_method + " .pakettikauppa-pickup-select");
  if ( select_field !== null && select_field.value != '__NULL__' ) {
    selected_point = select_field.value;
  }

  var data = {
    action: 'update_estimated_shipping_price',
    security: jQuery("#pakettikauppa_metabox_nonce").val(),
    order_id: document.getElementById("pakettikauppa_metabox_order_id").value,
    method: selected_method,
    selected: selected_products,
    services: additional_services,
    point: selected_point
  }

  jQuery.post(ajaxurl, data, function(response) {
    estimated_block.innerHTML = response;
  });
}

function resize_textarea(element) {
  element.style.height = "1px";
  element.style.height = (3+element.scrollHeight)+"px";
}

function click_enchancedtextarea_code(field_key, param_key) {
  var $ = jQuery;
  $("#" + field_key).val($("#" + field_key).val() + "{" + param_key + "}");
}
