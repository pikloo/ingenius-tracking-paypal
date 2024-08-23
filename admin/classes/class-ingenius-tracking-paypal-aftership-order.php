<?php

defined('ABSPATH') || exit;
if (!class_exists('Ingenius_Tracking_Paypal_Aftership_Order')) {
    class Ingenius_Tracking_Paypal_Aftership_Order
    {
        private int $order_id;

        private string $tracking_number;

        private string $carrier_name;

        private array $tracking_items = [];

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

        /**
         * @param int $order_id
         * @param string $mode
         */
        public function __construct($order_id, $mode = "edit")
        {
            $this->order_id = $order_id;
            $order = wc_get_order($order_id);
            $this->it_set_order_datas($order, $mode);

            // Vérification du nom du transporteur et envoi du mail
            if (!isset(self::CARRIERS_NAME[$this->carrier_name])) {
                $this->it_notify_admin_of_unknown_carrier();
            }
        }

        /**
         * Initialize the object properties
         *
         * @param WC_Order $order
         * @param string $mode
         * @return void
         */
        private function it_set_order_datas(WC_Order $order, $mode)
        {
            $this->tracking_number = $order->get_meta('_aftership_tracking_number') ?? '';
            $aftership_meta_tracking_items = $order->get_meta('_aftership_tracking_items', true);
            $tracking_items_data = maybe_unserialize($aftership_meta_tracking_items);
            if (is_array($tracking_items_data)) {
                $this->tracking_items = $tracking_items_data;
            }
            if ($mode === 'edit') {
                $this->carrier_name = $order->get_meta('_aftership_tracking_provider_name') ?? '';
            } else {
                //Mettre la poste colissimo si rien n'est renseigné
                $this->carrier_name = $order->get_meta('_aftership_tracking_provider_name') ? $order->get_meta('_aftership_tracking_provider_name') : 'la-poste-colissimo';
                //Mettre à jour aussi _aftership_tracking_items
                if (!empty($this->tracking_items)) {
                    $this->it_save_aftership_tracking_items($order);
                }
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

        /**
         * Retrieve an array of order datas
         *
         * @return array
         */
        public function it_get_order_datas(): array
        {
            return [
                'tracking_number' => $this->tracking_number,
                'carrier_name' => $this->carrier_name,
                'payment_method' => $this->payment_method,
                'items' => $this->items
            ];
        }

        /**
         * Send order informations to Paypal:
         * - Initialize a Paypal instance connection with client ID & Client secret
         * - Check the tracking linked to the order
         * - Update tracking datas or add new tracking by deleting old tracking
         *
         * @return void
         */
        public function it_send_tracking_to_paypal()
        {
            $order = wc_get_order($this->order_id);
            $paypal_connection = $this->it_initialize_paypal_connection();

            try {
                $paypal_token = $paypal_connection->it_get_paypal_bearer_token();
                $paypal_token_data = json_decode($paypal_token);

                $tracking_data = $this->it_prepare_data_to_send($order);

                $order_details_response = $paypal_connection->it_get_order_details($order->get_meta('_ppcp_paypal_order_id'), $paypal_token_data->access_token);

                // Check si la commande est déja suivi par Paypal tracking
                if ($order_details_response['code'] == 200) {
                    $order_details =  json_decode($order_details_response['response']);
                    $paypal_trackings = $this->it_get_tracking_data_from_paypal_order($order_details);

                    // Récupération des tracking dans les détails de la commande tracké par Paypal
                    if ($paypal_trackings) {
                        $this->it_cancel_different_paypal_tracking_numbers($paypal_connection, $order, $paypal_trackings, $paypal_token_data);
                        // Recherche si le numéro de tracking lié à la commande existe coté Paypal
                        $order_tracking = $this->it_check_paypal_tracking_number_exists($order, $paypal_trackings);
                        // Ajout du tracking Paypal si le numéro de tracking lié à la commande n'existe ou modification dans le cas contraire
                        if (!$order_tracking) {
                            $paypal_connection->it_add_order_tracking($order->get_meta('_ppcp_paypal_order_id'), $tracking_data, $paypal_token_data->access_token);
                        } else {
                            // Annulation du tracking paypal si le tracking number n'est pas renseignée 
                            if (empty($this->tracking_number)) {
                                $tracking_data['status'] = 'CANCELLED';
                            }
                            $paypal_connection->it_update_order_tracking($order->get_transaction_id(), $tracking_data, $paypal_token_data->access_token);
                        }
                    } else {
                        //Ajout du tracking Paypal si n'y a aucun tracking Paypal lié à la commande courante
                        $paypal_connection->it_add_order_tracking($order->get_meta('_ppcp_paypal_order_id'), $tracking_data, $paypal_token_data->access_token);
                    }
                }
            } catch (RuntimeException $e) {
                error_log($e->getMessage());
            }
        }

        /**
         * Initialize a paypal connection with saved client credentials
         *
         * @return PayPalConnection
         */
        private function it_initialize_paypal_connection(): PayPalConnection
        {
            $paypal_settings = get_option('woocommerce-ppcp-settings');
            $client_id = isset($paypal_settings['sandbox_on']) && $paypal_settings['sandbox_on'] ? $paypal_settings['client_id_sandbox'] : $paypal_settings['client_id_production'];
            $client_secret = isset($paypal_settings['sandbox_on']) && $paypal_settings['sandbox_on'] ? $paypal_settings['client_secret_sandbox'] : $paypal_settings['client_secret_production'];

            require_once plugin_dir_path(__FILE__) . 'class-ingenius-tracking-paypal-api-connection.php';
            return new PayPalConnection($client_id, $client_secret);
        }

        /**
         * Prepare the data to send to Paypal API
         *
         * @param WC_Order $order
         * @return array
         */
        private function it_prepare_data_to_send(WC_Order $order): array
        {
            $tracking_data = [
                'tracking_number' => $this->tracking_number,
                'carrier'         => isset(self::CARRIERS_NAME[$this->carrier_name]) ? self::CARRIERS_NAME[$this->carrier_name] : 'OTHER',
                'capture_id' => $order->get_transaction_id(),
                'items' => $this->items,
            ];

            // Si le transporteur est 'OTHER', ajouter le nom du transporteur dans la clé 'carrier_name_other'
            // @see https://developer.paypal.com/docs/tracking/reference/carriers/
            if ($tracking_data['carrier'] === 'OTHER') {
                $tracking_data['carrier_name_other'] = $this->carrier_name;
            }

            if (isset(self::ORDER_STATUS[$order->get_status()])) {
                $tracking_data['status'] = self::ORDER_STATUS[$order->get_status()];
            } else {
                $tracking_data['status'] = 'SHIPPED';
            }

            return $tracking_data;
        }

        /**
         * Retrieve tracking data from PayPal Order details
         *
         * @param [type] $order_details
         * @return mixed
         */
        private function it_get_tracking_data_from_paypal_order($order_details)
        {
            if (isset($order_details->purchase_units[0]->shipping)) {
                $shipping_data = $order_details->purchase_units[0]->shipping;
                return $shipping_data->trackers;
            }

            return false;
        }

        /**
         * Cancel each Paypal tracking if the tracking number is different from the tracking number of order
         *
         * @param PayPalConnection $paypal_connection
         * @param WC_Order $order
         * @param mixed $paypal_trackings
         * @param mixed $paypal_token_data
         * @return void
         */
        private function it_cancel_different_paypal_tracking_numbers(PayPalConnection $paypal_connection, WC_Order $order, $paypal_trackings, $paypal_token_data)
        {
            foreach ($paypal_trackings as $tracking) {
                // Récupération du numéro de tracking dans la clé id (ID de la transaction-numéro de tracking)
                $parts = explode('-', $tracking->id);
                $tracking_number = isset($parts[1]) ? $parts[1] : '';
                //Annulation du tracking si le numéro de tracking est différent du numéro de tracking de la commande enregistrée
                if ($tracking_number != $this->tracking_number) {
                    $tracking_data_to_update['status'] = 'CANCELLED';
                    $tracking_data_to_update['tracking_number'] = $tracking_number;
                    $paypal_connection->it_update_order_tracking($order->get_transaction_id(), $tracking_data_to_update, $paypal_token_data->access_token);
                }
            }
        }

        /**
         * Check if the tracking number linked to the order exists on the Paypal side
         *
         * @param WC_Order $order
         * @param mixed $paypal_trackings
         * @return boolean
         */
        private function it_check_paypal_tracking_number_exists(WC_Order $order, $paypal_trackings)
        {
            $trackers_map = array_map(function ($tracker) {
                return (array) $tracker;
            }, $paypal_trackings);

            $column = array_column($trackers_map, 'id');
            $order_tracking = array_search("{$order->get_transaction_id()}-{$this->tracking_number}", $column);

            return $order_tracking;
        }


        /**
         * Send an e-mail to admin account if a carrier name is unknown
         *
         * @return void
         */
        private function it_notify_admin_of_unknown_carrier()
        {

            $subject = __('Transporteur inconnu lors de l\'import de la commande', TEXT_DOMAIN);
            $message = sprintf(
                __('Le transporteur "%s" utilisé pour la commande #%d n\'est pas reconnu. Veuillez vérifier et mettre à jour les informations si nécessaire.', TEXT_DOMAIN),
                $this->carrier_name,
                $this->order_id
            );
            wp_mail(ADMIN_EMAIL, $subject, $message);
        }

        /**
         * Updates aftership_tracking_items metadata
         *
         * @param WC_Order $order
         * @return void
         */
        private function it_save_aftership_tracking_items(WC_Order $order)
        {
            $this->tracking_items[0]["tracking_number"] = $this->tracking_number;
            $this->tracking_items[0]["slug"] = $this->carrier_name;
            $this->tracking_items[0]["metrics"]["updated_at"] = current_time('c');

            $order->update_meta_data('_aftership_tracking_items', $this->tracking_items);
            $order->save();
        }
    }
}
