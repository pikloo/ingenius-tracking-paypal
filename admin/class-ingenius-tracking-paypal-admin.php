<?php
defined('ABSPATH') || exit;
if (!class_exists('Ingenius_Tracking_Paypal_Admin')) {
	class Ingenius_Tracking_Paypal_Admin
	{

		/**
		 * The ID of this plugin.
		 *
		 * @since    1.0.0
		 * @access   private
		 * @var      string    $plugin_name    The ID of this plugin.
		 */
		private $plugin_name;

		/**
		 * The version of this plugin.
		 *
		 * @since    1.0.0
		 * @access   private
		 * @var      string    $version    The current version of this plugin.
		 */
		private $version;

		/**
		 * Initialize the class and set its properties.
		 *
		 * @since    1.0.0
		 * @param      string    $plugin_name       The name of this plugin.
		 * @param      string    $version    The version of this plugin.
		 */
		public function __construct($plugin_name, $version)
		{

			$this->plugin_name = $plugin_name;
			$this->version = $version;
		}

		/**
		 * Register the stylesheets for the admin area.
		 *
		 * @since    1.0.0
		 */
		public function enqueue_styles()
		{
			wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/ingenius-tracking-paypal-admin.css', array(), $this->version, 'all');
		}

		/**
		 * Register the JavaScript for the admin area.
		 *
		 * @since    1.0.0
		 */
		public function enqueue_scripts()
		{
			wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/ingenius-tracking-paypal-admin.js', array('jquery'), $this->version, false);
		}

		/**
		 * Detects if the order has been manually saved
		 *
		 * @param mixed $order_id
		 * @return void
		 */
		public function it_detect_order_save($order_id, $order)
		{
			if (
				isset($_REQUEST['_wpnonce'])
				&& wp_verify_nonce($_REQUEST['_wpnonce'], "update-order_{$order_id}")
			) {

				//Teste si le hook de modification de commande ont été déclenché lors de l'import
				error_log("Une modification est déclenché pour la commande #{$order_id}"); // A supprimer

				$order = wc_get_order($order_id);
				// Vérifier que la commande a bien le statut 'completed'
				if ($order && $order->get_status() === 'completed') {
					$this->it_woocommerce_aftership_order_paid($order_id);
				}
			}
		}

		/**
		 * Retrieve the information of an order managed by Aftership and paid by Paypal
		 *
		 * @param mixed $order_id
		 * @return void
		 */
		private function it_woocommerce_aftership_order_paid($order_id)
		{
			require_once plugin_dir_path(__FILE__) . 'class-ingenius-tracking-paypal-aftership-order.php';
			Ingenius_Tracking_Paypal_Aftership_Order::check_dependencies();

			$aftership_order = new Ingenius_Tracking_Paypal_Aftership_Order($order_id);
			$aftership_order->it_get_payment_method();
			// Vérifier si le mode de paiement est PayPal et que la transaction n'est pas encore envoyer à paypal
			$is_already_sent_to_paypal = $aftership_order->it_is_send_to_paypal();

			if ($aftership_order->it_get_payment_method() === 'ppcp-gateway' && !$is_already_sent_to_paypal) {
				//Récupérer les données de tracking de la commande
				$order = wc_get_order($order_id);
				$aftership_order->it_register_order_datas($order);

				// Envoyer à Woocommerce paypal payment
				$this->it_send_tracking_to_paypal($aftership_order, $order_id);
			}
		}

		/**
		 * Send tracking datas to paypal
		 *
		 * @param mixed $order_id
		 * @param Ingenius_Tracking_Paypal_Aftership_Order $order
		 * @return void
		 */
		private function it_send_tracking_to_paypal(Ingenius_Tracking_Paypal_Aftership_Order $order, $order_id)
		{
			// Vérifier que l'ID de la commande est valide
			if (!$order_id) {
				return;
			}

			$order->it_send_to_paypal();
		}
	}
}
