<?php

class Ingenius_Tracking_Paypal_Aftership_Order {
    
    private $order_id;

    private $tracking_number;

    private $carrier_name; 

    public function __construct ($order_id) {
        $this->order_id = $order_id;
    }

    /**
     * Check if Aftership is enabled
     *
     * @return void
     */
    public static function check_dependencies() {
        if (!is_plugin_active('aftership-woocommerce-tracking/aftership-woocommerce-tracking.php')) {
			return;
		}
    }

    /**
     * Retrieve the order's payment method
     *
     * @return string
     */
    public function it_get_payment_method(): string{
        $order = wc_get_order($this->order_id);
        return $order->get_payment_method();
    }

    /**
     * Get order's data from the assiocates post meta datas
     * Provides the instantiate object with the tracking number and carrier name
     *
     * @return void
     */
    public function it_register_order_datas() {
        $tracking_items = get_post_meta($this->order_id, '_aftership_tracking_items', true);
        if (!empty($tracking_items)) {
            foreach ($tracking_items as $tracking_item) {
                // Récupérer le numéro de tracking et le nom du transporteur
                $this->tracking_number = isset($tracking_item['tracking_number']) ? $tracking_item['tracking_number'] : '';
                $this->carrier_name = isset($tracking_item['carrier_name']) ? $tracking_item['carrier_name'] : '';
            }
        }
    }


    /**
     * Get the value of tracking_number
     */ 
    public function get_tracking_number()
    {
        return $this->tracking_number;
    }

    /**
     * Get the value of carrier_name
     */ 
    public function get_carrier_name()
    {
        return $this->carrier_name;
    }
}