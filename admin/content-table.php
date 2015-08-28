<?php

if(!class_exists('WP_List_Table')){
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' ); // since WP 3.1
}

class Lingotek_Content_Table extends WP_List_Table {
	protected $profiles, $content_types;

	/*
	 * constructor
	 *
	 * @since 0.2
	 */
	function __construct($content_types) {
		parent::__construct(array(
			'plural'   => 'lingotek-content', // do not translate (used for css class)
			'ajax'	 => false
		));

		$this->profiles = Lingotek::get_profiles();
		$this->content_types = $content_types;
	}

	/*
	 * displays the item information in a column (default case)
	 *
	 * @since 0.2
	 *
	 * @param array $item
	 * @param string $column_name
	 * @return string
	 */
	function column_name($item) {
		global $polylang;

		printf('<span class="content-type-name">%s</span>', esc_html($item['name']));

		// the source language for strings is always the default language
		if ('string' != $item['type']) {
			printf('<a id="id[%s]" class="dashicons dashicons-arrow-right" onclick="%s" href="#"></a>',
				esc_attr($item['type']),
				"
				d1 = document.getElementById('sources-name[{$item['type']}]');
				d2 = document.getElementById('sources-profile[{$item['type']}]');
				c = 'dashicons dashicons-arrow-';
				if (c+'right' == this.className) {
					this.className = c+'down';
					d1.style.display = d2.style.display = '';
				}
				else {
					this.className = c+'right';
					d1.style.display = d2.style.display = 'none';
				}
				return false;
				"
			);

			printf('<ul class="sources-name" id="sources-name[%s]" style="display:none;">', $item['type']);
			foreach ($polylang->model->get_languages_list() as $language) {
				printf('<li>%s</li>', sprintf(__('%s source', 'wp-lingotek'), esc_html($language->name)));
			}
			echo '</ul>';
		}
	}

	/*
	 * displays the item profile dropdown list
	 *
	 * @since 0.2
	 *
	 * @param array $item
	 * @return string
	 */
	function column_profile($item) {
		global $polylang;

		printf('<select class="content-type-profile" name="%1$s" id="%1$s">', $item['type'] . '[profile]');
		foreach ($this->profiles as $key => $profile) {
			$selected = (isset($item['profile']) && $key == $item['profile']) ? 'selected="selected"' : '';
			echo "\n\t<option value='" . esc_attr($key) . "' $selected>" . esc_html($profile['name']) . '</option>';
		}
		echo '</select>';

		$options = array_merge(array('default' => array('name' => __('Use content type default', 'wp-lingotek'))), $this->profiles);

		// the source language for strings is always the default language
		if ('string' != $item['type']) {
			printf('<ul class="sources-profile" id="sources-profile[%s]" style="display:none;">', $item['type']);
			foreach ($polylang->model->get_languages_list() as $language) {
				printf('<li><select name="%1$s" id="%1$s">', $item['type'] . '[sources][' . $language->slug . ']' );
				foreach ($options as $key => $profile) {
					$selected = (isset($item['sources'][$language->slug]) && $key == $item['sources'][$language->slug]) ? 'selected="selected"' : '';
					echo "\n\t<option value='" . esc_attr($key) . "' $selected>" . esc_html($profile['name']) . '</option>';
				}
				echo '</select></li>';
			}
			echo '</ul>';
		}
	}

	/*
	 * displays checkboxes in fields columns
	 * can handle fields with one or two keys
	 *
	 * @since 0.2
	 *
	 * @param array $labels
	 * @param array $values
	 * @param string $parent
	 */
	protected function display_fields($labels, $values, $name) {
		foreach ($labels as $key => $str) {
			if (is_array($str)) {
				if ($key === 'metas') {
					continue;
				}
				$this->display_fields($str, isset($values[$key]) ? $values[$key] : array(), $name . "[$key]");
			}
			else {
				printf(
					'<li><label><input name="%s" type="checkbox" value="1" %s /> %s</label></li>',
					esc_attr($name . "[$key]"),
					empty($values[$key]) ? 'checked="checked"' : '',
					esc_html($str)
				);
			}
		}
	}

	/*
	 * displays the item fields checkboxes
	 *
	 * @since 0.2
	 *
	 * @param array $item
	 * @return string
	 */
	function column_fields($item) {
		if (!empty($item['fields'])) {
			echo '<ul class="content-type-fields">';
			$this->display_fields($item['fields']['label'], isset($item['fields']['value']) ? $item['fields']['value'] : array() , $item['type'] . '[fields]');
			echo '</ul>';
		}
	}

	/*
	 * gets the list of columns
	 *
	 * @since 0.2
	 *
	 * @return array the list of column titles
	 */
	function get_columns() {
		return array(
			'name'    => __('Content Type', 'wp-lingotek'),
			'profile' => __('Profile', 'wp-lingotek'),
			'fields'  => __('Fields', 'wp-lingotek'),
		);
	}

	/*
	 * gets the list of sortable columns
	 *
	 * @since 0.2
	 *
	 * @return array
	 */
	function get_sortable_columns() {
		return array(
			'name' => array('name', false),
		);
	}

	/*
	 * prepares the list of items ofr displaying
	 *
	 * @since 0.2
	 *
	 * @param array $data
	 */
	function prepare_items($data = array()) {
		$per_page = $this->get_items_per_page('lingotek_content_per_page');
		$this->_column_headers = array($this->get_columns(), array(), $this->get_sortable_columns());

		function usort_reorder($a, $b){
			$result = strcmp($a[$_REQUEST['orderby']], $b[$_REQUEST['orderby']]); // determine sort order
			return (empty($_REQUEST['order']) || $_REQUEST['order'] == 'asc') ? $result : -$result; // send final sort direction to usort
		};

		if (!empty($_REQUEST['orderby'])) // no sort by default
			usort($data, 'usort_reorder');

		$total_items = count($data);
		$this->items = array_slice($data, ($this->get_pagenum() - 1) * $per_page, $per_page);

		$this->set_pagination_args(array(
			'total_items' => $total_items,
			'per_page'	=> $per_page,
			'total_pages' => ceil($total_items/$per_page)
		));
	}
}
