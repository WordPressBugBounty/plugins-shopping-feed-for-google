<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if (! class_exists('WP_GSF_EMBEDDED')) {
    class WP_GSF_EMBEDDED
    {
        public function __construct()
        {
            add_action('admin_enqueue_scripts', [$this, 'enqueue_embedded_assets'], 999);
            add_action('admin_head', [$this, 'remove_notices_from_embedded']);
        }

        public function enqueue_embedded_assets()
        {
            $screen = get_current_screen();
            if (! $screen || ! in_array($screen->id, WP_GSF_EMBEDDED_APP)) {
                return;
            }
            
            wp_enqueue_style('gsf-embedded-style', plugin_dir_url(__DIR__) . 'assets/main.css', [], WP_GSF_PLUGIN_VERSION);
            wp_add_inline_style('gsf-embedded-style', '#wpfooter{display:none;}#wpbody-content{padding-bottom:0}');
            wp_enqueue_script('gsf-embedded-js', plugin_dir_url(__DIR__) . 'assets/main.iife.js', [], WP_GSF_PLUGIN_VERSION, true);

            $userData = getUserDataGSF();
            wp_localize_script('gsf-embedded-js', 'GSF_EMBEDDED', [
                'shop_secret' => getWpShopSecretKeyGSF(),
                'shop_url' => WP_BASE_URL,
                'shop_site_url' => WP_BASE_SITE_URL,
                'plugin_version' => getPluginVersionGSF(),
                'associated_user_id' => $userData['id'] ?? null,
                'associated_user_name' => $userData['display_name'] ?? null,
                'associated_user_email' => $userData['user_email'] ?? null,
                'permalink_structure' => get_option('permalink_structure'),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'ajax_url' => admin_url( 'admin-ajax.php' ),
            ]);
        }
     
        public function remove_notices_from_embedded()
        {
            $screen = get_current_screen();
            if ($screen && in_array($screen->id, WP_GSF_EMBEDDED_APP)) {
                remove_all_actions('admin_notices');
                remove_all_actions('all_admin_notices');
            }
        }
    }
    new WP_GSF_EMBEDDED();
}
