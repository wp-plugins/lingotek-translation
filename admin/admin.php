<?php

class Lingotek_Admin {
	private $ajax_dashboard_language_endpoint = "lingotek_language";
	/*
	 * setups filters and action needed on all admin pages and on plugins page
	 *
	 * @since 0.1
	 */
	public function __construct() {
		$plugin = Lingotek::get_instance();
		$this->plugin_slug = $plugin->get_plugin_slug();
		$this->dashboard = new Lingotek_Dashboard($plugin);

		$this->pllm = $GLOBALS['polylang']->model;

		add_action('admin_enqueue_scripts', array(&$this, 'admin_enqueue_scripts'));

		// adds a 'settings' link in the plugins table
		add_filter('plugin_action_links_' . LINGOTEK_BASENAME, array(&$this, 'plugin_action_links'));

		// adds the link to the languages panel in the wordpress admin menu
		add_action('admin_menu', array(&$this, 'add_menus'));

		add_action('load-translation_page_wp-lingotek_manage',  array(&$this, 'load_manage_page'));
		add_filter('set-screen-option', array($this, 'set_screen_option'), 10, 3);

    add_action('wp_ajax_'.$this->ajax_dashboard_language_endpoint, array(&$this->dashboard, 'ajax_language_dashboard'));
    add_action('wp_ajax_get_current_status', array($this,'ajax_get_current_status'));
		//Network admin menu
		add_action('network_admin_menu', array($this, 'add_network_admin_menu'));
	}
  public function ajax_get_current_status(){
    $lgtm = &$GLOBALS['wp_lingotek']->model;
    $pllm = $GLOBALS['polylang']->model;
    $languages = pll_languages_list(array('fields' => 'locale'));
    $object_ids = $_POST['check_ids'];
    if($object_ids === null){
      return;
    }
    $terms = isset($_POST['terms_translations']);

    //The main array consists of
    //ids and nonces. Each id has a source language, languages with statuses, and a workbench link
    $content_metadata = array();
    foreach($object_ids as $object_id) {
      $id = $object_id;
      $type = $terms ? 'term' : 'post';
		if (isset($_POST['taxonomy'])) {
			$taxonomy = $_POST['taxonomy'];
			if (strpos($_POST['taxonomy'], '&')) {
				$taxonomy = strstr($_POST['taxonomy'], '&', true);
			}
		}
		else {
			$taxonomy = get_post_type($id);
		}
        $content_metadata[$id] = array(
          'existing_trans' => false,
          'source' => false,
          'doc_id' => null,
          'source_id' => null,
          'source_status' => null,
        );

		$document = $lgtm->get_group($type, $object_id);
		if($document && !isset($document->source) && count($document->desc_array) >= 3) {
			$content_metadata[$id]['existing_trans'] = true;
		}
		if ($document && isset($document->source) && isset($document->document_id) && isset($document->status) && isset($document->translations)) {
	        if($document->source !== (int) $object_id){
	          $document = $lgtm->get_group($type, $document->source);
	        }
	        $source_id = $document->source !== null ? $document->source : $object_id;
	        $source_language = $terms ? pll_get_term_language($document->source, 'locale')
	          : pll_get_post_language($document->source, 'locale');
	        $existing_translations = $pllm->get_translations($type, $source_id);

	        if(count($existing_translations) > 1){
	          $content_metadata[$id]['existing_trans'] = true;
	        }
	        $content_metadata[$id]['source'] = $source_language;
	        $content_metadata[$id]['doc_id'] = $document->document_id;
	        $content_metadata[$id]['source_id'] = $document->source;
	        $content_metadata[$id]['source_status'] = $document->status;
	        $target_status = $document->status == 'edited' || $document->status == null ? 'edited' : 'current';
	        $content_metadata[$id][$source_language]['status'] = $document->source == $object_id ? $document->status : $target_status;

	        if(is_array($document->translations)){
	          foreach($document->translations as $locale => $translation_status) {
	            $content_metadata[$id][$locale]['status'] = $translation_status;
	            $workbench_link = Lingotek_Actions::workbench_link($document->document_id, $locale);
	            $content_metadata[$id][$locale]['workbench_link'] = $workbench_link;
	          }
	        }

	        //fills in missing languages, makes life easier for the updater
		    foreach ($languages as $language) {
				foreach ($content_metadata as $group => $status) {
					$language_obj = $pllm->get_language($source_language);
					$target_lang_obj = $pllm->get_language($language);
					$profile = Lingotek_Model::get_profile($taxonomy, $language_obj, $group);
					if ($profile['profile'] != 'disabled' && $status['source'] != false) {
						if (!isset($status[$language])) {
							$content_metadata[$group][$language]['status'] = "none";
							if ($document->is_disabled_target($pllm->get_language($source_language), $pllm->get_language($language)) || (isset($document->desc_array[$target_lang_obj->slug]) && !isset($document->source))) {
								$content_metadata[$group][$language]['status'] = 'disabled';
							}
						}
					}
				}
			}
		}

		$language = $type == 'post' ? pll_get_post_language($id) : pll_get_term_language($id);
		$language = $pllm->get_language($language);
		if ($language) {
		  $profile = Lingotek_Model::get_profile($taxonomy, $language, $id);
		  if ($profile['profile'] == 'disabled' && $content_metadata[$id]['source'] == false) {
		    $content_metadata[$id]['source'] = 'disabled';
		  }
		}
    }

    //get the nonces associated with the different actions
    $content_metadata['request_nonce'] = $this->lingotek_get_matching_nonce('lingotek-request');
    $content_metadata['download_nonce'] = $this->lingotek_get_matching_nonce('lingotek-download');
    $content_metadata['upload_nonce'] = $this->lingotek_get_matching_nonce('lingotek-upload');
    $content_metadata['status_nonce'] = $this->lingotek_get_matching_nonce('lingotek-status');

    wp_send_json($content_metadata);
  }

  public function lingotek_get_matching_nonce($action){
    $upload_link = wp_nonce_url(add_query_arg(array(
              'action' => $action)),
              $action);
    $nonce_begin = strpos($upload_link, 'wpnonce=') + 8;
    $nonce = substr($upload_link,$nonce_begin);
    return $nonce;
  }

  public function lingotek_get_placeholders($items){
    foreach($items as $item){
      $placeholders .= '%s,';
    }
    $placeholders = rtrim($placeholders, ',');
    return $placeholders;
  }

	public function get_dashboard_endpoint(){
		return site_url("wp-admin/admin-ajax.php?action=".$this->ajax_dashboard_language_endpoint);
	}

	/*
	 * setup js scripts & css styles (only on the relevant pages)
	 *
	 * @since 0.1
	 */
	public function admin_enqueue_scripts() {
		$screen = get_current_screen();

		// FIXME no minified file for now
		$suffix = '';
		//		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

		// for each script:
		// 0 => the pages on which to load the script
		// 1 => the scripts it needs to work
		// 2 => 1 if loaded in footer
		// FIXME: check if I can load more scripts in footer
		$scripts = array(
			'progress'	=> array(array('edit', 'upload', 'edit-tags', 'translation_page_wp-lingotek_manage', 'translation_page_wp-lingotek_settings'), array('jquery-ui-progressbar', 'jquery-ui-dialog', 'wp-ajax-response'), 1),
			'updater'	=> array(array('edit', 'upload', 'edit-tags'), array('jquery-ui-progressbar', 'jquery-ui-dialog', 'wp-ajax-response'), 1),
		);

		$styles = array(
			'admin'	=> array(array('edit', 'upload', 'edit-tags', 'translation_page_wp-lingotek_manage', 'translation_page_wp-lingotek_settings'), array('wp-jquery-ui-dialog')),
		);

		foreach ($scripts as $script => $v)
			if (in_array($screen->base, $v[0]))
				wp_enqueue_script('lingotek_'.$script, LINGOTEK_URL .'/js/'.$script.$suffix.'.js', $v[1], LINGOTEK_VERSION, $v[2]);

		foreach ($styles as $style => $v)
			if (in_array($screen->base, $v[0]))
				wp_enqueue_style('lingotek_'.$style, LINGOTEK_URL .'/css/'.$style.$suffix.'.css', $v[1], LINGOTEK_VERSION);
	}

	/*
	 * adds a 'settings' link in the plugins table
	 *
	 * @since 0.1
	 *
	 * @param array $links list of links associated to the plugin
	 * @return array modified list of links
	 */
	public function plugin_action_links($links) {
		array_unshift($links, '<a href="admin.php?page=wp-lingotek">' . __('Settings', 'wp-lingotek') . '</a>');
		return $links;
	}

	/*
	 * adds the links to the Lingotek panels in the wordpress admin menu
	 *
	 * @since 0.0.1
	 */
	public function add_menus() {
		add_menu_page(
			$title = __('Translation', 'wp-lingotek'),
			$title,
			'manage_options',
			$this->plugin_slug,
			array($this, 'display_dashboard_page'), 'dashicons-translation'
		);

		add_submenu_page($this->plugin_slug, __('Translation Dashboard', 'wp-lingotek'), __('Dashboard', 'wp-lingotek'), 'manage_options', $this->plugin_slug, array($this, 'display_dashboard_page'));
		add_submenu_page($this->plugin_slug, __('Translation Management', 'wp-lingotek'), __('Manage', 'wp-lingotek'), 'manage_options', $this->plugin_slug . '_manage', array($this, 'display_manage_page'));
		add_submenu_page($this->plugin_slug, __('Translation Settings', 'wp-lingotek'), __('Settings', 'wp-lingotek'), 'manage_options', $this->plugin_slug . '_settings', array($this, 'display_settings_page'));
		add_submenu_page($this->plugin_slug, __('Lingotek Tutorial', 'wp-lingotek'), __('Tutorial', 'wp-lingotek'), 'manage_options', $this->plugin_slug . '_tutorial', array($this, 'display_tutorial_page'));
	}

	/*
	 * displays the settings page
	 *
	 * @since 0.0.1
	 */
	public function display_settings_page() {

		// disconnect Lingotek account
		if (array_key_exists('delete_access_token', $_GET) && $_GET['delete_access_token']) {
			delete_option('lingotek_token');
			delete_option('lingotek_community');
			delete_option('lingotek_defaults');
		}

		// connect Lingotek account
		if (array_key_exists('access_token', $_GET)) {
			// set and get token details
			$client = new Lingotek_API();
			$token_details = $client->get_token_details($_GET['access_token']);
			if ($token_details && strlen($token_details->login_id)) {
				update_option('lingotek_token', array('access_token' => $_GET['access_token'], 'login_id' => $token_details->login_id));
				add_settings_error( 'lingotek_token', 'account-connection', __('Your Lingotek account has been successfully connected.', 'wp-lingotek'), 'updated' );
			}
			else {
				add_settings_error('lingotek_token', 'account-connection', __('Your Lingotek account was not connected.	The Access Token received was invalid.', 'wp-lingotek'), 'error');
			}
		}

		// set page key primarily used for form submissions
		$page_key = $this->plugin_slug.'_settings';
		if(isset($_GET['sm'])){
			$page_key .= '&sm='.$_GET['sm'];
		}

		// set community
		if (!empty($_POST) && key_exists('lingotek_community', $_POST) && strlen($_POST['lingotek_community'])) {
			check_admin_referer($page_key, '_wpnonce_' . $page_key);
			update_option('lingotek_community', $_POST['lingotek_community']);
			add_settings_error('lingotek_community', 'update', __('Your community has been successfully saved.', 'wp-lingotek'), 'updated');
			$this->set_community_resources($_POST['lingotek_community']);
		}
		$community_id = get_option('lingotek_community');
		if (!$community_id) {
			add_settings_error('lingotek_community', 'error', __('Select and save the community that you would like to use.', 'wp-lingotek'), 'error');
		}

		$token_details = self::has_token_details();
		$redirect_url = admin_url('admin.php?page=' . $this->plugin_slug . '_settings&sm=account');

		if($token_details){
			$access_token = $token_details['access_token'];
			$login_id = $token_details['login_id'];
			$base_url = get_option('lingotek_base_url');
			include(LINGOTEK_ADMIN_INC . '/settings.php');
		}
		else {
			$connect_url = "";
			// connect cloak redirect
			if(isset($_GET['connect'])){
				// set sandbox or production (after button clicked)
				if(strcasecmp($_GET['connect'],'sandbox')==0) {
					update_option('lingotek_base_url', Lingotek_API::SANDBOX_URL);
				}
				else {
					update_option('lingotek_base_url', Lingotek_API::PRODUCTION_URL);
				}
				$client = new Lingotek_API();
				echo '<div class="wrap"><p class="description">'.__("Redirecting to Lingotek to connect your account...",'wp-lingotek').'</p></div>';

				$connect_url = (strcasecmp($_GET['connect'],'new')==0) ? $client->get_new_url($redirect_url) : $client->get_connect_url($redirect_url);
			}
			$connect_account_cloak_url_new = admin_url('admin.php?page=' . $this->plugin_slug . '_settings&connect=new');
			$connect_account_cloak_url_test = admin_url('admin.php?page=' . $this->plugin_slug . '_settings&connect=sandbox');
			$connect_account_cloak_url_prod = admin_url('admin.php?page=' . $this->plugin_slug . '_settings&connect=production');
			include(LINGOTEK_ADMIN_INC . '/settings/connect-account.php');
		}
	}

	/*
	 * get possible settings for defaults or translation profiles
	 *
	 * @since 0.2
	 *
	 * @return array
	 */
	public function get_profiles_settings($defaults = false) {
		$resources = get_option('lingotek_community_resources');
		$options = array(
			'manual' => __('Manual', 'wp-lingotek'),
			'automatic' => __('Automatic', 'wp-lingotek')
		);

		return array(
			'upload' => array(
				'label'       => __('Upload content', 'wp-lingotek'),
				'options'     => $options,
				'description' => __('How should new and modified content be uploaded to Lingotek?', 'wp-lingotek')
			),
			'download' => array(
				'label'       => __('Download translations', 'wp-lingotek'),
				'options'     => $options,
				'description' => __('How should completed translations be downloaded to WordPress?', 'wp-lingotek')
			),
			'project_id' => array(
				'label'	      => $defaults ? __('Default Project', 'wp-lingotek') : __('Project', 'wp-lingotek'),
				'options'     => $resources['projects'],
				'description' => __('Changes will affect new entities only', 'wp-lingotek')
			),
			'workflow_id' => array(
				'label'	      => $defaults ? __('Default Workflow', 'wp-lingotek') : __('Workflow', 'wp-lingotek'),
				'options'     => $resources['workflows'],
				'description' => __('Changes will affect new entities only', 'wp-lingotek')
			),
			'primary_filter_id' => array(
				'label'       => $defaults ? __('Primary Filter', 'wp-lingotek') : __('Primary Filter', 'wp-lingotek'),
				'options'     => $resources['filters'],
				'description' => __('Changes will affect new entities only', 'wp-lingotek')
			),
			'secondary_filter_id' => array(
				'label'       => $defaults ? __('Secondary Filter', 'wp-lingotek') : __('Secondary Filter', 'wp-lingotek'),
				'options'     => $resources['filters'],
				'description' => __('Changes will affect new entities only', 'wp-lingotek')
			),
		);
	}

	/*
	 * get usage for all profiles
	 *
	 * @since 0.2
	 *
	 * @param arry $profiles
	 * @return array profiles with added usage column
	 */
	public function get_profiles_usage($profiles) {
		$content_types = get_option('lingotek_content_type');

		// initialize usage column
		foreach ($profiles as $key => $profile)
			$profiles[$key]['usage'] = 0;

		// fill usage column
		foreach (array_merge($this->pllm->get_translated_post_types(), $this->pllm->get_translated_taxonomies(), array('string')) as $type) {
			if (isset($content_types[$type]['profile']))
				$profiles[$content_types[$type]['profile']]['usage'] += 1;
			elseif ('post' == $type)
				$profiles['automatic']['usage'] += 1;
			else
				$profiles['manual']['usage'] += 1;

			if (isset($content_types[$type]['sources'])) {
				foreach ($content_types[$type]['sources'] as $profile)
					$profiles[$profile]['usage'] +=1;
			}
		}
		return $profiles;
	}

	/*
	 * store community options and set any missing defaults to the first available option
	 *
	 * @since 0.1.0
	 */
	public function set_community_resources($community_id, $update_first_project_callback = FALSE) {
		$client = new Lingotek_API();
		$refresh_success = array(
			'projects' => FALSE,
			'workflows' => FALSE,
		);

		$api_data = $client->get_projects($community_id);
		$projects = array();
        if ($api_data !== FALSE) {
            foreach ($api_data->entities as $project) {
                $projects[$project->properties->id] = $project->properties->title;
            }
            if ($api_data->properties->total == 1) {
                if (!$project->properties->callback_url) {
                    $client->update_callback_url($project->properties->id);
                }
            }
            natcasesort($projects); //order by title (case-insensitive)
            $refresh_success['projects'] = TRUE;
        }

		$api_data = $client->get_workflows($community_id);
		$default_workflows = array(
			'c675bd20-0688-11e2-892e-0800200c9a66' => 'Machine Translation',
			'ddf6e3c0-0688-11e2-892e-0800200c9a66' => 'Machine Translation + Post-Edit',
			'6ff1b470-33fd-11e2-81c1-0800200c9a66' => 'Machine Translation + Translate',
			'2b5498e0-f3c7-4c49-9afa-cca4b3345af7' => 'Translation + 1 review',
			'814172a6-3744-4da7-b932-5857c1c20976' => 'Translation + 2 reviews',
			'2210b148-0c44-4ae2-91d0-ca2ee47c069e' => 'Translation + 3 reviews',
			'7993b4d7-4ada-46d0-93d5-858db46c4c7d' => 'Translation Only'
		);
		$workflows = array();
		if ($api_data) {
			foreach ($api_data->entities as $workflow) {
				$workflows[$workflow->properties->id] = $workflow->properties->title;
			}
			$diff = array_diff_key($workflows, $default_workflows);
			if (empty($diff)) {
				$workflows = array('c675bd20-0688-11e2-892e-0800200c9a66' => 'Machine Translation');
			}
			natcasesort($workflows); //order by title (case-insensitive)
			$refresh_success['workflows'] = TRUE;
		}

		$api_data = $client->get_filters();
		$filters = array();
		if ($api_data && $api_data->properties->total > 0) {
			foreach ($api_data->entities as $filter) {
				if (!$filter->properties->is_public) {
					$filters[$filter->properties->id] = $filter->properties->title;
				}
				if ($filter->properties->title == 'okf_json@with-html-subfilter.fprm' || $filter->properties->title == 'okf_html@wordpress.fprm') {
					$filters[$filter->properties->id] = $filter->properties->title;
				}
			}
			$primary_filter_id = array_search('okf_json@with-html-subfilter.fprm', $filters);
			$secondary_filter_id = array_search('okf_html@wordpress.fprm', $filters);
			$defaults = get_option('lingotek_defaults');
			if($defaults == NULL) {
				$defaults['primary_filter_id'] = $primary_filter_id;
				$defaults['secondary_filter_id'] = $secondary_filter_id;
				update_option('lingotek_defaults', $defaults);
			}
		}

		$resources = array(
			'projects' => $projects,
			'workflows' => $workflows,
			'filters' => $filters,
		);

		if ($refresh_success['projects'] == TRUE || $refresh_success['workflows'] == TRUE) {
			update_option('lingotek_community_resources', $resources);
			$this->ensure_valid_defaults();
		}
		return $refresh_success;
	}

	public function ensure_valid_defaults() {
		$resources = get_option('lingotek_community_resources');
		$defaults = get_option('lingotek_defaults');
		$valid_default = array();
		foreach ($resources as $resource_key => $options) {
			$key = substr($resource_key,0,strlen($resource_key)-1)."_id";
			$valid_default[$key] = 0;
			if(!is_array($defaults)) {
				continue;
			}
			foreach ($options as $option_key => $option_val) {
				if(!array_key_exists($key, $defaults)){
					continue;
				}
				if ($option_key === $defaults[$key]) {
					$valid_default[$key] = 1;
					break;
				}
			}
		}
		foreach ($valid_default as $key => $valid) {
			$resource_key = substr($key,0,strpos($key,'_'))."s";
			if ($valid) {
				continue;
			}
			else {
				$defaults[$key] = current(array_keys($resources[$resource_key]));
			}
		}
		$num_valid_defaults = array_sum($valid_default);

		if($num_valid_defaults < count($valid_default)){
			add_settings_error( 'lingotek_defaults', 'community-selected', sprintf(__('Your <a href="%s"><i>Defaults</i></a> have been updated to valid options for this community.', 'wp-lingotek'), admin_url('admin.php?page=wp-lingotek_settings&sm=defaults')), 'updated' );
		}
		unset($defaults['filter_id']);
		update_option('lingotek_defaults', $defaults);
	}

	/*
	 * displays the admin manage page
	 *
	 * @since 0.1.0
	 */
	public function display_manage_page() {
		if (self::has_token_details()) {
			include(LINGOTEK_ADMIN_INC . '/view-manage.php');
		}
		else {
			$this->display_settings_page();
		}
	}

	/*
	 * add screen option on translations->manage page
	 *
	 * @since 0.2
	 */
	public function load_manage_page() {
		add_screen_option('per_page', array(
			'label'   => __('Strings groups', 'wp-lingotek'),
			'default' => 10,
			'option'  => 'lingotek_strings_per_page'
		));
	}

	/*
	 * save screen option from translations->manage page
	 *
	 * @since 0.2
	 */
	public function set_screen_option($status, $option, $value) {
		if ('lingotek_strings_per_page' == $option) {
			return $value;
    }
		return $status;
	}

	/*
	 * displays the admin manage page
	 *
	 * @since 0.1.0
	 */
	public function display_dashboard_page() {
		$token_details = self::has_token_details();
		if ($token_details) {
			$community_id = get_option('lingotek_community');
			$defaults = get_option('lingotek_defaults');
			$user = wp_get_current_user();

			// The data that will be passed to the Lingotek GMC dashboard
			$cms_data = array(
			// lingotek
			"community_id"=> $community_id,
			"external_id"=> $token_details['login_id'],
			//"vault_id"=> $defaults['vault_id'],
			"workflow_id"=> $defaults['workflow_id'],
			"project_id"=> $defaults['project_id'],
			"first_name"=> $user->display_name,
			"last_name"=> '',
			"email"=> get_bloginfo('admin_email'),
			// cms
			"cms_site_id"=> site_url(),
			"cms_site_key"=> site_url(),
			"cms_site_name"=> get_bloginfo('name'),
			"cms_type"=> "Wordpress",
			"cms_version"=> get_bloginfo('version'),
			"cms_tag"=> LINGOTEK_PLUGIN_SLUG,
			"locale"=> pll_current_language('lingotek_locale'),
			"module_version"=> LINGOTEK_VERSION,
			"endpoint_url" => $this->get_dashboard_endpoint()
			);
			include(LINGOTEK_ADMIN_INC . '/view-dashboard.php');
		}
		else {
			$this->display_settings_page();
		}
	}

	/*
	 * returns the access token when present, otherwise returns FALSE
	 *
	 * @since 0.1.0
	 */
	public static function has_token_details() {
		$token_details = get_option('lingotek_token');
		$has_token = FALSE;
		if ($token_details !== FALSE && key_exists('access_token', $token_details) && key_exists('login_id', $token_details) && strlen($token_details['access_token']) && strlen($token_details['login_id'])) {
			$has_token = TRUE;
			return $token_details;
		}
		return $has_token;
	}

	public function display_network_settings_page() {
		if (is_multisite() && self::has_token_details()) {
			include(LINGOTEK_ADMIN_INC . '/view-network.php');
		}
		else {
			$this->display_settings_page();
		}
	}

	/*
	 * adds a Lingotek settings option in the network admin page
	 *
	 * @since 0.1.0
	 */
	public function add_network_admin_menu() {
		add_submenu_page('settings.php', __('Lingotek Settings', 'wp-lingotek'), __('Lingotek Settings', 'wp-lingotek'), 'manage_network_options', $this->plugin_slug . '_network', array($this, 'display_network_settings_page'), 'dashicons-translation');
	}

	public function display_tutorial_page() {
		if (self::has_token_details()) {
			include(LINGOTEK_ADMIN_INC . '/view-tutorial.php');
		}
		else {
			$this->display_settings_page();
		}
	}

}