// phpcs:disable PEAR.Functions.FunctionCallSignature
function pakettikauppa_pickup_point_change(obj) {
  jQuery(function( $ ) {
    var data = {
      action: 'pakettikauppa_update_pickup_point',
      security: $("#pakettikauppa_pickup_point_update_nonce").val(),
      pickup_point_id: $(obj).val()
    };

    $.post(wc_checkout_params.ajax_url, data, function (response) {
      // do nothing
    }).fail(function () {
      // do nothing
    });
  });
}
