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
            'cancelled' => 'CANCELLED',
        ];


        protected const CARRIERS_NAME = [
            '4px' => 'FOUR_PX_EXPRESS',
            'china-post' => 'CN_CHINA_POST_EMS',
            'colis-prive' => 'COLIS_PRIVE',
            'dhl' => 'DHL_API',
            'la-poste-colissimo' => 'FR_COLIS',
            'yunexpress' => 'YUNEXPRESS'
        ];

        public function __construct($order_id, $mode = "edit")
        {
            $this->order_id = $order_id;
            $order = wc_get_order($order_id);
            $this->tracking_number = $order->get_meta('_aftership_tracking_number') ?? '';
            if ($mode === 'edit') {
                $this->carrier_name = $order->get_meta('_aftership_tracking_provider_name') ?? '';
            } else {
                $this->carrier_name = $order->get_meta('_aftership_tracking_provider_name') ? $order->get_meta('_aftership_tracking_provider_name') : 'la-poste-colissimo';
            }
            $this->payment_method = $order->get_payment_method();
            foreach ($order->get_items() as $item_id => $item) {
                $this->items[] = [
                    'item_id' => $item_id,
                    'quantity' => $item->get_quantity(),
                    'name' => $item->get_name(),
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
                $order_tracking_response = $paypal_connection->it_get_order_tracking($order->get_meta('_ppcp_paypal_order_id'), $paypal_token_data->access_token);

                // // if($order_tracking_response['response']['tracking_number'] != $this->tracking_number) {
                if ($order_tracking_response['code'] == 200) {
                    $order_details =  json_decode($order_tracking_response['response']);
                    $trackers = $order_details->purchase_units[0]->shipping->trackers;

                    foreach ($trackers as $tracker) {
                        $parts = explode('-', $tracker->id);
                        $status = $tracker->status;
                        $tracking_number = isset($parts[1]) ? $parts[1] : '';
                        if ($tracking_number !== $this->tracking_number && $status !== 'SHIPPED') {
                            $tracking_data_to_update['status'] = 'CANCELLED';
                            $tracking_data_to_update['tracking_number'] = $tracking_number;
                            $paypal_connection->it_update_order_tracking($order->get_transaction_id(), $tracking_data_to_update, $paypal_token_data->access_token);
                        }
                    }

                    $trackers_map = array_map(function ($tracker) {
                        return (array) $tracker;
                    }, $trackers);

                    $column = array_column($trackers_map, 'id');
                    $index = array_search("{$order->get_transaction_id()}-{$this->tracking_number}", $column);

                    if (!$index) {
                        $paypal_connection->it_add_order_tracking($order->get_meta('_ppcp_paypal_order_id'), $tracking_data, $paypal_token_data->access_token);
                    } else {
                        error_log($order->get_status());
                        $tracking_data['status'] = 'SHIPPED';
                        $paypal_connection->it_update_order_tracking($order->get_transaction_id(), $tracking_data, $paypal_token_data->access_token);
                    }
                }
            } catch (RuntimeException $e) {
                error_log($e->getMessage());
                echo 'Erreur : ' . $e->getMessage();
            }
        }
    }
}
