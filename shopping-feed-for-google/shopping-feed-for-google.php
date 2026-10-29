<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/*
Plugin Name: Simprosys Product Feed For WooCommerce
Requires Plugins: woocommerce
Plugin URI: http://wordpress.org/plugins/shopping-feed-for-google/
Description: Sync WooCommerce products to Google, Microsoft Advertising & Facebook in real time and boost visibility with Simprosys AI Optimisation.
Version: 5.4.0
Author: Simprosys InfoMedia
Author URI: https://simprosys.com/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/
require plugin_dir_path( __FILE__ ) . 'config/const.php';
require plugin_dir_path( __FILE__ ) . 'classes/class-wp-gsf.php';

register_activation_hook( __FILE__, 'activatePluginGSF' );
register_deactivation_hook( __FILE__, 'deactivatePluginGSF' );

$gsf_plugin = new WP_GSF_Controller();
$gsf_plugin->runGSF();


// Login button in admin notifications (posts "wp_gsf_app_redirect" and "wp_gsf_ref")
function appRedirectGSF() {
    if ( ! isset( $_POST['wp_gsf_app_redirect'] ) ) {
        return;
    }
    check_admin_referer( 'wp_gsf_app_redirect_app_button_clicked' );
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $ref = isset( $_POST['wp_gsf_ref'] ) ? sanitize_text_field( wp_unslash( $_POST['wp_gsf_ref'] ) ) : '';
    $client = new WP_GSF_HttpClient();
    $resultsData = $client->callAPI("verify-api-token",['ref' => $ref]);
    
    if(!empty($resultsData) && isset($resultsData->auth_url)){
       // Allow redirect to the Simprosys app host only
       add_filter( 'allowed_redirect_hosts', function( $hosts ) {
           $hosts[] = wp_parse_url( WP_GSF_API_URL, PHP_URL_HOST );
           return $hosts;
       } );
       wp_safe_redirect( esc_url_raw( $resultsData->auth_url ) );
       exit; // Added By JG 06/12/2021
    } else {
      if(!empty($resultsData) && isset($resultsData->message)){
          setErrorMessageGSF($resultsData->message);
      } else {
          setErrorMessageGSF();
      }
    }
}
add_action( 'admin_init', 'appRedirectGSF' );

if(isCheckWoocommerceAvailableGSF()){
    add_action( 'admin_menu', 'addAdminMenuGSF' );
    add_action( 'wp_ajax_gsf_wp_action', 'ajaxRegisterStoreGSF' ); // If "Woocommerce" is not activate this hooks are useless
    add_action( 'wp_head', 'addGoogleVerificationTokenGSF' ); // If "Woocommerce" is not activate this hooks are useless

    // Display errors if found
    add_action('admin_notices', 'showAdminErrorsGSF');
    add_action('show_gsf_admin_notices', 'showAdminErrorsGSF');
}

add_action( 'upgrader_process_complete', 'upgradePluginVersionGSF', 10, 2 );
add_action( 'admin_notices', 'generalAdminNoticeGSF' );

/*  google conversion script Code */

function addGoogleConversionTrackingScriptGSF() {
    // Theme may have deregistered jQuery; restore the core copy so tracking still works
    if ( ! wp_script_is( 'jquery', 'registered' ) ) {
        // Core ships jquery.min.js since WordPress 5.6, jquery.js before that
        $gsf_jquery_file = version_compare( get_bloginfo( 'version' ), '5.6', '>=' ) ? 'jquery.min.js' : 'jquery.js';
        wp_register_script( 'jquery', includes_url( 'js/jquery/' . $gsf_jquery_file ), array(), get_bloginfo( 'version' ), false );
    }
    wp_enqueue_script( 'gsf-tracking-nonce-js', plugin_dir_url( __FILE__ ) . 'assets/js/gsf-tracking-nonce.js', array( 'jquery' ), WP_GSF_PLUGIN_VERSION, false );
    wp_localize_script( 'gsf-tracking-nonce-js', 'GSF_Tracking', array(
        'nonce'    => wp_create_nonce( 'gsfwc_ajax_nonce' ),
        'ajax_url' => admin_url( 'admin-ajax.php' ),
    ) );
    // Loaded in the header so the tracking setup runs before other tags
    wp_enqueue_script( 'gsfwc-script', getWpGoogleConversionTrackingScriptGSF(), array ( 'jquery', 'gsf-tracking-nonce-js' ), WP_GSF_PLUGIN_VERSION, false );
}

if(isWpGoogleConversionTrackingEnableGSF()){
    
    /* Add GoogleConversionTrackingScriptGSF */
    add_action( 'wp_enqueue_scripts', 'addGoogleConversionTrackingScriptGSF', 20 );
    
    /* product Page view Event */
    add_action(get_option('wp_gsf_product_page_view','woocommerce_before_single_product'),'productViewItemGSF');
    
    /* Category Page view Event */
    add_action(get_option('wp_gsf_shop_page_view','woocommerce_before_shop_loop'),'productViewItemCategoryPageGSF');
    
    /* Cart Page view Event */
    add_action(get_option('wp_gsf_cart_page_view','woocommerce_before_cart'),'productViewItemCartPageGSF');
    
    /* Home Page view Event */
    add_action('wp','productViewItemHomePageGSF');
    
    // Add to Cart Conversion Tag
    add_filter( 'woocommerce_add_to_cart', 'addToCartGSF',10,5 );
    
    /* Checkout Page view Event */
    add_action(get_option('wp_gsf_checkout_page_view','woocommerce_before_checkout_form'), 'proceedToCheckoutGSF', 10);
    
    /* Thank You Page view Event */
    add_action(get_option('wp_gsf_thankyou_page_view','woocommerce_thankyou'), 'proceedToPurchaseGSF', 10, 1);
    add_action('simprosys_purchase_action', 'proceedToPurchaseGSF', 10, 1);

    /* Search Page view Event */
    add_filter( 'get_search_query', 'proceedToSearchGSF' );

    add_action('woocommerce_checkout_create_order', 'saveOrderMetaGSF', 10, 1);
    add_action('woocommerce_store_api_checkout_update_order_meta', 'saveOrderMetaGSF', 10, 1);
    
    // Conversion tag for Add to cart & Checkout for Woocommerce Blocks
    if( has_action( 'render_block' ) ){
        add_filter( 'render_block', 'gsf_woocommerce_block_do_actions', 9999, 2 );
        if ( !is_admin() ){
            add_action('gsf_before_woocommerce/checkout', 'proceedToCheckoutGSF', 10);
            add_action('gsf_before_woocommerce/cart','productViewItemCartPageGSF');
        }
	}
}

if(isEnableGSFAdvancedFeature('gsf_scp_discount')){
    add_action(get_option('wp_gsf_product_page_view','woocommerce_before_single_product'),'gsfAutomatedDiscountPrice');
    add_filter( 'woocommerce_get_price_html', 'alterPriceDisplayGSF', 9999, 2 );
    add_filter( 'woocommerce_add_order_item_meta', 'addOrderItemMetaGSF', 10, 3 );
    add_filter( 'woocommerce_add_cart_item_data', 'addCartItemDataGSF', 10, 3 );
    add_action( 'woocommerce_before_calculate_totals', 'alterCartPriceGSF', 9999 );
    add_action( 'woocommerce_init', 'gsf_woocommerce_init_session', 10 );
    add_filter( 'woocommerce_show_variation_price', 'gsf_check_is_automated_discount', 10, 2 );
}

/* listing page conversion tag ajax call */
//Define AJAX URL
function gsfwc_plugin_ajaxurl() {
   echo '<script type="text/javascript">
           var gsfwc_ajaxurl = "' . esc_url(admin_url('admin-ajax.php')) . '";
         </script>';
}
add_action('wp_head', 'gsfwc_plugin_ajaxurl');
if(isWpGoogleConversionTrackingEnableGSF()){
    add_action( 'wp_ajax_ajaxRequestGSF', 'ajaxRequestGSF' );
    add_action( 'wp_ajax_nopriv_ajaxRequestGSF', 'ajaxRequestGSF' ); 
    add_action( 'wp_ajax_gsfwc_refresh_nonce', 'refreshNonceGSF' );
    add_action( 'wp_ajax_nopriv_gsfwc_refresh_nonce', 'refreshNonceGSF' );
}

/* listing page conversion tag ajax call */


add_filter( 'plugin_row_meta', 'pluginRowMetaGSF', 10, 2 );

function pluginRowMetaGSF( $links, $file ) {    
    if ( plugin_basename( __FILE__ ) == $file ) {
        $row_meta = array(
          'Docs'    => '<a href="' . esc_url( 'https://support.simprosys.com/shopping-feed-for-google' ) . '" target="_blank" aria-label="' . esc_attr__( 'Shopping Feed For Google-WooCommerce', 'shopping-feed-for-google' ) . '">' . esc_html__( 'Docs', 'shopping-feed-for-google' ) . '</a>',          
          'Support' => '<a href="' . esc_url( 'mailto:support@simprosys.com' ) . '" target="_blank" aria-label="' . esc_attr__( 'Support', 'shopping-feed-for-google' ) . '">' . esc_html__( 'Support', 'shopping-feed-for-google' ) . '</a>',
          'Terms Of Service'    => '<a href="' . esc_url( 'https://support.simprosys.com/faq/terms-of-service-shopping-feed-for-google-shopping-plugin' ) . '" target="_blank" aria-label="' . esc_attr__( 'Shopping Feed For Google-WooCommerce', 'shopping-feed-for-google' ) . '">' . esc_html__( 'Terms Of Service', 'shopping-feed-for-google' ) . '</a>'   
        );

        return array_merge( $links, $row_meta );
    }
    return (array) $links;
}

/* Settings Button on plugin */
$plugin = plugin_basename(__FILE__); 
add_filter("plugin_action_links_$plugin", 'pluginSettingsLinkGSF',10,2 );

// Display upgrade notice in plugins page
add_action( "in_plugin_update_message-$plugin", 'gsf_upgrade_notice', 10, 2 );

//Simpro tips Dashboard
add_action( 'plugins_loaded', function() {
    GSF_WC_Tips::init();
    // if (!session_id() && ! is_admin() && ! ( defined('REST_REQUEST') && REST_REQUEST ) && ! ( defined('DOING_AJAX') && DOING_AJAX ) ) { // Start session if not already started in frontend only
    //     session_start();
    // }
});