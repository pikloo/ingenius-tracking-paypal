<?php //phpcs:ignore

if (!class_exists('Ingenius_Tracking_Paypal_Order')) {
    /**
     * Manages PayPal order tracking for Ingenius Tracking Paypal.
     */
    class Ingenius_Tracking_Paypal_Order
    {

        /**
         * The ID of the WooCommerce order.
         *
         * @var int
         */
        private int $order_id;

        /**
         * The AfterShip tracking number of the WooCommerce order.
         *
         * @var string
         */
        private string $tracking_number;

        /**
         * The AfterShip carrier name of the WooCommerce order.
         *
         * @var string
         */
        private string $carrier_name;

        /**
         * An array of the Aftership tracking items metadata.
         *
         * @var array
         */
        private array $tracking_items = array();

        /**
         * The payment method of the WooCommerce order.
         *
         * @var string
         */
        private string $payment_method;

        /**
         * The items linked to a WooCommerce order.
         *
         * @var array
         */
        private array $items = array();

        /**
         * Indicates whether the order is being edited in admin mode.
         *
         * @var bool
         */
        private bool $is_admin_edit_mode = false;

        protected const ORDER_STATUS = array(
            'shipped' => 'SHIPPED',
            'on-hold' => 'ON_HOLD',
            'delivered' => 'DELIVERED',
            'cancelled' => 'CANCELLED',
        );

        protected const CARRIERS_NAME = array(
            '4px' => 'FOUR_PX_EXPRESS',
            'china-post' => 'CN_CHINA_POST_EMS',
            'colis-prive' => 'COLIS_PRIVE',
            'dhl' => 'DHL_API',
            'la-poste-colissimo' => 'FR_COLIS',
            'yunexpress' => 'YUNEXPRESS',
            'postnl' => 'POSTNL_INTL_3S',
            'poste-italiane' => 'IT_POSTE_ITALIANE',
            'spain-correos-es' => 'ES_SPAIN_CORREOS_ES',
            'portugal-ctt' => 'PRT_CTT',
            'sweden-posten' => 'SWE_POSTNORD',
        );

        protected const FOREIGN_DEFAULT_CARRIER_NAME = array(
            'de' => 'dhl',
            'se' => 'sweden-posten',
            'nl' => 'postnl',
            'it' => 'poste-italiane',
            'es' => 'spain-correos-es',
            'pt' => 'portugal-ctt',
        );

        protected const AFTERSHIP_TRACKING_NUMBER_META_NAME = '_aftership_tracking_number';
        protected const SEND_TO_AFTERSHIP_TRACKING_NUMBER_META_NAME = 'aftership_tracking_number';
        protected const FBALI_AFTERSHIP_TRACKING_NUMBER_META_NAME = 'tracking_number';
        protected const AFTERSHIP_TRACKING_ITEMS_META_NAME = '_aftership_tracking_items';
        protected const AFTERSHIP_TRACKING_PROVIDER_META_NAME = '_aftership_tracking_provider_name';
        protected const SEND_TO_AFTERSHIP_TRACKING_PROVIDER_META_NAME = 'aftership_carrier';
        protected const FBALI_AFTERSHIP_TRACKING_PROVIDER_META_NAME = 'carrier';
        protected const PAYPAL_ORDER_ID_META_NAME = '_ppcp_paypal_order_id';
        protected const DEFAULT_IMPORT_PROVIDER_NAME = 'la-poste-colissimo';
        protected const PAYPAL_PAYMENT_NAME_SLUG = array(
            'ppcp-gateway',
            'ppcp-credit-card-gateway',
            'ppcp-pay-upon-invoice',
            'ppcp-paylater',
            'ppcp-applepay',
            'ppcp-googlepay'
        );



        /**
         * Initializes the order object and sets up the tracking for PayPal.
         *
         * @param int    $order_id The id of current WooCommerce order.
         * @param string $mode The edition mode of the order.
         */
        public function __construct(int $order_id, string $mode = 'edit')
        {
            $this->order_id = $order_id;
            $order = wc_get_order($order_id);
            $this->set_order_datas($order, $mode);

            $this->is_admin_edit_mode = 'edit' === $mode;

            if (!$this->check_paypal_payment_method()) {
                error_log("[PAYPAL_TRACKING] Payment method is not PayPal for order $order_id");
                return;
            }


            $this->send_tracking_to_paypal();
        }


        /**
         * Initialize the object properties
         *
         * @param WC_Order $order The current WooCommerce order.
         * @param string   $mode The edition mode of the order.
         */
        private function set_order_datas(WC_Order $order, string $mode): void
        {
            $tracking_number = $order->get_meta(self::AFTERSHIP_TRACKING_NUMBER_META_NAME);
            $send_to_aftership_tracking_number = $order->get_meta(self::SEND_TO_AFTERSHIP_TRACKING_NUMBER_META_NAME);
            $fbali_tracking_number = $order->get_meta(self::FBALI_AFTERSHIP_TRACKING_NUMBER_META_NAME);

            if (empty($tracking_number)) {
                $tracking_number = $send_to_aftership_tracking_number ?: $fbali_tracking_number;
            } elseif (!empty($send_to_aftership_tracking_number)) {
                $tracking_number = $send_to_aftership_tracking_number;
            }

            $this->tracking_number = $tracking_number ?: '';

            $aftership_meta_tracking_items = $order->get_meta(self::AFTERSHIP_TRACKING_ITEMS_META_NAME, true);

            $tracking_items_data = maybe_unserialize($aftership_meta_tracking_items);

            if (is_array($tracking_items_data)) {
                $this->tracking_items = $tracking_items_data;
            }

            if ('edit' === $mode) {
                $carrier_name = $order->get_meta(self::AFTERSHIP_TRACKING_PROVIDER_META_NAME);
                $send_to_aftership_carrier_name = $order->get_meta(self::SEND_TO_AFTERSHIP_TRACKING_PROVIDER_META_NAME);
                if (empty($carrier_name) || !empty($send_to_aftership_carrier_name)) {
                    $carrier_name = $send_to_aftership_carrier_name;
                }

                $this->carrier_name = $carrier_name ?: '';
            } else {

                $site_language_code = explode('_', get_locale())[0];

                $this->carrier_name = $order->get_meta(self::AFTERSHIP_TRACKING_PROVIDER_META_NAME);

                if (empty($this->carrier_name)) {
                    $this->carrier_name = $order->get_meta(self::SEND_TO_AFTERSHIP_TRACKING_PROVIDER_META_NAME);
                }

                if (empty($this->carrier_name)) {
                    $this->carrier_name = $order->get_meta(self::FBALI_AFTERSHIP_TRACKING_PROVIDER_META_NAME);
                }

                if (empty($this->carrier_name)) {
                    $this->carrier_name = ($site_language_code === 'fr')
                        ? self::DEFAULT_IMPORT_PROVIDER_NAME
                        : (self::FOREIGN_DEFAULT_CARRIER_NAME[$site_language_code]);
                }


                // if (! empty($this->tracking_items)) {
                $this->save_aftership_tracking_items($order);
                // }
            }

            $this->payment_method = $order->get_payment_method();

            foreach ($order->get_items() as $item_id => $item) {
                $this->items[] = array(
                    'item_id' => $item_id,
                    'quantity' => $item->get_quantity(),
                    'name' => $item->get_name(),
                );
            }
        }

        /**
         * Check if payment method is Paypal
         */
        private function check_paypal_payment_method(): bool
        {
            return strpos($this->payment_method, 'ppcp') === 0;

            // if (!in_array($this->payment_method, self::PAYPAL_PAYMENT_NAME_SLUG)) {
            //     return false;
            // }

            // return true;
        }


        /**
         * Send order informations to Paypal:
         * - Initialize a Paypal instance connection with client ID & Client secret
         * - Check the tracking linked to the order
         * - Update tracking datas or add new tracking by deleting old tracking
         */
        public function send_tracking_to_paypal(): void
        {
            $order = wc_get_order($this->order_id);
            $paypal_connection = $this->initialize_paypal_connection();

            try {
                $paypal_token = $paypal_connection->get_paypal_bearer_token();
                $paypal_token_data = json_decode($paypal_token);

                if (empty($paypal_token_data->access_token)) {
                    return;
                }

                // === TRAITEMENT COMMANDE PRINCIPALE ===
                $tracking_data = $this->prepare_data_to_send($order);

                $paypal_order_id = $order->get_meta(self::PAYPAL_ORDER_ID_META_NAME);
                error_log("[PAYPAL_TRACKING] PayPal order ID (main order): $paypal_order_id");

                $order_details_response = $paypal_connection->get_order_details($paypal_order_id, $paypal_token_data->access_token);

                if (200 === $order_details_response['code']) {
                    error_log("[PAYPAL_TRACKING] PayPal order details fetched successfully for order {$this->order_id}");
                    $order_details = json_decode($order_details_response['response']);
                    $paypal_trackings = $this->get_tracking_data_from_paypal_order($order_details);

                    $this->update_order_paypal_trackings_data(
                        $paypal_connection,
                        $order,
                        $paypal_trackings,
                        $tracking_data,
                        $paypal_token_data
                    );
                } else {
                    error_log("[PAYPAL_TRACKING] Failed to fetch PayPal order details (main order). Response code: {$order_details_response['code']} - Order: {$this->order_id}");
                }

                // === TRAITEMENT UPSELLS ===
                $upsell_capture_id = $order->get_meta('wfocu_ppcp_order_current');

                if (!empty($upsell_capture_id)) {
                    error_log("[PAYPAL_TRACKING] Upsell capture ID found: $upsell_capture_id");


                    // On récupère les détails du capture pour trouver l'order_id lié
                    $capture_details = $paypal_connection->get_order_details($upsell_capture_id, $paypal_token_data->access_token);
                    $upsell_txn_id = null;

                    if ($capture_details['code'] === 200) {

                        $capture_data = json_decode($capture_details['response'], true);

                        if (!empty($capture_data['purchase_units'][0]['payments']['captures'][0]['id'])) {
                            $upsell_txn_id = $capture_data['purchase_units'][0]['payments']['captures'][0]['id'];
                            error_log("[PAYPAL_TRACKING] Extracted upsell capture ID from order details: {$upsell_txn_id}");
                        }

                        $upsell_tracking_data = $this->prepare_data_to_send($order);
                        $upsell_tracking_data['capture_id'] = $upsell_txn_id;

                        error_log("[PAYPAL_TRACKING] Sending tracking for  capture $upsell_capture_id");
                        error_log("[PAYPAL_TRACKING] Tracking payload (upsell): " . print_r($upsell_tracking_data, true));

                        $response = $this->update_order_paypal_trackings_data(
                            $paypal_connection,
                            $order,
                            false, // pas besoin des trackings du parent
                            $upsell_tracking_data,
                            $paypal_token_data,
                            $upsell_capture_id,
                            $upsell_txn_id
                        );

                    } else {
                        error_log("[PAYPAL_TRACKING] Failed to fetch capture details for upsell capture $upsell_capture_id. Code: {$capture_details['code']}");
                    }
                } else {
                    error_log("[PAYPAL_TRACKING] No upsell capture ID found for order {$this->order_id}");
                }

            } catch (RuntimeException $e) {
                error_log("[PAYPAL_TRACKING] Exception for order {$this->order_id}: " . $e->getMessage());
            }
        }


        /**
         * Initialize a paypal connection with saved client credentials
         */
        private function initialize_paypal_connection(): PayPalConnection
        {
            $paypal_settings = get_option('woocommerce-ppcp-settings');
            $client_id = isset($paypal_settings['client_id_production']) ? $paypal_settings['client_id_production'] : '';
            $client_secret = isset($paypal_settings['client_secret_production']) ? $paypal_settings['client_secret_production'] : '';

            require_once plugin_dir_path(__FILE__) . 'class-ingenius-tracking-paypal-api-connection.php';
            return new PayPalConnection($client_id, $client_secret);
        }

        /**
         * Prepare the data to send to Paypal API
         *
         * @param WC_Order $order The current WooCommerce order.
         */
        private function prepare_data_to_send(WC_Order $order): array
        {
            $tracking_data = array(
                'tracking_number' => $this->tracking_number,
                'carrier' => isset(self::CARRIERS_NAME[$this->carrier_name]) ? self::CARRIERS_NAME[$this->carrier_name] : 'OTHER',
                'capture_id' => $order->get_transaction_id(),
                'items' => $this->items,
            );


            // Si le transporteur est 'OTHER', ajouter le nom du transporteur dans la clé 'carrier_name_other'
            // @see https://developer.paypal.com/docs/tracking/reference/carriers/.
            if ('OTHER' === $tracking_data['carrier']) {
                $tracking_data['carrier_name_other'] = $this->carrier_name;
            }

            $tracking_data['status'] = self::ORDER_STATUS[$order->get_status()] ?? 'SHIPPED';

            error_log("[PAYPAL_TRACKING] Data to send: " . print_r($tracking_data, true));


            return $tracking_data;
        }

        /**
         * Retrieve tracking data from PayPal Order details
         *
         * @param mixed $order_details The details of a Paypal order.
         */
        private function get_tracking_data_from_paypal_order(mixed $order_details): mixed
        {
            if (isset($order_details->purchase_units[0]->shipping)) {
                $shipping_data = $order_details->purchase_units[0]->shipping;

                if (isset($shipping_data->trackers))
                    return $shipping_data->trackers;
            }

            return false;
        }

        /**
         * Update tracking datas or add new tracking by deleting old tracking
         *
         * @param PayPalConnection $paypal_connection The Paypal connection initialized.
         * @param WC_Order         $order The current WooCommerce order.
         * @param mixed            $paypal_trackings The trackings from Paypal order.
         * @param array            $tracking_data An array of tracking datas to send to Paypal API.
         * @param mixed            $paypal_token_data The data inside Paypal token.
         */
        private function update_order_paypal_trackings_data(
            PayPalConnection $paypal_connection,
            WC_Order $order,
            mixed $paypal_trackings,
            array $tracking_data,
            $paypal_token_data,
            ?string $override_paypal_order_id = null,
            ?string $override_capture_id = null
        ): void {
            error_log("[PAYPAL_TRACKING] Entered update_order_paypal_trackings_data for order {$this->order_id}");

            if ($paypal_trackings) {
                $txn_for_tracker = $override_capture_id ?: ($tracking_data['capture_id'] ?? '');

                error_log("[PAYPAL_TRACKING] Updating tracking for transaction ID: " . $order->get_transaction_id());

                $response = $paypal_connection->update_order_tracking(
                    $txn_for_tracker,
                    $tracking_data,
                    $paypal_token_data->access_token
                );

                error_log("[PAYPAL_TRACKING] Update tracking response: " . print_r($response, true));

            } else {
                $paypal_order_id = $override_paypal_order_id ?: $order->get_meta(self::PAYPAL_ORDER_ID_META_NAME);
                error_log("[PAYPAL_TRACKING] Adding tracking for PayPal order ID: $paypal_order_id");

                $response = $paypal_connection->add_order_tracking(
                    $paypal_order_id,
                    $tracking_data,
                    $paypal_token_data->access_token
                );

                error_log("[PAYPAL_TRACKING] Add tracking response: " . print_r($response, true));
            }
        }


        /**
         * Cancel each Paypal tracking if the tracking number is different from the tracking number of order
         *
         * @param PayPalConnection $paypal_connection The Paypal connection initialized.
         * @param WC_Order         $order The current WooCommerce order.
         * @param mixed            $paypal_trackings The trackings from Paypal order.
         * @param mixed            $paypal_token_data The data inside Paypal token.
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
         * @param WC_Order $order The current WooCommerce order.
         * @param mixed    $paypal_trackings The trackings from Paypal order.
         */
        private function check_paypal_tracking_number_exists(WC_Order $order, mixed $paypal_trackings)
        {

            if (!is_array($paypal_trackings)) {
                return;
            }

            $trackers_map = array_map(
                function ($tracker) {
                    return (array) $tracker;
                },
                $paypal_trackings
            );

            $order_tracking = array_search("{$order->get_transaction_id()}-{$this->tracking_number}", array_column($trackers_map, 'id'));

            return $order_tracking;
        }

        /**
         * Check if the carrier name is in CARRIERS_NAME constant array and send a notification e-mail if the carrier name does not exist
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
         */
        private function notify_admin_of_unknown_carrier(): void
        {

            $subject = __('Transporteur inconnu lors de la sauvegarde de la commande', 'ingenius-tracking-paypal');
            $message = sprintf(
                esc_html__('Le transporteur "%1$s" utilisé pour la commande #%2$d n\'est pas reconnu. Veuillez vérifier et mettre à jour les informations si nécessaire.', 'ingenius-tracking-paypal'), //phpcs:ignore
                $this->carrier_name,
                $this->order_id
            );
            wp_mail(ADMIN_EMAIL, $subject, $message);
        }

        /**
         * Updates aftership_tracking_items metadata
         *
         * @param WC_Order $order The current WooCommerce order.
         */
        private function save_aftership_tracking_items(WC_Order $order): void
        {
            $this->tracking_items[0]['tracking_number'] = $this->tracking_number;
            $this->tracking_items[0]['slug'] = $this->carrier_name;
            $this->tracking_items[0]['metrics']['updated_at'] = current_time('c');

            $order->update_meta_data(self::AFTERSHIP_TRACKING_ITEMS_META_NAME, $this->tracking_items);
            $order->save();
        }
    }
}
