<?php 
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

function getShopDataGSF($option_key){
      $shop_details = [];    
      if(!empty($option_key)){
          $shop_details[$option_key] = get_option($option_key);
      } else {
          $shop_details = get_alloptions();
      } 
    return $shop_details;
}

function getUserDataGSF(){
    global $wpdb;
    
    $table_wp_users    = $wpdb->prefix . 'users';
    $table_wp_usermeta = $wpdb->prefix . 'usermeta';

    $current_users = []; 
    if (isUserLoggedInGSF()) { 
      $current_user_data = wp_get_current_user();
    } else {
      $users = $wpdb->get_results("SELECT u.ID, u.user_email, u.display_name FROM $table_wp_users u INNER JOIN $table_wp_usermeta m ON m.user_id = u.ID WHERE m.meta_key = 'wp_capabilities' AND m.meta_value LIKE '%administrator%' ORDER BY u.user_registered LIMIT 1");
        if(count($users) > 0){
          $current_user_data = isset($users[0]) ? $users[0] : [];
        }

        if(empty($current_user_data)){
          $all_users = $wpdb->get_results( "SELECT ID, user_email, display_name FROM $table_wp_users LIMIT 1" );
          $current_user_data = isset($all_users[0]) ? $all_users[0] : [];
        }
    }
     
    $current_users['id']           = isset($current_user_data->ID)?$current_user_data->ID : 1;
    $current_users['user_email']   = isset($current_user_data->user_email)?$current_user_data->user_email : "";
    $current_users['display_name'] = isset($current_user_data->display_name)?$current_user_data->display_name : ""; 

    return $current_users; 
}

function getRemoteDataContentHtmlGSF(){
    $client      = new WP_GSF_HttpClient();
    $resultsData = $client->callAPI("get-description-html");
    return $resultsData;
}

function getDataGSF($key, $default = "", $need_url_decode = false) {
    return isset($_REQUEST[$key]) ? addslashes(($need_url_decode ? urldecode($_REQUEST[$key]) : $_REQUEST[$key])) : $default;
}

function getPluginVersionGSF() {
  if ( ! function_exists( 'get_plugins' ) ) {
     require_once ABSPATH . 'wp-admin/includes/plugin.php';
  }
  $plugin_data = get_plugins();
  if(is_array($plugin_data) && isset($plugin_data['shopping-feed-for-google/shopping-feed-for-google.php']['Version'])){
    $current_plugin_version = $plugin_data['shopping-feed-for-google/shopping-feed-for-google.php']['Version'];
  } else {
    $current_plugin_version = WP_GSF_PLUGIN_VERSION;
  }
  return $current_plugin_version;
}

function getWpShopSecretKeyGSF(){

  $shop_secret = get_option('wp_gsf_shop_secret', null);
  
  if ($shop_secret !==  null) { 
    $shop_secret = unserialize($shop_secret); 
  }

  return $shop_secret;
}

function setErrorMessageGSF($error_message = ""){
    if(!is_admin()){ return; }
    if(empty($error_message)){
        $error_message = WP_NOTIFICATION_ERROR_MSG;
    }
    // Set error transient if not 
    $display_errors = get_transient('show_gsf_errors');
    if(empty($display_errors)){
      set_transient('show_gsf_errors',$error_message,60);
      do_action('show_gsf_admin_notices');
    }
}

function registerStoreGSF(){
  global $wpdb;
  
    $store_country_code = "";
    $store_province_code = "";

    // The country code /province_code
    $store_raw_country = get_option( 'woocommerce_default_country' );

    if(isset($store_raw_country))
    {
        // Split the country code/province_code
        $split_country = explode( ":", $store_raw_country );

        // Country code and province_code separated:
        $store_country_code   = isset($split_country[0])?$split_country[0]:'';
        $store_province_code  = isset($split_country[1])?$split_country[1]:'';
    }
    $user_detail = getUserDataGSF();

    $shopData = array(
        'user_id'         => $user_detail['id'],
        'shop_url'        => WP_BASE_URL,
        'shop_email'      => $user_detail['user_email'],
        'shop_owner'      => $user_detail['display_name'],
        'shop_name'       => get_bloginfo('name'),
        'currency'        => get_woocommerce_currency(),
        'country_code'    => $store_country_code,
        'province_code'   => $store_province_code,
        'city'            => get_option( 'woocommerce_store_city' ),
        'address1'        => get_option( 'woocommerce_store_address' ),
        'gmt_offset_timezone' => get_option('gmt_offset'),
        'string_timezone' => get_option('timezone_string')
    );
    
    $client = new WP_GSF_HttpClient();
    $resultsData = $client->callAPI("register-store",$shopData);
    
    if($resultsData){
        update_option('wp_gsf_shop_secret', serialize($resultsData->shop_secret));
        update_option('woocommerce_api_enabled', 'yes');
    }
}

function isDependencyAvailableGSF(){
    $active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );

    if (is_array($active_plugins) && in_array( 'woocommerce/woocommerce.php', $active_plugins ) ) {
      return true;
    } else {
      deactivate_plugins( plugin_basename( __FILE__ ) );
      $link = admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' );
      wp_die( WP_GSF_PLUGIN_NAME." can not activate because WooCommerce is not installed or active. Please activate WooCommerce if already installed or <a href='".$link."'>Install WooCommerce!</a>" );
      return false;
    }

}

function isUserLoggedInGSF() {
    
    if ( ! function_exists( 'wp_get_current_user' ) ) {
         include_once(ABSPATH . 'wp-includes/pluggable.php');
    }
    
    $user = wp_get_current_user();
    if($user->exists()){
        return true;
    } else {
        return false;
    }

}

function addGoogleVerificationTokenGSF() {
  $google_token_string = stripslashes(get_option('wp_gsf_google_token_string', ''));//Updated by DJ @12/11/24 old:null, php8.1 deprecation
  if ( !empty($google_token_string) ) {
    printf($google_token_string);
  }
}

function addAdminMenuGSF() {
    $filename_icon = sanitize_file_name('WC_GSF_logo_30.png'); //updated by DJ @04/06/24
    $page_title = WP_GSF_PLUGIN_NAME;
    $menu_title = WP_GSF_PLUGIN_MENU_NAME;
    $capability = 'manage_options';
    $menu_slug = 'shopping-feed-for-google';
    $function = 'menuCallbackGSF';
    $icon_url  = plugin_dir_url( __DIR__ ) . 'assets/img/'.$filename_icon;
    $position  = 81;
    add_menu_page(  $page_title,  $menu_title,  $capability,  $menu_slug,  $function  ,$icon_url ,$position );
}

function menuCallbackGSF() {
    require_once plugin_dir_path( __DIR__ ) . '/views/shopping-feed-for-google.php';
}

function activatePluginGSF() {
    WP_GSF_Activator::activate();
}

function deactivatePluginGSF() {
    WP_GSF_Activator::deactivate();
}

function pluginActivationRedirectGSF( $plugin ) {
      exit( wp_redirect( admin_url( 'admin.php?page=shopping-feed-for-google' ) ) );    
}

function pluginDeactivateGSF(){
  global $wpdb;
    $shopData = array(
     'is_activated' => 0
    );
    $client = new WP_GSF_HttpClient();
    $client->callAPI("update-plugin-status",$shopData); 
}

function isCheckWoocommerceAvailableGSF(){
    $active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );
    if (is_array($active_plugins) && in_array( 'woocommerce/woocommerce.php', $active_plugins ) ) {
      return true;
    } else {
      return false;
    }
}

function getWcProductCountsGSF(){
    $count_products = wp_count_posts( 'product' );
    return isset($count_products->publish)?$count_products->publish : 0;
}

function upgradePluginVersionGSF( $upgrader_object, $options ) {
  if ( $options['action'] == 'update' && $options['type'] === 'plugin' && isset($options['plugins']) && is_array($options['plugins']))  { //updated by DJ @10-08-22 added isset
    if(in_array("shopping-feed-for-google/shopping-feed-for-google.php",$options['plugins'])){   //added by DJ @10-08-22
        $client = new WP_GSF_HttpClient();
        $client->callAPI("upgrade-plugin-version");
    }
  }
}

function isCheckDefaultPermalinkGSF(){
  $structure = get_option( 'permalink_structure' );
  if(!isset($structure) || trim($structure) === ''){
        return true;
    } else {
        return false;
    }
}


/*  Added google conversion script Code : By JG : 24/03/2021 */

function getWpGoogleConversionTrackingScriptGSF(){
    return get_option('wp_gsf_google_conversion_tracking_script', null);
}

function isWpGoogleConversionTrackingEnableGSF(){
    $get_wp_gsf_gct_script = getWpGoogleConversionTrackingScriptGSF();
    
    if($get_wp_gsf_gct_script === 'NULL' || $get_wp_gsf_gct_script === 'null' || $get_wp_gsf_gct_script == '' || empty($get_wp_gsf_gct_script)){
        return false;
    } 
    return (filter_var($get_wp_gsf_gct_script, FILTER_VALIDATE_URL))? true: false;
}

if ( ! function_exists( 'callJSFuncGSF' ) ) {
    function callJSFuncGSF($productData, $funName){
        
        $productData = str_replace("'", "\'", json_encode($productData));
        
        add_action( 'wp_footer', function() use ($productData,$funName) { 
            if ($funName == 'proceedToSearchGSF') { ?>
                <script>
                    var product_search_data = '<?php echo $productData; ?>';
                    document.addEventListener('DOMContentLoaded', function () { <?php echo $funName; ?>(product_search_data) }, false);
                </script>
            <?php } else { ?>
                <script> var product_data = '<?php echo $productData; ?>'; document.addEventListener('DOMContentLoaded', function() { <?php echo $funName; ?>(product_data) }, false);
                </script>
            <?php } 
        });
    }
}

function arrayToStrCommaGSF($arr){
    return implode(', ', $arr);
}


/* Added code for Google Conversion Tracking */

if ( ! function_exists( 'proceedToPurchaseGSF' ) ) {
  function proceedToPurchaseGSF($order_id){
    if ( ! $order_id )
      return;
          
    // Allow code execution only once 
    if( ! get_post_meta( $order_id, 'thankyou_action_done_'.$order_id, true ) ) {
        update_post_meta($order_id, 'thankyou_action_done_'.$order_id,1);
        // Get an instance of the WC_Order object
        $order = wc_get_order( $order_id );

        $productData = array();  
        // Loop through order items
        $count = 0;
        foreach ( $order->get_items() as $item_id => $item ) {
          // Get the product object
          $product  = $item->get_product();
          $quantity = $item->get_quantity(); 
          // global $product; 
          //$variation_id = isset($product->get_children()[0]) ? $product->get_children()[0] : 0;
          //$productData[$count]['variant_id']= $variation_id;
          //$productData[$count]['product_id']= $product->get_id();
          
          /* edited by DJ 28/6/21 */
          if($product->get_parent_id()==0){
            $productData[$count]['product_id']	= $item->get_product_id();
            $productData[$count]['variant_id']	= 0;
          }else{
            $productData[$count]['product_id']	= $item->get_product_id();
            $productData[$count]['variant_id']	= $product->get_id();
          }
          /* edited by DJ 28/6/21 */
          
          $productData[$count]['name']        = filterStringsWithHtmlentitiesGSF($product->get_name());
          $productData[$count]['price']       = $product->get_price();//$product->get_regular_price();
          $productData[$count]['quantity']    = $quantity ?? 1;
          $productData[$count]['currency']    = get_woocommerce_currency();
          $productData[$count]['sku']         = $product->get_sku();
          $productData[$count]['brand']       = "";
          $productData[$count]['variant']     = arrayToStrCommaGSF($product->get_children());
          $productData[$count]['category']    = strip_tags( wc_get_product_category_list( $productData[$count]['product_id'] ) );
          $productData[$count]['total_price']	= $product->get_price();
          $count++;
        }
        $total_price    = $order->get_total()? $order->get_total() : 0;
        $subtotal_price = $order->get_subtotal()? $order->get_subtotal() : 0; // added by DJ 01/08/23
        $total_tax      = $order->get_total_tax()? $order->get_total_tax() : 0;//added by DJ @29/07/24
        $total_shipping = $order->get_shipping_total()?$order->get_shipping_total(): 0;//added by DJ @29/07/24
        
        $productData['order_id']           = $order_id;
        $productData['subtotal_price']     = $subtotal_price; // added by DJ 01/08/23
        $productData['total_price']        = $total_price;
        $productData['total_tax']          = $total_tax;
        $productData['total_shipping']     = $total_shipping;
        $productData['discount']           = $order->get_discount_total();//$order_id;
        $productData['currency']           = get_woocommerce_currency();
        $productData['order_created_date'] = ($order->get_date_created() != '') ?  $order->get_date_created() : ''; // added by PL @14/09/23 for GCR 

        // added by DJ @15/06/22 for enhanced conversion tracking 
        // Get the Customer billing email
        $productData['billing_email']  = ($order->get_billing_email() != '')?$order->get_billing_email():'';

        // Get the Customer billing phone
        $productData['billing_phone']  = ($order->get_billing_phone() != '')?$order->get_billing_phone():'';

        // Customer billing information details
        $productData['billing_first_name'] = ($order->get_billing_first_name() != '')?$order->get_billing_first_name():'';
        $productData['billing_last_name']  = ($order->get_billing_last_name() != '')?$order->get_billing_last_name():'';
        $productData['billing_company']    = ($order->get_billing_company() != '')?$order->get_billing_company():'';
        $productData['billing_address_1']  = ($order->get_billing_address_1() != '')?$order->get_billing_address_1():'';
        $productData['billing_address_2']  = ($order->get_billing_address_2() != '')?$order->get_billing_address_2():'';
        $productData['billing_city']       = ($order->get_billing_city() != '')?$order->get_billing_city():'';
        $productData['billing_state']      = ($order->get_billing_state() != '')?$order->get_billing_state():'';
        $productData['billing_postcode']   = ($order->get_billing_postcode() != '')?$order->get_billing_postcode():'';
        $productData['billing_country']    = ($order->get_billing_country() != '')?$order->get_billing_country():'';
        $productData['order_key']          = ($order->get_order_key() != '')?$order->get_order_key():'';
        // added by DJ @15/06/22 for enhanced conversion tracking 
        
        callJSFuncGSF($productData, "proceedToPurchaseGSF");
    }
  }
}

if ( ! function_exists( 'proceedToCheckoutGSF' ) ) {
  function proceedToCheckoutGSF(){
    $gsfwc_cart     = WC()->cart;
    $subtotal_price = $gsfwc_cart->subtotal ? $gsfwc_cart->subtotal : 0; // added by DJ 01/08/23
    $total_price    = $gsfwc_cart->total ? $gsfwc_cart->total : 0;
    $items          = $gsfwc_cart->get_cart();
    $count          = 0;
    $productData    = array();

    foreach($items as $values) { 
      $_product =  wc_get_product( $values['data']->get_id()); 
      $price    = get_post_meta($values['data']->get_id() , '_price', true);/* edited by DJ 28/6/21 old ($values['product_id'] ) */
      $sku      = get_post_meta($values['data']->get_id() , '_sku', true);/* edited by DJ 28/6/21 old ($values['product_id'] ) */

      $productData[$count]['variant_id']= $values['variation_id'];
      $productData[$count]['product_id']= $values['product_id'];
      $productData[$count]['name']= filterStringsWithHtmlentitiesGSF($_product->get_title());
      $productData[$count]['price']= $price;
      $productData[$count]['quantity']= $values['quantity']; // added by DJ 01/08/23
      $productData[$count]['currency']= get_woocommerce_currency();
      $productData[$count]['sku']= $sku;
      $productData[$count]['brand']= "";/* edited by DJ 28/6/21 old ($values['product_id'] ) */
      $productData[$count]['variant']= arrayToStrCommaGSF($_product->get_children());/* edited by DJ 28/6/21 old ($values['product_id'] ) */
      $productData[$count]['category']= strip_tags(wc_get_product_category_list($values['product_id']) );/* edited by DJ 28/6/21 old ($values['product_id'] ) */
      
      $count++;
    } 
    $productData['subtotal_price']  = $subtotal_price; // added by DJ 01/08/23
    $productData['total_price'] = $total_price;
    $productData['currency']        = get_woocommerce_currency(); // added by DJ 01/08/23
    callJSFuncGSF($productData, "proceedToCheckoutGSF");
  }
}

if ( ! function_exists( 'productViewItemCategoryPageGSF' ) ) {
  function productViewItemCategoryPageGSF(){
      
      global $product;

      $cate = get_queried_object();
      $cateID = isset($cate->term_id)? $cate->term_id : "";

      if($cateID != ""){
        $args = array(
          'post_type'             => 'product',
          'post_status'           => 'publish',
          'ignore_sticky_posts'   => 1,
          'posts_per_page'        => '4',
          'orderby' => array( 'title' => 'ASC'), 
          'tax_query'             => array(
            array(
                'taxonomy'      => 'product_cat',
                'field' => 'term_id',
                'terms'         => $cateID,
                'operator'      => 'IN'
            ),
            array(
                'taxonomy'      => 'product_visibility',
                'field'         => 'slug',
                'terms'         => 'exclude-from-catalog',
                'operator'      => 'NOT IN'
            )
          )
        );
      } else  {
        $args = array(
            'post_type'             => 'product',
            'post_status'           => 'publish',
            'ignore_sticky_posts'   => 1,
            'posts_per_page'        => '4',
            'orderby' => array( 'title' => 'ASC')
        );
      }

      $products = new WP_Query($args);
      $productData = array();
      $count = 0;
      $total_price = 0;

      while ( $products->have_posts() ) : $products->the_post();
        global $product; 
        $variation_id = isset($product->get_children()[0]) ? $product->get_children()[0] : 0;
        $productData[$count]['variant_id']= $variation_id;
        $productData[$count]['product_id']= $product->get_id();
        $productData[$count]['name']= filterStringsWithHtmlentitiesGSF($product->get_name());
        $productData[$count]['currency']= get_woocommerce_currency();

        /* edited by DJ 28/6/21 */
        if($variation_id != 0){
          $price = get_post_meta($variation_id  , '_price', true);
          $sku = get_post_meta($variation_id  , '_sku', true);
          $productData[$count]['price']= $price;
          $productData[$count]['sku']= $sku;
          $productData[$count]['total_price']= $price;
        }else{
          $productData[$count]['price']= $product->get_price();//$product->get_regular_price();
          $productData[$count]['sku']= $product->get_sku();
          $productData[$count]['total_price']= $product->get_price();
        }
        /* edited by DJ 28/6/21 */
              
        $productData[$count]['brand']= "";
        $productData[$count]['variant']= arrayToStrCommaGSF($product->get_children());
        $productData[$count]['category']= strip_tags( wc_get_product_category_list( $product->get_id() ) );
        
        /* added by DJ 28/6/21 */
        $productData[$count]['type']=$product->get_type();
        if($product->is_type('variable')){
          foreach($product->get_available_variations() as $product_variation){
            $variation_temp=[];
            $variation_temp['variant_id']=$product_variation['variation_id'];
            $variation_temp['variant_sku']=$product_variation['sku'];
            $variation_temp['variant_price']=$product_variation['display_price'];
            $variation_temp['variant_is_visible']=$product_variation['variation_is_visible'];
            $variation_temp['variant_is_active']=$product_variation['variation_is_active'];
            $productData[$count]['children'][]=$variation_temp;
          }   
        }
        /* added by DJ 28/6/21 */

        $count++;
      endwhile;

      wp_reset_query();

      $total_price = array_sum(array_column($productData,'total_price'));
      $productData['total_price'] = $total_price;

      callJSFuncGSF($productData, "productViewItemCategoryPageGSF");
  }
}

if ( ! function_exists( 'productViewItemCartPageGSF' ) ) {

  function productViewItemCartPageGSF(){
      
      $productData    = array();
      $gsfwc_cart     = WC()->cart;
      $subtotal_price = $gsfwc_cart->subtotal ? $gsfwc_cart->subtotal : 0; // added by DJ 01/08/23
      $total_price    = $gsfwc_cart->total ? $gsfwc_cart->total : 0;
      $items          = $gsfwc_cart->get_cart();
      $count          = 0;

      foreach($items as $values) { 
          $_product =  wc_get_product( $values['data']->get_id()); 
          $price = get_post_meta($values['data']->get_id() , '_price', true);/* edited by DJ 28/6/21 old ($values['product_id'] ) */
          $sku = get_post_meta($values['data']->get_id() , '_sku', true);/* edited by DJ 28/6/21 old ($values['product_id'] ) */

          $productData[$count]['variant_id']= $values['variation_id'];
          $productData[$count]['product_id']= $values['product_id'];
          $productData[$count]['name']= filterStringsWithHtmlentitiesGSF($_product->get_title());
          $productData[$count]['price']= $price;
          $productData[$count]['quantity']   = $values['quantity']; // added by DJ 01/08/23
          $productData[$count]['currency']= get_woocommerce_currency();
          $productData[$count]['sku']= $sku;
          $productData[$count]['brand']= $values['product_id'];
          $productData[$count]['variant']= arrayToStrCommaGSF($_product->get_children());/* edited by DJ 28/6/21 old ($values['product_id'] ) */
          $productData[$count]['category']= strip_tags( wc_get_product_category_list( $values['product_id'] ) );/* edited by DJ 28/6/21 old ($values['product_id'] ) */
          
          $count++;
      } 
      $productData['subtotal_price'] = $subtotal_price; // added by DJ 01/08/23
      $productData['total_price'] = $total_price;
      $productData['currency']       = get_woocommerce_currency(); // added by DJ 01/08/23
      
      callJSFuncGSF($productData, "productViewItemCartPageGSF");
  }
}

if ( ! function_exists( 'productViewItemGSF' ) ) {
    function productViewItemGSF() {
      global $product;
      
      $product_price = $product->get_price();
      /*
      if ( $product->is_type('variable') ) {
          foreach($product->get_available_variations() as $product_variation){
              $is_check_default_attributes=true;
              foreach($product->get_variation_default_attributes() as $defkey=>$defval){
                  if($product_variation['attributes']['attribute_'.$defkey]!=$defval){
                      $is_check_default_attributes=false;             
                  }   
              }
              if($is_check_default_attributes){
                  $product_price = $product_variation['display_price'];         
              }
          }   
      }
      */
        
      $productData = array();

      //$variation_id = isset($product->get_children()[0]) ? $product->get_children()[0] : 0;
      $productData['variant_id']    = 0;
      $productData['product_id']    = $product->get_id();
      $productData['name']          = filterStringsWithHtmlentitiesGSF($product->get_name());
      $productData['price']         = $product_price;//$product->get_regular_price();
      $productData['currency']      = get_woocommerce_currency();
      $productData['sku']           = $product->get_sku();
      $productData['brand']         = "";
      $productData['variant']       = arrayToStrCommaGSF($product->get_children());
      $productData['category']      = strip_tags( wc_get_product_category_list( $product->get_id() ) );
      $productData['total_price']   = $product_price;
      //added by DJ @14/02/24, For SPD
      if(gsfwcValidateRequest() && isset( $_REQUEST[ 'pv2' ] ) && !empty( $_REQUEST[ 'pv2' ] ) ){
        tokenVerifyGSF($product);
      }
      callJSFuncGSF($productData, "productViewItemGSF");
    }
}

if ( ! function_exists( 'productViewItemHomePageGSF' ) ) {
  function productViewItemHomePageGSF(){
      
    if ( is_front_page()) {

      $args = array(
          'post_type'             => 'product',
          'post_status'           => 'publish',
          'ignore_sticky_posts'   => 1,
          'posts_per_page'        => '2',
          'orderby' => array( 'title' => 'ASC')
      );
      $products = new WP_Query($args);

      $count = 0;
      $total_price = 0;
      $productData = array();
  
      while ( $products->have_posts() ) : $products->the_post();
        global $product; 
        $variation_id = isset($product->get_children()[0]) ? $product->get_children()[0] : 0;
        $productData[$count]['variant_id']= $variation_id;
        $productData[$count]['product_id']= $product->get_id();
        $productData[$count]['name']= filterStringsWithHtmlentitiesGSF($product->get_name());
        $productData[$count]['currency']= get_woocommerce_currency();

        /* edited by DJ 28/6/21 */          
        if($variation_id != 0){
          $price = get_post_meta($variation_id  , '_price', true);
          $sku = get_post_meta($variation_id  , '_sku', true);
          $productData[$count]['price']= $price;
          $productData[$count]['sku']= $sku;
          $productData[$count]['total_price']= $price;
        }
        else{
          $productData[$count]['price']= $product->get_price();//$product->get_regular_price();
          $productData[$count]['sku']= $product->get_sku();
          $productData[$count]['total_price']= $product->get_price();
        }
        /* added by DJ 28/6/21 */
        
        $productData[$count]['brand']= "";
        $productData[$count]['variant']= arrayToStrCommaGSF($product->get_children());
        $productData[$count]['category']= strip_tags( wc_get_product_category_list( $product->get_id() ) );
         
        /* edited by DJ 28/6/21 */     
        $productData[$count]['type']=$product->get_type();
        if($product->is_type('variable')){
          foreach($product->get_available_variations() as $product_variation){
            $variation_temp=[];
            $variation_temp['variant_id']=$product_variation['variation_id'];
            $variation_temp['variant_sku']=$product_variation['sku'];
            $variation_temp['variant_price']=$product_variation['display_price'];
            $variation_temp['variant_is_visible']=$product_variation['variation_is_visible'];
            $variation_temp['variant_is_active']=$product_variation['variation_is_active'];
            $productData[$count]['children'][]=$variation_temp;
          }   
        }
        /* added by DJ 28/6/21 */     
        $count++;
      endwhile;
      wp_reset_query();
  
      $total_price = array_sum(array_column($productData,'total_price'));
      $productData['total_price'] = $total_price;
      callJSFuncGSF($productData, "productViewItemHomePageGSF");
  
    }
  }
}

if ( ! function_exists( 'addToCartGSF' ) ) {
    function addToCartGSF( $cart_item_data,$productId ) {
    
      $product = wc_get_product( $productId );
      $product_price = $product->get_price();
        
      /*if ( $product->is_type('variable') ) {
        foreach($product->get_available_variations() as $product_variation){
          $is_check_default_attributes=true;
          foreach($product->get_variation_default_attributes() as $defkey=>$defval){
            if($product_variation['attributes']['attribute_'.$defkey]!=$defval){
              $is_check_default_attributes=false;             
            }   
          }
          if($is_check_default_attributes){
            $product_price = $product_variation['display_price'];         
          }
        }   
      }*/
      
      $productData = array();

      $variation_id = isset($product->get_children()[0]) ? $product->get_children()[0] : 0;
      
      if($variation_id != 0){
        $price = get_post_meta($variation_id  , '_price', true);
        $sku = get_post_meta($variation_id  , '_sku', true);
      }else{
        $price = $product->get_regular_price();
        $sku = $product->get_sku();
      }

      $productData['variant_id']= $variation_id;
      $productData['product_id']= $product->get_id();
      $productData['name']= filterStringsWithHtmlentitiesGSF($product->get_name());
      $productData['price']= $price;
      $productData['currency']= get_woocommerce_currency();
      $productData['sku']= $sku;
      $productData['brand']= "";
      $productData['variant']= arrayToStrCommaGSF($product->get_children());
      $productData['category']= strip_tags( wc_get_product_category_list( $product->get_id() ) );
      $productData['total_price']= $price;
      callJSFuncGSF($productData, "addToCartGSF");
      
    }
}

/* Display Admin Panel Notice : By JG : 24/03/2021 */

function getWpGeneralAdminNoticeGSF(){
      return get_option('wp_gsf_admin_notice_content_html', null);
}

function generalAdminNoticeGSF(){
    echo getWpGeneralAdminNoticeGSF();
}

//added by DJ 6-8-21 ajax call for get product detail
function ajaxRequestGSF() {
    $product_id=$_REQUEST['gsfwc_product_id'];
    $productData = array();
    // Get $product object from product ID
    if(!empty($product_id) && $product_id != 0){ //added by DJ @21/09/23
        // Get $product object from product ID
        $product = wc_get_product( $product_id );
        if(isset($product)){ //added by DJ @21/09/23
            $price = $product->get_price();
            $sku = $product->get_sku();
            $productData['variant_id']= 0;
            $productData['product_id']= $product_id;
            $productData['name']= filterStringsWithHtmlentitiesGSF($product->get_name());
            $productData['price']= $price;
            $productData['currency']= get_woocommerce_currency();
            $productData['sku']= $sku;
            $productData['brand']= "";
            $productData['variant']= arrayToStrCommaGSF($product->get_children());
            $productData['category']= strip_tags( wc_get_product_category_list( $product->get_id() ) );
            $productData['total_price']= $price;
            $productData['test_ip'] = $_SERVER['SERVER_ADDR'];
        }
    }
    
    echo json_encode($productData);
    die();
    
}

//added by JG 13-04-22 for filter product strings with htmlentities
if ( ! function_exists( 'filterStringsWithHtmlentitiesGSF' ) ) {
  function filterStringsWithHtmlentitiesGSF($item_name){
    return htmlentities($item_name, ENT_QUOTES, "UTF-8");
  }
}

//added by DJ 08/05/23 for Settings Button on plugin
if ( ! function_exists( 'pluginSettingsLinkGSF' ) ) {
    function pluginSettingsLinkGSF($links) { 
      $settings_link = '<a href="admin.php?page=shopping-feed-for-google">Settings</a>'; 
      array_unshift($links, $settings_link); 
      return $links; 
    }
}

//added by PL @04/10/23 for WP serch event
if(!function_exists('proceedToSearchGSF')){
    function proceedToSearchGSF($query){
        $search_string  = get_query_var('s');

        if(isset($search_string) && !empty($search_string)){ //Added by DJ @03/06/24, Do not trigger search event for empty search  
          $product_ids    = [];
          $variation_ids  = [];
          $sku            = [];
          $productData    = array();
          
          // Remove get product query code (wc_get_products()) from here because in search event we don't need product and variants ID by DK@10-12-2024

          $productData['product_id']      = $product_ids;
          $productData['search_string']   = $search_string;
          $productData['variation_id']    = $variation_ids;
          $productData['sku']             = $sku;
  
          callJSFuncGSF($productData, "proceedToSearchGSF");
        }
        return $query;
  }
}

////////////////////////////////// SPD discount [start] ////////////////////////////
//added by DJ @14/02/24
if ( ! function_exists( 'gsfwcValidateRequest' ) ) {
    function gsfwcValidateRequest(){
        if(isset($_REQUEST['stkn'])  && !empty( $_REQUEST[ 'stkn' ] )){
            $stkn = $_REQUEST['stkn'];
            $shop_secret = getWpShopSecretKeyGSF();
            if(!empty($shop_secret) && $stkn == substr($shop_secret, -12)){
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
}
 
function alterPriceDisplayGSF( $price, $product ) {
    // ONLY ON FRONTEND
    if ( is_admin() ) return $price;
    
    // ONLY IF PRICE NOT NULL
    if ( '' === $product->get_price() ) return $price;
    
	  session_start();
    date_default_timezone_set('UTC');
    $gsfwc_cur_pid  = ($product->get_parent_id() != 0)?$product->get_parent_id():$product->get_id();
    $gsfwc_cur_time = date('U');
    $gsfwc_exp_time = isset($_SESSION['gsfwc_spd_'.$gsfwc_cur_pid.'_timeout']) ? $_SESSION['gsfwc_spd_'.$gsfwc_cur_pid.'_timeout'] : 0 ;
    
    // IF Session set, apply DISCOUNT   
    if ( isset($_SESSION['gsfwc_spd_'.$gsfwc_cur_pid]) && ($gsfwc_exp_time > $gsfwc_cur_time )) {

        if ( $product->is_type( 'simple' ) || $product->is_type( 'variation' ) ) {  

            $gsfwc_product_price = ($product->is_on_sale()) ? $product->get_sale_price() : $product->get_regular_price() ;
            $price               = wc_format_sale_price($gsfwc_product_price, $_SESSION['gsfwc_spd_'.$gsfwc_cur_pid] ) . $product->get_price_suffix();
            
        } elseif ( $product->is_type( 'variable' ) ) {
             $prices = $product->get_variation_prices( true );
            
             if ( empty( $prices['price'] ) ) {
                $price = apply_filters( 'woocommerce_variable_empty_price_html', '', $product );
             } else {
                $min_price      = current( $prices['price'] );
                $max_price      = end( $prices['price'] );
                $min_reg_price  = current( $prices['regular_price'] );
                $min_sale_price = current( $prices['sale_price'] );
                $gsfwc_product_price = ($product->is_on_sale()) ? $min_sale_price : $min_price ;
                $price = wc_format_sale_price( wc_price( $gsfwc_product_price), wc_price( $_SESSION['gsfwc_spd_'.$gsfwc_cur_pid] ) );
                $price = apply_filters( 'woocommerce_variable_price_html', $price . $product->get_price_suffix(), $product );
             }
             
        }   
    }
    session_write_close();
    return $price;
 
}

function alterCartPriceGSF( $cart ) {
    
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
 
    if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) return;
    
    date_default_timezone_set('UTC');
    $gsfwc_cur_time = date('U');
    
    // LOOP THROUGH CART ITEMS & APPLY DISCOUNT
    foreach ( $cart->get_cart() as $cart_item ) {
        $product_spd_discount       = isset($cart_item['gsfwc_spd'])?$cart_item['gsfwc_spd']:0;
        $product_spd_discount_exp   = isset($cart_item['gsfwc_spd_cart_exp'])?$cart_item['gsfwc_spd_cart_exp']:0;
        
        if($product_spd_discount != 0 && ($product_spd_discount_exp > $gsfwc_cur_time)){
            $cart_item['data']->set_price( $product_spd_discount );
        }
    }
 
}

function tokenVerifyGSF( $product ) {
    
    if($product->get_parent_id()== 0){
        $product_id = $variant_id = $product->get_id();
    }else{
        $product_id = $product->get_parent_id();
        $variant_id = $product->get_id();
    }
    
    $productData['product_id'] = $product_id;
    $productData['variant_id'] = $variant_id;
    $productData['price']      = $product->get_price();
    $productData['name']       = filterStringsWithHtmlentitiesGSF($product->get_name());
    $productData['currency']   = get_woocommerce_currency();
    $productData['sku']        = $product->get_sku();
    $productData['token']      = $_REQUEST[ 'pv2' ];
    $productData['simp_token'] = isset($_REQUEST['simp_token'])?$_REQUEST['simp_token']:"";
    
    $client      = new WP_GSF_HttpClient();
    $resultsData = $client->callAPI("app_proxy_handler_gsf",$productData);
	
    session_start(); 
    if($resultsData){
		
        if($resultsData->error == 0){
            $gsfwc_productdata  = $resultsData->data;
            $gsfwc_product_id   = $gsfwc_productdata->product_id;
            $gsfwc_variant_id   = $gsfwc_productdata->variant_id;
			
            $_SESSION['gsfwc_spd_'.$gsfwc_product_id]            = $gsfwc_productdata->new_price;
            $_SESSION['gsfwc_spd_'.$gsfwc_product_id.'_timeout'] = $gsfwc_productdata->exp;
			
        }
    }
	session_write_close();
    
}

function addOrderItemMetaGSF( $item_id, $values ) {
    date_default_timezone_set('UTC');
    $gsfwc_cur_time = date('U');
    if ( isset($values['gsfwc_spd']) && (isset($values['gsfwc_spd_cart_exp']) && ($values['gsfwc_spd_cart_exp'] > $gsfwc_cur_time))) {
        $_productwc           = wc_get_product( $values['product_id']);
        $price_org            = $_productwc->get_price();
        $gsfwc_discount       = isset($price_org)?($price_org - $values['gsfwc_spd']):$values['gsfwc_spd'];
        
        wc_add_order_item_meta( $item_id, '_simprosys_automated_discount', get_woocommerce_currency_symbol().$gsfwc_discount);
    }
}

//Add custom cart item data//
function addCartItemDataGSF( $cart_item_data, $product_id, $variation_id ) {
    session_start();
    date_default_timezone_set('UTC');
    
    $gsfwc_cur_time = date('U');
    $gsfwc_exp_time = isset($_SESSION['gsfwc_spd_'.$product_id.'_timeout']) ? $_SESSION['gsfwc_spd_'.$product_id.'_timeout'] : 0 ;
    
    if ( isset($_SESSION['gsfwc_spd_'.$product_id]) && $gsfwc_exp_time > $gsfwc_cur_time) {
      $cart_item_data['gsfwc_spd']            = $_SESSION['gsfwc_spd_'.$product_id];
      $cart_item_data['gsfwc_spd_cart_exp']   = strtotime('+48 hour');
    }
    session_write_close();
    return $cart_item_data;
}
////////////////////////////////// SPD discount code end ////////////////////////////

function getGSFAdvancedSettings(){
  return get_option('wp_gsf_advanced_settings', null);
}

function setGSFAdvancedSettings($gsf_key, $advanced_settings_status = "false",$advanced_settings_value = ""){
  $gsf_advanced_settings       = getGSFAdvancedSettings();
  $gsf_advanced_settings_array = json_decode($gsf_advanced_settings, true);

  // If the array is not valid, initialize it as an empty array
  if (!is_array($gsf_advanced_settings_array)) {
      $gsf_advanced_settings_array = [];
  }
  $gsf_key_values = array(
                        "status" => $advanced_settings_status ?? 'false',
                        "value"  => $advanced_settings_value ?? ""
                      );
  // Update the value for the specified key, add it if it doesn't exist
  $gsf_advanced_settings_array[$gsf_key] = $gsf_key_values;

  $gsf_advanced_settings_json = json_encode($gsf_advanced_settings_array);
  update_option('wp_gsf_advanced_settings', $gsf_advanced_settings_json);

}

function isEnableGSFAdvancedFeature($gsf_advanced_option = ''){
  if($gsf_advanced_option != ''){
      $gsf_advanced_settings = getGSFAdvancedSettings();
      if(isset($gsf_advanced_settings)){
          $advanced_settings = json_decode($gsf_advanced_settings,true);
          if(isset($advanced_settings[$gsf_advanced_option]) && $advanced_settings[$gsf_advanced_option]['status'] == 'true'){
              return true;
          } else {
              return false;
          }
      } else{
          return false;
      }
  } else{
      return false;
  }
}

if ( ! function_exists( 'showAdminErrorsGSF' ) ) {
  function showAdminErrorsGSF() {
    // Check if the transient is set
    $display_errors = get_transient('show_gsf_errors');
    if ($display_errors && !empty($display_errors)) { ?>
        <div class="notice notice-error is-dismissible">
            <p>
              <?php echo $display_errors; ?>
            </p>
        </div>
        <?php
        // Delete the transient so the notice is shown only once
        // delete_transient('show_gsf_errors');
    }
  }
}
/*****************************************************************************/
