// phpcs:disable PEAR.Functions.FunctionCallSignature
function pakettikauppa_pickup_point_change(element) {
  var $ = jQuery;
  var data = {
    action: 'pakettikauppa_save_pickup_point_info_to_session',
    security: $("#pakettikauppa_pickup_point_update_nonce").val(),
    pickup_point_id: $(element).val()
  };

  $.post(wc_checkout_params.ajax_url, data, function (response) {
    // do nothing
  }).fail(function (e) {
    // do nothing
  });
}

function pakettikauppa_custom_pickup_point_change(element) {
  var $ = jQuery;
  var address = element.value;

  var data = {
    action: 'pakettikauppa_use_custom_address_for_pickup_point',
    security: $("#pakettikauppa_pickup_point_update_nonce").val(),
    address: address
  }

  $.post(wc_checkout_params.ajax_url, data, function (response) {
    $('body').trigger('update_checkout');
  }).fail(function (e) {
    // should probably do SOMETHING?
  });
}
