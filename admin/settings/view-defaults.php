<?php

$page_key = $this->plugin_slug . '_settings&sm=defaults';

wp_enqueue_script('defaults', LINGOTEK_URL . '/js/defaults.js');

if (!empty($_POST)) {
	check_admin_referer($page_key, '_wpnonce_' . $page_key);
	if (array_key_exists('refresh', $_POST)) {
            $refresh_success = $this->set_community_resources($community_id);
            if ($refresh_success['projects'] == TRUE && $refresh_success['workflows'] == TRUE) {
                add_settings_error('lingotek_community_resources', 'options', __('Resources from Lingotek were successfully updated for projects and workflows.', 'wp-lingotek'), 'updated');
            }
            else if ($refresh_success['projects'] == TRUE) {
                add_settings_error('lingotek_community_resources', 'error', __('Resources from Lingotek were successfully updated for projects.', 'wp-lingotek'), 'updated');
            }
            else if ($refresh_success['workflows'] == TRUE) {
                add_settings_error('lingotek_community_resources', 'error', __('Resources from Lingotek were successfully updated for workflows.', 'wp-lingotek'), 'updated');
            }
            else if ($refresh_success['workflows'] == FALSE || $refresh_success['projects'] == FALSE) {
                add_settings_error('lingotek_community_resources', 'error', __('The Lingotek TMS is currently unavailable. Please try again later. If the problem persists, contact Lingotek Support.', 'wp-lingotek'), 'error');
            }
	}
	else {
            $options = array();
            $settings = $this->get_profiles_settings(true);
            foreach ($settings as $key => $setting) {
                if (isset($_POST[$key])) {
                    $options[$key] = $_POST[$key];
                }
            }
            update_option('lingotek_defaults', $options);
            add_settings_error('lingotek_defaults', 'defaultgs', __('Your <i>Defaults</i> were sucessfully saved.', 'wp-lingotek'), 'updated');

            if (isset($_POST['update_callback'])) {
                $client = new Lingotek_API();
                if ($client->update_callback_url($options['project_id']))
                    add_settings_error('lingotek_defaults', 'defaultgs', __('Your callback url was successfully updated.', 'wp-lingotek'), 'updated');
            }

            //adds new project if text box is filled out
            if (!empty($_POST['new_project'])) {
                $client = new Lingotek_API();
                $title = stripslashes($_POST['new_project']);

                if ($new_id = $client->create_project($title, $community_id)) {
                    add_settings_error('lingotek_defaults', 'defaultgs', __('Your new project was successfully created.', 'wp-lingotek'), 'updated');
                    $this->set_community_resources($community_id);// updates the cache to include the newly created project
                    $options['project_id'] = $new_id;
                    update_option('lingotek_defaults', $options);
                }
            }
	}
	settings_errors();
}
$settings = $this->get_profiles_settings(true);
$options = get_option('lingotek_defaults');

// Code to determine which filter scenario will be displayed (Not configured, defaults, custom filters)
$primary_filter_id = array_search('okf_json@with-html-subfilter.fprm', $settings['primary_filter_id']['options']);
$secondary_filter_id = array_search('okf_html@wordpress.fprm', $settings['secondary_filter_id']['options']);
$default_filters = array($primary_filter_id => 'okf_json@with-html-subfilter.fprm', $secondary_filter_id => 'okf_html@wordpress.fprm');
$default_filters_exist = FALSE;
$extra_filters_exist = FALSE;
$no_filters_exist = FALSE;

if ($settings['primary_filter_id']['options'] == $default_filters) {
    $default_filters_exist = TRUE;
    $options['primary_filter_id'] = $primary_filter_id;
    $options['secondary_filter_id'] = $secondary_filter_id;
    update_option('lingotek_defaults', $options);
}
else {
    $num = count(array_diff_assoc($settings['primary_filter_id']['options'], $default_filters));
    if ($num > 0) {
        $extra_filters_exist = TRUE;
    }
    else {
        $options['primary_filter_id'] = '';
        $options['secondary_filter_id'] = '';
        update_option('lingotek_defaults', $options);
        $no_filters_exist = TRUE;
    }
}
unset($settings['primary_filter_id']['options'][$secondary_filter_id]);
unset($settings['secondary_filter_id']['options'][$primary_filter_id]);
?>

<h3><?php _e('Defaults', 'wp-lingotek'); ?></h3>
<p class="description"><?php _e('The default automation settings and resources that should be used for this site.  These settings can be overriden using translation profiles and content type configuration.', 'wp-lingotek'); ?></p>


<form id="lingotek-settings" method="post" action="admin.php?page=<?php echo $page_key; ?>" class="validate">
	<?php wp_nonce_field($page_key, '_wpnonce_' . $page_key); ?>

	<table class="form-table"><?php foreach ($settings as $key => $setting) { ?>
		<tr id="<?php echo $key.'_row'?>">
			<th scope="row"><label for="<?php echo $key; ?>"><?php echo $setting['label'] ?></label></th>
			<td>
				<select name="<?php echo $key ?>" id="<?php echo $key ?>"><?php
					foreach ($setting['options'] as $id => $title) {
						$selected = array_key_exists($key, $options) && ($options[$key] == $id) ? 'selected="selected"' : '';
						echo "\n\t<option value='" . esc_attr($id) . "' $selected>" . $title . '</option>';
					} ?>
				</select><?php
				if ('project_id' == $key) { ?>
                                    <?php

                                    if (empty($setting['options'])) { ?>
                                        <script> document.getElementById('project_id').style.display = 'none';</script>
                                        <input type="text" name="new_project" id="new_project" placeholder="<?php _e('Enter new project name', 'wp-lingotek') ?>" />
                                    <?php }
                                    else { ?>

                                        <input type="text" style="display:none" name="new_project" id="new_project" placeholder="<?php _e('Enter new project name', 'wp-lingotek') ?>" />
                                        <input type="checkbox" name="update_callback" id="update_callback"/>
                                        <label for="update_callback" id="callback_label"><?php _e('Update the callback url for this project.', 'wp-lingotek') ?></label>

                                        <br/><a href="#" id="create" onclick="toggleTextbox()" style="padding-left:3px; color:#999; font-size:80%; text-decoration:none"><b>+</b> <?php echo _e('Create New Project', 'wp-lingotek') ?></a>
                                    <?php } ?>
                                <?php } ?>
            <!-- Code to handle displaying of Primary and Secondary Filters -->
            <?php   if($no_filters_exist) { ?>
                        <script> document.getElementById("primary_filter_id_row").style.display = "none";</script>
                        <script> document.getElementById("secondary_filter_id_row").style.display = "none";</script> <?php
                        if ('primary_filter_id' == $key) { ?>
                            <tr id="filters_row"><th><?php _e('Filters', 'wp-lingotek') ?></th><td><i><?php _e('Not configured', 'wp-lingotek') ?></i></td></tr>
                    <?php }
                    }
                    if ($default_filters_exist) { ?>
                        <script> document.getElementById("primary_filter_id_row").style.display = "none";</script>
                        <script> document.getElementById("secondary_filter_id_row").style.display = "none";</script>
                    <?php } ?>
            <!-- End of filter code -->
		</tr><?php } ?>
	</table>

	<p>
	<?php submit_button(__('Save Changes', 'wp-lingotek'), 'primary', 'submit', false); ?>
	<?php submit_button(__( 'Refresh Resources', 'wp-lingotek'), 'secondary', 'refresh', false ); ?>
	</p>
</form>
