$(document).ready(function() {
	$(window).load(function() {
		try {
			var filterValues = JSON.parse($('#filtervalues').val());
		} catch (SyntaxError) {
			var filterValues = {};
		}

		if ($("#price").length) {
			setupSlider(
				'price',
				(filterValues.price.min_price ? Math.round(filterValues.price.min_price) : 0),
				(filterValues.price.max_price ? Math.round(filterValues.price.max_price) : 0),
				(filterValues.price.min_price ? Math.round(filterValues.price.min_price) : 0),
				(filterValues.price.max_price ? Math.round(filterValues.price.max_price) : 0)
			);
		}

		$(".filter_bar form fieldset").append(createNamedFilter("providers", filterValues.providers).prepend($("<span>").text(mw.msg('sub_th_provider_id'))));

		$(".filter_bar form fieldset").append(createNamedFilter("plans", filterValues.plans).prepend($("<span>").text(mw.msg('sub_th_plan_name'))));
	});

	/**
	 * Setup a slider filter.
	 * http://codepen.io/ignaty/pen/EruAe
	 *
	 * @access	public
	 * @param	string	Filter name, also the name of the ID for the main element.
	 * @param	mixed	Integer or float of the absolute minimum allowed value.
	 * @param	mixed	Integer or float of the absolute maximum allowed value.
	 * @param	mixed	Integer or float of the user selected minimum allowed value.
	 * @param	mixed	Integer or float of the user selected minimum allowed value.
	 * @return	void
	 */
	function setupSlider(filterName, absoluteMin, absoluteMax, currentMin, currentMax) {
		var element = $("#"+filterName);
		$(element).slider({
			range: true,
			min: absoluteMin,
			max: absoluteMax,
			values: [currentMin, currentMax],
			slide: function(event, ui) {
				$('.ui-slider-handle:eq(0) .price-range-min', this).html('$' + ui.values[0]);
				$('.ui-slider-handle:eq(1) .price-range-max', this).html('$' + ui.values[1]);
				$('.price-range-both').html('$' + ui.values[0] + ' - $' + ui.values[1]);


				if (slideCollision($('.price-range-min', this), $('.price-range-max', this)) == true) {
					$('.price-range-min, .price-range-max', this).css('opacity', '0');
					$('.price-range-both', this).css('display', 'block');
				} else {
					$('.price-range-min, .price-range-max', this).css('opacity', '1');
					$('.price-range-both', this).css('display', 'none');
				}
			}
		});

		$('.ui-slider-range', $(element)).append('<span class="price-range-both value">$' + $(element).slider('values', 0 ) + ' - ' + $(element).slider('values', 1 ) + '</span>');

		$('.ui-slider-handle:eq(0)', $(element)).append('<span class="price-range-min value">$' + $(element).slider('values', 0 ) + '</span>');

		$('.ui-slider-handle:eq(1)', $(element)).append('<span class="price-range-max value">$' + $(element).slider('values', 1 ) + '</span>');
	}

	//http://codepen.io/ignaty/pen/EruAe
	function slideCollision($div1, $div2) {
		var x1 = $div1.offset().left;
		var w1 = 40;
		var r1 = x1 + w1;
		var x2 = $div2.offset().left;
		var w2 = 40;
		var r2 = x2 + w2;

		if (r1 < x2 || x1 > r2) {
			return false;
		}

		return true;
	}

	function updateRangeDisplay(element, min, max) {
		$(element).text(min+" - "+max);
	}

	/**
	 * Create a named filter set of check boxes.
	 *
	 * @access	public
	 * @param	string	Filter Key/Name to use for form handling.
	 * @param	object	List of items to include in the group filter.
	 * @return	object	Built HTML jQuery Object
	 */
	function createNamedFilter(filterKey, items) {
		var container = $("<div>").addClass('named_filter').append("<ul>");

		console.log(filterKey);
		console.log(items);
		console.log(items.length);
		$.each(items, function(unused, item) {
			var input = $("<li>").append($("<label>").append($("<input>").attr('type', 'checkbox').attr('name', filterKey+"[]").val(item)).append(item));
			$("ul", container).append(input);
		});

		return container;
	}
});