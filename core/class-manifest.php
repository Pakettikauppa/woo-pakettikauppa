<?php

namespace Woo_Pakettikauppa_Core;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit();
}

if ( ! class_exists(__NAMESPACE__ . '\Manifest') ) {

    class Manifest {

        /**
         * @var Core
         */
        private $core = null;

        /**
         * @var Admin
         */
        private $admin = null;

        public function __construct( Core $plugin ) {
            $this->core = $plugin;
            $this->admin = $this->core->admin;

            if ( current_user_can('manage_woocommerce') ) {
                add_action('init', array( $this, 'manifest_post_type' ));
                add_action('init', array( $this, 'manifest_post_types_statuses' ));
                add_filter('views_edit-pk_manifest', array( $this, 'manifest_post_view' ));
                add_filter('bulk_actions-edit-shop_order', array( $this, 'register_multi_manifest_orders' ), 99);
                add_action('handle_bulk_actions-edit-shop_order', array( $this, 'add_manifest_orders' ), 3, 10);
                add_filter('manage_pk_manifest_posts_columns', array( $this, 'set_pk_manifest_columns' ));
                add_action('manage_pk_manifest_posts_custom_column', array( $this, 'render_pk_manifest_columns' ), 10, 2);
                add_action('woocommerce_order_actions', array( $this, 'add_manifest_order_action' ));
                add_action('woocommerce_order_action_' . $this->core->prefix . '_add_to_manifest', array( $this, 'add_order_to_manifest' ));
                add_action('admin_menu', array( $this, 'add_submenu' ));
                add_action('admin_enqueue_scripts', array( $this, 'manifest_enqueue_scripts' ));
                add_action('wp_ajax_pk_manifest_call_courier', array( $this, 'pk_manifest_call_courier' ));
                add_filter('bulk_actions-edit-pk_manifest', array( $this, 'remove_from_bulk_actions' ));
            }
        }

        public function remove_from_bulk_actions( $actions ) {
            unset($actions[ 'edit' ]);
            unset($actions[ 'trash' ]);
            return $actions;
        }

        public function manifest_enqueue_scripts( $hook ) {
            global $pagenow, $typenow;
            if ( $pagenow == 'edit.php' && $typenow == 'pk_manifest' ) {
                wp_enqueue_script($this->core->prefix . '_datetimepicker_js', $this->core->dir_url . 'assets/js/jquery.datetimepicker.full.min.js', array( 'jquery' ), $this->core->version, true);
                wp_enqueue_style($this->core->prefix . '_datetimepicker', $this->core->dir_url . 'assets/css/jquery.datetimepicker.min.css', array(), $this->core->version);
                wp_enqueue_script($this->core->prefix . '_manifest_js', $this->core->dir_url . 'assets/js/manifest.js', array( 'jquery' ), $this->core->version, true);
                wp_enqueue_style($this->core->prefix . '_manifest', $this->core->dir_url . 'assets/css/manifest.css', array(), $this->core->version);
            }
        }

        public function add_submenu() {
            add_submenu_page('woocommerce', 'Pickup orders', 'Pickup orders', 'manage_woocommerce', 'edit.php?post_type=pk_manifest');
        }

        public function manifest_post_type() {
            $labels = array(
              'name' => __('Pickup Orders', 'woo-pakettikauppa'),
              'singular_name' => __('Pickup Order', 'woo-pakettikauppa'),
              'all_items' => __('All Pickup Orders', 'woo-pakettikauppa'),
              'add_new_item' => __('Create New Pickup Order', 'woo-pakettikauppa'),
              'add_new' => __('Create', 'woo-pakettikauppa'),
            );
            register_post_type(
              'pk_manifest',
              array(
                'labels' => $labels,
                'public' => false,
                'has_archive' => false,
                'show_in_menu' => false,
                'show_ui' => true,
                'supports' => false,
                'capability_type' => 'post',
                'capabilities' => array(
                  'create_posts' => false,
                ),
                'map_meta_cap' => false,
              )
            );
        }

        public function manifest_post_types_statuses() {
            register_post_status(
              'open',
              array(
                'label' => __('Open ', 'woo-pakettikauppa'),
                'public' => is_admin(),
                /* translators: %s: label_count */
                'label_count' => _n_noop('Open <span class="count">(%s)</span>', 'Open <span class="count">(%s)</span>', 'woo-pakettikauppa'),
                'post_type' => array( 'pk_manifest' ), // Define one or more post types the status can be applied to.
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
                'show_in_metabox_dropdown' => true,
                'show_in_inline_dropdown' => true,
                'dashicon' => 'dashicons-businessman',
              )
            );

            register_post_status(
              'closed',
              array(
                'label' => __('Closed ', 'woo-pakettikauppa'),
                'public' => is_admin(),
                /* translators: %s: label_count */
                'label_count' => _n_noop('Closed <span class="count">(%s)</span>', 'Closed <span class="count">(%s)</span>', 'woo-pakettikauppa'),
                'post_type' => array( 'pk_manifest' ), // Define one or more post types the status can be applied to.
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
                'show_in_metabox_dropdown' => true,
                'show_in_inline_dropdown' => true,
                'dashicon' => 'dashicons-businessman',
              )
            );
        }

        public function manifest_post_view( $views ) {

            $remove_views = array( 'all', 'publish', 'future', 'sticky', 'draft', 'pending' );

            foreach ( (array) $remove_views as $view ) {
                if ( isset($views[$view]) ) {
                    unset($views[$view]);
                }
            }
            return $views;
        }

        /**
         * @param $bulk_actions
         *
         * @return mixed
         */
        public function register_multi_manifest_orders( $bulk_actions ) {
            global $wp_version;

            if ( version_compare($wp_version, '5.6.0', '>=') ) {
                if ( ! isset($bulk_actions[$this->core->vendor_name]) ) {
                    $bulk_actions[$this->core->vendor_name] = array();
                }
                $bulk_actions[$this->core->vendor_name][str_replace('wc_', '', $this->core->prefix) . '_add_to_manifest'] = __('Add to pickup order', 'woo-pakettikauppa');
            } else {
                $bulk_actions[str_replace('wc_', '', $this->core->prefix) . '_add_to_manifest'] = $this->core->vendor_name . ': ' . __('Add to pickup order', 'woo-pakettikauppa');
            }

            return $bulk_actions;
        }

        /**
         * This function exits on success, returns on error
         *
         * @throws Exception
         */
        public function add_manifest_orders( $redirect_to, $action, $order_ids ) {

            if ( $action === str_replace('wc_', '', $this->core->prefix) . '_add_to_manifest' ) {
                $manifest = $this->get_current_manifest();
                $this->add_orders_to_manifest($manifest, $order_ids);
                if ( $redirect_to ) {
                    wp_redirect(site_url($redirect_to));
                    exit;
                }
            }
            return;
            //return $redirect_to;
        }

        public function add_manifest_order_action( $actions ) {
            global $theorder;

            if ( $theorder->has_status('completed') ) {
                return $actions;
            }

            // add "mark printed" custom action
            $actions[ $this->core->prefix . '_add_to_manifest' ] = __('Add to pickup order', 'woo-pakettikauppa');
            return $actions;
        }

        public function add_order_to_manifest( $order ) {
            $manifest = $this->get_current_manifest();
            $this->add_orders_to_manifest($manifest, array( $order->id ));
        }

        private function get_current_manifest() {
            $manifests = get_posts(
              array(
                'numberposts' => 1,
                'post_type' => 'pk_manifest',
                'post_status' => 'open',
              )
            );
            if ( empty($manifests) ) {
                return $this->create_new_manifest();
            }
            return $manifests[0];
        }

        private function create_new_manifest() {
            $manifest_args = array(
              'post_title' => gmdate('Y-m-d H:i:s'),
              'post_content' => '',
              'post_status' => 'open',
              'post_type' => 'pk_manifest',
            );

            $id = wp_insert_post($manifest_args);
            if ( $id ) {
                return get_post($id);
            }
            return false;
        }

        private function add_orders_to_manifest( $manifest, $order_ids ) {
            //TODO: check if selected order is paketikauppa and maybe other conditions
            $current_orders = get_post_meta($manifest->ID, $this->core->prefix . '_manifest_orders', true);
            if ( ! is_array($current_orders) ) {
                $current_orders = array();
            }
            $errors = array();
            $success = array();
            $allowed_orders = array();
            foreach ( $order_ids as $order_id ) {
                $labels = get_post_meta($order_id, '_' . $this->core->prefix . '_labels', true);
                if ( ! empty($labels) ) {
                    $allowed_orders[] = $order_id;
                    /* translators: %s: order_number */
                    $success[] = sprintf(__('Order #%s added to pickup order', 'woo-pakettikauppa'), $order_id);
                } else {
                    /* translators: %s: order_number */
                    $errors[] = sprintf(__('Order #%s does not have shipment labels', 'woo-pakettikauppa'), $order_id);
                }
            }
            $new_orders_ids = array_unique(array_merge($current_orders, $allowed_orders));
            update_post_meta($manifest->ID, $this->core->prefix . '_manifest_orders', $new_orders_ids);
            foreach ( $new_orders_ids as $order_id ) {
                $this->set_order_manifest($order_id, $manifest->ID);
            }
            if ( ! empty($errors) ) {
                $this->admin->add_admin_notice(implode('. ', $errors), 'error');
            }
            if ( ! empty($success) ) {
                $this->admin->add_admin_notice(implode('. ', $success), 'success');
            }
        }

        private function set_order_manifest( $order_id, $manifest_id ) {
            update_post_meta($order_id, $this->core->prefix . '_manifest', $manifest_id);
        }

        public function set_pk_manifest_columns( $columns ) {
            unset($columns['date']);
            $columns['orders'] = __('Orders', 'woo-pakettikauppa');
            $columns['status'] = __('Status', 'woo-pakettikauppa');
            $columns['pickup_time'] = __('Pickup time', 'woo-pakettikauppa');
            $columns['actions'] = __('Actions', 'woo-pakettikauppa');

            return $columns;
        }

        public function render_pk_manifest_columns( $column, $post_id ) {
            $manifest = get_post($post_id);
            switch ( $column ) {
              case 'orders':
                $current_orders = get_post_meta($post_id, $this->core->prefix . '_manifest_orders', true);

                if ( ! empty($current_orders) ) {
                  echo $this->generate_orders_links($current_orders);
                } else {
                  echo '-';
                }
                break;
              case 'actions':
                if ( $manifest->post_status == 'open' ) {
                  $current_orders = get_post_meta($post_id, $this->core->prefix . '_manifest_orders', true);
                  if ( ! empty($current_orders) ) {
                    ?>
                    <?php add_thickbox(); ?>
                    <div id="manifest-id-<?php echo $manifest->ID; ?>" style="display:none;">
                        <p>
                            <?php _e('Select date and time between courier should pickup order', 'woo-pakettikauppa'); ?>
                        <table class = "call-courier-table">
                            <tr>
                                <th><?php _e('Date', 'woo-pakettikauppa'); ?></th>
                                <th><?php _e('Earliest time', 'woo-pakettikauppa'); ?></th>
                                <th><?php _e('Latest time', 'woo-pakettikauppa'); ?></th>
                                <th><?php _e('Additional information', 'woo-pakettikauppa'); ?></th>
                                <th></th>
                            </tr>
                            <tr>
                                <td><input type = "text" value = "" class = "manifest-date"/></td>
                                <td><input type = "text" value = "" class = "manifest-time-from"/></td>
                                <td><input type = "text" value = "" class = "manifest-time-to"/></td>
                                <td><input type = "text" value = "" class = "manifest-additional-info"/></td>
                                <td><input type = "button" class = "button manifest_action" data-id = "<?php echo $manifest->ID; ?>" value = "<?php _e('Submit', 'woo-pakettikauppa'); ?>"/></td>
                            </tr>
                        </table>
                        </p>
                    </div>

                    <a href="#TB_inline?&width=600&height=200&inlineId=manifest-id-<?php echo $manifest->ID; ?>" class = "button thickbox"><?php echo __('Place pickup order', 'woo-pakettikauppa'); ?></a>
                    <?php

                  } else {
                      echo __('No orders assigned', 'woo-pakettikauppa');
                  }
                } else if ( $manifest->post_status == 'closed' ) {
                  echo __('Already placed pickup order', 'woo-pakettikauppa');
                }
                break;
              case 'status':
                echo get_post_status_object(get_post_status($manifest))->label;
                break;
              case 'pickup_time':
                $pickup_time = get_post_meta($post_id, $this->core->prefix . '_manifest_pickup_time', true);
                if ( $pickup_time ) {
                    echo $pickup_time;
                } else {
                    echo '-';
                }
                break;
            }
        }

        public function generate_orders_links( $current_orders ) {
            $html = '';
            foreach ( $current_orders as $order_id ) {
                $html .= '<a href="' . admin_url('post.php?post=' . absint($order_id) . '&action=edit') . '" >#' . $order_id . '&nbsp;';
                $labels = get_post_meta($order_id, '_' . $this->core->prefix . '_labels', true);
                if ( ! empty($labels) ) {
                    foreach ( $labels as $label ) {
                        $html .= $label['tracking_code'] . ' ' ?? '';
                    }
                }
                $html .= '</a> <br>';
            }
            return $html;
        }

        public function pk_manifest_call_courier() {
            try {
                $date = sanitize_text_field($_POST['date']);
                $time_from = sanitize_text_field($_POST['time_from']);
                $time_to = sanitize_text_field($_POST['time_to']);
                $additional_info = sanitize_text_field($_POST['additional_info']);
                $id = sanitize_text_field($_POST['id']);
                $manifest = get_post($id);
                if ( ! $date || ! $time_from || ! $time_to || ! $id ) {
                    echo json_encode(array( 'error' => __('Wrong data requested, please try again', 'woo-pakettikauppa') ));
                    wp_die();
                }
                if ( $manifest && $manifest->post_status == 'open' && $manifest->post_type == 'pk_manifest' ) {
                    $order_ids = get_post_meta($manifest->ID, $this->core->prefix . '_manifest_orders', true);
                    if ( empty($order_ids) ) {
                        echo json_encode(array( 'error' => __('Manifest has no orders assigned', 'woo-pakettikauppa') ));
                    } else {
                        $response = $this->make_call($date, $time_from, $time_to, $manifest, $order_ids, $additional_info);
                        if ( ! isset($response['status']) ) {
                            throw new \Exception(__('Wrong response.', 'woo-pakettikauppa'));
                        }
                        if ( $response['status'] == 200 ) {
                            wp_update_post(
                              array(
                                'ID' => $id,
                                'post_status' => 'closed',
                              )
                            );
                              //make orders complete
                              $current_orders = get_post_meta($id, $this->core->prefix . '_manifest_orders', true);
                              foreach ( $current_orders as $order_id ) {
                                  $_order = new \WC_Order($order_id);
                                  $_order->update_status('completed');
                              }
                            echo json_encode('ok');
                        } else {
                            echo json_encode(array( 'error' => $response['message'] ));
                        }
                    }
                } else {
                    echo json_encode(array( 'error' => __('Manifest not found or incorrect status', 'woo-pakettikauppa') ));
                }
            } catch ( \Exception $e ) {
                echo json_encode(array( 'error' => $e->getMessage() ));
            }
            wp_die();
        }

        private function make_call( $date, $time_from, $time_to, $manifest, $order_ids, $additional_info ) {
            $settings = $this->core->shipment->get_settings();

            $xml = new \SimpleXMLElement('<Postra/>');
            $xml->addAttribute('xmlns', 'http://api.posti.fi/xml/POSTRA/1');

            $header = $xml->addChild('Header');
            $header->addChild('SenderId', $settings['order_pickup_sender_id']);
            $header->addChild('ReceiverId', '003715318644');
            $header->addChild('DocumentDateTime', gmdate('c'));
            $header->addChild('Sequence', floor(microtime(true) * 1000));
            $header->addChild('MessageCode', 'POSTRA');
            $header->addChild('MessageVersion', 1);
            $header->addChild('MessageRelease', 2);
            $header->addChild('MessageAction', 'PICKUP_ORDER');

            $shipments = $xml->addChild('Shipments');
            foreach ( $order_ids as $order_id ) {
                $data = get_post_meta($order_id, '_' . $this->core->prefix . '_labels', true);
                if ( empty($data) ) {
                    continue;
                }
                foreach ( $data as $_data ) {
                    $shipment = $shipments->addChild('Shipment');
                    $shipment->addChild('MessageFunctionCode', 'ORIGINAL');
                    $shipment->addChild('PickupOrderType', 'PICKUP');
                    $shipment->addChild('ShipmentNumber', $_data['tracking_code']);
                    $shipment->addChild('ShipmentDateTime', gmdate('c'));
                    $pickup = $shipment->addChild('PickupDate', $date);
                    $pickup->addAttribute('timeEarliest', gmdate('H:i:sP', strtotime($date . ' ' . $time_from)));
                    $pickup->addAttribute('timeLatest', gmdate('H:i:sP', strtotime($date . ' ' . $time_to)));
                    $instructions = $shipment->addChild('Instructions');
                    $instruction = $instructions->addChild('Instruction', $additional_info);
                    $instruction->addAttribute('type', 'GENERAL');

                    $parties = $shipment->addChild('Parties');

                    $consignor = $parties->addChild('Party');
                    $consignor->addAttribute('role', 'CONSIGNOR');
                    $consignor->addChild('Name1', $settings['sender_name']);
                    $consignor_location = $consignor->addChild('Location');
                    $consignor_location->addChild('Street1', $settings['sender_address']);
                    $consignor_location->addChild('Postcode', $settings['sender_postal_code']);
                    $consignor_location->addChild('City', $settings['sender_city']);
                    $consignor_location->addChild('Country', $settings['sender_country']);

                    $payer = $parties->addChild('Party');
                    $payer->addAttribute('role', 'PAYER');
                    $account1 = $payer->addChild('Account', $settings['order_pickup_customer_id']);
                    $account1->addAttribute('type', 'SAP_CUSTOMER');
                    $account2 = $payer->addChild('Account', $settings['order_pickup_invoice_id']);
                    $account2->addAttribute('type', 'SAP_INVOICE');
                    $payer->addChild('Name1', $settings['sender_name']);

                    $items = $shipment->addChild('GoodsItems');
                    foreach ( $_data['products'] as $_product ) {
                        $item = $items->addChild('GoodsItem');
                        $qty = $item->addChild('PackageQuantity', $_product['qty']);
                        $qty->addAttribute('type', 'CW');
                    }
                }
            }

            $xml_data = $xml->asXML();

            $url = $this->core->order_pickup_url;
            if ( ! $url ) {
                throw new \Exception(__('Order pickup URL not set.', 'woo-pakettikauppa'));
            }
            $transient_name = $this->core->prefix . '_access_token';
            $token = get_transient($transient_name);
            if ( ! $token ) {
                throw new \Exception(__('Token not found. Please check credentials', 'woo-pakettikauppa'));
            }
            return $this->do_post($url, $xml_data, $token);
        }

        /**
        * @param string $url
        * @param string $body
        * @return bool|string
        */
        private function do_post( $url, $body, $token ) {
            $headers = array();
            $headers[] = 'Content-type: text/xml; charset=utf-8';
            $headers[] = 'Authorization: Bearer ' . $token;
            $post_data = $body;

            $options = array(
              CURLOPT_POST            => 1,
              CURLOPT_HEADER          => 0,
              CURLOPT_URL             => $url,
              CURLOPT_FRESH_CONNECT   => 1,
              CURLOPT_RETURNTRANSFER  => 1,
              CURLOPT_FORBID_REUSE    => 1,
              CURLOPT_USERAGENT       => 'pk-client-lib/2.0',
              CURLOPT_TIMEOUT         => 30,
              CURLOPT_HTTPHEADER      => $headers,
              CURLOPT_POSTFIELDS      => $post_data,
            );

            $ch = curl_init();
            curl_setopt_array($ch, $options);
            $this->http_response_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $this->http_error           = curl_errno($ch);
            $response = curl_exec($ch);
            $xml = $this->parse_response_xml($response);
            if ( $xml !== false ) {
                $status = (string) $xml->result;
                $message = (string) $xml->result_message;
                if ( $status == 'FAILURE' ) {
                 return array(
                   'status' => '500',
                   'message' => $status . ' - ' . $message,
                 );
                } else if ( $status == 'SUCCESS' ) {
                  return array( 'status' => '200' );
                } else {
                   return array(
                     'status' => '500',
                     'message' => 'Unknown response status - ' . $status,
                   );
                }
            }
            return json_decode($response, true);
        }

        private function parse_response_xml( $xml_data ) {
            //fix for tests
            $response = str_replace('resultMessage', 'result_message', $xml_data);
            $prev = libxml_use_internal_errors(true);

            $doc = simplexml_load_string($response);
            $errors = libxml_get_errors();

            libxml_clear_errors();
            libxml_use_internal_errors($prev);

            return false !== $doc && empty($errors) ? $doc : false;

        }

        public function order_meta_box( $post ) {
            $order = wc_get_order($post->ID);

            if ( $order === null ) {
              return;
            }

            $manifest_id = get_post_meta($post->ID, $this->core->prefix . '_manifest', true);
            if ( ! $manifest_id ) {
                echo '<h4>' . esc_attr__('No manifest assigned', 'woo-pakettikauppa') . '</h4>';
                return;
            }

            $manifest = get_post($manifest_id);
            if ( ! $manifest ) {
                return;
            }
            ?>
            <h4><?php echo esc_attr__('Manifest ID', 'woo-pakettikauppa'); ?>: <?php echo $manifest_id; ?></h4>
            <h4><?php echo esc_attr__('Manifest status', 'woo-pakettikauppa'); ?>: <?php echo get_post_status($manifest); ?></h4>
            <hr/>
            <h4><?php echo esc_attr__('Assigned orders', 'woo-pakettikauppa'); ?>:</h4>
            <?php
            $current_orders = get_post_meta($manifest_id, $this->core->prefix . '_manifest_orders', true);
            ?>
            <ol style="list-style: circle;">
            <?php
            foreach ( $current_orders as $order_id ) {
                $_order = wc_get_order($order_id);
                if ( $_order !== null ) {
                    $data = get_post_meta($order_id, '_' . $this->core->prefix . '_labels', true);
                    ?>
                    <li>
                        <a href = "<?php echo $_order->get_edit_order_url(); ?>" target = "_blank">#<?php echo $_order->get_id(); ?></a>
                        <?php
                        echo empty($data) ? __('Shipment not ready', 'woo-pakettikauppa') : __('Shipment ready', 'woo-pakettikauppa');
                        ?>
                    </li>
                    <?php
                }
            }
            ?>
            </ol>
            <?php
        }
    }
}
