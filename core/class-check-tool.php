<?php

namespace Woo_Pakettikauppa_Core;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit();
}

if ( ! class_exists(__NAMESPACE__ . '\Check_Tool') ) {

    class Check_Tool {

        /**
         * @var Core
         */
        private $core = null;

        /**
         * @var Shipment
         */
        private $shipment = null;
        private $vendor_type = 'pakettikauppa';
        private $check_urls = array(
          'pakettikauppa' => array(
            'http://api.pakettikauppa.fi',
            'http://apitest.pakettikauppa.fi',
            'https://api.pakettikauppa.fi',
            'https://apitest.pakettikauppa.fi',
          ),
          'posti' => array(
            'https://oauth2.posti.com/oauth/token',
            'http://nextshipping.posti.fi',
            'https://nextshipping.posti.fi',
          ),
        );

        public function __construct( Core $plugin ) {
            $this->core = $plugin;

            $this->shipment = $this->core->shipment;

            if ( strtolower($this->core->vendor_name) != $this->vendor_type ) {
                $this->vendor_type = strtolower($this->core->vendor_name);
            }

            if ( current_user_can('manage_woocommerce') ) {
                add_action('admin_menu', array( $this, 'admin_page' ));
                add_action('admin_enqueue_scripts', array( $this, 'enqueue_scripts' ));
            }
        }

        public function admin_page() {
            /* translators: %s: vendor_name */
            $title = sprintf(__('WC %s check tool', 'woo-pakettikauppa'), $this->core->vendor_name);
            add_submenu_page('tools.php', $title, $title, 'manage_woocommerce', $this->core->prefix . '_check', array( $this, 'render_check_tool' ), 10);
        }

        public function enqueue_scripts() {
            wp_enqueue_style($this->core->prefix . '_check_tool', $this->core->dir_url . 'assets/css/check-tool.css', array(), $this->core->version);
        }

        public function render_check_tool() {
            ?>
            <div class = "pakettikauppa-check-tool-header">
                <h1>
                <?php
                /* translators: %s: vendor_name */
                printf(__('WooCommerce %s check tool', 'woo-pakettikauppa'), $this->core->vendor_name);
                ?>
                </h1>
            </div>
            <div class = "pakettikauppa-check-tool-content">
                <div class = "pakettikauppa-check-tool-line">
                    <b><?php _e('PHP version', 'woo-pakettikauppa'); ?></b> <?php echo $this->check_php_version(); ?>
                </div>
                <div class = "pakettikauppa-check-tool-line">
                    <b><?php _e('Curl extension', 'woo-pakettikauppa'); ?></b> <?php echo $this->check_curl(); ?>
                </div>
                <div class = "pakettikauppa-check-tool-line">
                    <b><?php _e('Wordpress version', 'woo-pakettikauppa'); ?></b> <?php echo $this->check_wordpress_version(); ?>
                </div>
                <div class = "pakettikauppa-check-tool-line">
                    <b><?php _e('WooCommerce version', 'woo-pakettikauppa'); ?></b> <?php echo $this->check_woocommerce_version(); ?>
                </div>
                <div class = "pakettikauppa-check-tool-line">
                    <b>
                        <?php
                        /* translators: %s: vendor_name */
                        printf(__('WooCommerce %s version', 'woo-pakettikauppa'), $this->core->vendor_name);
                        ?>
                    </b> <?php echo $this->check_current_version(); ?>
                </div>
            <?php if ( isset($this->check_urls[$this->vendor_type]) ) : ?>
                <?php foreach ( $this->check_urls[ $this->vendor_type ] as $url ) : ?>
                        <div class = "pakettikauppa-check-tool-line">
                            <b><?php _e('Checking URL ', 'woo-pakettikauppa'); ?> <?php echo $url; ?></b> <?php echo $this->check_url($url); ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div class = "pakettikauppa-check-tool-line">
                    <b><?php _e('API login information', 'woo-pakettikauppa'); ?></b> <?php echo $this->check_api_login(); ?>
                </div>

            </div>
            <?php
        }

        private function check_curl() {
            if ( phpversion('curl') ) {
                return $this->render_success(__('Installed', 'woo-pakettikauppa'));
            } else {
                return $this->render_error(__('Not installed', 'woo-pakettikauppa'));
            }
        }

        private function check_php_version() {
            return $this->render_success(phpversion());
        }

        private function check_wordpress_version() {
            // Include an unmodified $wp_version.
            include ABSPATH . WPINC . '/version.php';
            if ( $wp_version < 5.8 ) {
                return $this->render_warning($wp_version);
            } else {
                return $this->render_success($wp_version);
            }
        }

        private function check_woocommerce_version() {
            if ( ! class_exists('WooCommerce') ) {
                return $this->render_error(__('Not installed', 'woo-pakettikauppa'));
            }
            if ( version_compare(WC_VERSION, '3.4', '<') ) {
                return $this->render_error(WC_VERSION);
            } else if ( version_compare(WC_VERSION, '5.0', '<') ) {
                return $this->render_warning(WC_VERSION);
            } else {
                return $this->render_success(WC_VERSION);
            }
        }

        private function check_current_version() {
            $plugin_data = get_plugin_data($this->core->dir . '/wc-pakettikauppa.php');
            $plugin_version = $plugin_data['Version'];
            return $this->render_success($plugin_version);
        }

        private function render_success( $text ) {
            return '<span class = "success">' . $text . '</span>';
        }

        private function render_warning( $text ) {
            return '<span class = "warning">' . $text . '</span>';
        }

        private function render_error( $text ) {
            return '<span class = "error">' . $text . '</span>';
        }

        private function check_url( $url ) {
            if ( ! phpversion('curl') ) {
                $this->render_error(__('Curl not installed', 'woo-pakettikauppa'));
            }
            $curl = curl_init($url);

            curl_setopt($curl, CURLOPT_NOBODY, true);
            curl_setopt($curl, CURLOPT_TIMEOUT, 2);

            $result = curl_exec($curl);

            if ( $result !== false ) {
                $status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

                if ( $status_code == 404 ) {
                    return $this->render_error(__('Not found', 'woo-pakettikauppa'));
                } else {
                    return $this->render_success(__('OK', 'woo-pakettikauppa'));
                }
            } else {
                return $this->render_error(curl_error($curl));
            }
        }

        private function check_api_login() {
            $api_check = $this->shipment->check_api_credentials('kk', 'jj');
            if ( $api_check['api_good'] ) {
                return $this->render_success($api_check['msg']);
            } else {
                return $this->render_error($api_check['msg']);
            }
        }

    }

}
