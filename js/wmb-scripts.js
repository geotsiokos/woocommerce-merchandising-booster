/* WooCommerce Merchandising Booster Scripts */
jQuery(document).ready(function($) {
	$('.wmb-recommendations').on('click', '.product', function() {
		var productName = $(this).find('.woocommerce-loop-product__title').text();
		console.log('Product clicked:', productName);
		$.ajax({
			url: wmbAjax.ajax_url,
			method: 'POST',
			data: {
				action: 'wmb_track_click',
				product_name: productName,
				nonce: wmbAjax.nonce
			},
			success: function(response) {
				console.log('Click tracked:', response);
			}
		});
	});
});