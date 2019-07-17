// phpcs:disable PEAR.Functions.FunctionCallSignature
/**
 * Add frontend scripts to this file.
 */

jQuery('#billing_postcode').bind('blur', function() {
  trigger('update_checkout');
});
