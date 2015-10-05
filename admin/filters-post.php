<?php

/*
 * Modifies Polylang filters
 * Manages automatic upload
 * Manages delete / trash sync
 *
 * @since 0.1
 */
class Lingotek_Filters_Post extends PLL_Admin_Filters_Post {
	public $lgtm; // Lingotek model
	public $pllm;

	/*
	 * Constructor
	 *
	 * @since 0.1
	 */
	public function __construct(&$polylang) {
		parent::__construct($polylang);

		$this->lgtm = &$GLOBALS['wp_lingotek']->model;
		$this->pllm = &$GLOBALS['polylang']->model;

		// automatic upload
		add_action('post_updated', array(&$this, 'post_updated'), 10, 3);

		// trash sync
		add_action('trashed_post', array(&$this, 'trash_post'));
		add_action('untrashed_post', array(&$this, 'untrash_post'));

		add_filter('manage_posts_columns', array(&$this, 'add_profile_column'));
		add_action('manage_posts_custom_column', array(&$this, 'add_profile_column_data'), 10, 3);

		add_filter('manage_pages_columns', array(&$this, 'add_profile_column'));
		add_action('manage_pages_custom_column', array(&$this, 'add_profile_column_data'), 10, 3);
	}

	/*
	 * controls whether to display the language metabox or not
	 *
	 * @since 0.1
	 */
	public function add_meta_boxes($post_type) {
		global $post_ID;
		if ($this->model->is_translated_post_type($post_type)) {
			$document = $this->lgtm->get_group('post', $post_ID);
			if (empty($document->source)) {
				parent::add_meta_boxes($post_type);
			}
			else {
				add_action( 'edit_form_top', array( &$this, 'edit_form_top' ) );
			}
		}
	}

	/*
	 * adds a column to display the post or page translation profile
	 *
	 * @since 1.1
	 */
	public function add_profile_column($columns) {
		$n = array_search('date', array_keys($columns));
		if ($n) {
			$end = array_slice($columns, $n);
			$columns = array_slice($columns, 0, $n);
		}

		$columns['profile'] = 'Profile';
		return isset($end) ? array_merge($columns, $end) : $columns;
	}

	/*
	 * finds the translation profile for a post or page and if not set then displays the content type default profile
	 *
	 * @since 1.1
	 */
	public function add_profile_column_data($column_name, $post_id) {
		if ($column_name == 'profile') {
			$document = $this->lgtm->get_group('post', $post_id);
			if (isset($document->source)) {
				$post_id = $document->source;
			}
			$profiles = Lingotek::get_profiles();
			$content_profiles = get_option('lingotek_content_type');
			$post_profile = Lingotek_Post_actions::get_post_profile($post_id);
			$post_language = $this->pllm->get_post_language($post_id);
			$post_type = 'post';
			if (isset($_REQUEST['post_type'])) {
				$post_type = $_REQUEST['post_type'];
			}

			if ($post_profile) {
				echo $profiles[$post_profile->description]['name'] . sprintf('<a title="%s">%s</a>', __('Not set to the content default profile', 'wp-lingotek'), '*');
			}
			else if ($post_language && isset($content_profiles[$post_type]['sources'][$post_language->slug])) {
				$profile = $content_profiles[$post_type]['sources'][$post_language->slug];
				echo $profiles[$profile]['name'];
			}
			else if (!empty($content_profiles)) {
				echo $profiles[$content_profiles[$post_type]['profile']]['name'];
			}
			else {
				if ($post_type == 'post') {
					_e('Automatic', 'wp-lingotek');
				}
				else if ($post_type == 'page') {
					_e('Manual', 'wp-lingotek');
				}
			}
		}
	}

	/*
	 * outputs hidden fields so that Polylang get correct information when its metabox is removed
	 *
	 * @since 1.10
	 */
	public function edit_form_top() {
		global $post_ID;
		printf( '<input type="hidden" id="post_lang_choice" name="post_lang_choice" value="%s" />', pll_get_post_language( $post_ID ) );
		wp_nonce_field('pll_language', '_pll_nonce');
	}

	/*
	 * uploads a post when saved for the first time
	 *
	 * @since 0.2

	 * @param int $post_id
	 * @param object $post
	 * @param bool $update whether it is an update or not
	 */
	public function save_post($post_id, $post, $update) {
		if ($this->can_save_post_data($post_id, $post, true)) {
			$document = $this->lgtm->get_group('post', $post_id);
			// updated post
			if ($document && $post_id == $document->source && $this->post_hash_has_changed($post)) {
				$document->source_edited();

				if ($document->is_automatic_upload() && Lingotek_Group_Post::is_valid_auto_upload_post_status($post->post_status)) {
					$this->lgtm->upload_post($post_id);
				}
			}
		}

		if (!$this->model->is_translated_post_type($post->post_type))
			return;
		// new post
		if (!isset($_REQUEST['import'])) {
			parent::save_post($post_id, $post, $update);

			if (!wp_is_post_revision($post_id) && 'auto-draft' != $post->post_status && Lingotek_Group_Post::is_valid_auto_upload_post_status($post->post_status) && 'automatic' == Lingotek_Model::get_profile_option('upload', $post->post_type, $this->model->get_post_language($post_id), false, $post_id) && !(isset($_POST['action']) && 'heartbeat' == $_POST['action']) && $this->lgtm->can_upload('post', $post_id)) {
				$this->lgtm->upload_post($post_id);
			}
		}
	}

	/*
	 * checks if we can act when saving a post
	 *
	 * @since 0.1
	 *
	 * @param int $post_id
	 * @param object $post
	 * @param bool $update whether it is an update or not
	 * @return bool
	 */
	protected function can_save_post_data($post_id, $post, $update) {
		// does nothing except on post types which are filterable
		// also don't act on revisions
		if (!$this->model->is_translated_post_type($post->post_type) || wp_is_post_revision($post_id))
			return false;

		// capability check
		// as 'wp_insert_post' can be called from outside WP admin
		$post_type_object = get_post_type_object($post->post_type);
		if (($update && !current_user_can($post_type_object->cap->edit_post, $post_id)) || (!$update && !current_user_can($post_type_object->cap->create_posts)))
			return false;

		return true;
	}

	/*
	 * marks the post as edited if needed
	 *
	 * @since 0.1
	 *
	 * @param int $post_id
	 * @param object $post_after
	 * @param object $post_before
	 */
	public function post_updated($post_id, $post_after, $post_before) {
	}

	/*
	 * get translations ids to sync for delete / trash / untrash
	 * since we can't sync all translations as we get conflicts when attempting to act two times on the same
	 *
	 * @since 0.2
	 *
	 * @param int $post_id
	 * @return array
	 */
	protected function get_translations_to_sync($post_id) {
		// don't synchronize disassociated posts
		$group = $this->lgtm->get_group('post', $post_id);
		if (empty($group->source))
			return array();

		if (isset($_REQUEST['media']) && is_array($_REQUEST['media']))
			$post_ids = array_map('intval', $_REQUEST['media']);
		elseif (!empty($_REQUEST['post']) && is_array($_REQUEST['post']))
			$post_ids = array_map('intval', $_REQUEST['post']);

		$post_ids[] = $post_id;
		return array_diff($this->model->get_translations('post', $post_id), $post_ids);
	}

	/*
	 * deletes the Lingotek document when a source document is deleted
	 *
	 * @since 0.1
	 *
	 * @param int $post_id
	 */
	public function delete_post($post_id) {
		static $avoid_recursion = array();

		if (!wp_is_post_revision($post_id) && !in_array($post_id, $avoid_recursion)) {
			// sync delete is not needed when emptying the bin as trash is synced
			if (empty($_REQUEST['delete_all'])) {
				$tr_ids = $this->get_translations_to_sync($post_id);
				$avoid_recursion = array_merge($avoid_recursion, array_values($tr_ids));
				foreach ($tr_ids as $tr_id) {
					wp_delete_post($tr_id, true); // forces deletion for the translations which are not already in the list
				}
			}
			$this->lgtm->delete_post($post_id);
		}
		// delete lingotek_profile term upon post deletion
		$terms = wp_get_object_terms($post_id, 'lingotek_profile');
		$term = array_pop($terms);
		if ($term) {
			wp_delete_term($term->term_id, 'lingotek_profile');
		}
		//delete lingotek_hash term upon post deletion
		$hash_terms = wp_get_object_terms($post_id, 'lingotek_hash');
		$hash_term = array_pop($hash_terms);
		if ($hash_term) {
			wp_delete_term($hash_term->term_id, 'lingotek_hash');
		}
	}

	/*
	 * sync trash
	 *
	 * @since 0.1
	 *
	 * @param int $post_id
	 */
	public function trash_post($post_id) {
		foreach ($this->get_translations_to_sync($post_id) as $tr_id)
			wp_trash_post($tr_id);
	}

	/*
	 * sync untrash
	 *
	 * @since 0.1
	 *
	 * @param int $post_id
	 */
	public function untrash_post($post_id) {
		foreach ($this->get_translations_to_sync($post_id) as $tr_id)
			wp_untrash_post($tr_id);
	}

	/*
	 * checks stored hash against current hash to report change
	 *
	 * @since 1.0
	 *
	 * @param wp_post $post_after
	 */
	protected function post_hash_has_changed($post_after) {
		if ($post_after->post_status === 'trash') {
			return false;
		}
		$document_id = 'lingotek_hash_' . $post_after->ID;
		$new_hash = md5(Lingotek_Group_Post::get_content($post_after));
		$old_term = $this->get_post_hash($post_after->ID);
		$old_hash = $old_term->description;

		// new or updated page
		if ($old_hash === null || strcmp($new_hash, $old_hash)) {
			if (empty($old_term)) {
				wp_insert_term($document_id, 'lingotek_hash', array('description' => $new_hash));
			}
			else {
				wp_update_term((int) $old_term->term_id, 'lingotek_hash', array('description' => $new_hash));
			}

			wp_set_object_terms($post_after->ID, $document_id, 'lingotek_hash');
			return true;
		}
		else {
			return false;
		}
	}

	/*
	 * returns a lingotek post hash if it exists
	 *
	 * @since 1.0
	 *
	 * @param int $post_id
	 */
	protected function get_post_hash($post_id) {
		if (taxonomy_exists('lingotek_hash')) {
			$terms = wp_get_object_terms($post_id, 'lingotek_hash');
			$term = array_pop($terms);
			return $term;
		}
		else {
			return null;
		}
	}
}
