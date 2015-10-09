<?php

/*
 * Base class to add row and bulk actions to posts, media and terms list
 * Bulk actions management is inspired by http://www.foxrunsoftware.net/articles/wordpress/add-custom-bulk-action/
 *
 * @since 0.2
 */
abstract class Lingotek_Actions {
	public $pllm, $lgtm; // Polylang and Lingotek models
	public $type; // *must* be defined in child class: 'post' or 'term'
	public static $actions, $icons, $confirm_message;

	/*
	 * Constructor
	 *
	 * @since 0.2
	 */
	public function __construct($type) {
		// confirm message
		self::$confirm_message = sprintf(' onclick = "return confirm(\'%s\');"', __('You are about to overwrite existing translations. Are you sure?', 'wp-lingotek'));

		// row actions
		self::$actions = array(
			'upload'   => array(
				'action'      => __('Upload to Lingotek', 'wp-lingotek'),
				'progress'    => __('Uploading...', 'wp-lingotek'),
				'description' => __('Upload this item to Lingotek TMS', 'wp-lingotek' ),
			),

			'request'  => array(
				'action'      => __('Request translations', 'wp-lingotek'),
				'progress'    => __('Requesting translations...', 'wp-lingotek'),
				'description' => __('Request translations of this item to Lingotek TMS', 'wp-lingotek' ),
			),

			'status'   => array(
				'action'      => __('Update translations status', 'wp-lingotek'),
				'progress'    => __('Updating translations status...', 'wp-lingotek'),
				'description' => __('Update translations status of this item in Lingotek TMS', 'wp-lingotek' ),
			),

			'download' => array(
				'action'      => __('Download translations', 'wp-lingotek'),
				'progress'    => __('Downloading translations...', 'wp-lingotek'),
				'description' => __('Download translations of this item from Lingotek TMS', 'wp-lingotek' ),
			),

			'delete' => array(
				'action'      => __('Disassociate translations', 'wp-lingotek'),
				'progress'    => __('Disassociating translations...', 'wp-lingotek'),
				'description' => __('Disassociate the translations of this item from Lingotek TMS', 'wp-lingotek' ),
			),
		);

		// action icons
		self::$icons = array(
			'upload' => array(
				'title' => __('Upload Now', 'wp-lingotek'),
				'icon'  => 'upload'
			),

			'importing' => array(
				'title' => __('Importing source', 'wp-lingotek'),
				'icon'  => 'clock'
			),

			'uploaded' => array(
				'title' => __('Source uploaded', 'wp-lingotek'),
				'icon'  => 'yes'
			),

			'request' => array(
				'title' => __('Request a translation', 'wp-lingotek'),
				'icon'  => 'plus'
			),

			'pending' => array(
				'title' => __('In Progress', 'wp-lingotek'),
				'icon'  => 'clock'
			),

			'ready' => array(
				'title' => __('Ready to download', 'wp-lingotek'),
				'icon'  => 'download'
			),

			'current' => array(
				'title' => __('Current', 'wp-lingotek'),
				'icon'  => 'edit'
			),

			'not-current' => array(
				'title' => __('The target translation is no longer current as the source content has been updated', 'wp-lingotek'),
				'icon'  => 'edit'
			),

			'error' => array(
				'title' => __('There was an error contacting Lingotek', 'wp-lingotek'),
				'icon'  => 'warning'
			),
		);

		$this->type = $type;
		$this->pllm = $GLOBALS['polylang']->model;
		$this->lgtm = $GLOBALS['wp_lingotek']->model;

		add_action('admin_enqueue_scripts', array(&$this, 'admin_enqueue_scripts'));

		foreach (array_keys(self::$actions) as $action)
			add_action('wp_ajax_lingotek_progress_' . $this->type . '_'. $action , array(&$this, 'ajax_' . $action));
	}

	/*
	 * generates a workbench link
	 *
	 * @since 0.1
	 *
	 * @param string $document_id
	 * @param string $locale Lingotek locale
	 * @return string workbench link
	 */
	public static function workbench_link($document_id, $locale) {
		$client_id = Lingotek_API::CLIENT_ID;
		$token_details = get_option('lingotek_token');
		$user = wp_get_current_user();
		$base_url = get_option('lingotek_base_url');

		$acting_login_id = $user->user_email; //user_nicename;

		return self::generate_workbench_link(
			$document_id,
			$locale,
			$client_id,
			$token_details['access_token'],
			$token_details['login_id'],
			$acting_login_id,
			$base_url
		);
	}

	/*
	 * generates a workbench link
	 * function provided by Matt Smith from Lingotek
	 *
	 * @since 0.1
	 *
	 * @param string $document_id
	 * @param string $locale_code
	 * @param string $client_id
	 * @param string $access_token
	 * @param string $login_id
	 * @param string $acting_login_id
	 * @param string $base_url
	 * @param int|null $expiration
	 * @return string workbench link
	 */
	public static function generate_workbench_link($document_id, $locale_code, $client_id, $access_token, $login_id, $acting_login_id = "anonymous", $base_url = "https://myaccount.lingotek.com", $expiration = NULL) {
		$expiration_default = time() + (60 * 30); // 30-minute default, otherwise use $expiration as passed in
		$expiration = is_null($expiration) ? $expiration_default : $expiration;
		$data = array(
			'document_id'     => $document_id,
			'locale_code'     => $locale_code,
			'client_id'       => $client_id,
			'login_id'        => $login_id,
			'acting_login_id' => $acting_login_id,
			'expiration'      => $expiration
		);
		$query_data = utf8_encode(http_build_query($data));
		$hmac = urlencode(base64_encode(hash_hmac('sha1', $query_data, $access_token, TRUE)));
		$workbench_url = $base_url . '/lingopoint/portal/wb.action?' . $query_data . "&hmac=" . $hmac;
		return $workbench_url;
	}

	/*
	 * outputs an action icon
	 *
	 * @since 0.2
	 *
	 * @param string $name
	 * @param string $link
	 * @param string $additional parameters to add (js, target)
	 */
	public static function display_icon($name, $link, $additional = '') {
		return sprintf('<a class="lingotek-color dashicons dashicons-%s" title="%s" href="%s"%s></a>',
			self::$icons[$name]['icon'], self::$icons[$name]['title'], esc_url($link), $additional);
	}

	/*
	 * outputs an API error icon
	 *
	 * @since 1.2
	 *
	 * @param string $name
	 * @param string $additional parameters to add (js, target)
	 */
	public static function display_error_icon($name, $api_error, $additional = '') {
		return sprintf('<span class="lingotek-error dashicons dashicons-%s" title="%s"></span>',
			self::$icons[$name]['icon'], self::$icons[$name]['title'] . "\n" . $api_error, $additional);
	}

	/*
	 * outputs an upload icon
	 *
	 * @since 0.2
	 *
	 * @param int|string $object_id
	 * @param bool $warning
	 */
	public function upload_icon($object_id, $confirm = false) {
		$args = array($this->type => $object_id, 'action' => 'lingotek-upload', 'noheader' => true);
		$link = wp_nonce_url(defined('DOING_AJAX') && DOING_AJAX ? add_query_arg($args, wp_get_referer()) : add_query_arg($args), 'lingotek-upload');
		return self::display_icon('upload', $link, $confirm ? self::$confirm_message : '');
	}

	/*
	 * outputs an importing icon
	 *
	 * @since 0.2
	 *
	 * @param object $document
	 */
	public static function importing_icon($document) {
		$args = array('document_id' => $document->document_id, 'action' => 'lingotek-status', 'noheader' => true);
		$link = wp_nonce_url(defined('DOING_AJAX') && DOING_AJAX ? add_query_arg($args, wp_get_referer()) : add_query_arg($args), 'lingotek-status');
		return self::display_icon('importing', $link);
	}

	/*
	 * outputs icons for translations
	 *
	 * @since 0.2
	 *
	 * @param object $document
	 * @param object $language
	 */
	public static function translation_icon($document, $language) {
		if (isset($document->translations[$language->locale])) {
			if ('ready' == $document->translations[$language->locale]) {
				$link = wp_nonce_url(add_query_arg(array('document_id' => $document->document_id, 'locale' => $language->locale, 'action' => 'lingotek-download', 'noheader' => true)), 'lingotek-download');
				return self::display_icon($document->translations[$language->locale], $link);
			}
			else if ('not-current' == $document->translations[$language->locale]) {
				return  '<div class="lingotek-color dashicons dashicons-no"></div>';
			}
			else {
				$link = self::workbench_link($document->document_id, $language->lingotek_locale);
				return self::display_icon($document->translations[$language->locale], $link, ' target="_blank"');
			}

		}
		else {
			$link = wp_nonce_url(add_query_arg(array('document_id' => $document->document_id, 'locale' => $language->locale, 'action' => 'lingotek-request', 'noheader' => true)), 'lingotek-request');
			return self::display_icon('request', $link);
		}
	}

	/*
	 * creates an html action link
	 *
	 * @since 0.2
	 *
	 * @param array $args parameters to add to the link
	 * @param bool $warning whether to display an alert or not, optional, defaults to false
	 * @return string
	 */
	protected function get_action_link($args, $warning = false) {
		$action = $args['action'];
		$args['action'] = 'lingotek-' . $action;
		$args['noheader'] = true;

		return sprintf(
			'<a class="lingotek-color" title="%s" href="%s"%s>%s</a>',
			self::$actions[$action]['description'],
			wp_nonce_url(defined('DOING_AJAX') && DOING_AJAX ? add_query_arg($args, wp_get_referer()) : add_query_arg($args), 'lingotek-' .$action),
			empty($warning) ? '' : self::$confirm_message,
			self::$actions[$action]['action']
		);
	}

	/*
	 * adds a row action link
	 *
	 * @since 0.2
	 *
	 * @param array $actions list of action links
	 * @param $id object id
	 * @return array
	 */
	protected function _row_actions($actions, $id) {
		// first check that a language is associated to this object
		if (!$this->get_language($id))
			return $actions;

		$document = $this->lgtm->get_group($this->type, $id);
		if ($this->type != 'string' && isset($document->desc_array['lingotek']['source'])) {
			$id = $document->desc_array['lingotek']['source'];
		}

		if ($this->lgtm->can_upload($this->type, $id) || (isset($document->source) && 'string' != $this->type && $this->lgtm->can_upload($this->type, $document->source))) {
			if ($document) {
				$desc_array = $document->desc_array;
				unset($desc_array['lingotek']);
				if (count($desc_array) >= 2) {
					$actions['lingotek-upload'] = $this->get_action_link(array($this->type => $id, 'action' => 'upload'), true);
				}
				else {
					$actions['lingotek-upload'] = $this->get_action_link(array($this->type => $id, 'action' => 'upload'));
				}
			}
			else {
				$actions['lingotek-upload'] = $this->get_action_link(array($this->type => $id, 'action' => 'upload'));
			}
		}

		elseif (isset($document->translations)) {
			// translations to download ?
			if ($document->has_translation_status('ready'))
				$actions['lingotek-download'] = $this->get_action_link(array('document_id' => $document->document_id, 'action' => 'download'));

			// need to request translations ?
			$language = $this->get_language($document->source);
			$all_locales = array_flip($this->pllm->get_languages_list(array('fields' => 'locale')));
			if (!empty($language)) // in case a language has been deleted
				unset($all_locales[$language->locale]);
			$untranslated = array_diff_key($all_locales, $document->translations);

			// remove disabled target language from untranslated languages list
			foreach ($untranslated as $k => $v) {
				if ($this->type == 'term') {
					if ($document->is_disabled_target($language, $this->pllm->get_language($k)))
					unset($untranslated[$k]);
				}
				else {
					if ($document->is_disabled_target($language, $this->pllm->get_language($k)))
					unset($untranslated[$k]);
				}
			}

			if ('current' == $document->status && !empty($untranslated))
				$actions['lingotek-request'] = $this->get_action_link(array('document_id' => $document->document_id, 'action' => 'request'));

			// offers to update translations status
			if ('importing' == $document->status || $document->has_translation_status('pending'))
				$actions['lingotek-status'] = $this->get_action_link(array('document_id' => $document->document_id, 'action' => 'status'));
		}

		elseif (empty($document->source)) {
			$actions['lingotek-upload'] = $this->get_action_link(array($this->type => $id, 'action' => 'upload'), true);
		}

		// offers to disassociate translations
		if (isset($document->source))
			$actions['lingotek-delete'] = $this->get_action_link(array('document_id' => $document->document_id, 'action' => 'delete'));

		return $actions;
	}

	/*
	 * adds actions to bulk dropdown list table using a javascript hack
	 * as the existing filter does not allow to *add* actions
	 * also displays the progress dialog placeholder
	 *
	 * @since 0.2
	 */
	protected function _add_bulk_actions() {
		$js = '';

		foreach (self::$actions as $action => $strings) {
			foreach (array('', '2') as $i)
				$js .= sprintf('jQuery("<option>").val("bulk-lingotek-%s").text("%s").appendTo("select[name=\'action%s\']");', $action, $strings['action'], $i);

			if (!empty($_GET['bulk-lingotek-' . $action]))
				$text = $strings['progress'];
		}

		echo '<script type="text/javascript">jQuery(document).ready(function() {' . $js . '});</script>';

		if (!empty($text))
			printf('<div id="lingotek-progressdialog" title="%s"><div id="lingotek-progressbar"></div></div>', $text);
	}

	/*
	 * outputs javascript data for progress.js
	 *
	 * @since 0.1
	 */
	public function admin_enqueue_scripts() {
		foreach (array_keys(self::$actions) as $action) {
			if (!empty($_GET['bulk-lingotek-' . $action])) {
				wp_localize_script('lingotek_progress', 'lingotek_data', array(
					'action'   => empty($_GET['page']) ? (empty($_GET['taxonomy']) ? 'post_' . $action : 'term_' . $action) : 'string_' . $action,
					'taxonomy' => empty($_GET['taxonomy']) || !taxonomy_exists($_GET['taxonomy']) ? '' : $_GET['taxonomy'],
					'sendback' => remove_query_arg( array('bulk-lingotek-' . $action, 'ids', 'lingotek_warning'), wp_get_referer() ),
					'ids'      => array_map('intval', explode(',', $_GET['ids'])),
					'warning'  => empty($_GET['lingotek_warning']) ? '' : __('You are about to overwrite existing translations. Are you sure?', 'wp-lingotek'),
					'nonce'    => wp_create_nonce('lingotek_progress')
				));
				return;
			}
		}
	}

	/*
	 * manages actions driven by dcoument_id
	 *
	 * @since 0.2
	 *
	 * @param string $action action name
	 * @return bool true if the action was managed, false otherwise
	 */
	protected function _manage_actions($action) {
		if (!empty($_GET['document_id']))
			$document = $this->lgtm->get_group_by_id($_GET['document_id']);

		switch($action) {
			case 'lingotek-status':
				check_admin_referer('lingotek-status');
				$document->source_status();
				$document->translations_status();
				break;

			case 'lingotek-request':
				check_admin_referer('lingotek-request');
				isset($_GET['locale']) ? $document->request_translation($_GET['locale']) : $document->request_translations();
				break;

			case 'lingotek-download':
				check_admin_referer('lingotek-download');
				isset($_GET['locale']) ? $document->create_translation($_GET['locale']) : $document->create_translations();
				break;

			case 'lingotek-delete':
				check_admin_referer('lingotek-delete');
				$document->disassociate();
				if (isset($_GET['lingotek_redirect']) && $_GET['lingotek_redirect'] == true) {
					$site_id = get_current_blog_id();
					wp_redirect(get_site_url($site_id, '/wp-admin/edit.php?post_type=page'));
					exit();
				}
				break;

			default:
				return false;
		}

		return true;
	}

	/*
	 * ajax response to download translations and showing progress
	 *
	 * @since 0.1
	 */
	public function ajax_download() {
		check_ajax_referer('lingotek_progress', '_lingotek_nonce');

		if ($document = $this->lgtm->get_group($this->type, $_POST['id'])) {
			foreach ($document->translations as $locale => $status) {
				if ('pending' == $status || 'ready' == $status) {
					$document->create_translation($locale);
				}
			}
		}
		die();
	}

	/*
	 * ajax response to request translations and showing progress
	 *
	 * @since 0.2
	 */
	public function ajax_request() {
		check_ajax_referer('lingotek_progress', '_lingotek_nonce');
		if ($document = $this->lgtm->get_group($this->type, $_POST['id'])) {
			$document->request_translations();
		}
		die();
	}

	/*
	 * ajax response to check translation status and showing progress
	 *
	 * @since 0.1
	 */
	public function ajax_status() {
		check_ajax_referer('lingotek_progress', '_lingotek_nonce');
		if ($document = $this->lgtm->get_group($this->type, $_POST['id'])) {
			$document->source_status();
			$document->translations_status();
		}
		die();
	}

	/*
	 * ajax response disassociate translations and showing progress
	 *
	 * @since 0.2
	 */
	public function ajax_delete() {
		check_ajax_referer('lingotek_progress', '_lingotek_nonce');
		if ($document = $this->lgtm->get_group($this->type, $_POST['id'])) {
			$document->disassociate();
		}
		die();
	}

	/*
	 * collects and returns all API errors
	 *
	 * @since 1.1
	 *
	 * @param string errors
	 */
	public static function retrieve_api_error($errors) {
		$api_error = "\n";

		foreach($errors as $error => $error_message) {
			if (is_array($error_message)) {
				if (!empty($error_message)) {
					foreach ($error_message as $locale => $message) {
						$api_error = $api_error . $message . "\n";
					}
				}
			}
			else {
				$api_error = $api_error . $error_message . "\n";
			}
		}

		return $api_error;
	}
}