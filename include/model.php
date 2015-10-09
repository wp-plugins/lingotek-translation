<?php

/*
 * Manages interactions with database
 * Factory for Lingotek_Group objects
 *
 * @since 0.1
 */
class Lingotek_Model {
	public $pllm; // Polylang model
	static public $copying_post;
	static public $copying_term;

	/*
	 * constructor
	 *
	 * @since 0.1
	 */
	public function __construct() {
		$this->pllm = $GLOBALS['polylang']->model;

		register_taxonomy('lingotek_profile', null , array('label' => false, 'public' => false, 'query_var' => false, 'rewrite' => false));
		register_taxonomy('lingotek_hash', null , array('label' => false, 'public' => false, 'query_var' => false, 'rewrite' => false));
	}

	/*
	 * get the strings groups as well as their count
	 *
	 * @since 0.2
	 *
	 * @return array
	 */
	public static function get_strings() {
		static $strings = array();
		if (empty($strings)) {
			PLL_Admin_Strings::init(); // enables sanitization filter

			foreach (PLL_Admin_Strings::get_strings() as $string) {
				$strings[$string['context']]['context'] = $string['context'];
				$strings[$string['context']]['count'] = empty($strings[$string['context']]['count']) ? 1 : $strings[$string['context']]['count'] + 1;
			}
			$strings = array_values($strings);
		}
		return $strings;
	}

	/*
	 * create a translation group object from a translation term
	 *
	 * @since 0.2
	 *
	 * @param object $term
	 * @return object
	 */
	protected function convert_term($term) {
		switch($term->taxonomy) {
			case 'term_translations':
				return new Lingotek_Group_Term($term, $this->pllm);

			case 'post_translations':
				$class = $term->name == $term->slug ? 'Lingotek_Group_Post' : 'Lingotek_Group_String';
				return new $class($term, $this->pllm);
		}
	}

	/*
	 * get the translation term of an object
	 *
	 * @since 0.2
	 *
	 * @param string $type either 'post' or 'term' or 'string'
	 * @param int|string $id post id or term id or strings translations group name
	 * @return object translation term
	 */
	public function get_group($type, $id) {
		switch ($type) {
			case 'post':
			case 'term':
				return ($term = $this->pllm->get_object_term((int) $id, $type . '_translations')) && !empty($term) ? $this->convert_term($term) : false;
			case 'string':
				if (is_numeric($id)) {
					$strings = self::get_strings();
					$id = $strings[$id]['context'];
				}
				return ($term = get_term_by('name', $id, 'post_translations')) && !empty($term) ? $this->convert_term($term) : false;
			default:
				return false;
		}
	}

	/*
	 * get the translation term of an object by its Lingotek document id
	 *
	 * @since 0.2
	 *
	 * @param string|object $document_id
	 * @return object translation term
	 */
	public function get_group_by_id($document_id) {
		// we already passed a translation group object
		if (is_object($document_id))
			return $document_id;

		$terms = get_terms(array('post_translations', 'term_translations'), array('slug' => $document_id));
		return is_wp_error($terms) || empty($terms) ? false : $this->convert_term(reset($terms));
	}

	/*
	 * get a translation profile
	 *
	 * @since 0.2
	 *
	 * @param string $type post type or taxonomy
	 * @param object $language
	 * @return array
	 */
	static public function get_profile($type, $language, $post_id = null) {
		$content_types = get_option('lingotek_content_type');
		$profiles = get_option('lingotek_profiles');

		// If a profile is set for a specific post/page get that first
		if ($post_id) {
			$terms = get_terms('lingotek_profile', 'orderby=count&hide_empty=0');
			foreach ($terms as $term) {
				$extracted_post_id = str_replace('lingotek_profile_', '', $term->name);
				if ($extracted_post_id == $post_id) {
					return $profiles[$term->description];
				}
			}
		}

		// default profile is manual except for post
		$default = 'post' == $type ? 'automatic' : 'manual';

		$profile = isset($content_types[$type]['sources'][$language->slug]) ?
			$content_types[$type]['sources'][$language->slug] :
			(isset($content_types[$type]['profile']) ? $content_types[$type]['profile'] : $default);

		return $profiles[$profile];
	}

	static public function get_prefs() {
		$default = array(
			'download_post_status' => Lingotek_Group_Post::SAME_AS_SOURCE,
			'auto_upload_post_statuses' => array(
				'draft' => 0, 	// ignore auto-upload
				'pending' => 1, // auto-upload
				'publish' => 1,
				'future' => 1,
				'private' => 0,
			),
			'delete_document_from_tms' => array(
				'delete' => 1,
			),
		);
		$prefs = array_merge($default, get_option('lingotek_prefs', $default)); // ensure defaults are set for missing keys
		return $prefs;
	}

	/*
	 * get a profile option
	 *
	 * @since 0.2
	 *
	 * @param string $item 'project_id' | 'workflow_id' | 'upload' | 'download'
	 * @param string $type post type or taxonomy
	 * @param object $source_language
	 * @param object $target_language optional, needed to get custom target informations 'workflow_id' | 'download'
	 * @return string | bool either the option or false if the translation is disabled
	 */
	static public function get_profile_option($item, $type, $source_language, $target_language = false, $post_id = null) {
		$profile = self::get_profile($type, $source_language, $post_id);
		if ('disabled' == $profile['profile'])
			return false;

		if (!empty($target_language) && isset($profile['targets'][$target_language->slug]) && !empty($profile['custom'][$item][$target_language->slug]))
			return $profile['custom'][$item][$target_language->slug];

		if (!empty($profile[$item]))
			return $profile[$item];

		$defaults = get_option('lingotek_defaults');
		return $defaults[$item];
	}

	/*
	 * find targets that are set to copy in a profile
	 *
	 * @since 1.1.1
	 *
	 * @param array $profile (use get_profile to retrieve)
	 * @return array of targets that should be copied. if none exist returns empty array
	 */
	public function targets_to_be_copied($profile) {
		if (isset($profile['targets']) && in_array('copy', $profile['targets'])) {
			$targets_to_copy = array_keys($profile['targets'], 'copy');
			return $targets_to_copy;
		}
		else {
			return array();
		}
	}

	/*
	 * copy a post from the source language to a target language
	 *
	 * @since 1.1.1
	 *
	 * @param object $post
	 * @param string $target polylang language slug (ex: en, de, fr, etc)
	 * @return $new_post_id if copy of post is successful, false otherwise
	 */
	public function copy_post($post, $target) {
		self::$copying_post = true;
		$document = $this->get_group('post', $post->ID);
		$prefs = self::get_prefs();
		$cp_lang = $this->pllm->get_language($target);
		$cp_post = (array) $post;
		$cp_post['post_status'] = ($prefs['download_post_status'] === 'SAME_AS_SOURCE')? $post->post_status : $prefs['download_post_status']; // status
		unset($cp_post['ID']);
		unset($cp_post['post_name']);
		if (!isset($document->desc_array[$target])) {
			$new_post_id = wp_insert_post($cp_post, true);
			if (!is_wp_error($new_post_id)) {
				$this->pllm->set_post_language($new_post_id, $cp_lang);
				wp_set_object_terms($new_post_id, $document->term_id, 'post_translations');
				$GLOBALS['polylang']->sync->copy_post_metas($document->source, $new_post_id, $cp_lang->slug);
				Lingotek_Group_Post::copy_or_ignore_metas($post->ID, $new_post_id);
				$document->desc_array[$target] = $new_post_id;
				$document->save();
			}
		}
		self::$copying_post = false;
	}

	public function copy_term($term, $target, $taxonomy) {
		self::$copying_term = true;
		$document = $this->get_group('term', $term->term_id);
		$cp_lang = $this->pllm->get_language($target);
		$cp_term = (array) $term;
		//unset($cp_term['term_id']);

		if (isset($cp_term['slug']) && term_exists($cp_term['slug'])) {
			$cp_term['slug'] .= '-' . $cp_lang->slug;
		}
		$new_term = wp_insert_term($cp_term['name'], $taxonomy, $cp_term);

		if (!is_wp_error($new_term)) {
			$this->pllm->set_term_language($new_term['term_id'], $cp_lang);
			wp_set_object_terms($new_term['term_id'], $document->term_id, 'term_translations');
			$document->desc_array[$target] = $new_term['term_id'];
			$document->save();
		}
		self::$copying_term = false;
	}

	/*
	 * uploads a new post to Lingotek TMS
	 *
	 * @since 0.1
	 *
	 * @param int $post_id
	 */
	public function upload_post($post_id) {
		$post = get_post($post_id);
		$language = $this->pllm->get_post_language($post_id);
		if (empty($post) || empty($language))
			return;

		$profile = self::get_profile($post->post_type, $language, $post_id);
		if ('disabled' == $profile['profile'])
			return;

		$client = new Lingotek_API();
		$external_url = add_query_arg(array('lingotek' => 1, 'document_id' => '{document_id}', 'locale' => '{locale}', 'type' => 'get'), site_url());

		$params = array(
			'title' => $post->post_title,
			'content' => Lingotek_Group_Post::get_content($post),
			'locale_code' => $language->lingotek_locale,
			'project_id' => self::get_profile_option('project_id', $post->post_type, $language, false, $post_id),
			'workflow_id' => self::get_profile_option('workflow_id', $post->post_type, $language, false, $post_id),
			'external_url' => $external_url,
		);

		$filter_ids = array();
		if (self::get_profile_option('primary_filter_id', $post->post_type, $language, false, $post_id)) {
			$filter_ids['fprm_id'] = self::get_profile_option('primary_filter_id',$post->post_type, $language, false, $post_id);
		}
		if (self::get_profile_option('secondary_filter_id', $post->post_type, $language, false, $post_id)) {
			$filter_ids['fprm_subfilter_id'] = self::get_profile_option('secondary_filter_id',$post->post_type, $language, false, $post_id);
		}
		$params = array_merge($params, $filter_ids);

		if (($document = $this->get_group('post', $post_id)) && 'edited' == $document->status) {
			$document->patch($post->post_title, $post, $external_url, $filter_ids);
		}

		elseif (!Lingotek_Group::$creating_translation && !self::$copying_post) {
			$document_id = $client->upload_document($params, $post->ID);

			if ($document_id) {
				Lingotek_Group_Post::create($post->ID , $language, $document_id);

				// If a translation profile has targets set to copy then copy them
				$targets_to_copy = $this->targets_to_be_copied($profile);
				if (!empty($targets_to_copy)) {
					foreach ($targets_to_copy as $target) {
						$this->copy_post($post, $target);
					}
				}
			}
		}
	}

	/*
	 * uploads a new term to Lingotek TMS
	 *
	 * @since 0.2
	 *
	 * @param int $term_id
	 * @param string $taxonomy
	 */
	public function upload_term($term_id, $taxonomy) {
		$term = get_term($term_id, $taxonomy);
		$language = $this->pllm->get_term_language($term_id);
		if (empty($term) || empty($language))
			return;

		$profile = self::get_profile($taxonomy, $language);
		if ('disabled' == $profile['profile'])
			return;

		$client = new Lingotek_API();

		$params = array(
			'title' => $term->name,
			'content' => Lingotek_Group_Term::get_content($term),
			'locale_code' => $language->lingotek_locale,
			'project_id' => self::get_profile_option('project_id', $taxonomy, $language),
			'workflow_id' => self::get_profile_option('workflow_id', $taxonomy, $language)
		);

		$filter_ids = array();
		if (self::get_profile_option('primary_filter_id', $taxonomy, $language)) {
			$filter_ids['fprm_id'] = self::get_profile_option('primary_filter_id', $taxonomy, $language);
		}
		if (self::get_profile_option('secondary_filter_id', $taxonomy, $language)) {
			$filter_ids['fprm_subfilter_id'] = self::get_profile_option('secondary_filter_id', $taxonomy, $language);
		}
		$params = array_merge($params, $filter_ids);

		if (($document = $this->get_group('term', $term_id)) && 'edited' == $document->status) {
			$document->patch($term->name, $term, '', $filter_ids);
		}

		elseif (!Lingotek_Group::$creating_translation && !self::$copying_term) {
			$document_id = $client->upload_document($params, $term_id);

			if ($document_id) {
				Lingotek_Group_Term::create($term_id, $taxonomy , $language, $document_id);

				// If a translation profile has targets set to copy then copy them
				$targets_to_copy = $this->targets_to_be_copied($profile);
				if (!empty($targets_to_copy)) {
					foreach ($targets_to_copy as $target) {
						$this->copy_term($term, $target, $taxonomy);
					}
				}
			}
		}
	}

	/*
	 * uploads a strings group to Lingotek TMS
	 *
	 * @since 0.2
	 *
	 * @param string $group
	 */
	public function upload_strings($group) {
		$language = $this->pllm->get_language($this->pllm->options['default_lang']);
		$profile = self::get_profile('string', $language);

		if ('disabled' == $profile['profile'])
			return;

		if (is_numeric($group)) {
			$strings = self::get_strings();
			$group = $strings[$group]['context'];
		}

		// check that we have a valid string group
		if (!in_array($group, wp_list_pluck(self::get_strings(), 'context')))
			return;

		$client = new Lingotek_API();

		$params = array(
			'title' => $group,
			'content' => Lingotek_Group_String::get_content($group),
			'locale_code' => $language->lingotek_locale,
			'project_id' => self::get_profile_option('project_id', 'string', $language),
			'workflow_id' => self::get_profile_option('workflow_id', 'string', $language)
		);

		$filter_ids = array();
		if (self::get_profile_option('primary_filter_id', 'string', $language)) {
			$filter_ids['fprm_id'] = self::get_profile_option('primary_filter_id', 'string', $language);
		}
		if (self::get_profile_option('secondary_filter_id', 'string', $language)) {
			$filter_ids['fprm_subfilter_id'] = self::get_profile_option('secondary_filter_id', 'string', $language);
		}
		$params = array_merge($params, $filter_ids);

		if (($document = $this->get_group('string', $group)) && 'edited' == $document->status) {
			$document->patch($group);
		}
		else {
			$document_id = $client->upload_document($params, $group);

			if ($document_id) {
				Lingotek_Group_String::create($group, $language, $document_id);
			}
		}
	}

	/*
	 * checks if the document can be upload to Lingotek
	 *
	 * @since 0.1
	 *
	 * @param string $type either 'post' or 'term'
	 * @param int $object_id post id or term id
	 * @return bool
	 */
	// FIXME should I check for disabled profile here?
	public function can_upload($type, $object_id) {
		$document = $this->get_group($type, $object_id);

		switch ($type) {
			case 'string':
/*
				$profile = self::get_profile('string', $this->pllm->get_language($this->pllm->options['default_lang']));
				if ('disabled' == $profile['profile'])
					return false;
*/
				if (empty($document))
					return true;

				elseif ($document->md5 != md5(Lingotek_Group_String::get_content($object_id))) { // check if source strings have not been modified
					$document->source_edited();
					return true;
				}

				return false;

			case 'post':
			case 'term':
				// first check that a language is associated to the object
				$language = call_user_func(array(&$this->pllm, 'get_'.$type.'_language'), $object_id);

				// FIXME how to get profile to check if disabled?

				return !empty($language) && (empty($document) ||
					(empty($document->translations) && empty($document->source))  || // specific for terms as document is never empty
					(isset($document) && 'edited' == $document->status && $document->source == $object_id));
		}
	}

	/*
	 * deletes a post
	 *
	 * @since 0.1
	 *
	 * @param int $object_id post id
	 */
	public function delete_post($object_id) {
		if ($document = $this->get_group('post', $object_id)) {
			$client = new Lingotek_API();

			if ($document->source == $object_id) {
				$client->delete_document($document->document_id, $object_id);
			}
			else {
				$this->pllm->delete_translation('post', $object_id);
				$lang = $this->pllm->get_post_language($object_id);
				$client->delete_translation($document->document_id, $lang->lingotek_locale, $object_id);
			}
		}
	}

	/*
	 * deletes a term
	 *
	 * @since 0.2
	 *
	 * @param int $object_id term id
	 */
	public function delete_term($object_id) {
		if ($document = $this->get_group('term', $object_id)) {
			$client = new Lingotek_API();

			if ($document->source == $object_id) {
				$client->delete_document($document->document_id, $object_id);
			}
			else {
				$lang = $this->pllm->get_term_language($object_id);
				$this->pllm->delete_term_language($object_id);
				$this->pllm->delete_translation('term', $object_id);
				$client->delete_translation($document->document_id, $lang->lingotek_locale, $object_id);
			}
		}
	}

	/*
	 * counts the number of targets per language
	 *
	 * @since 0.2
	 *
	 * @param array $groups array of serialized 'post_translations' or 'term_translations' description
	 * @return array number of targets per language
	 */
	protected function get_target_count($groups) {
		$targets = array_fill_keys($this->pllm->get_languages_list(array('fields' => 'slug')), 0);

		foreach ($groups as $group) {
			$group = unserialize($group);
			if (isset($group['lingotek']['translations'])) {
				foreach ($group['lingotek']['translations'] as $locale => $status) {
					if ('current' == $status && $language = $this->pllm->get_language($locale))
						$targets[$language->slug]++;
				}
			}
		}
		return $targets;
	}

	/*
	 * counts the number of sources and targets per language for a certain post type
	 *
	 * @since 0.2
	 *
	 * @param string $post_type
	 * @return array
	 */
	public function count_posts($post_type) {
		global $wpdb;

		static $r = array();
		if (!empty($r[$post_type]))
			return $r[$post_type];

		if (!post_type_exists($post_type))
			return;

		// gets all translations groups for the post type
		$groups = $wpdb->get_col($wpdb->prepare("
			SELECT DISTINCT tt.description FROM $wpdb->term_taxonomy AS tt
			INNER JOIN $wpdb->term_relationships AS tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
			INNER JOIN $wpdb->posts AS p ON p.ID = tr.object_id
			WHERE tt.taxonomy = %s
			AND p.post_type = %s
			AND p.post_status NOT IN ('trash', 'auto-draft')",
			'post_translations', $post_type
		));

		$targets = $this->get_target_count($groups);


		$group_ids = array();
		$disabled = array();

		foreach ($this->pllm->get_languages_list() as $language) {
			// counts all the posts in one language
			$n = $wpdb->get_var($wpdb->prepare("
				SELECT COUNT(*) FROM $wpdb->term_relationships AS tr
				INNER JOIN $wpdb->posts AS p ON p.ID = tr.object_id
				WHERE tr.term_taxonomy_id = %d
				AND p.post_type = %s
				AND p.post_status NOT IN ('trash', 'auto-draft')",
				$language->term_taxonomy_id, $post_type
			));

			$objects = $wpdb->get_col($wpdb->prepare("
				SELECT object_id FROM $wpdb->term_relationships AS tr
				INNER JOIN $wpdb->posts AS p ON p.ID = tr.object_id
				WHERE tr.term_taxonomy_id = %d
				AND p.post_type = %s
				AND p.post_status NOT IN ('trash', 'auto-draft')",
				$language->term_taxonomy_id, $post_type
			));

			foreach ($groups as $group) {
				$group = unserialize($group);
				if (array_key_exists($language->slug, $group)) {
					$group_ids[] = $group[$language->slug];
				}
			}

			$count = 0;
			foreach ($objects as $object) {
				$id = $object;
				if (!in_array($id, $group_ids)) {
					$profile = self::get_profile($post_type, $language, $id);
					if ($profile['profile'] == 'disabled' && in_array($id, $objects)) {
						$count += 1;
					}
				}
			}
			$disabled[$language->slug] = $count;

			// if a post is not a target, then it is source
			$sources[$language->slug] = $n - $targets[$language->slug];
			$sources[$language->slug] -= $disabled[$language->slug];
		}

		// untranslated posts have no associated translation group in DB
		// so let's count them indirectly

		// counts the number of translated posts
		$n_translated = $wpdb->get_var($wpdb->prepare("
			SELECT COUNT(*) FROM $wpdb->term_relationships AS tr
			INNER JOIN $wpdb->posts AS p ON p.ID = tr.object_id
			INNER JOIN $wpdb->term_taxonomy AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
			WHERE tt.taxonomy = %s
			AND p.post_type = %s
			AND p.post_status NOT IN ('trash', 'auto-draft')",
			'post_translations', $post_type
		));

		// untranslated = total - translated
		// total of posts translations groups = untranslated + number of translation groups stored in DB
		$count_posts = (array) wp_count_posts($post_type);
		unset($count_posts['trash'], $count_posts['auto-draft']); // don't count trash and auto-draft
		$total = array_sum($count_posts) - $n_translated + count($groups) - array_sum($disabled);

		return $r[$post_type] = compact('sources', 'targets', 'total');
	}

	/*
	 * counts the number of sources and targets per language for a certain taxonomy
	 *
	 * @since 0.2
	 *
	 * @param string $taxonomy
	 * @return array
	 */
	public function count_terms($taxonomy) {
		global $wpdb;

		static $r = array();
		if (!empty($r[$taxonomy]))
			return $r[$taxonomy];

		if (!taxonomy_exists($taxonomy))
			return;

		// gets all translations groups for the taxonomy
		$groups = $wpdb->get_col($wpdb->prepare("
			SELECT DISTINCT tt1.description FROM $wpdb->term_taxonomy AS tt1
			INNER JOIN $wpdb->term_relationships AS tr ON tt1.term_taxonomy_id = tr.term_taxonomy_id
			INNER JOIN $wpdb->term_taxonomy AS tt2 ON tt2.term_id = tr.object_id
			WHERE tt1.taxonomy = %s
			AND tt2.taxonomy = %s",
			'term_translations', $taxonomy
		));

		$targets = $this->get_target_count($groups);

		$group_ids = array();
		$disabled = array();

		foreach ($this->pllm->get_languages_list() as $language) {
			// counts all the terms in one language
			$n = $wpdb->get_var($wpdb->prepare("
				SELECT COUNT(*) FROM $wpdb->term_relationships AS tr
				INNER JOIN $wpdb->term_taxonomy AS tt ON tt.term_id = tr.object_id
				WHERE tr.term_taxonomy_id = %d
				AND tt.taxonomy = %s",
				$language->tl_term_taxonomy_id, $taxonomy
			));

			$objects = $wpdb->get_col($wpdb->prepare("
				SELECT object_id FROM $wpdb->term_relationships AS tr
				INNER JOIN $wpdb->term_taxonomy AS tt ON tt.term_id = tr.object_id
				WHERE tr.term_taxonomy_id = %d
				AND tt.taxonomy = %s",
				$language->tl_term_taxonomy_id, $taxonomy
			));

			$count = 0;
			foreach ($groups as $group) {
				$group = unserialize($group);
				if (array_key_exists($language->slug, $group)) {
					$group_ids[] = $group[$language->slug];
					$profile = self::get_profile($taxonomy, $language, $group[$language->slug]);
					if ($profile['profile'] == 'disabled' && !isset($group['lingotek'])) {
						$count += 1;
					}
				}
			}


			$disabled[$language->slug] = $count;

			// if a term is not a target, then it is a source
			$sources[$language->slug] = $n - $targets[$language->slug];
			$sources[$language->slug] -= $disabled[$language->slug];
		}

		$total = count($groups);

		// default categories are created by Polylang in all languages
		// don't count them as sources if they are not associated to the TMS
		if ('category' === $taxonomy) {
			$term_id = get_option('default_category');
			$group = $this->get_group('term', $term_id);
			foreach($this->pllm->get_languages_list() as $language) {
				if (empty($group->source) || ($group->get_source_language()->slug != $language->slug && empty($group->translations[$language->locale]))) {
					if ($language->slug != $this->pllm->options['default_lang']) {
						$sources[$language->slug]--;
					}
				}
			}
			// Remove category targets from being counted until they are downloaded. Fixed target categories being counted as source languages.
			foreach ($groups as $group) {
				$group = unserialize($group);
				if (isset($group['lingotek']['translations'])) {
					foreach ($group['lingotek']['translations'] as $locale => $status) {
						if (('pending' == $status || 'ready' == $status) && $language = $this->pllm->get_language($locale)) {
							if ($sources[$language->slug] > 0) {
								$sources[$language->slug]--;
							}
						}
					}
				}
			}
			if (count($sources) == 1 && $total != $sources[$this->pllm->options['default_lang']]) {
				$total = $sources[$this->pllm->options['default_lang']];
			}
		}

		$total -= array_sum($disabled);
		return $r[$taxonomy] = compact('sources', 'targets', 'total');
	}
}
