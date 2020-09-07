// phpcs:disable PEAR.Functions.FunctionCallSignature
function pakettikauppa_pickup_point_change(element) {
  var $ = jQuery;
  var data = {
    action: 'pakettikauppa_save_pickup_point_info_to_session',
    security: $("#pakettikauppa_pickup_point_update_nonce").val(),
    pickup_point_id: $(element).val()
  };

  // Ensure that the user knows that the pickup point they chose is private
  var privatePoints = $(element).data('private-points') ? $(element).data('private-points').split(';') : [];
  var chosenPoint = $(element).val();
  var chosenIsPrivate = privatePoints.indexOf(chosenPoint) > -1;
  var global = window.pakettikauppaData;

  if (chosenIsPrivate) {
    var userKnows = confirm(global.privatePickupPointConfirm);

    if (! userKnows) {
      $(element).val('__NULL__');
      return;
    }
  }

  $.post(wc_checkout_params.ajax_url, data, function (response) {
    // Update checkout after selection changes
    $('body').trigger('update_checkout');
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
    // change pickup point value to Other, so custom field is still visible
    $('#pakettikauppa_pickup_point').val('other').change();
  }).fail(function (e) {
    // should probably do SOMETHING?
  });
}
