jQuery(document).ready(function($) {
  $('input[name="wc_pakettikauppa_cod"]').change(function(e) {
    wc_pakettikauppa_toggle_cod();
  });

  function wc_pakettikauppa_toggle_cod() {
    if ($('input[name="wc_pakettikauppa_cod"]').is(':checked')) {
      $('#wc-pakettikauppa-cod-reference-wrapper').slideDown(300);
      $('#wc-pakettikauppa-cod-amount-wrapper').slideDown(300);
    } else {
      $('#wc-pakettikauppa-cod-reference-wrapper').slideUp(300);
      $('#wc-pakettikauppa-cod-amount-wrapper').slideUp(300);
    }
  }
  wc_pakettikauppa_toggle_cod();

  $('input[name="wc_pakettikauppa_pickup_points"]').change(function(e) {
    wc_pakettikauppa_toggle_pickup_points();
  });

  function wc_pakettikauppa_toggle_pickup_points() {
    if ($('input[name="wc_pakettikauppa_pickup_points"]').is(':checked')) {
      $('#wc-pakettikauppa-pickup-points-wrapper').slideDown(300);
    } else {
      $('#wc-pakettikauppa-pickup-points-wrapper').slideUp(300);
    }
  }
  wc_pakettikauppa_toggle_pickup_points();
});
