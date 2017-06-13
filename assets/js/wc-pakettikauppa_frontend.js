// Dirty flag to prevent pickup point list reload when a pickup point
// is selected (updated_checkout is fired when an item in the list is selected)
var wc_pakettikauppa_postcode_changed = true;

jQuery(document).ready(function($) {
  // For now, the pickup point is selected only based on postcode, but the
  // Pakettikauppa API has other options, too. Therefore we don't just hook
  // to the postcode field change. The flag above is used to detect if pickup
  // point list should be updated or not.
  jQuery( document ).on( 'updated_checkout', function() {
    wc_pakettikauppa_get_pickup_points( jQuery('#shipping_postcode').val() );
  });
  
  jQuery('#shipping_postcode').change(function() {
    wc_pakettikauppa_postcode_changed = true;
  });
});



function wc_pakettikauppa_get_pickup_points ( postcode ) {
  // Only call the API is flag is set to true. Otherwise this function would be
  // run immediately after a poickup point was chosen, returning selection to
  // "no pickup point selected" state.

  if ( wc_pakettikauppa_postcode_changed ) {
    jQuery('#shipping_pakettikauppa_pickup_point_id').prop( "disabled", true );
    jQuery.post(
      ajaxurl, 
      {
        'action': 'pakettikauppa_get_pickup_points',
        'data': jQuery('#shipping_postcode').val()
      }, 
      function(response){
        jQuery('#shipping_pakettikauppa_pickup_point_id').empty();
        jQuery('#shipping_pakettikauppa_pickup_point_id').append('<option value="">- No pickup point selected -</option>');
        var jsonData = jQuery.parseJSON( response );
        for( var i in jsonData ) {
          var p = jsonData[i];
          var value = p.provider + ": " + p.name + " (" + p.street_address + ")";
          var title = p.provider + ": " + p.name + " (" + p.street_address + ")";
          jQuery('#shipping_pakettikauppa_pickup_point_id').append('<option value="' + value + '">' + title + '</option>');
        }
        jQuery('#shipping_pakettikauppa_pickup_point_id').prop( "disabled", false );
          wc_pakettikauppa_postcode_changed = false;
      }
    );  
  }
}
