<?php

//if uninstall not called from WordPress exit
if (!defined('WP_UNINSTALL_PLUGIN'))
    exit();

class Lingotek_Uninstall {

	/*
	 * constructor: manages uninstall for multisite
	 *
	 * @since 0.1
	 */
	function __construct() {
		global $wpdb;

		// check if it is a multisite uninstall - if so, run the uninstall function for each blog id
		if (is_multisite()) {
			foreach ($wpdb->get_col("SELECT blog_id FROM $wpdb->blogs") as $blog_id) {
				switch_to_blog($blog_id);
				$this->uninstall();
			}
			restore_current_blog();
		}
		else
			$this->uninstall();
	}

	/*
	 * removes ALL plugin options
	 * do not remove the data merged with Polylang data in translations groups
	 * these data are removed when uninstalling Polylang
	 *
	 * @since 0.1
	 */
	function uninstall() {
		delete_option('lingotek_prefs');
		delete_option('lingotek_content_type');
		delete_option('lingotek_community');
		delete_option('lingotek_defaults');
		delete_option('lingotek_profiles');
		delete_option('lingotek_token');
		delete_option('lingotek_community_resources');
		delete_option('lingotek_custom_fields');
		delete_option('lingotek_log_errors');
	}
}

new Lingotek_Uninstall();
