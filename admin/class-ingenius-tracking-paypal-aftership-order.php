<?php

namespace IngeniusTrackingPaypal\Admin;

use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WP_Error;

defined('ABSPATH') || exit;
if (!class_exists('Ingenius_Tracking_Paypal_Aftership_Order')) {
    class Ingenius_Tracking_Paypal_Aftership_Order
    {
        private $order_id;

        private string $tracking_number;

        private string $carrier_name;

        private string $payment_method;

        private array $items = [];

        protected const ORDER_STATUS = [
            'shipped' => 'SHIPPED',
            'on-hold' => 'ON_HOLD',
            'delivered' => 'DELIVERED',
            'cancelled' => 'CANCELLED'
        ];


        protected const CARRIERS_NAME = [
            '4px' => 'FOUR_PX_EXPRESS',
            'bpost' => 'BE_BPOST',
            'china-ems' => 'CN_CHINA_POST_EMS',
            'china-post' => 'CN_CHINA_POST_EMS',
            'colis-prive' => 'COLIS_PRIVE',
            'dhl-global-mail-asia' => 'DHL_GLOBAL_MAIL_ASIA',
            'dhl-reference' => 'DHL_REFERENCE_API',
            'dpd' => 'DPD',
            'exapaq' => 'FR_EXAPAQ',
            'gls' => 'GLS',
            'la-poste-colissimo' => 'FR_COLIS',
            'postnl-international' => 'POSTNL_INTERNATIONAL',
            'singapore-post' => 'SG_SG_POST',
            'swiss-post' => 'SWISS_POST',
            'tnt-fr' => 'TNT_FR',
            'yanwen' => 'YANWEN',
            'yunexpress' => 'YUNEXPRESS'
        ];

        public function __construct($order_id)
        {
            $this->order_id = $order_id;
            $order = wc_get_order($order_id);
            $this->tracking_number = $order->get_meta('_aftership_tracking_number') ?? '';
            $this->carrier_name = $order->get_meta('_aftership_tracking_provider_name') ??  '';
            $this->payment_method = $order->get_payment_method();
            foreach ($order->get_items() as $item_id => $item) {
                $this->items[] = [
                    'item_id' => $item_id,
                    'quantity' => $item->get_quantity(),
                    'name' => $item->get_name(),
                    // Ajoutez d'autres détails pertinents des items si nécessaire
                ];
            }
        }

        public function it_get_order_datas()
        {
            return [
                'tracking_number' => $this->tracking_number,
                'carrier_name' => $this->carrier_name,
                'payment_method' => $this->payment_method,
                'items' => $this->items
            ];
        }


        public function it_send_tracking_to_paypal()
        {
            // Check if the required classes and methods are available
            if (!class_exists('WooCommerce\PayPalCommerce\OrderTracking\Endpoint\OrderTrackingEndpoint')) {
                return new WP_Error('class_not_found', __('The required class is not available.', 'woocommerce'));
            }

            $order = wc_get_order($this->order_id);

            // Récupérer les credentials API Paypal
            $paypal_settings = get_option('woocommerce-ppcp-settings');
            $client_id = isset($paypal_settings['sandbox_on']) && $paypal_settings['sandbox_on'] ? $paypal_settings['client_id_sandbox'] : $paypal_settings['client_id_production'];
            $client_secret = isset($paypal_settings['sandbox_on']) && $paypal_settings['sandbox_on'] ? $paypal_settings['client_secret_sandbox'] : $paypal_settings['client_secret_production'];

            require_once plugin_dir_path(__FILE__) . 'class-ingenius-tracking-paypal-api-connection.php';
            // Instanciation de la connexion PayPal
            $paypal_connection = new PayPalConnection($client_id, $client_secret);

            try {
                // Obtenir le Bearer Token
                $paypal_token = $paypal_connection->it_get_paypal_bearer_token();
                $paypal_token_data = json_decode($paypal_token);

                $tracking_data = [
                    'tracking_number' => $this->tracking_number,
                    'carrier'         => isset(self::CARRIERS_NAME[$this->carrier_name]) ? self::CARRIERS_NAME[$this->carrier_name] : 'OTHER',
                    'capture_id' => $order->get_transaction_id(),
                    'items' => $this->items,
                ];

                if ($tracking_data['carrier'] === 'OTHER') {
                    $tracking_data['carrier_name_other'] = $this->carrier_name;
                }

                if (isset(self::ORDER_STATUS[$order->get_status()])) {
                    $tracking_data['status'] = self::ORDER_STATUS[$order->get_status()];
                }
                //Voir si un tracking comportant le num de transaction et le tracking number existe
                $order_tracking_response = $paypal_connection->it_get_order_tracking($order->get_transaction_id(), $this->tracking_number, $paypal_token_data->access_token);
                if ($order_tracking_response['code'] >= 400) {
                    $paypal_connection->it_add_order_tracking($order->get_meta('_ppcp_paypal_order_id'), $tracking_data, $paypal_token_data->access_token);
                } else if ($order_tracking_response === 200) {
                    $paypal_connection->it_update_order_tracking($order->get_transaction_id(), $tracking_data, $paypal_token_data->access_token);
                }
            } catch (RuntimeException $e) {
                // Gestion des erreurs
                error_log($e->getMessage());
                echo 'Erreur : ' . $e->getMessage();
            }
        }
    }
}
