<div class="wrap">
  <h2><?php _e('Dashboard', 'wp-lingotek'); ?></h2>
  <script>
    var cms_data = <?php echo json_encode($cms_data); ?>
  </script>
  <link rel="stylesheet" href="https://gmc.lingotek.com/v2/styles/ltk.css">
  <script src="https://gmc.lingotek.com/v2/ltk.min.js"></script>
  <div ltk-dashboard ng-app="LingotekApp" style="margin-top: -15px;"></div>
</div>
