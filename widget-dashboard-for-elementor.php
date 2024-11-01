<?php
/*
Plugin Name: Widget Dashboard for Elementor
Excerpt: A dashboard plugin show Elementor widgets and elements usage across your Wordpress installation.
Description: A dashboard plugin show Elementor widgets and elements usage across your Wordpress installation.
Version: 1.0.2
Author:      Web Programming Solutions
Author URI:  https://webprogrammingsolutions.com/
*/

if (!defined('WPINC')) {
	die;
}

class WidgetDashboardElementor
{

	public function __construct()
	{

		global $widget_count;

		$widget_count = array();

		require_once(__DIR__ . '/includes/plugin-mappings.php');

		add_action('admin_menu', [$this, 'register_dashboard_page']);
		add_action('wp_enqueue_scripts', [$this, 'enqueue_plugin_stylesheet']);
	}

	public function register_dashboard_page()
	{
		add_submenu_page(
			'tools.php',
			__('Widget Dashboard for Elementor', 'widget-dashboard-elementor'),
			__('Widget Dashboard for Elementor', 'widget-dashboard-elementor'),
			'manage_options',
			'widget-dashboard-elementor',
			[$this, 'render_dashboard_page']
		);
	}


	public function enqueue_plugin_stylesheet()
	{
		$plugin_dir = plugin_dir_url(__FILE__);
		wp_enqueue_style('plugin-style', $plugin_dir . 'assets/widget-dashboard.min.css');
	}


	public function render_dashboard_page()
	{

		global $widget_count;
		global $mapping_list;

		$elementor_pages_widgets = $this->get_elementor_pages_widgets();

		echo '<h1>' . esc_html__('Widgets Dashboard for Elementor', 'widget-dashboard-elementor') . '</h1>';

		echo '<p>' . esc_html__('This page shows a list of all Elementor widgets used on your site.', 'widget-dashboard-elementor') . '</p>';

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead>';
		echo '<tr>';
		echo '<th>' . esc_html__('Post Title', 'widget-dashboard-elementor') . '</th>';
		echo '<th>' . esc_html__('Post Type', 'widget-dashboard-elementor') . '</th>';
		echo '<th>' . esc_html__('Widgets', 'widget-dashboard-elementor') . '</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';

		foreach ($elementor_pages_widgets as $page_id => $page_data) {
			echo '<tr>';
			echo '<td><a href="' . esc_url(get_edit_post_link($page_id)) . '">' . esc_html($page_data['title']) . '</a></td>';
			echo '<td>' . esc_html($page_data['type']) . '</td>';
			echo '<td>' . implode(', ', array_map('esc_html', $page_data['widgets'])) . '</td>';
			echo '</tr>';
		}

		echo '</tbody>';
		echo '</table>';



		echo '<h2>' . esc_html__('Widget Usage', 'widget-dashboard-elementor') . '</h2>';
		echo '<p>' . esc_html__('This table shows a list of all Elementor widgets used on your site, grouped by plugin.', 'widget-dashboard-elementor') . '</p>';


		// Output the table with enhanced formatting
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead>
		<tr>
		<th>Plugin Name</th>
		<th>Widget Type</th>
		<th>Count</th>
		<th>Edit</th>
		</tr>
		</thead>';
		echo '<tbody>';


		foreach ($widget_count as $widget_type => $count) {
			echo '<tr>';

			// Default values if plugin name is not found in mapping list
			$plugin_name = '';
			$class_name = '';

			// Look for plugin name and icon in the mapping list
			foreach ($mapping_list as $name => $prefix) {
				if (strpos($widget_type, $prefix) === 0) {
					$plugin_name = $name;
					$class_name = strtolower(str_replace(' ', '-', $name));
					break;
				}
			}

			// Set "Elementor" label for native Elementor widgets
			if (empty($plugin_name)) {
				$plugin_name = 'Elementor';
				$class_name = 'elementor';
			}

			// Apply enhanced formatting to cells
			echo '<td class="plugin-name ' . esc_html__($class_name, 'widget-dashboard-elementor') . '">' . esc_html__($plugin_name, 'widget-dashboard-elementor') . '</td>';
			echo '<td class="widget-type">' . esc_html__($widget_type, 'widget-dashboard-elementor') . '</td>';
			echo '<td class="count">' . esc_html__($count, 'widget-dashboard-elementor') . '</td>';

			// Set up WP_Query arguments
			$query_args = array(
				'post_type' => 'any',
				'posts_per_page' => -1,
				'meta_query' => array(
					array(
						'key' => '_elementor_data',
						'value' => $widget_type,
						'compare' => 'LIKE'
					)
				)
			);

			// Run the WP_Query
			$query = new WP_Query($query_args);

			// Generate "Edit" and "Edit with Elementor" links for each post
			echo '<td>';
			while ($query->have_posts()) {
				$query->the_post();
				$post_id = get_the_ID();
				$edit_url = get_edit_post_link($post_id);
				$elementor_edit_url = str_replace('=edit', '=elementor', $edit_url);
				$title = get_the_title($post_id);
				echo '<strong>' . esc_html__($title, 'widget-dashboard-elementor') . '</strong><br />';
				echo '<a href="' . esc_url($edit_url) . '">Edit</a> | ';
				echo '<a href="' . esc_url($elementor_edit_url) . '">Edit with Elementor</a><br />';
			}
			echo '</td>';

			// Reset post data
			wp_reset_postdata();

			echo '</tr>';
		}

		echo '</tbody>';
		echo '</table>';
	}

	private function extract_widgets($elements)
	{

		global $widget_count;

		$widgets = [];

		foreach ($elements as $element) {
			if ($element['elType'] == 'widget') {

				$widget_type = $element['widgetType'];

				// Increment widget count or initialize count to 1
				if (isset($widget_count[$widget_type])) {
					$widget_count[$widget_type]++;
				} else {
					$widget_count[$widget_type] = 1;
				}

				$widgets[] = $widget_type;
			} elseif (!empty($element['elements'])) {
				$widgets = array_merge($widgets, $this->extract_widgets($element['elements']));
			}
		}

		return array_unique($widgets);
	}

	public function get_elementor_pages_widgets()
	{

		$elementor_pages = get_posts([
			'post_type' => ['any'],
			'post_status' => 'publish',
			'meta_key' => '_elementor_data',
			'numberposts' => -1
		]);

		$elementor_pages_widgets = [];

		foreach ($elementor_pages as $page) {
			$elementor_data = json_decode(get_post_meta($page->ID, '_elementor_data', true), true);
			$widgets = $this->extract_widgets($elementor_data);
			$elementor_pages_widgets[$page->ID] = [
				'title' => $page->post_title,
				'type' => $page->post_type,
				'widgets' => $widgets
			];
		}

		return $elementor_pages_widgets;
	}
}

$elementor_widgets_dashboard = new WidgetDashboardElementor();
