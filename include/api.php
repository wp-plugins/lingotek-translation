<?php

require_once('http.php');

/*
 * manages communication with Lingotek TMS
 * uses Lingotek API V5
 *
 * @since 0.1
 */
class Lingotek_API extends Lingotek_HTTP {
	protected $base_url;
	protected $api_url;
	protected $client_id;

	const PRODUCTION_URL = "https://myaccount.lingotek.com";
	const SANDBOX_URL = "https://cms.lingotek.com";
	const CLIENT_ID = "780966c9-f9c8-4691-96e2-c0aaf47f62ff";// Lingotek App ID

	/*
	 * constructor
	 *
	 * @since 0.1
	 */
	public function __construct() {
		$base_url = get_option('lingotek_base_url');
		$this->base_url = $base_url ? $base_url : self::PRODUCTION_URL;
		$this->api_url = $this->base_url.'/api';
		$this->client_id = self::CLIENT_ID;
		$token_details = get_option('lingotek_token');
		$this->headers['Authorization'] = 'bearer ' . $token_details['access_token'];
		$this->defaults = get_option('lingotek_defaults');
	}

	public function get_token_details($access_token) {
		$url = $this->base_url . "/auth/oauth2/access_token_info?access_token=" . $access_token;
		Lingotek::log("GET " . $url . " (" . __METHOD__ . ")");
		$response = wp_remote_get($url);
		$response_code = wp_remote_retrieve_response_code($response);

		if ($response_code == 200) {
			$response_body = json_decode(wp_remote_retrieve_body($response));
			$token_details = $response_body;
		}
		else {
			$token_details = FALSE;
		}
		return $token_details;
	}

	public function get_api_url() {
		return $this->api_url;
	}

	/*
	 * updates the projet callback
	 *
	 * @since 0.2
	 *
	 * @param string $project_id
	 */
	public function update_callback_url($project_id) {
		$args = array('callback_url' => add_query_arg('lingotek', 1, site_url()));
		$response = $this->patch($this->api_url . '/project/' . $project_id, $args);
		return !is_wp_error($response) && 204 == wp_remote_retrieve_response_code($response);
	}

        /*
	 * creates a new project
	 *
	 * @since 0.2
	 *
	 * @param string $title
	 */
        public function create_project($title, $community_id) {
                $args = array(
                    'title' => $title,
                    'community_id' => $community_id,
                    'workflow_id' => $this->defaults['workflow_id'],
                    'callback_url' => add_query_arg('lingotek', 1, site_url()),
                );

                $response = $this->post($this->api_url . '/project', $args);
                if(!is_wp_error($response) && 201 == wp_remote_retrieve_response_code($response)) {
                    $new_id = json_decode(wp_remote_retrieve_body($response));
                    return $new_id->properties->id;
                }
                else {
                    return false;
                }
        }

	/*
	 * uploads a document
	 *
	 * @since 0.1
	 *
	 * @param array $args expects array with title, content and locale_code
	 * @returns bool|string document_id, false if something got wrong
	 */
	public function upload_document($args) {
		$args = wp_parse_args($args, array('format' => 'JSON', 'project_id' => $this->defaults['project_id'], 'workflow_id' => $this->defaults['workflow_id']));

		$this->format_as_multipart($args);
		$response = $this->post($this->api_url . '/document', $args);

		if (!is_wp_error($response) && 202 == wp_remote_retrieve_response_code($response)) {
			$b = json_decode(wp_remote_retrieve_body($response));
			return $b->properties->id;
		}
		return false;
	}

	/*
	 * modifies a document
	 *
	 * @since 0.1
	 *
	 * @param string $id document id
	 * @param array $args expects array with content
	 * @return bool false if something got wrong
	 */
	public function patch_document($id, $args) {
		$args = wp_parse_args($args, array('format' => 'JSON'));
		$this->format_as_multipart($args);
		$response = $this->patch($this->api_url . '/document/' . $id, $args);
		return !is_wp_error($response) && 202 == wp_remote_retrieve_response_code($response);
	}

	/*
	 * deletes a document
	 *
	 * @since 0.1
	 *
	 * @param string $id document id
	 */
	public function delete_document($id) {
		$response = $this->delete($this->api_url . '/document/' . $id);
		return !is_wp_error($response) && 204 == wp_remote_retrieve_response_code($response);
	}

	/*
	 * get documents ids
	 *
	 * @since 0.1
	 */
	public function get_documents($args = array()) {
		$response = $this->get(add_query_arg($args, $this->api_url . '/document'));
		$ids = array();

		if (!is_wp_error($response) && 200 == wp_remote_retrieve_response_code($response)) {
			$documents = json_decode(wp_remote_retrieve_body($response));
			foreach ($documents->entities as $doc) {
				$ids[] = $doc->properties->id;
			}
		}

		return $ids;
	}

	/*
	 * check if a document is existing
	 *
	 * @since 0.1
	 *
	 * @param string $id document id
	 * @return bool
	 */
	public function document_exists($id) {
		$response = $this->get($this->api_url . '/document/' . $id);
		return !is_wp_error($response) && 200 == wp_remote_retrieve_response_code($response);
	}

	/*
	 * check translations status of a document
	 *
	 * @since 0.1
	 *
	 * @param string $id document id
	 * @return array with locale as key and status as value
	 */
	public function get_translations_status($id) {
		$response = $this->get($this->api_url . '/document/' . $id . '/translation');
		if (!is_wp_error($response) && 200 == wp_remote_retrieve_response_code($response)) {
			$b = json_decode(wp_remote_retrieve_body($response));
			foreach ($b->entities as $e) {
				$translations[$e->properties->locale_code] = $e->properties->percent_complete;
			}
		}

		return empty($translations) ? array() : $translations;
	}

	/*
	 * requests a new translation of a document
	 *
	 * @since 0.1
	 *
	 * @param string $id document id
	 * @param string $locale Lingotek locale
	 * @param array $args optional arguments (only workflow_id at the moment)
	 * @return bool true if the request succeeded
	 */
	public function request_translation($id, $locale, $args = array()) {
		$args = wp_parse_args($args, array('workflow_id' => $this->defaults['workflow_id']));
		$args = array_merge(array('locale_code' => $locale), $args);
		$response = $this->post($this->api_url . '/document/' . $id . '/translation', $args);
		return !is_wp_error($response) && 201 == wp_remote_retrieve_response_code($response);
	}

	/*
	 * get a translation
	 *
	 * @since 0.1
	 *
	 * @param string $id document id
	 * @param string $locale Lingotek locale
	 * @return string|bool the translation, false if there is none
	 */
	public function get_translation($id, $locale) {
		$response = $this->get(add_query_arg(array('locale_code' => $locale, 'auto_format' => 'true') , $this->api_url . '/document/' . $id . '/content'));
		return !is_wp_error($response) && 200 == wp_remote_retrieve_response_code($response) ? wp_remote_retrieve_body($response) : false;
	}

	/*
	 * deletes a translation
	 *
	 * @since 0.1
	 *
	 * @param string $id document id
	 * @param string $locale Lingotek locale
	 */
	public function delete_translation($id, $locale) {
		$response = $this->delete($this->api_url . '/document/' . $id . '/translation/' . $locale);
		// FIXME send a response
	}

	/*
	 * get connect account url
	 *
	 * @param string $redirect_uri the location where to redirect to after account has been connected
	 * @return string the complete url for the connect account link
	 */
	public function get_connect_url($redirect_uri, $env = NULL) {
		$base_url = $this->base_url;
		$client_id = $this->client_id;
		if(!is_null($env)){
			$base_url = (strcasecmp($env,'SANDBOX') == 0) ? self::SANDBOX_URL : self::PRODUCTION_URL;
		}
		return $base_url . "/auth/authorize.html?client_id=" . $client_id . "&redirect_uri=" . urlencode($redirect_uri) . "&response_type=token";
	}

	public function get_new_url($redirect_uri) {
		$base_url = self::PRODUCTION_URL;
		$client_id = $this->client_id;
		return $base_url . "/lingopoint/portal/requestAccount.action?client_id=" . $client_id . "&app=" . urlencode($redirect_uri) . "&response_type=token";

	}

	public function get_communities() {
		$response = $this->get(add_query_arg(array('limit' => 100), $this->api_url . '/community'));
		return !is_wp_error($response) && 200 == wp_remote_retrieve_response_code($response) ? json_decode(wp_remote_retrieve_body($response)) : false;
	}

	public function get_projects($community_id) {
		$response = $this->get(add_query_arg(array('community_id' => $community_id, 'limit' => 100), $this->api_url . '/project'));
		if (wp_remote_retrieve_response_code($response) == 204) {
			return 'empty';
		}
		return !is_wp_error($response) && 200 == wp_remote_retrieve_response_code($response) ? json_decode(wp_remote_retrieve_body($response)) : false;
	}

	public function get_vaults($community_id) {
		$response = $this->get(add_query_arg(array('community_id' => $community_id, 'limit' => 100), $this->api_url . '/vault'));
		return !is_wp_error($response) && 200 == wp_remote_retrieve_response_code($response) ? json_decode(wp_remote_retrieve_body($response)) : false;
	}

	public function get_workflows($community_id) {
		$response = $this->get(add_query_arg(array('community_id' => $community_id, 'limit' => 100), $this->api_url . '/workflow'));
		return !is_wp_error($response) && 200 == wp_remote_retrieve_response_code($response) ? json_decode(wp_remote_retrieve_body($response)) : false;
	}

	public function get_filters() {
		$response = $this->get(add_query_arg(array('limit' => 100), $this->api_url . '/filter'));
		return !is_wp_error($response) && 200 == wp_remote_retrieve_response_code($response) ? json_decode(wp_remote_retrieve_body($response)) : false;
	}

	public function upload_filter($name, $type, $content) {
		$args = array('name' => $name, 'type' => $type, 'content' => $content);
		$response = $this->post($this->api_url . '/filter', $args);
	}
}
