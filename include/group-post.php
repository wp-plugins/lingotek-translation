<?php

/*
 * Translations groups for posts, pages and custom post types
 *
 * @since 0.2
 */
class Lingotek_Group_Post extends Lingotek_Group {

	const SAME_AS_SOURCE = 'SAME_AS_SOURCE'; // pref constant used for downloaded translations

	/*
	 * set a translation term for an object
	 *
	 * @since 0.2
	 *
	 * @param int $object_id post id
	 * @param object $language
	 * @param string $document_id translation term name (Lingotek document id)
	 */
	public static function create($object_id, $language, $document_id) {
		$data = array(
			'lingotek' => array(
				'type'         => get_post_type($object_id),
				'source'       => $object_id,
				'status'       => 'importing',
				'translations' => array()
			),
			$language->slug => $object_id // for Polylang
		);

		self::_create($object_id, $document_id, $data, 'post_translations');
	}

	/*
	 * returns content type fields
	 *
	 * @since 0.2
	 *
	 * @param string $post_type
	 * @return array
	 */
	static public function get_content_type_fields($post_type) {
		$arr = 'attachment' == $post_type ?
			array(
				'post_title'   => __('Title', 'wp-lingotek'),
				'post_excerpt' => __('Caption', 'wp-lingotek'),
				'metas'        => array('_wp_attachment_image_alt' => __('Alternative Text', 'wp-lingotek')),
				'post_content' => __('Description', 'wp-lingotek'),
			) :
			array(
				'post_title'   => __('Title', 'wp-lingotek'),
				'post_name'    => __('Slug', 'wp-lingotek'),
				'post_content' => __('Content', 'wp-lingotek'),
				'post_excerpt' => __('Excerpt', 'wp-lingotek')
			);

		// add the custom fields from wpml-config.xml <custom-fields> sections
		$wpml_config = PLL_WPML_Config::instance();

		if (isset($wpml_config->tags['custom-fields'])) {
			foreach ($wpml_config->tags['custom-fields'] as $context) {
				foreach ($context['custom-field'] as $cf) {
					if ('translate' == $cf['attributes']['action'])
						$arr['metas'][$cf['value']] = $cf['value'];
				}
			}
		}

		// allow plugins to modify the fields to translate
		return apply_filters('lingotek_post_content_type_fields', $arr, $post_type);
	}

	/*
	 * returns the content to translate
	 *
	 * @since 0.2
	 *
	 * @param object $post
	 * @return string json encoded content to translate
	 */
	public static function get_content($post) {
		$fields = self::get_content_type_fields($post->post_type);
		$content_types = get_option('lingotek_content_type');

		foreach (array_keys($fields) as $key) {
			if ('metas' == $key) {
				foreach (array_keys($fields['metas']) as $meta) {
					if (empty($content_types[$post->post_type]['fields']['metas'][$meta]) && $value = get_post_meta($post->ID, $meta, true))
						$arr['metas'][$meta] = $value;
				}
			}

			// send slug for translation only if it has been modified
			elseif('post_name' == $key && empty($content_types[$post->post_type]['fields'][$key])) {
				$default_slug = sanitize_title($post->post_title); // default slug created by WP
				if ($default_slug != $post->post_name)
					$arr['post'][$key] = $post->$key;
			}

			elseif (empty($content_types[$post->post_type]['fields'][$key])) {
				$arr['post'][$key] = $post->$key;
			}
		}

		return json_encode($arr);
	}

	public static function is_valid_auto_upload_post_status($post_status) {
		$prefs = Lingotek_Model::get_prefs();
		$valid_statuses = $prefs['auto_upload_post_statuses'];
		$valid = array_key_exists($post_status, $valid_statuses) && $valid_statuses[$post_status];
		return $valid;
	}

	/*
	 * requests translations to Lingotek TMS
	 *
	 * @since 0.1
	 */
	public function request_translations() {
		if (isset($this->source)) {
			$language = $this->pllm->get_post_language((int) $this->source);
			$this->_request_translations($language);
		}
	}

	/*
	 * create a translation downloaded from Lingotek TMS
	 *
	 * @since 0.1
	 * @uses Lingotek_Group::safe_translation_status_update() as the status can be automatically set by the TMS callback
	 *
	 * @param string $locale
	 */
	public function create_translation($locale) {
		$client = new Lingotek_API();

		if (false === ($translation = $client->get_translation($this->document_id, $locale)))
			return;

		self::$creating_translation = true;
		$prefs = Lingotek_Model::get_prefs(); // need an array by default

		$translation = json_decode($translation, true); // wp_insert_post expects array
		$tr_post = $translation['post'];

		$post = get_post($this->source); // source post
		$tr_post['post_status'] = ($prefs['download_post_status'] === self::SAME_AS_SOURCE)? $post->post_status : $prefs['download_post_status']; // status

		// update existing translation
		if ($tr_id = $this->pllm->get_post($this->source, $locale)) {
			$tr_post['ID'] = $tr_id;
			wp_update_post($tr_post);

			$this->safe_translation_status_update($locale, 'current');
		}

		// create new translation
		else {
			unset($post->post_name); // forces the creation of a new default slug if not translated by Lingotek
			$tr_post = array_merge((array) $post , $tr_post); // copy all untranslated fields from the original post
			$tr_post['ID'] = null; // will force the creation of a new post

			// translate parent
			$tr_post['post_parent'] = ($post->post_parent && $tr_parent = $this->pllm->get_translation('post', $post->post_parent, $locale)) ? $tr_parent : 0;

			if ('attachment' == $post->post_type) {
				$tr_id = wp_insert_attachment($tr_post);
				add_post_meta($tr_id, '_wp_attachment_metadata', get_post_meta($this->source, '_wp_attachment_metadata', true));
				add_post_meta($tr_id, '_wp_attached_file', get_post_meta($this->source, '_wp_attached_file', true));
			}
			else {
				$tr_id = wp_insert_post($tr_post);
			}

			if ($tr_id) {
				$tr_lang = $this->pllm->get_language($locale);
				$this->pllm->set_post_language($tr_id, $tr_lang);

				$this->safe_translation_status_update($locale, 'current', array($tr_lang->slug => $tr_id));
				wp_set_object_terms($tr_id, $this->term_id, 'post_translations');

				// assign terms and metas
				$GLOBALS['polylang']->sync->copy_post_metas($this->source, $tr_id, $tr_lang->slug);

				// translate metas
				if (!empty($translation['metas'])) {
					foreach ($translation['metas'] as $key => $meta)
						update_post_meta($tr_id, $key, $meta);
				}
			}
		}

		self::$creating_translation = false;
	}

	/*
	 * checks if content should be automatically uploaded
	 *
	 * @since 0.2
	 *
	 * @return bool
	 */
	public function is_automatic_upload() {
		return 'automatic' == Lingotek_Model::get_profile_option('upload', get_post_type($this->source), $this->get_source_language());
	}

	/*
	 * get the the language of the source post
	 *
	 * @since 0.2
	 *
	 * @return object
	 */
	public function get_source_language() {
		return $this->pllm->get_post_language($this->source);
	}
}
