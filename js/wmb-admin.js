/* WooCommerce Merchandising Booster Admin Scripts */
jQuery(document).ready(function($) {
	window.wmbAddRule = function() {
		const rulesDiv = $('#wmb-rule');
		$('.wmb-rule.is-new').removeClass('hidden').hide().slideDown(300);
		//rulesDiv.removeClass('hidden');
		//const rulesDiv = document.getElementById('wmb-rules');
		//const newRule = rulesDiv.querySelector('.wmb-rule:last-child').cloneNode(true);
		//newRule.querySelectorAll('input, select').forEach(input => {
		//	const nameMatch = input.name.match(/\[rules\]\[([^\]]*)\]/);
		//	const newIndex = 'new_' + (ruleCounter++);
		//	input.name = input.name.replace(/\[rules\]\[([^\]]*)\]/, `[rules][${newIndex}]`);
		//	input.value = input.tagName === 'INPUT' ? '' : input.options[0].value;
		//});
		//rulesDiv.appendChild(newRule);
	};

	window.wmbToggleRule = function(button) {
		$(button).closest('.wmb-rule').find('.wmb-rule-content').slideToggle(300);
	};
});