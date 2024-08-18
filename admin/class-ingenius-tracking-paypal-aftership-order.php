<?php

namespace IngeniusTrackingPaypal\Admin;

use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\OrderTracking\Endpoint\OrderTrackingEndpoint;
use WooCommerce\PayPalCommerce\OrderTracking\Shipment\ShipmentFactory;
use WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;
use WooCommerce\WooCommerce\Logging\Logger\NullLogger;
use WP_Error;

defined('ABSPATH') || exit;
if (!class_exists('Ingenius_Tracking_Paypal_Aftership_Order')) {
    class Ingenius_Tracking_Paypal_Aftership_Order
    {

        use TransactionIdHandlingTrait;

        private $order_id;

        protected string $tracking_number;

        protected string $carrier_name;

        private array $items;

        protected const ORDER_STATUS = [
            'shipped' => 'SHIPPED',
            'on-hold' => 'ON_HOLD',
            'delivered' => 'DELIVERED',
            'cancelled' => 'CANCELLED'
        ];

        public function __construct($order_id)
        {
            $this->order_id = $order_id;
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
            $this->tracking_number = $order->get_meta('_aftership_tracking_number') ?? '';
            $this->carrier_name = $order->get_meta('_aftership_tracking_provider_name') ??  '';
            foreach ($order->get_items() as $item_id => $item) {
                $this->items[] = [
                    'item_id' => $item_id,
                    'quantity' => $item->get_quantity(),
                    'name' => $item->get_name(),
                    // Ajoutez d'autres détails pertinents des items si nécessaire
                ];
            }
        }


        public function it_send_tracking_to_paypal()
        {
            // Check if the required classes and methods are available
            if (!class_exists('WooCommerce\PayPalCommerce\OrderTracking\Endpoint\OrderTrackingEndpoint')) {
                return new WP_Error('class_not_found', __('The required class is not available.', 'woocommerce'));
            }

            $order = wc_get_order($this->order_id);

            //TODO: Récupérer les API credentials créé dans Paypal
            // Récupérer les identifiants Paypal
            $client_id = 'AWAoPKd2jCxCIVLsDgC-TNU1jd0H9XG2ELkmqmLdmrj4Oo7FnHXWuPtoo4-R7ra4t5oc1itAG-G6w0_w';
            $client_secret = 'EFMWwGwyjZZ2fNJs7ov7HPUavhEoImt2pZJEnJlxx7hEv2YUvxi3tVcX4nYZ7y32rMVqkHnrbPUKssq9';

            // require_once plugin_dir_path(__FILE__) . 'class-ingenius-tracking-paypal-api-connection.php';
            // Instanciation de la connexion PayPal
            $paypal_connection = new PayPalConnection($client_id, $client_secret);

            //TODO: Si le numéro de tracking et le carrier est changé faire l'update
            error_log('avant connexion');
            try {
                // Obtenir le Bearer Token
                $paypal_token = $paypal_connection->it_get_paypal_bearer_token();
                $paypal_token_data = json_decode($paypal_token);

                //Créer un objet json pour le token
                $token_json = (object)[
                    'token' => $paypal_token_data->access_token, // Remplacez par le token réel
                    'token_type' => $paypal_token_data->token_type,
                    'expires_in' => $paypal_token_data->expires_in,  // Durée de vie du token en secondes
                    'scope' => $paypal_token_data->scope,
                    'created' => time()  // Temps de création du token
                ];

                // require_once plugin_dir_path(__FILE__) . 'class-ingenius-tracking-paypal-bearer.php';

                $bearer = new BearerToken($token_json);

                $shipment_factory = new ShipmentFactory(); // Exemple, cela peut nécessiter plus de configuration
                $allowed_statuses = ['SHIPPED', 'ON_HOLD', 'DELIVERED', 'CANCELLED'];
                $should_use_new_api = true;
                //TODO: détecter si sandbox ou live
                $host = 'https://api.sandbox.paypal.com'; // URL de l'API PayPal

                $order_tracking_endpoint = new OrderTrackingEndpoint(
                    $host,
                    $bearer,
                    new NullLogger(), // Logger de base, vous pouvez utiliser un logger personnalisé,
                    new RequestData(), // Exemple, cela peut nécessiter plus de configuration,
                    $shipment_factory,
                    $allowed_statuses,
                    $should_use_new_api
                );


                // Préparer les données à envoyer à l'API PayPal
                $tracking_data = [
                    'tracking_number' => $this->tracking_number,
                    'carrier'         => 'DPD', //$this->carrier_name,
                    'carrier_name_other' => '',
                    'capture_id' => $order->get_transaction_id(),
                    'status' => self::ORDER_STATUS[$order->get_status()],
                    'items' => $this->items,
                ];

                // Création d'une instance de Shipment
                $shipment = $shipment_factory->create_shipment(
                    $this->order_id,
                    $tracking_data['capture_id'],
                    $tracking_data['tracking_number'],
                    $tracking_data['status'],
                    $tracking_data['carrier'],
                    $tracking_data['carrier_name_other'],
                    $tracking_data['items']
                );

                error_log("Commande #{$this->order_id} capture_id #{$tracking_data['capture_id']}  status #{$tracking_data['status']}");

                // Appel à add_tracking_information ou update_tracking_information
                try {

                    // $data = $order_tracking_endpoint->get_tracking_information($this->order_id, $this->tracking_number);
                    $order_tracking_endpoint->add_tracking_information($shipment, $this->order_id);
                    error_log('Tracking information added successfully!');
                    echo 'Tracking information added successfully!';
                } catch (RuntimeException $e) {
                    error_log($e->getMessage());
                    echo 'Error adding tracking information: ' . $e->getMessage();
                }

                // Utiliser le Bearer Token comme requis par votre application
                // Par exemple, en passant le token à une autre classe ou en le stockant pour une utilisation ultérieure
            } catch (RuntimeException $e) {
                // Gestion des erreurs
                error_log($e->getMessage());
                echo 'Erreur : ' . $e->getMessage();
            }
        }
    }
}
