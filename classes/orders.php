<?php
/**
 * The orders-specific functionality of the plugin.
 * Handles XP Courier shipment creation from order pages.
 *
 */
class apxpc_Orders {
	private $plugin;
	
	public function __construct($instance) {
		$this->plugin = $instance;
		
		// Add metabox to order page (for traditional post-based orders)
		add_action('add_meta_boxes', [&$this, 'add_xp_courier_metabox']);
		
		// Add content to HPOS order pages
		add_action('woocommerce_admin_order_data_after_shipping_address', [&$this, 'render_hpos_xp_courier_section']);
		
		// Enqueue scripts for admin
		add_action('admin_enqueue_scripts', [&$this, 'enqueue_order_scripts']);
		
		// Handle AJAX requests
		add_action('wp_ajax_apxpc_create_voucher', [&$this, 'handle_create_voucher']);
		add_action('wp_ajax_apxpc_print_voucher', [&$this, 'handle_print_voucher']);
		add_action('wp_ajax_apxpc_cancel_voucher', [&$this, 'handle_cancel_voucher']);
	}
	
	/**
	 * Add metabox to order page (for traditional post-based orders)
	 */
	public function add_xp_courier_metabox() {
		add_meta_box(
			$this->plugin->setPrefix('xp_courier'),
			__('XP Courier', $this->plugin->config['textDomain']),
			[&$this, 'render_xp_courier_metabox'],
			'shop_order',
			'side',
			'high'
		);
	}
	
	/**
	 * Render metabox content with button (for traditional post-based orders)
	 */
	public function render_xp_courier_metabox($post) {
		$order_id = $post->ID;
		$shipment_number = get_post_meta($order_id, $this->plugin->setPrefix('shipment_number'), true);
		$tracking_numbers = get_post_meta($order_id, $this->plugin->setPrefix('tracking_numbers'), true);
		$shipment_master_id = get_post_meta($order_id, $this->plugin->setPrefix('shipment_master_id'), true);
		
		echo '<div class="apxpc-metabox-content">';
		
		if ($shipment_number) {
			echo '<div style="background-color: #f0f6fc; padding: 10px; border-left: 4px solid #0073aa; margin-bottom: 10px;">';
			echo '<p><strong>' . esc_html__('Shipment Number:', $this->plugin->config['textDomain']) . '</strong> ' . esc_html($shipment_number) . '</p>';
			
			if ($tracking_numbers) {
				$tracking_array = is_array($tracking_numbers) ? $tracking_numbers : [$tracking_numbers];
				echo '<p><strong>' . esc_html__('Tracking Numbers:', $this->plugin->config['textDomain']) . '</strong><br/>';
				foreach ($tracking_array as $number) {
					echo esc_html($number) . '<br/>';
				}
				echo '</p>';
			}
			
			if ($shipment_master_id) {
				echo '<p><strong>' . esc_html__('Shipment Master ID:', $this->plugin->config['textDomain']) . '</strong><br/>' . esc_html($shipment_master_id) . '</p>';
			}
			echo '</div>';
			
			echo '<div style="display: flex; gap: 5px; margin-top: 5px;">';
			echo '<button type="button" id="apxpc-print-voucher-btn" class="button button-primary" style="flex: 1; font-size: 12px;" data-order-id="' . esc_attr($order_id) . '" data-shipment-number="' . esc_attr($shipment_number) . '">';
			echo esc_html__('Εκτύπωση XP Courier Voucher', $this->plugin->config['textDomain']);
			echo '</button>';
			echo '<button type="button" id="apxpc-cancel-voucher-btn" class="button button-danger" style="flex: 1; font-size: 12px; background-color: red; color: white;" data-order-id="' . esc_attr($order_id) . '" data-shipment-number="' . esc_attr($shipment_number) . '">';
			echo esc_html__('Ακύρωση Voucher', $this->plugin->config['textDomain']);
			echo '</button>';
			echo '</div>';
		} else {
			echo '<p>' . esc_html__('Δεν έχει δημιουργηθεί ακόμη Voucher. Με την δημιουργία του, θα αποσταλεί email στον πελάτη αυτόματα με το Voucher.', $this->plugin->config['textDomain']) . '</p>';
			
			// Get account codes from settings
			$account_code_1 = get_option($this->plugin->setPrefix('account_code_1'));
			$account_code_1_desc = get_option($this->plugin->setPrefix('account_code_1_description'));
			$account_code_2 = get_option($this->plugin->setPrefix('account_code_2'));
			$account_code_2_desc = get_option($this->plugin->setPrefix('account_code_2_description'));
			
			echo '<div style="display: flex; gap: 5px; margin-top: 5px; flex-direction: column;">';
			
			if ($account_code_1) {
				$button_text = 'Δημιουργία XP Courier Voucher';
				if ($account_code_1_desc) {
					$button_text .= ' ' . $account_code_1_desc;
				}
				echo '<button type="button" class="apxpc-create-voucher-btn button button-primary" style="style="background-color: steelblue; width: 100%; font-size: 12px;" data-order-id="' . esc_attr($order_id) . '" data-account-code="' . esc_attr($account_code_1) . '">';
				echo esc_html__($button_text, $this->plugin->config['textDomain']);
				echo '</button>';
			}
			
			if ($account_code_2) {
				$button_text = 'Δημιουργία XP Courier Voucher';
				if ($account_code_2_desc) {
					$button_text .= ' ' . $account_code_2_desc;
				}
				echo '<button type="button" class="apxpc-create-voucher-btn button button-secondary" style="background-color: bisque; width: 100%; font-size: 12px;" data-order-id="' . esc_attr($order_id) . '" data-account-code="' . esc_attr($account_code_2) . '">';
				echo esc_html__($button_text, $this->plugin->config['textDomain']);
				echo '</button>';
			}
			
			echo '</div>';
		}
		
		echo '<div id="apxpc-status-message" style="margin-top: 10px;"></div>';
		echo '</div>';
	}
	
	/**
	 * Render HPOS XP Courier section
	 */
	public function render_hpos_xp_courier_section($order) {
		if (is_int($order)) {
			$order_id = $order;
		} else {
			$order_id = $order->get_id();
		}
		
		$shipment_number = get_post_meta($order_id, $this->plugin->setPrefix('shipment_number'), true);
		$tracking_numbers = get_post_meta($order_id, $this->plugin->setPrefix('tracking_numbers'), true);
		$shipment_master_id = get_post_meta($order_id, $this->plugin->setPrefix('shipment_master_id'), true);
		
		echo '<div id="apxpc-xp-courier-section" style="background: #fff; border: 1px solid #ddd; margin: 20px 0; padding: 20px; border-radius: 4px;">';
		echo '<h3 style="margin-top: 0; color: #333;">' . esc_html__('XP Courier', $this->plugin->config['textDomain']) . '</h3>';
		
		if ($shipment_number) {
			echo '<div style="background-color: #f0f6fc; padding: 10px; border-left: 4px solid #0073aa; margin-bottom: 10px;">';
			echo '<p><strong>' . esc_html__('Shipment Number:', $this->plugin->config['textDomain']) . '</strong> ' . esc_html($shipment_number) . '</p>';
			
			if ($tracking_numbers) {
				$tracking_array = is_array($tracking_numbers) ? $tracking_numbers : [$tracking_numbers];
				echo '<p><strong>' . esc_html__('Tracking Numbers:', $this->plugin->config['textDomain']) . '</strong><br/>';
				foreach ($tracking_array as $number) {
					echo esc_html($number) . '<br/>';
				}
				echo '</p>';
			}
			
			if ($shipment_master_id) {
				echo '<p><strong>' . esc_html__('Shipment Master ID:', $this->plugin->config['textDomain']) . '</strong><br/>' . esc_html($shipment_master_id) . '</p>';
			}
			echo '</div>';
			
			echo '<div style="display: flex; gap: 5px; margin-top: 5px;">';
			echo '<button type="button" id="apxpc-print-voucher-btn" class="button button-primary" style="flex: 1; padding: 5px; font-size: 12px;" data-order-id="' . esc_attr($order_id) . '" data-shipment-number="' . esc_attr($shipment_number) . '">';
			echo esc_html__('Εκτύπωση XP Courier Voucher', $this->plugin->config['textDomain']);
			echo '</button>';
			echo '<button type="button" id="apxpc-cancel-voucher-btn" class="button button-danger" style="flex: 1; padding: 5px; font-size: 12px; background-color: red; color: white;" data-order-id="' . esc_attr($order_id) . '" data-shipment-number="' . esc_attr($shipment_number) . '">';
			echo esc_html__('Ακύρωση Voucher', $this->plugin->config['textDomain']);
			echo '</button>';
			echo '</div>';
		} else {
			echo '<p style="color: #666;">' . esc_html__('Δεν έχει δημιουργηθεί ακόμη Voucher. Με την δημιουργία του, θα αποσταλεί email στον πελάτη αυτόματα με το Voucher.', $this->plugin->config['textDomain']) . '</p>';
			
			// Get account codes from settings
			$account_code_1 = get_option($this->plugin->setPrefix('account_code_1'));
			$account_code_1_desc = get_option($this->plugin->setPrefix('account_code_1_description'));
			$account_code_2 = get_option($this->plugin->setPrefix('account_code_2'));
			$account_code_2_desc = get_option($this->plugin->setPrefix('account_code_2_description'));
			
			echo '<div style="display: flex; gap: 5px; margin-top: 5px; flex-direction: column;">';
			
			if ($account_code_1) {
				$button_text = 'Δημιουργία XP Courier Voucher';
				if ($account_code_1_desc) {
					$button_text .= ' ' . $account_code_1_desc;
				}
				echo '<button type="button" class="apxpc-create-voucher-btn button button-primary" style="background-color: steelblue; width: 100%; padding: 5px; font-size: 12px;" data-order-id="' . esc_attr($order_id) . '" data-account-code="' . esc_attr($account_code_1) . '">';
				echo esc_html__($button_text, $this->plugin->config['textDomain']);
				echo '</button>';
			}
			
			if ($account_code_2) {
				$button_text = 'Δημιουργία XP Courier Voucher';
				if ($account_code_2_desc) {
					$button_text .= ' ' . $account_code_2_desc;
				}
				echo '<button type="button" class="apxpc-create-voucher-btn button button-secondary" style="width: 100%; background-color: bisque; padding: 5px; font-size: 12px;" data-order-id="' . esc_attr($order_id) . '" data-account-code="' . esc_attr($account_code_2) . '">';
				echo esc_html__($button_text, $this->plugin->config['textDomain']);
				echo '</button>';
			}
			
			echo '</div>';
		}
		
		echo '<div id="apxpc-status-message" style="margin-top: 10px;"></div>';
		echo '</div>';
	}
	
	
	/**
	 * Enqueue scripts for order page
	 */
	public function enqueue_order_scripts($hook) {
		// Get current screen
		$screen = get_current_screen();
		
		// Check for both traditional post-based orders and HPOS orders
		$is_order_page = false;
		
		// Traditional post-based orders
		if (($hook === 'post.php' || $hook === 'post-new.php') && $screen && $screen->post_type === 'shop_order') {
			$is_order_page = true;
		}
		
		// HPOS orders (WooCommerce 7.0+)
		if ($screen && (strpos($screen->id, 'woocommerce_page_wc-orders') !== false || $screen->id === 'woocommerce_page_wc-orders')) {
			$is_order_page = true;
		}
		
		// Also check for edit-shop_order which is HPOS
		if ($screen && $screen->id === 'edit-shop_order') {
			$is_order_page = true;
		}
		
		if (!$is_order_page) {
			return;
		}
		
		wp_enqueue_script(
			$this->plugin->setPrefix('order_script'),
			plugins_url() . '/' . $this->plugin->config['slug'] . '/assets/order.js',
			['jquery'],
			filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/order.js'),
			true
		);
		
		// Localize script with AJAX URL and nonce
		wp_localize_script(
			$this->plugin->setPrefix('order_script'),
			'apxpcOrderObj',
			[
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce($this->plugin->setPrefix('nonce')),
				'prefix' => $this->plugin->config['prefix'],
			]
		);
	}
	
	/**
	 * Handle AJAX request to create voucher
	 */
	public function handle_create_voucher() {
		// Verify nonce
		if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], $this->plugin->setPrefix('nonce'))) {
			wp_send_json_error(['message' => 'Security check failed.']);
		}
		
		// Verify capability
		if (!current_user_can('manage_woocommerce')) {
			wp_send_json_error(['message' => 'Insufficient permissions.']);
		}
		
		$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
		
		if (!$order_id) {
			wp_send_json_error(['message' => 'Invalid order ID.']);
		}
		
		$order = wc_get_order($order_id);
		if (!$order) {
			wp_send_json_error(['message' => 'Order not found.']);
		}
		
		// Get settings
		$user_alias = get_option($this->plugin->setPrefix('user_alias'));
		$credential_value = get_option($this->plugin->setPrefix('credential_value'));
		$api_key = get_option($this->plugin->setPrefix('api_key'));
		
		if (!$user_alias || !$credential_value || !$api_key) {
			wp_send_json_error(['message' => 'XP Courier credentials not configured.']);
		}
		
		// Get account code from AJAX request
		$account_code = isset($_POST['account_code']) ? sanitize_text_field($_POST['account_code']) : '';
		
		if (!$account_code) {
			wp_send_json_error(['message' => 'Account code not provided.']);
		}
		
		// Build payload
		$payload = $this->build_shipment_payload($order, $user_alias, $credential_value, $api_key, $account_code);
		
		// Make API request
		$response = $this->call_xp_courier_api($payload);
		
		if (is_wp_error($response)) {
			wp_send_json_error(['message' => $response->get_error_message()]);
		}
		
		if (!$response['success']) {
			wp_send_json_error(['message' => $response['message']]);
		}
		
		// Save meta fields
		update_post_meta($order_id, $this->plugin->setPrefix('shipment_number'), $response['data']['ShipmentNumber']);
		update_post_meta($order_id, $this->plugin->setPrefix('tracking_numbers'), $response['data']['TrackingNumbers']);
		update_post_meta($order_id, $this->plugin->setPrefix('shipment_master_id'), $response['data']['ShipmentMasterId']);
		
		// Now fetch the voucher
		$voucher_response = $this->call_voucher_api(
			$response['data']['ShipmentNumber'],
			$user_alias,
			$credential_value,
			$api_key
		);
		
		if (!is_wp_error($voucher_response) && $voucher_response['success']) {
			// Save voucher PDF
			update_post_meta($order_id, $this->plugin->setPrefix('voucher_pdf'), $voucher_response['data']['Voucher']);
		}
		
		// Add order note
		$order->add_order_note(sprintf(
			__('XP Courier voucher created. Shipment Number: %s', $this->plugin->config['textDomain']),
			$response['data']['ShipmentNumber']
		));
		
		// Send email to customer if auto-send is enabled
		$auto_send_email = get_option($this->plugin->setPrefix('auto_send_email'));
		if ($auto_send_email) {
			$this->send_voucher_email($order, $response['data']['ShipmentNumber']);
		}
		
		wp_send_json_success([
			'message' => __('Voucher created successfully!', $this->plugin->config['textDomain']),
			'data' => $response['data'],
			'voucher' => isset($voucher_response['data']['Voucher']) ? $voucher_response['data']['Voucher'] : null,
		]);
	}
	
	/**
	 * Handle AJAX request to print voucher
	 */
	public function handle_print_voucher() {
		// Verify nonce
		if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], $this->plugin->setPrefix('nonce'))) {
			wp_send_json_error(['message' => 'Security check failed.']);
		}
		
		// Verify capability
		if (!current_user_can('manage_woocommerce')) {
			wp_send_json_error(['message' => 'Insufficient permissions.']);
		}
		
		$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
		
		if (!$order_id) {
			wp_send_json_error(['message' => 'Invalid order ID.']);
		}
		
		$order = wc_get_order($order_id);
		if (!$order) {
			wp_send_json_error(['message' => 'Order not found.']);
		}
		
		$shipment_number = isset($_POST['shipment_number']) ? sanitize_text_field($_POST['shipment_number']) : '';
		
		// Get settings
		$user_alias = get_option($this->plugin->setPrefix('user_alias'));
		$credential_value = get_option($this->plugin->setPrefix('credential_value'));
		$api_key = get_option($this->plugin->setPrefix('api_key'));
		
		if (!$user_alias || !$credential_value || !$api_key) {
			wp_send_json_error(['message' => 'XP Courier credentials not configured.']);
		}
		
		// Fetch voucher
		$response = $this->call_voucher_api($shipment_number, $user_alias, $credential_value, $api_key);
		
		if (is_wp_error($response)) {
			wp_send_json_error(['message' => $response->get_error_message()]);
		}
		
		if (!$response['success']) {
			wp_send_json_error(['message' => $response['message']]);
		}
		
		// Save voucher PDF
		update_post_meta($order_id, $this->plugin->setPrefix('voucher_pdf'), $response['data']['Voucher']);
		
		wp_send_json_success([
			'message' => __('Voucher fetched successfully!', $this->plugin->config['textDomain']),
			'voucher' => $response['data']['Voucher'],
		]);
	}
	
	/**
	 * Build shipment payload from order
	 */
	private function build_shipment_payload($order, $user_alias, $credential_value, $api_key, $account_code) {
		// Get shipping address
		$shipping_address = $order->get_address('shipping');
		
		// Get state name from code
		$state_code = $shipping_address['state'] ?? '';
		$state_name = $this->get_greek_state_name($state_code);
		
		// Get items from order
		$items = [];
		foreach ($order->get_items() as $item) {
			$product = $item->get_product();
			$quantity = $item->get_quantity();
			
			// Get product weight
			$weight = $product ? (float)$product->get_weight() : 1;
			
			// Add item for each quantity
			for ($i = 0; $i < $quantity; $i++) {
				$items[] = [
					'GoodsType' => 'NoDocs',
					'Content' => $item->get_name(),
					'IsDangerousGoods' => false,
					'IsDryIce' => false,
					'IsFragile' => false,
					'Weight' => [
						'Unit' => 'kg',
						'Value' => $weight ?: 1,
					],
				];
			}
		}
		
		// If no items, add a default
		if (empty($items)) {
			$items[] = [
				'GoodsType' => 'NoDocs',
				'Content' => 'Order Item',
				'IsDangerousGoods' => false,
				'IsDryIce' => false,
				'IsFragile' => false,
				'Weight' => [
					'Unit' => 'kg',
					'Value' => 1,
				],
			];
		}
		
		// Build payload
		$payload = [
			'Context' => [
				'UserAlias' => $user_alias,
				'CredentialValue' => $credential_value,
				'ApiKey' => $api_key,
			],
			'ShipmentDate' => current_time('Y-m-d'),
			'comments' => $order->get_customer_note() ?: '',
			'Requestor' => [
				'Code' => $account_code,
			],
			'Shipper' => [
				'Code' => $account_code,
			],
			'Consignee' => [
				'CompanyName' => $shipping_address['company'] ?: $shipping_address['first_name'] . ' ' . $shipping_address['last_name'],
				'ContactName' => $shipping_address['first_name'] . ' ' . $shipping_address['last_name'],
				'Address' => $shipping_address['address_1'],
				'City' => $shipping_address['city'],
				// 'Area' => $shipping_address['state'] ?? '',
				'Region' => $state_name,
				'ZipCode' => $shipping_address['postcode'],
				'Country' => $shipping_address['country'],
				'Phone1' => $order->get_billing_phone(),
				'Reference' => '',
			],
			'BillTo' => 'requestor',
			'Items' => $items,
		];
		
		// Add CODs if payment method is COD
		if ($order->get_payment_method() === 'cod') {
			$payload['CODs'] = [
				[
					'Type' => 'Cash',
					'Amount' => [
						'Currency' => 'EUR',
						'Value' => (float)$order->get_total(),
					],
				],
			];
		}
		
		return $payload;
	}
	
	/**
	 * Convert Greek state code to state name
	 */
	private function get_greek_state_name($state_code) {
		$greek_states = [
			'I' => 'Αττική',
			'A' => 'Ανατολική Μακεδονία και Θράκη',
			'B' => 'Κεντρική Μακεδονία',
			'C' => 'Δυτική Μακεδονία',
			'D' => 'Ήπειρος',
			'E' => 'Θεσσαλία',
			'F' => 'Ιόνια νησιά',
			'G' => 'Δυτική Ελλάδα',
			'H' => 'Στερεά Ελλάδα',
			'J' => 'Πελοπόννησος',
			'K' => 'Βόρειο Αιγαίο',
			'L' => 'Νότιο Αιγαίο',
			'M' => 'Κρήτη',
		];
		
		return isset($greek_states[$state_code]) ? $greek_states[$state_code] : $state_code;
	}
	
	/**
	 * Call XP Courier API
	 */
	private function call_xp_courier_api($payload) {
		$api_url = 'https://xp-prod.qualco.eu/xpservice/api/Shipment';
		
		$args = [
			'method' => 'POST',
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body' => wp_json_encode($payload),
			'timeout' => 30,
		];
		
		$response = wp_remote_post($api_url, $args);
		
		if (is_wp_error($response)) {
			return $response;
		}
		
		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);
		
		// Check if response was successful
		if ($status_code >= 200 && $status_code < 300) {
			if ($data && isset($data['Result']) && $data['Result'] === 'Success') {
				return [
					'success' => true,
					'data' => $data,
				];
			}
		}
		
		$error_message = 'API request failed.';
		if ($data && isset($data['Message'])) {
			$error_message = $data['Message'];
		} elseif ($data && isset($data['Errors']) && is_array($data['Errors']) && !empty($data['Errors'])) {
			$error_message = $data['Errors'][0]['Message'] ?? 'Unknown error';
		}
		
		// Append payload to error message for debugging
		$error_message .= ' | Payload: ' . wp_json_encode($payload);
		
		return [
			'success' => false,
			'message' => $error_message,
		];
	}
	
	/**
	 * Call XP Courier Voucher API
	 */
	private function call_voucher_api($shipment_number, $user_alias, $credential_value, $api_key) {
		$api_url = 'https://xp-prod.qualco.eu/xpservice/api/Voucher';
		
		$payload = [
			'Context' => [
				'UserAlias' => $user_alias,
				'CredentialValue' => $credential_value,
				'ApiKey' => $api_key,
			],
			'ShipmentNumber' => $shipment_number,
		];
		
		// Add template if configured
		$voucher_template = get_option($this->plugin->setPrefix('voucher_template'));
		if ($voucher_template === 'singlepdf') {
			$payload['Template'] = 'singlepdf_100x150';
		}
		
		$args = [
			'method' => 'POST',
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body' => wp_json_encode($payload),
			'timeout' => 30,
		];
		
		$response = wp_remote_post($api_url, $args);
		
		if (is_wp_error($response)) {
			return $response;
		}
		
		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);
		
		// Check if response was successful
		if ($status_code >= 200 && $status_code < 300) {
			if ($data && isset($data['Result']) && $data['Result'] === 'Success') {
				return [
					'success' => true,
					'data' => $data,
				];
			}
		}
		
		// Extract error message from Errors array if present
		$error_message = 'API request failed.';
		if ($data && isset($data['Message'])) {
			$error_message = $data['Message'];
		} elseif ($data && isset($data['Errors']) && is_array($data['Errors']) && !empty($data['Errors'])) {
			$error_message = $data['Errors'][0]['Message'] ?? 'Unknown error';
		}
		
		return [
			'success' => false,
			'message' => $error_message,
		];
	}
	
	/**
	 * Handle AJAX request to cancel voucher
	 */
	public function handle_cancel_voucher() {
		// Verify nonce
		if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], $this->plugin->setPrefix('nonce'))) {
			wp_send_json_error(['message' => 'Security check failed.']);
		}
		
		// Verify capability
		if (!current_user_can('manage_woocommerce')) {
			wp_send_json_error(['message' => 'Insufficient permissions.']);
		}
		
		$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
		
		if (!$order_id) {
			wp_send_json_error(['message' => 'Invalid order ID.']);
		}
		
		$order = wc_get_order($order_id);
		if (!$order) {
			wp_send_json_error(['message' => 'Order not found.']);
		}
		
		$shipment_number = isset($_POST['shipment_number']) ? sanitize_text_field($_POST['shipment_number']) : '';
		
		if (!$shipment_number) {
			wp_send_json_error(['message' => 'Shipment number is required.']);
		}
		
		// Get settings
		$user_alias = get_option($this->plugin->setPrefix('user_alias'));
		$credential_value = get_option($this->plugin->setPrefix('credential_value'));
		$api_key = get_option($this->plugin->setPrefix('api_key'));
		
		if (!$user_alias || !$credential_value || !$api_key) {
			wp_send_json_error(['message' => 'XP Courier credentials not configured.']);
		}
		
		// Call void shipment API
		$response = $this->call_void_shipment_api($shipment_number, $user_alias, $credential_value, $api_key);
		
		if (is_wp_error($response)) {
			wp_send_json_error(['message' => $response->get_error_message()]);
		}
		
		if (!$response['success']) {
			wp_send_json_error(['message' => $response['message']]);
		}
		
		// Delete meta fields
		delete_post_meta($order_id, $this->plugin->setPrefix('shipment_number'));
		delete_post_meta($order_id, $this->plugin->setPrefix('tracking_numbers'));
		delete_post_meta($order_id, $this->plugin->setPrefix('shipment_master_id'));
		delete_post_meta($order_id, $this->plugin->setPrefix('voucher_pdf'));
		
		// Add order note
		$order->add_order_note(sprintf(
			__('XP Courier voucher cancelled. Shipment Number: %s', $this->plugin->config['textDomain']),
			$shipment_number
		));
		
		wp_send_json_success([
			'message' => __('Voucher cancelled successfully!', $this->plugin->config['textDomain']),
		]);
	}
	
	/**
	 * Call XP Courier Void Shipment API
	 */
	private function call_void_shipment_api($shipment_number, $user_alias, $credential_value, $api_key) {
		$api_url = 'https://xp-prod.qualco.eu/xpservice/api/Shipment/Void';
		
		$payload = [
			'Context' => [
				'UserAlias' => $user_alias,
				'CredentialValue' => $credential_value,
				'ApiKey' => $api_key,
			],
			'ShipmentNumber' => $shipment_number,
		];
		
		$args = [
			'method' => 'POST',
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body' => wp_json_encode($payload),
			'timeout' => 30,
		];
		
		$response = wp_remote_post($api_url, $args);
		
		if (is_wp_error($response)) {
			return $response;
		}
		
		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);
		
		// Check if response was successful
		if ($status_code >= 200 && $status_code < 300) {
			if ($data && isset($data['Result']) && $data['Result'] === 'Success') {
				return [
					'success' => true,
					'data' => $data,
				];
			}
		}
		
		// Extract error message from Errors array if present
		$error_message = 'API request failed.';
		if ($data && isset($data['Message'])) {
			$error_message = $data['Message'];
		} elseif ($data && isset($data['Errors']) && is_array($data['Errors']) && !empty($data['Errors'])) {
			$error_message = $data['Errors'][0]['Message'] ?? 'Unknown error';
		}
		
		return [
			'success' => false,
			'message' => $error_message,
		];
	}
	
	/**
	 * Get Woodmart logo URL with multiple fallback options
	 */
	private function get_woodmart_logo_url() {
		$logo_url = '';

		// Method 1: WoodMart option (handles both array and attachment ID)
		if (function_exists('woodmart_get_opt')) {
			$logo = woodmart_get_opt('logo');

			if (!empty($logo)) {
				// Newer WoodMart versions return an array with 'id' and 'url'
				if (is_array($logo)) {
					if (!empty($logo['url'])) {
						$logo_url = $logo['url'];
					} elseif (!empty($logo['id'])) {
						$logo_url = wp_get_attachment_url($logo['id']);
					}
				}
				// Older versions return just the attachment ID
				elseif (is_numeric($logo)) {
					$logo_url = wp_get_attachment_url((int) $logo);
				}
				// Some versions return a direct URL string
				elseif (is_string($logo) && filter_var($logo, FILTER_VALIDATE_URL)) {
					$logo_url = $logo;
				}
			}
		}

		// Method 2: Try the retina logo as fallback
		if (empty($logo_url) && function_exists('woodmart_get_opt')) {
			$logo_retina = woodmart_get_opt('logo_retina');
			if (is_array($logo_retina) && !empty($logo_retina['url'])) {
				$logo_url = $logo_retina['url'];
			}
		}

		// Method 3: WordPress native custom logo fallback
		if (empty($logo_url)) {
			$custom_logo_id = get_theme_mod('custom_logo');
			if ($custom_logo_id) {
				$logo_url = wp_get_attachment_url($custom_logo_id);
			}
		}

		return $logo_url;
	}

	/**
	 * Send voucher email to customer
	 */
	private function send_voucher_email($order, $shipment_number) {
		$customer_email = $order->get_billing_email();
		
		if (!$customer_email) {
			return false;
		}
		
		$customer_name = $order->get_billing_first_name();
		$subject = 'Η παραγγελία σας έχει αποσταλεί!';
		
		// Get logo URL - check for custom email logo first, then fallback to site logo
		$logo_url = get_option($this->plugin->setPrefix('email_logo_image'));
		if (empty($logo_url)) {
			$logo_url = $this->get_woodmart_logo_url();
		}
		
		$logo_html = '';
		
		if ($logo_url) {
			$logo_html = '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr(get_bloginfo('name')) . '" style="max-width: 200px; height: auto; display: block; margin: 0 auto 20px;">';
		} else {
			// Text fallback if no logo found
			$logo_html = '<p style="font-size: 24px; font-weight: bold; text-align: center; margin: 0 0 20px 0;">' . esc_html(get_bloginfo('name')) . '</p>';
		}
		
		// Build professional HTML email
		$message = '<html><body style="font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f5f5f5;">';
		$message .= '<div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 0; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">';
		
		// Header
		$message .= '<div style="padding: 40px 20px; text-align: center; color: #333333;">';
		if ($logo_html) {
			$message .= $logo_html;
		}
    
		$message .= '<h1 style="margin: 0; font-size: 28px; font-weight: bold; color: #333333;">Η παραγγελία σας απο το ' . esc_html(get_bloginfo('name')) . ' έχει αποσταλεί! 📦</h1>';
		$message .= '</div>';
		
		// Content
		$message .= '<div style="padding: 40px 30px; color: #333333;">';
		
		// Greeting
		$message .= '<p style="font-size: 16px; margin: 0 0 20px 0; line-height: 1.6;">Γεία σας <strong>' . esc_html($customer_name) . '</strong>,</p>';
		
		// Main message
		$message .= '<p style="font-size: 15px; color: #555555; margin: 0 0 25px 0; line-height: 1.6;"> Η παραγγελία σας  έχει αποσταλεί με την XP Courier.</p>';
		
		// Shipment code box
		$message .= '<div style="background-color: #f0f6fc; border-left: 5px solid #333333; padding: 20px; margin: 30px 0; border-radius: 4px;">';
		$message .= '<p style="font-size: 13px; color: #666666; margin: 0 0 10px 0; text-transform: uppercase; letter-spacing: 1px; font-weight: bold;">Κωδικός Παρακολούθησης:</p>';
		$message .= '<p style="font-size: 24px; color: #333333; margin: 0; font-weight: bold; letter-spacing: 2px;" >' . esc_html($shipment_number) . '</p>';
		$message .= '</div>';
		
		// Details
		$message .= '<p style="font-size: 14px; color: #666666; margin: 0 0 25px 0; line-height: 1.6;">Με τον παραπάνω κωδικό voucher μπορείτε να παρακολουθήσετε το πακέτο σας σε κάθε βήμα του ταξιδιού του.</p>';
		
		// CTA Button
		$message .= '<div style="text-align: center; margin: 30px 0;">';
		$message .= '<a href="https://xpcourier.gr/track_package?tracking_number=' . esc_attr($shipment_number) . '" style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; padding: 14px 35px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 15px; border: none; cursor: pointer;">Παρακολουθήστε την Παραγγελία</a>';
		$message .= '</div>';
		
		// Info box
		// $message .= '<div style="background-color: #f9f9f9; border-left: 4px solid #ffc107; padding: 15px; margin: 30px 0; border-radius: 4px; font-size: 13px; color: #666666; line-height: 1.6;">';
		// $message .= '<strong style="color: #333333;">Σημαντικό:</strong> Η παραγγελία σας διακινείται από την XP Courier. Για γρήγορη παρακολούθηση, χρησιμοποιήστε τον κωδικό παραπάνω στον ιστότοπό τους.';
		// $message .= '</div>';
		
		// Closing
		$message .= '<p style="font-size: 14px; color: #666666; margin: 25px 0 0 0; line-height: 1.6;">Ευχαριστούμε για την εμπιστοσύνη σας!<br/><strong style="color: #333333;">' . esc_html(get_bloginfo('name')) . '</strong></p>';
		
		$message .= '</div>';
		
		// Footer
		$message .= '<div style="background-color: #f0f0f0; padding: 20px; text-align: center; font-size: 12px; color: #999999; border-top: 1px solid #e0e0e0;">';
		$message .= '<p style="margin: 0 0 5px 0;">Αυτό είναι ένα αυτόματο email. Παρακαλώ μην απαντήσετε.</p>';
		$message .= '<p style="margin: 0;">© ' . esc_html(get_bloginfo('name')) . ' - Όλα τα δικαιώματα διατηρούνται.</p>';
		$message .= '</div>';
		
		$message .= '</div>';
		$message .= '</body></html>';
		
		// Set email headers
		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_option('blogname') . ' <' . get_option('admin_email') . '>',
		];
		
		// Send email
		wp_mail($customer_email, $subject, $message, $headers);
	}
}
