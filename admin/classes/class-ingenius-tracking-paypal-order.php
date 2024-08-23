<?php

if (!class_exists('Ingenius_Tracking_Paypal_Order')) {
    class Ingenius_Tracking_Paypal_Order
    {
        private int $order_id;

        private string $tracking_number;

        private string $carrier_name;

        private array $tracking_items = [];

        private string $payment_method;

        private array $items = [];

        private bool $is_admin_edit_mode = false;

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

        protected const AFTERSHIP_TRACKING_NUMBER_META_NAME = '_aftership_tracking_number';
        protected const AFTERSHIP_TRACKING_ITEMS_META_NAME = '_aftership_tracking_items';
        protected const AFTERSHIP_TRACKING_PROVIDER_META_NAME = '_aftership_tracking_provider_name';
        protected const PAYPAL_ORDER_ID_META_NAME = '_ppcp_paypal_order_id';
        protected const DEFAULT_IMPORT_PROVIDER_NAME = 'la-poste-colissimo';
        protected const PAYPAL_PAYMENT_NAME_SLUG = 'ppcp-gateway';

        public function __construct(int $order_id, string $mode = "edit")
        {
            $this->order_id = $order_id;
            $order = wc_get_order($order_id);
            $this->set_order_datas($order, $mode);

            $this->is_admin_edit_mode = $mode == 'edit';

            if (!$this->check_paypal_payment_method()) {
                return;
            }

            $this->send_tracking_to_paypal();
        }


        /**
         * Initialize the object properties
         *
         */
        private function set_order_datas(WC_Order $order, string $mode): void
        {
            $this->tracking_number = $order->get_meta(self::AFTERSHIP_TRACKING_NUMBER_META_NAME) ?? '';
            $aftership_meta_tracking_items = $order->get_meta(self::AFTERSHIP_TRACKING_ITEMS_META_NAME, true);

            $tracking_items_data = maybe_unserialize($aftership_meta_tracking_items);

            if (is_array($tracking_items_data)) {
                $this->tracking_items = $tracking_items_data;
            }

            if ($mode === 'edit') {
                $this->carrier_name = $order->get_meta(self::AFTERSHIP_TRACKING_PROVIDER_META_NAME) ?? '';
            } else {
                $this->carrier_name = $order->get_meta(self::AFTERSHIP_TRACKING_PROVIDER_META_NAME) ? $order->get_meta(self::AFTERSHIP_TRACKING_PROVIDER_META_NAME) : self::DEFAULT_IMPORT_PROVIDER_NAME;

                if (!empty($this->tracking_items)) {
                    $this->save_aftership_tracking_items($order);
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
         * Check if payment method is Paypal
         *
         */
        private function check_paypal_payment_method(): bool
        {
            if ($this->payment_method !== self::PAYPAL_PAYMENT_NAME_SLUG) {
                return false;
            }

            return true;
        }


        /**
         * Send order informations to Paypal:
         * - Initialize a Paypal instance connection with client ID & Client secret
         * - Check the tracking linked to the order
         * - Update tracking datas or add new tracking by deleting old tracking
         *
         */
        public function send_tracking_to_paypal(): void
        {
            $order = wc_get_order($this->order_id);
            $paypal_connection = $this->initialize_paypal_connection();

            try {
                $paypal_token = $paypal_connection->get_paypal_bearer_token();
                $paypal_token_data = json_decode($paypal_token);

                $tracking_data = $this->prepare_data_to_send($order);

                $order_details_response = $paypal_connection->get_order_details($order->get_meta(self::PAYPAL_ORDER_ID_META_NAME), $paypal_token_data->access_token);

                if ($order_details_response['code'] == 200) {
                    $order_details =  json_decode($order_details_response['response']);
                    $paypal_trackings = $this->get_tracking_data_from_paypal_order($order_details);

                    $this->update_order_paypal_trackings_data($paypal_connection, $order, $paypal_trackings, $tracking_data, $paypal_token_data);
                }
            } catch (RuntimeException $e) {
                error_log($e->getMessage());
            }
        }

        /**
         * Initialize a paypal connection with saved client credentials
         *
         */
        private function initialize_paypal_connection(): PayPalConnection
        {
            $paypal_settings = get_option('woocommerce-ppcp-settings');
            $client_id = isset($paypal_settings['client_id_production'])  ?  $paypal_settings['client_id_production'] : '';
            $client_secret = isset($paypal_settings['client_secret_production']) ?  $paypal_settings['client_secret_production'] : '';

            require_once plugin_dir_path(__FILE__) . 'class-ingenius-tracking-paypal-api-connection.php';
            return new PayPalConnection($client_id, $client_secret);
        }

        /**
         * Prepare the data to send to Paypal API
         *
         */
        private function prepare_data_to_send(WC_Order $order): array
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
         */
        private function get_tracking_data_from_paypal_order(mixed $order_details): mixed
        {
            if (isset($order_details->purchase_units[0]->shipping)) {
                $shipping_data = $order_details->purchase_units[0]->shipping;
                return $shipping_data->trackers;
            }

            return false;
        }

        /**
         * Update tracking datas or add new tracking by deleting old tracking
         *
         */
        private function update_order_paypal_trackings_data(PayPalConnection $paypal_connection, WC_Order $order, mixed $paypal_trackings, $tracking_data, $paypal_token_data): void
        {
            if ($paypal_trackings) {
                $this->cancel_different_paypal_tracking_numbers($paypal_connection, $order, $paypal_trackings, $paypal_token_data);
                $order_tracking = $this->check_paypal_tracking_number_exists($order, $paypal_trackings);

                if (!$order_tracking) {
                    $paypal_connection->add_order_tracking($order->get_meta(self::PAYPAL_ORDER_ID_META_NAME), $tracking_data, $paypal_token_data->access_token);
                    $this->check_carrier_name_for_notification();
                } else {

                    if (empty($this->tracking_number)) {
                        $tracking_data['status'] = 'CANCELLED';
                    }

                    $paypal_connection->update_order_tracking($order->get_transaction_id(), $tracking_data, $paypal_token_data->access_token);
                    $this->check_carrier_name_for_notification();
                }
            } else {
                $paypal_connection->add_order_tracking($order->get_meta(self::PAYPAL_ORDER_ID_META_NAME), $tracking_data, $paypal_token_data->access_token);
                $this->check_carrier_name_for_notification();
            }
        }


        /**
         * Cancel each Paypal tracking if the tracking number is different from the tracking number of order
         *
         */
        private function cancel_different_paypal_tracking_numbers(PayPalConnection $paypal_connection, WC_Order $order, mixed $paypal_trackings, mixed $paypal_token_data): void
        {
            foreach ($paypal_trackings as $tracking) {
                $parts = explode('-', $tracking->id);
                $tracking_number = isset($parts[1]) ? $parts[1] : '';

                if ($tracking_number != $this->tracking_number) {
                    $tracking_data_to_update['status'] = 'CANCELLED';
                    $tracking_data_to_update['tracking_number'] = $tracking_number;

                    $paypal_connection->update_order_tracking($order->get_transaction_id(), $tracking_data_to_update, $paypal_token_data->access_token);
                }
            }
        }

        /**
         * Check if the tracking number linked to the order exists on the Paypal side
         *
         */
        private function check_paypal_tracking_number_exists(WC_Order $order, mixed $paypal_trackings)
        {

            if (!is_array($paypal_trackings)) {
                return;
            }

            $trackers_map = array_map(function ($tracker) {
                return (array) $tracker;
            }, $paypal_trackings);

            $order_tracking = array_search("{$order->get_transaction_id()}-{$this->tracking_number}", array_column($trackers_map, 'id'));

            return $order_tracking;
        }

        /**
         * Check if the carrier name is in CARRIERS_NAME constant array and send a notification e-mail if the carrier name does not exist
         *
         */
        private function check_carrier_name_for_notification(): void
        {
            if (isset(self::CARRIERS_NAME[$this->carrier_name]) && !$this->is_admin_edit_mode) {
                return;
            }

            $this->notify_admin_of_unknown_carrier();
        }


        /**
         * Send an e-mail to admin account if a carrier name is unknown
         *
         */
        private function notify_admin_of_unknown_carrier(): void
        {

            $subject = __('Transporteur inconnu lors de la sauvegarde de la commande', TEXT_DOMAIN);
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
         */
        private function save_aftership_tracking_items(WC_Order $order): void
        {
            $this->tracking_items[0]["tracking_number"] = $this->tracking_number;
            $this->tracking_items[0]["slug"] = $this->carrier_name;
            $this->tracking_items[0]["metrics"]["updated_at"] = current_time('c');

            $order->update_meta_data(self::AFTERSHIP_TRACKING_ITEMS_META_NAME, $this->tracking_items);
            $order->save();
        }
    }
}
