<?php

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
            wp_enqueue_script('gsf-embedded-js', plugin_dir_url(__DIR__) . 'assets/main.iife.js', [], WP_GSF_PLUGIN_VERSION, true);
            wp_localize_script('gsf-embedded-js', 'GSF_EMBEDDED', [
                'shop_secret' => getWpShopSecretKeyGSF(),
                'shop_url' => WP_BASE_URL,
                'shop_site_url' => WP_BASE_SITE_URL,
                'plugin_version' => getPluginVersionGSF(),
                'associated_user_id' => get_current_user_id(),
                'permalink_structure' => get_option('permalink_structure'),
                'nonce' => wp_create_nonce( 'wp_create_nonce' ),
                'ajax_url' => admin_url( 'admin-ajax.php' )
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
