<?php
define( 'WP_GSF_PLUGIN_VERSION', '3.8' );
define( 'WP_GSF_PLUGIN_NAME', 'Simprosys Product Feed For WooCommerce' ); //updated by DJ @04/06/24 //Changed by DJ 27/01/23
define( 'WP_GSF_PLUGIN_MENU_NAME', 'Simprosys Product Feed' );//added by DJ @04/06/24
define( 'WP_GSF_API_URL',"https://gsf-wc.simprosysapps.com");
define( 'WP_BASE_URL', get_bloginfo('url'));
define( 'WP_BASE_SITE_URL', get_site_url()); // Change By JG 28/08/2021
define( 'WP_SITE_URL', site_url()); // Change By JG 28/08/2021
define( 'WP_SUPPORT_EMAIL', "support@simprosys.com");
define( 'WP_NOTIFICATION_EMAIL', 'reporting.normal@simprosys.net');

define( 'WP_NOTIFICATION_ERROR_MSG', 'We are not able to complete your request right now. Please try after sometime. Get in touch with us at <a href="mailto:'.WP_SUPPORT_EMAIL.'">'.WP_SUPPORT_EMAIL.'</a> if you are seeing this error again and again.');
define( 'WP_GSF_CDN_IMAGES_PATH','https://cdn.simprosysapps.com/gsf-wc-app/');
$plugin = plugin_basename( __FILE__ );
