<?php

global $polylang;

$items = array();

if (!empty($_POST)) {
  check_admin_referer('lingotek-custom-fields', '_wpnonce_lingotek-custom-fields');

  if (!empty($_POST['settings'])) {
    update_option('lingotek_custom_fields', $_POST['settings']);
  }

  if (!empty($_POST['refresh'])) {
    $items = Lingotek_Group_Post::get_updated_meta_values();
  }
}

$items = Lingotek_Group_Post::get_cached_meta_values();

?>

<h3><?php _e('Custom Field Configuration', 'wp-lingotek'); ?></h3>
<p class="description"><?php _e('Custom Fields can be translated, copied, or ignored.', 'wp-lingotek'); ?></p>

<form id="lingotek-custom-fields" method="post" action="admin.php?page=wp-lingotek_settings&amp;sm=custom-fields" class="validate"><?php
wp_nonce_field('lingotek-custom-fields', '_wpnonce_lingotek-custom-fields');

$table = new Lingotek_Custom_Fields_Table();
$table->prepare_items($items);
$table->display();
?>

  <p>
    <?php submit_button(__('Save Changes', 'wp-lingotek'), 'primary', 'submit', false); ?>
    <?php submit_button(__( 'Refresh Custom Fields', 'wp-lingotek'), 'secondary', 'refresh', false ); ?>
  </p>
</form>