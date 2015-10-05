<?php

/*
 * Modifies Polylang filters
 *
 * @since 0.1
 */
class Lingotek_Filters_Columns extends PLL_Admin_Filters_Columns {
	public $lgtm; // Lingotek model

	/*
	 * cosntructor
	 *
	 * @since 0.1
	 *
	 * @param object $polylang Polylang object
	 */
	public function __construct(&$polylang) {
		parent::__construct($polylang);

		$this->lgtm = &$GLOBALS['wp_lingotek']->model;

		// FIXME remove quick edit and bulk edit for now waiting for a solution to remove it only for uploaded documents
		remove_filter('quick_edit_custom_box', array(&$this, 'quick_edit_custom_box'), 10, 2);
		remove_filter('bulk_edit_custom_box', array(&$this, 'quick_edit_custom_box'), 10, 2);
	}

	/*
	 * adds languages and translations columns in posts, pages, media, categories and tags tables
	 * overrides Polylang method to display all languages including the filtered one
	 * as well as displaying a tooltip with the language name and locale when there is no flag
	 *
	 * @since 0.2
	 *
	 * @param array $columns list of table columns
	 * @param string $before the column before which we want to add our languages
	 * @return array modified list of columns
	 */
	protected function add_column($columns, $before) {
		$n = array_search($before, array_keys($columns));
		if ($n) {
			$end = array_slice($columns, $n);
			$columns = array_slice($columns, 0, $n);
		}

		foreach ($this->model->get_languages_list() as $language) {
			$columns['language_'.$language->locale] = $language->flag ? $language->flag :
				sprintf('<a href="" title="%s">%s</a>',
					esc_html("$language->name ($language->locale)"),
					esc_html($language->slug)
				);
		}

		return isset($end) ? array_merge($columns, $end) : $columns;
	}

	/*
	 * fills the language and translations columns in the posts and taxonomies lists tables
	 * take care that when doing ajax inline edit, the post or term may not be updated in database yet
	 *
	 * @since 0.2
	 *
	 * @param string $type 'post' or 'term'
	 * @param string $column column name
	 * @param int $object_id id of the current object in row
	 */
	protected function _column($type, $column, $object_id, $custom_data = NULL) {
		$action = 'post' == $type ? 'inline-save' : 'inline-save-tax';
		$inline = defined('DOING_AJAX') && $_REQUEST['action'] == $action && isset($_POST['inline_lang_choice']);
		$lang = $inline ?
			$this->model->get_language($_POST['inline_lang_choice']) :
			call_user_func(array($this->model, 'get_' . $type . '_language'), $object_id);

		if (false === strpos($column, 'language_') || !$lang) {
			if ($custom_data) {
				return $custom_data;
			}
			else {
				return '';
			}
		}

		$language = $this->model->get_language(substr($column, 9));

		// FIXME should I suppress quick edit?
		// yes for uploaded posts, but I will need js as the field is built for all posts
		// /!\ also take care not add this field two times when translations are managed by Polylang
		// hidden field containing the post language for quick edit (used to filter categories since Polylang 1.7)
		if ($column == $this->get_first_language_column()  /*&& !$this->model->get_translation_id('post', $post_id)*/) {
			printf('<div class="hidden" id="lang_%d">%s</div>', esc_attr($object_id), esc_html($lang->slug));
		}

		$id = ($inline && $lang->slug != $this->model->get_language($_POST['old_lang'])->slug) ?
			($language->slug == $lang->slug ? $object_id : 0) :
			call_user_func(array($this->model, 'get_' . $type), $object_id, $language);

		$document = $this->lgtm->get_group($type, $object_id);
		if (isset($document->source)) {
			$source_language = $type == 'post' ? $this->model->get_post_language($document->source) : $this->model->get_term_language($document->source);
		}
		else {
			$source_language = $lang;
		}

		// FIXME not very clean
		$actions = 'post' == $type ? $GLOBALS['wp_lingotek']->post_actions : $GLOBALS['wp_lingotek']->term_actions;

		$profile = Lingotek_Model::get_profile($this->content_type, $language, $object_id);
		$source_profile = Lingotek_Model::get_profile($this->content_type, $lang, $id);
		$disabled = 'disabled' == $profile['profile'];

		// post ready for upload
		if ($this->lgtm->can_upload($type, $object_id) && $object_id == $id) {
			return $disabled ? ('post' == $type ? parent::post_column($column, $object_id) : parent::term_column('', $column, $object_id))
			: ($document && (count($document->desc_array) >= 3) ? $actions->upload_icon($object_id, true) : $actions->upload_icon($object_id));
		}

		// translation disabled
		elseif (isset($document->source) && $document->is_disabled_target($source_language, $language) && !isset($document->translations[$language->locale])) {
			return 'post' == $type ? parent::post_column($column, $object_id) : parent::term_column('', $column, $object_id);
		}

		// source post is uploaded
		elseif (isset($document->source) && $document->source == $id) {
			// source ready for upload
			if ($this->lgtm->can_upload($type, $id)) {
				return $actions->upload_icon($id);
			}

			// importing source
			if ($id == $object_id && 'importing' == $document->status) {
				return Lingotek_Actions::importing_icon($document);
			}

			// uploaded
			return 'post' == $type ? Lingotek_Post_actions::uploaded_icon($id) : Lingotek_Term_actions::uploaded_icon($id);
		}

		elseif ($type == 'term' && !isset($document->translations[$language->locale]) && $document->source != $object_id) {
			return parent::term_column('', $column, $object_id);
		}

		// translations
		elseif (isset($document->translations[$language->locale]) || (isset($document->source) && 'current' == $document->status)){
			return Lingotek_Actions::translation_icon($document, $language);
		}

		// translations exist but are not managed by Lingotek TMS
		elseif (empty($document->source)) {
			return $object_id == $id && !$disabled ? $actions->upload_icon($object_id, true) : ('post' == $type ? parent::post_column($column, $object_id) : parent::term_column('', $column, $object_id));
		}

		// no translation
		else {
			return  '<div class="lingotek-color dashicons dashicons-no"></div>';
		}
	}

	/*
	 * fills the language and translations columns in the posts, pages and media library tables
	 * take care that when doing ajax inline edit, the post may not be updated in database yet
	 *
	 * @since 0.1
	 *
	 * @param string $column column name
	 * @param int $post_id
	 */
	public function post_column($column, $post_id) {
		$this->content_type = get_post_type($post_id);

		echo $this->_column('post', $column, $post_id);

		// checking for api errors
		$document = $this->lgtm->get_group('post', $post_id);
		if (isset($document->source)) {
			$source_language = $this->model->get_post_language($document->source);
			$this->error_icon_html($column, $post_id, $source_language->locale);
		}
		else {
			$this->error_icon_html($column, $post_id);
		}
	}

	/*
	 * fills the language and translations columns in the categories and post tags tables
	 * take care that when doing ajax inline edit, the term may not be updated in database yet
	 *
	 * @since 0.2
	 *
	 * @param string $empty not used
	 * @param string $column column name
	 * @param int term_id
	 */
	public function term_column($custom_data, $column, $term_id) {
		$this->content_type = $GLOBALS['taxonomy'];
		if (!$custom_data) {
			echo $this->_column('term', $column, $term_id);
		}
		else {
			echo $this->_column('term', $column, $term_id, $custom_data);
		}
		// checking for api errors
		$this->error_icon_html($column, $term_id);
	}

	/*
	 * checks for errors in the lingotek_log_errors option and displays an icon
	 *
	 * @since 1.2
	 *
	 * @param string $column
	 * @param string $object_id
	 */
	protected function error_icon_html($column, $object_id, $source_locale = null) {
		// checking for api errors
		$source_column = substr($column, 9);
		$column_language_only = substr($column, 0, 11);
		$errors = get_option('lingotek_log_errors');

		if ($source_column == $source_locale) {
			if (isset($errors[$object_id])) {
				$api_error = Lingotek_Actions::retrieve_api_error($errors[$object_id]);
				echo Lingotek_Actions::display_error_icon('error', $api_error);
			}
		}
	}
}
