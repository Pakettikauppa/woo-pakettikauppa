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
                add_action('handle_bulk_actions-edit-pk_manifest', array( $this, 'manifest_actions' ), 3, 10);
                add_filter('manage_pk_manifest_posts_columns', array( $this, 'set_pk_manifest_columns' ));
                add_action('manage_pk_manifest_posts_custom_column', array( $this, 'render_pk_manifest_columns' ), 10, 2);
                add_action('woocommerce_order_actions', array( $this, 'add_manifest_order_action' ));
                add_action('woocommerce_order_action_' . $this->core->prefix . '_add_to_manifest', array( $this, 'add_order_to_manifest' ));
            }
        }

        public function manifest_post_type() {
            $labels = array(
              'name' => __('Manifests', 'woo-pakettikauppa'),
              'singular_name' => __('Manifest', 'woo-pakettikauppa'),
              'all_items' => __('All Manifests', 'woo-pakettikauppa'),
              'add_new_item' => __('Create New Manifest', 'woo-pakettikauppa'),
              'add_new' => __('Create', 'woo-pakettikauppa'),
            );
            register_post_type(
              'pk_manifest',
              array(
                'labels' => $labels,
                'public' => false,
                'has_archive' => false,
                'show_in_menu' => true,
                'show_ui' => true,
                'supports' => false,
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
                $bulk_actions[$this->core->vendor_name][str_replace('wc_', '', $this->core->prefix) . '_add_to_manifest'] = __('Add to manifest', 'woo-pakettikauppa');
            } else {
                $bulk_actions[str_replace('wc_', '', $this->core->prefix) . '_add_to_manifest'] = $this->core->vendor_name . ': ' . __('Add to manifest', 'woo-pakettikauppa');
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
            }
            wp_redirect(site_url($redirect_to));
            exit;
            //return $redirect_to;
        }

        public function add_manifest_order_action( $actions ) {
            global $theorder;

            if ( $theorder->has_status('completed') ) {
                return $actions;
            }

            // add "mark printed" custom action
            $actions[ $this->core->prefix . '_add_to_manifest' ] = __('Add to manifest', 'woo-pakettikauppa');
            return $actions;
        }

        public function add_order_to_manifest( $order ) {
            $manifest = $this->get_current_manifest();
            $this->add_orders_to_manifest($manifest, array( $order->id ));
        }

        /**
         * This function exits on success, returns on error
         *
         * @throws Exception
         */
        public function manifest_actions( $redirect_to, $action, $manifest_ids ) {

            if ( $action === 'print_and_close' ) {
                foreach ( $manifest_ids as $manifest_id ) {
                    wp_update_post(
                      array(
                        'ID' => $manifest_id,
                        'post_status' => 'closed',
                      )
                    );
                    //make orders complete
                    $current_orders = get_post_meta($manifest_id, $this->core->prefix . '_manifest_orders', true);
                    foreach ( $current_orders as $order_id ) {
                        $_order = new \WC_Order($order_id);
                        $_order->update_status('completed');
                    }
                }
                //TODO: do print
            }
            //if ( $action === 'print' ) {
                //TODO: do print
            //}
            wp_redirect(site_url($redirect_to));
            exit;
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
                    $success[] = sprintf(__('Order #%s added to manifest', 'woo-pakettikauppa'), $order_id);
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
            $columns['actions'] = __('Actions', 'woo-pakettikauppa');

            return $columns;
        }

        public function render_pk_manifest_columns( $column, $post_id ) {
            $manifest = get_post($post_id);
            switch ( $column ) {
              case 'orders':
                $current_orders = get_post_meta($post_id, $this->core->prefix . '_manifest_orders', true);
                if ( ! empty($current_orders) ) {
                  echo implode(', ', $current_orders);
                } else {
                  echo '-';
                }
                break;
              case 'actions':
                if ( $manifest->post_status == 'open' ) {
                  echo '<input type = "button" class = "button manifest_action" data-action = "print_and_close" value = "' . __('Print and close', 'woo-pakettikauppa') . '"/>';
                } else if ( $manifest->post_status == 'closed' ) {
                  echo '<input type = "button" class = "button manifest_action" data-action = "print" value = "' . __('Print', 'woo-pakettikauppa') . '"/>';
                }
                break;
              case 'status':
                echo get_post_status_object(get_post_status($manifest))->label;
                break;
            }
        }

    }

}
