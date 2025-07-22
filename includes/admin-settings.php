<?php
// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

// Check premium license (placeholder)
function wmb_is_premium() {
	return get_option('wmb_premium_license', true);
}

// Enqueue admin styles
function wmb_admin_enqueue_styles() {
	if (isset($_GET['page']) && $_GET['page'] === 'wmb') {
		wp_enqueue_style('wmb-admin-styles', WMB_PLUGIN_URL . 'css/wmb-admin.css', array(), '2.1.1');
	}
}
add_action('admin_enqueue_scripts', 'wmb_admin_enqueue_styles');

// Register settings
function wmb_register_settings() {
	register_setting(
		'wmb_options',
		'wmb_settings',
		array(
			'sanitize_callback' => 'wmb_sanitize_settings',
		)
	);
	
	add_settings_section(
			'wmb_main',
			'Merchandising Rules',
			function() {
				echo '<p>Define rules for dynamic product recommendations. Premium users can add advanced filters, variation support, and analytics.</p>';
			},
			'wmb_settings'
					);
	
	// Register rules field
	add_settings_field(
			'wmb_rules',
			__('Recommendation Rules', 'woocommerce-merchandising-booster'),
			'wmb_rules',
			'wmb_settings',
			'wmb_main'
			);
	
	// Register stock threshold field
	add_settings_field(
			'wmb_stock_threshold',
			__('Low Stock Threshold', 'woocommerce-merchandising-booster'),
			function() {
				$settings = get_option('wmb_settings', array());
				$stock_threshold = $settings['stock_threshold'] ?? 5;
				?>
			<input type="number" name="wmb_settings[stock_threshold]" value="<?php echo esc_attr($stock_threshold); ?>">
			<?php
		},
		'wmb_settings',
		'wmb_main'
	);
}
add_action('admin_init', 'wmb_register_settings');

// Sanitize settings
function wmb_sanitize_settings( $input ) {
	// Load existing settings to preserve unchanged rules
	$sanitized = get_option( 'wmb_settings', array() );

	$existing_rules = isset( $sanitized['rules'] ) && is_array( $sanitized['rules'] ) ? $sanitized['rules'] : array();
	error_log('Existing Rules: ' . print_r($sanitized, true));

	error_log('WMB Sanitize Settings Input: ' . print_r($input, true));
	error_log('Raw $_POST: ' . print_r($_POST, true));
	

	$new_rules = $existing_rules; // Start with existing rules
	$existing_rules_count = count( $sanitized['rules'] );
	$new_rule_index = $existing_rules_count > 0 ? $existing_rules_count++ : 0;

	if ( isset( $input['rules'] ) && is_array( $input['rules'] ) ) {
		// Set index_counter to highest existing key + 1, or 0 if empty
		//$existing_keys = array_keys($existing_rules);
		//$index_counter = !empty($existing_keys) ? max(array_map('intval', $existing_keys)) + 1 : 0;
		//foreach ($input['rules'] as $index => $rule) {
			// Skip empty new rule
			//if ($index === 'new' && empty($rule['product_tag']) && empty($rule['title'])) {
			//	continue;
			//}
			// Log invalid product_tag but still save the rule
			//if (!empty($rule['product_tag']) && !term_exists($rule['product_tag'], 'product_tag')) {
			//	error_log('WMB Invalid product_tag: ' . $rule['product_tag'] . ' for rule index ' . $index);
			//}

			// Use a new index for 'new' rules, preserve existing indices
			//$new_index = strpos($index, 'new') === 0 ? $index_counter++ : $index;
			// Merge with existing rule if it exists, otherwise start fresh
			//$existing_rule = isset($existing_rules[$new_index]) ? $existing_rules[$new_index] : array();
			/*$new_rules[$new_index] = array(
				'title'       => sanitize_text_field( isset( $rule['title'] ) ? $rule['title'] : ( $existing_rule['title'] ?? 'Recommended Products' ) ),
				'cart_total' => absint(isset($rule['cart_total']) ? $rule['cart_total'] : ($existing_rule['cart_total'] ?? 0)),
				'day' => sanitize_text_field(isset($rule['day']) ? $rule['day'] : ($existing_rule['day'] ?? '')),
				'product_tag' => sanitize_text_field(isset($rule['product_tag']) ? $rule['product_tag'] : ($existing_rule['product_tag'] ?? '')),
				'category' => sanitize_text_field(isset($rule['category']) ? $rule['category'] : ($existing_rule['category'] ?? '')),
				'attribute' => sanitize_text_field(isset($rule['attribute']) ? $rule['attribute'] : ($existing_rule['attribute'] ?? '')),
				'price_min' => floatval(isset($rule['price_min']) ? $rule['price_min'] : ($existing_rule['price_min'] ?? 0)),
				'amplifier' => sanitize_text_field(isset($rule['amplifier']) ? $rule['amplifier'] : ($existing_rule['amplifier'] ?? 'date')),
				'location' => sanitize_text_field(isset($rule['location']) ? $rule['location'] : ($existing_rule['location'] ?? 'product')),
				'layout' => sanitize_text_field(isset($rule['layout']) ? $rule['layout'] : ($existing_rule['layout'] ?? 'slider')),
				'priority' => absint(isset($rule['priority']) ? $rule['priority'] : ($existing_rule['priority'] ?? 10)),
			);*/
			//error_log('Updated Rule [' . $new_index . ']: ' . print_r($new_rules[$new_index], true));
		//}
	}

	//$sanitized = array( 'rules' => array() );
	//$sanitized['rules'] = $new_rules;
	//$sanitized['stock_threshold'] = absint($input['stock_threshold'] ?? 5);
	//error_log('WMB Sanitized Settings: ' . print_r($sanitized, true));
	return $sanitized;
}

// Analytics section
function wmb_analytics_section() {
	if (!wmb_is_premium()) {
		echo '<p>Upgrade to premium to track impressions, clicks, and conversions. <a href="#">Learn more</a>.</p>';
		return;
	}
	$analytics = get_option('wmb_analytics', array('impressions' => 0, 'clicks' => 0));
	?>
	<p>Recommendation Analytics:</p>
	<ul>
		<li>Impressions: <?php echo esc_html($analytics['impressions']); ?></li>
		<li>Clicks: <?php echo esc_html($analytics['clicks']); ?></li>
		<li>Conversions: Coming soon in a future update!</li>
	</ul>
	<?php
}

// Settings page
function wmb_admin_menu() {
	add_submenu_page(
		'woocommerce',
		'Merchandising Booster',
		'Merchandising Booster',
		'manage_woocommerce',
		'wmb',
		'wmb_settings_page'
	);
}
add_action('admin_menu', 'wmb_admin_menu');

// Render settings page with tabs
function wmb_settings_page() {
	$settings = get_option('wmb_settings', array());
	if (empty($settings['rules'])) {
		echo '<div class="notice notice-warning"><p>No rules defined. Add a rule to enable recommendations.</p></div>';
	}
	if (isset($_POST['submit'])) {
		error_log('Settings POST: ' . print_r($_POST, true));
	}
	$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'rules';
	?>
	<div class="wrap">
		<h1>WooCommerce Dynamic Merchandising Booster</h1>
		<h2 class="nav-tab-wrapper">
			<a href="?page=wmb&tab=rules" class="nav-tab <?php echo $active_tab === 'rules' ? 'nav-tab-active' : ''; ?>">Rules</a>
			<a href="?page=wmb&tab=analytics" class="nav-tab <?php echo $active_tab === 'analytics' ? 'nav-tab-active' : ''; ?>">Analytics</a>
		</h2>
		<?php if ($active_tab === 'rules') : ?>
			<form method="post" action="options.php">
				<?php
				settings_fields('wmb_options');
				do_settings_sections('wmb_settings');
				submit_button();
				?>
				<p><button type="button" onclick="wmbAddRule()">Add Another Rule</button></p>
			</form>
			<div id="wmb-rules">
				<?php wmb_rules(); ?>
			</div>
		<?php else : ?>
			<div id="wmb-analytics">
				<?php wmb_analytics_section(); ?>
			</div>
		<?php endif; ?>
		<script>
		let ruleCounter = 0;
		function wmbAddRule() {
			
			//const rulesDiv = document.getElementById('wmb-rules');
			//const newRule = rulesDiv.querySelector('.wmb-rule:last-child').cloneNode(true);
			newRule.querySelectorAll('input, select').forEach(input => {
				const nameMatch = input.name.match(/\[rules\]\[([^\]]*)\]/);
				const newIndex = 'new_' + (ruleCounter++);
				input.name = input.name.replace(/\[rules\]\[([^\]]*)\]/, `[rules][${newIndex}]`);
				input.value = input.tagName === 'INPUT' ? '' : input.options[0].value;
			});
			rulesDiv.appendChild(newRule);
		}
		</script>
	</div>
	<?php
}

// Render rules field
function wmb_rules() {
	$settings = get_option('wmb_settings', array());
	$rules = $settings['rules'] ?? array();
	$rules_count = count( $rules );
	$rules_index = $rules_count > 0 ? $rules_count + 1 : 0;
	error_log( "Count of Rules " . $rules_count );
	$stock_threshold = $settings['stock_threshold'] ?? 5;
	$is_premium = wmb_is_premium();
	$output = '';

	$output .= '<p>Configure rules for product recommendations. <a href="#">Upgrade to premium</a> for advanced filters and analytics.</p>'; 

	// New rule
	$output .= wmb_rules_fields( $rules_index, $rule, $is_premium, true );

	// Existing rules
	foreach ($rules as $index => $rule) {
		$output .= wmb_rules_fields( $index, $rule, $is_premium, false );
		
	}

	echo $output;
}

function wmb_rules_fields( $index, $rule, $is_premium, $is_new_rule ) {


	$output = '';
	$output .= '<div class="wmb-rule '. ( $is_new_rule ? 'is-new ' : '' ) .' ">';
	$output .= '<div class="wmb-field wmb-title-field">';
	$output .= '<label>Rule Title:</label>';
	$output .= '<input type="text" name="wmb_settings[rules]['.esc_attr($index).'][title]" value="'.esc_attr($rule['title']).'">';
	$output .= '</div>';
	$output .= '<div class="wmb-field wmb-cart-total-field">';
	$output .= '<label>Cart Total Greater Than ($):</label>';
	$output .= '<input type="number" name="wmb_settings[rules]['.esc_attr($index).'][cart_total]" value="'.esc_attr($rule['cart_total']).'">';
	$output .= '</div>';
	$output .= '<div class="wmb-field wmb-day-field">';
	$output .= '<label>Day of Week:</label>';
	$output .= '<select name="wmb_settings[rules]['.esc_attr($index).'][day]">';
	$output .= '<option value="">Any</option>';
	$output .= '<option ' . ( $rule['day'] === "Mon" ? ' selected' : '' ) . ' value="Mon">Monday</option>';
	$output .= '<option ' . ( $rule['day'] === "Tue" ? ' selected' : '' ) . ' value="Tue">Tuesday</option>';
	$output .= '<option ' . ( $rule['day'] === "Wed" ? ' selected' : '' ) . ' value="Wed">Wednesday</option>';
	$output .= '<option ' . ( $rule['day'] === "Thu" ? ' selected' : '' ) . ' value="Thu">Thursday</option>';
	$output .= '<option ' . ( $rule['day'] === "Fri" ? ' selected' : '' ) . ' value="Fri">Friday</option>';
	$output .= '<option ' . ( $rule['day'] === "Sat" ? ' selected' : '' ) . ' value="Sat">Saturday</option>';
	$output .= '<option ' . ( $rule['day'] === "Sun" ? ' selected' : '' ) . ' value="Sun">Sunday</option>';
	$output .= '</select>';
	$output .= '</div>';
	$output .= '<div class="wmb-field wmb-product-tag-field">';
	$output .= '<label>Product Tag (slug):</label>';
	$output .= '<input type="text" name="wmb_settings[rules]['.esc_attr($index).'][product_tag]" value="'.esc_attr($rule['product_tag']).'">';
	$output .= '</div>';
	if ($is_premium) {
		$output .= '<div class="wmb-field wmb-category-field">';
		$output .= '<label>Category (slug):</label>';
		$output .= '<input type="text" name="wmb_settings[rules]['.esc_attr($index).'][category]" value="'.esc_attr($rule['category']).'">';
		$output .= '</div>';
		$output .= '<div class="wmb-field wmb-attribute-field">';
		$output .= '<label>Attribute (slug=value):</label>';
		$output .= '<input type="text" name="wmb_settings[rules]['.esc_attr($index).'][attribute]" value="'.esc_attr($rule['attribute']).'" placeholder="e.g., pa_size=large">';
		$output .= '</div>';
		$output .= '<div class="wmb-field wmb-price-min-field">';
		$output .= '<label>Minimum Price ($):</label>';
		$output .= '<input type="number" step="0.01" name="wmb_settings[rules]['.esc_attr($index).'][price_min]" value="'.esc_attr($rule['price_min']).'">';
		$output .= '</div>';
		$output .= '<div class="wmb-field wmb-amplifier-field">';
		$output .= '<label>Sort By:</label>';
		$output .= '<select name="wmb_settings[rules]['.esc_attr($index).'][amplifier]"><option value="date"'.($rule['amplifier'] === 'date' ? ' selected' : '').'>Date</option><option value="popularity"'.($rule['amplifier'] === 'popularity' ? ' selected' : '').'>Popularity</option><option value="rating"'.($rule['amplifier'] === 'rating' ? ' selected' : '').'>Rating</option></select></div>';
		$output .= '<div class="wmb-field wmb-location-field">';
		$output .= '<label>Location:</label>';
		$output .= '<select name="wmb_settings[rules]['.esc_attr($index).'][location]">';
		$output .= '<option value="product"'.($rule['location'] === 'product' ? ' selected' : '').'>Product Page</option>';
		$output .= '<option value="cart"'.($rule['location'] === 'cart' ? ' selected' : '').'>Cart Page</option>';
		$output .= '<option value="checkout"'.($rule['location'] === 'checkout' ? ' selected' : '').'>Checkout Page</option>';
		$output .= '<option value="shop"'.($rule['location'] === 'shop' ? ' selected' : '').'>Shop Page</option>';
		$output .= '</select>';
		$output .= '</div>';
		$output .= '<div class="wmb-field wmb-layout-field">';
		$output .= '<label>Layout:</label>';
		$output .= '<select name="wmb_settings[rules]['.esc_attr($index).'][layout]">';
		$output .= '<option value="slider"'.($rule['layout'] === 'slider' ? ' selected' : '').'>Slider</option>';
		$output .= '<option value="grid"'.($rule['layout'] === 'grid' ? ' selected' : '').'>Grid</option>';
		$output .= '</select>';
		$output .= '</div>';
	}
	$output .= '<div class="wmb-field wmb-priority-field"><label>Priority:</label><input type="number" name="wmb_settings[rules]['.esc_attr($index).'][priority]" value="'.esc_attr($rule['priority']).'"></div>';
	$output .= '<hr class="wmb-rule-divider"></div>';

	return $output;
}
