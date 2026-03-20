<?php
/**
 * The core plugin class.
 *
 */
require_once plugin_dir_path(dirname(__FILE__)) . 'classes/setup.php';

class apxpc_Plugin extends apxpc_Setup {
	public $config;
	
	public function __construct($config) {
		$this->config = $config;
		add_action('init', array(&$this, 'init'));
	}

	public function init() {

		if(is_admin()) {
			// The class responsible for defining all actions that occur in the admin area.
			require_once plugin_dir_path(dirname(__FILE__)) . 'classes/backend.php';
			$plugin_backend = new apxpc_Backend($this);
			
			// The class responsible for order-specific functionality.
			require_once plugin_dir_path(dirname(__FILE__)) . 'classes/orders.php';
			$plugin_orders = new apxpc_Orders($this);
		}
	}

}