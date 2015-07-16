<?php

/*
 * Adds row and bulk actions to posts, pages and media list
 * Manages actions which trigger communication with Lingotek TMS

 *
 * @since 0.2
 */
class Lingotek_Post_actions extends Lingotek_Actions {

	/*
	 * Constructor
	 *
	 * @since 0.2
	 */
	public function __construct() {
		parent::__construct('post');

		// row actions
		add_filter('post_row_actions', array(&$this, 'post_row_actions'), 10, 2);
		add_filter('page_row_actions', array(&$this, 'post_row_actions'), 10, 2); // hierarchical post types
		add_filter('media_row_actions', array(&$this, 'post_row_actions'), 10, 2);

		// add bulk actions
		add_action('admin_footer-edit.php', array(&$this, 'add_bulk_actions')); // FIXME admin_print_footer_scripts instead?
		add_action('admin_footer-upload.php', array(&$this, 'add_bulk_actions'));

		// manage bulk actions, row actions and icon actions
		add_action('load-edit.php', array(&$this, 'manage_actions'));
		add_action('load-upload.php', array(&$this, 'manage_actions'));
	}

	/*
	 * get the language of a post
	 *
	 * @since 0.2
	 *
	 * @param int $post_id
	 * @return object
	 */
	protected function get_language($post_id) {
		return $this->pllm->get_post_language($post_id);
	}

	/*
	 * displays the icon of an uploaded post with the relevant link
	 *
	 * @since 0.2
	 *
	 * @param int $id
	 */
	public static function uploaded_icon($id) {
		return self::display_icon('uploaded', get_edit_post_link($id));
	}

	/*
	 * adds a row action link
	 *
	 * @since 0.1
	 *
	 * @param array $actions list of action links
	 * @param object $post
	 * @return array
	 */
	public function post_row_actions($actions, $post) {
		if ($this->pllm->is_translated_post_type($post->post_type)) {
			$actions = $this->_row_actions($actions, $post->ID);

			$language = $this->pllm->get_post_language($post->ID);
			if (!empty($language)) {
				$profile = Lingotek_Model::get_profile($post->post_type, $language);
				if ('disabled' == $profile['profile'])
					unset($actions['lingotek-upload']);
			}
		}
		return $actions;
	}

	/*
	 * adds actions to bulk dropdown in posts list table
	 *
	 * @since 0.1
	 */
	public function add_bulk_actions() {
		if (isset($GLOBALS['post_type']) && $this->pllm->is_translated_post_type($GLOBALS['post_type']))
			$this->_add_bulk_actions();
	}

	/*
	 * manages Lingotek specific actions before WordPress acts
	 *
	 * @since 0.1
	 */
	public function manage_actions() {
		global $typenow;
		$post_type = 'load-upload.php' == current_filter() ? 'attachment' : $typenow;

		if (!$this->pllm->is_translated_post_type($post_type))
			return;

		// get the action
		// $typenow is empty for media
		$wp_list_table = _get_list_table(empty($typenow) ? 'WP_Media_List_Table' : 'WP_Posts_List_Table');
		$action = $wp_list_table->current_action();

		if (empty($action))
			return;

		$redirect = remove_query_arg( array('action', 'action2', 'tags_input', 'post_author', 'comment_status', 'ping_status', '_status', 'post', 'bulk_edit', 'post_view'), wp_get_referer() );
		if (!$redirect)
			$redirect = admin_url("edit.php?post_type=$typenow");

		switch($action) {
			case 'bulk-lingotek-upload':
				$type = empty($typenow) ? 'media' : 'post';
				if (empty($_REQUEST[$type]))
					return;

				$post_ids = array();

				foreach (array_map('intval', $_REQUEST[$type]) as $post_id) {
					// safe upload
					if ($this->lgtm->can_upload('post', $post_id))
						$post_ids[] = $post_id;

					// the document is already translated so will be overwritten
					elseif(($document = $this->lgtm->get_group('post', $post_id)) && empty($document->source)) {
						// take care to upload only one post in a translation group
						$intersect = array_intersect($post_ids, $this->pllm->get_translations('post', $post_id));
						if (empty($intersect)) {
							$post_ids[] = $post_id;
							$redirect = add_query_arg('lingotek_warning', 1, $redirect);
						}
					}
				}

				// check if translation is disabled
				if (!empty($post_ids)) {
					foreach ($post_ids as $key => $post_id) {
						$language = $this->pllm->get_post_language($post_id);
						$profile = Lingotek_Model::get_profile($post_type, $language);
						if ('disabled' == $profile['profile'])
							unset($post_ids[$key]);
					}
				}

			case 'bulk-lingotek-request':
			case 'bulk-lingotek-download':
			case 'bulk-lingotek-status':
			case 'bulk-lingotek-delete':
				if (empty($post_ids)) {
					$type = empty($typenow) ? 'media' : 'post';
					if (empty($_REQUEST[$type]))
						return;

					$post_ids = array_map('intval', $_REQUEST[$type]);
				}

				empty($typenow) ? check_admin_referer('bulk-media') : check_admin_referer('bulk-posts');

				$redirect = add_query_arg($action, 1, $redirect);
				$redirect = add_query_arg('ids', implode(',', $post_ids), $redirect);

				break;

			case 'lingotek-upload':
				check_admin_referer('lingotek-upload');
				$this->lgtm->upload_post((int) $_GET['post']);
				break;

			default:
				if (!$this->_manage_actions($action))
					return; // do not redirect if this is not one of our actions

		}

		wp_redirect($redirect);
		exit();

	}

	/*
	 * ajax response to upload documents and showing progress
	 *
	 * @since 0.1
	 */
	public function ajax_upload() {
		check_ajax_referer('lingotek_progress', '_lingotek_nonce');
		$this->lgtm->upload_post((int) $_POST['id']);
		die();
	}
}
