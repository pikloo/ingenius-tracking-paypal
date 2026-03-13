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
            $payment_like = 'ppcp%';
            $pending = $this->count_orders_by_tracking_state($meta_key, $payment_like, '0');
            $sent = $this->count_orders_by_tracking_state($meta_key, $payment_like, '1');

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
            global $wpdb;

            $meta_key = Ingenius_Tracking_Paypal_Order::TRACKING_SENT_META_NAME;
            $payment_like = 'ppcp%';

            if ($this->uses_hpos()) {
                $orders_table = $wpdb->prefix . 'wc_orders';
                $orders_meta_table = $wpdb->prefix . 'wc_orders_meta';

                $results = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT DISTINCT tracking.order_id
                        FROM {$orders_meta_table} tracking
                        INNER JOIN {$orders_table} orders ON orders.id = tracking.order_id
                        WHERE tracking.meta_key = %s
                        AND tracking.meta_value = %s
                        AND orders.type = 'shop_order'
                        AND orders.status NOT IN ('trash', 'auto-draft', 'wc-trash', 'wc-auto-draft')
                        AND orders.payment_method LIKE %s
                        ORDER BY orders.date_created_gmt DESC
                        LIMIT %d",
                        $meta_key,
                        '0',
                        $payment_like,
                        $limit
                    )
                );

                return array_map('intval', $results);
            }

            $results = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT tracking.post_id
                    FROM {$wpdb->postmeta} tracking
                    INNER JOIN {$wpdb->posts} posts ON posts.ID = tracking.post_id
                    INNER JOIN {$wpdb->postmeta} payment ON payment.post_id = posts.ID
                    WHERE tracking.meta_key = %s
                    AND tracking.meta_value = %s
                    AND payment.meta_key = %s
                    AND payment.meta_value LIKE %s
                    AND posts.post_type = 'shop_order'
                    AND posts.post_status NOT IN ('trash', 'auto-draft')
                    ORDER BY posts.post_date_gmt DESC
                    LIMIT %d",
                    $meta_key,
                    '0',
                    '_payment_method',
                    $payment_like,
                    $limit
                )
            );

            return array_map('intval', $results);
        }

        private function count_orders_by_tracking_state(string $meta_key, string $payment_like, string $tracking_state): int
        {
            global $wpdb;

            if ($this->uses_hpos()) {
                $orders_table = $wpdb->prefix . 'wc_orders';
                $orders_meta_table = $wpdb->prefix . 'wc_orders_meta';

                return (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(DISTINCT tracking.order_id)
                        FROM {$orders_meta_table} tracking
                        INNER JOIN {$orders_table} orders ON orders.id = tracking.order_id
                        WHERE tracking.meta_key = %s
                        AND tracking.meta_value = %s
                        AND orders.type = 'shop_order'
                        AND orders.status NOT IN ('trash', 'auto-draft', 'wc-trash', 'wc-auto-draft')
                        AND orders.payment_method LIKE %s",
                        $meta_key,
                        $tracking_state,
                        $payment_like
                    )
                );
            }

            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT tracking.post_id)
                    FROM {$wpdb->postmeta} tracking
                    INNER JOIN {$wpdb->posts} posts ON posts.ID = tracking.post_id
                    INNER JOIN {$wpdb->postmeta} payment ON payment.post_id = posts.ID
                    WHERE tracking.meta_key = %s
                    AND tracking.meta_value = %s
                    AND payment.meta_key = %s
                    AND payment.meta_value LIKE %s
                    AND posts.post_type = 'shop_order'
                    AND posts.post_status NOT IN ('trash', 'auto-draft')",
                    $meta_key,
                    $tracking_state,
                    '_payment_method',
                    $payment_like
                )
            );
        }

        private function uses_hpos(): bool
        {
            return class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')
                && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        }
    }
}
