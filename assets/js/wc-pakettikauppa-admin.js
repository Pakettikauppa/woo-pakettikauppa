jQuery(document).load(function($) {
  $('input[name="wc_pakettikauppa_cod"]').change(function(e) {
    wc_pakettikauppa_toggle_cod();
  });

  function wc_pakettikauppa_toggle_cod() {
    if ($('input[name="wc_pakettikauppa_cod"]').is(':checked')) {
      $('#wc-pakettikauppa-cod-reference-wrapper').show();
      $('#wc-pakettikauppa-cod-amount-wrapper').show();
    } else {
      $('#wc-pakettikauppa-cod-reference-wrapper').hide();
      $('#wc-pakettikauppa-cod-amount-wrapper').hide();
    }
  }
  wc_pakettikauppa_toggle_cod();

  $('input[name="wc_pakettikauppa_pickup_points"]').change(function(e) {
    wc_pakettikauppa_toggle_pickup_points();
  });

  function wc_pakettikauppa_toggle_pickup_points() {
    if ($('input[name="wc_pakettikauppa_pickup_points"]').is(':checked')) {
      $('#wc-pakettikauppa-pickup-points-wrapper').show();
    } else {
      $('#wc-pakettikauppa-pickup-points-wrapper').hide();
    }
  }
  wc_pakettikauppa_toggle_pickup_points();
});
