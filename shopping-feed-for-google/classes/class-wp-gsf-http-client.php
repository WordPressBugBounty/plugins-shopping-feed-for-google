<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WP_GSF_HttpClient {

    public function callAPI($url, $parameters = [], $method = "POST"){
        
        $parameters['plugin_version'] = getPluginVersionGSF();
        $parameters['shop_url']       = WP_BASE_URL;
        $parameters['shop_site_url']  = WP_BASE_SITE_URL;
        $parameters['shop_base_url']  = WP_SITE_URL;
        $parameters['shop_secret']    = getWpShopSecretKeyGSF();
        $parameters['user_info']      = getUserDataGSF();
        $parameters['total_products'] = getWcProductCountsGSF();
        $parameters['request_detail'] = $_SERVER;//added bu DJ @ 16-12-22 for debug 
        
        $request_url = WP_GSF_API_URL."/".$url;
        $request = wp_remote_post($request_url, array(
        'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
        'body'        => json_encode($parameters, true),
        'method'      => "POST",
        'data_format' => 'body',
        ));
        
        if( is_wp_error( $request ) ) {
            $error_string = $request->get_error_message();
            $response_headers =  wp_remote_retrieve_headers( $request );
            $body = wp_remote_retrieve_body( $request );
            $to = WP_NOTIFICATION_EMAIL;
            $subject = 'GSF-WC: Server Error has occurred : from '.WP_BASE_URL.' - WooCommerce';
            $message = '<table border="0" cellpadding="0" cellspacing="0" width="100%" >
                        <tr>
                            <td valign="top">
                            
                            <p style="font-family: Verdana, \'Helvetica Neue\', Helvetica, sans-serif; font-size:12px; ">Hello Admin,<br><br> GSF-WC: Server Error has occurred from  <b>'.WP_BASE_URL.'</b> WooCommerce.<br><br>
                                Here is the detail about Store.<br><br>
                                <table border="0" cellpadding="0" cellspacing="0" width="500" class="flexibleContainer">
                                <tr><td align="left" valign="top" width="150"> Plugin Version </td><td align="left" valign="top" width="250" > : 
                                '.getPluginVersionGSF().'</td></tr>
                                <tr><td align="left" valign="top" width="150"> User Info </td><td align="left" valign="top" width="250" > : 
                                '.json_encode(getUserDataGSF()).'</td></tr>
                                <tr><td align="left" valign="top" width="150"> Shop Secret </td><td align="left" valign="top" width="250" > : 
                                '.getWpShopSecretKeyGSF().'</td></tr>
                                <tr><td align="left" valign="top" width="150"> Shop Url </td><td align="left" valign="top" width="250" > : 
                                '.WP_BASE_URL.'</td></tr>
                                <tr><td align="left" valign="top" width="150"> Call End Point </td><td align="left" valign="top" width="250" > : 
                                '.$request_url.'</td></tr>
                                <tr><td align="left" valign="top" width="150"> Error String </td><td align="left" valign="top" width="250" > : 
                                '.$error_string.'</td></tr>
                                <tr><td align="left" valign="top" width="150"> parameters </td><td align="left" valign="top" width="250" > : 
                                '. json_encode($parameters) .'</td></tr>
                              <tr><td align="left" valign="top" width="150"> body </td><td align="left" valign="top" width="250" > : 
                                '. json_decode($body) .'</td></tr>
                                <tr><td align="left" valign="top" width="150"> headers </td><td align="left" valign="top" width="250" > : 
                                '. json_encode($response_headers) .'</td></tr>
                                </table><br><br>
                            </p>
                            
                            </td>
                        </tr>
                    </table>';
            $headers = array('Content-Type: text/html; charset=UTF-8');
      
          wp_mail( $to, $subject, $message, $headers );
          setErrorMessageGSF();
        } else {
          $results = wp_remote_retrieve_body( $request );
          return json_decode( $results );  
        }
    }

}
