<div class="wrap">
  <h2><?php _e('Settings', 'wp-lingotek'); ?></h2>

  <?php
  if (strlen($access_token)) {
    ?>

    <?php

    $menu_items = array(
      'account' => __('Account', 'wp-lingotek'),
    );

    $community_required_menu_items = array(
      'defaults' => __('Defaults', 'wp-lingotek'),
      'preferences' => __('Preferences', 'wp-lingotek'),
      //'advanced' => __('Advanced', 'wp-lingotek'),
      //'logging' => __('Logging', 'wp-lingotek'),
      'utilities' => __('Utilities', 'wp-lingotek'),
    );

    if($community_id !== FALSE){
      $menu_items = array_merge($menu_items, $community_required_menu_items);
    }

    ?>

    <h3 class="nav-tab-wrapper">
      <?php
      $menu_item_index = 0;
      foreach ($menu_items as $menu_item_key => $menu_item_label) {
        $use_as_default = ($menu_item_index === 0 && !isset($_GET['sm'])) ? TRUE : FALSE;
        $alias = NULL;
        // custom sub sub-menus
        if(isset($_GET['sm']) && $_GET['sm'] == "edit-profile") {
          $alias = "profiles";
        }
        ?>

        <a class="nav-tab <?php if ($use_as_default || (isset($_GET['sm']) && $_GET['sm'] == $menu_item_key) || $alias == $menu_item_key): ?> nav-tab-active<?php endif; ?>"
           href="admin.php?page=<?php echo $_GET['page']; ?>&amp;sm=<?php echo $menu_item_key; ?>"><?php echo $menu_item_label; ?></a>
           <?php
           $menu_item_index++;
         }
         ?>
    </h3>

    <?php
    settings_errors();
    $submenu = isset($_GET['sm']) ? $_GET['sm'] : 'account';
    $dir = dirname(__FILE__) . '/settings/';
    $filename = $dir . 'view-' . $submenu . ".php";
    if (file_exists($filename))
      include $filename;
    else
      echo "TO-DO: create <i>" . 'settings/view-' . $submenu . ".php</i>";
    ?>

    <?php
  }
  ?>
</div>
