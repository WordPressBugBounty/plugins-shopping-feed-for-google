<?php 
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>

<form action="admin.php?page=shopping-feed-for-google" method="post" target="_blank">
 <?php  wp_nonce_field('wp_gsf_app_redirect_app_button_clicked'); ?>
 <input type="hidden" value="true" name="wp_gsf_app_redirect" />
  <?php 
    //wp_remote_retrieve_body
    $results = getRemoteDataContentHtmlGSF();
    if( ! empty($results)){
      if(isCheckDefaultPermalinkGSF()){
        echo stripslashes($results->gsf_permalink_structure_message);
      } else {
        echo stripslashes($results->gsf_plugin_body_html);
      }
    } else {
        setErrorMessageGSF();
    }
 ?>
</form>