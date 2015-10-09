<?php

/*
 * Translations groups for strings
 *
 * @since 0.2
 */
class Lingotek_Group_String extends Lingotek_Group {

	/*
	 * assigns this object properties from the underlying term
	 *
	 * @since 0.2
	 *
	 * @param object $term term translation object
	 */
	protected function load($term) {
		parent::load($term);
		$this->name = $term->name;
		$this->md5 = &$this->desc_array['lingotek']['md5'];
	}

	/*
	 * updates the translation term in DB
	 *
	 * @since 0.2
	 */
	public function save() {
		wp_update_term((int) $this->term_id, $this->taxonomy, array('slug' => $this->document_id, 'name' => $this->name, 'description' => serialize($this->desc_array)));
	}

	/*
	 * set a translation term for a strings group
	 *
	 * @since 0.2
	 *
	 * @param string string group name
	 * @param object $language
	 * @param string $document_id translation term name (Lingotek document id)
	 */
	public static function create($name, $language, $document_id) {
		$desc = array(
			'lingotek' => array(
				'type'         => 'string',
				'md5'          => md5(self::get_content($name)),
				'source'       => $language->mo_id,
				'status'       => 'importing',
				'translations' => array()
			),
		);

		$terms = wp_get_object_terms($language->mo_id, 'post_translations');

		// the translation already exists but was previously disassociated
		if ($key = array_search($name, wp_list_pluck($terms, 'name'))) {
			wp_update_term((int) $terms[$key]->term_id, 'post_translations', array('slug' => $document_id, 'name' => $name, 'description' => serialize($desc)));
		}

		else {
			wp_insert_term($name, 'post_translations', array('slug' => $document_id, 'description' => serialize($desc)));
		}

		wp_set_object_terms($language->mo_id, $document_id, 'post_translations', true); // add terms

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
			$client->delete_document($this->document_id, $this->name);
			wp_delete_term($this->term_id, 'post_translations');
		}
		else {
			wp_delete_term($this->term_id, 'post_translations');
		}
	}

	/*
	 * uploads a modified source
	 *
	 * @since 0.2
	 *
	 * @param string $group group name
	 * @param string $empty used for compatibility with parent class
	 */
	public function patch($group, $empty = '', $external_url = '', $filters = array()) {
		$client = new Lingotek_API();
		$content = $this->get_content($group);

		$params = array(
			'title' => $group,
			'content' => $content,
			'external_url' => $external_url,
		);
		$params = array_merge($params, $filters);

		$res = $client->patch_document($this->document_id, $params, $group);

		if ($res) {
			$this->md5 = md5($content);
			$this->status = 'importing';
			$this->translations = array_fill_keys(array_keys($this->translations), 'pending');
			$this->save();
		}
	}

	/*
	 * returns the content to translate
	 *
	 * @since 0.2
	 *
	 * @param object $group string group name
	 * @return string json encoded content to translate
	 */
	public static function get_content($group) {
		foreach (PLL_Admin_Strings::get_strings() as $string) {
			if ($string['context'] == $group)
				$arr[$string['string']] = $string['string'];
		}
		return json_encode($arr);
	}

	/*
	 * requests translations to Lingotek TMS
	 *
	 * @since 0.2
	 */
	public function request_translations() {
		if (isset($this->source))
			$this->_request_translations($this->get_source_language());
	}

	/*
	 * create a translation downloaded from Lingotek TMS
	 *
	 * @since 0.2
	 * @uses Lingotek_Group::safe_translation_status_update() as the status can be automatically set by the TMS callback
	 *
	 * @param string $locale
	 */
	public function create_translation($locale) {
		$client = new Lingotek_API();

		if (false === ($translation = $client->get_translation($this->document_id, $locale, $this->name)))
			return;

		$strings = wp_list_pluck(PLL_Admin_Strings::get_strings(), 'name', 'string'); // get the strings name for the filter
		$translations = json_decode($translation, true); // wp_insert_post expects array
		$language = $this->pllm->get_language($locale);

		$mo = new PLL_MO();
		$mo->import_from_db($language);

		foreach ($translations as $key => $translation) {
			$translation = apply_filters('pll_sanitize_string_translation', $translation, $strings[$key], $this->name);
			$mo->add_entry($mo->make_entry($key, $translation));
		}

		$mo->export_to_db($language);
		$this->safe_translation_status_update($locale, 'current');
	}

	/*
	 * checks if content should be automatically uploaded
	 *
	 * @since 0.2
	 *
	 * @return bool
	 */
	public function is_automatic_upload() {
		return 'automatic' == Lingotek_Model::get_profile_option('upload', 'string', $this->get_source_language());
	}

	/*
	 * get the the language of the source string (always the default language)
	 *
	 * @since 0.2
	 *
	 * @return object
	 */
	public function get_source_language() {
		return $this->pllm->get_language($this->pllm->options['default_lang']);
	}
}
