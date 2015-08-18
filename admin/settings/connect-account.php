<?php /* Redirect Access Token */ ?>
<script>
  var hash = window.location.hash;
  if (hash.length && hash.indexOf("access_token") !== -1) {
    var parts = hash.substring(1).split('=');
    var access_token = parts[1];
    var url_with_access_token = window.location.origin + window.location.pathname + window.location.search + '&access_token=' + access_token;
    window.location.href = url_with_access_token;
  }
  else if (window.location.search.indexOf("connect") != -1) {
    window.location.href = "<?php echo $connect_url ?>";
  }
</script>
<?php /* Connect Your Account Button */ ?>
<div class="wrap">
  <h2><?php _e('Connect Your Account', 'wp-lingotek') ?></h2>
  <div>
  <p class="description">
    <?php _e('Get started by clicking the button below to connect your Lingotek account to this Wordpress installation.', 'wp-lingotek') ?>
  </p>
  <hr/>
  <p>
    <a class="button button-large button-hero" href="<?php echo $connect_account_cloak_url_new ?>">
      <img src="<?php echo LINGOTEK_URL; ?>/img/lingotek-icon.png" style="padding: 0 4px 2px 0;" align="absmiddle"/> <?php _e('Connect New Account', 'wp-lingotek') ?>
    </a>
  </p>
  <hr/>
  <p class="description"><?php echo sprintf( __('Do you already have a Lingotek account? <a href="%s">Connect Lingotek Account</a>', 'wp-lingotek'), esc_attr($connect_account_cloak_url_prod)) ?></p>
  <p class="description"><?php echo sprintf( __('Do you have a Lingotek sandbox account? <a href="%s">Connect Sandbox Account</a>', 'wp-lingotek'), esc_attr($connect_account_cloak_url_test)) ?></p>
  </div>
</div>