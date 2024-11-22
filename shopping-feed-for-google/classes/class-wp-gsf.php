<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WP_GSF_Controller {


	protected $loader;
	protected $plugin_name;
	protected $version;
	protected $dependency_checker;

	public function __construct() {
		if ( defined( 'WP_GSF_PLUGIN_VERSION' ) ) {
			$this->version = WP_GSF_PLUGIN_VERSION;
		} else {
			$this->version = '1.0';
		}
		$this->plugin_name = 'shopping-feed-for-google';

	}

	private function loadDependenciesGSF() {
	    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'helpers/helper.php';
	    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'classes/class-wp-gsf-http-client.php';
	    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'classes/class-wp-gsf-activator.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'classes/class-wp-gsf-loader.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'classes/class-wp-gsf-rest-controller.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'classes/class-wp-gsf-admin-notification.php';
		$this->loader = new WP_GSF_Loader();
		$Custom_Rest = new WP_GSF_Rest_Controller();
		$Custom_Rest->callHooksGSF();
	}


	public function runGSF() {
		$this->loadClassesGSF();
		$this->createInstancesGSF();
		$this->loadDependenciesGSF();
		try {
			$this->dependency_checker->checkGSF();
		} catch ( WP_GSF_Missing_Dependencies_Exception $e ) {
			$this->reportMissingDependenciesGSF( $e->getMissingPluginNamesGSF() );
			return;
		}
		$this->loader->run();
	}

	public function getPluginNameGSF() {
		return $this->plugin_name;
	}

	public function getLoaderGSF() {
		return $this->loader;
	}

	public function getVersionGSF() {
		return $this->version;
	}


	private function loadClassesGSF() {
		// Exceptions
		require_once dirname( __DIR__ ) . '/classes/exceptions/class-wp-gsf-exception.php';
		require_once dirname( __DIR__ ) . '/classes/exceptions/class-wp-gsf-missing-dependencies-exception.php';

		// Dependency checker
		require_once dirname( __DIR__ ) . '/classes/class-wp-gsf-dependency-checker.php';
		require_once dirname( __DIR__ ) . '/classes/class-wp-gsf-missing-dependency-reporter.php';
	}

	private function createInstancesGSF() {
		$this->dependency_checker = new WP_GSF_Dependency_Checker();
	}
	
	/**
	 * @param string[] $missing_plugin_names
	 */
	private function reportMissingDependenciesGSF( $missing_plugin_names ) {
		$missing_dependency_reporter = new WP_GSF_Missing_Dependency_Reporter( $missing_plugin_names );
		$missing_dependency_reporter->bindToAdminHooksGSF();
	}

}
