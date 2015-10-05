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

		// add meta box on post and page edit pages, and hook on save
		add_action('add_meta_boxes', array(&$this, 'lingotek_add_meta_boxes'));
		add_action('save_post', array(&$this, 'lingotek_save_meta_boxes'));
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
				$profile = Lingotek_Model::get_profile($post->post_type, $language, $post->ID);
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

	/*
	 * adds the lingotek profile meta box on edit pages
   *
	 * @since 0.1
   */
	public function lingotek_add_meta_boxes() {
		global $post;
		$lgtm = new Lingotek_Model();
		$group = $lgtm->get_group($post->post_type, $post->ID);

		// if it's a new page, $group will be null, so don't check if it's a source page
		if ($group) {
			$desc_array = $group->desc_array;
			$source_id = $desc_array['lingotek']['source'];
			// only display the meta box if it's a source
			if (isset($desc_array['lingotek']['source']) && $post->ID != $source_id) {
				return;
			}
		}

		add_meta_box('lingotek_post_meta_box', __('Lingotek Translation', 'wp-lingotek'), array( __CLASS__, 'lingotek_edit_meta_box_html'), 'post', 'side', 'default');
		add_meta_box('lingotek_page_meta_box', __('Lingotek Translation', 'wp-lingotek'), array( __CLASS__, 'lingotek_edit_meta_box_html'), 'page', 'side', 'default');
	}

	/*
	 * builds the html for the edit post and edit page meta boxes
	 *
	 * @since 0.1
	 */
	public static function lingotek_edit_meta_box_html() {
		wp_enqueue_script('lingotek_defaults', LINGOTEK_URL .'/js/defaults.js');

		global $post;
		$post_type = get_post_type($post->ID);
		$lgtm = new Lingotek_Model();
		$group = $lgtm->get_group('post', $post->ID);
		$profiles = Lingotek::get_profiles();
		$content_profiles = get_option('lingotek_content_type');
		$language_profiles = self::retrieve_lang_Profiles($post_type, $profiles, $content_profiles);
		$default_name = empty($content_profiles) == false ? $profiles[$content_profiles[$post->post_type]['profile']]['name'] : ($post_type == 'page' ? __('Manual', 'wp-lingotek') : __('Automatic', 'wp-lingotek'));
		$content_default_profile = array('default' => array(
			'name' => __('Content Default', 'wp-lingotek') . ' (' . $default_name . ')', // Adds in the name of the content type default profile
		));
		$language_profiles['defaults'] = array(
			'content_default' => $default_name,
			'title' => __('Content Default', 'wp-lingotek'),
		);
		$profiles = array_merge($content_default_profile, $profiles);
		$post_profile = self::get_post_profile($post->ID);
		if (isset($post_profile)) {
			$selected[$post_profile->description] = $profiles[$post_profile->description];
			unset($profiles[$post_profile->description]);
			$profiles = array_merge($selected, $profiles);
		}

		if (isset($group->source)) { // Disables selection of a different profile if content has been uploaded to Lingotek
			$args = array(
				'document_id' => $group->document_id,
				'action' => 'lingotek-delete',
				'noheader' => true,
			);
			if ($post_type == 'page') {
				$args['lingotek_redirect'] = true;
			}
			$site_id = get_current_blog_id();
			$url = $post_type == 'page' ? get_site_url($site_id, '/wp-admin/edit.php?post_type=page') : get_site_url($site_id, '/wp-admin/edit.php');
			$disassociate_url = wp_nonce_url(add_query_arg($args, $url), 'lingotek-delete');
			$remove_post = 'post=' . $post->ID;
			$disassociate_url = str_replace($remove_post, '', $disassociate_url);
			$prefs = Lingotek_Model::get_prefs();
			$confirm_message = isset($prefs['delete_document_from_tms']) === false ?  __('Are you sure you want to do this?', 'wp-lingotek') : __('Are you sure you want to do this? The document will be deleted from Lingotek TMS.', 'wp-lingotek');
			$confirm_message = sprintf(' onclick = "return confirm(\'%s\');"', $confirm_message);
			printf('<strong>%s</strong><br><br>', __('Translation Profile', 'wp-lingotek'));
			printf('<em>%s</em><br>', __('Disassociate this content to change the Translation Profile', 'wp-lingotek'));
			printf(('<a class="button button-small" href="%s" %s>%s</a><br><br>'), esc_url($disassociate_url), $confirm_message, __('Disassociate', 'wp-lingotek'));
			printf('<select disabled class="lingotek-profile-setting" name="%1$s" id="%1$s">', 'lingotek_profile_meta');
		}
		else {
			printf('<strong>%s</strong><br><br>', __('Translation Profile', 'wp-lingotek'));
			printf('<select class="lingotek-profile-setting" name="%1$s" id="%1$s">', 'lingotek_profile_meta');
		}

		foreach ($profiles as $key => $profile) {
			echo "\n\t<option value=" . esc_attr($key) . ">" . esc_attr($profile['name']) . '</option>';
		}
		echo '</select>';
		echo '<div id="lingotek-language-profiles" style="display: none;">'.json_encode($language_profiles).'</div>';
	}

	public function lingotek_save_meta_boxes() {
		if (!isset($_POST['lingotek_profile_meta'])) {
			return;
		}

		global $post;
		$profile_choice = $_POST['lingotek_profile_meta'];
		$document_id = 'lingotek_profile_' . $post->ID;
		$term = self::get_post_profile($post->ID);
		$post_language = $this->get_language($post->ID);
		$content_profiles = get_option('lingotek_content_type');

		if ($profile_choice == 'default' && !empty($term)) {
			wp_delete_term((int) $term->term_id, 'lingotek_profile');
		}
		elseif ($profile_choice != 'default') {
			if (empty($term)) {
				wp_insert_term($document_id, 'lingotek_profile', array('description' => $profile_choice));
			}
			else {
				wp_update_term((int) $term->term_id, 'lingotek_profile', array('description' => $profile_choice));
			}

			wp_set_object_terms($post->ID, $document_id, 'lingotek_profile');
		}
	}

	public static function get_post_profile($post_id) {
		if (taxonomy_exists('lingotek_profile')) {
			$terms = wp_get_object_terms($post_id, 'lingotek_profile');
			$term = array_pop($terms);
			return $term;
		}
		else {
			return 'false';
		}
	}

	public static function retrieve_lang_Profiles($post_type, $profiles, $content_profiles) {
		$language_profiles = array();

			if(isset($content_profiles[$post_type]['sources'])) {
				$sources = $content_profiles[$post_type]['sources'];
				foreach($sources as $lang_code => $profile) {
					$language_profiles[$lang_code] = $profiles[$profile]['name'];
				}
		}

		return $language_profiles;
	}
}
