<?php

/*
 * manages compatibility with 3rd party plugins (and themes)
 * this class is available as soon as the plugin is loaded
 * code borrowed from Polylang
 *
 * @since 1.0.6
 */
class Lingotek_Plugins_Compat {
	static protected $instance;

	protected function __construct() {
		// WordPress Importer
		add_action('init', array(&$this, 'lingotek_maybe_wordpress_importer'));
	}

	static public function instance() {
		if (empty(self::$instance))
			self::$instance = new self();

		return self::$instance;
	}

	function lingotek_maybe_wordpress_importer() {
		if (defined('WP_LOAD_IMPORTERS') && class_exists('WP_Import')) {
			remove_action('admin_init', 'lingotek_wordpress_importer_init');
			add_action('admin_init', array(&$this, 'lingotek_wordpress_importer_init'));
		}
	}

	function lingotek_wordpress_importer_init() {
		$class = new ReflectionClass('WP_Import');
		load_plugin_textdomain( 'wordpress-importer', false, basename(dirname( $class->getFileName() )) . '/languages' );

		$GLOBALS['wp_import'] = new Lingotek_WP_Import();
		register_importer( 'wordpress', 'WordPress', __('Import <strong>posts, pages, comments, custom fields, categories, and tags</strong> from a WordPress export file.', 'wordpress-importer'), array( $GLOBALS['wp_import'], 'dispatch' ) );
	}
}
?>