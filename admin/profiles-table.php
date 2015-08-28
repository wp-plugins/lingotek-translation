<?php

if(!class_exists('WP_List_Table')){
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' ); // since WP 3.1
}

class Lingotek_Profiles_Table extends WP_List_Table {

	/*
	 * constructor
	 *
	 * @since 0.2
	 */
	function __construct() {
		parent::__construct(array(
			'plural'   => 'lingotek-profiles', // do not translate (used for css class)
			'ajax'	 => false
		));
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
	function column_default($item, $column_name) {
		return isset($item[$column_name]) ? esc_html($item[$column_name]) : '';
	}

	/*
	 * displays the edit link in actions column
	 *
	 * @since 0.2
	 *
	 * @param array $item
	 * @return string
	 */
	function column_usage($item){
		return empty($item['usage']) ?
			__('No content types', 'wp-lingotek') :
			sprintf(_n('1 content type', '%d content types', $item['usage'], 'wp-lingotek'), number_format_i18n($item['usage']));
	}

	/*
	 * displays the edit link in actions column
	 *
	 * @since 0.2
	 *
	 * @param array $item
	 * @return string
	 */
	function column_actions($item){
		$actions = array();

		if ('disabled' != $item['profile'])
			$actions[] = sprintf(
				'<a href=%s>%s</a>',
				esc_url(admin_url('admin.php?page=wp-lingotek_manage&sm=edit-profile&profile='.$item['profile'])),
				__('Edit', 'wp-lingotek')
			);

		if (!in_array($item['profile'], array('automatic', 'manual', 'disabled')) && empty($item['usage']))
			$actions[] = sprintf(
				'<a href="%s" onclick = "return confirm(\'%s\');">%s</a>',
				esc_url(wp_nonce_url('admin.php?page=wp-lingotek_manage&sm=profiles&lingotek_action=delete-profile&noheader=true&profile='.$item['profile'], 'delete-profile')),
				__('You are about to permanently delete this profile. Are you sure?', 'wp-lingotek'),
				__('Delete', 'wp-lingotek')
			);

		return implode(' | ', $actions);
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
			'name' => __('Profile name', 'wp-lingotek'),
			'usage'   => __('Usage', 'wp-lingotek'),
			'actions' => __('Actions', 'wp-lingotek'),
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
		$per_page = $this->get_items_per_page('lingotek_profiles_per_page');
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
