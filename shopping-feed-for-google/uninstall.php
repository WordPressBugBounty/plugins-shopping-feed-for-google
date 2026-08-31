<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require plugin_dir_path( __FILE__ ) . 'config/const.php';
require plugin_dir_path( __FILE__ ) . 'helpers/helper.php';
require plugin_dir_path( __FILE__ ) . 'classes/class-wp-gsf-http-client.php';

$parameters = array(
'app_status' => 0
);

// Remove the abandoned-customer recurring action. Pending Action Scheduler
// actions are never auto-purged, so an uninstall must cancel the chain itself.
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'gsf_process_abandoned_customers' );
}
delete_transient( 'gsf_has_active_webhook' );
delete_transient( 'gsf_has_active_category_webhook' );
delete_option( 'gsf_wp_abandoned_customer_sessions' );

delete_metadata( 'post', 0, 'gsfwc_product_feed_status', '', true );
update_option('wp_gsf_plugin_status_update', "");
update_option('wp_gsf_google_conversion_tracking_script', null);

//Update Plugin Status
$client = new WP_GSF_HttpClient();
$client->callAPI("uninstall-plugin_status",$parameters);

