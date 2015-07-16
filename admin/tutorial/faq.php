<style>
	.tutorial-photo-right {
		width: 50%;
		height: auto;
		float: right;
		padding-left: 3px;
	}
</style>

<p class="about-description"><?php _e('Questions and answers...', 'wp-lingotek'); ?></p>

<b><?php _e('How does this really work?', 'wp-lingotek') ?></b>
<p><?php _e('<i>Polylang</i> puts in the framework to make WordPress multilingual and <i>Lingotek</i> leverages that framework to provide localization through machine translation and professional translation services.', 'wp-lingotek') ?></p>

<b><?php _e('What does the Lingotek plugin do?', 'wp-lingotek') ?></b>
<p><?php _e('<i>Lingotek</i> has teamed up with <i>Polylang</i> to offer a simple way to make your WordPress site truly multilingual. Manage all your multilingual content in the same site. No need to have a different site for each language!', 'wp-lingotek') ?></p>

<b><?php _e('How can I add a language?', 'wp-lingotek') ?></b>
<p><?php _e('On the <i>translation dashboard</i>, click on the <i>Translate my site into...</i> textbox and choose from the list or start typing to quickly find a language.', 'wp-lingotek') ?></p>

<b><?php _e('How can I remove a language?', 'wp-lingotek') ?></b>
<p>
	<?php _e('On the <i>translation dashboard</i>, click on the blue check mark for the language you would like to remove.', 'wp-lingotek') ?>
	<i><?php _e('Note: If you have translated content in a language you will not be able to remove that language until that content has been deleted.', 'wp-lingotek') ?></i>
</p>

<b><?php _e("Why can't I upload my existing content to Lingotek?", 'wp-lingotek') ?></b>
<p><?php printf(__("You must assign a language to content that existed before you installed the <i>Polylang</i> and <i>Lingotek</i> plugins. You can do this manually or use the <a href='%s'><i>Lingotek Language Utility</i></a> to set all content that doesn't have an assigned language to the default language. This will allow you to upload your existing content to Lingotek.", 'wp-lingotek'), 'admin.php?page=wp-lingotek_settings&sm=utilities'); ?></p>

<b><?php _e("Can I use my own translation agency with Lingotek?", 'wp-lingotek') ?></b>
<p><?php _e( "Use your own translation agency or tap into Lingotek's network of more than 5,000+ in-country translators. Content transfer is fully automated between WordPress and Lingotek. You'll have full visibility into the translation process every step of the way. And once the translations are completed, they'll automatically download and publish to your website according to the preferences you've set." , 'wp-lingotek'); ?></p>

<b><?php _e('How can I check the overall translation progress of my site?', 'wp-lingotek') ?></b>
<p><?php _e('On the <i>translation dashboard</i>, the bars under <i>Completed Percentage</i> show the translation progress of content for each language. You can filter by content type or show progress for all content.', 'wp-lingotek') ?></p>
<ul>
	<li>
		<b><?php _e('Why are there two different shades of blue in the progress bar?', 'wp-lingotek') ?></b>
		<p><?php _e('The <i>translation dashboard</i> not only shows how much of your content is translated, but also indicates the language that your source content was authored in.', 'wp-lingotek') ?></p>
	</li>
	<ul style="list-style-type:disc; padding-left:30px">
		<li><?php _e('<i>Dark Blue:</i> Indicates that this is a source language that the content was authored in.', 'wp-lingotek') ?></li>
		<li><?php _e('<i>Light Blue:</i> Indicates that this is a target language that the content was translated into.', 'wp-lingotek') ?></li>
	</ul>
</ul>

<b><?php _e('What happens when I <i>Disassociate Translations</i>?', 'wp-lingotek') ?></b>
<p><?php _e('When content is disassociated, the connection between WordPress and <i>Lingotek</i> is safely removed so that translations can be solely managed inside of WordPress.', 'wp-lingotek') ?></p>

<b><?php _e("How do I translate strings within widgets or general WordPress settings (e.g., Site Title, Tagline)?", 'wp-lingotek') ?></b>
<p><?php  printf(__( "Groups of strings can be sent for translation and managed using the <a href='%s'>String Groups</a> page. Individual strings may be viewed on the <a href='%s'>Strings</a> page." , 'wp-lingotek'),'admin.php?page=wp-lingotek_manage&sm=string-groups','admin.php?page=wp-lingotek_manage&sm=strings'); ?></p>


<div class="changelog feature-section two-col">
	<div>
		<img class="workbench-image" src="<?php echo LINGOTEK_URL . '/admin/tutorial/img/workbench-full.png'; ?>">
		<h3><?php _e( 'Lingotek Workbench', 'wp-lingotek'); ?></h3>
		<p><?php _e( 'Lingotek Translation is the only WordPress plugin to integrate a Translation Management System directly into WordPress, thus allowing the WordPress community to use professional-grade translation technologies (e.g. machine translation, translation memory, CAT tool) without ever having to leave the comfort of the WordPress environment.', 'wp-lingotek'); ?></p>
	</div>
	<div class="last-feature">
		<a href="admin.php?page=wp-lingotek"><img src="<?php echo LINGOTEK_URL . '/admin/tutorial/img/dashboard.png'; ?>"></a>
		<h3><?php _e( 'Translation Dashboard', 'wp-lingotek'); ?></h3>
		<p><?php _e( "The <i>translation dashboard</i> allows you to view your site languages, add and remove new languages, check the overall translation progress of your site, see site analytics, and connect with Lingotek support.", 'wp-lingotek'); ?></p>
	</div>
</div>



