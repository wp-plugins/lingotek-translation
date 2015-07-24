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

	/*
	 * Constructor
	 *
	 * @since 0.1
	 */
	public function __construct(&$polylang) {
		parent::__construct($polylang);

		$this->lgtm = &$GLOBALS['wp_lingotek']->model;

		// automatic upload
		add_action('post_updated', array(&$this, 'post_updated'), 10, 3);

		// trash sync
		add_action('trashed_post', array(&$this, 'trash_post'));
		add_action('untrashed_post', array(&$this, 'untrash_post'));
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
			if (empty($document->source))
				parent::add_meta_boxes($post_type);
		}
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
		if (!$this->model->is_translated_post_type($post->post_type))
			return;

		if (!isset($_REQUEST['import'])) {
			parent::save_post($post_id, $post, $update);

			if (!wp_is_post_revision($post_id) && 'auto-draft' != $post->post_status && Lingotek_Group_Post::is_valid_auto_upload_post_status($post->post_status) && 'automatic' == Lingotek_Model::get_profile_option('upload', $post->post_type, $this->model->get_post_language($post_id)) && !(isset($_POST['action']) && 'heartbeat' == $_POST['action']) && $this->lgtm->can_upload('post', $post_id)) {
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
		if ($this->can_save_post_data($post_id, $post_after, true)) {
			$document = $this->lgtm->get_group('post', $post_id);

			if ($document && $post_id == $document->source && md5(Lingotek_Group_Post::get_content($post_after)) != md5(Lingotek_Group_Post::get_content($post_before))) {
				$document->source_edited();

				if ($document->is_automatic_upload() && Lingotek_Group_Post::is_valid_auto_upload_post_status($post_after->post_status)) {
					$this->lgtm->upload_post($post_id);
				}
			}
		}
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
}
