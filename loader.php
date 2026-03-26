<?php
/**
 * Plugin Name:       XP Courier print vouchers
 * Plugin URI:        
 * Description:       Create and print XP Courier vouchers from WooCommerce orders.
 * Version:           1.0.0
 * Author:            Akis Paneras
 * Author URI:        
 * Text Domain:       axc
 * Domain Path:       /languages
 *
 *
 *
 */

// Prevent to access the file from outside of WordPress
if(!defined('ABSPATH')) {
	exit;
}
if(!function_exists('wp_get_current_user')) {
	include(ABSPATH . 'wp-includes/pluggable.php');
}

class apxpc_Loader {
	public $config = [
		'name' => 'ap-xp-courier',
		'version' => '1.0.0',
		'textDomain' => 'axc',
		'textDomainPath' => 'languages',
		'slug' => 'ap-xp-courier',
		'prefix' => 'apxpc',
		'prefixSeparator' => '_',
	];
	
	//
	public function __construct() {
		self::init();

		self::start();
	}

	/**
	 * Initialize the plugin
	 */
	private function init() {
	}

	
	/**
	 * Deactivates the plugin due to the missing requirements.
	 */
	public function deactivate() {
		if(current_user_can('activate_plugins') && is_plugin_active(plugin_basename(__FILE__))) {
			deactivate_plugins(plugin_basename(__FILE__));
	
			// Hide the default "Plugin activated" notice
			if(isset($_GET['activate'])) {
				unset($_GET['activate']);
			}
		}
	}

	/**
	 * Starting the plugin.
	 */
	public function start() {
		require_once plugin_dir_path(__FILE__) . 'classes/plugin.php';
		$plugin = new apxpc_Plugin($this->config);

		register_activation_hook(__FILE__, [&$plugin, 'activate']);
		register_deactivation_hook(__FILE__, [&$plugin, 'deactivate']);
	}
}

$apxpc = new apxpc_Loader();
