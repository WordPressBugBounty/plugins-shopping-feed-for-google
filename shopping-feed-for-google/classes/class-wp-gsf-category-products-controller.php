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

        $orderby_map = array(
            'id'         => 't.term_id',
            'name'       => 't.name',
            'slug'       => 't.slug',
            'count'      => 'count',
        );
        $orderby_raw = sanitize_key($request->get_param('orderby'));
        $orderby_col = isset($orderby_map[$orderby_raw]) ? $orderby_map[$orderby_raw] : 't.term_id';

        $order_raw = strtoupper(sanitize_text_field($request->get_param('order')));
        $order     = ($order_raw === 'DESC') ? 'DESC' : 'ASC';

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
                WHERE tt.taxonomy = 'product_cat'
                ORDER BY {$orderby_col} {$order}
                LIMIT %d OFFSET %d
            ", $per_page, $offset)
        );

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
