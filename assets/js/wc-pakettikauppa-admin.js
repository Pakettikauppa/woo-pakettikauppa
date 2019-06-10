/* admin js */
jQuery( function ( $ ) {
  window.pakettikauppa_meta_box_submit = function(obj) {
    $('#wc-pakettikauppa').block({
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
    }

    data[$(obj).attr('name')] = $(obj).val();

    $('.pakettikauppa_metabox_values').each(function (i, obj) {
      data[$(this).attr('name')] = $(this).val();
    });

    $.post(woocommerce_admin_meta_boxes.ajax_url, data, function (response) {
      $("#wc-pakettikauppa .inside").html(response);
      $('#wc-pakettikauppa').unblock();
    });
  }
});
