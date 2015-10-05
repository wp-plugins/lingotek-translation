<?php

global $polylang;

foreach ($polylang->model->get_translated_post_types() as $post_type) {
	$post_type_object = get_post_type_object($post_type);
	$data[$post_type] = array(
		'type'    => $post_type,
		'name'    => $post_type_object->labels->name,
		'fields'  => array(
			'label' => Lingotek_Group_Post::get_content_type_fields($post_type)
		)
	);
}

foreach ($polylang->model->get_translated_taxonomies() as $tax) {
	$taxonomy = get_taxonomy($tax);
	$data[$tax] = array(
		'type'    => $tax,
		'name'    => $taxonomy->labels->name,
		'fields'  => array(
			'label' => Lingotek_Group_Term::get_content_type_fields($tax)
		)
	);
}

$data['string'] = array(
	'type'    => 'string',
	'name'    => __('Strings', 'wp-lingotek'),
);

if (empty($_POST)) {
	$content_types = get_option('lingotek_content_type');
}

else {
	check_admin_referer('lingotek-content-types', '_wpnonce_lingotek-content-types');

	$profiles = array_keys(get_option('lingotek_profiles'));

	foreach ($data as $key => $item) {
		if (isset($data[$key]['name'])) {
			if (in_array($_POST[$key]['profile'], $profiles))
				$content_types[$key]['profile'] = $_POST[$key]['profile'];

			foreach ($polylang->model->get_languages_list() as $language) {
				if (isset($_POST[$key]['sources'][$language->slug]) && in_array($_POST[$key]['sources'][$language->slug], $profiles))
					$content_types[$key]['sources'][$language->slug] = $_POST[$key]['sources'][$language->slug];
			}

		}
		if (isset($data[$key]['fields'])) {
			foreach ($data[$key]['fields']['label'] as $key1 => $arr) {
				if (is_array($arr)) {
					foreach (array_keys($arr) as $key2) {
						 if(empty($_POST[$key]['fields'][$key1][$key2]))
							$content_types[$key]['fields'][$key1][$key2] = 1;
					}
				}
				elseif (empty($_POST[$key]['fields'][$key1])) {
					$content_types[$key]['fields'][$key1] = 1;
				}
			}
		}
	}

	update_option('lingotek_content_type', $content_types);
	add_settings_error('lingotek_content_types', 'default', __('Your content types were sucessfully saved.', 'wp-lingotek'), 'updated');
	settings_errors();
}

foreach ($data as $key => $item) {
	// default profile is manual except for post
	$data[$key]['profile'] = empty($content_types[$key]['profile']) ? ('post' == $key ? 'automatic' : 'manual') : $content_types[$key]['profile'];
	$data[$key]['sources'] = empty($content_types[$key]['sources']) ? array() : $content_types[$key]['sources'];
	if (!empty($content_types[$key]['fields']))
		$data[$key]['fields']['value'] = $content_types[$key]['fields'];
}

?>
<h3><?php _e('Content Type Configuration', 'wp-lingotek'); ?></h3>
<p class="description"><?php _e('Content types can be configured to use any translation profile.  Additionally, translation profiles can be set based on the language the content was authored in.', 'wp-lingotek'); ?></p>

<form id="lingotek-content-types" method="post" action="admin.php?page=wp-lingotek_manage&amp;sm=content" class="validate"><?php
wp_nonce_field('lingotek-content-types', '_wpnonce_lingotek-content-types');

$table = new Lingotek_Content_Table($content_types);
$table->prepare_items($data);
$table->display();

submit_button(__('Save Changes', 'wp-lingotek'), 'primary', 'submit', false);
?>
</form>
