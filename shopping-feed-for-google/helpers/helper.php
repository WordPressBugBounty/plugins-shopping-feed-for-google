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

    $dismissed = [];
    if(isset($current_user_data->ID)){
      $dismissed = get_user_meta( $current_user_data->ID, '_gsf_dismissed_notices', true );
    }
     
    $current_users['id']           = isset($current_user_data->ID)?$current_user_data->ID : 1;
    $current_users['user_email']   = isset($current_user_data->user_email)?$current_user_data->user_email : "";
    $current_users['display_name'] = isset($current_user_data->display_name)?$current_user_data->display_name : ""; 
    $current_users['dismissed_notification'] = $dismissed;
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
    $filename_icon = sanitize_file_name('WC-GSF-icon.svg'); //updated by DJ @04/06/24
    $page_title = WP_GSF_PLUGIN_NAME;
    $menu_title = WP_GSF_PLUGIN_MENU_NAME;
    $capability = 'manage_options';
    $menu_slug = 'shopping-feed-for-google';
    $function = 'menuCallbackGSF';
    $position  = 55.8;
    $icon_url  = plugin_dir_url( __DIR__ ) . 'assets/img/'.$filename_icon;
    $gsf_wc_icon_data = 'data:image/svg+xml;base64,'. base64_encode( file_get_contents( $icon_url ) );
    add_menu_page(  $page_title,  $menu_title,  $capability,  $menu_slug,  $function  ,$gsf_wc_icon_data ,$position );
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
    $feedback_data = get_transient('gsf_deactivation_feedback') ?? '';
    $shopData = array(
     'is_activated' => 0,
     'feedback_data' => $feedback_data
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
    function callJSFuncGSF($gsfProductData, $funName){
        
      $gsfProductData = str_replace("'", "\'", json_encode($gsfProductData));
        
        add_action( 'wp_footer', function() use ($gsfProductData,$funName) { 
            if ($funName == 'addToCartGSF') { ?>
                <script> 
                  var product_add_cart_data = '<?php echo $gsfProductData; ?>'; 
                  document.addEventListener('DOMContentLoaded', function() { <?php echo $funName; ?>(product_add_cart_data) }, false);
                </script>
            <?php }else if ($funName == 'proceedToSearchGSF') { ?>
                <script>
                    var product_search_data = '<?php echo $gsfProductData; ?>';
                    document.addEventListener('DOMContentLoaded', function () { <?php echo $funName; ?>(product_search_data) }, false);
                </script>
            <?php } else { ?>
                <script> var product_data = '<?php echo $gsfProductData; ?>'; document.addEventListener('DOMContentLoaded', function() { <?php echo $funName; ?>(product_data) }, false);
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

        $gsfProductOrderData = array();  
        // Loop through order items
        $count = 0;
        foreach ( $order->get_items() as $item_id => $item ) {
          // Get the product object
          $product  = $item->get_product();
          $quantity = $item->get_quantity(); 
          // global $product; 
          //$variation_id = isset($product->get_children()[0]) ? $product->get_children()[0] : 0;
          //$gsfProductOrderData[$count]['variant_id']= $variation_id;
          //$gsfProductOrderData[$count]['product_id']= $product->get_id();
          
          /* edited by DJ 28/6/21 */
          if($product->get_parent_id()==0){
            $gsfProductOrderData[$count]['product_id']	= $item->get_product_id();
            $gsfProductOrderData[$count]['variant_id']	= 0;
          }else{
            $gsfProductOrderData[$count]['product_id']	= $item->get_product_id();
            $gsfProductOrderData[$count]['variant_id']	= $product->get_id();
          }
          /* edited by DJ 28/6/21 */
          
          $gsfProductOrderData[$count]['name']        = filterStringsWithHtmlentitiesGSF($product->get_title());
          $gsfProductOrderData[$count]['price']       = $product->get_price();//$product->get_regular_price();
          $gsfProductOrderData[$count]['quantity']    = $quantity ?? 1;
          $gsfProductOrderData[$count]['currency']    = get_woocommerce_currency();
          $gsfProductOrderData[$count]['sku']         = $product->get_sku();
          $gsfProductOrderData[$count]['brand']       = gsf_get_product_brand($item->get_product_id());
          $gsfProductOrderData[$count]['variant']     = arrayToStrCommaGSF($product->get_children());
          $gsfProductOrderData[$count]['variant_title']= !empty($product->get_attributes()) ? gsf_get_variant_title($product->get_attributes()) : '';
          $gsfProductOrderData[$count]['category']    = gsf_get_first_category(strip_tags( wc_get_product_category_list( $gsfProductOrderData[$count]['product_id'] ) ));
          $gsfProductOrderData[$count]['total_price']	= $product->get_price();
          $gsfProductOrderData[$count]['index']	= $count;
          $count++;
        }
        $total_price    = $order->get_total()? $order->get_total() : 0;
        $subtotal_price = $order->get_subtotal()? $order->get_subtotal() : 0; // added by DJ 01/08/23
        $total_tax      = $order->get_total_tax()? $order->get_total_tax() : 0;//added by DJ @29/07/24
        $total_shipping = $order->get_shipping_total()?$order->get_shipping_total(): 0;//added by DJ @29/07/24
        
        $gsfProductOrderData['order_id']           = $order_id;
        $gsfProductOrderData['subtotal_price']     = $subtotal_price; // added by DJ 01/08/23
        $gsfProductOrderData['total_price']        = $total_price;
        $gsfProductOrderData['total_tax']          = $total_tax;
        $gsfProductOrderData['total_shipping']     = $total_shipping;
        $gsfProductOrderData['discount']           = $order->get_discount_total();//$order_id;
        $gsfProductOrderData['currency']           = get_woocommerce_currency();
        $gsfProductOrderData['order_created_date'] = ($order->get_date_created() != '') ?  $order->get_date_created() : ''; // added by PL @14/09/23 for GCR 

        // added by DJ @15/06/22 for enhanced conversion tracking 
        // Get the Customer billing email
        $gsfProductOrderData['billing_email']  = ($order->get_billing_email() != '')?$order->get_billing_email():'';

        // Get the Customer billing phone
        $gsfProductOrderData['billing_phone']  = ($order->get_billing_phone() != '')?$order->get_billing_phone():'';

        // Customer billing information details
        $gsfProductOrderData['billing_first_name'] = ($order->get_billing_first_name() != '')?$order->get_billing_first_name():'';
        $gsfProductOrderData['billing_last_name']  = ($order->get_billing_last_name() != '')?$order->get_billing_last_name():'';
        $gsfProductOrderData['billing_company']    = ($order->get_billing_company() != '')?$order->get_billing_company():'';
        $gsfProductOrderData['billing_address_1']  = ($order->get_billing_address_1() != '')?$order->get_billing_address_1():'';
        $gsfProductOrderData['billing_address_2']  = ($order->get_billing_address_2() != '')?$order->get_billing_address_2():'';
        $gsfProductOrderData['billing_city']       = ($order->get_billing_city() != '')?$order->get_billing_city():'';
        $gsfProductOrderData['billing_state']      = ($order->get_billing_state() != '')?$order->get_billing_state():'';
        $gsfProductOrderData['billing_postcode']   = ($order->get_billing_postcode() != '')?$order->get_billing_postcode():'';
        $gsfProductOrderData['billing_country']    = ($order->get_billing_country() != '')?$order->get_billing_country():'';
        $gsfProductOrderData['order_key']          = ($order->get_order_key() != '')?$order->get_order_key():'';
        // added by DJ @15/06/22 for enhanced conversion tracking 
        
        callJSFuncGSF($gsfProductOrderData, "proceedToPurchaseGSF");
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
    $gsfProductCheckoutData    = array();

    foreach($items as $values) { 
      $_product =  wc_get_product( $values['data']->get_id()); 
      $price    = get_post_meta($values['data']->get_id() , '_price', true);/* edited by DJ 28/6/21 old ($values['product_id'] ) */
      $sku      = get_post_meta($values['data']->get_id() , '_sku', true);/* edited by DJ 28/6/21 old ($values['product_id'] ) */

      $gsfProductCheckoutData[$count]['variant_id']= $values['variation_id'];
      $gsfProductCheckoutData[$count]['product_id']= $values['product_id'];
      $gsfProductCheckoutData[$count]['name']= filterStringsWithHtmlentitiesGSF($_product->get_title());
      $gsfProductCheckoutData[$count]['price']= $price;
      $gsfProductCheckoutData[$count]['quantity']= $values['quantity']; // added by DJ 01/08/23
      $gsfProductCheckoutData[$count]['currency']= get_woocommerce_currency();
      $gsfProductCheckoutData[$count]['sku']= $sku;
      $gsfProductCheckoutData[$count]['brand']= gsf_get_product_brand($values['data']->get_id());/* edited by DJ 28/6/21 old ($values['product_id'] ) */
      $gsfProductCheckoutData[$count]['variant']= arrayToStrCommaGSF($_product->get_children());/* edited by DJ 28/6/21 old ($values['product_id'] ) */
      $gsfProductCheckoutData[$count]['variant_title']= !empty($values['variation']) ? gsf_get_variant_title($values['variation']) : '';
      $gsfProductCheckoutData[$count]['category']= gsf_get_first_category(strip_tags(wc_get_product_category_list($values['product_id']) ));/* edited by DJ 28/6/21 old ($values['product_id'] ) */
      
      $gsfProductCheckoutData[$count]['index']= $count;
      $count++;
    } 
    $gsfProductCheckoutData['subtotal_price']  = $subtotal_price; // added by DJ 01/08/23
    $gsfProductCheckoutData['total_price'] = $total_price;
    $gsfProductCheckoutData['currency']        = get_woocommerce_currency(); // added by DJ 01/08/23
    callJSFuncGSF($gsfProductCheckoutData, "proceedToCheckoutGSF");
  }
}

if ( ! function_exists( 'productViewItemCategoryPageGSF' ) ) {
  function productViewItemCategoryPageGSF(){
      
      global $product, $wp_query;

        // Use global query for Shop & Category page
      if(isset( $wp_query->query_vars['wc_query'] ) && $wp_query->query_vars['wc_query'] === 'product_query' ){
        $products = $wp_query;
      }else{
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
      }
      $gsfProductCategoryData = array();
      $count = 0;
      $total_price = 0;

      while ( $products->have_posts() ) : $products->the_post();
        if ( $count >= 5 ) {
            continue; // Let the loop finish to not break $wp_query
        }
        global $product; 
        $variant_title = '';
        $variation_id = isset($product->get_children()[0]) ? $product->get_children()[0] : 0;
        $gsfProductCategoryData[$count]['variant_id']= $variation_id;
        $gsfProductCategoryData[$count]['product_id']= $product->get_id();
        $gsfProductCategoryData[$count]['name']= filterStringsWithHtmlentitiesGSF($product->get_name());
        $gsfProductCategoryData[$count]['currency']= get_woocommerce_currency();

        /* edited by DJ 28/6/21 */
        if($variation_id != 0){
          $price = get_post_meta($variation_id  , '_price', true);
          $sku = get_post_meta($variation_id  , '_sku', true);
          $gsfProductCategoryData[$count]['price']= $price;
          $gsfProductCategoryData[$count]['sku']= $sku;
          $gsfProductCategoryData[$count]['total_price']= $price;
        }else{
          $gsfProductCategoryData[$count]['price']= $product->get_price();//$product->get_regular_price();
          $gsfProductCategoryData[$count]['sku']= $product->get_sku();
          $gsfProductCategoryData[$count]['total_price']= $product->get_price();
        }
        /* edited by DJ 28/6/21 */
              
        $gsfProductCategoryData[$count]['brand']= gsf_get_product_brand($product->get_id());
        $gsfProductCategoryData[$count]['variant']= arrayToStrCommaGSF($product->get_children());
        $gsfProductCategoryData[$count]['category']= gsf_get_first_category(strip_tags( wc_get_product_category_list( $product->get_id() ) ));
        
        /* added by DJ 28/6/21 */
        $gsfProductCategoryData[$count]['type']=$product->get_type();
        if($product->is_type('variable')){
          foreach($product->get_available_variations() as $product_variation){
            $variation_temp=[];
            $variation_temp['variant_id']=$product_variation['variation_id'];
            $variation_temp['variant_sku']=$product_variation['sku'];
            $variation_temp['variant_price']=$product_variation['display_price'];
            $variation_temp['variant_is_visible']=$product_variation['variation_is_visible'];
            $variation_temp['variant_is_active']=$product_variation['variation_is_active'];
            $gsfProductCategoryData[$count]['children'][]=$variation_temp;
          }   
          if ( ! empty( $available_variations ) ) {
            $first_variation = $available_variations[0];
            $variant_title = !empty($first_variation['attributes']) ? gsf_get_variant_title($first_variation['attributes']) : '';
          }
        }
        $gsfProductCategoryData[$count]['variant_title']= $variant_title;
        /* added by DJ 28/6/21 */
        
        $gsfProductCategoryData[$count]['index']=$count;
        $gsfProductCategoryData[$count]['quantity']=1;
        $count++;
      endwhile;

      wp_reset_query();

      $total_price = array_sum(array_column($gsfProductCategoryData,'total_price'));
      $gsfProductCategoryData['total_price'] = $total_price;
      
      $list_id = $list_name = '';
      if ( is_product_category() ) {
        $page_object = get_queried_object();
        $list_id = $page_object->term_id;
        $list_name = $page_object->name;
      }else if ( is_shop() ) {
        $shop_page_id = wc_get_page_id( 'shop' );
        $list_id = $shop_page_id;
        $list_name = get_the_title($shop_page_id);
      }

      $gsfProductCategoryData['list_id'] = $list_id;
      $gsfProductCategoryData['list_name'] = $list_name;

      callJSFuncGSF($gsfProductCategoryData, "productViewItemCategoryPageGSF");
  }
}

if ( ! function_exists( 'productViewItemCartPageGSF' ) ) {

  function productViewItemCartPageGSF(){
      
      $gsfProductCartData    = array();
      $gsfwc_cart     = WC()->cart;
      $subtotal_price = $gsfwc_cart->subtotal ? $gsfwc_cart->subtotal : 0; // added by DJ 01/08/23
      $total_price    = $gsfwc_cart->total ? $gsfwc_cart->total : 0;
      $items          = $gsfwc_cart->get_cart();
      $count          = 0;

      foreach($items as $values) { 
          $_product =  wc_get_product( $values['data']->get_id()); 
          $price = get_post_meta($values['data']->get_id() , '_price', true);/* edited by DJ 28/6/21 old ($values['product_id'] ) */
          $sku = get_post_meta($values['data']->get_id() , '_sku', true);/* edited by DJ 28/6/21 old ($values['product_id'] ) */

          $gsfProductCartData[$count]['variant_id']= $values['variation_id'];
          $gsfProductCartData[$count]['product_id']= $values['product_id'];
          $gsfProductCartData[$count]['name']= filterStringsWithHtmlentitiesGSF($_product->get_title());
          $gsfProductCartData[$count]['price']= $price;
          $gsfProductCartData[$count]['quantity']   = $values['quantity']; // added by DJ 01/08/23
          $gsfProductCartData[$count]['currency']= get_woocommerce_currency();
          $gsfProductCartData[$count]['sku']= $sku;
          $gsfProductCartData[$count]['brand']= gsf_get_product_brand($values['data']->get_id());
          $gsfProductCartData[$count]['variant']= arrayToStrCommaGSF($_product->get_children());/* edited by DJ 28/6/21 old ($values['product_id'] ) */
          $gsfProductCartData[$count]['variant_title']= !empty($values['variation']) ? gsf_get_variant_title($values['variation']) : '';
          $gsfProductCartData[$count]['category']= gsf_get_first_category(strip_tags( wc_get_product_category_list( $values['product_id'] ) ));/* edited by DJ 28/6/21 old ($values['product_id'] ) */
          
          $gsfProductCartData[$count]['index']= $count;
          $count++;
      } 
      $gsfProductCartData['subtotal_price'] = $subtotal_price; // added by DJ 01/08/23
      $gsfProductCartData['total_price'] = $total_price;
      $gsfProductCartData['currency']       = get_woocommerce_currency(); // added by DJ 01/08/23
      
      callJSFuncGSF($gsfProductCartData, "productViewItemCartPageGSF");
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
        
      $gsfProductDetailData = array();

      //$variation_id = isset($product->get_children()[0]) ? $product->get_children()[0] : 0;
      $gsfProductDetailData['variant_id']    = 0;
      $gsfProductDetailData['product_id']    = $product->get_id();
      $gsfProductDetailData['name']          = filterStringsWithHtmlentitiesGSF($product->get_title());
      $gsfProductDetailData['price']         = $product_price;//$product->get_regular_price();
      $gsfProductDetailData['currency']      = get_woocommerce_currency();
      $gsfProductDetailData['sku']           = $product->get_sku();
      $gsfProductDetailData['brand']         = gsf_get_product_brand($product->get_id());
      $gsfProductDetailData['variant']       = arrayToStrCommaGSF($product->get_children());
      $gsfProductDetailData['variant_title'] = '';
      $gsfProductDetailData['category']      = gsf_get_first_category(strip_tags( wc_get_product_category_list( $product->get_id() ) ));
      $gsfProductDetailData['total_price']   = $product_price;
      $gsfProductDetailData['index']   = 0;
      $gsfProductDetailData['quantity']   = 1;
      //added by DJ @14/02/24, For SPD
      if(gsfwcValidateRequest() && isset( $_REQUEST[ 'pv2' ] ) && !empty( $_REQUEST[ 'pv2' ] ) ){
        tokenVerifyGSF($product);
      }
      callJSFuncGSF($gsfProductDetailData, "productViewItemGSF");
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
      $gsfProductHomeData = array();
  
      while ( $products->have_posts() ) : $products->the_post();
        global $product; 
        $variant_title = '';
        $variation_id = isset($product->get_children()[0]) ? $product->get_children()[0] : 0;
        $gsfProductHomeData[$count]['variant_id']= $variation_id;
        $gsfProductHomeData[$count]['product_id']= $product->get_id();
        $gsfProductHomeData[$count]['name']= filterStringsWithHtmlentitiesGSF($product->get_name());
        $gsfProductHomeData[$count]['currency']= get_woocommerce_currency();

        /* edited by DJ 28/6/21 */          
        if($variation_id != 0){
          $price = get_post_meta($variation_id  , '_price', true);
          $sku = get_post_meta($variation_id  , '_sku', true);
          $gsfProductHomeData[$count]['price']= $price;
          $gsfProductHomeData[$count]['sku']= $sku;
          $gsfProductHomeData[$count]['total_price']= $price;
        }
        else{
          $gsfProductHomeData[$count]['price']= $product->get_price();//$product->get_regular_price();
          $gsfProductHomeData[$count]['sku']= $product->get_sku();
          $gsfProductHomeData[$count]['total_price']= $product->get_price();
        }
        /* added by DJ 28/6/21 */
        
        $gsfProductHomeData[$count]['brand']= gsf_get_product_brand($product->get_id());
        $gsfProductHomeData[$count]['variant']= arrayToStrCommaGSF($product->get_children());
        $gsfProductHomeData[$count]['category']= gsf_get_first_category(strip_tags( wc_get_product_category_list( $product->get_id() ) ));
         
        /* edited by DJ 28/6/21 */     
        $gsfProductHomeData[$count]['type']=$product->get_type();
        if($product->is_type('variable')){
          $available_variations = $product->get_available_variations();
          foreach($available_variations as $product_variation){
            $variation_temp=[];
            $variation_temp['variant_id']=$product_variation['variation_id'];
            $variation_temp['variant_sku']=$product_variation['sku'];
            $variation_temp['variant_price']=$product_variation['display_price'];
            $variation_temp['variant_is_visible']=$product_variation['variation_is_visible'];
            $variation_temp['variant_is_active']=$product_variation['variation_is_active'];
            $gsfProductHomeData[$count]['children'][]=$variation_temp;
          }   
          if ( ! empty( $available_variations ) ) {
            $first_variation = $available_variations[0];
            $variant_title = !empty($first_variation['attributes']) ? gsf_get_variant_title($first_variation['attributes']) : '';
          }
        }
        $gsfProductHomeData[$count]['variant_title']= $variant_title;
        /* added by DJ 28/6/21 */   
        
        $gsfProductHomeData[$count]['index'] = $count;
        $gsfProductHomeData[$count]['quantity'] = 1;
        $count++;
      endwhile;
      wp_reset_query();
  
      $total_price = array_sum(array_column($gsfProductHomeData,'total_price'));
      $gsfProductHomeData['total_price'] = $total_price;
      callJSFuncGSF($gsfProductHomeData, "productViewItemHomePageGSF");
  
    }
  }
}

if ( ! function_exists( 'addToCartGSF' ) ) {
    function addToCartGSF( $cart_item_data,$productId,$quantity,$variation_id, $variation ) {
    
      $product = wc_get_product( $productId );
      $cart_data = WC()->cart->get_cart();
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
      
      $gsfProductAddCartData = array();

      // $variation_id = isset($product->get_children()[0]) ? $product->get_children()[0] : 0;
      
      if($variation_id != 0){
        $price = get_post_meta($variation_id  , '_price', true);
        $sku = get_post_meta($variation_id  , '_sku', true);
      }else{
        $price = $product->get_price(); // $product->get_regular_price();
        $sku = $product->get_sku();
      }

      $gsfProductAddCartData['variant_id']= $variation_id;
      $gsfProductAddCartData['product_id']= $product->get_id();
      $gsfProductAddCartData['name']= filterStringsWithHtmlentitiesGSF($product->get_name());
      $gsfProductAddCartData['price']= $price;
      $gsfProductAddCartData['currency']= get_woocommerce_currency();
      $gsfProductAddCartData['sku']= $sku;
      $gsfProductAddCartData['brand']= gsf_get_product_brand($product->get_id());
      $gsfProductAddCartData['variant']= arrayToStrCommaGSF($product->get_children());
      $gsfProductAddCartData['variant_title']= !empty($variation) ? gsf_get_variant_title($variation) : '';
      $gsfProductAddCartData['category']= gsf_get_first_category(strip_tags( wc_get_product_category_list( $product->get_id() ) ));
      $gsfProductAddCartData['total_price']= $price;
      $gsfProductAddCartData['index']= (is_array($cart_data)) ? count($cart_data) : 0;
      $gsfProductAddCartData['quantity']= $quantity;
      callJSFuncGSF($gsfProductAddCartData, "addToCartGSF");
      
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
    $gsfProductAjaxData = array();
    // Get $product object from product ID
    if(!empty($product_id) && $product_id != 0){ //added by DJ @21/09/23
        $cart_data = WC()->cart->get_cart();
        // Get $product object from product ID
        $product = wc_get_product( $product_id );
        if(isset($product)){ //added by DJ @21/09/23
          // Check is variant or simple product
          if($product->get_parent_id()==0){
            $gsfProductAjaxData['variant_id']= 0;
            $gsfProductAjaxData['product_id']= $product_id;
            $main_product_id = $product_id;
          }else{
            $gsfProductAjaxData['variant_id']= $product_id;
            $gsfProductAjaxData['product_id']= $product->get_parent_id();
            $main_product_id = $product->get_parent_id();
          }
            $price = $product->get_price();
            $sku = $product->get_sku();
            $gsfProductAjaxData['variant_id']= 0;
            $gsfProductAjaxData['product_id']= $product_id;
            $gsfProductAjaxData['name']= filterStringsWithHtmlentitiesGSF($product->get_name());
            $gsfProductAjaxData['price']= $price;
            $gsfProductAjaxData['currency']= get_woocommerce_currency();
            $gsfProductAjaxData['sku']= $sku;
            $gsfProductAjaxData['brand']= gsf_get_product_brand($main_product_id);
            $gsfProductAjaxData['variant']= arrayToStrCommaGSF($product->get_children());
            $gsfProductAjaxData['category']= gsf_get_first_category(strip_tags( wc_get_product_category_list( $main_product_id ) ));
            $gsfProductAjaxData['total_price']= $price;
            $gsfProductAjaxData['test_ip'] = $_SERVER['SERVER_ADDR'];
            $gsfProductAjaxData['index'] = (is_array($cart_data)) ? count($cart_data) : 0;
            $gsfProductAjaxData['quantity'] = 1;
        }
    }
    
    echo json_encode($gsfProductAjaxData);
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
          $gsfProductSearchData    = array();
          
          // Remove get product query code (wc_get_products()) from here because in search event we don't need product and variants ID by DK@10-12-2024

          $gsfProductSearchData['product_id']      = $product_ids;
          $gsfProductSearchData['search_string']   = $search_string;
          $gsfProductSearchData['variation_id']    = $variation_ids;
          $gsfProductSearchData['sku']             = $sku;
  
          callJSFuncGSF($gsfProductSearchData, "proceedToSearchGSF");
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

if (! function_exists('gsf_woocommerce_block_do_actions')) {
  function gsf_woocommerce_block_do_actions($block_content, $block){
      if (is_admin()) {
          return $block_content;
      }

      $blocks = array(
          'woocommerce/cart',
          'woocommerce/checkout',
      );
      if (in_array($block['blockName'], $blocks)) {
          ob_start();
          do_action('gsf_before_' . $block['blockName']);
          echo $block_content;
          // do_action( 'gsf_after_' . $block['blockName'] );
          $block_content = ob_get_contents();
          ob_end_clean();
      }
      return $block_content;
  }
}

if (! function_exists('gsf_get_product_brand')) {  
  /**
   * Method gsf_get_product_brand Retrive WC Product default brand from the list of brands
   *
   * @param $product_id 
   *
   * @return string
   */
  function gsf_get_product_brand($product_id){
    $brands = wp_get_post_terms( $product_id, 'product_brand' );
    if ( !is_wp_error( $brands ) && !empty( $brands ) ) {
        return $brands[0]->name;
    }
    return '';
  }
}

if (! function_exists('gsf_get_first_category')) {  
  /**
   * Method gsf_get_first_category Retrive first category from the list of categories
   *
   * @param $categories
   *
   * @return string
   */
  function gsf_get_first_category($categories){
    $categories_data = explode(",",$categories);
    return !empty($categories_data) ? trim($categories_data[0]) : $categories;
  }
}

if (! function_exists('gsf_get_variant_title')) {  
  /**
   * Method gsf_get_variant_title
   *
   * @param $variations [Variation attributes array]
   *
   */
  function gsf_get_variant_title($variations){
    if(empty($variations)){
      return '';
    }
    return rtrim(implode(', ', array_map('ucfirst', array_values($variations))),", ");
  }
}

if (! function_exists('saveOrderMetaGSF')) {  
  /**
   * Method saveOrderMetaGSF to store data in order meta and retrive in REST API
   */
  function saveOrderMetaGSF($order_id){
    // Store FBP and FBC in order meta for Purchase FB event
    if(isset($_COOKIE['_fbp']) && !empty($_COOKIE['_fbp'])){
      update_post_meta($order_id, '_wp_gsf_fbp', sanitize_text_field($_COOKIE['_fbp']));
    }
    if(isset($_COOKIE['_fbc']) && !empty($_COOKIE['_fbc'])){
      update_post_meta($order_id, '_wp_gsf_fbc', sanitize_text_field($_COOKIE['_fbc']));
    }

    // Store consent method and user consent in order meta for GA4 event
    if(isset($_COOKIE['cmplz_marketing']) && !empty($_COOKIE['cmplz_marketing'])){
      update_post_meta($order_id, '_wp_gsf_consent_method', 'cmplz');
      update_post_meta($order_id, '_wp_gsf_user_consent', sanitize_text_field($_COOKIE['cmplz_marketing']));
    } else if(isset($_COOKIE['cookieyes-consent']) && !empty($_COOKIE['cookieyes-consent'])){
      update_post_meta($order_id, '_wp_gsf_consent_method', 'cookieyes');
      update_post_meta($order_id, '_wp_gsf_user_consent', sanitize_text_field($_COOKIE['cookieyes-consent']));
    } 

  }
}

/*****************************************************************************/
