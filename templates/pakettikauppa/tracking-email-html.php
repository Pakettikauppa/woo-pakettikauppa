<?php
/****************************************************************
 * WC Pakettikauppa template: Attach tracking to email (HTML)
 *
 * Variables:
 *   (array) $tracking_codes - Tracking code
 *   (bool) $add_pickup_point_to_email - Checks if pickup point info should be added in the email
 ***************************************************************/
?>

<h2><?php esc_attr_e('Tracking', 'woo-pakettikauppa'); ?></h2>

<?php
  foreach ( $tracking_codes as $code ) {
    ?>
    <p>
    <?php
    if ( $add_pickup_point_to_email === 'yes' ) {
      if ( ! empty($code['point']) ) {
        ?>
        <b> 
        <?php esc_attr_e('Pickup point', 'woo-pakettikauppa'); ?>
          </b> 
          <?php echo esc_attr($code['point']); ?>
          <br/>
        <?php
      } else {
        ?>
        <b>
        <?php esc_attr_e('Pickup point', 'woo-pakettikauppa'); ?>
          :</b> —<br/>
        <?php
      }
    }
    /* translators: 1: Shipment tracking link with text "track your order" 2: Shipment tracking code */
    echo sprintf(__('You can %1$s with tracking code %2$s.', 'woo-pakettikauppa'), '<a href="' . esc_url($code['url']) . '">' . __('track your order', 'woo-pakettikauppa') . '</a>', '<b>' . esc_attr($code['code']) . '</b>') . '</p>';
  }
