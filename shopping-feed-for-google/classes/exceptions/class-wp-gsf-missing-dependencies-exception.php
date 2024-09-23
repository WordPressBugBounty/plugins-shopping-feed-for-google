<?php
// my-plugin-name/classes/exceptions/Missing_Dependencies_Exception.php
class WP_GSF_Missing_Dependencies_Exception extends WP_GSF_Exception {

	/** @var string[] */
	private $missing_plugin_names;

	/**
	 * @param string[] $missing_plugin_names Names of the plugins that our plugin depends on,
	 *                                       that were found to be inactive.
	 */
	public function __construct( $missing_plugin_names ) {
		$this->missing_plugin_names = $missing_plugin_names;
	}

	/**
	 * @return string[]
	 */
	public function getMissingPluginNamesGSF() {
		return $this->missing_plugin_names;
	}

}