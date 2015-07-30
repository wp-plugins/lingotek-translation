<?php

/*
 * Manages automatic upload
 *
 * @since 0.2
 */
class Lingotek_Filters_Media extends PLL_Admin_Filters_Media {
	public $lgtm; // Lingotek model

	/*
	 * Constructor
	 *
	 * @since 0.2
	 */
	public function __construct(&$polylang) {
		parent::__construct($polylang);

		$this->lgtm = &$GLOBALS['wp_lingotek']->model;

		add_action('edit_attachment', array(&$this, 'edit_attachment'));
		add_action('add_attachment', array(&$this, 'add_attachment'), 11); // after Polylang
	}

	/*
	 * marks the attachment as edited if needed
	 *
	 * @since 0.2
	 *
	 * @param int $post_id
	 */
	public function edit_attachment($post_id) {
		$document = $this->lgtm->get_group('post', $post_id);

		// FIXME how to check if the attachment is really modified?
		if ($document && $post_id == $document->source) {
			$document->source_edited();

			if ($document->is_automatic_upload()) {
				$this->lgtm->upload_post($post_id);
			}
		}
	}

	/*
	 * uploads an attachment when saved for the first time
	 *
	 * @since 0.2

	 * @param int $post_id
	 */
	public function add_attachment($post_id) {
		if ($this->model->is_translated_post_type('attachment') && 'automatic' == Lingotek_Model::get_profile_option('upload', 'attachment', $this->model->get_post_language($post_id)) && $this->lgtm->can_upload('post', $post_id))
			$this->lgtm->upload_post($post_id);
	}
}
