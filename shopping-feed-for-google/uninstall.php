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

//Update Plugin Status
$client = new WP_GSF_HttpClient();
$client->callAPI("uninstall-plugin_status",$parameters);

