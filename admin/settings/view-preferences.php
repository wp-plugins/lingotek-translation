<?php

$setting_details = array(
  'download_post_status' => array(
    'type' => 'dropdown',
    'label' => __('Download translation status', 'wp-lingotek'),
    'description' => __('The post status for newly downloaded translations', 'wp-lingotek'),
    'values' => array(
      Lingotek_Group_Post::SAME_AS_SOURCE => __('Same as source post', 'wp-lingotek'),
      'draft' => __('Draft', 'wp-lingotek'),
      'pending' => __('Pending Review', 'wp-lingotek'),
      'publish' => __('Published', 'wp-lingotek'),
      //'future' => __('Scheduled', 'wp-lingotek'),
      'private' => __('Privately Published', 'wp-lingotek'),
    )),
  'auto_upload_post_statuses' => array( // blacklist
    'type' => 'checkboxes',
    'label' => __('Auto upload statuses', 'wp-lingotek'),
    'description' => __('The post statuses checked above are enabled for automatic upload (when using automatic uploading translation profiles).', 'wp-lingotek'),
    'values' => array(
      'draft' => __('Draft', 'wp-lingotek'),
      'pending' => __('Pending Review', 'wp-lingotek'),
      'publish' => __('Published', 'wp-lingotek'),
      'future' => __('Scheduled', 'wp-lingotek'),
      'private' => __('Privately Published', 'wp-lingotek'),
    )
  ),
  'delete_document_from_tms' => array(
    'type' => 'checkboxes',
    'label' => __('Disassociation', 'wp-lingotek'),
    'description' => __('Your documents will remain in your WordPress site but will be deleted from the Lingotek TMS if this option is checked.', 'wp-lingotek'),
    'values' => array(
      'delete' => __('Delete documents from Lingotek TMS when disassociating.', 'wp-lingotek'),
    )
  ),
);

$page_key = $this->plugin_slug . '_settings&sm=preferences';

if (!empty($_POST)) {
  check_admin_referer($page_key, '_wpnonce_' . $page_key);
  $options = array();
  foreach ($setting_details as $key => $setting) {
    if (isset($_POST[$key])) {
      $options[$key] = $_POST[$key];
    }
    else {
      $options[$key] = null;
    }
  }
  update_option('lingotek_prefs', $options);

  add_settings_error('lingotek_prefs', 'prefs', __('Your preferences were successfully updated.', 'wp-lingotek'), 'updated');
  settings_errors();
}

$selected_options = Lingotek_Model::get_prefs();

?>

<h3><?php _e('Preferences', 'wp-lingotek'); ?></h3>
<p class="description"><?php _e('These are your preferred settings.', 'wp-lingotek'); ?></p>


<form id="lingotek-settings" method="post" action="admin.php?page=<?php echo $page_key; ?>" class="validate">
<?php wp_nonce_field($page_key, '_wpnonce_' . $page_key); ?>

  <table class="form-table"><?php foreach ($setting_details as $key => $setting) { ?>

      <tr>
        <th scope="row"><label for="<?php echo $key; ?>"><?php echo $setting['label'] ?></label></th>
        <td>
          <?php if ($setting['type'] == 'dropdown') { ?>
          <select name="<?php echo $key ?>" id="<?php echo $key ?>">
            <?php
            foreach ($setting['values'] as $id => $title) {
              echo "\n\t" . '<option value="' . esc_attr($id) . '" ' . selected($selected_options[$key], $id) . '>' . $title . '</option>';
            }
            ?>
            </select>
          <?php } else if ($setting['type'] == 'checkboxes') {
            echo '<ul class="pref-statuses">';
            foreach ($setting['values'] as $id => $title) {
              $cb_name = $key.'['.esc_attr($id) . ']';
              $checked = checked('1', (isset($selected_options[$key][$id]) && $selected_options[$key][$id]), false);
              echo '<li><input type="checkbox" id="'.$cb_name.'" name="'.$cb_name.'" value="1" ' . $checked. '><label for="'.$cb_name.'">' . $title . '</label></li>';
            }
            echo '</ul>';
          } ?>
          <p class="description">
            <?php echo $setting['description']; ?>
          </p>
      </tr><?php } ?>
  </table>

<?php submit_button(__('Save Changes', 'wp-lingotek'), 'primary', 'submit', false); ?>
</form>
