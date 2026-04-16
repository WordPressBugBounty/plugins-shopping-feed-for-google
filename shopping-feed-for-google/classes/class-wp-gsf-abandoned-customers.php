<?php

if ( ! class_exists( 'WP_GSF_Abandoned_Customers' ) ) {

    class WP_GSF_Abandoned_Customers {
    
        const ABANDONED_KEY = 'gsf_wp_abandoned_customer_sessions';
        const ABANDONED_DELAY = 1800; //3600;
        const ABANDONED_SCHEDULE_TIME = 300; //3600;
        public function __construct() {
            // Add webhooks
            add_filter( 'woocommerce_webhook_topics', [ $this, 'gsf_wp_webhook_topics' ], 10, 1 );
            add_filter( 'woocommerce_valid_webhook_resources', [ $this, 'gsf_wp_valid_webhook_resources' ], 10, 1 );
            add_filter( 'woocommerce_valid_webhook_events', [ $this, 'gsf_wp_valid_webhook_events' ], 10, 1 );
            add_filter( 'woocommerce_webhook_topic_hooks', [ $this, 'gsf_wp_webhook_topic_hooks' ], 10, 1 );

            // Process Webhooks
            add_action('init', [ $this, 'schedule_gsf_cron' ]);
            add_filter( 'woocommerce_cart_updated', [ $this, 'gsf_wp_woocommerce_cart_updated' ] );
            add_action( 'gsf_process_abandoned_customers', [ $this, 'process_abandoned_customers' ] );
			add_filter( 'woocommerce_webhook_payload', [ $this, 'gsf_modify_webhook_payload' ], 10, 4 );
			
			// Save customer Data in WC session
			add_filter( 'woocommerce_checkout_update_order_review', [ $this, 'gsf_update_session_for_customer_detail' ], 10, 1 );
			add_filter( 'woocommerce_thankyou', [ $this, 'gsf_update_session_id' ], 20, 1 );

            // For Classic Checkout Script
			add_filter( 'wp_footer', [ $this, 'save_customers_data_script' ], 20 );


        }

        public function gsf_wp_webhook_topics($topics) {
            $topics['gsf.abandoned_customer'] = 'Abandoned Customer';
            return $topics;
        }
        public function gsf_wp_valid_webhook_resources($resources) {
            $resources[] = 'gsf';
            return $resources;
        }
        public function gsf_wp_valid_webhook_events($events) {
            $events[] = 'abandoned_customer';
            return $events;
        }
        public function gsf_wp_webhook_topic_hooks($topic_hooks) {
            $topic_hooks['gsf.abandoned_customer'] = array('gsf_wp_abandoned_customer_hook');
            return $topic_hooks;
        }




        public function gsf_custom_cron_intervals($schedules){
            $schedules['five_minutes'] = [
                'interval' => self::ABANDONED_SCHEDULE_TIME,
                'display'  => 'Every 5 Minutes',
            ];
            return $schedules;
        }

        public function schedule_gsf_cron() {
            if ( ! as_next_scheduled_action( 'gsf_process_abandoned_customers' ) ) {
                as_schedule_recurring_action( time(),  self::ABANDONED_SCHEDULE_TIME, 'gsf_process_abandoned_customers' );
            }
        }
        public function gsf_wp_woocommerce_cart_updated() {
            if (is_admin()) return;

            $session = WC()->session;
            if (!$session) return;
            if (!$session->cart) return;

            $session_id = $session->get_customer_id() ?: $session->get_session_cookie()[0];
            $last_update = time();

            // Fetch existing option
            WC()->session->set('gsf_session_id',$session_id);
            $abandoned_sessions = get_option(self::ABANDONED_KEY, []);
            $abandoned_sessions[$session_id] = $last_update;

            // Save back to options
            update_option(self::ABANDONED_KEY, $abandoned_sessions);
        }

        public function process_abandoned_customers(){
            global $wpdb;
            $table = $wpdb->prefix . 'woocommerce_sessions';

            $abandoned_sessions = get_option(self::ABANDONED_KEY, []);
            if (empty($abandoned_sessions)) return;

            $ready_sessions = [];

            // Filter sessions that are past threshold
            foreach ($abandoned_sessions as $session_id => $last_update) {
                if (time() - $last_update >= self::ABANDONED_DELAY) {
                    $ready_sessions[] = $session_id;
                }
            }

            if (empty($ready_sessions)) return;

            $batch_size = 50;
            $chunks = array_chunk($ready_sessions, $batch_size);

            foreach ($chunks as $chunk) {
                // Fetch all session rows in this batch in a single query
                $placeholders = implode(',', array_fill(0, count($chunk), '%s'));
                $query = $wpdb->prepare(
                    "SELECT session_key, session_value FROM $table WHERE session_key IN ($placeholders)",
                    ...$chunk
                );
                $rows = $wpdb->get_results($query);

                if ( ! empty( $rows ) ) {

                    foreach ($rows as $row) {
                        $session_data = maybe_unserialize($row->session_value);
						
                        if (!isset($session_data['cart']) || empty($session_data['cart'])) continue;
                        if (!isset($session_data['customer']) || empty($session_data['customer'])) continue;
                        
                        // Fire webhook
                        do_action('gsf_wp_abandoned_customer_hook', [
							'gsf_session_id' => $row->session_key,
							'session_hash' => $session_cart_hash,
							'customer'       => maybe_unserialize($session_data['customer']),
							'cart'       => maybe_unserialize($session_data['cart']),
                        ]);

                        // Remove processed session from option
                        unset($abandoned_sessions[$row->session_key]);
                    }
                }else{
                    // If no rows found, remove these session IDs from tracking
                    foreach ($chunk as $session_id) {
                        unset($abandoned_sessions[$session_id]);
                    }
                }
            }

            // Save back updated option
            update_option(self::ABANDONED_KEY, $abandoned_sessions);
        }
		
		public function gsf_modify_webhook_payload($payload, $resource, $resource_id, $webhook_id) {
			if ($resource === 'gsf') {
				$logger = new WC_Logger();
				$log_name = 'checking_webhook';
				// You can modify payload here if needed
				$dd = [
					'test' => $payload,
					'res' => $resource,
					'resid' => $resource_id,
					'web' => $webhook
				];
				$logger->add( $log_name, print_r( $dd, true ) );

				return $resource_id; // your custom payload
			}
			return $payload;
		}
		
		public function gsf_update_session_for_customer_detail($posted_data){
			if ( ! WC()->session ) return;

			$data = [];
			parse_str( $posted_data, $data );
			if ( empty( $data ) ) return;

			// Update WC_Customer object
			$customer = WC()->customer;

			// --- Billing fields ---
			$customer->set_billing_first_name( $data['billing_first_name'] ?? $customer->get_billing_first_name() );
			$customer->set_billing_last_name( $data['billing_last_name'] ?? $customer->get_billing_last_name() );
			$customer->set_billing_email( $data['billing_email'] ?? $customer->get_billing_email() );
			$customer->set_billing_phone( $data['billing_phone'] ?? $customer->get_billing_phone() );

			// --- Determine shipping fields based on admin option ---
			$ship_option = get_option( 'woocommerce_ship_to_destination', 'shipping' );

			if ( $ship_option === 'force' ) {
				// Force shipping = billing
				$customer->set_shipping_first_name( $customer->get_billing_first_name() );
				$customer->set_shipping_last_name( $customer->get_billing_last_name() );
				$customer->set_shipping_phone( $customer->get_billing_phone() );

			} elseif ( $ship_option === 'billing' ) {
				// Default to billing address if shipping not provided
				$customer->set_shipping_first_name( $data['shipping_first_name'] ?? $customer->get_billing_first_name() );
				$customer->set_shipping_last_name( $data['shipping_last_name'] ?? $customer->get_billing_last_name() );
				$customer->set_shipping_phone( $data['shipping_phone'] ?? $customer->get_billing_phone() );

			} else {
				// 'shipping' = Default to shipping address
				$customer->set_shipping_first_name( $data['shipping_first_name'] ?? $customer->get_shipping_first_name() );
				$customer->set_shipping_last_name( $data['shipping_last_name'] ?? $customer->get_shipping_last_name() );
				$customer->set_shipping_phone( $data['shipping_phone'] ?? $customer->get_shipping_phone() );
			}
			
			$ship_to_diff = isset($data['ship_to_different_address']) && $data['ship_to_different_address'] == '1';
            WC()->session->set('ship_to_different_address', $ship_to_diff ? '1' : '0');
		}

        public function gsf_update_session_id($order_id){
            if ( ! $order_id )
            return;

            $session = WC()->session;
            if ( $session ) {
                $session_id = WC()->session->get( 'gsf_session_id' );
                update_post_meta($order_id, '_gsf_session_id',$session_id);
            }
        }

        // Detect if classic checkout shortcode is used
        public function is_classic_checkout() {
            global $post;

            if (!is_a($post, 'WP_Post')) {
                return false;
            }

            return has_shortcode($post->post_content, 'woocommerce_checkout');
        }

        // Script for Classic Checkout
        public function save_customers_data_script(){
            if (!$this->is_classic_checkout()) return;

            $ship_to_diff = WC()->session->get('ship_to_different_address');

            ?>
            <script>
            jQuery(function($){
                // Trigger checkout refresh
                $('#billing_first_name,#billing_last_name,#billing_email,#billing_phone,#shipping_first_name,#shipping_last_name,#shipping_phone,#ship-to-different-address-checkbox')
                .on('change', function(){
                    $(document.body).trigger('update_checkout');
                });

                <?php if ($ship_to_diff === '1') : ?>
                    $('#ship-to-different-address-checkbox').prop('checked', true).trigger('change');
                <?php endif; ?>

            });
            </script>
            <?php
        }

    

    }
    new WP_GSF_Abandoned_Customers();
}
