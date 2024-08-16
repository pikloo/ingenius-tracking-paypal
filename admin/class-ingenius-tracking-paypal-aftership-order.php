<?php
defined('ABSPATH') || exit;
if (!class_exists('Ingenius_Tracking_Paypal_Aftership_Order')) {
    class Ingenius_Tracking_Paypal_Aftership_Order
    {

        private $order_id;

        private $tracking_number;

        private $carrier_name;

        public function __construct($order_id)
        {
            $this->order_id = $order_id;
        }

        /**
         * Check if Aftership is enabled
         *
         * @return void
         */
        public static function check_dependencies()
        {
            if (!is_plugin_active('aftership-woocommerce-tracking/aftership-woocommerce-tracking.php')) {
                return;
            }
        }

        /**
         * Retrieve the order's payment method
         *
         * @return string
         */
        public function it_get_payment_method(): string
        {
            $order = wc_get_order($this->order_id);
            return $order->get_payment_method();
        }

        /**
         * Get order's data from the assiocates order meta datas
         * Provides the instantiate object with the tracking number and carrier name
         * @param WC_Order $order
         * @return void
         */
        public function it_register_order_datas(WC_Order $order)
        {
            $tracking_number = $order->get_meta('_aftership_tracking_number');
            $carrier_name = $order->get_meta('_aftership_tracking_provider_name');

            $this->tracking_number = $tracking_number ? $tracking_number : '';
            $this->carrier_name = $carrier_name ? $carrier_name : '';
        }


        public function it_send_to_paypal()
        {
            // Déclencher l'envoi des informations à PayPal
            $order = wc_get_order($this->order_id);

            // Récupération de la transaction
            $transaction_id = $order->get_transaction_id();
            if (!$transaction_id) {
                return;
            }

            // Préparer les données à envoyer à l'API PayPal
            $tracking_data = [
                'tracking_number' => $this->tracking_number,
                'carrier'         => $this->carrier_name,
            ];

            // Faire l'appel à l'API WooCommerce PayPal Payments
            try {
                // Récupérer les configurations de PayPal
        $settings = get_option('woocommerce_paypal_payments_settings', array());
        $client_id = $settings['client_id'];
        $client_secret = $settings['client_secret'];
        $sandbox = $settings['sandbox_enabled'] === 'yes';

        // Créer une instance de l'endpoint de tracking PayPal
        $order_tracking_endpoint = new \WooCommerce\PayPalCommerce\Endpoint\OrderTrackingEndpoint($client_id, $client_secret, $sandbox);

        // Envoyer les informations de tracking à PayPal
        $response = $order_tracking_endpoint->create($transaction_id, $tracking_data);


                // Vérifier la réponse de l'API
                if (is_wp_error($response)) {
                    // Gérer l'erreur (par exemple, en enregistrant un log)
                    error_log('Erreur lors de l\'envoi des données de tracking à PayPal : ' . $response->get_error_message());
                } else {
                    // Mise à jour réussie, vous pouvez faire quelque chose ici si nécessaire
                    error_log('Les informations de suivi ont été envoyées à PayPal avec succès pour la commande ' . $this->order_id);
                }
            } catch (Exception $e) {
                // Gérer les exceptions
                error_log('Exception lors de l\'envoi des informations de tracking à PayPal : ' . $e->getMessage());
            }
        }
    }
}
