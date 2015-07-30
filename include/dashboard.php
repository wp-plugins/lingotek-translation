<?php

/*
 * a class to handle Lingotek dashboard requests
 *
 * @since 0.1
 */
class Lingotek_Dashboard {

	/*
	 * Constructor
	 *
	 * @since 0.1
	 */
	public function __construct() {
	}

	/**
	 * Lingotek
	 */
	function ajax_language_dashboard() {
		global $polylang;

		$request_method = isset($_REQUEST['_method']) ? $_REQUEST['_method'] : (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET');

		$response = array(
			'method' => $request_method
		);
		switch ($request_method) {
			case 'POST':
				if (isset($_REQUEST['code'], $_REQUEST['native'], $_REQUEST['direction'])) {
					$name = $_REQUEST['native'];
					$slug = substr($_REQUEST['code'], 0, strpos($_REQUEST['code'], '_')); // 3rd parameter of strstr needs PHP 5.3
					$locale = Lingotek::map_to_wp_locale($_REQUEST['code']);
					
					// avoid conflicts between language slugs
					$existing_slugs = $polylang->model->get_languages_list(array('fields' => 'slug'));
					if (!empty($existing_slugs) && in_array($slug, $existing_slugs))
						$slug = strtolower(str_replace('_', '-', $locale));
					
					$rtl = $_REQUEST['direction'] == 'RTL';
					$term_group = 0;

					// adds the language
					$polylang->model->add_language(compact('name', 'slug', 'locale', 'rtl', 'term_group'));

					// attempts to install the language pack
					require_once(ABSPATH . 'wp-admin/includes/translation-install.php');
					wp_download_language_pack($locale);

					// force checking for themes and plugins translations updates
					wp_update_themes();
					wp_update_plugins();

					$response = array(
						'request' => 'POST: add target language to CMS and Lingotek Project Language',
						'locale' => $_REQUEST['code'],
						'xcode' => $locale,
						'active' => 1,
						'enabled' => 1,
						'source' => self::get_counts_by_type($locale, 'sources'),
						'target' => self::get_counts_by_type($locale, 'targets')
					);
					status_header(200);
				}
				break;

			case 'DELETE':
				$body = file_get_contents("php://input");
				$code = str_replace('code=', '', $body);
				$lang = $polylang->model->get_language(Lingotek::map_to_wp_locale($code)); // map code to WP locales to find the language

				// prevents deleting the last language as it would break the Lingotek dashboard
				if (1 == count($polylang->model->get_languages_list())) {
					$response = array (
						'request' => sprintf('DELETE: remove language from CMS and project (%s)', $code),
						'code' => $code,
						'success' => false,
						'message' => __('You must keep at least one language.', 'wp-lingotek')
					);
					status_header(403);
				}

				elseif (!self::has_language_content($lang)) {
					$default_category = pll_get_term(get_option('default_category'), $lang->slug);
					$polylang->model->delete_language((int) $lang->term_id);
					wp_delete_term( $default_category, 'category' ); // delete the default category after the language

					// Deletes the translation status so when re-adding a language the string groups translations won't display as current
					$lingotek_model = new Lingotek_Model();
					$strings = $lingotek_model->get_strings();
					foreach ($strings as $string) {
						$group = $lingotek_model->get_group('string', $string['context']);
						unset($group->translations[$lang->locale]);
						$group->save();
					}

					$response = array (
						'request' => sprintf('DELETE: remove language from CMS and project (%s)', $code),
						'code' => $code,
						'active' => false,
						'success' => true
					);
					status_header(204);
				}

				else {
					$response = array (
						'request' => sprintf('DELETE: remove language from CMS and project (%s)', $code),
						'code' => $code,
						'success' => false,
						'message' => __('The language can only be removed when no existing content is using it.  If you would like to remove this language from the site, then first remove any content assigned to this language.', 'wp-lingotek')
					);
					status_header(403);
				}
				break;

			case 'GET':
			default:
				$locale_code = isset($_REQUEST['code']) ? $_REQUEST['code'] : NULL;
				$response = $response + $this->get_language_details($locale_code);
				break;
		}

		wp_send_json($response);
	}

	/**
	 * Lingotek - get the details of each language
	 */
	function get_language_details($locale_requested = NULL) {
		global $polylang;
		
		$response = array();
		$available_languages = $polylang->model->get_languages_list();
		$source_total = 0;
		$target_total = 0;
		$source_totals = array();
		$target_totals = array();
		
		// If we get a parameter, only return that language. Otherwise return all languages.
		foreach ($available_languages as $lang_details) {
			$wordpress_lang_code = $lang_details->slug;
			$locale = $lang_details->locale;
			if (!is_null($locale_requested) && $locale_requested != $locale) {
				continue;
			}

			$lingotek_locale = str_replace('-', '_', $lang_details->lingotek_locale);
			$source_counts = self::get_source_counts($wordpress_lang_code);
			$target_counts = self::get_target_counts($wordpress_lang_code);
			self::standardize_and_sort($source_counts['types'], $target_counts['types']);
			$source_count = $source_counts['total']; //unset($source_counts['total']);
			$target_count = $target_counts['total'];

			$target_status = array(
				'locale' => $lingotek_locale, // Return this language code as the Lingotek language code.
				'xcode' => $wordpress_lang_code,
				'active' => 1, //TO-DO: lingotek_enabled?
				'enabled' => 1, //intval($lang_details->active), // wordpress enabled
				'source' => $source_counts,
				'target' => $target_counts
			);
			if ($locale_requested == $locale) {
				$response = $target_status;
			}
			else {
				$response[$lingotek_locale] = $target_status;
			}
			//$source_total += $source_count;
			$target_total += $target_count;
			//$source_totals = self::array_sum_values($source_totals, $source_counts['types']);
			$target_totals = self::array_sum_values($target_totals, $target_counts['types']);
		}
		if (is_null($locale_requested)) {
			$source_totals_package = self::get_unique_source_counts_by_type();
			$source_totals = $source_totals_package['types'];
			$source_total = $source_totals_package['total'];
			self::standardize_and_sort($source_totals, $target_totals);

			$response = array(
				'languages' => $response,
				'source' => array('types' => $source_totals, 'total' => $source_total),
				'target' => array('types' => $target_totals, 'total' => $target_total),
				'count' => count($available_languages),
			);
		}
		return $response;
	}

	/**
	 * Lingotek - standardize and sort helper function
	 */
	static function standardize_and_sort(&$a1, &$a2) {
		$merged_totals = array_fill_keys(array_keys($a1 + $a2), 0);
		$a1 = $a1 + $merged_totals;
		$a2 = $a2 + $merged_totals;
		ksort($a1);
		ksort($a2);
	}

	/**
	 * Lingotek - get_source_counts
	 */
	function get_source_counts($locale) {
		return self::get_counts_by_type($locale, 'sources');
	}

	/**
	 * Lingotek - get_target_counts
	 */
	function get_target_counts($locale) {
		return self::get_counts_by_type($locale, 'targets');
	}

	/**
	 * Lingotek - get_counts_by_type
	 */
	static function get_counts_by_type($locale, $condition) {
		global $polylang;

		$lgtm = new Lingotek_Model(); // FIXME not created by Lingotek as Polylang believes we are doing ajax on frontend

		foreach ($polylang->model->get_translated_post_types() as $post_type) {
			$count = $lgtm->count_posts($post_type);
			$post_type_object = get_post_type_object($post_type);
			$response['types'][$post_type_object->labels->name] = isset($count[$condition][$locale]) ? $count[$condition][$locale] : 0;
		}

		foreach ($polylang->model->get_translated_taxonomies() as $tax) {
			$count = $lgtm->count_terms($tax);
			$taxonomy = get_taxonomy($tax);
			$response['types'][$taxonomy->labels->name] = isset($count[$condition][$locale]) ? $count[$condition][$locale] : 0;
		}

		$response['total'] = array_sum($response['types']);

		return $response;
	}

	static function get_unique_source_counts_by_type(){

		global $polylang;

		$lgtm = new Lingotek_Model(); // FIXME not created by Lingotek as Polylang believes we are doing ajax on frontend

		foreach ($polylang->model->get_translated_post_types() as $post_type) {
			$count = $lgtm->count_posts($post_type);
			$post_type_object = get_post_type_object($post_type);
			$response['types'][$post_type_object->labels->name] = isset($count['total']) ? $count['total'] : 0; //only count translation sets
		}

		foreach ($polylang->model->get_translated_taxonomies() as $tax) {
			$count = $lgtm->count_terms($tax);
			$taxonomy = get_taxonomy($tax);
			$response['types'][$taxonomy->labels->name] = isset($count['total']) ? $count['total'] : 0; //only count translation sets
		}

		$response['total'] = array_sum($response['types']);

		return $response;
	}

	/**
	 * Sums the values of the arrays be there keys (PHP 4, PHP 5)
	 * array array_sum_values ( array array1 [, array array2 [, array ...]] )
	 */
	public static function array_sum_values() {
		$return = array();
		$intArgs = func_num_args();
		$arrArgs = func_get_args();
		if ($intArgs < 1) {
			trigger_error('Warning: Wrong parameter count for arraySumValues()', E_USER_WARNING);
		}

		foreach ($arrArgs as $arrItem) {
			if (!is_array($arrItem)) {
				trigger_error('Warning: Wrong parameter values for arraySumValues()', E_USER_WARNING);
			}
			foreach ($arrItem as $k => $v) {
				if (!key_exists($k, $return)) {
					$return[$k] = 0;
				}
				$return[$k] += $v;
			}
		}
		return $return;

		$sumArray = array();
		foreach ($myArray as $k => $subArray) {
			foreach ($subArray as $id => $value) {
				$sumArray[$id]+=$value;
			}
		}
		return $sumArray;
	}

	/*
	 * checks if any content exists in the language
	 *
	 * @since 0.2
	 *
	 * @param object $language
	 * @return bool false if no content in the language or only the default category
	 */
	static public function has_language_content($language) {
		// posts
		$objects = get_objects_in_term($language->term_id, 'language');
		if (!empty($objects))
			return true;

		// terms, only the default category is accepted
		$objects = get_objects_in_term($language->tl_term_id, 'term_language');
		return count($objects) > 1 || (isset($objects[0]) && $objects[0] != pll_get_term(get_option('default_category'), $language->slug));
	}
}
