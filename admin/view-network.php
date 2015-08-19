<?php
	$sites = wp_get_sites();
	$site_data = array();

	foreach ($sites as $site) 
	{
	    switch_to_blog($site['blog_id']);
	   	$details = get_blog_details($site['blog_id'])->blogname;
	   	$temp = array("blog_id" => $site['blog_id'], "blogname" => $details);
	   	array_push($site_data, $temp);
		$ltk = new Lingotek();
		$ltk->admin_init();
		restore_current_blog();
	}

	if (!empty($_POST)) {
		$source_site = $_POST['source'];

		if (isset($_POST['destination'])) {
			$destination_site = $_POST['destination'];

			foreach ($destination_site as $destination) {

				if (!empty($_POST['settings'])) {
					$selected_settings = $_POST['settings'];

					foreach ($selected_settings as $setting) {
						//Updates account options for access token and the base url to connect to Lingotek
						if ($setting == 'token') {
							$lingotek_option = 'lingotek_' . $setting;

							$source_options = get_blog_option($source_site, $lingotek_option);
							update_blog_option($destination, $lingotek_option, $source_options);

							$source_options = get_blog_option($source_site, 'lingotek_base_url');
							update_blog_option($destination, 'lingotek_base_url', $source_options);
						}

						//Updates the chosen option
						$lingotek_option = 'lingotek_' . $setting;

						$source_options = get_blog_option($source_site, $lingotek_option);
						update_blog_option($destination, $lingotek_option, $source_options);
					}
				}

				//Creates a new project based on the name of the selected site
				if (isset($_POST['new_project'])) {
					$options = get_blog_option($destination, 'lingotek_defaults');
	                $client = new Lingotek_API();
	                $title = htmlspecialchars_decode(get_blog_details($destination)->blogname, ENT_QUOTES);

	                if ($new_id = $client->create_project($title, $community_id = get_blog_option($destination, 'lingotek_community'))) {
	                    $options['project_id'] = $new_id;
	                    // Adds correct callback URL for new project
	                    $args = array('callback_url' => add_query_arg('lingotek', 1, get_blog_details($destination)->siteurl));
						$response = $client->patch($client->get_api_url() . '/project/' . $new_id, $args);
	                    update_blog_option($destination, 'lingotek_defaults', $options);
	                }
	            }

	            if (isset($_POST['preferences'])) {
					switch_to_blog($source_site);
					$preferences = Lingotek_Model::get_prefs();
					update_blog_option($destination, 'lingotek_prefs', $preferences);
					restore_current_blog();
				}

	            if (isset($_POST['utility_set_default_language'])) {
					switch_to_blog($destination);
					$GLOBALS['wp_lingotek']->utilities->run_utility('utility_set_default_language');
					restore_current_blog();
	            }

			}

			if (isset($_POST['utility_set_default_language'])) {
				add_settings_error('network', 'utilities', __('The language utility ran successfully.', 'wp-lingotek'), 'updated');
			}
			add_settings_error('network', 'destination', __('Your chosen settings have updated successfully for all selected sites.', 'wp-lingotek'), 'updated');
		}
		else {
			add_settings_error('network', 'destination', __('Please choose at least one destination site.', 'wp-lingotek'), 'error');
		}

		//Refreshes community resources so that the defaults are set when you visit the sites translation settings
		if (isset($_POST['new_project']) && isset($_POST['destination'])) {
			$this->set_community_resources(get_option('lingotek_community'));
			foreach ($destination_site as $destination) {
				$source_options = get_option('lingotek_community_resources');
				update_blog_option($destination, 'lingotek_community_resources', $source_options);
			}
			$num = count($destination_site);
			if ($num > 1) {
				add_settings_error('network', 'projects', __('Your new projects were successfully created.', 'wp-lingotek'), 'updated');
			}
			else {
				add_settings_error('network', 'projects', __('Your new project was successfully created.', 'wp-lingotek'), 'updated');
			}
		}
	}
	settings_errors('network');
?>

<style>
	input[type=checkbox] {
		margin: 0px 7px;
	}
</style>

<div class="wrap">
	<h2><?php _e('Lingotek Network Settings', 'wp-lingotek'); ?></h2>
	<p><?php _e('Copy Lingotek settings from the source site to multiple sites', 'wp-lingotek'); ?></p>

	<form id="network-settings" method="post" action="admin.php?page=wp-lingotek_network" class="validate" onsubmit="return confirm('Are you sure you want to submit this request? It will overwrite any current settings you have for the destination sites.');">

		<table class="form-table">
			<tr>
				<th><?php echo _e('Source Site', 'wp-lingotek'); ?></th>
				<td>
					<select name="source" id="source"><?php foreach ($site_data as $site) {
								echo "\n\t<option value='" . esc_attr($site['blog_id']) . "'>" . $site['blogname'] . '</option>'; 
							} ?>	
					</select>
				</td>
			</tr>
			<tr>
				<th><?php echo _e('Destination Site', 'wp-lingotek'); ?></th>
				<td>
					<select multiple="multiple" name="destination[]" id="destination"><?php foreach ($site_data as $site) {
								echo "\n\t<option value='" . esc_attr($site['blog_id']) . "'>" . $site['blogname'] . '</option>';
							} ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php echo _e('Settings to copy', 'wp-lingotek'); ?></th>
				<td>
					<input checked type="checkbox" id="account" name="settings[]" value="token"><label for="account"><?php echo _e('Account', 'wp-lingotek'); ?></label>
					<input checked type="checkbox" id="community" name="settings[]" value="community"><label for="community"><?php echo _e('Community', 'wp-lingotek'); ?></label>
					<input checked type="checkbox" id="defaults" name="settings[]" value="defaults"><label for="defaults"><?php echo _e('Defaults', 'wp-lingotek'); ?></label>
					<input checked type="checkbox" id="resources" name="settings[]" value="community_resources"><label for="resources"><?php echo _e('Resources', 'wp-lingotek'); ?></label>
					<input checked type="checkbox" id="profiles" name="settings[]" value="profiles"><label for="profiles"><?php echo _e('Profiles', 'wp-lingotek'); ?></label>
					<input checked type="checkbox" id="content_types" name="settings[]" value="content_type"><label for="content_types"><?php echo _e('Content Types', 'wp-lingotek'); ?></label>
					<input checked type="checkbox" id="preferences" name="preferences" value="preferences"><label for="preferences"><?php echo _e('Preferences', 'wp-lingotek'); ?></label>
				</td>
			</tr>
			<tr>
				<th><?php echo _e('New Project', 'wp-lingotek'); ?></th>
				<td>
					<input checked type="checkbox" name="new_project" id="new_project" ><label for="new_project"><?php echo _e('Create a new project using the name of the selected site (Recommended for a newly created site)', 'wp-lingotek'); ?></label>
				</td>
			</tr>
			<tr>
				<th><?php echo _e('Language', 'wp-lingotek'); ?></th>
				<td>
					<input checked type="checkbox" name="utility_set_default_language" id="utility_set_default_language" ><label for="utility_set_default_language"><?php echo _e('Set <i>default language</i> as the language for all existing content that has not been assigned a language.', 'wp-lingotek'); ?></label>
				</td>
			</tr>
		</table>

		<p>
		<?php submit_button(__('Update Options', 'wp-lingotek'), 'primary', 'submit', false); ?>
		</p>

	</form>
</div>