<?php
define( 'WP_GSF_PLUGIN_VERSION', '2.8' );
define( 'WP_GSF_PLUGIN_NAME', 'Shopping Feed for Google, Microsoft and Multiple Marketing Platforms by Simprosys' ); //updated by DJ @04/06/24 //Changed by DJ 27/01/23
define( 'WP_GSF_PLUGIN_MENU_NAME', 'API Feed by Simprosys' );//added by DJ @04/06/24
define( 'WP_GSF_API_URL',"https://gsf-wc.simpshopifyapps.com/v2");
define( 'WP_BASE_URL', get_bloginfo('url'));
define( 'WP_BASE_SITE_URL', get_site_url()); // Change By JG 28/08/2021
define( 'WP_SITE_URL', site_url()); // Change By JG 28/08/2021
define( 'WP_SUPPORT_EMAIL', "support@simprosys.com");
define( 'WP_NOTIFICATION_EMAIL', 'reporting.normal@simprosys.net');

define( 'WP_NOTIFICATION_ERROR_MSG', 'We are not able to complete your request right now. Please try after sometime. Get in touch with us at <a href="mailto:'.WP_SUPPORT_EMAIL.'">'.WP_SUPPORT_EMAIL.'</a> if you are seeing this error again and again.');

$plugin = plugin_basename( __FILE__ );

