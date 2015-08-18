<?php
/*
Plugin name: Lingotek Translation
Plugin URI: http://lingotek.com/wordpress#utm_source=wpadmin&utm_medium=plugin&utm_campaign=wplingotektranslationplugin
Version: 1.0.6
Author: Lingotek and Frédéric Demarle
Author uri: http://lingotek.com
Description: Lingotek offers convenient cloud-based localization and translation.
Text Domain: wp-lingotek
Domain Path: /languages
GitHub Plugin URI: https://github.com/lingotek/wp-lingotek
*/

// don't access directly
if (!function_exists('add_action'))
	exit();

define('LINGOTEK_VERSION', '1.0.6'); // plugin version (should match above meta)
define('LINGOTEK_MIN_PLL_VERSION', '1.7.4.2');
define('LINGOTEK_BASENAME', plugin_basename(__FILE__)); // plugin name as known by WP
define('LINGOTEK_PLUGIN_SLUG', 'wp-lingotek');// plugin slug (should match above meta: Text Domain)
define('LINGOTEK_DIR', dirname(__FILE__)); // our directory
define('LINGOTEK_INC', LINGOTEK_DIR . '/include');
define('LINGOTEK_ADMIN_INC',  LINGOTEK_DIR . '/admin');
define('LINGOTEK_URL', plugins_url('', __FILE__));

class Lingotek {
	public $model; // Lingotek model
	public $callback;

	// array to map Lingotek locales to WP locales
	// map as 'WP locale' => 'Lingotek locale'
	public static $lingotek_locales = array(
		'af' => 'af-ZA', 'ak' => 'ak-GH', 'am' => 'am-ET', 'ar' => 'ar', 'as' => 'as-IN', 'az' => 'az-AZ',
		'ba' => 'ba-RU', 'bel' => 'be-BY', 'bg_BG' => 'bg-BG', 'bn_BD' => 'bn-BD', 'bo' => 'bo-CN', 'bs_BA' => 'bs-BA',
		'ca' => 'ca-ES', 'co' => 'co-FR', 'cs_CZ' => 'cs-CZ', 'cy' => 'cy-GB', 'da_DK' => 'da-DK', 'de_CH' => 'de-CH',
		'de_DE' => 'de-DE', 'dv' => 'dv-MV', 'el' => 'el-GR', 'en_AU' => 'en-AU', 'en_CA' => 'en-CA', 'en_GB' => 'en-GB',
		'en_US' => 'en-US', 'eo' => 'eo-FR', 'es_AR' => 'es-AR', 'es_CL' => 'es-CL', 'es_CO' => 'es-CO', 'es_ES' => 'es-ES',
		'es_MX' => 'es-MX', 'es_PE' => 'es-PE', 'es_PR' => 'es-PR', 'es_VE' => 'es-VE', 'et' => 'et-EE', 'eu' => 'eu-ES',
		'fa_IR' => 'fa-IR', 'fi' => 'fi-FI', 'fr_FR' => 'fr-FR', 'ga' => 'ga-IE', 'gd' => 'gd-GB', 'gl_ES' => 'gl-ES',
		'gn' => 'gn-BO', 'haw_US' => 'haw-US', 'he_IL' => 'he-IL', 'hi_IN' => 'hi-IN', 'hr' => 'hr-HR', 'hu_HU' => 'hu-HU',
		'hy' => 'hy-AM', 'id_ID' => 'id-ID', 'is_IS' => 'is-IS', 'it_IT' => 'it-IT', 'ja' => 'ja-JP', 'jv_ID' => 'jv-ID',
		'ka_GE' => 'ka-GE', 'kin' => 'kin-RW', 'kk' => 'kk-KZ', 'kn' => 'kn-IN', 'ko_KR' => 'ko-KR', 'ky_KY' => 'ky-KG',
		'lb_LU' => 'lb-LU', 'lo' => 'lo-LA', 'lt_LT' => 'lt-LT', 'lv' => 'lv-LV', 'mg_MG' => 'mg-MG', 'mk_MK' => 'mk-MK',
		'ml_IN' => 'ml-IN', 'mn' => 'mn-MN', 'mr' => 'mr-IN', 'ms_MY' => 'ms-MY', 'my_MM' => 'my-MM', 'ne_NP' => 'ne-NP',
		'nl_BE' => 'nl-BE', 'nl_NL' => 'nl-NL', 'nn_NO' => 'nn-NO', 'pa_IN' => 'pa-IN', 'pl_PL' => 'pl-PL', 'ps' => 'ps-AF',
		'pt_BR' => 'pt-BR', 'pt_PT' => 'pt-PT', 'ro_RO' => 'ro-RO', 'ru_RU' => 'ru-RU', 'sa_IN' => 'sa-IN', 'sd_PK' => 'sd-PK',
		'si_LK' => 'si-LK', 'sk_SK' => 'sk-SK', 'sl_SI' => 'sl-SI', 'so_SO' => 'so-SO', 'sq' => 'sq-SQ', 'sr_RS' => 'sr-CS',
		'su_ID' => 'su-ID', 'sv_SE' => 'sv-SE', 'sw' => 'sw-TZ', 'ta_IN' => 'ta-IN', 'te' => 'te-IN', 'tg' => 'tg-TJ',
		'th' => 'th-TH', 'tir' => 'ti-ER', 'tl' => 'tl-PH', 'tr_TR' => 'tr-TR', 'ug_CN' => 'ug-CN', 'uk' => 'uk-UA',
		'ur' => 'ur-PK', 'uz_UZ' => 'uz-UZ', 'vi' => 'vi-VN', 'zh_CN' => 'zh-CN', 'zh_HK' => 'zh-HK', 'zh_TW' => 'zh-TW',
	);

	/**
	 *
	 * Unique identifier for your plugin.
	 *
	 *
	 * The variable name is used as the text domain when internationalizing strings
	 * of text. Its value should match the Text Domain file header in the main
	 * plugin file.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected $plugin_slug = LINGOTEK_PLUGIN_SLUG;

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

	protected static $logging = FALSE;

	/*
	 * constructor
	 *
	 * @since 0.1
	 */
	public function __construct() {
		// manages plugin activation and deactivation
		register_activation_hook( __FILE__, array(&$this, 'activate'));
		register_deactivation_hook( __FILE__, array(&$this, 'deactivate'));

		// stopping here if we are going to deactivate the plugin (avoids breaking rewrite rules)
		if (isset($_GET['action'], $_GET['plugin']) && 'deactivate' == $_GET['action'] && plugin_basename(__FILE__) == $_GET['plugin'])
			return;

		// loads the admin side of Polylang for the dashboard
		if (defined('DOING_AJAX') && DOING_AJAX && isset($_REQUEST['action']) && 'lingotek_language' == $_REQUEST['action']) {
			define('PLL_AJAX_ON_FRONT', false);
			add_filter('pll_model', create_function('$c', 'return "PLL_Admin_Model";'));
		}

		spl_autoload_register(array(&$this, 'autoload')); // autoload classes

		// init
		add_filter('pll_model', array(&$this, 'pll_model'));
		add_action('init', array(&$this, 'init'));
		add_action('admin_init', array(&$this, 'admin_init'));

		// add Lingotek locale to languages
		add_filter('pll_languages_list', array(&$this, 'pre_set_languages_list'));

		// flag title
		add_filter('pll_flag_title', array(&$this, 'pll_flag_title'), 10, 3);

		// adds a pointer upon plugin activation to draw attention to Lingotek
		if (!get_option('lingotek_token')) {
			add_action('init', array(&$this, 'lingotek_activation_pointer'));
		}

		// adds extra plugin compatibility - borrowed from Polylang
		if (!defined('LINGOTEK_PLUGINS_COMPAT') || LINGOTEK_PLUGINS_COMPAT) {
			Lingotek_Plugins_Compat::instance();
		}
	}

	/**
	 * Return the plugin slug.
	 *
	 * @since    0.1
	 *
	 * @return    Plugin slug variable.
	 */
	public function get_plugin_slug() {
		return $this->plugin_slug;
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/*
	 * activation or deactivation for all blogs
	 * method taken from Polylang
	 *
	 * @since 0.1
	 *
	 * @param string $what either 'activate' or 'deactivate'
	 */
	protected function do_for_all_blogs($what) {
		// network
		if (is_multisite() && isset($_GET['networkwide']) && ($_GET['networkwide'] == 1)) {
			global $wpdb;

			foreach ($wpdb->get_col("SELECT blog_id FROM $wpdb->blogs") as $blog_id) {
				switch_to_blog($blog_id);
				$what == 'activate' ? $this->_activate() : $this->_deactivate();
			}
			restore_current_blog();
		}

		// single blog
		else
			$what == 'activate' ? $this->_activate() : $this->_deactivate();
	}

	/*
	 * plugin activation for multisite
	 *
	 * @since 0.1
	 */
	public function activate() {
		$this->do_for_all_blogs('activate');
	}

	/*
	 * plugin activation
	 *
	 * @since 0.1
	 */
	protected function _activate() {
		global $polylang;

		if (isset($polylang)) {
			$polylang->model->clean_languages_cache(); // to add lingotek_locale property
		}

		// default profiles
		if (false === get_option('lingotek_profiles')) {
			$profiles = array(
				'automatic' => array(
					'profile'  => 'automatic',
					'name'     => __('Automatic', 'wp-lingotek'),
					'upload'   => 'automatic',
					'download' => 'automatic'
				),
				'manual' => array(
					'profile'  => 'manual',
					'name'     => __('Manual', 'wp-lingotek'),
					'upload'   => 'manual',
					'download' => 'manual'
				),
				'disabled' => array(
					'profile'  => 'disabled',
					'name'     => __('Disabled', 'wp-lingotek'),
				),
			);
			update_option('lingotek_profiles', $profiles);
		}

		// for the end point for the Lingoteck callback in rewrite rules
		// don't use flush_rewrite_rules at network activation. See #32471
		delete_option('rewrite_rules');
	}

	/*
	 * provides localized version of the canned translation profiles
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public static function get_profiles() {
		$profiles = get_option('lingotek_profiles');

		//localize canned profile names
		foreach($profiles as $k=>$v){
			if(in_array($k,array('automatic','manual','disabled'))){
				$profile_name = $profiles[$k]['name'];
				$profiles[$k]['name'] = __($profile_name,'wp-lingotek');// localize canned profile names
			}
		}

		return $profiles;
	}

	/*
	 * plugin deactivation for multisite
	 *
	 * @since 0.1
	 */
	public function deactivate() {
		$this->do_for_all_blogs('deactivate');
	}

	/*
	 * plugin deactivation
	 *
	 * @since 0.5
	 */
	protected function _deactivate() {
		delete_option('rewrite_rules');
	}

	/*
	 * blog creation on multisite (to set default options)
	 *
	 * @since 0.1
	 *
	 * @param int $blog_id
	 */
	public function wpmu_new_blog($blog_id) {
		switch_to_blog($blog_id);
		$this->_activate();
		restore_current_blog();
	}

	/*
	 * autoload classes
	 *
	 * @since 0.1
	 *
	 * @param string $class
	 */
	public function autoload($class) {
		// not a Lingotek class
		if (0 !== strncmp('Lingotek_', $class, 9))
			return;

		$class = str_replace('_', '-', strtolower(substr($class, 9)));
		foreach (array(LINGOTEK_INC, LINGOTEK_ADMIN_INC) as $path) {
			if (file_exists($file = "$path/$class.php")) {
				require_once($file);
				break;
			}
		}
	}

	/*
	 * set the Polylang model class to PLL_Admin_Model on Lingotek admin pages
	 *
	 * @since 0.2
	 *
	 * @param string $class
	 * @return string modified class 'PLL_Model' | 'PLL_Admin_Model'
	 */
	public function pll_model($class) {
		if (PLL_ADMIN && isset($_GET['page']) && in_array($_GET['page'], array('wp-lingotek', 'wp-lingotek_manage', 'wp-lingotek_settings', 'wp-lingotek_network')))
			return 'PLL_Admin_Model';
		return $class;
	}

	/*
	 * setups Lingotek model and callback
	 * sets filters to call Lingotek child classes instead of Polylang classes
	 *
	 * @since 0.1
	 *
	 */
	public function init() {
		if (!defined('POLYLANG_VERSION'))
			return;

		add_rewrite_rule( 'lingotek/?$', 'index.php?lingotek=1&$matches[1]', 'top' );

		if (is_admin())
			new Lingotek_Admin();

		// admin side
		if (PLL_ADMIN && !PLL_SETTINGS) {
			$this->model = new Lingotek_Model();

			// overrides Polylang classes
			$classes = array('Filters_Post', 'Filters_Term', 'Filters_Media', 'Filters_Columns');
			foreach ($classes as $class)
				add_filter('pll_' . strtolower($class) , create_function('$v', "return 'Lingotek_$class';"));

			// add actions to posts, media and terms list
			// no need to load this if there is no language yet
			if ($GLOBALS['polylang']->model->get_languages_list()) {
				$this->post_actions = new Lingotek_Post_Actions();
				$this->term_actions = new Lingotek_Term_Actions();
				$this->string_actions = new Lingotek_String_actions();
			}

			$this->utilities = new Lingotek_Utilities();
		}

		// callback
		elseif (!PLL_ADMIN && !PLL_AJAX_ON_FRONT) {
			$GLOBALS['wp']->add_query_var('lingotek');

			$this->model = new Lingotek_Model();
			$this->callback = new Lingotek_Callback($this->model);
		}
	}

	/*
	 * some init
	 *
	 * @since 0.1
	 */
	public function admin_init() {
		// plugin i18n, only needed for backend
		load_plugin_textdomain('wp-lingotek', false, basename(LINGOTEK_DIR).'/languages');

		if (!defined('POLYLANG_VERSION'))
			add_action('all_admin_notices', array(&$this, 'pll_inactive_notice'));

		elseif (version_compare(POLYLANG_VERSION, LINGOTEK_MIN_PLL_VERSION, '<'))
			add_action('all_admin_notices', array(&$this, 'pll_old_notice'));

		elseif (isset($GLOBALS['polylang']) && !count($GLOBALS['polylang']->model->get_languages_list()))
			self::create_first_language();

		wp_enqueue_style('lingotek_admin', LINGOTEK_URL .'/css/admin.css', array(), LINGOTEK_VERSION);
	}

	/*
	 * displays a notice if Polylang is inactive
	 *
	 * @since 0.1
	 */
	public function pll_inactive_notice() {
		printf(
			'<div class="error"><p>%s</p></div>',
			__('Lingotek Translation requires Polylang to work. Please install Polylang.', 'wp-lingotek')
		);
	}

	/*
	 * displays a notice if Polylang is obsolete
	 *
	 * @since 0.1
	 */
	public function pll_old_notice() {
		printf(
			'<div class="error"><p>%s</p></div>',
			sprintf(
				__('Lingotek Translation requires Polylang %s to work. Please upgrade Polylang.', 'wp-lingotek'),
				'<strong>' . LINGOTEK_MIN_PLL_VERSION . '</strong>'
			)
		);
	}

	/*
	 * creates at least on language to avoid breaking the Lingotek Dashboard
	 *
	 * @since 0.2
	 */
	static protected function create_first_language() {
		global $polylang;

		include(PLL_ADMIN_INC.'/languages.php');
		$locale = get_locale();

		// attempts to set the default language from the current locale
		foreach ($languages as $lang) {
			if (get_locale() == $lang[1])
				$language = $lang;
		}

		// defaults to en_US
		if (empty($language))
			$language = array('en', 'en_US', 'English');

		$pll_model = new PLL_Admin_Model($polylang->options); // need admin model
		$pll_model->add_language(array(
			'slug'       => $language[0],
			'locale'     => $language[1],
			'name'       => $language[2],
			'rtl'        => isset($language[3]) ? 1 : 0,
			'term_group' => 0
		));
	}

	/*
	 * adds Lingotek locale to the PLL_Language objects
	 * uses the map otherwise uses a stupid fallback
	 *
	 * @since 0.1
	 *
	 * @param array $languages list of language objects
	 * @return array
	 */
	public function pre_set_languages_list($languages) {
		foreach ($languages as $key => $language) {
			if (is_object($language))
				$languages[$key]->lingotek_locale = self::map_to_lingotek_locale($language->locale); // backward compatibility with Polylang < 1.7.3
			else
				$languages[$key]['lingotek_locale'] = self::map_to_lingotek_locale($language['locale']);
		}

		return $languages;
	}

	/*
	 * maps a Lingotek locale to a WordPress locale
	 *
	 * @since 0.3
	 *
	 * @param string $lingotek_locale Lingotek locale
	 * @return string WordPress locale
	 */
	public static function map_to_wp_locale($lingotek_locale) {
		// look for the locale in the map (take care that Lingotek sends locales with either '_' or '-'
		// if not found just replace '-' by '_'
		$wp_locale = array_search(str_replace('_', '-', $lingotek_locale), self::$lingotek_locales);
		return $wp_locale ? $wp_locale : str_replace('-', '_', $lingotek_locale);
	}

	/*
	 * maps a WordPres locale to a Lingotek locale
	 *
	 * @since 0.3
	 *
	 * @param string $wp_locale WordPress locale
	 * @return string Lingotek locale
	 */
	public static function map_to_lingotek_locale($wp_locale) {
		// look for the locale in the map
		// if not found just replace '_ 'by '-'
		return isset(self::$lingotek_locales[$wp_locale]) ? self::$lingotek_locales[$wp_locale] : str_replace('_', '-', $wp_locale);
	}

	/*
	 * modifies the flag title to add the locale
	 *
	 * @since 0.3
	 *
	 * @param string $name language name
	 * @param string $slug language code
	 * @param string $locale language locale
	 * @return string
	 */
	public function pll_flag_title($name, $slug, $locale) {
		return "$name ($locale)";
	}

	public static function log($data, $label = NULL) {
		if (self::$logging) {
			$log_string = "";
			if (is_string($label))
				$log_string .= $label . "\n";
			if (is_string($data)) {
				$log_string .= $data;
			}
			else {
				$log_string .= print_r($data, TRUE);
			}
			error_log($log_string);
		}
	}

	/*
	 * Creates a pointer to draw attention to the new Lingotek menu item upon plugin activation
	 * code borrowed from Polylang
	 * @since 1.0.1
	 */
	public function lingotek_activation_pointer() {
		$content = __('You’ve just installed Lingotek Translation! Click below to activate your account and automatically translate your website for free!', 'wp-lingotek');

		$buttons = array(
			array(
				'label' => __('Close')
			),
			array(
				'label' => __('Activate Account', 'wp-lingotek'),
				'link' => admin_url('admin.php?page=' . $this->plugin_slug . '_settings&connect=new'),
			)
		);

		$args = array(
			'pointer' => 'wp-lingotek',
			'id' => 'toplevel_page_wp-lingotek',
			'position' => array(
				'edge' => 'bottom',
				'align' => 'left',
			),
			'width' => 380,
			'title' => __('Congratulations!', 'wp-lingotek'),
			'content' => $content,
			'buttons' => $buttons
		);

		new Lingotek_Pointer($args);
	}
}

// Helpers - development functions
if (!function_exists('dc')) {
  function dc($data, $label = NULL, $die = FALSE) {
    echo '<pre style="background: #f3f3f3; color: #000">';
    if (is_string($label))
      echo '<h1>' . $label . '</h1>';
    print_r($data);
    echo '</pre>';
    if ($die || (is_bool($label) && $label))
      die();
  }
}
if (!function_exists('dd')) {
  function dd($data, $label = NULL, $die = FALSE) {
    return dc($data, $label, TRUE);
  }
}

$GLOBALS['wp_lingotek'] = Lingotek::get_instance();
