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

delete_metadata( 'post', 0, 'gsfwc_product_feed_status', '', true );
update_option('wp_gsf_plugin_status_update', "");
update_option('wp_gsf_google_conversion_tracking_script', null);

//Update Plugin Status
$client = new WP_GSF_HttpClient();
$client->callAPI("uninstall-plugin_status",$parameters);

