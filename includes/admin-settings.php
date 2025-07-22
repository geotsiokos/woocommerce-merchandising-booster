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
	register_setting('wmb_options', 'wmb_settings', array(
			'sanitize_callback' => 'wmb_sanitize_settings',
	));
	
	add_settings_section('wmb_main', 'Merchandising Rules', function() {
		echo '<p>Define rules for dynamic product recommendations. Premium users can add advanced filters, variation support, and analytics.</p>';
	}, 'wmb_rules');
}
add_action('admin_init', 'wmb_register_settings');

// Sanitize settings
function wmb_sanitize_settings($input) {
	$sanitized = get_option('wmb_settings', array());
	$new_rules = array();
	
	if (isset($input['rules']) && is_array($input['rules'])) {
		foreach ($input['rules'] as $index => $rule) {
			if ($index === 'new' && empty($rule['product_tag']) && empty($rule['title'])) {
				continue;
			}
			if (!term_exists($rule['product_tag'], 'product_tag')) {
				error_log('WMB Invalid product_tag: ' . $rule['product_tag']);
				continue;
			}
			$new_rules[$index] = array(
					'cart_total' => absint($rule['cart_total'] ?? 0),
					'day' => sanitize_text_field($rule['day'] ?? ''),
					'product_tag' => sanitize_text_field($rule['product_tag'] ?? ''),
					'category' => sanitize_text_field($rule['category'] ?? ''),
					'attribute' => sanitize_text_field($rule['attribute'] ?? ''),
					'price_min' => floatval($rule['price_min'] ?? 0),
					'amplifier' => sanitize_text_field($rule['amplifier'] ?? 'date'),
					'location' => sanitize_text_field($rule['location'] ?? 'product'),
					'layout' => sanitize_text_field($rule['layout'] ?? 'slider'),
					'title' => sanitize_text_field($rule['title'] ?? 'Recommended Products'),
					'priority' => absint($rule['priority'] ?? 10),
			);
		}
	}
	
	$sanitized['rules'] = $new_rules;
	$sanitized['stock_threshold'] = absint($input['stock_threshold'] ?? 5);
	error_log('WMB Sanitized Settings: ' . print_r($new_rules, true));
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
                do_settings_sections('wmb_rules');
                submit_button();
                ?>
                <p><button type="button" onclick="wmbAddRule()">Add Another Rule</button></p>
            </form>
            <div id="wmb-rules">
                <?php wmb_rules_field(); ?>
            </div>
        <?php else : ?>
            <div id="wmb-analytics">
                <?php wmb_analytics_section(); ?>
            </div>
        <?php endif; ?>
        <script>
        function wmbAddRule() {
            const rulesDiv = document.getElementById('wmb-rules');
            const newRule = rulesDiv.querySelector('.wmb-rule:last-child').cloneNode(true);
            newRule.querySelectorAll('input, select').forEach(input => {
                input.name = input.name.replace(/\[\d+\]/, `[${Date.now()}]`);
                input.value = input.tagName === 'INPUT' ? '' : '';
            });
            rulesDiv.appendChild(newRule);
        }
        </script>
    </div>
<?php
}

// Render rules field
function wmb_rules_field() {
    $settings = get_option('wmb_settings', array());
    error_log( "WMB Settings " . print_r( $settings, true ) );
    $rules = $settings['rules'] ?? array();
    $stock_threshold = $settings['stock_threshold'] ?? 5;
    $is_premium = wmb_is_premium();
?>
    <p>Configure rules for product recommendations. <?php if (!$is_premium) echo '<a href="#">Upgrade to premium</a> for advanced filters and analytics.'; ?></p>
    <?php foreach ($rules as $index => $rule) : ?>
        <div class="wmb-rule">
            <p>
                <label>Rule Title:</label>
                <input type="text" name="wmb_settings[rules][<?php echo esc_attr($index); ?>][title]" value="<?php echo esc_attr($rule['title']); ?>">
            </p>
            <p>
                <label>Cart Total Greater Than ($):</label>
                <input type="number" name="wmb_settings[rules][<?php echo esc_attr($index); ?>][cart_total]" value="<?php echo esc_attr($rule['cart_total']); ?>">
            </p>
            <p>
                <label>Day of Week:</label>
                <select name="wmb_settings[rules][<?php echo esc_attr($index); ?>][day]">
                    <option value="">Any</option>
                    <?php foreach (array('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun') as $day) : ?>
                        <option value="<?php echo $day; ?>" <?php selected($rule['day'], $day); ?>><?php echo $day; ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label>Product Tag (slug):</label>
                <input type="text" name="wmb_settings[rules][<?php echo esc_attr($index); ?>][product_tag]" value="<?php echo esc_attr($rule['product_tag']); ?>">
            </p>
            <?php if ($is_premium) : ?>
                <p>
                    <label>Category (slug):</label>
                    <input type="text" name="wmb_settings[rules][<?php echo esc_attr($index); ?>][category]" value="<?php echo esc_attr($rule['category']); ?>">
                </p>
                <p>
                    <label>Attribute (slug=value):</label>
                    <input type="text" name="wmb_settings[rules][<?php echo esc_attr($index); ?>][attribute]" value="<?php echo esc_attr($rule['attribute']); ?>" placeholder="e.g., pa_size=large">
                </p>
                <p>
                    <label>Minimum Price ($):</label>
                    <input type="number" step="0.01" name="wmb_settings[rules][<?php echo esc_attr($index); ?>][price_min]" value="<?php echo esc_attr($rule['price_min']); ?>">
                </p>
                <p>
                    <label>Sort By:</label>
                    <select name="wmb_settings[rules][<?php echo esc_attr($index); ?>][amplifier]">
                        <option value="date" <?php selected($rule['amplifier'], 'date'); ?>>Date</option>
                        <option value="popularity" <?php selected($rule['amplifier'], 'popularity'); ?>>Popularity</option>
                        <option value="rating" <?php selected($rule['amplifier'], 'rating'); ?>>Rating</option>
                    </select>
                </p>
                <p>
                    <label>Location:</label>
                    <select name="wmb_settings[rules][<?php echo esc_attr($index); ?>][location]">
                        <option value="product" <?php selected($rule['location'], 'product'); ?>>Product Page</option>
                        <option value="cart" <?php selected($rule['location'], 'cart'); ?>>Cart Page</option>
                        <option value="checkout" <?php selected($rule['location'], 'checkout'); ?>>Checkout Page</option>
                        <option value="shop" <?php selected($rule['location'], 'shop'); ?>>Shop Page</option>
                    </select>
                </p>
                <p>
                    <label>Layout:</label>
                    <select name="wmb_settings[rules][<?php echo esc_attr($index); ?>][layout]">
                        <option value="slider" <?php selected($rule['layout'], 'slider'); ?>>Slider</option>
                        <option value="grid" <?php selected($rule['layout'], 'grid'); ?>>Grid</option>
                    </select>
                </p>
            <?php endif; ?>
            <p>
                <label>Priority:</label>
                <input type="number" name="wmb_settings[rules][<?php echo esc_attr($index); ?>][priority]" value="<?php echo esc_attr($rule['priority']); ?>">
            </p>
            <hr>
        </div>
    <?php endforeach; ?>
    <div class="wmb-rule">
        <h4>Add New Rule</h4>
        <p>
            <label>Rule Title:</label>
            <input type="text" name="wmb_settings[rules][new][title]" value="">
        </p>
        <p>
            <label>Cart Total Greater Than ($):</label>
            <input type="number" name="wmb_settings[rules][new][cart_total]" value="0">
        </p>
        <p>
            <label>Day of Week:</label>
            <select name="wmb_settings[rules][new][day]">
                <option value="">Any</option>
                <?php foreach (array('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun') as $day) : ?>
                    <option value="<?php echo $day; ?>"><?php echo $day; ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label>Product Tag (slug):</label>
            <input type="text" name="wmb_settings[rules][new][product_tag]" value="">
        </p>
        <?php if ($is_premium) : ?>
            <p>
                <label>Category (slug):</label>
                <input type="text" name="wmb_settings[rules][new][category]" value="">
            </p>
            <p>
                <label>Attribute (slug=value):</label>
                <input type="text" name="wmb_settings[rules][new][attribute]" value="" placeholder="e.g., pa_size=large">
            </p>
            <p>
                <label>Minimum Price ($):</label>
                <input type="number" step="0.01" name="wmb_settings[rules][new][price_min]" value="0">
            </p>
            <p>
                <label>Sort By:</label>
                <select name="wmb_settings[rules][new][amplifier]">
                    <option value="date">Date</option>
                    <option value="popularity">Popularity</option>
                    <option value="rating">Rating</option>
                </select>
            </p>
            <p>
                <label>Location:</label>
                <select name="wmb_settings[rules][new][location]">
                    <option value="product">Product Page</option>
                    <option value="cart">Cart Page</option>
                    <option value="checkout">Checkout Page</option>
                    <option value="shop">Shop Page</option>
                </select>
            </p>
            <p>
                <label>Layout:</label>
                <select name="wmb_settings[rules][new][layout]">
                    <option value="slider">Slider</option>
                    <option value="grid">Grid</option>
                </select>
            </p>
        <?php endif; ?>
        <p>
            <label>Priority:</label>
            <input type="number" name="wmb_settings[rules][new][priority]" value="10">
        </p>
    </div>
    <p>
        <label>Low Stock Threshold:</label>
        <input type="number" name="wmb_settings[stock_threshold]" value="<?php echo esc_attr($stock_threshold); ?>">
    </p>
<?php
}
?>