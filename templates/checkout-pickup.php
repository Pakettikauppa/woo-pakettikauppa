<?php
/****************************************************************
 * WC Pakettikauppa template: Pickup point select field in Checkout page
 *
 * Variables:
 *   (string) $nonce - Value for nonce field
 *   (array) $error - Error message
 *   (array) $pickup - Pickup point field
 *   (array) $custom - Custom pickup point address field
 ***************************************************************/
?>

<tr class="shipping-pickup-point">
  <th><?php esc_attr_e('Pickup point', 'woo-pakettikauppa'); ?></th>
  <td data-title="<?php esc_attr_e('Pickup point', 'woo-pakettikauppa'); ?>">
    <input type="hidden" name="pakettikauppa_nonce" value="<?php echo $nonce; ?>" id="pakettikauppa_pickup_point_update_nonce"/>
    <?php if ( ! empty($error['msg']) ) : ?>
      <p class="error-pickup"><?php echo $error['msg']; ?></p>
      <input type='hidden' name='<?php echo $error['name']; ?>' value='__NULL__'>
    <?php endif; ?>
    <?php if ( $pickup['show'] ) : ?>
      <span><?php esc_html_e('Choose one of pickup points close to the address you entered:', 'woo-pakettikauppa'); ?></span>
      <?php woocommerce_form_field($pickup['field']['name'], $pickup['field']['data'], $pickup['field']['value']); ?>
    <?php endif; ?>
  </td>
</tr>
<?php if ( $custom['show'] ) : ?>
  <tr class="shipping-custom-pickup-point">
    <th><?php echo $custom['title']; ?></th>
    <td data-title="<?php echo $custom['title']; ?>">
      <?php woocommerce_form_field($custom['field']['name'], $custom['field']['data'], $custom['field']['value']); ?>
      <button type="button" onclick="pakettikauppa_custom_pickup_point_change(pakettikauppacustom_pickup_point)" class="btn" id="pakettikauppacustom_pickup_point_btn"><i class="fa fa-search"></i><?php esc_html_e('Search', 'woo-pakettikauppa'); ?></button>
      <?php if ( ! empty($custom['desc']) ) : ?>
        <p><?php echo $custom['desc']; ?></p>
      <?php endif; ?>
    </td>
  </tr>
<?php endif; ?>
