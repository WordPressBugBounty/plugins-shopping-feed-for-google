<?php 
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


class WP_GSF_Rest_Controller extends WP_REST_Controller {
 
    public function callHooksGSF(){
      add_action( 'rest_api_init', array($this,'registerApiEndpointsGSF') );
    }
    
    function registerApiEndpointsGSF() {
        register_rest_route( 'gsf/v1', '/update-options', array(
            'methods' => 'POST',
            'callback' => array($this,'resetApiAllOptionsGSF'),
            'permission_callback' => '__return_true',
        ) );
      
    }
    
    public function resetApiAllOptionsGSF(){
        global $wpdb;
        $shop_secret_db = getWpShopSecretKeyGSF();
        $message        = "";//changed by DJ @03/06/24, old : [] || new : ""
        $error          = 0;
        $action         = "";//changed by DJ @03/06/24, old : [] || new : ""
        $route_action   = getDataGSF('action');
        $shop_secret    = getDataGSF('shop_secret');

        if(empty($shop_secret) || $shop_secret != $shop_secret_db){
            $error      = 1;
            $message    = "shop secret Mismatch Unauthorized request";
            $action     = $route_action;
        } else {
            switch ( $route_action ) {
                case 'createUpdateOption':
                    $option_key     = getDataGSF('option_key');
                    $option_value   = getDataGSF('option_value');
                    
                    if(strpos($option_key, 'wp_gsf_') === 0){ //added by DJ @04/06/24 //only allow gsf option keys to update.
                        if(!empty($option_key)){
                            $message      = "The wordpress option has been created or updated successfully.";
                            $action       = "wordpress option updated";
                            $option_value = str_replace('\\', '', $option_value);
                            update_option($option_key, $option_value);
                        } else {
                            $error      = 1;
                            $message    = "option Key & value Empty.";
                            $action     = "createUpdateOption";
                        }
                    } else {
                        $error      = 1;
                        $message    = "Requested unauthorized option_key change";
                        $action     = "createUpdateOption";
                    }
                break;
                case 'getShopData':
                    $option_key = getDataGSF('option_key');
                    return getShopDataGSF($option_key);
                break;    
                // added by DJ 01/08/23 to get published product id for debugging
                case 'getPublishedProductId' :
                    $args = array(
                        'limit'     => -1,
                        'status'    => 'publish',
                        'return'    => 'ids',
                        'orderby'   => 'ids',
                        'order'     => 'ASC',
                    );
                    $products = wc_get_products( $args );
                    $response = array(
                        "Total_Product" => count($products),
                        "Product_list"  => implode(',',$products)
                    );
                    return $response;
                break;

                case 'manageAdvanceSettings' :
                    $option_key      = 'wp_gsf_advanced_settings';
                    $setting_key     = getDataGSF('option_key');
                    $setting_status  = getDataGSF('option_status')?? "false";
                    $setting_value   = getDataGSF('option_value') ?? "";

                    if(!empty($setting_key)){
                        setGSFAdvancedSettings($setting_key, $setting_status, $setting_value);
                        $error      = 0;
                        $message    = $setting_key." is updated successfully.";
                        $action     = "manageAdvanceSettings";
                    } else {
                        $error      = 1;
                        $message    = "Requested Setting is not applicable";
                        $action     = "manageAdvanceSettings";
                    }
                break;
                default:
                    $error   = 1;
                    $message = "Invalid Rest Route";
                    $action  = "Invalid action";
                    
            }
        }

        $response = array(
            "error"   => $error,
            "message" => $message,
            "action"  => $action
        );
        return json_encode($response);	
    }
}