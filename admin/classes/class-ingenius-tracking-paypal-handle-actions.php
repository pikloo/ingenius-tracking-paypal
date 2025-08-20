<?php // phpcs:ignore

if (! class_exists('Ingenius_Tracking_Paypal_Handle_Actions')) {

    class Ingenius_Tracking_Paypal_Handle_Actions
    {

        /**
         * Is the order as already updated
         *
         * @var boolean
         */
        private $order_updated = false;

        /**
         * Detects if the order has been manually or by REST API saved
         * Check if order exist and prevent the action from being executed only once
         *
         * @param int $order_id The id of current WooCommerce order.
         */
        public function handle_order_save(int $order_id): void
        {
            if (! $order_id || $this->order_updated) {
                return;
            }

            $this->order_updated = true;
            $this->process_paypal_order_tracking($order_id);
        }


        /**
         * Detects if the order has been import via WP All Import
         *
         * @param int $post_id The ID of the current post.
         */
        public function handle_wp_all_import_order(int $post_id): void
        {
            $post_type = get_post_type($post_id);
            if ('shop_order_placehold' === $post_type || 'shop_order' === $post_type) {
                $this->process_paypal_order_tracking($post_id, 'import');
            }
        }

        /**
         * Treats order tracking for order paid with PayPal
         *
         * @param int    $order_id The id of current order.
         * @param string $mode The edition mode of the order.
         */
        private function process_paypal_order_tracking(int $order_id, string $mode = 'edit'): void
        {
            require_once plugin_dir_path(__FILE__) . 'class-ingenius-tracking-paypal-order.php';
            new Ingenius_Tracking_Paypal_Order($order_id, $mode);
        }
    }
}
