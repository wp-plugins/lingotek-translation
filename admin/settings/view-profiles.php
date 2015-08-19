<?php
global $polylang;

$profiles = Lingotek::get_profiles();
$profiles = $this->get_profiles_usage($profiles);
$settings = $this->get_profiles_settings();

if (isset($_GET['lingotek_action']) && 'delete-profile' == $_GET['lingotek_action']) {
	check_admin_referer('delete-profile');

	// check again that usage empty
	if (!empty($profiles[$_GET['profile']]) && empty($profiles[$_GET['profile']]['usage'])) {
		unset($profiles[$_GET['profile']]);
		update_option('lingotek_profiles', $profiles);
		add_settings_error('lingotek_profile', 'default', __('Your translation profile was sucessfully deleted.', 'wp-lingotek'), 'updated');
		set_transient('settings_errors', get_settings_errors(), 30);
		wp_redirect(admin_url('admin.php?page=wp-lingotek_settings&sm=profiles&settings-updated=1'));
		exit;
	}
}

if (!empty($_POST)) {
	check_admin_referer('lingotek-edit-profile', '_wpnonce_lingotek-edit-profile');

	$defaults = get_option('lingotek_defaults');

	if (empty($_POST['name']) && empty($_POST['profile'])) {
		add_settings_error('lingotek_profile', 'default', __('You must provide a name for your translation profile.', 'wp-lingotek'), 'error');
	}
	else {
		$profile = sanitize_title(empty($_POST['profile']) ? $_POST['name'] : $_POST['profile']);
		$profiles[$profile]['profile'] = $profile;
		if (!empty($_POST['name']))
			$profiles[$profile]['name'] = strip_tags($_POST['name']);

		foreach (array('upload', 'download', 'project_id', 'workflow_id') as $key) {
			if (isset($_POST[$key]) && in_array($_POST[$key], array_keys($settings[$key]['options'])))
				$profiles[$profile][$key] = $_POST[$key];

			if (empty($_POST[$key]) || 'default' == $_POST[$key])
				unset($profiles[$profile][$key]);
		}

		foreach ($this->pllm->get_languages_list() as $language) {
			switch($_POST['targets'][$language->slug]) {
				case 'custom':
					foreach (array('download', 'project_id', 'workflow_id') as $key) {
						if (isset($_POST['custom'][$key][$language->slug]) && in_array($_POST['custom'][$key][$language->slug], array_keys($settings[$key]['options']))) {
							$profiles[$profile]['custom'][$key][$language->slug] = $_POST['custom'][$key][$language->slug];
						}

						if (empty($_POST['custom'][$key][$language->slug]) || 'default' == $_POST['custom'][$key][$language->slug]) {
							unset($profiles[$profile]['custom'][$key][$language->slug]);
						}
					}

				case 'disabled':
					$profiles[$profile]['targets'][$language->slug] = $_POST['targets'][$language->slug];
					break;

				case 'default':
					unset($profiles[$profile]['targets'][$language->slug]);
			}
		}

		// hardcode default values for automatic and manual profiles as the process above emptied them
		$profiles['automatic']['upload'] = $profiles['automatic']['download'] = 'automatic';
		$profiles['manual']['upload'] = $profiles['manual']['download'] = 'manual';
		$profiles['automatic']['name'] = 'Automatic'; $profiles['manual']['name'] = 'Manual'; $profiles['disabled']['name'] = 'Disabled';// do not localize names here

		update_option('lingotek_profiles', $profiles);
		add_settings_error('lingotek_profile', 'default', __('Your translation profile was sucessfully saved.', 'wp-lingotek'), 'updated');

		if (isset($_POST['update_callback'])) {
			$project_id = isset($profiles[$profile]['project_id']) ? $profiles[$profile]['project_id'] : $defaults['project_id'];
			$client = new Lingotek_API();
			if ($client->update_callback_url($project_id))
				add_settings_error('lingotek_profile', 'default', __('Your callback url was successfully updated.', 'wp-lingotek'), 'updated');
		}
	}
	settings_errors();
}

?>
<h3><?php _e('Translation Profiles', 'wp-lingotek'); ?></h3>
<p class="description"><?php _e('Translation profiles allow you to quickly configure and re-use translation settings.', 'wp-lingotek'); ?></p><?php
$table = new Lingotek_Profiles_Table();
$table->prepare_items($profiles);
$table->display();
printf(
	'<a href="%s" class="button button-primary">%s</a>',
	admin_url('admin.php?page=wp-lingotek_settings&sm=edit-profile'),
	__('Add New Profile', 'wp-lingotek')
);

