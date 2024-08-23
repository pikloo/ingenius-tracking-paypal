<?php

namespace IngeniusTrackingPaypal\Includes;

use IngeniusTrackingPaypal\Admin\Ingenius_Tracking_Paypal_Admin;

defined('ABSPATH') || exit;

if (!class_exists('Ingenius_Tracking_Paypal')) {
	class Ingenius_Tracking_Paypal
	{

		/**
		 * The loader that's responsible for maintaining and registering all hooks that power
		 * the plugin.
		 *
		 * @var      Ingenius_Tracking_Paypal_Loader    $loader    Maintains and registers all hooks for the plugin.
		 */
		protected $loader;

		/**
		 * The unique identifier of this plugin.
		 *
		 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
		 */
		protected $plugin_name;

		/**
		 * The current version of the plugin.
		 *
		 * @var      string    $version    The current version of the plugin.
		 */
		protected $version;

		/**
		 * Define the core functionality of the plugin.
		 *
		 * Set the plugin name and the plugin version that can be used throughout the plugin.
		 * Load the dependencies, define the locale, and set the hooks for the admin area and
		 * the public-facing side of the site.
		 *
		 */
		public function __construct()
		{
			if (defined('INGENIUS_TRACKING_PAYPAL_VERSION')) {
				$this->version = INGENIUS_TRACKING_PAYPAL_VERSION;
			} else {
				$this->version = '1.0.0';
			}
			$this->plugin_name = 'ingenius-tracking-paypal';

			$this->load_dependencies();
			$this->set_locale();
			$this->define_admin_hooks();

		}


		/**
		 * Load the required dependencies for this plugin.
		 *
		 * Include the following files that make up the plugin:
		 *
		 * - Ingenius_Tracking_Paypal_Loader. Orchestrates the hooks of the plugin.
		 * - Ingenius_Tracking_Paypal_i18n. Defines internationalization functionality.
		 * - Ingenius_Tracking_Paypal_Admin. Defines all hooks for the admin area.
		 * - Ingenius_Tracking_Paypal_Public. Defines all hooks for the public side of the site.
		 *
		 * Create an instance of the loader which will be used to register the hooks
		 * with WordPress.
		 *
		 */
		private function load_dependencies()
		{

			/**
			 * The class responsible for orchestrating the actions and filters of the
			 * core plugin.
			 */
			require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-ingenius-tracking-paypal-loader.php';

			/**
			 * The class responsible for defining internationalization functionality
			 * of the plugin.
			 */
			require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-ingenius-tracking-paypal-i18n.php';

			/**
			 * The class responsible for defining all actions that occur in the admin area.
			 */
			require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-ingenius-tracking-paypal-admin.php';

			$this->loader = new Ingenius_Tracking_Paypal_Loader();
		}

		/**
		 * Define the locale for this plugin for internationalization.
		 *
		 * Uses the Ingenius_Tracking_Paypal_i18n class in order to set the domain and to register the hook
		 * with WordPress.
		 *
		 * @access   private
		 */
		private function set_locale()
		{

			$plugin_i18n = new Ingenius_Tracking_Paypal_i18n();

			$this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
		}

		/**
		 * Register all of the hooks related to the admin area functionality
		 * of the plugin.
		 *
		 * @access   private
		 */
		private function define_admin_hooks()
		{

			$plugin_admin = new Ingenius_Tracking_Paypal_Admin($this->get_plugin_name(), $this->get_version());

			$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
			$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
			
			$this->loader->add_action('pmxi_saved_post', $plugin_admin, 'it_handle_wp_all_import_order', 5, 1);
			$this->loader->add_action('woocommerce_update_order', $plugin_admin, 'it_handle_order_save', 10);
			
		}

		/**
		 * Run the loader to execute all of the hooks with WordPress.
		 *
		 */
		public function run()
		{
			$this->loader->run();
		}

		/**
		 * The name of the plugin used to uniquely identify it within the context of
		 * WordPress and to define internationalization functionality.
		 *
		 * @return    string    The name of the plugin.
		 */
		public function get_plugin_name()
		{
			return $this->plugin_name;
		}

		/**
		 * The reference to the class that orchestrates the hooks with the plugin.
		 *
		 * @since     1.0.0
		 * @return    Ingenius_Tracking_Paypal_Loader    Orchestrates the hooks of the plugin.
		 */
		public function get_loader()
		{
			return $this->loader;
		}

		/**
		 * Retrieve the version number of the plugin.
		 *
		 * @since     1.0.0
		 * @return    string    The version number of the plugin.
		 */
		public function get_version()
		{
			return $this->version;
		}
	}
}
