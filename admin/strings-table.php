<?php

if(!class_exists('WP_List_Table')){
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' ); // since WP 3.1
}

class Lingotek_Strings_Table extends WP_List_Table {
	public $pllm, $lgtm, $string_actions;

	/*
	 * constructor
	 *
	 * @since 0.2
	 */
	function __construct($string_actions) {
		parent::__construct(array(
			'plural'   => 'lingotek-strings-translations', // do not translate (used for css class)
			'ajax'	 => false
		));
		$this->pllm = $GLOBALS['polylang']->model;
		$this->lgtm = $GLOBALS['wp_lingotek']->model;
		$this->string_actions = $string_actions;
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
		// generic case (count)
		if (false === strpos($column_name, 'language_'))
			return $item[$column_name];

		// language column
		$language = $this->pllm->get_language(substr($column_name, 9));
		$document = $this->lgtm->get_group('string', $item['context']); // FIXME

		// post ready for upload
		if ($this->lgtm->can_upload('string', $item['context']) && $language->slug == $this->pllm->options['default_lang'])
			echo $this->string_actions->upload_icon($item['context']);

		// translation disabled
		elseif (isset($document->source) && $document->is_disabled_target($language))
			echo '<div class="lingotek-color dashicons dashicons-no"></div>';

		// source post is uploaded
		elseif (isset($document->source) && $document->source == $language->mo_id)
			echo 'importing' == $document->status ? Lingotek_Actions::importing_icon($document) : Lingotek_String_actions::uploaded_icon($item['context']);

		// translations
		elseif (isset($document->translations[$language->locale]) || (isset($document->source) && 'current' == $document->status))
			echo Lingotek_Actions::translation_icon($document, $language);

		// no translation
		else
			echo '<div class="lingotek-color dashicons dashicons-no"></div>';

		$language_only = 'language_' . $language->slug;
		$errors = get_option('lingotek_log_errors');
		if ($language_only == $this->get_first_language_column()) {
			if (isset($errors[$item['context']])) {
				$api_error = Lingotek_Actions::retrieve_api_error($errors[$item['context']]);
				echo Lingotek_Actions::display_error_icon('error', $api_error);
			}
		}
	}

	/*
	 * displays the checkbox in first column
	 *
	 * @since 0.2
	 *
	 * @param array $item
	 * @return string
	 */
	function column_cb($item){
		return sprintf('<input type="checkbox" name="strings[]" value="%d" />', esc_attr($item['row']));
	}

	/*
	 * displays the item information in the column 'group'
	 * displays the row actions links
	 *
	 * @since 0.2
	 *
	 * @param object $item
	 * @return string
	 */
	function column_context($item) {
		return $item['context'] . $this->row_actions($this->string_actions->row_actions($item['context']));
	}

	/*
	 * gets the list of columns
	 *
	 * @since 0.2
	 *
	 * @return array the list of column titles
	 */
	function get_columns() {
		$columns = array(
			'cb'           => '<input type="checkbox" />', //checkbox
			'context'      => __('Group', 'wp-lingotek'),
			'count'        => __('Count', 'wp-lingotek'),
		);

		foreach ($GLOBALS['polylang']->model->get_languages_list() as $lang) {
			if (!$lang->flag) {
				$columns['language_' . $lang->slug] = $lang->slug;
			}
			else {
				$columns['language_' . $lang->slug] = $lang->flag;
			}
		}

		return $columns;
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
			'context' => array('context', false),
			'count'   => array('count', false),
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
		$per_page = $this->get_items_per_page('lingotek_strings_per_page');
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

	/*
	 * get the list of possible bulk actions
	 *
	 * @since 0.2
	 *
	 * @return array
	 */
	function get_bulk_actions() {
		foreach (Lingotek_String_actions::$actions as $action => $strings)
			$arr['bulk-lingotek-' . $action] = $strings['action'];
		return $arr;
	}

	/*
	 * returns the first language column
	 *
	 * @since 1.2
	 *
	 * @return string first language column name
	 */
	protected function get_first_language_column() {
		foreach ($this->pllm->get_languages_list() as $language) {
			$columns[] = 'language_'.$language->slug;
		}

		return empty($columns) ? '' : reset($columns);
	}
}
