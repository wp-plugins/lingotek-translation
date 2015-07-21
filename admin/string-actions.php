<?php

class Lingotek_String_actions extends Lingotek_Actions {

	/*
	 * Constructor
	 *
	 * @since 0.2
	 */
	public function __construct() {
		parent::__construct('string');

		if (isset($this->pllm->options['default_lang']) && 'automatic' == Lingotek_Model::get_profile_option('upload', 'string', $this->pllm->get_language($this->pllm->options['default_lang'])))
			add_action('updated_option', array(&$this, 'updated_option'));
	}

	/*
	 * get the language of strings sources
	 *
	 * @since 0.2
	 *
	 * @param string $name
	 * @return object
	 */
	protected function get_language($name) {
		return $this->pllm->get_language($this->pllm->options['default_lang']);;
	}


	/*
	 * displays the icon of an uploaded strings group (no link)
	 *
	 * @since 0.2
	 *
	 * @param int $id
	 */
	public static function uploaded_icon($id) {
		return self::display_icon('uploaded', '#');
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
		$args['page'] = 'wp-lingotek_manage';
		$args['noheader'] = true;
		return parent::get_action_link($args, $warning);
	}

	/*
	 * adds a row actions links
	 *
	 * @since 0.2
	 *
	 * @param string $name strings group name
	 * @return array
	 */
	public function row_actions($name) {
		return $this->_row_actions(array(), $name);
	}

	/*
	 * manages Lingotek actions
	 *
	 * @since 0.2
	 */
	public function manage_actions($action) {

		$redirect = remove_query_arg( array('action', 'action2'), wp_get_referer() );
		if (!$redirect)
			$redirect = admin_url("admin.php?page=wp-lingotek_manage&sm=strings");

		switch($action) {
			case 'bulk-lingotek-upload':
				$ids = array();

				foreach ($_REQUEST['strings'] as $id) {
					// safe upload
					if ($this->lgtm->can_upload('string', $id))
						$ids[] = $id;
				}

			case 'bulk-lingotek-request':
			case 'bulk-lingotek-download':
			case 'bulk-lingotek-status':
			case 'bulk-lingotek-delete':
				if (empty($ids)) {
					if (empty($_REQUEST['strings']))
						return;

					$ids = $_REQUEST['strings'];
				}

				check_admin_referer('bulk-lingotek-strings-translations');
				$redirect = add_query_arg($action, 1, $redirect);
				$redirect = add_query_arg('ids', implode(',', array_map('intval', $ids)), $redirect);

				break;

			case 'lingotek-upload':
				check_admin_referer('lingotek-upload');
				$this->lgtm->upload_strings($_GET['string']);
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
	 * @since 0.2
	 */
	public function ajax_upload() {
		check_ajax_referer('lingotek_progress', '_lingotek_nonce');
		$this->lgtm->upload_strings($_POST['id']);
		die();
	}

	/*
	 * automatic upload of strings when an option is updated
	 *
	 * @since 0.2
	 */
	public function updated_option() {
		foreach (Lingotek_Model::get_strings() as $id) {
			if ($this->lgtm->can_upload('string', $id['context']))
				$this->lgtm->upload_strings($id['context']);
		}
	}
}
