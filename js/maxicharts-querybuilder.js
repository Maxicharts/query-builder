jQuery(document).ready(function($) {
	console.log("Maxicharts query builder");

	function injectFilterDataAndUpdate(jsonFilter, chartId, atts) {
		console.log(chartId);
		console.log(jsonFilter);

		// get new data
		if (0) {
			var randIdx = Math.floor((Math.random() * 10) + 1);
			var randValue = Math.floor((Math.random() * 10) + 1);
			var newData = [randIdx, randValue];
			var newLabels = [randIdx, 'new 1'];

			// update chart
			window.maxicharts_reports_init[chartId].data.datasets[0].data = newData;
			window.maxicharts_reports_init[chartId].data.labels = newLabels;
			window.maxicharts_reports_init[chartId].update();
		} else {

			var data0 = {
				action : 'maxicharts_get_new_data',
				'jsonFilter' : jsonFilter,
			};

			data = $.extend({}, atts, data0);

			console.log(data);
			console.log(maxicharts_ajax_object);
			console.log(maxicharts_ajax_object.ajax_url);

			$.post(maxicharts_ajax_object.ajax_url, data, function(response) {
				console.log("response from WP site " + chartId);

				console.log(response);
				swal.close();
				var newData = [];
				var newLabels = [];

				for ( var property in response) {
					if (response.hasOwnProperty(property)) {
						var data = response[property];
						console.log(property + ' -> ' + data);
						if (typeof data === 'object') {
							for ( var property2 in data) {
								if (data.hasOwnProperty(property2)) {
									var data2 = data[property2];
									console.log(property2 + ' -> ' + data2);
								}

							}
							newData = data['data'];
							newLabels = data['labels'];

						}
					}
				}
				console.log(newData);
				console.log(newLabels);

				window.maxicharts_reports_init[chartId].data.datasets[0].data = newData;
				window.maxicharts_reports_init[chartId].data.labels = newLabels;
				window.maxicharts_reports_init[chartId].update();
				console.log("Chartjs " + chartId + " updated");

				/*
				 * var dataKey = 'Data_' + chartId;
				 * console.log(dataKey);
				 * console.log(responseArray);
				 * 
				 * var newData = responseArray[dataKey]['data'];
				 * var newLabels =
				 * responseArray[dataKey]['labels']; // update
				 * chart
				 * window.maxicharts_reports_init[chartId].data.datasets[0].data =
				 * newData;
				 * window.maxicharts_reports_init[chartId].data.labels =
				 * newLabels;
				 * window.maxicharts_reports_init[chartId].update();
				 * console.log("Chartjs updated");
				 */

			});

			console.log("after response");
			return false;
		}

	}

	function adddata(chartId) {

		var randIdx = Math.floor((Math.random() * 10) + 1);
		var randValue = Math.floor((Math.random() * 10) + 1);
		window.maxicharts_reports_init[chartId].data.datasets[0].data[randIdx] = randValue;
		window.maxicharts_reports_init[chartId].data.labels[randIdx] = "Newly Added";
		window.maxicharts_reports_init[chartId].update();
	}

	$(".maxicharts_builder").each(function(index) {
		console.log(index + ": " + $(this).attr('id'));
		console.log($(this));
		$(this).queryBuilder({
			plugins : [

			/*
			 * 'sortable', 'filter-description', 'unique-filter',
			 * 'bt-tooltip-errors', 'bt-selectpicker',
			 * 'bt-checkbox', 'invert', 'not-group'
			 */
			],

			filters : [{
				id : 'date',
				label : 'Date',
				type : 'date',
				validation : {
					format : 'YYYY/MM/DD'
				},
				plugin : 'datepicker',
				plugin_config : {
					locale : "en",
					format : 'yyyy/mm/dd',
					todayBtn : 'linked',
					todayHighlight : true,
					autoclose : true
				},
				operators : ['equal', 'less', 'less_or_equal', 'greater', 'greater_or_equal', 'between'],
			}, {
				id : 'field_id',
				label : 'Field ID',
				type : 'integer',
				operators : ['equal'],
			}, {
				id : 'field_value',
				label : 'Field Value',
				type : 'string',
				operators : ['equal'],
			}],
		});
	});

	$('.maxicharts_filter').on('click', function() {

		swal("Refreshing...");
		/*, {
		    button : false,
		});*/

		var closestQueryBuilder = $(this).siblings(".maxicharts_builder");
		console.log(closestQueryBuilder);
		var result = closestQueryBuilder.queryBuilder('getRules');
		// FIXME : this is buggy, should inject all possible maxicharts parameters !!!
		var atts = {
			'gf_form_id' : $(this).attr('gf_form_id'),
			'include' : $(this).attr("include"),
			'exclude' : $(this).attr("exclude"),
			'graph_type' : $(this).attr("graph_type"),
			'datasets_invert' : $(this).attr("datasets_invert"),
			'filter' : $(this).attr("filter"),
			'maxentries' : $(this).attr("maxentries"),
		};

		if (!$.isEmptyObject(result)) {
			var jsonStr = JSON.stringify(result, null, 2);
			// alert();
			var t = $(this);
			console.log(t);
			var filterId = t.attr('id');
			console.log(filterId);
			var splitted = filterId.split('_');
			console.log(splitted);
			var chartID = splitted[1];
			console.log(chartID);

			injectFilterDataAndUpdate(jsonStr, chartID, atts);
		}

	});

	function initializeWidget(maxicharts_builder) {

		// get gf form id
		console.log(maxicharts_builder);
		//var associatedFilter = maxicharts_builder.find('.maxicharts_filter');
		var associatedFilter = $( "input.maxicharts_filter");
		console.log(associatedFilter);
		var formID = associatedFilter.attr('gf_form_id');
		console.log("initializeWidget formID");
		console.log("gf changed " + formID);

		data = {
			action : 'qb_get_gf_form_fields',
			form_id : formID,

		};

		swal({
			title : 'Working...',
			text : 'retrieving GF fields',
			type : "info",
			showCloseButton : true,
			showCancelButton : false,

		});

		// retreive all fields and values

		$.post(maxicharts_ajax_object.ajax_url, data, function(response) {
			console.log(response);
			// set all field and values as filters
			var filters = [];

			filters.push({
				id : 'date',
				label : 'Date',
				type : 'date',
				validation : {
					format : 'YYYY/MM/DD'
				},
				plugin : 'datepicker',
				plugin_config : {
					format : 'yyyy/mm/dd',
					todayBtn : 'linked',
					todayHighlight : true,
					autoclose : true
				},
				operators : ['equal', 'less', 'less_or_equal', 'greater', 'greater_or_equal', 'between'],
			});
			/*
			 {
			id : 'field_id',
			label : 'Field ID',
			type : 'integer',
			operators : [ 'equal' ],
			},*/

			Object.keys(response).forEach(function(key) {

				filters.push({
					id : key,
					label : response[key],
					type : 'string',
					//operators : [ 'equal' ],
					operators : ['equal', 'less', 'less_or_equal', 'greater', 'greater_or_equal', 'between'],
				});
			});

			//$('select[class="gf_form_field_key_id"]').empty().append(optionsAsString);
			// http://querybuilder.js.org/plugins.html#change-filters
			console.log(filters);

			$('.maxicharts_builder').queryBuilder('setFilters', true, filters);

			$title = "Done!";
			$type = "success"
			var responseText = 'Form fields retrieved';
			swal({
				title : $title,
				type : $type,
				text : responseText,
				timer : 1000,
				buttons : false,
			});

		});
		return false;

	}

	// initialize widget
	/*
	if ($('.maxicharts_builder').length > 0) {
		console.log($('.maxicharts_builder'));
		initializeWidget();
	}*/
	
	$(".maxicharts_builder").each(function(index) {
		console.log("initialize");
		console.log($(this));
		initializeWidget($(this));
	});

});