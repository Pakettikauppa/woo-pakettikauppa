<?php

namespace Woo_Pakettikauppa_Core;

// Prevent direct access to this script
if (!defined('ABSPATH')) {
    exit();
}

if (!class_exists(__NAMESPACE__ . '\Check_Tool')) {

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
        
        private $check_urls = [
            'pakettikauppa' => [
                'http://api.pakettikauppa.fi',
                'http://apitest.pakettikauppa.fi',
                'https://api.pakettikauppa.fi',
                'https://apitest.pakettikauppa.fi',
            ],
            'posti' => [
                'https://oauth2.posti.com/oauth/token',
                'http://nextshipping.posti.fi',
                'https://nextshipping.posti.fi',
            ]
        ];

        public function __construct(Core $plugin) {
            $this->core = $plugin;
            
            $this->shipment = $this->core->shipment;
            
            if (strtolower($this->core->vendor_name) != $this->vendor_type){
                $this->vendor_type = strtolower($this->core->vendor_name);
            }
            
            if (current_user_can('manage_woocommerce')) {
                add_action('admin_menu', array($this, 'admin_page'));
                add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
            }
        }

        public function admin_page() {
            $title = sprintf(__('WC %s check tool', 'woo-pakettikauppa'), $this->core->vendor_name);
            add_submenu_page('tools.php', $title, $title, 'manage_woocommerce', $this->core->prefix . '_check', [$this, 'renderCheckTool'], 10);
        }

        public function enqueue_scripts() {
            wp_enqueue_style($this->core->prefix . '_check_tool', $this->core->dir_url . 'assets/css/check-tool.css', array(), $this->core->version);
        }

        public function renderCheckTool() {
            ?>
            <div class = "pakettikauppa-check-tool-header">
                <h1>
            <?php printf(__('WooCommerce %s check tool', 'woo-pakettikauppa'), $this->core->vendor_name); ?>
                </h1>
            </div>
            <div class = "pakettikauppa-check-tool-content">
                <div class = "pakettikauppa-check-tool-line">
                    <b><?php _e('PHP version', 'woo-pakettikauppa'); ?></b> <?php echo $this->checkPhpVersion(); ?>
                </div>
                <div class = "pakettikauppa-check-tool-line">
                    <b><?php _e('Curl extension', 'woo-pakettikauppa'); ?></b> <?php echo $this->checkCurl(); ?>
                </div>
                <div class = "pakettikauppa-check-tool-line">
                    <b><?php _e('Wordpress version', 'woo-pakettikauppa'); ?></b> <?php echo $this->checkWordpressVersion(); ?>
                </div>
                <div class = "pakettikauppa-check-tool-line">
                    <b><?php _e('WooCommerce version', 'woo-pakettikauppa'); ?></b> <?php echo $this->checkWoocommerceVersion(); ?>
                </div>
                <div class = "pakettikauppa-check-tool-line">
                    <b><?php _e('WooCommerce ' . $this->core->vendor_name . ' version', 'woo-pakettikauppa'); ?></b> <?php echo $this->checkCurrentVersion(); ?>
                </div>
                <?php if (isset($this->check_urls[$this->vendor_type])): ?>
                    <?php foreach ($this->check_urls[$this->vendor_type] as $url): ?>
                        <div class = "pakettikauppa-check-tool-line">
                            <b><?php _e('Checking URL ', 'woo-pakettikauppa'); ?> <?php echo $url;?></b> <?php echo $this->checkUrl($url); ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div class = "pakettikauppa-check-tool-line">
                    <b><?php _e('API login information', 'woo-pakettikauppa'); ?></b> <?php echo $this->checkAPILogin(); ?>
                </div>
                
            </div>
            <?php
        }

        private function checkCurl() {
            if (phpversion('curl')) {
                return $this->renderSuccess(__('Installed', 'woo-pakettikauppa'));
            } else {
                return $this->renderError(__('Not installed', 'woo-pakettikauppa'));
            }
        }

        private function checkPhpVersion() {
            return $this->renderSuccess(phpversion());
        }

        private function checkWordpressVersion() {
            // Include an unmodified $wp_version.
            require ABSPATH . WPINC . '/version.php';
            if ($wp_version < 5.8) {
                return $this->renderWarning($wp_version);
            } else {
                return $this->renderSuccess($wp_version);
            }
        }

        private function checkWoocommerceVersion() {
            if (!class_exists('WooCommerce')) {
                return $this->renderError(__('Not installed', 'woo-pakettikauppa'));
            }
            if (version_compare(WC_VERSION, '3.4', '<')) {
                return $this->renderError(WC_VERSION);
            } else if (version_compare(WC_VERSION, '5.0', '<')) {
                return $this->renderWarning(WC_VERSION);
            } else {
                return $this->renderSuccess(WC_VERSION);
            }
        }

        private function checkCurrentVersion() {
            $plugin_data = get_plugin_data($this->core->dir . '/wc-pakettikauppa.php');
            $plugin_version = $plugin_data['Version'];
            return $this->renderSuccess($plugin_version);
        }

        private function renderSuccess($text) {
            return '<span class = "success">' . $text . '</span>';
        }

        private function renderWarning($text) {
            return '<span class = "warning">' . $text . '</span>';
        }

        private function renderError($text) {
            return '<span class = "error">' . $text . '</span>';
        }

        private function checkUrl($url) {
            if (!phpversion('curl')) {
                $this->renderError(__('Curl not installed', 'woo-pakettikauppa'));
            }
            $curl = curl_init($url);

            curl_setopt($curl, CURLOPT_NOBODY, true);
            curl_setopt($curl, CURLOPT_TIMEOUT, 2);

            $result = curl_exec($curl);

            if ($result !== false) {
                $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

                if ($statusCode == 404) {
                    return $this->renderError(__('Not found', 'woo-pakettikauppa'));
                } else {
                    return $this->renderSuccess(__('OK', 'woo-pakettikauppa'));
                }
            } else {
                return $this->renderError(curl_error($curl));
            }
        }
        
        private function checkAPILogin(){
            $api_check = $this->shipment->check_api_credentials('kk', 'jj');
            if ($api_check['api_good']){
                return $this->renderSuccess($api_check['msg']);
            } else {
                return $this->renderError($api_check['msg']);
            }
        }    

    }

}
