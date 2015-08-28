<?php

/*
 * Fires before WordPress Importer to remove Lingotek metadata so Lingotek can track posts correctly
 *
 * @since 1.0.6
 */
class Lingotek_WP_Import extends PLL_WP_Import {

	/*
	 * Removes post_translations metadata if no translations exist so it doesn't get put in the database by WP_Import
	 *
	 * @since 1.0.6
	 */
	public function process_posts() {
		if (empty($this->post_translations)) {
			foreach ($this->posts as &$post) {
				foreach ($post['terms'] as $key => &$term) {
					if (!empty($post['terms'])) {
						if (in_array('post_translations', $term)) {
							unset($post['terms'][$key]);
						}
					}
				}
			}
		}
		parent::process_posts();
	}
}

?>