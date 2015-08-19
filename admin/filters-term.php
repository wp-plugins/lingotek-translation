<?php

/*
 * Modifies Polylang filters
 * Manages automatic upload
 *
 * @since 0.2
 */
class Lingotek_Filters_Term extends PLL_Admin_Filters_Term {
	public $lgtm; // Lingotek model
	protected $old_term; // used to save old md5sum of a term when it is edited

	/*
	 * Constructor
	 *
	 * @since 0.2
	 */
	public function __construct(&$polylang) {
		parent::__construct($polylang);

		$this->lgtm = &$GLOBALS['wp_lingotek']->model;

		add_action('edit_terms', array(&$this, 'save_old_term'), 10, 2);
		add_action('edited_term', array(&$this, 'edited_term'), 10, 3);
	}

	/*
	 * controls whether to display the language metabox or not
	 *
	 * @since 0.2
	 */
	public function edit_term_form($tag) {
		if ($this->model->is_translated_taxonomy($tag->taxonomy)) {
			$document = $this->lgtm->get_group('term', $tag->term_id);
			if (empty($document->source))
				parent::edit_term_form($tag);
		}
	}

	/*
	 * uploads a term when saved for the first time
	 *
	 * @since 0.2

	 * @param int $term_id
	 * @param int $tt_id term taxononomy id
	 * @param string $taxonomy
	 */
	public function save_term($term_id, $tt_id, $taxonomy) {
		if (!$this->model->is_translated_taxonomy($taxonomy))
			return;

		if (!isset($_REQUEST['import'])) {
			parent::save_term($term_id, $tt_id, $taxonomy);

			if ('automatic' == Lingotek_Model::get_profile_option('upload', $taxonomy, $this->model->get_term_language($term_id)) && $this->lgtm->can_upload('term', $term_id))
				$this->lgtm->upload_term($term_id, $taxonomy); {
			}
		}
	}

	/*
	 * saves the md5sum of a term before it is edited
	 *
	 * @since 0.2
	 *
	 * @param int $term_id
	 * @param string $taxonomy
	 */
	public function save_old_term($term_id, $taxonomy) {
		if (pll_is_translated_taxonomy($taxonomy))
			$this->old_term = md5(Lingotek_Group_Term::get_content(get_term($term_id, $taxonomy)));
	}

	/*
	 * marks the term as edited if needed
	 *
	 * @since 0.2
	 *
	 * @param int $term_id
	 * @param int $tt_id not used
	 * @param string $taxonomy
	 */
	public function edited_term($term_id, $tt_id, $taxonomy) {
		if (pll_is_translated_taxonomy($taxonomy)) {
			$document = $this->lgtm->get_group('term', $term_id);

			if ($document && $term_id == $document->source && md5(Lingotek_Group_Term::get_content(get_term($term_id, $taxonomy))) != $this->old_term) {
				$document->source_edited();

				if ($document->is_automatic_upload()) {
					$this->lgtm->upload_term($term_id, $taxonomy);
				}
			}
		}
	}

	/*
	 * get translations ids to sync for delete
	 * since we can't sync all translations as we get conflicts when attempting to act two times on the same
	 *
	 * @since 0.2
	 *
	 * @param int $term_id
	 * @return array
	 */
	protected function get_translations_to_sync($term_id) {
		// don't synchronize disassociated terms
		$group = $this->lgtm->get_group('term', $term_id);
		if (empty($group->source))
			return array();

		if (isset($_REQUEST['delete_tags']) && is_array($_REQUEST['delete_tags']))
			$term_ids = array_map('intval', $_REQUEST['delete_tags']);

		$term_ids[] = $term_id;
		return array_diff($this->model->get_translations('term', $term_id), $term_ids);
	}

	/*
	 * deletes the Lingotek document when a source document is deleted
	 *
	 * @since 0.2
	 *
	 * @param int $term_id
	 */
	public function delete_term($term_id) {
		$taxonomy = substr(current_filter(), 7);
		foreach ($this->get_translations_to_sync($term_id) as $tr_id) {
			wp_delete_term($tr_id, $taxonomy); // forces deletion for the translations which are not already in the list
		}
		$this->lgtm->delete_term($term_id);
	}
}
