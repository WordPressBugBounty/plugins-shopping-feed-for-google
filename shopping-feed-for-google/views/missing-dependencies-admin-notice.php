<?php
// my-plugin-name/views/missing-dependencies-admin-notice.php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/** @var string[] $missing_plugin_names */
$link = admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' );
?>

<div class="error notice">
    <p>
        <strong>Error:</strong>
        The <em><?php echo WP_GSF_PLUGIN_NAME;?></em> can not activate because WooCommerce is not installed or active. Please activate WooCommerce if already installed or <a href='<?php echo $link; ?>'>Install WooCommerce!</a>
    </p>
</div>