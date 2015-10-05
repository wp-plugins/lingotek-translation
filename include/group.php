<?php

/*
 * Abstract class for Translations groups objects
 *
 * @since 0.2
 */
abstract class Lingotek_Group {
	static public $creating_translation; // used to avoid uploading a translation when using automatinc upload

	/*
	 * constructor
	 *
	 * @since 0.2
	 */
	public function __construct($term, &$pllm) {
		$this->pllm = &$pllm;
		$this->load($term);
	}

	/*
	 * assigns this object properties from the underlying term
	 *
	 * @since 0.2
	 *
	 * @param object $term term translation object
	 */
	protected function load($term) {
		$this->term_id = (int) $term->term_id;
		$this->tt_id = (int) $term->term_taxonomy_id;
		$this->document_id = $term->slug;
		$this->taxonomy = $term->taxonomy;
		$this->desc_array = unserialize($term->description);

		foreach (array('type', 'source', 'status', 'translations') as $prop)
			$this->$prop = &$this->desc_array['lingotek'][$prop];
	}

	/*
	 * updates the translation term in DB
	 *
	 * @since 0.2
	 */
	public function save() {
		wp_update_term((int) $this->term_id, $this->taxonomy, array('slug' => $this->document_id, 'name' => $this->document_id, 'description' => serialize($this->desc_array)));
	}

	/*
	 * provides a safe way to update the translations statuses when receiving "simultaneous" TMS callbacks
	 *
	 * @since 0.2
	 *
	 * @param string $locale
	 * @param string $status
	 * @param array $arr translations to add
	 */
	protected function safe_translation_status_update($locale, $status, $arr = array()) {
		global $wpdb;
		$wpdb->query("LOCK TABLES $wpdb->term_taxonomy WRITE");
		$d = $wpdb->get_var($wpdb->prepare("SELECT description FROM $wpdb->term_taxonomy WHERE term_taxonomy_id = %d", $this->tt_id));
		$d = unserialize($d);
		$this->translations[$locale] = $d['lingotek']['translations'][$locale] = $status;
		$d = array_merge($d, $arr); // optionally add a new translation
		$d = serialize($d);
		$wpdb->query($wpdb->prepare("UPDATE $wpdb->term_taxonomy SET description = %s WHERE term_taxonomy_id = %d", $d, $this->tt_id));
		$wpdb->query("UNLOCK TABLES");
	}

	/*
	 * creates a new term translation object in DB
	 *
	 * @since 0.2
	 *
	 * @param int $object_id the id of the object to translate
	 * @param string $document_id Lingotek document id
	 * @param array $desc data to store in the Lingotek array
	 * @param string $taxonomy either 'post_translations' or 'term_translations'
	 */
	protected static function _create($object_id, $document_id, $desc, $taxonomy) {
		$terms = wp_get_object_terms($object_id, $taxonomy);
		$term = array_pop($terms);

		if (empty($term)) {
			wp_insert_term($document_id, $taxonomy, array('description' => serialize($desc)));
		}

		// the translation already exists but was not managed by Lingotek
		else {
			if (is_array($old_desc = maybe_unserialize($term->description)))
				$desc = array_merge($old_desc, $desc);
			wp_update_term((int) $term->term_id, $taxonomy, array('slug' => $document_id, 'name' => $document_id, 'description' => serialize($desc)));
		}

		wp_set_object_terms($object_id, $document_id, $taxonomy);
	}

	/*
	 * disassociates translations from the Lingotek TMS
	 *
	 * @since 0.2
	 *
	 * @param bool $delete whether to delete the Lingotek document or not
	 */
	public function disassociate() {
		$client = new Lingotek_API();
		$prefs = Lingotek_Model::get_prefs();

		if ($prefs['delete_document_from_tms']) {
			$client->delete_document($this->document_id, $this->source);
			unset($this->desc_array['lingotek']);
			$this->save();
		}
		else {
			unset($this->desc_array['lingotek']);
			$this->save();
		}

	}

	/*
	 * uploads a modified source
	 *
	 * @since 0.2
	 *
	 * @param string $title
	 * @param object $content can be a post object, a term object
	 */
	public function patch($title, $content, $external_url = '', $filters = array()) {
		$client = new Lingotek_API();

		$params = array(
			'title' => $title,
			'content' => $this->get_content($content),
			'external_url' => $external_url,
		);
		$params = array_merge($params, $filters);

		$res = $client->patch_document($this->document_id, $params, $this->source);

		if ($res) {
			$this->status = 'importing';
			$this->translations = array_fill_keys(array_keys($this->translations), 'pending');
			$this->save();
		}
	}

	/*
	 * checks the status of source document
	 *
	 * @since 0.2
	 */
 	public function source_status() {
		$client = new Lingotek_API();

		if ('importing' == $this->status && $client->document_exists($this->document_id, $this->source)){
			$this->status = 'current';
			$this->save();
		}
	}

	/*
	 * sets source status to ready
	 *
	 * @since 0.2
	 */
	public function source_ready() {
		$this->status = 'current';
		$this->save();
	}

	/*
	 * requests a translation to Lingotek TMS
	 *
	 * @since 0.2
	 *
	 * @param string $locale
	 */
	public function request_translation($locale) {
		$client = new Lingotek_API();
		$language = $this->pllm->get_language($locale);
		$workflow = Lingotek_Model::get_profile_option('workflow_id', $this->type, $this->get_source_language(), $language, $this->source);
		$args = $workflow ? array('workflow_id' => $workflow) : array();

		if (!$this->is_disabled_target($language) && empty($this->translations[$language->locale])) {
			// don't change translations to pending if the api call failed
			if ($client->request_translation($this->document_id, $language->locale, $args, $this->source)) {
				$this->status = 'current';
				$this->translations[$language->locale] = 'pending';
			}

			$this->save();
		}
	}

	/*
	 * requests translations to Lingotek TMS
	 *
	 * @since 0.2
	 *
	 * @param object $source_language language of the source
	 */
	protected function _request_translations($source_language) {
		$type_id;
		$client = new Lingotek_API();

		foreach ($this->pllm->get_languages_list() as $lang) {
			if ($source_language->slug != $lang->slug && !$this->is_disabled_target($source_language, $lang) && empty($this->translations[$lang->locale])) {
				$workflow = Lingotek_Model::get_profile_option('workflow_id', $this->type, $source_language, $lang, $this->source);
				$args = $workflow ? array('workflow_id' => $workflow) : array();

				if ($this->type == 'string') {
					$type_id = $this->name;
				}
				else {
					$type_id = $this->source;
				}
				// don't change translations to pending if the api call failed
				if ($client->request_translation($this->document_id, $lang->locale, $args, $type_id)) {
					$this->status = 'current';
					$this->translations[$lang->locale] = 'pending';
				}
			}
		}

		$this->save();
	}

	/*
	 * checks the translations status of a document
	 *
	 * @since 0.1
	 */
	public function translations_status() {
		$client = new Lingotek_API();
		$translations = $client->get_translations_status($this->document_id, $this->source); // key are Lingotek locales
		foreach($this->translations as $locale => $status) {
			$lingotek_locale = $this->pllm->get_language($locale)->lingotek_locale;
			if ('current' != $status && isset($translations[$lingotek_locale]) && 100 == $translations[$lingotek_locale])
				$this->translations[$locale] = 'ready';
		}
		$this->save();
	}

	/*
	 * sets translation status to ready
	 *
	 * @since 0.1
	 * @uses Lingotek_Group::safe_translation_status_update() as the status can be automatically set by the TMS callback
	 */
	public function translation_ready($locale) {
		$this->safe_translation_status_update($locale, 'ready');
	}

	/*
	 * attempts to create all translations from an object
	 *
	 * @since 0.2
	 */
	public function create_translations() {
		if (isset($this->translations)) {
			foreach ($this->translations as $locale => $status)
				if ('pending' == $status || 'ready' == $status)
					$this->create_translation($locale);
		}
	}

	/*
	 * sets document status to edited
	 *
	 * @since 0.1
	 */
	public function source_edited() {
		$this->status = 'edited';
		$this->translations = array_fill_keys(array_keys($this->translations), 'not-current');
		$this->save();
	}

	/*
	 * returns true if at least one of the translations has the requested status
	 *
	 * @since 0.2
	 *
	 * @param string $status
	 * @return bool
	 */
	public function has_translation_status($status) {
		return array_intersect(array_keys($this->translations, $status), $this->pllm->get_languages_list(array('fields' => 'locale')));
	}

	/*
	 * checks if target should be automatically downloaded
	 *
	 * @since 0.2
	 *
	 * @param string $locale
	 * @return bool
	 */
	public function is_automatic_download($locale) {
		return 'automatic' == Lingotek_Model::get_profile_option('download', $this->type, $this->get_source_language(), $this->pllm->get_language($locale), $this->source);
	}

	/*
	 * checks if translation is disabled for a target language
	 *
	 * @since 0.2
	 *
	 * @param string $type post type or taxonomy
	 * @param object $language
	 */
	public function is_disabled_target($language, $target = null) {
		$profile = Lingotek_Model::get_profile($this->type, $language, $this->source);
		if ($target) {
			return isset($profile['targets'][$target->slug]) && ('disabled' == $profile['targets'][$target->slug] || 'copy' == $profile['targets'][$target->slug]);
		}
		else {
			return isset($profile['targets'][$language->slug]) && ('disabled' == $profile['targets'][$language->slug] || 'copy' == $profile['targets'][$language->slug]);
		}
	}
}
