<?php

$settings = $this->get_profiles_settings();

$defaults = get_option('lingotek_defaults');
$default = __('Use global default (%s)', 'wp-lingotek');
foreach ($settings as $key => $setting) {
	if (isset($defaults[$key]) && array_key_exists($defaults[$key], $settings[$key]['options'])) {
		$default_arr = array('default' => sprintf($default, $settings[$key]['options'][$defaults[$key]]));
		$settings[$key]['options'] = array_merge($default_arr, $settings[$key]['options']);
	}
}

$target_settings = array(
	'default'  => __('Use default settings', 'wp-lingotek'),
	'custom'   => __('Use custom settings', 'wp-lingotek'),
	'copy'	   => __('Copy source language', 'wp-lingotek'),
	'disabled' => __('Disabled', 'wp-lingotek')
);

$profiles = $this->get_profiles_usage(get_option('lingotek_profiles'));

$profile = isset($_GET['profile']) && array_key_exists($_GET['profile'], $profiles) ? $profiles[$_GET['profile']] : array();
$disabled = isset($profile['profile']) && in_array($profile['profile'], array('automatic', 'manual')) ? 'disabled="disabled"' : '';

// Code to determine which filter scenario will be displayed. (Not configured, defaults, custom filters)
$primary_filter_id = array_search('okf_json@with-html-subfilter.fprm', $settings['primary_filter_id']['options']);
$secondary_filter_id = array_search('okf_html@wordpress.fprm', $settings['secondary_filter_id']['options']);
$default_filters = array($primary_filter_id => 'okf_json@with-html-subfilter.fprm', $secondary_filter_id => 'okf_html@wordpress.fprm');
$default_filters_exist = FALSE;
$extra_filters_exist = FALSE;
$no_filters_exist = FALSE;

if (array_key_exists('default', array_diff_assoc($settings['primary_filter_id']['options'], $default_filters)) && count(array_diff_assoc($settings['primary_filter_id']['options'], $default_filters)) == 1) {
	unset($settings['primary_filter_id']['options']['default']);
}
if ($settings['primary_filter_id']['options'] == $default_filters) {
    $default_filters_exist = TRUE;
    $defaults['primary_filter_id'] = $primary_filter_id;
    $defaults['secondary_filter_id'] = $secondary_filter_id;
    update_option('lingotek_defaults', $defaults);
}
else {
    $num = count(array_diff_assoc($settings['primary_filter_id']['options'], $default_filters));
    if ($num > 0) {
        $extra_filters_exist = TRUE;
    }
    else {
        $defaults['primary_filter_id'] = '';
        $defaults['secondary_filter_id'] = '';
        update_option('lingotek_defaults', $defaults);
        $no_filters_exist = TRUE;
    }
}
unset($settings['primary_filter_id']['options'][$secondary_filter_id]);
unset($settings['secondary_filter_id']['options'][$primary_filter_id]);
?>

<form id="lingotek-edit-profile" method="post" action="admin.php?page=wp-lingotek_manage&sm=profiles" class="validate">
<?php wp_nonce_field('lingotek-edit-profile', '_wpnonce_lingotek-edit-profile');?>
<input name="profile" type="hidden" value="<?php echo empty($profile['profile']) ? '' : esc_attr($profile['profile']); ?>">

<table class="form-table">
	<tr>
		<th scope="row"><?php printf('<label for="%s">%s</label>', 'name' , __('Profile name', 'wp-lingotek')); ?></th>
		<td><?php
		if (!empty($profile)) {
			$profile_name = $disabled ? __($profile['name'],'wp-lingotek') : $profile['name']; // localize canned profile names
		}
		printf('<input name="name" id="name" type="text" value="%s" %s>',
			empty($profile['name']) ? '' : esc_attr($profile_name),
			$disabled
		); ?>
		</td>
	</tr>
</table>

<h3><?php _e('Default settings', 'wp-lingotek'); ?></h3>

<table class="form-table"><?php
	foreach ($settings as $key => $setting) { ?>
	<tr id="<?php echo $key.'_row'?>">
		<th scope="row"><?php printf('<label for="%s">%s</label>', $key , $setting['label']); ?></th>
		<td><?php
			printf('<select name="%1$s" id="%1$s" %2$s>', $key, in_array($key, array('upload', 'download')) ? $disabled : '');
				foreach ($setting['options'] as $id => $title) {
					$selected = isset($profile[$key]) && $profile[$key] == $id ? 'selected="selected"' : '';
					echo "\n\t<option value='" . esc_attr($id) . "' $selected>" . esc_html($title) . '</option>';
				} ?>
			</select><?php

			if ('project_id' == $key) { ?>
				<input type="checkbox" name="update_callback" id="update_callback"/>
				<label for="update_callback"><?php _e('Update the callback url for this project.', 'wp-lingotek') ?></label><?php
			}

			if (isset($setting['description']))
				printf('<p class="description">%s</p>', $setting['description']);?>

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
		</td>
	</tr><?php
	} ?>
</table>

<h3><?php _e('Target languages', 'wp-lingotek'); ?></h3>

<table class="form-table"><?php
	unset($settings['upload']); // we don't want this for target languages
	unset($settings['project_id']); // FIXME disable the possibility to have a different project per target language for now
	// Filters not needed for target languages
	unset($settings['primary_filter_id']);
	unset($settings['secondary_filter_id']);

	foreach ($this->pllm->get_languages_list() as $language) { ?>
	<tr>
		<th scope="row"><?php printf('<label for="%s">%s (%s)</label>', esc_attr($language->slug) , esc_html($language->name), esc_attr($language->locale)); ?><?php

			printf('<a id="%1$s_details_link" %2$s class="dashicons dashicons-arrow-right" onclick="%3$s">%4$s</a>',
				esc_attr($language->slug),
				isset($profile['targets'][$language->slug]) && 'custom' == $profile['targets'][$language->slug] ? '' : 'style="display:none;"',
				"
				d = document.getElementById('{$language->slug}_details');
				if ('none' == d.style.display) {
					d.style.display = '';
					this.className = 'dashicons dashicons-arrow-down';
				}
				else {
					d.style.display = 'none';
					this.className = 'dashicons dashicons-arrow-right';
				}",
				'' //__('Show', 'wp-lingotek')

			); ?></th>
		<td><?php
			printf('<select name="targets[%1$s]" id="targets[%1$s]" onchange="%2$s">',
				esc_attr($language->slug),
				"
				dl = document.getElementById('{$language->slug}_details_link');
				d = document.getElementById('{$language->slug}_details');
				if ('custom' == this.value) {
					d.style.display = dl.style.display = '';
					dl.className = 'dashicons dashicons-arrow-down';
				}
				else {
					d.style.display = dl.style.display = 'none';
					dl.className = 'dashicons dashicons-arrow-right';
				}"
			);
				foreach ($target_settings as $id => $title) {
					$selected = (empty($profile['targets'][$language->slug]) && 'default' == $id) || (isset($profile['targets'][$language->slug]) && $profile['targets'][$language->slug] == $id) ? 'selected="selected"' : '';
					echo "\n\t<option value='" . $id . "' $selected>" . $title . '</option>';
				} ?>
			</select>

		</td>
	</tr>
  <tr id="<?php echo esc_attr($language->slug); ?>_details" style="display:none;">
    <td colspan="2" style="padding:0;">
      <table class="form-table" style="background: #ffffff; margin:0; padding: 10px;"><?php
				foreach ($settings as $key => $setting) {
					$custom_key = 'custom['.$key.']['.esc_attr($language->slug).']'; ?>
					<tr>
						<th scope="row">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?php printf('<label for="%s">%s</label>', $custom_key , $setting['label']); ?></th>
						<td><?php
							printf('<select name="%1$s" id="%1$s" %2$s>', $custom_key, in_array($key, array('upload', 'download')) ? $disabled : '');
								foreach ($setting['options'] as $id => $title) {
									if ($id === 'default' && $key === 'workflow_id') {
										$id = $defaults['workflow_id'];
									}
									$selected = isset($profile['custom'][$key][$language->slug]) && $profile['custom'][$key][$language->slug] == $id ? 'selected="selected"' : '';
									echo "\n\t<option value='" . esc_attr($id) . "' $selected>" . esc_html($title) . '</option>';
								} ?>
							</select><?php
							//if (isset($setting['description']))
								//printf('<p class="description">%s</p>', $setting['description']);?>
						</td>
					</tr><?php
				} ?>
			</table>
    </td>
  </tr>

  <?php
	} ?>
</table><?php submit_button(__('Save Changes', 'wp-lingotek'), 'primary', 'submit', false); ?>

<?php
if (!empty($profile['profile']) && !in_array($profile['profile'], array('automatic', 'manual', 'disabled')) && empty($profile['usage']))
	printf(
		'<a href="%s" class="button" onclick = "return confirm(\'%s\');">%s</a>',
		esc_url(wp_nonce_url('admin.php?page=wp-lingotek_manage&sm=profiles&lingotek_action=delete-profile&noheader=true&profile='.$profile['profile'], 'delete-profile')),
		__('You are about to permanently delete this profile. Are you sure?', 'wp-lingotek'),
		__('Delete', 'wp-lingotek')
	);
?> <a href="admin.php?page=wp-lingotek_manage&amp;sm=profiles" class="button"> <?php _e('Cancel', 'wp-lingotek'); ?></a>
</form>
