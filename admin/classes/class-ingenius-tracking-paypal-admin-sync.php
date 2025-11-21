<?php //phpcs:ignore

require_once plugin_dir_path(__FILE__) . 'class-ingenius-tracking-paypal-order.php';

if (!class_exists('Ingenius_Tracking_Paypal_Admin_Sync')) {
    /**
     * Handles the PayPal tracking sync admin page and AJAX actions.
     */
    class Ingenius_Tracking_Paypal_Admin_Sync
    {
        private string $menu_slug = 'ingenius-tracking-paypal-sync';
        private string $capability = 'manage_woocommerce';
        private int $ajax_batch_size = 10;

        /**
         * Register the submenu page under WooCommerce.
         */
        public function register_menu_page(): void
        {
            add_submenu_page(
                'woocommerce',
                __('Synchronisation PayPal Tracking', 'ingenius-tracking-paypal'),
                __('PayPal Tracking', 'ingenius-tracking-paypal'),
                $this->capability,
                $this->menu_slug,
                array($this, 'render_page')
            );
        }

        /**
         * Render the admin page content.
         */
        public function render_page(): void
        {
            if (!current_user_can($this->capability)) {
                wp_die(__('Vous n\'avez pas les droits suffisants pour accéder à cette page.', 'ingenius-tracking-paypal'));
            }

            $stats = $this->get_sync_stats();
            $nonce = wp_create_nonce('itp_sync_orders_nonce');
            $page_slug = $this->menu_slug;
            $batch_size = $this->ajax_batch_size;

            include plugin_dir_path(__DIR__) . 'partials/ingenius-tracking-paypal-admin-display.php';
        }

        /**
         * Handle the AJAX synchronization request.
         */
        public function handle_ajax_sync(): void
        {
            if (!current_user_can($this->capability)) {
                wp_send_json_error(array('message' => __('Action non autorisée.', 'ingenius-tracking-paypal')), 403);
            }

            check_ajax_referer('itp_sync_orders_nonce', 'nonce');

            $limit = isset($_POST['batch']) ? absint($_POST['batch']) : $this->ajax_batch_size;
            $limit = $limit > 0 ? $limit : $this->ajax_batch_size;

            $processed = $this->process_pending_orders($limit);
            $stats = $this->get_sync_stats();

            $message = empty($processed)
                ? __('Aucune commande à synchroniser.', 'ingenius-tracking-paypal')
                : sprintf(
                    _n('%d commande synchronisée.', '%d commandes synchronisées.', count($processed), 'ingenius-tracking-paypal'),
                    count($processed)
                );

            wp_send_json_success(
                array(
                    'processed' => count($processed),
                    'processed_ids' => $processed,
                    'stats' => $stats,
                    'message' => $message,
                )
            );
        }

        /**
         * Get statistics about synced and pending orders.
         */
        public function get_sync_stats(): array
        {
            global $wpdb;

            $meta_key = Ingenius_Tracking_Paypal_Order::TRACKING_SENT_META_NAME;
            $payment_meta_key = '_payment_method';
            $payment_like = 'ppcp%';

            $base_query = "FROM {$wpdb->postmeta} tracking
                INNER JOIN {$wpdb->posts} posts ON posts.ID = tracking.post_id
                INNER JOIN {$wpdb->postmeta} payment ON payment.post_id = posts.ID AND payment.meta_key = %s AND payment.meta_value LIKE %s
                WHERE tracking.meta_key = %s AND posts.post_type = 'shop_order' AND posts.post_status NOT IN ('trash', 'auto-draft')";

            $pending = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT tracking.post_id) {$base_query} AND tracking.meta_value = %s",
                    $payment_meta_key,
                    $payment_like,
                    $meta_key,
                    '0'
                )
            );

            $sent = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT tracking.post_id) {$base_query} AND tracking.meta_value = %s",
                    $payment_meta_key,
                    $payment_like,
                    $meta_key,
                    '1'
                )
            );

            $total = $pending + $sent;

            return array(
                'pending' => $pending,
                'sent' => $sent,
                'total' => $total,
            );
        }

        /**
         * Process pending orders to sync.
         *
         * @return int[]
         */
        private function process_pending_orders(int $limit): array
        {
            $order_ids = $this->get_orders_to_sync($limit);

            if (empty($order_ids)) {
                return array();
            }

            foreach ($order_ids as $order_id) {
                new Ingenius_Tracking_Paypal_Order((int) $order_id, 'edit');
            }

            return array_map('intval', $order_ids);
        }

        /**
         * Retrieve pending orders IDs limited by batch size.
         *
         * @return int[]
         */
        private function get_orders_to_sync(int $limit): array
        {
            $query = new WC_Order_Query(
                array(
                    'limit' => $limit,
                    'return' => 'ids',
                    'orderby' => 'date',
                    'order' => 'DESC',
                    'meta_query' => array(
                        'relation' => 'AND',
                        array(
                            'key' => Ingenius_Tracking_Paypal_Order::TRACKING_SENT_META_NAME,
                            'value' => '0',
                            'compare' => '=',
                        ),
                        array(
                            'key' => '_payment_method',
                            'value' => 'ppcp',
                            'compare' => 'LIKE',
                        ),
                    ),
                )
            );

            return $query->get_orders();
        }
    }
}
