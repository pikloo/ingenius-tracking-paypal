<?php //phpcs:ignore

if (! class_exists('Ingenius_Tracking_Paypal_Admin')) {
    /**
     * Admin class for Ingenius Tracking Paypal plugin.
     *
     * This class handles the admin-related functionality for the Ingenius Tracking Paypal plugin.
     *
     * @package Ingenius_Tracking_Paypal
     */
    class Ingenius_Tracking_Paypal_Admin
    {


        /**
         * The ID of this plugin.
         *
         * @var      string    $plugin_name    The ID of this plugin.
         */
        private $plugin_name;

        /**
         * The version of this plugin.
         *
         * @var      string    $version    The current version of this plugin.
         */
        private $version;


        /**
         * Initialize the class and set its properties.
         *
         * @param      string $plugin_name       The name of this plugin.
         * @param      string $version    The version of this plugin.
         */
        public function __construct($plugin_name, $version)
        {

            $this->plugin_name = $plugin_name;
            $this->version     = $version;
        }

        /**
         * Register the stylesheets for the admin area.
         */
        public function enqueue_styles()
        {
            wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/ingenius-tracking-paypal-admin.css', array(), $this->version, 'all');
        }

        /**
         * Register the JavaScript for the admin area.
         */
        public function enqueue_scripts()
        {
            wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/ingenius-tracking-paypal-admin.js', array('jquery'), $this->version, false);
        }
    }
}
