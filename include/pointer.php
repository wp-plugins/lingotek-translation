<?php
// code borrowed from Polylang
class Lingotek_Pointer {
	protected $args;

	/**
	 * Constructor
	 * Enqueues the pointer script
	 *
	 * List of parameters accepted in $args:
	 *
	 * pointer   => required, unique identifier of the pointer
	 * id        => required, the pointer will be attached to this html id
	 * position  => optional array, if used both sub parameters are required
	 * 	 edge    => 'top' or 'bottom'
	 * 	 align   => 'right' or 'left'
	 * width     => optional, the width in px
	 * title     => required, title
	 * content   => required, content
	 * buttons   => optional array of arrays, by default the pointer uses the standard dismiss button offered by WP
	 * 	   label => the label of the button
	 * 	   link  => optional link for the button. By default, the button just dismisses the pointer
	 *
	 * @since 1.0.1
	 *
	 * @param array $args
	 */
	public function __construct($args) {
		$this->args = $args;
		add_action('admin_enqueue_scripts', array(&$this, 'enqueue_scripts'));
	}

	/**
	 * Enqueue JavaScript and styles if the pointer has not been dismissed
	 *
	 * @since 1.0.1
	 */
	public function enqueue_scripts() {
		$dismissed = explode(',', get_user_meta(get_current_user_id(), 'dismissed_wp_pointers', true));

		// comment the two lines below to make the pointer non dismissable
		if (in_array($this->args['pointer'], $dismissed) || !current_user_can('manage_options'))
			return;

		// Add pointer JavaScript
		add_action('admin_print_footer_scripts', array(&$this, 'print_js'));

		wp_enqueue_style('wp-pointer');
		wp_enqueue_script('wp-pointer');
	}

	/**
	 * Adds the JavaScript of our pointer to the page
	 *
	 * @since 1.0.1
	 */
	public function print_js() {

		// add optional buttons
		if (!empty($this->args['buttons'])) {
			$b = "
				var widget = pointer.pointer('widget');
				var buttons = $('.wp-pointer-buttons', widget);
				$('a.close', widget).remove();"; // removes the WP button

			// all the buttons use the standard WP ajax action to remember the pointer has been dismissed
			foreach ($this->args['buttons'] as $button) {
				$b .= sprintf("
					$('<a>').addClass('%s').html('%s').css('margin-left', '10px').click(function() {
						$.post(ajaxurl, {
							pointer: '%s',
							action: 'dismiss-wp-pointer'
						}, function(response) {
							%s
						});
					}).appendTo(buttons);",
					empty($button['link']) ? 'button' : 'button button-primary',
					$button['label'],
					$this->args['pointer'],
					empty($button['link']) ? "pointer.pointer('close')" : sprintf("location.href = '%s'", $button['link'])
				);
			}
		}

		$js = sprintf("
			//<![CDATA[
			jQuery(document).ready(function($) {
				var pointer = $('#%s').pointer({
					content: '%s',
					%s
					%s
				});
				pointer.pointer('open');
				%s
			});
			// ]]>",
			$this->args['id'],
			sprintf('<h3>%s</h3><p>%s</p>', $this->args['title'], $this->args['content']),
			empty($this->args['position']) ? '' : sprintf('position: {edge: "%s", align: "%s",},', $this->args['position']['edge'], $this->args['position']['align']),
			empty($this->args['width']) ? '' : sprintf('pointerWidth: %d,', $this->args['width']),
			empty($b) ? '' : $b
		);
		echo "<script type='text/javascript'>" .$js. "</script>";
	}
}

?>