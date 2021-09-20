<?php
/*
 * Plugin Name: MaxiCharts Query Builder Add-on
 * Plugin URI: https://wordpress.org/plugins/maxicharts-query-builder-add-on/
 * Description: Extends MaxiCharts : Add the possibility to build complex query and update chart dynamically
 * Version: 1.2.2
 * Author: MaxiCharts
 * Author URI: https://wordpress.org/plugins/maxicharts/
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: maxicharts
 * Domain Path: /languages
 */
if (! defined('ABSPATH')) {
	exit();
}

require_once __DIR__ . '/libs/vendor/autoload.php';

if (! class_exists('maxicharts_querybuilder_plugin')) {
	
	class maxicharts_querybuilder_plugin
	{
		
		private $shortcode_atts_name = 'filter';
		
		function __construct()
		{
			if (! class_exists('MAXICHARTSAPI')) {
				$msg = __('Please install MaxiCharts before');
				return $msg;
			}
			self::getLogger()->debug("Adding Module : " . __CLASS__);
			
			add_action("wp_enqueue_scripts", array(
				$this,
				"maxicharts_load_scripts"
			));
			
			add_filter("maxicharts_current_chart_filter", array(
				$this,
				"maxicharts_add_filter_widget"
			), 10, 3);
			
			add_action('wp_ajax_maxicharts_get_new_data', array(
				$this,
				'maxicharts_get_new_data'
			));
			
			add_action('wp_ajax_nopriv_maxicharts_get_new_data', array(
				$this,
				'maxicharts_get_new_data'
			));
			
			add_filter("mcharts_modify_custom_search_criteria", array(
				$this,
				"modify_custom_search_criteria"
			), 10, 2);
			
			// update field when form id is known
			add_action('wp_ajax_qb_get_gf_form_fields', array(
				$this,
				'qb_get_gf_form_fields'
			));
			add_action('wp_ajax_nopriv_qb_get_gf_form_fields', array(
				$this,
				'qb_get_gf_form_fields'
			));
		}
		
		function qb_get_gf_form_fields()
		{
			self::getLogger()->debug("getting forms fields...");
			self::getLogger()->debug( $_POST );
			$form_id = sanitize_text_field($_POST['form_id']);
			if (empty($form_id)){
				$form_id = sanitize_text_field($_POST['gf_form_id']);
			}
			
			
			$result = $this->getFormFields($form_id);
			
			echo $result;
			wp_die();
		}
		
		public function getFormFields($form_id)
		{
			self::getLogger()->debug("Get form " . $form_id . " fields");
			if (empty($form_id)) {
				self::getLogger()->error("No ID specified ");
				$form_id = 1;
			}
			$list = array();
			$form = GFAPI::get_form($form_id);
			$form_fields = $form['fields'];
			
			foreach ($form_fields as $field) {
				
				if ($field['type'] == 'page') {
					continue;
				}
				
				$selected = '';
				$field_id = $field['id'];
				$field_label = ! empty($field['label']) ? $field['label'] : 'no label';
				$list[$field_id] = $field_label;
				/*
				 * if (empty($field ['label'])){
				 * bulkusereditor_log ($field);
				 * }
				 */
			}
			
			self::getLogger()->debug($list);
			wp_send_json($list);
			
			// echo $list;
			die();
		}
		
		function modify_custom_search_criteria($search_criteria, $atts)
		{
			self::getLogger()->debug(__CLASS__ . "modify_custom_search_criteria BEFORE");
			self::getLogger()->debug($search_criteria);
			
			self::getLogger()->debug($atts);
			$bool_att = filter_var(trim($atts[$this->shortcode_atts_name]), FILTER_VALIDATE_BOOLEAN);
			//$bool_att = boolval($atts[$this->shortcode_atts_name]);
			self::getLogger()->debug($bool_att);
			if ($bool_att === false) {
				self::getLogger()->debug(__CLASS__ . " not a real time filtering...");
				return $search_criteria;
			}
			$new_search_criteria = array();
			
			foreach ($search_criteria as $key => $datas) {
				if ($key == "condition") {
					/*
					 * $search_criteria['field_filters']['mode'] = 'all'; // default
					 * $search_criteria['field_filters']['mode'] = 'any';
					 */
					$new_search_criteria['field_filters']['mode'] = $datas == 'OR' ? 'any' : 'all';
				} else if ($key == 'rules') {
					foreach ($datas as $groupOrRule) {
						
						MAXICHARTSAPI::getLogger()->debug($groupOrRule);
						if (is_array($groupOrRule) && false !== array_key_exists('id', $groupOrRule)) {
							$rule = $groupOrRule;
							MAXICHARTSAPI::getLogger()->debug($rule);
							// just rule, no group
							$value = $rule['value'];
							$id = $rule['id'];
							if (is_numeric($id)) {
								// FIXME : add less greater etc...
								$new_search_criteria['field_filters'][] = array(
									'key' => $id,
									'value' => $value
								);
							} else if ($id === 'date') {
								
								$operator = $rule['operator'];
								
								switch ($operator) {
									case 'less':
									case 'less_or_equal':
										$start_date = '';
										$end_date = $value;
										break;
									case 'greater':
									case 'greater_or_equal':
										$start_date = $value;
										$end_date = '2100-01-01';
										break;
									case 'between':
										$start_date = $value['0'];
										$end_date = $value['1'];
										break;
									case 'equal':
										$start_date = $end_date = $value;
										break;
								}
								
								$new_search_criteria["start_date"] = date('Y-m-d', strtotime($start_date));
								$new_search_criteria["end_date"] = date('Y-m-d', strtotime($end_date));
							}
						}
					}
				}
			}
			
			self::getLogger()->debug($new_search_criteria);
			return $new_search_criteria;
		}
		
		static function getLogger()
		{
			if (class_exists('MAXICHARTSAPI')) {
				return MAXICHARTSAPI::getLogger('QUERY_BUILDER');
			}
		}
		
		function maxicharts_get_new_data()
		{
			self::getLogger()->debug("maxicharts_get_new_data");
			self::getLogger()->debug($_POST);
			
			// FIXME: need to get all possible parameters !!!!!
			
			$form_id = $_POST['gf_form_id'];
			$type = sanitize_text_field($_POST['graph_type']);
			$include = $_POST['include'];
			$exclude = $_POST['exclude'];
			$atts['gf_form_id'] = $form_id;
			$atts['type'] = $type;
			$atts['include'] = $include;
			$atts['exclude'] = $exclude;
			$atts['maxentries'] = $_POST['maxentries'];
			$atts['filter'] = $_POST['filter'];
			$atts['data_only'] = 'true';
			$atts['datasets_invert'] = $_POST['datasets_invert'];
			
			$json_filter = $_POST['jsonFilter'];
			// self::getLogger()->debug($json_filter);
			// str_replace ( mixed $search , mixed $replace , mixed $subject [, int &$count ] )
			
			$json_filter = str_replace("\\", "", $json_filter);
			/*
			 * $json_filter = str_replace ("]" , "" ,$json_filter);
			 * $json_filter = str_replace ("[" , "" ,$json_filter);
			 */
			$atts['custom_search_criteria'] = $json_filter;
			// self::getLogger()->debug($json_filter);
			$custom_filter = json_decode($json_filter, true);
			self::getLogger()->debug($custom_filter);
			
			$jsonString = json_encode($custom_filter);
			
			self::getLogger()->debug($jsonString);
			
			// $shortcode = '[gfchartsreports data_only="true" type="'.$type.'" gf_form_id="'.$form_id.'" include="'.$include.'" exclude="'.$exclude.'" custom_search_criteria=\''.$jsonString.'\']';
			$source = 'gf';
			$destination = 'chartjs';
			
			self::getLogger()->debug("Executing shortcode gfchartsreports : " . $source . ' -> ' . $destination);
			if (class_exists('maxicharts_reports')) {
				self::getLogger()->debug("Refreshing query builder chart with atts:");
				self::getLogger()->debug($atts);
				$result = maxicharts_reports::chartReports($source, $destination, $atts);
			}
			self::getLogger()->debug($result);
			echo $result;
			wp_die();
		}
		
		function maxicharts_load_scripts()
		{
			if (! is_admin()) {
				
				$old_queryBuilder = false;
				if ($old_queryBuilder) {
					$baseDir = 'libs/vendor';
					
					// jQuery-QueryBuilder/blob/master/dist/js/query-builder.standalone.js
					$file = '/blob/master/dist/js/query-builder.standalone.js'; // dist/js/query-builder.standalone.js
					$queryBuilderExtendextJs = plugins_url($baseDir . "/mistic100/jquery-querybuilder/dist/js/query-builder.standalone.js", __FILE__);
					// if (is_readable($queryBuilderJs)) {
					wp_register_script('mistic-extendext-query-builder-js', $queryBuilderExtendextJs, array(
						'jquery'
						
					));
					wp_enqueue_script('mistic-extendext-query-builder-js');
					
					$queryBuilderMomentJs = plugins_url($baseDir . "/moment/moment/min/moment.min.js", __FILE__);
					// if (is_readable($queryBuilderJs)) {
					wp_register_script('mistic-moment-js', $queryBuilderMomentJs, array(
						'jquery'
						
					));
					wp_enqueue_script('mistic-moment-js');
				} else {
					$baseDir = 'libs/node_modules';
					$queryBuilderExtendextJs = plugins_url($baseDir . "/jQuery-QueryBuilder/dist/js/query-builder.standalone.js", __FILE__);
					// if (is_readable($queryBuilderJs)) {
					wp_register_script('maxicharts-query-builder-standalone-js', $queryBuilderExtendextJs, array(
						'jquery'
						
					));
					wp_enqueue_script('maxicharts-query-builder-standalone-js');
					
					$queryBuilderMomentJs = plugins_url($baseDir . "/moment/min/moment.min.js", __FILE__);
					// if (is_readable($queryBuilderJs)) {
					wp_register_script('maxicharts-moment-js', $queryBuilderMomentJs, array(
						'jquery'
						
					));
					wp_enqueue_script('maxicharts-moment-js');
				}
				
				
				// ---------------------
				
				// FIXME : if scoped css added, no more style messup :) , but no more datepicker :(
				$scopedBootstrap = true;
				if ($scopedBootstrap) {
					$relPath1 = 'bootstrap-scoped-scoped/dist/css/bootstrap-scoped.css';
					$relPath2 = 'bootstrap-scoped-scoped/dist/css/bootstrap-scoped-theme.css';
				} else {
					$relPath1 = 'node_modules/bootstrap/dist/css/bootstrap.min.css';
					$relPath2 = 'bootstrap-scoped-scoped/dist/css/bootstrap-scoped-theme.css';
					// $relPath2 = 'node_modules/bootstrap/dist/css//bootstrap-scoped-theme.css';
					
				}
				
				
				$bootstrapCSS = array(
					$relPath1,
					$relPath2
				);
				foreach ($bootstrapCSS as $cssPath) {
					if (empty($cssPath)){
						continue;
					}
					$currentPath = plugins_url("/libs/" . $cssPath, __FILE__);
					
					$identifier = 'maxcharts_' . basename($currentPath);
					wp_enqueue_style($identifier, $currentPath, __FILE__);
					self::getLogger()->debug("File " . $identifier . " enqueued  :" . $currentPath);
				}
				
				/*
				 * self::getLogger()->debug($queryBuilderBootCss);
				 * wp_enqueue_style('maxicharts-querybuilder-css', $queryBuilderBootCss, __FILE__);
				 *
				 *
				 * $queryBuilderThemeCss = plugins_url("/libs/" . $relPath, __FILE__);
				 * self::getLogger()->debug($queryBuilderThemeCss);
				 * wp_enqueue_style('maxicharts-querybuildertheme-css', $queryBuilderThemeCss, __FILE__);
				 */
				// only buttons and utilities
				/*
				 * $queryBuilderBootUtilitiesCss = plugins_url($baseDir . "/node_modules/bootstrap-utilities/bootstrap-utilities.css", __FILE__);
				 * wp_enqueue_style('maxicharts-querybuilder-css', $queryBuilderBootUtilitiesCss, __FILE__);
				 *
				 */
				
				if ($old_queryBuilder) {
					$queryBuilderDefaultCss = plugins_url($baseDir . "/mistic100/jquery-querybuilder/dist/css/query-builder.default.min.css", __FILE__);
					wp_enqueue_style('maxicharts-querybuilderdefault-css', $queryBuilderDefaultCss, __FILE__);
					
					$queryBuilderJs = plugins_url($baseDir . "/mistic100/jquery-querybuilder/dist/js/query-builder.js", __FILE__);
					// if (is_readable($queryBuilderJs)) {
					wp_register_script('mistic-query-builder-js', $queryBuilderJs, array(
						'jquery'
						
					));
					wp_enqueue_script('mistic-query-builder-js');
				} else {
					$queryBuilderDefaultCss = plugins_url($baseDir . "/jQuery-QueryBuilder/dist/css/query-builder.default.min.css", __FILE__);
					wp_enqueue_style('maxicharts-querybuilderdefault-css', $queryBuilderDefaultCss, __FILE__);
					
					$queryBuilderJs = plugins_url($baseDir . "/jQuery-QueryBuilder/dist/js/query-builder.js", __FILE__);
					// if (is_readable($queryBuilderJs)) {
					wp_register_script('maxicharts-query-builder-js', $queryBuilderJs, array(
						'jquery'
						
					));
					wp_enqueue_script('maxicharts-query-builder-js');
				}
				
				/*
				 wp_register_script('maxicharts-bt-min-js', plugins_url("libs/node_modules/bootstrap/dist/js/bootstrap.min.js", __FILE__));
				 wp_enqueue_script('maxicharts-bt-min-js');
				 */
				// https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js
				
				wp_register_script('maxicharts-bt-datepicker-js', plugins_url("libs/bootstrap-datepicker/1.7.1/bootstrap-datepicker.min.js", __FILE__), array(
					'jquery',
					//'maxicharts-bt-min-js'
				));
				wp_enqueue_script('maxicharts-bt-datepicker-js');
				
				wp_enqueue_style('maxicharts-bt-datepicker-css', plugins_url("libs/bootstrap-datepicker/1.7.1/bootstrap-datepicker.min.css", __FILE__));
				
				wp_enqueue_style('maxicharts-datepicker-css', plugins_url("libs/bootstrap-datepicker/1.7.1/datepicker.css", __FILE__));
				
				
				// make ajaxurl accessible for front-end
				/*
				 * wp_enqueue_script( 'ajax-script', get_template_directory_uri() . '/js/my-ajax-script.js', array('jquery') );
				 *
				 * wp_localize_script( 'ajax-script', 'my_ajax_object',
				 * array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
				 */
				
				$swalVersion = "2.0.3";
				$jsEnd = '.min.js';
				$swalPath = plugins_url("libs/node_modules/sweetalert/dist/sweetalert" . $jsEnd, __FILE__);
				
				// http://www.idweblogs.eu/test/wp-content/plugins/maxicharts-querybuilder-add-on/libs/node_modules/sweetalert/dist/sweetalert.min.js
				
				wp_register_script('swal', $swalPath, null, null);
				wp_enqueue_script('swal');
				
				
				// maxicharts includes ----------------------
				
				$maxichartsQueryBuilderJs = plugins_url("js/maxicharts-querybuilder.js", __FILE__);
				self::getLogger()->debug("maxicharts_load_scripts::File : " . $maxichartsQueryBuilderJs);
				wp_register_script('maxicharts-mc-query-builder-js', $maxichartsQueryBuilderJs, array(
					'jquery'
					/* 'moment', */
				));
				wp_enqueue_script('maxicharts-mc-query-builder-js');
				wp_localize_script('maxicharts-mc-query-builder-js', 'maxicharts_ajax_object', array(
					'ajax_url' => admin_url('admin-ajax.php')
				));
				
				$queryBuilderCss = plugins_url("css/maxicharts_qb.css", __FILE__);
				wp_enqueue_style('maxicharts-qb-css', $queryBuilderCss, __FILE__);
				
			}
		}
		
		function maxicharts_add_filter_widget($currentchart, $chartAttributes, $atts)
		{
			self::getLogger()->debug("maxicharts_add_filter_widget");
			$bool_att = filter_var(trim($atts[$this->shortcode_atts_name]), FILTER_VALIDATE_BOOLEAN);
			self::getLogger()->debug($bool_att);
			//$bool_att = boolval($atts[$this->shortcode_atts_name]);
			self::getLogger()->debug($bool_att);
			if ( $bool_att === false) {
				return $currentchart;
			}
			
			$currentChartId = $chartAttributes['id'];
			
			$maxichartsQB = "maxicharts_builder";
			//$additionnalClass = "bootstrap";
			$atts['graph_type'] = $atts['type'];
			$shortcode_data = str_replace("=", '="', http_build_query($atts, null, '" ', PHP_QUERY_RFC3986)) . '"';
			
			self::getLogger()->debug($shortcode_data);
			
			$buttonHtml = '<input class="maxicharts_filter" id="filter_' . $currentChartId . '" type="button" value="Filter" ' . $shortcode_data . ' />';
			
			self::getLogger()->debug($buttonHtml);
			
			$filter = $buttonHtml;
			
			$builderId = $maxichartsQB . '_' . $currentChartId;
			$querybuilder = '<div class="' . $maxichartsQB . '" id="' . $builderId . '"></div>';
			
			return $querybuilder . $filter . $currentchart;
		}
	}
}
new maxicharts_querybuilder_plugin();