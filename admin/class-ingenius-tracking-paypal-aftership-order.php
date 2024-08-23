<?php

namespace IngeniusTrackingPaypal\Admin;

use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WP_Error;

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
            $this->tracking_number = $order->get_meta('_aftership_tracking_number') ?? '';
            $tracking_items = $order->get_meta('_aftership_tracking_items', true);
            //Déserialize de la métadonnée
            $tracking_items_data = maybe_unserialize($tracking_items);
            // Vérifier que la désérialisation a réussi
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

            // Vérification du nom du transporteur
            if (!isset(self::CARRIERS_NAME[$this->carrier_name])) {
                $this->it_notify_admin_of_unknown_carrier();
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
         * - Check if the required OrderTrackingEndpoint classes and methods are available
         * - Initialize a Paypal instance connection with client ID & Client secret
         * - Check the tracking linked to the order
         * - Update tracking datas or add new tracking by deleting old tracking
         *
         * @return void
         */
        public function it_send_tracking_to_paypal()
        {
            $order = wc_get_order($this->order_id);

            $paypal_settings = get_option('woocommerce-ppcp-settings');
            $client_id = isset($paypal_settings['sandbox_on']) && $paypal_settings['sandbox_on'] ? $paypal_settings['client_id_sandbox'] : $paypal_settings['client_id_production'];
            $client_secret = isset($paypal_settings['sandbox_on']) && $paypal_settings['sandbox_on'] ? $paypal_settings['client_secret_sandbox'] : $paypal_settings['client_secret_production'];

            require_once plugin_dir_path(__FILE__) . 'class-ingenius-tracking-paypal-api-connection.php';
            $paypal_connection = new PayPalConnection($client_id, $client_secret);

            try {
                $paypal_token = $paypal_connection->it_get_paypal_bearer_token();
                $paypal_token_data = json_decode($paypal_token);

                //Préparation des datas à envoyer à paypal
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

                // Check si la commande est déja suivi par Paypal tracking 
                $order_tracking_response = $paypal_connection->it_get_order_tracking($order->get_meta('_ppcp_paypal_order_id'), $paypal_token_data->access_token);
                if ($order_tracking_response['code'] == 200) {
                    $order_details =  json_decode($order_tracking_response['response']);
                    $shipping_data = $order_details->purchase_units[0]->shipping;

                    // Récupération des tracking dans les détails de la commande tracké par Paypal
                    if (isset($shipping_data->trackers)) {
                        $trackers = $shipping_data->trackers;
                        foreach ($trackers as $tracker) {
                            // Récupération du numéro de tracking dans la clé id (ID de la transaction-numéro de tracking)
                            $parts = explode('-', $tracker->id);
                            $status = $tracker->status;
                            $tracking_number = isset($parts[1]) ? $parts[1] : '';
                            //Annulation du tracking si le numéro de tracking est différent du numéro de tracking de la commande enregistrée
                            if ($tracking_number != $this->tracking_number) {
                                $tracking_data_to_update['status'] = 'CANCELLED';
                                $tracking_data_to_update['tracking_number'] = $tracking_number;
                                $paypal_connection->it_update_order_tracking($order->get_transaction_id(), $tracking_data_to_update, $paypal_token_data->access_token);
                            }
                        }

                        // Recherche si le numéro de tracking lié à la commande existe coté Paypal
                        $trackers_map = array_map(function ($tracker) {
                            return (array) $tracker;
                        }, $trackers);

                        $column = array_column($trackers_map, 'id');
                        $order_tracking = array_search("{$order->get_transaction_id()}-{$this->tracking_number}", $column);

                        // Ajout du tracking Paypal si le numéro de tracking lié à la commande n'existe ou modification dans le cas contraire
                        if (!$order_tracking) {
                            $paypal_connection->it_add_order_tracking($order->get_meta('_ppcp_paypal_order_id'), $tracking_data, $paypal_token_data->access_token);
                        } else {
                            // Annulation du tracking paypal si le tracking number n'est pas renseignée 
                            if(empty($this->tracking_number)) {
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
                error_log($e->getMessage()) ;
            }
        }

        /**
         * Send an e-mail to admin account if a carrier name is unknown
         *
         * @return void
         */
        private function it_notify_admin_of_unknown_carrier()
        {

            //TODO: Créer une adresse mail uniquement destinée au debug
            $admin_email = get_option('admin_email');
            $subject = 'Transporteur inconnu lors de l\'import de la commande';
            $message = sprintf(
                'Le transporteur "%s" utilisé pour la commande #%d n\'est pas reconnu. Veuillez vérifier et mettre à jour les informations si nécessaire.',
                $this->carrier_name,
                $this->order_id
            );
            wp_mail($admin_email, $subject, $message);
        }

        private function it_save_aftership_tracking_items(WC_Order $order)
        {
            $this->tracking_items[0]["tracking_number"] = $this->tracking_number;
            $this->tracking_items[0]["slug"] = $this->carrier_name;
            $this->tracking_items[0]["metrics"]["updated_at"] = current_time('c');
            // Sérialiser à nouveau la méta-donnée
            $tracking_items_serialized = maybe_serialize($this->tracking_items);
            // Mettre à jour la méta-donnée dans la base de données
            $order->update_meta_data('_aftership_tracking_items', $tracking_items_serialized);
            $order->save();
        }
    }
}
