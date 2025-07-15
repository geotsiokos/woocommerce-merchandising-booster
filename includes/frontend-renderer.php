<?php
// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

// Enqueue frontend assets
function wmb_enqueue_assets() {
	$locations = array('product', 'shop', 'cart', 'checkout');
	$settings = get_option('wmb_settings', array());
	$rules = $settings['rules'] ?? array();
	$load_assets = false;
	
	foreach ($rules as $rule) {
		$location = $rule['location'] ?? 'product';
		if (in_array($location, $locations) && (
				($location === 'product' && is_product()) ||
				($location === 'shop' && is_shop()) ||
				($location === 'cart' && is_cart()) ||
				($location === 'checkout' && is_checkout())
				)) {
					$load_assets = true;
					break;
				}
	}
	
	if ($load_assets) {
		wp_enqueue_style('wmb-styles', WMB_PLUGIN_URL . 'css/wmb-styles.css', array('wc-block-style', 'woocommerce-general', 'woocommerce-layout', 'storefront-woocommerce-style'), '2.1.1');
		wp_enqueue_script('wmb-scripts', WMB_PLUGIN_URL . 'js/wmb-scripts.js', array('jquery'), '2.1.1', array('strategy' => 'defer'));
		wp_localize_script('wmb-scripts', 'wmbAjax', array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('wmb_nonce'),
		));
	}
}
add_action('wp_enqueue_scripts', 'wmb_enqueue_assets');

// Track analytics (premium)
function wmb_track_click() {
	if (!wmb_is_premium() || !check_ajax_referer('wmb_nonce', 'nonce', false)) {
		wp_send_json_error('Invalid request');
	}
	
	$analytics = get_option('wmb_analytics', array('impressions' => 0, 'clicks' => 0));
	$analytics['clicks'] = ($analytics['clicks'] ?? 0) + 1;
	update_option('wmb_analytics', $analytics);
	wp_send_json_success('Click tracked');
}
add_action('wp_ajax_wmb_track_click', 'wmb_track_click');

// Display personalized product spotlight
function wmb_render_spotlight($location = 'product') {
	$session_key = is_user_logged_in() ? (string) get_current_user_id() : (isset($_COOKIE['wp_woocommerce_session_' . COOKIEHASH]) ? sanitize_text_field($_COOKIE['wp_woocommerce_session_' . COOKIEHASH]) : md5(uniqid('wmb_guest_', true)));
	$cache_key = 'wmb_spotlight_' . md5($session_key . date('Y-m-d') . $location);
	error_log('WMB Cache Key: ' . $cache_key);
	
	$cached_output = get_transient($cache_key);
	if ($cached_output !== false) {
		error_log('WMB Using cached output for key: ' . $cache_key);
		if (wmb_is_premium()) {
			$analytics = get_option('wmb_analytics', array('impressions' => 0, 'clicks' => 0));
			$analytics['impressions'] = ($analytics['impressions'] ?? 0) + 1;
			update_option('wmb_analytics', $analytics);
		}
		echo $cached_output;
		return;
	}
	
	$settings = get_option('wmb_settings', array());
	$rules = $settings['rules'] ?? array();
	error_log('WMB Rules: ' . print_r($rules, true));
	
	if (empty($rules)) {
		$output = '<div class="wmb-fallback">No merchandising rules defined.</div>';
		set_transient($cache_key, $output, HOUR_IN_SECONDS);
		echo $output;
		return;
	}
	
	$cart_total = WC()->cart->get_total('edit');
	$current_day = date('D');
	$output = '';
	
	error_log('WMB Cart Total: ' . $cart_total . ', Current Day: ' . $current_day . ', Location: ' . $location);
	
	usort($rules, function($a, $b) { return $a['priority'] <=> $b['priority']; });
	
	foreach ($rules as $rule) {
		$rule_location = $rule['location'] ?? 'product';
		if ($rule_location !== $location) {
			continue;
		}
		
		$conditions_met = (!$rule['cart_total'] || $cart_total > $rule['cart_total']) &&
		(!$rule['day'] || $current_day === $rule['day']);
		error_log('WMB Rule Check: ' . print_r($rule, true) . ', Conditions Met: ' . ($conditions_met ? 'Yes' : 'No'));
		
		if ($conditions_met) {
			$args = array(
					'post_type' => 'product',
					'post_status' => 'publish',
					'posts_per_page' => 3,
					'fields' => 'ids',
					'tax_query' => array(),
					'meta_query' => array(),
			);
			
			if ($rule['product_tag']) {
				$args['tax_query'][] = array(
						'taxonomy' => 'product_tag',
						'terms' => $rule['product_tag'],
						'field' => 'slug',
				);
			}
			if (wmb_is_premium()) {
				if ($rule['category']) {
					$args['tax_query'][] = array(
							'taxonomy' => 'product_cat',
							'terms' => $rule['category'],
							'field' => 'slug',
					);
				}
				if ($rule['attribute']) {
					list($taxonomy, $value) = explode('=', $rule['attribute']);
					$args['tax_query'][] = array(
							'taxonomy' => sanitize_text_field($taxonomy),
							'terms' => sanitize_text_field($value),
							'field' => 'slug',
					);
				}
				if ($rule['price_min']) {
					$args['meta_query'][] = array(
							'key' => '_price',
							'value' => $rule['price_min'],
							'compare' => '>=',
							'type' => 'NUMERIC',
					);
				}
				if ($rule['amplifier'] === 'popularity') {
					$args['meta_key'] = 'total_sales';
					$args['orderby'] = 'meta_value_num';
					$args['order'] = 'DESC';
				} elseif ($rule['amplifier'] === 'rating') {
					$args['meta_key'] = '_wc_average_rating';
					$args['orderby'] = 'meta_value_num';
					$args['order'] = 'DESC';
				}
			} else {
				$args['post_parent'] = 0;
			}
			
			$query = new WP_Query($args);
			$product_ids = array_unique(array_map('intval', $query->posts));
			error_log('WMB Raw Query Results: ' . print_r($query->posts, true));
			error_log('WMB Product IDs for rule: ' . print_r($product_ids, true));
			
			if (!empty($product_ids)) {
				ob_start();
				?>
				<div class="wmb-spotlight">
					<?php
					$heading_block = array(
						'blockName' => 'core/heading',
						'attrs' => array(
							'level' => 3,
						),
						'innerContent' => array(esc_html($rule['title'])),
					);
					if (function_exists('render_block')) {
						echo render_block($heading_block);
					} else {
						echo '<h3>' . esc_html($rule['title']) . '</h3>';
					}
					?>
					<div class="wmb-recommendations wmb-<?php echo esc_attr($rule['layout']); ?>">
						<ul class="products wmb-products">
							<?php
							global $product, $post;
							$original_post = $post;
							foreach ($product_ids as $product_id) {
								$post = get_post($product_id);
								$product = wc_get_product($product_id);
								if (!$product || !$product->is_visible()) {
									error_log('WMB Skipping product ID ' . $product_id . ': Not visible or invalid');
									continue;
								}
								ob_start();
								wc_get_template('content-product.php', array('product' => $product));
								$template_output = ob_get_clean();
								if (empty(trim($template_output))) {
									error_log('WMB Skipping product ID ' . $product_id . ': Empty template output');
									continue;
								}
								echo $template_output;
							}
							$post = $original_post;
							?>
						</ul>
					</div>
				</div>
				<?php
				$output = ob_get_clean();
				if (wmb_is_premium()) {
					$analytics = get_option('wmb_analytics', array('impressions' => 0, 'clicks' => 0));
					$analytics['impressions'] = ($analytics['impressions'] ?? 0) + 1;
					update_option('wmb_analytics', $analytics);
				}
				error_log('WMB Applied Rule: ' . $rule['title']);
				break;
			} else {
				$output = '<div class="wmb-fallback">No products found for rule: ' . esc_html($rule['title']) . '</div>';
			}
		}
	}

	if (empty($output)) {
		$output = '<div class="wmb-fallback">No matching rules or products found.</div>';
	}

	set_transient($cache_key, $output, HOUR_IN_SECONDS);
	echo $output;
}

// Add recommendation hooks
add_action('woocommerce_after_single_product', function() {
	wmb_render_spotlight('product');
});
add_action('woocommerce_after_cart', function() {
	wmb_render_spotlight('cart');
});
add_action('woocommerce_after_checkout_form', function() {
	wmb_render_spotlight('checkout');
});
add_action('woocommerce_after_shop_loop', function() {
	wmb_render_spotlight('shop');
});

// Shortcode for manual placement
function wmb_shortcode($atts) {
	ob_start();
	wmb_render_spotlight('shortcode');
	return ob_get_clean();
}
add_shortcode('wmb_recommendations', 'wmb_shortcode');

// Display low-stock alert
function wmb_low_stock_alert() {
	global $product;
	$settings = get_option('wmb_settings', array());
	$threshold = $settings['stock_threshold'] ?? 5;

	if ($product && $product->managing_stock() && $product->get_stock_quantity() <= $threshold) {
		$stock_block = array(
			'blockName' => 'core/paragraph',
			'attrs' => array(
				'className' => 'wmb-stock-alert',
			),
			'innerContent' => array(sprintf(esc_html__('Hurry! Only %d left in stock!', 'wmb'), esc_html($product->get_stock_quantity()))),
		);
		if (function_exists('render_block')) {
			echo render_block($stock_block);
		}
	}
}
add_action('woocommerce_single_product_summary', 'wmb_low_stock_alert', 15);

// Clear cache on cart or inventory changes
function wmb_clear_cache() {
	global $wpdb;
	$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wmb_spotlight_%'");
}
add_action('woocommerce_cart_updated', 'wmb_clear_cache');
add_action('woocommerce_product_set_stock', 'wmb_clear_cache');
?>