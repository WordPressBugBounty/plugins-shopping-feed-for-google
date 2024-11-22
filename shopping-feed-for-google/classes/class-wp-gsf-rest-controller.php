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
                case 'getOrderDetails':
                    $start_date = getDataGSF('start_date')?? null;
                    $end_date   = getDataGSF('end_date')?? null;
                    $result_with_dates = $this->getGSFOrderData($start_date, $end_date);
                    return json_encode($result_with_dates);
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

    public function getGSFOrderData($start_date = null, $end_date = null) {
        // Prepare the arguments array
        $args = [
            'limit'        => -1, // To retrieve all orders
            'status'       => 'completed', // You can change this to any order status you need
            'meta_key'     => '_created_via', // Meta key for order creation method
            'meta_value'   => 'checkout' // Orders created via checkout
        ];
    
        // Add the date range if provided
        if ($start_date && $end_date) {
            $start_date = date('Y-m-d', strtotime($start_date));
            $end_date   = date('Y-m-d', strtotime($end_date));
        } else {
            $start_date = date('Y-m-d', strtotime('-30 days'));
            $end_date   = date('Y-m-d');
        }

        $args['date_created'] = $start_date . '...' . $end_date;
    
        // Query the orders
        $orders = wc_get_orders($args);
    
        // Get the count of orders
        $order_count = count($orders);
        
        // Initialize result array
        $order_data = [];

        // Iterate over the orders to fetch required details
        foreach ($orders as $order) {
            $order_data[] = [
                'order_id'     => $order->get_id(),
                'order_total'  => $order->get_total(),
                'order_currency' => $order->get_currency(),
            ];
        }
        
        // Return the order count and IDs
        return [
            'order_count' => $order_count,
            'order_data'   => $order_data,
        ];
    }

}