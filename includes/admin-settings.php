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
	if (isset($_GET['page']) && in_array($_GET['page'], array('wmb-settings-page', 'wmb'))) {
		
		$wmb_handle = wp_enqueue_style('wmb-admin-styles', WMB_PLUGIN_URL . 'css/wmb-admin.css', array(), '2.1.1');
		wp_enqueue_script('wmb-admin-scripts', WMB_PLUGIN_URL . 'js/wmb-admin.js', array('jquery'), '2.1.1', array('strategy' => 'defer'));
		
		if (!$wmb_handle) {
			error_log('WMB: Failed to enqueue wmb-admin-styles' );
		}
	} else {
		error_log('WMB: Not enqueuing styles/scripts, page is ' . ($_GET['page'] ?? 'none'));
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
			'wmb_main', // id
			'Merchandising Rules', // Title
			'wmb_settings_section', // callback
			'wmb-settings-page' // page slug, used in add_settings_field()
			);
	
	// Register rules field
	add_settings_field(
			'wmb_rules', // id
			__('Recommendation Rules', 'woocommerce-merchandising-booster'), // title
			'wmb_rules', // callback
			'wmb-settings-page', // page slug, used in add_settings_field
			'wmb_main' // section
			);
	
	// Register stock threshold field
	add_settings_field(
			'wmb_stock_threshold',
			__('Low Stock Threshold', 'woocommerce-merchandising-booster'),
			function() {
				$settings = get_option('wmb_settings', array());
				$stock_threshold = $settings['stock_threshold'] ?? 5;
				?>
				<input type="number" name="wmb_settings[stock_threshold]" value="<?php echo esc_attr($stock_threshold); ?>" class="w-24 p-2 border rounded-md focus:ring-2 focus:ring-blue-500">
				<p class="text-gray-500 text-sm mt-1">Set the stock level to trigger low stock alerts.</p>
				<?php
			},
			'wmb-settings-page',
			'wmb_main'
	);
}
add_action('admin_init', 'wmb_register_settings');

// Settings Section
function wmb_settings_section() {
	echo '<p class="text-gray-600">Define rules for dynamic product recommendations. Premium users can add advanced filters, variation support, and analytics. <a href="#" class="text-blue-500 hover:underline">Learn more</a>.</p>';
}

// Analytics section
function wmb_analytics_section() {
	if (!wmb_is_premium()) {
		echo '<p class="text-gray-600">Upgrade to premium to track impressions, clicks, and conversions. <a href="#" class="text-blue-500 hover:underline">Learn more</a>.</p>';
		return;
	}
	$analytics = get_option('wmb_analytics', array('impressions' => 0, 'clicks' => 0));
	?>
	<div class="bg-white p-6 rounded-lg shadow-md">
		<h3 class="text-lg font-semibold mb-4">Recommendation Analytics</h3>
		<ul class="list-disc pl-5">
			<li>Impressions: <?php echo esc_html($analytics['impressions']); ?></li>
			<li>Clicks: <?php echo esc_html($analytics['clicks']); ?></li>
			<li>Conversions: Coming soon in a future update!</li>
		</ul>
	</div>
	<?php
}

// Settings page
function wmb_admin_menu() {
	add_submenu_page(
		'woocommerce',
		'Merchandising Booster',
		'Merchandising Booster',
		'manage_woocommerce',
		'wmb-settings-page',
		'wmb_settings_page'
	);
}
add_action('admin_menu', 'wmb_admin_menu');

// Render settings page with tabs
function wmb_settings_page() {
	$settings = get_option('wmb_settings', array());
	if (empty($settings['rules'])) {
		echo '<div class="notice notice-warning is-dismissible"><p>No rules defined. Add a rule to enable recommendations.</p></div>';
	}
	if (isset($_POST['submit'])) {
		error_log('Settings POST: ' . print_r($_POST, true));
	}
	$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'rules';
	?>
	<div class="wrap max-w-4xl mx-auto">
		<h1 class="text-3xl font-bold mb-6">WooCommerce Dynamic Merchandising Booster</h1>
		<h2 class="nav-tab-wrapper mb-6 border-b-2 border-gray-200">
			<a href="?page=wmb-settings-page&tab=rules" class="nav-tab <?php echo $active_tab === 'rules' ? 'nav-tab-active bg-white border-b-0 text-blue-600' : 'text-gray-600'; ?> px-4 py-2">Rules</a>
			<a href="?page=wmb-settings-page&tab=analytics" class="nav-tab <?php echo $active_tab === 'analytics' ? 'nav-tab-active bg-white border-b-0 text-blue-600' : 'text-gray-600'; ?> px-4 py-2">Analytics</a>
		</h2>
		<?php if ($active_tab === 'rules') : ?>
			<div id="wmb-rules" class="bg-white p-6 rounded-lg shadow-md">
				<form method="post" action="options.php">
					<?php
						settings_fields('wmb_options');
						do_settings_sections('wmb-settings-page');
						submit_button('Save Rules', 'primary', 'submit', false, array('class' => 'mt-4 bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-md'));
					?>
					<p><button type="button" onclick="wmbAddRule()" class="mt-4 bg-green-500 hover:bg-green-600 text-white font-semibold py-2 px-4 rounded-md">Add New Rule</button></p>
				</form>
			
				<?php //wmb_rules(); ?>
			</div>
		<?php else : ?>
			<div id="wmb-analytics" class="bg-white p-6 rounded-lg shadow-md">
				<?php wmb_analytics_section(); ?>
			</div>
		<?php endif; ?>
		<script>
		let ruleCounter = 0;
		function wmbAddRule() {
			const rulesDiv = document.getElementById('wmb-rule');
			jQuery('.wmb-rule.is-new').removeClass('hidden');
			//rulesDiv.removeClass('hidden');
			//const rulesDiv = document.getElementById('wmb-rules');
			//const newRule = rulesDiv.querySelector('.wmb-rule:last-child').cloneNode(true);
			//newRule.querySelectorAll('input, select').forEach(input => {
			///	const nameMatch = input.name.match(/\[rules\]\[([^\]]*)\]/);
			//	const newIndex = 'new_' + (ruleCounter++);
			//	input.name = input.name.replace(/\[rules\]\[([^\]]*)\]/, `[rules][${newIndex}]`);
			//	input.value = input.tagName === 'INPUT' ? '' : input.options[0].value;
			//});
			//rulesDiv.appendChild(newRule);
		}
		</script>
	</div>
	<?php
}

// Render rules field
function wmb_rules() {
	$settings = get_option('wmb_settings', array());
error_log( "Settings " . print_r( $settings, true ) );
	$rules = $settings['rules'] ?? array();
	$rules_count = count( $rules );
	$rules_index = $rules_count > 0 ? $rules_count : 0;
error_log( "Count of Rules " . $rules_count );
	$stock_threshold = $settings['stock_threshold'] ?? 5;
	$is_premium = wmb_is_premium();
	$output = '';

	$output .= '<p class="text-gray-600">Configure rules for product recommendations. <a href="#" class="text-blue-500 hover:underline">Upgrade to premium</a> for advanced filters and analytics.</p>'; 

	// New rule
	$new_rule = array(
		'rule_id'     => 0,
		'title'       => '',
		'cart_total'  => 0,
		'day'         => '',
		'product_tag' => '',
		'category'    => '',
		'attribute'   => '',
		'price_min'   => '',
		'amplifier'   => 'date',
		'location'    => 'product',
		'layout'      => 'slider',
		'priority'    => 10
	);

	// New rule
	$output .= '<p class="text-lg font-semibold">New Rule</p>';
	$output .= wmb_rules_fields( $rules_index, $new_rule, $is_premium, true, true );

	// Existing rules
//error_log( "Loading Existing Settings " . print_r( $settings, true ) );
	$output .= '<p class="text-lg font-semibold mt-6">Existing Rules</p>';
	foreach ($rules as $index => $rule ) {
		$output .= wmb_rules_fields( $index, $rule, $is_premium, false, false );
		//$output .= '<div class="wmb-rule '. $is_new . ' ' . $hide .' ">';
		//$output .= '<div class="wmb-field wmb-title-field">';
		//$output .= '<label>Rule Title:</label>';
		//$output .= $rule['title'];
		//$output .= '</div>';
	}

	echo $output;
}

// Sanitize settings
function wmb_sanitize_settings( $input ) {
	// Load existing settings to preserve unchanged rules
	$sanitized = get_option( 'wmb_settings', array() );

	$existing_rules = isset( $sanitized['rules'] ) && is_array( $sanitized['rules'] ) ? $sanitized['rules'] : array();
	error_log('WMB Existing Settings: ' . print_r($sanitized, true));

	error_log('WMB Sanitize Input: ' . print_r($input, true));
	//error_log('Raw $_POST: ' . print_r($_POST, true));


	//$new_rules = $existing_rules; // Start with existing rules
	//$existing_rules_count = count( $existing_rules );
	//$new_rule_index = $existing_rules_count > 0 ? $existing_rules_count : 0;

	$updated_rules = array();
	if ( isset( $input['rules'] ) && is_array( $input['rules'] ) ) {

		// Additional rules options
		// daterange, seasonal,past_orders,location:taxonomy-term
		// Use a new index for 'new' rules, preserve existing indices
		//$new_index = strpos($index, 'new') === 0 ? $index_counter++ : $index;
//error_log( "Let\'s check the title ". $input['rules'][$new_rule_index]['title'] );
		foreach( $input['rules'] as $index => $rule ) {
			$updated_rules['rules'][$index] = array(
				'title'       => sanitize_text_field( isset( $rule['title'] ) ? $rule['title'] : 'Recommended Products' ),
			/*	'cart_total'  => absint(isset($rule['cart_total']) ? $rule['cart_total'] : ($existing_rules['cart_total'] ?? 0)),
				'day'         => sanitize_text_field(isset($rule['day']) ? $rule['day'] : ($existing_rules['day'] ?? '')),
				'product_tag' => sanitize_text_field(isset($rule['product_tag']) ? $rule['product_tag'] : ($existing_rules['product_tag'] ?? '')),
				'category'    => sanitize_text_field(isset($rule['category']) ? $rule['category'] : ($existing_rules['category'] ?? '')),
				'attribute'   => sanitize_text_field(isset($rule['attribute']) ? $rule['attribute'] : ($existing_rules['attribute'] ?? '')),
				'price_min'   => floatval(isset($rule['price_min']) ? $rule['price_min'] : ($existing_rules['price_min'] ?? 0)),
				'amplifier'   => sanitize_text_field(isset($rule['amplifier']) ? $rule['amplifier'] : ($existing_rules['amplifier'] ?? 'date')),
				'location'    => sanitize_text_field(isset($rule['location']) ? $rule['location'] : ($existing_rules['location'] ?? 'product')),
				'layout'      => sanitize_text_field(isset($rule['layout']) ? $rule['layout'] : ($existing_rules['layout'] ?? 'slider')),
				'priority'    => absint(isset($rule['priority']) ? $rule['priority'] : ($existing_rules['priority'] ?? 10)),
			*/
			);
			//error_log('Updated Rule [' . $new_rule_index . ']: ' . print_r($new_rules[$new_rule_index], true));
			
		}
	}

	//$sanitized = array( 'rules' => array() );
	//$sanitized['rules'] = $new_rules;
	//$updated_rules = array_merge( $existing_rules, $new_rules );
	//$sanitized['stock_threshold'] = absint($input['stock_threshold'] ?? 5);
	//error_log('WMB Sanitized Settings: ' . print_r($sanitized, true));
	return $updated_rules;
}

// Rule Form
function wmb_rules_fields( $index, $rule, $is_premium, $is_new_rule, $hidden ) {
	$is_new = $is_new_rule ? 'is-new' : '';
	$hide = '';
	if ( $is_new ) {
		$hide = $hidden ? 'hidden' : '';
	}

	$output = '';
	$output .= '<div class="wmb-rule bg-gray-50 p-4 rounded-lg shadow-sm mt-4 '. $is_new . ' ' . $hide .' " data-index="' . esc_attr($index) . '">';
	$output .= '<div class="flex justify-between items-center mb-4">';
	$output .= '<h3 class="text-lg font-semibold">' . ($is_new ? 'New Rule' : 'Rule: ' . esc_html($rule['title'])) . '</h3>';
	$output .= '<button type="button" onclick="wmbToggleRule(this)" class="text-blue-500 hover:text-blue-700">Toggle</button>';
	$output .= '</div>';
	$output .= '<div class="wmb-rule-content space-y-4">';
	$output .= '<div class="flex items-center">';
	$output .= '<label class="w-40 font-medium">Rule Title:</label>';
	$output .= '<input type="text" name="wmb_settings[rules]['.esc_attr($index).'][title]" value="'. esc_attr( $rule['title'] ) .'" class="flex-1 p-2 border rounded-md focus:ring-2 focus:ring-blue-500">';
	$output .= '<p class="text-gray-500 text-sm ml-2">Enter a descriptive title for the rule.</p>';
	$output .= '</div>';
	$output .= '</div>';
	// @todo remove
	$output .= '</div>';
	// @todo remove
	/*$output .= '<div class="wmb-field wmb-cart-total-field">';
	$output .= '<label>Cart Total Greater Than ($):</label>';
	$output .= '<input type="number" name="wmb_settings[rules]['.esc_attr($index).'][cart_total]" value="'. esc_attr($rule[$index]['cart_total']).'">';
	$output .= '</div>';
	$output .= '<div class="wmb-field wmb-day-field">';
	$output .= '<label>Day of Week:</label>';
	$output .= '<select name="wmb_settings[rules]['. esc_attr($index).'][day]">';
	$output .= '<option value="">Any</option>';
	$output .= '<option ' . ( $rule[$index]['day'] === "Mon" ? ' selected' : '' ) . ' value="Mon">Monday</option>';
	$output .= '<option ' . ( $rule[$index]['day'] === "Tue" ? ' selected' : '' ) . ' value="Tue">Tuesday</option>';
	$output .= '<option ' . ( $rule[$index]['day'] === "Wed" ? ' selected' : '' ) . ' value="Wed">Wednesday</option>';
	$output .= '<option ' . ( $rule[$index]['day'] === "Thu" ? ' selected' : '' ) . ' value="Thu">Thursday</option>';
	$output .= '<option ' . ( $rule[$index]['day'] === "Fri" ? ' selected' : '' ) . ' value="Fri">Friday</option>';
	$output .= '<option ' . ( $rule[$index]['day'] === "Sat" ? ' selected' : '' ) . ' value="Sat">Saturday</option>';
	$output .= '<option ' . ( $rule[$index]['day'] === "Sun" ? ' selected' : '' ) . ' value="Sun">Sunday</option>';
	$output .= '</select>';
	$output .= '</div>';
	$output .= '<div class="wmb-field wmb-product-tag-field">';
	$output .= '<label>Product Tag (slug):</label>';
	$output .= '<input type="text" name="wmb_settings[rules]['.esc_attr($index).'][product_tag]" value="'. esc_attr($rule[$index]['product_tag']).'">';
	$output .= '</div>';
	if ($is_premium) {
		$output .= '<div class="wmb-field wmb-category-field">';
		$output .= '<label>Category (slug):</label>';
		$output .= '<input type="text" name="wmb_settings[rules]['.esc_attr($index).'][category]" value="'. esc_attr($rule[$index]['category']).'">';
		$output .= '</div>';
		$output .= '<div class="wmb-field wmb-attribute-field">';
		$output .= '<label>Attribute (slug=value):</label>';
		$output .= '<input type="text" name="wmb_settings[rules]['. esc_attr($index).'][attribute]" value="'. esc_attr($rule[$index]['attribute']).'" placeholder="e.g., pa_size=large">';
		$output .= '</div>';
		$output .= '<div class="wmb-field wmb-price-min-field">';
		$output .= '<label>Minimum Price ($):</label>';
		$output .= '<input type="number" step="0.01" name="wmb_settings[rules]['.esc_attr($index).'][price_min]" value="'. esc_attr($rule[$index]['price_min]).'">';
		$output .= '</div>';
		$output .= '<div class="wmb-field wmb-amplifier-field">';
		$output .= '<label>Sort By:</label>';
		$output .= '<select name="wmb_settings[rules]['.esc_attr($index).'][amplifier]">';
		$output .= '<option value="date"'.($rule[$index]['amplifier'] === 'date' ? ' selected' : '').'>Date</option>';
		$output .= '<option value="popularity"'.($rule[$index]['amplifier'] === 'popularity' ? ' selected' : '').'>Popularity</option>';
		$output .= '<option value="rating"'.($rule[$index]['rating'] === 'rating' ? ' selected' : '').'>Rating</option>';
		$output .= '</select>';
		$output .= '</div>';
		$output .= '<div class="wmb-field wmb-location-field">';
		$output .= '<label>Location:</label>';
		$output .= '<select name="wmb_settings[rules]['.esc_attr($index).'][location]">';
		$output .= '<option value="product"'.($rule[$index]['location'] === 'product' ? ' selected' : '').'>Product Page</option>';
		$output .= '<option value="cart"'.($rule[$index]['location'] === 'cart' ? ' selected' : '').'>Cart Page</option>';
		$output .= '<option value="checkout"'.($rule[$index]['location'] === 'checkout' ? ' selected' : '').'>Checkout Page</option>';
		$output .= '<option value="shop"'.($rule[$index]['location'] === 'shop' ? ' selected' : '').'>Shop Page</option>';
		$output .= '</select>';
		$output .= '</div>';
		$output .= '<div class="wmb-field wmb-layout-field">';
		$output .= '<label>Layout:</label>';
		$output .= '<select name="wmb_settings[rules]['.esc_attr($index).'][layout]">';
		$output .= '<option value="slider"'.($rule[$index]['layout'] === 'slider' ? ' selected' : '').'>Slider</option>';
		$output .= '<option value="grid"'.($rule[$index]['layout'] === 'grid' ? ' selected' : '').'>Grid</option>';
		$output .= '</select>';
		$output .= '</div>';
	}
	$output .= '<div class="wmb-field wmb-priority-field"><label>Priority:</label><input type="number" name="wmb_settings[rules]['.esc_attr($index).'][priority]" value="'. esc_attr($rule[$index]['priority']).'"></div>';
	$output .= '<hr class="wmb-rule-divider"></div>';
*/
	return $output;
}

function isset_array_offset( $array_key ) {
	return isset( $array_key ) ? esc_attr( $array_key ) : '';
}
?>