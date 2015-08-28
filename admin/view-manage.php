<div class="wrap">
  <h2><?php _e('Manage', 'wp-lingotek'); ?></h2>

    <?php

    $menu_items = array(
      'content' => __('Content Type Configuration', 'wp-lingotek'),
      'profiles' => __('Translation Profiles', 'wp-lingotek'),
      'custom-fields' => __('Custom Fields', 'wp-lingotek'),
      'string-groups' => __('String Groups', 'wp-lingotek'),
      'strings' => __('Strings', 'wp-lingotek'),
    );

    ?>

    <h3 class="nav-tab-wrapper">
      <?php
      $menu_item_index = 0;
      foreach ($menu_items as $menu_item_key => $menu_item_label) {
        $use_as_default = ($menu_item_index === 0 && !isset($_GET['sm'])) ? TRUE : FALSE;
        ?>

        <a class="nav-tab <?php if ($use_as_default || (isset($_GET['sm']) && $_GET['sm'] == $menu_item_key)): ?> nav-tab-active<?php endif; ?>"
           href="admin.php?page=<?php echo $_GET['page']; ?>&amp;sm=<?php echo $menu_item_key; ?>"><?php echo $menu_item_label; ?></a>
           <?php
           $menu_item_index++;
         }
         ?>
    </h3>

    <?php
    settings_errors();
    $submenu = isset($_GET['sm']) ? $_GET['sm'] : current(array_keys($menu_items));
    $dir = dirname(__FILE__) . '/manage/';
    $filename = $dir . 'view-' . $submenu . ".php";
    if (file_exists($filename))
      include $filename;
    else
      echo "TO-DO: create <i>" . 'manage/view-' . $submenu . ".php</i>";
    ?>

</div>