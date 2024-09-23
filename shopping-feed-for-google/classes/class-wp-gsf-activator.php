<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WP_GSF_Activator {

	public static function activate() {
		if (isDependencyAvailableGSF()) {
				registerStoreGSF();
		        add_action( 'activated_plugin', 'pluginActivationRedirectGSF' );
		}
	}
   	public static function deactivate() {
		pluginDeactivateGSF();
	}
}
