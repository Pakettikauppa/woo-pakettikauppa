<?php

// Prevent direct access to this script
if (!defined('ABSPATH')) {
    exit();
}

require_once WC_PAKETTIKAUPPA_DIR . 'includes/class-wc-pakettikauppa-shipping-method.php';
require_once WC_PAKETTIKAUPPA_DIR . 'includes/class-wc-pakettikauppa-shipment.php';

/**
 * WC_Pakettikauppa_Admin Class
 *
 * @class WC_Pakettikauppa_Admin
 * @version  1.0.0
 * @since 1.0.0
 * @package  woocommerce-pakettikauppa
 * @author Seravo
 */
class WC_Pakettikauppa_Admin
{
	/**
	 * @var WC_Pakettikauppa_Shipment
	 */
	private $wc_pakettikauppa_shipment = null;
    private $errors = array();

    public function __construct()
    {
        $this->id = 'wc_pakettikauppa_admin';
    }

    public function load()
    {
        add_filter('plugin_action_links_' . WC_PAKETTIKAUPPA_BASENAME, array($this, 'add_settings_link'));
        add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);

        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('add_meta_boxes', array($this, 'register_meta_boxes'));
        add_action('save_post', array($this, 'save_metabox'), 10, 2);
        add_action('admin_post_show_pakettikauppa', array($this, 'show'), 10);
        add_action('woocommerce_email_order_meta', array($this, 'attach_tracking_to_email'), 10, 4);
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'show_pickup_point_in_admin_order_meta'), 10, 1);

        try {
            $this->wc_pakettikauppa_shipment = new WC_Pakettikauppa_Shipment();
            $this->wc_pakettikauppa_shipment->load();

        } catch (Exception $e) {
            $this->add_error($e->getMessage());
            $this->add_error_notice($e->getMessage());
            return;
        }
    }

    /**
     * Add an error with a specified error message.
     *
     * @param string $message A message containing details about the error.
     */
    public function add_error($message)
    {
        if (!empty($message)) {
            array_push($this->errors, $message);
            error_log($message);
        }
    }

    /**
     * Return all errors that have been added via add_error().
     *
     * @return array Errors
     */
    public function get_errors()
    {
        return $this->errors;
    }

    /**
     * Clear all existing errors that have been added via add_error().
     */
    public function clear_errors()
    {
        unset($this->errors);
        $this->errors = array();
    }

    /**
     * Add an admin error notice to wp-admin.
     */
    public function add_error_notice($message)
    {
        if (!empty($message)) {
            $class = 'notice notice-error';
            /* translators: %s: Error message */
            $print_error = wp_sprintf(__('An error occured: %s', 'wc-pakettikauppa'), $message);
            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($print_error));
        }
    }

    /**
     * Show row meta on the plugin screen.
     *
     * @param  mixed $links Plugin Row Meta
     * @param  mixed $file Plugin Base file
     * @return  array
     */
    public static function plugin_row_meta($links, $file)
    {
        if ($file === WC_PAKETTIKAUPPA_BASENAME) {
            $row_meta = array(
                'service' => sprintf('<a href="%1$s" aria-label="%2$s">%3$s</a>',
                    esc_url('https://www.pakettikauppa.fi'),
                    esc_attr__('Visit Pakettikauppa', 'wc-pakettikauppa'),
                    esc_html__('Show site Pakettikauppa', 'wc-pakettikauppa')
                ),
            );
            return array_merge($links, $row_meta);
        }
        return (array)$links;
    }

    /**
     * Register meta boxes for WooCommerce order metapage.
     */
    public function register_meta_boxes()
    {
        foreach (wc_get_order_types('order-meta-boxes') as $type) {
            add_meta_box(
                'wc-pakettikauppa',
                esc_attr__('Pakettikauppa', 'wc-pakettikauppa'),
                array(
                    $this,
                    'meta_box',
                ),
                $type,
                'side',
                'default'
            );
        }
    }

    /**
     * Enqueue admin-specific styles and scripts.
     */
    public function admin_enqueue_scripts()
    {
        wp_enqueue_style('wc_pakettikauppa_admin', plugin_dir_url(__FILE__) . '../assets/css/wc-pakettikauppa-admin.css');
    }

    /**
     * Add settings link to the Pakettikauppa metabox on the plugins page when used with
     * the WordPress hook plugin_action_links_woocommerce-pakettikauppa.
     *
     * @param array $links Already existing links on the plugin metabox
     * @return array The plugin settings page link appended to the already existing links
     */
    public function add_settings_link($links)
    {
        $url = admin_url('admin.php?page=wc-settings&tab=shipping&section=wc_pakettikauppa_shipping_method');
        $link = sprintf('<a href="%1$s">%2$s</a>', $url, esc_attr__('Settings'));

        return array_merge(array($link), $links);
    }

    /**
     * Show the selected pickup point in admin order meta. Use together with the hook
     * woocommerce_admin_order_data_after_shipping_address.
     *
     * @param WC_Order $order The order that is currently being viewed in wp-admin
     */
    public function show_pickup_point_in_admin_order_meta($order)
    {
        echo sprintf('<p class="form-field"><strong>%s:</strong><br>', esc_attr__('Requested pickup point', 'wc-pakettikauppa'));
        if ($order->get_meta('_pakettikauppa_pickup_point')) {
            echo esc_attr($order->get_meta('_pakettikauppa_pickup_point'));
        } else {
            echo esc_attr__('None');
        }
        echo '</p>';
    }

	/**
	 * Meta box for managing shipments.
	 *
	 * @param $post
	 */
    public function meta_box($post)
    {
        $order = wc_get_order($post->ID);

        if (!WC_Pakettikauppa_Shipment::validate_order_shipping_receiver($order)) {
            esc_attr_e('Please add shipping info to the order to manage Pakettikauppa shipments.', 'wc-pakettikauppa');
            return;
        }

        // Get active services from active_shipping_options
        $settings = get_option('woocommerce_WC_Pakettikauppa_Shipping_Method_settings', null);
        $active_shipping_options = json_decode($settings['active_shipping_options'], true);

        // The tracking code will only be available if the shipment label has been generated
        $tracking_code = get_post_meta($post->ID, '_wc_pakettikauppa_tracking_code', true);
        $service_id = get_post_meta($post->ID, '_wc_pakettikauppa_service_id', true);

        if(empty($service_id)) {
	        $shipping_methods = $order->get_shipping_methods();
	        $service_id       = array_pop( $shipping_methods )->get_meta( 'service_code' );

	        if(!empty($service_id)) {
		        update_post_meta( $post->ID, '_wc_pakettikauppa_service_id', $service_id );
	        }
        }

        $pickup_point_id = $order->get_meta('_pakettikauppa_pickup_point_id');
        $status = get_post_meta($post->ID, '_wc_pakettikauppa_shipment_status', true);

        $document_url = admin_url('admin-post.php?post=' . $post->ID . '&action=show_pakettikauppa&sid=' . $tracking_code);
        $tracking_url = WC_Pakettikauppa_Shipment::tracking_url($tracking_code);

        ?>
        <div>
            <?php if (!empty($tracking_code)) : ?>
                <p class="pakettikauppa-shipment">
                    <strong>
                        <?php
                        printf(
                            '%1$s<br>%2$s<br>%3$s',
                            esc_attr($this->wc_pakettikauppa_shipment->service_title($service_id)),
                            esc_attr($tracking_code),
                            esc_attr(WC_Pakettikauppa_Shipment::get_status_text($status))
                        );
                        ?>
                    </strong><br>

                    <a href="<?php echo esc_url($document_url); ?>" target="_blank"
                       class="download"><?php esc_attr_e('Print document', 'wc-pakettikauppa'); ?></a>&nbsp;-&nbsp;

                    <?php if (!empty($tracking_url)) : ?>
                        <a href="<?php echo esc_url($tracking_url); ?>" target="_blank"
                           class="tracking"><?php esc_attr_e('Track', 'wc-pakettikauppa'); ?></a>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <?php if (empty($tracking_code)) : ?>
                <div class="pakettikauppa-services">
                    <fieldset class="pakettikauppa-metabox-fieldset">
                        <h4><?php esc_attr_e('Service', 'wc-pakettikauppa'); ?></h4>
                        <?php foreach ($active_shipping_options as $shipping_code => $shipping_settings) : ?>
                            <?php if ($shipping_settings['active'] === 'yes' && ($service_id == $shipping_code || empty($service_id))) : ?>
                                    <label for="service-<?php echo esc_attr($shipping_code); ?>">
                                    <input type="radio"
                                           name="wc_pakettikauppa_service_id"
                                           value="<?php echo esc_attr($shipping_code); ?>"
                                           id="service-<?php echo esc_attr($shipping_code); ?>"
		                        <?php if ($service_id == $shipping_code || (empty($service_id) && WC_Pakettikauppa_Shipment::get_default_service($post, $order) == $shipping_code)): ?>
                                    checked="checked"
		                        <?php endif; ?>
                                    />
                                    <span><?php echo esc_attr($this->wc_pakettikauppa_shipment->service_title($shipping_code)); ?></span>
                                </label>
                                <br>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <?php if($pickup_point_id): ?>
                            <input type="hidden" name="wc_pakettikauppa_pickup_points" value="1">
                            <input type="hidden" name="wc_pakettikauppa_pickup_point_id" value="<?php echo $pickup_point_id;?>">
                        <?php endif; ?>
                    </fieldset>

                </div>
                <p>
                    <input type="submit" value="<?php esc_attr_e('Create', 'wc-pakettikauppa'); ?>"
                           name="wc_pakettikauppa[create]" class="button"/>
                </p>
            <?php else : ?>
                <p>
                    <input type="submit" value="<?php esc_attr_e('Update Status', 'wc-pakettikauppa'); ?>"
                           name="wc_pakettikauppa[get_status]" class="button"/>
                    <input type="submit" value="<?php esc_attr_e('Delete Shipping Label', 'wc-pakettikauppa'); ?>"
                           name="wc_pakettikauppa[delete_shipping_label]" class="button wc-pakettikauppa-delete-button"/>
                </p>
            <?php endif; ?>
        </div>

        <?php
    }

    /**
     * Save metabox values and fetch the shipping label for the order.
     */
    public function save_metabox($post_id)
    {
        /**
         * Because this function is called everytime something is saved in WooCommerce, then let's check this first
         * so it won't slow down saving other stuff too much.
         */
        if (!isset($_POST['wc_pakettikauppa'])) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (wp_is_post_autosave($post_id)) {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

	    $order = new WC_Order($post_id);

	    $command = key($_POST['wc_pakettikauppa']);

	    switch($command) {
            case 'create':
                $service_id = get_post_meta($order->get_id(), '_wc_pakettikauppa_service_id', true);

                if(empty($service_id)) {
                    if(!empty($_REQUEST['wc_pakettikauppa_service_id'])) {
                        $service_id = $_REQUEST['wc_pakettikauppa_service_id'];
                    }

                    if(empty($service_id)) {
	                    $service_id = WC_Pakettikauppa_Shipment::get_default_service();
                    }

                    update_post_meta( $order->get_id(), '_wc_pakettikauppa_service_id', $service_id );
                }

                $pickup_point_id = $order->get_meta('_pakettikauppa_pickup_point_id');

                if(empty($pickup_point_id) && isset( $_REQUEST['wc_pakettikauppa_pickup_point_id'] ) ) {
                    $pickup_point_id = $_REQUEST['wc_pakettikauppa_pickup_point_id'];

                    if(!empty($pickup_point_id)) {
                        update_post_meta( $order->get_id(), '_pakettikauppa_pickup_point_id', $pickup_point_id );
                    }
                }

                $this->create_shipment($order);
                break;
            case 'get_status':
    	        $this->get_status($order);
    	        break;
            case 'delete_shipping_label':
    	        $this->delete_shipping_label($order);
    	        break;
        }
    }

	/**
	 * @param WC_Order $order
	 */
	private function delete_shipping_label(WC_Order $order) {
		try {
			// Delete old tracking code
			update_post_meta($order->get_id(), '_wc_pakettikauppa_tracking_code', '');

			$order->add_order_note(esc_attr__('Successfully deleted Pakettikauppa shipping label.', 'wc-pakettikauppa'));

		} catch (Exception $e) {
			$this->add_error($e->getMessage());
			add_action('admin_notices', function () {
				/* translators: %s: Error message */
				$this->add_error_notice(wp_sprintf(esc_attr__('An error occured: %s', 'wc-pakettikauppa'), $e->getMessage()));
			});

			$order->add_order_note(
				sprintf(
				/* translators: %s: Error message */
					esc_attr__('Deleting Pakettikauppa shipment failed! Errors: %s', 'wc-pakettikauppa'),
					$e->getMessage()
				)
			);
		}
    }

	/**
	 * @param WC_Order $order
	 */
	private function get_status(WC_Order $order) {
	    try {
		    $status_code = $this->wc_pakettikauppa_shipment->get_shipment_status($order->get_id());
		    update_post_meta($order->get_id(), '_wc_pakettikauppa_shipment_status', $status_code);
	    } catch (Exception $e) {
		    $this->add_error($e->getMessage());
		    add_action('admin_notices', function () {
			    /* translators: %s: Error message */
			    $this->add_error_notice(wp_sprintf(esc_attr__('An error occured: %s', 'wc-pakettikauppa'), $e->getMessage()));
		    });
	    }
    }
	/**
	 * @param WC_Order $order
	 */
	private function create_shipment(WC_Order $order) {

	    // Bail out if the receiver has not been properly configured
	    if (!WC_Pakettikauppa_Shipment::validate_order_shipping_receiver($order)) {
		    add_action('admin_notices', function () {
			    echo '<div class="update-nag notice">' .
			         esc_attr__('The shipping label was not created because the order does not contain valid shipping details.', 'wc-pakettikauppa')
			         . '</div>';
		    });
		    return;
	    }

	    try {
		    $tracking_code = $this->wc_pakettikauppa_shipment->create_shipment($order);
	    } catch (Exception $e) {
		    $this->add_error($e->getMessage());
		    /* translators: %s: Error message */
		    $order->add_order_note(sprintf(esc_attr__('Failed to create Pakettikauppa shipment. Errors: %s', 'wc-pakettikauppa'), $e->getMessage()));
		    add_action('admin_notices', function () {
			    /* translators: %s: Error message */
			    $this->add_error_notice(wp_sprintf(esc_attr__('An error occured: %s', 'wc-pakettikauppa'), $e->getMessage()));
		    });
		    return;
	    }

        if ($tracking_code == null) {
	        $order->add_order_note(esc_attr__('Failed to create Pakettikauppa shipment.', 'wc-pakettikauppa'));
	        add_action('admin_notices', function () {
		        /* translators: %s: Error message */
		        $this->add_error_notice(esc_attr__('Failed to create Pakettikauppa shipment.', 'wc-pakettikauppa'));
	        });
	        return;
        }

        update_post_meta($order->get_id(), '_wc_pakettikauppa_tracking_code', $tracking_code);

        $document_url = admin_url('admin-post.php?post=' . $order->get_id() . '&action=show_pakettikauppa&tracking_code=' . $tracking_code);
        $tracking_url = WC_Pakettikauppa_Shipment::tracking_url($tracking_code);

        // Add order note
        $dl_link = sprintf('<a href="%1$s" target="_blank">%2$s</a>', $document_url, esc_attr__('Print document', 'wc-pakettikauppa'));
        $tracking_link = sprintf('<a href="%1$s" target="_blank">%2$s</a>', $tracking_url, __('Track', 'wc-pakettikauppa'));

        $service_id = get_post_meta($order->get_id(), '_wc_pakettikauppa_service_id', true);

        $order->add_order_note(sprintf(
        /* translators: 1: Shipping service title 2: Shipment tracking code 3: Shipping label URL 4: Shipment tracking URL */
            __('Created Pakettikauppa %1$s shipment.<br>%2$s<br>%1$s - %3$s<br>%4$s', 'wc-pakettikauppa'),
            $this->wc_pakettikauppa_shipment->service_title($service_id),
            $tracking_code,
            $dl_link,
            $tracking_link
        ));

    }

    /**
     * Output shipment label as PDF in browser.
     */
    public function show()
    {
        // Find shipment ID either from GET parameters or from the order
        // data.
        if (empty($_REQUEST['tracking_code'])) {
            esc_attr_e('Shipment tracking code is not defined.', 'wc-pakettikauppa');
            return;
        }

	    $tracking_code = $_REQUEST['tracking_code'];

        $contents = $this->wc_pakettikauppa_shipment->fetch_shipping_label($tracking_code);

        if($contents->{"response.file"}->__toString() == '') {
	        esc_attr_e( 'Cannot find shipment with given shipment number.', 'wc-pakettikauppa' );
	        return;
        }

        // Output
        header('Content-type:application/pdf');
        header("Content-Disposition:inline;filename={$tracking_code}.pdf");
        echo base64_decode($contents->{"response.file"});

        exit();
    }

	/**
	 * Attach tracking URL to email.
	 *
	 * @param $order
	 * @param bool $sent_to_admin
	 * @param bool $plain_text
	 * @param null $email
	 */
    public function attach_tracking_to_email($order, $sent_to_admin = false, $plain_text = false, $email = null)
    {

        $settings = get_option('woocommerce_WC_Pakettikauppa_Shipping_Method_settings', null);
        $add_to_email = $settings['add_tracking_to_email'];

        if (!($add_to_email === 'yes' && isset($email->id) && $email->id === 'customer_completed_order')) {
	        return;
        }

        $tracking_code = get_post_meta($order->get_ID(), '_wc_pakettikauppa_tracking_code', true);
        $tracking_url = WC_Pakettikauppa_Shipment::tracking_url($tracking_code);

        if (empty($tracking_code) || empty($tracking_url)) {
            return;
        }

        if ($plain_text) {
            /* translators: %s: Shipment tracking URL */
            echo sprintf(esc_html__("You can track your order at %1$s.\n\n", 'wc-pakettikauppa'), esc_url($tracking_url));
        } else {
            echo '<h2>' . esc_attr__('Tracking', 'wc-pakettikauppa') . '</h2>';
            /* translators: 1: Shipment tracking URL 2: Shipment tracking code */
            echo '<p>' . sprintf(__('You can <a href="%1$s">track your order</a> with tracking code %2$s.', 'wc-pakettikauppa'), esc_url($tracking_url), esc_attr($tracking_code)) . '</p>';
        }
    }
}
