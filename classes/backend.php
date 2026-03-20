<?php
/**
 * The backend(admin)-specific functionality of the plugin.
 * - admin menus
 * - admin pages (e.g. settings)
 * - stylesheets
 * - scripts
 *
 */
class apxpc_Backend {
	private $plugin;
	private $settings;
	private $isSettingsPage = false;
	
	private $pages = [];
	public function __construct($instance) {
		$this->plugin = $instance;
		
		add_action('admin_enqueue_scripts', [&$this, 'enqueue_styles_and_scripts']);
		
		if(current_user_can('manage_options'))
			add_action('admin_menu', [&$this, 'register_admin_menu']);
		
		add_action('admin_init', [&$this, 'register_settings']);
		
		add_action('current_screen', [$this, 'wpdocs_this_screen']);
	}
	
	// Register stylesheets and scripts for the admin area.
	public function enqueue_styles_and_scripts() {
		if(!$this->isSettingsPage)
			return;
		
		wp_enqueue_style($this->plugin->setPrefix('apxpc_backend_style'), plugins_url() . '/' . $this->plugin->config["slug"] . '/assets/backend.css', [], filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/backend.css'), 'all');
		wp_enqueue_script($this->plugin->setPrefix('apxpc_backend_script'), plugins_url() . '/' . $this->plugin->config["slug"] . '/assets/backend.js', ['jquery'], filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/backend.js'), false);
		
	}
	
	public function wpdocs_this_screen() {
		$current_screen = get_current_screen();
		
		$this->isSettingsPage = in_array($current_screen->id, $this->pages);
	}
	/**
	 * Register admin menu.
	 */
	public function register_admin_menu() {
		if(!current_user_can('manage_options')) {
			wp_die(__('You do not have sufficient permissions to access this page.', $this->plugin->config['textDomain']));
		}


		/**
		 * Add a top-level menu page.
		 * https://developer.wordpress.org/reference/functions/add_menu_page/
		 *
		 * Arguments:
		 * 1. Page Title
		 * 2. Menu Title
		 * 3. Capability
		 * 4. Menu Slug (you will overwrite that menu item if specified slug is already exists)
		 * 5. Function to display page
		 * 6. Icon URL
		 * 7. Position (https://developer.wordpress.org/reference/functions/add_menu_page/#menu-structure)
		 */
		$page = add_menu_page(
			__("XP COURIER", $this->plugin->config["textDomain"]),
			__("XP COURIER", $this->plugin->config["textDomain"]),
			"manage_options",
			$this->plugin->setPrefix("xp-courier-settings"),
			[&$this, "render_page_xp_courier_settings"],
			"",
			2
		);
		array_push($this->pages, $page);
	}
	
	/**
	 * Register settings page(s), sections and fields.
	 */
	public function register_settings() {
		$this->settings = (array)$this->plugin->getOption('fields'); 
		
		$xp_courier_settings = $this->plugin->setPrefix("xp-courier-settings");
		$menu1_section1 = $this->plugin->setPrefix("menu1_section1");
		
		/**
		 * Add a section for the settings page
		 * https://developer.wordpress.org/reference/functions/add_settings_section/
		 *
		 * Arguments:
		 * 1. id
		 * 2. title
		 * 3. callback
		 * 4. page custom (e.g. slug defined in custom menu) or existing wp page (e.g. reading --> Settings/Reading page)
		 */
		add_settings_section(
			$menu1_section1,
			__("Credentials", $this->plugin->config["textDomain"]),
			function() {
				esc_html_e("Enter your API credentials below.", $this->plugin->config["textDomain"]);
			},
			$xp_courier_settings
		);

		// Register settings
		register_setting('apxpc_options', $this->plugin->setPrefix('user_alias'));
		register_setting('apxpc_options', $this->plugin->setPrefix('credential_value'));
		register_setting('apxpc_options', $this->plugin->setPrefix('api_key'));
		register_setting('apxpc_options', $this->plugin->setPrefix('account_code_1'));
		register_setting('apxpc_options', $this->plugin->setPrefix('account_code_1_description'));
		register_setting('apxpc_options', $this->plugin->setPrefix('account_code_2'));
		register_setting('apxpc_options', $this->plugin->setPrefix('account_code_2_description'));
		register_setting('apxpc_options', $this->plugin->setPrefix('auto_send_email'));
		register_setting('apxpc_options', $this->plugin->setPrefix('email_logo_image'));
		register_setting('apxpc_options', $this->plugin->setPrefix('voucher_template'));

		// Add fields
		add_settings_field(
			$this->plugin->setPrefix('user_alias'),
			__('User Alias', $this->plugin->config["textDomain"]),
			[&$this, 'render_field_user_alias'],
			$xp_courier_settings,
			$menu1_section1
		);

		add_settings_field(
			$this->plugin->setPrefix('credential_value'),
			__('Credential Value', $this->plugin->config["textDomain"]),
			[&$this, 'render_field_credential_value'],
			$xp_courier_settings,
			$menu1_section1
		);

		add_settings_field(
			$this->plugin->setPrefix('api_key'),
			__('API Key', $this->plugin->config["textDomain"]),
			[&$this, 'render_field_api_key'],
			$xp_courier_settings,
			$menu1_section1
		);

		add_settings_field(
			$this->plugin->setPrefix('account_code_1'),
			__('Account Code 1', $this->plugin->config["textDomain"]),
			[&$this, 'render_field_account_code_1'],
			$xp_courier_settings,
			$menu1_section1
		);

		add_settings_field(
			$this->plugin->setPrefix('account_code_1_description'),
			__('Account Code 1 Description', $this->plugin->config["textDomain"]),
			[&$this, 'render_field_account_code_1_description'],
			$xp_courier_settings,
			$menu1_section1
		);

		add_settings_field(
			$this->plugin->setPrefix('account_code_2'),
			__('Account Code 2', $this->plugin->config["textDomain"]),
			[&$this, 'render_field_account_code_2'],
			$xp_courier_settings,
			$menu1_section1
		);

		add_settings_field(
			$this->plugin->setPrefix('account_code_2_description'),
			__('Account Code 2 Description', $this->plugin->config["textDomain"]),
			[&$this, 'render_field_account_code_2_description'],
			$xp_courier_settings,
			$menu1_section1
		);

		add_settings_field(
			$this->plugin->setPrefix('auto_send_email'),
			__('Auto Send Email on Voucher Creation', $this->plugin->config["textDomain"]),
			[&$this, 'render_field_auto_send_email'],
			$xp_courier_settings,
			$menu1_section1
		);

		add_settings_field(
			$this->plugin->setPrefix('email_logo_image'),
			__('Email Template Logo Image', $this->plugin->config["textDomain"]),
			[&$this, 'render_field_email_logo_image'],
			$xp_courier_settings,
			$menu1_section1
		);

		add_settings_field(
			$this->plugin->setPrefix('voucher_template'),
			__('Voucher Template', $this->plugin->config["textDomain"]),
			[&$this, 'render_field_voucher_template'],
			$xp_courier_settings,
			$menu1_section1
		);
	}
	/**
	 * Display the settings page for the menu(s) that have created.
	 */
	public function render_page_xp_courier_settings() {
		?>
		<div id="wrap">
			<form action="options.php" method="post">
				<?php
				// Render all sections of the page
				do_settings_sections($this->plugin->setPrefix("xp-courier-settings"));
	

				// Render fields
				settings_fields($this->plugin->setPrefix("options"));
	

				submit_button();
				?>
			</form>
		</div>
		<?php
	}


	
	/**
	 * Display sections
	 */
	public function render_menu1_section1() {
		esc_html_e("Section content", $this->plugin->config["textDomain"]); 
	}

	/**
	 * Render User Alias field
	 */
	public function render_field_user_alias() {
		$value = get_option($this->plugin->setPrefix('user_alias'));
		echo '<input type="text" name="' . esc_attr($this->plugin->setPrefix('user_alias')) . '" value="' . esc_attr($value) . '" class="regular-text" />';
	}

	/**
	 * Render Credential Value field
	 */
	public function render_field_credential_value() {
		$value = get_option($this->plugin->setPrefix('credential_value'));
		echo '<input type="text" name="' . esc_attr($this->plugin->setPrefix('credential_value')) . '" value="' . esc_attr($value) . '" class="regular-text" />';
	}

	/**
	 * Render API Key field
	 */
	public function render_field_api_key() {
		$value = get_option($this->plugin->setPrefix('api_key'));
		echo '<input type="text" name="' . esc_attr($this->plugin->setPrefix('api_key')) . '" value="' . esc_attr($value) . '" class="regular-text" />';
	}

	/**
	 * Render Account Code 1 field
	 */
	public function render_field_account_code_1() {
		$value = get_option($this->plugin->setPrefix('account_code_1'));
		echo '<input type="text" name="' . esc_attr($this->plugin->setPrefix('account_code_1')) . '" value="' . esc_attr($value) . '" class="regular-text" placeholder="e.g., 100-0367-0001" />';
	}

	/**
	 * Render Account Code 1 Description field
	 */
	public function render_field_account_code_1_description() {
		$value = get_option($this->plugin->setPrefix('account_code_1_description'));
		echo '<input type="text" name="' . esc_attr($this->plugin->setPrefix('account_code_1_description')) . '" value="' . esc_attr($value) . '" class="regular-text" placeholder="e.g., Αθήνα" />';
	}

	/**
	 * Render Account Code 2 field
	 */
	public function render_field_account_code_2() {
		$value = get_option($this->plugin->setPrefix('account_code_2'));
		echo '<input type="text" name="' . esc_attr($this->plugin->setPrefix('account_code_2')) . '" value="' . esc_attr($value) . '" class="regular-text" placeholder="e.g., 100-0367-0002" />';
	}

	/**
	 * Render Account Code 2 Description field
	 */
	public function render_field_account_code_2_description() {
		$value = get_option($this->plugin->setPrefix('account_code_2_description'));
		echo '<input type="text" name="' . esc_attr($this->plugin->setPrefix('account_code_2_description')) . '" value="' . esc_attr($value) . '" class="regular-text" placeholder="e.g., Κρήτη" />';
	}

	/**
	 * Render Auto Send Email checkbox
	 */
	public function render_field_auto_send_email() {
		$value = get_option($this->plugin->setPrefix('auto_send_email'));
		$checked = $value ? 'checked' : '';
		echo '<input type="checkbox" name="' . esc_attr($this->plugin->setPrefix('auto_send_email')) . '" value="1" ' . esc_attr($checked) . ' />';
		echo '<label style="margin-left: 10px;">' . esc_html__('Automatically send email to customer when voucher is created', $this->plugin->config['textDomain']) . '</label>';
	}

	/**
	 * Render Email Template Logo Image field
	 */
	public function render_field_email_logo_image() {
		$value = get_option($this->plugin->setPrefix('email_logo_image'));
		echo '<div style="display: flex; gap: 10px; align-items: center;">';
		echo '<input type="text" id="' . esc_attr($this->plugin->setPrefix('email_logo_image')) . '" name="' . esc_attr($this->plugin->setPrefix('email_logo_image')) . '" value="' . esc_attr($value) . '" class="regular-text" placeholder="Paste image URL here" />';
		echo '<button type="button" class="button" id="' . esc_attr($this->plugin->setPrefix('email_logo_image_button')) . '">' . esc_html__('Upload Image', $this->plugin->config['textDomain']) . '</button>';
		echo '</div>';
		if (!empty($value)) {
			echo '<div style="margin-top: 10px;">';
			echo '<img src="' . esc_url($value) . '" alt="Logo" style="max-width: 200px; max-height: 100px;" />';
			echo '</div>';
		}
	}

	/**
	 * Render Voucher Template dropdown
	 */
	public function render_field_voucher_template() {
		$value = get_option($this->plugin->setPrefix('voucher_template'));
		echo '<select name="' . esc_attr($this->plugin->setPrefix('voucher_template')) . '">';
		echo '<option value="">' . esc_html__('Default', $this->plugin->config['textDomain']) . '</option>';
		// echo '<option value="pdf" ' . selected($value, 'pdf', false) . '>' . esc_html__('PDF', $this->plugin->config['textDomain']) . '</option>';
		echo '<option value="singlepdf" ' . selected($value, 'singlepdf', false) . '>' . esc_html__('Single PDF (100x150)', $this->plugin->config['textDomain']) . '</option>';
		echo '</select>';
	}

	
}
