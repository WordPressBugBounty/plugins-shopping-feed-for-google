<?php
if (! defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * REST API endpoint to retrieve product IDs by category.
 *
 * Route:   GET /gsf/v1/products/categories/{cat_id}
 * Params:  page (default 1), per_page (default 10, max 100)
 * Auth:    WooCommerce consumer_key / consumer_secret (passed as URL query params)
 */
class WP_GSF_Category_Products_Controller extends WP_REST_Controller
{
    const CACHE_GROUP = 'gsf_category_products';

    /**
     * Timestamp WordPress updates whenever items in the group ('terms' or 'posts') change.
     * Same as wp_cache_get_last_changed(), which needs WordPress 4.7.
     */
    private function getLastChangedGSF($group)
    {
        $last_changed = wp_cache_get('last_changed', $group);
        if (! $last_changed) {
            $last_changed = microtime();
            wp_cache_set('last_changed', $last_changed, $group);
        }
        return $last_changed;
    }

    public function callHooksGSF()
    {
        add_action('rest_api_init', array($this, 'registerApiEndpointsGSF'));
    }

    public function registerApiEndpointsGSF()
    {
        register_rest_route(
            'wc-gsf/v1',
            '/products/categories',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'getCategoriesGSF'),
                'permission_callback' => array($this, 'authenticateWooCommerceGSF'),
                'args'                => array(
                    'page'     => array(
                        'default'           => 1,
                        'sanitize_callback' => 'absint',
                        'description'       => 'Page number (1-based).',
                    ),
                    'per_page' => array(
                        'default'           => 10,
                        'sanitize_callback' => 'absint',
                        'description'       => 'Number of categories per page (max 100).',
                    ),
                    'orderby'  => array(
                        'default'           => 'id',
                        'sanitize_callback' => 'sanitize_key',
                        'description'       => 'Sort by: id, name, slug, count, menu_order.',
                    ),
                    'order'    => array(
                        'default'           => 'ASC',
                        'sanitize_callback' => 'sanitize_text_field',
                        'description'       => 'Sort direction: ASC or DESC.',
                    ),
                ),
            )
        );

        register_rest_route(
            'wc-gsf/v1',
            '/products/categories/(?P<cat_id>\d+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'getProductsByCategoryGSF'),
                'permission_callback' => array($this, 'authenticateWooCommerceGSF'),
                'args'                => array(
                    'cat_id'   => array(
                        'required'          => true,
                        'validate_callback' => function ($param) {
                            return is_numeric($param) && (int) $param > 0;
                        },
                        'sanitize_callback' => 'absint',
                        'description'       => 'WooCommerce product category ID.',
                    ),
                    'page'     => array(
                        'default'           => 1,
                        'sanitize_callback' => 'absint',
                        'description'       => 'Page number (1-based).',
                    ),
                    'per_page' => array(
                        'default'           => 100,
                        'sanitize_callback' => 'absint',
                        'description'       => 'Number of product IDs per page (max 500).',
                    ),
                ),
            )
        );
    }

    /**
     * Authenticate request using WooCommerce REST API keys.
     *
     * Uses WC_REST_Authentication::authenticate() with WP_REST_Request.
     * Returns a valid user ID on success.
     *
     * Expects credentials via:
     * ?consumer_key=ck_xxx&consumer_secret=cs_xxx
     *
     * @return true|WP_Error
     */
    public function authenticateWooCommerceGSF()
    {
        if (! class_exists('WC_REST_Authentication')) {
            return new WP_Error('wc_missing', 'WooCommerce not loaded.', ['status' => 500]);
        }

        $auth    = new WC_REST_Authentication();
        $user_id = $auth->authenticate(0);

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        if (empty($user_id)) {
            return new WP_Error(
                'gsf_rest_auth_missing',
                'Missing or invalid API credentials.',
                ['status' => 401]
            );
        }

        return true;
    }

    /**
     * Returns paginated list of all product_cat terms.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function getCategoriesGSF(WP_REST_Request $request)
    {
        global $wpdb;

        $page     = max(1, absint($request->get_param('page')));
        $per_page = min(100, max(1, absint($request->get_param('per_page'))));
        $offset   = ($page - 1) * $per_page;

        // Allowed sort keys; the query's ORDER BY picks the matching column (sort key and order are passed in through the "s" row)
        $orderby_keys = array('id', 'name', 'slug', 'count');
        $orderby_raw  = sanitize_key($request->get_param('orderby'));
        $orderby      = in_array($orderby_raw, $orderby_keys, true) ? $orderby_raw : 'id';

        $order_raw = strtoupper(sanitize_text_field($request->get_param('order')));
        $order     = ($order_raw === 'DESC') ? 'DESC' : 'ASC';

        // Cache key changes whenever categories or products change, so cached results are never stale
        $cache_key = 'categories_' . md5(wp_json_encode(array($page, $per_page, $orderby, $order)))
            . '_' . $this->getLastChangedGSF('terms') . '_' . $this->getLastChangedGSF('posts');
        $cached    = wp_cache_get($cache_key, self::CACHE_GROUP);

        if (is_array($cached)) {
            $total = $cached['total'];
            $terms = $cached['terms'];
        } else {
            $total = (int) $wpdb->get_var("
                SELECT COUNT(*)
                FROM {$wpdb->terms} t
                INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy = 'product_cat'
            ");

            $terms = $wpdb->get_results(
                $wpdb->prepare("
                    SELECT
                        t.term_id,
                        t.name,
                        t.slug,
                        tt.parent,
                        tt.description,
                        COALESCE(pc.count, 0) AS count
                    FROM {$wpdb->terms} t
                    INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                    LEFT JOIN (
                        SELECT tr.term_taxonomy_id, COUNT(DISTINCT p.ID) AS count
                        FROM {$wpdb->posts} p
                        INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                        WHERE p.post_type   = 'product'
                        AND   p.post_status = 'publish'
                        GROUP BY tr.term_taxonomy_id
                    ) AS pc ON pc.term_taxonomy_id = tt.term_taxonomy_id
                    CROSS JOIN (SELECT %s AS orderby, %s AS sort_order) AS s
                    WHERE tt.taxonomy = 'product_cat'
                    ORDER BY
                        CASE WHEN s.orderby = 'id'    AND s.sort_order = 'ASC'  THEN t.term_id END ASC,
                        CASE WHEN s.orderby = 'id'    AND s.sort_order = 'DESC' THEN t.term_id END DESC,
                        CASE WHEN s.orderby = 'name'  AND s.sort_order = 'ASC'  THEN t.name    END ASC,
                        CASE WHEN s.orderby = 'name'  AND s.sort_order = 'DESC' THEN t.name    END DESC,
                        CASE WHEN s.orderby = 'slug'  AND s.sort_order = 'ASC'  THEN t.slug    END ASC,
                        CASE WHEN s.orderby = 'slug'  AND s.sort_order = 'DESC' THEN t.slug    END DESC,
                        CASE WHEN s.orderby = 'count' AND s.sort_order = 'ASC'  THEN pc.count  END ASC,
                        CASE WHEN s.orderby = 'count' AND s.sort_order = 'DESC' THEN pc.count  END DESC
                    LIMIT %d OFFSET %d
                ", $orderby, $order, $per_page, $offset)
            );

            // Don't cache a failed query
            if (is_array($terms)) {
                wp_cache_set($cache_key, array('total' => $total, 'terms' => $terms), self::CACHE_GROUP, DAY_IN_SECONDS);
            }
        }

        // Query failed or no categories found, respond with an empty list
        if (empty($terms) || !is_array($terms)) {
            $terms = array();
        }

        $data = array();
        foreach ($terms as $term) {
            $data[] = array(
                'id'          => (int) $term->term_id,
                'name'        => $term->name,
                'slug'        => $term->slug,
                'parent'      => (int) $term->parent,
                'description' => $term->description,
                'count'       => (int) $term->count,
            );
        }

        $total_pages = (int) ceil($total / $per_page);

        $response = new WP_REST_Response($data, 200);
        $response->header('X-WP-Total', $total);
        $response->header('X-WP-TotalPages', $total_pages);

        return $response;
    }

    /**
     * Returns paginated product IDs belonging to the requested category.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function getProductsByCategoryGSF(WP_REST_Request $request)
    {
		global $wpdb;
        $cat_id   = absint($request->get_param('cat_id'));
        $page     = max(1, absint($request->get_param('page')));
        $per_page = min(500, max(1, absint($request->get_param('per_page'))));
		
		$offset = ($page - 1) * $per_page;


        $category = get_term($cat_id, 'product_cat');

        if (is_wp_error($category) || ! $category || $category->taxonomy !== 'product_cat') {
            return new WP_Error(
                'gsf_invalid_category',
                'Invalid or non-existent category ID.',
                ['status' => 404]
            );
        }

        // Cache key changes whenever categories or products change, so cached results are never stale
        $cache_key   = 'category_products_' . $cat_id . '_' . $page . '_' . $per_page
            . '_' . $this->getLastChangedGSF('terms') . '_' . $this->getLastChangedGSF('posts');
        $product_ids = wp_cache_get($cache_key, self::CACHE_GROUP);

        if (! is_array($product_ids)) {
            $product_ids = array_map('intval', $wpdb->get_col(
                $wpdb->prepare("
                    SELECT p.ID
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                    INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    WHERE p.post_type = 'product'
                    AND p.post_status = 'publish'
                    AND tt.taxonomy = 'product_cat'
                    AND tt.term_id = %d
                    ORDER BY p.ID ASC
                    LIMIT %d OFFSET %d
                ", $cat_id, $per_page, $offset)
            ));
            wp_cache_set($cache_key, $product_ids, self::CACHE_GROUP, DAY_IN_SECONDS);
        }
        $total_count = (int) $category->count;
        $total_pages = (int) ceil($total_count / $per_page);

        $response = new WP_REST_Response([
            'category_id'   => $cat_id,
            'category_name' => $category->name,
            'product_ids'   => $product_ids,
            'page'          => $page,
            'per_page'      => $per_page,
            'total'         => $total_count,
            'total_pages'   => $total_pages,
        ], 200);

        $response->header('X-WP-Total', $total_count);
        $response->header('X-WP-TotalPages', $total_pages);

        return $response;
    }
}
