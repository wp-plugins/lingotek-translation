<?php

  $menu_items = array(
    'features' => __("Features", 'wp-lingotek'),
    'content' => __('Tutorial', 'wp-lingotek'),
    'faq' => __('FAQ', 'wp-lingotek'),
    'credits' => __('Credits', 'wp-lingotek'),
  );

?>


<div class="wrap about-wrap">

<h1><?php printf( __( 'Welcome to Lingotek' , 'wp-lingotek') ); ?></h1>

<div class="about-text"><?php printf( __( 'Thank you for updating! Lingotek offers convenient cloud-based localization and translation.' , 'wp-lingotek'), LINGOTEK_VERSION ); ?></div>


<div class="wp-badge" style="background: url(<?php echo LINGOTEK_URL ?>/img/lingotek-chevrons-blue.png) center 24px/85px 80px no-repeat #fff; color: #666;"><?php printf( __( 'Version %s' , 'wp-lingotek'), LINGOTEK_VERSION ); ?></div>

<h2 class="nav-tab-wrapper">
  <?php
  $menu_item_index = 0;
  foreach ($menu_items as $menu_item_key => $menu_item_label) {
    $use_as_default = ($menu_item_index === 0 && !isset($_GET['sm'])) ? TRUE : FALSE;
    ?>

    <a class="nav-tab <?php if ($use_as_default || (isset($_GET['sm']) && $_GET['sm'] == $menu_item_key)): ?> nav-tab-active<?php endif; ?>"
       href="admin.php?page=<?php echo $_GET['page']; ?>&sm=<?php echo $menu_item_key; ?>"><?php echo $menu_item_label; ?></a>
       <?php
       $menu_item_index++;
     }
  ?>
</h2>


<?php
    settings_errors();
    $submenu = isset($_GET['sm']) ? $_GET['sm'] : current(array_keys($menu_items));
    $dir = dirname(__FILE__) . '/tutorial/';
    $filename = $dir . $submenu . ".php";
    if (file_exists($filename))
      include $filename;
    else
      echo "TO-DO: create <i>" . 'tutorial/' . $submenu . ".php</i>";
?>

</div>