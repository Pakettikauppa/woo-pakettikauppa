jQuery(document).ready(function($) {
  $('input[name="wc_pakettikauppa_cod"]').change(function(e) {
    wc_pakettikauppa_toggle_cod();
  });

  function wc_pakettikauppa_toggle_cod() {
    if ($('input[name="wc_pakettikauppa_cod"]').is(':checked')) {
      $('#wc-pakettikauppa-cod-reference-wrapper').slideDown(300);
      $('#wc-pakettikauppa-cod-amount-wrapper').slideDown(300);
    } else {
      $('#wc-pakettikauppa-cod-reference-wrapper').slideUp(100);
      $('#wc-pakettikauppa-cod-amount-wrapper').slideUp(100);
    }
  }
  wc_pakettikauppa_toggle_cod();

  function wc_pakettikauppa_toggle_pickup_points() {
    if ($('input[name="wc_pakettikauppa_pickup_points"]').is(':checked')) {
      $('#wc-pakettikauppa-pickup-points-wrapper').slideDown(300);
    } else {
      $('#wc-pakettikauppa-pickup-points-wrapper').slideUp(100);
    }
  }

  $('input[type="radio"][name="wc_pakettikauppa_service_id"]').change(function(e) {
    wc_pakettikauppa_update_pickup_point_wrapper();
  });

  // Display pickup point wrapper only if the service uses pickup points
  function wc_pakettikauppa_update_pickup_point_wrapper(){
    var service_id = $('input[type="radio"][name="wc_pakettikauppa_service_id"]:checked').val();

    // Check if the selected service uses pickup points
    var data = {
      'action': 'admin_update_pickup_point',
      'service_id': service_id
    }

    jQuery.post(ajaxurl, data, function(has_pickup_points) {
      if (has_pickup_points) {
        $('input[name="wc_pakettikauppa_pickup_points"]').val(1).prop('checked', true);
      } else {
        $('input[name="wc_pakettikauppa_pickup_points"]').val(0).prop('checked' ,false);
      }
      wc_pakettikauppa_toggle_pickup_points();
    });
  };
  wc_pakettikauppa_update_pickup_point_wrapper();
});
