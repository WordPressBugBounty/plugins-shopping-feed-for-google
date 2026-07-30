<?php
if (! defined('ABSPATH')) exit;

if (! class_exists('WP_GSF_Category_Webhooks')) {

    /**
     * Single WooCommerce webhook topic for all product category changes.
     *
     * Topic  : gsf_category.change
     * Payload: { type, id, name, slug, ... }
     *   type = created | updated | deleted | product_added | product_removed
     *
     * One webhook fired per unique category per request — bulk operations
     * (100 products moved) still produce exactly one delivery per category.
     *
     * Priority when multiple events hit the same category in one request:
     *   deleted > created > updated > product_added / product_removed
     */
    class WP_GSF_Category_Webhooks {

        /** @var array<int,string>  term_id => event */
        private static $pending = [];

        /** @var array<int,array>   term_id => payload captured before deletion */
        private static $deleted_data = [];

        /** @var array<int,int>     term_taxonomy_id => term_id cache */
        private static $tt_cache = [];

        private const TOPIC = 'gsf_category.change';
        private const WEBHOOK_CACHE_KEY = 'gsf_has_active_category_webhook';
        private const WEBHOOK_CACHE_TTL = 12 * HOUR_IN_SECONDS;

        private const PRIORITY = [
            'deleted'         => 4,
            'created'         => 3,
            'updated'         => 2,
            'product_added'   => 1,
            'product_removed' => 1,
        ];

        public function __construct() {
            // Single webhook topic
            add_filter('woocommerce_webhook_topics',          [$this, 'register_topics']);
            add_filter('woocommerce_valid_webhook_resources', [$this, 'register_resources']);
            add_filter('woocommerce_valid_webhook_events',    [$this, 'register_events']);
            add_filter('woocommerce_webhook_topic_hooks',     [$this, 'register_topic_hooks']);
            add_filter('woocommerce_webhook_payload',         [$this, 'build_payload'], 10, 4);

            // Category CRUD
            add_action('created_product_cat', [$this, 'on_category_created'], 10, 2);
            add_action('edited_product_cat',  [$this, 'on_category_updated'], 10, 2);
            add_action('delete_product_cat',  [$this, 'on_category_deleted'], 10, 4);

            // Product–category membership
            add_action('added_term_relationship',   [$this, 'on_product_added'],   10, 3);
            add_action('set_object_terms', [$this, 'on_product_removed'], 10, 6);

            // One dispatch per request
            add_action('shutdown', [$this, 'dispatch_pending']);

            // Invalidate the cached webhook check whenever webhooks change
            add_action('woocommerce_new_webhook',     [$this, 'clear_webhook_cache']);
            add_action('woocommerce_webhook_updated', [$this, 'clear_webhook_cache']);
            add_action('woocommerce_webhook_deleted', [$this, 'clear_webhook_cache']);
        }

        // ------------------------------------------------------------------ //
        //  Active-webhook check (12h transient, invalidated on webhook change)
        // ------------------------------------------------------------------ //

        private function has_active_webhook() {
            $cached = get_transient(self::WEBHOOK_CACHE_KEY);
            if ($cached !== false) {
                return $cached === 'yes';
            }

            $has_webhook = false;

            if (class_exists('WC_Data_Store')) {
                $data_store  = WC_Data_Store::load('webhook');
                $webhook_ids = $data_store->get_webhooks_ids('active');

                foreach ($webhook_ids as $webhook_id) {
                    $webhook = wc_get_webhook($webhook_id);
                    if ($webhook && $webhook->get_topic() === self::TOPIC) {
                        $has_webhook = true;
                        break;
                    }
                }
            }

            set_transient(self::WEBHOOK_CACHE_KEY, $has_webhook ? 'yes' : 'no', self::WEBHOOK_CACHE_TTL);

            return $has_webhook;
        }

        public function clear_webhook_cache() {
            delete_transient(self::WEBHOOK_CACHE_KEY);
        }

        // ------------------------------------------------------------------ //
        //  Webhook registration
        // ------------------------------------------------------------------ //

        public function register_topics($topics) {
            $topics['gsf_category.change'] = 'GSF Category Change';
            return $topics;
        }

        public function register_resources($resources) {
            $resources[] = 'gsf_category';
            return $resources;
        }

        public function register_events($events) {
            $events[] = 'change';
            return $events;
        }

        public function register_topic_hooks($topic_hooks) {
            $topic_hooks['gsf_category.change'] = ['gsf_category_hook'];
            return $topic_hooks;
        }

        public function build_payload($payload, $resource, $resource_id, $webhook_id) {
            if ($resource === 'gsf_category' && is_array($resource_id)) {
                return $resource_id;
            }
            return $payload;
        }

        // ------------------------------------------------------------------ //
        //  Event collectors
        // ------------------------------------------------------------------ //

        public function on_category_created($term_id, $tt_id) {
            $this->mark((int) $term_id, 'created');
        }

        public function on_category_updated($term_id, $tt_id) {
            $this->mark((int) $term_id, 'updated');
        }

        /** Capture payload now — term is deleted from DB by shutdown. */
        public function on_category_deleted($term_id, $tt_id, $deleted_term, $object_ids) {
            if (is_wp_error($deleted_term) || ! ($deleted_term instanceof WP_Term)) return;

            $payload         = $this->build_category_payload($deleted_term);
            $payload['type'] = 'deleted';
            $payload['deleted_product_ids'] = array_map('intval', (array) $object_ids);

            self::$deleted_data[$deleted_term->term_id] = $payload;
            $this->mark($deleted_term->term_id, 'deleted');
        }

        public function on_product_added($object_id, $tt_id, $taxonomy) {
            if ($taxonomy !== 'product_cat') return;
            if (get_post_type($object_id) !== 'product') return;

            $term_id = $this->resolve_tt_id((int) $tt_id);
            if ($term_id) $this->mark($term_id, 'product_added');
        }

        public function on_product_removed($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids) {
            if ($taxonomy !== 'product_cat') return;
            if (get_post_type($object_id) !== 'product') return;

            // Detect removed categories
            $removed = array_diff($old_tt_ids, $tt_ids);

            if(! empty($removed)) {
                foreach ($removed as $tt_id) {
                    $term_id = $this->resolve_tt_id((int) $tt_id);
                    if ($term_id) $this->mark($term_id, 'product_removed');
                }
            }
        }

        // ------------------------------------------------------------------ //
        //  Shutdown: one do_action per unique category
        // ------------------------------------------------------------------ //

        public function dispatch_pending() {
            if (empty(self::$pending)) return;

            foreach (self::$pending as $term_id => $event) {
                if ($event === 'deleted') {
                    $payload = self::$deleted_data[$term_id]
                        ?? ['id' => $term_id, 'type' => 'deleted'];
                } else {
                    $term = get_term($term_id, 'product_cat');
                    if (! $term || is_wp_error($term)) continue;
                    $payload         = $this->build_category_payload($term);
                    $payload['type'] = $event;
                }

                do_action('gsf_category_hook', $payload);
            }

            self::$pending    = [];
            self::$deleted_data = [];
        }

        // ------------------------------------------------------------------ //
        //  Helpers
        // ------------------------------------------------------------------ //

        private function mark($term_id, $event) {
            // Skip collecting changes entirely when no active webhook consumes them
            if (! $this->has_active_webhook()) return;

            $current = self::$pending[$term_id] ?? null;
            if ($current === null || self::PRIORITY[$event] > self::PRIORITY[$current]) {
                self::$pending[$term_id] = $event;
            }
        }

        private function build_category_payload(WP_Term $term) {
            $image_id  = get_term_meta($term->term_id, 'thumbnail_id', true);
            $image_url = $image_id ? wp_get_attachment_url((int) $image_id) : '';

            return [
                'id'          => $term->term_id,
                'name'        => $term->name,
                'slug'        => $term->slug,
                'parent_id'   => (int) $term->parent,
                'description' => $term->description,
                'count'       => (int) $term->count,
                'image_url'   => $image_url ?: '',
            ];
        }

        /** Cache tt_id → term_id to avoid repeated DB hits during bulk ops. */
        private function resolve_tt_id($tt_id) {
            if (! isset(self::$tt_cache[$tt_id])) {
                global $wpdb;
                $term_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT term_id FROM {$wpdb->term_taxonomy}
                     WHERE term_taxonomy_id = %d AND taxonomy = 'product_cat'",
                    $tt_id
                ));
                self::$tt_cache[$tt_id] = $term_id ? (int) $term_id : 0;
            }
            return self::$tt_cache[$tt_id] ?: null;
        }
    }

    new WP_GSF_Category_Webhooks();
}
