<style>
	.tutorial-photo-right {
		width: 50%;
		height: auto;
		float: right;
		padding-left: 3px;
	}

	.img-caption {
		font-size:  8px;
		color: #999;
		font-style: italic;
		padding-left: 20px;
	}

	th {
		text-align: left;
		padding-left: 10px;
	}
</style>

<p><?php _e('', 'wp-lingotek') ?></p>

<div>
	<h4><?php _e('1. Create content', 'wp-lingotek') ?></h4>
	<p><?php _e('Whether you write a blog post, create a page for your site, or have existing posts and pages, any of your Wordpress content can be uploaded to <i>Lingotek</i>.', 'wp-lingotek') ?>
	<?php _e('The examples shown below are for Pages but translation for other content types works the same way!', 'wp-lingotek') ?></p>
	<img class="lingotek-bordered" src="<?php echo LINGOTEK_URL . '/admin/tutorial/img/add-page.png'; ?>">
	<p class="img-caption"><?php _e('Create a new page for translation.', 'wp-lingotek') ?></p>
</div>
<div>
	<h4><?php _e('2. Upload content to Lingotek', 'wp-lingotek') ?></h4>
	<p><?php _e('Your Wordpress content can be uploaded to <i>Lingotek</i> with the simple push of a button.', 'wp-lingotek') ?></p>
	<img class="lingotek-bordered" src="<?php echo LINGOTEK_URL . '/admin/tutorial/img/ready-to-upload.png'; ?>">
	<p class="img-caption"><?php _e('Content has been created and is ready for upload to Lingotek.', 'wp-lingotek') ?></p>
</div>
<div>
	<h4><?php _e('3. Request translations for target languages', 'wp-lingotek') ?></h4>
	<p><?php _e('Request translation for a specific language by clicking on the orange plus icon, for all languages at once, or in bulk by using the <i>Bulk Actions</i> dropdown.', 'wp-lingotek') ?></p>
		<img class="lingotek-bordered" src="<?php echo LINGOTEK_URL . '/admin/tutorial/img/request-translations.png'; ?>">
	<p class="img-caption"><?php _e('The source content is uploaded and ready for target languages.', 'wp-lingotek') ?></p>
</div>
<div>
	<h4><?php _e('4. Translate your content', 'wp-lingotek') ?></h4>
	<p><?php _e('Your content will now be translated into your selected target languages by free machine translation or, if you contract with <i>Lingotek</i>, professional translation services.', 'wp-lingotek') ?></p>
	<img class="lingotek-bordered" src="<?php echo LINGOTEK_URL . '/admin/tutorial/img/translations-underway.png'; ?>">
	<p class="img-caption"><?php _e('Your translations are underway.', 'wp-lingotek') ?></p>
</div>
<div>
	<h4><?php _e('5. Download translations', 'wp-lingotek') ?></h4>
	<p><?php _e('Once your translations are complete they will be marked ready for download. You can download translations for all languages, each language individually, or in bulk (using the <i>Bulk Actions</i> dropdown).', 'wp-lingotek') ?></p>
	<img class="lingotek-bordered" src="<?php echo LINGOTEK_URL . '/admin/tutorial/img/translations-ready-for-download.png'; ?>">
	<p class="img-caption"><?php _e('Your translations are ready for download.', 'wp-lingotek') ?></p>
</div>
<div>
	<h4><?php _e('6. Your content is translated!', 'wp-lingotek') ?></h4>
	<p><?php _e('The orange pencil icons indicate that your translations are finished, downloaded, and current within your Wordpress site. Clicking on any one of the pencils will direct you to the Lingotek Workbench for that specific language. Here you can make updates and changes to your translations if necessary.', 'wp-lingotek') ?></p>
	<img class="lingotek-bordered" src="<?php echo LINGOTEK_URL . '/admin/tutorial/img/translations-downloaded.png'; ?>">
	<p class="img-caption"><?php _e('Your content has been translated.', 'wp-lingotek') ?></p>
</div>

<h2><?php _e('What do all the icons mean?', 'wp-lingotek') ?></h2>

<table>
	<tr>
		<td><span class="lingotek-color dashicons dashicons-upload"></span></td>
		<th><?php _e('Upload Source', 'wp-lingotek') ?></th>
		<td><?php _e('There is content ready to be uploaded to Lingotek.', 'wp-lingotek') ?></td>
	</tr>
	<tr>
		<td><span class="lingotek-color dashicons dashicons-clock"></span></td>
		<th><?php _e('In Progress', 'wp-lingotek') ?></th>
		<td><?php _e('Content is importing to Lingotek or a target language is being added to source content.', 'wp-lingotek') ?></td>
	</tr>
	<tr>
		<td><span class="lingotek-color dashicons dashicons-yes"></span></td>
		<th><?php _e('Source Uploaded', 'wp-lingotek') ?></th>
		<td><?php _e('The source content has been uploaded to Lingotek.', 'wp-lingotek') ?></td>
	</tr>
	<tr>
		<td><span class="lingotek-color dashicons dashicons-plus"></span></td>
		<th><?php _e('Request Translation', 'wp-lingotek') ?></th>
		<td><?php _e('Request a translation of the source content. (Add a target language)', 'wp-lingotek') ?></td>
	</tr>
	<tr>
		<td><span class="lingotek-color dashicons dashicons-download"></span></td>
		<th><?php _e('Download Translation', 'wp-lingotek') ?></th>
		<td><?php _e('Download the translated content to Wordpress.', 'wp-lingotek') ?></td>
	</tr>
	<tr>
		<td><span class="lingotek-color dashicons dashicons-edit"></span></td>
		<th><?php _e('Translation Current', 'wp-lingotek') ?></th>
		<td><?php _e('The translation is complete. (Clicking on this icon will allow you to edit translations in the Lingotek Workbench)', 'wp-lingotek') ?></td>
	</tr>
</table>