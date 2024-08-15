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

        /**
         * Return true if the order has been already sent to paypal
         * 
         */
        public function it_is_send_to_paypal()
        {
            $order = wc_get_order($this->order_id);
            $is_already_sent_to_paypal = $order->get_meta('_it_tracking_send_to_paypal');
            return $is_already_sent_to_paypal ? true : false;
        }

        public function it_send_to_paypal()
        {
            // Déclencher l'envoi des informations à PayPal
            //!KO : à tester
            do_action('woocommerce_paypal_add_tracking_information', $this->order_id, $this->tracking_number, $this->carrier_name);
            //Ajout de la méta data de vérification d'envoi à paypal
            $order = wc_get_order($this->order_id);
            $order->update_meta_data('_it_tracking_send_to_paypal', true);
            //!KO : boucle
            // $order->save();

            return $this;
        }
    }
}
