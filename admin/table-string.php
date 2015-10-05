<?php

/*
 * extends the Polylang class to disable the input fields
 *
 * @since 0.3
 */
class Lingotek_Table_String extends PLL_Table_String {
	/*
	 * displays the translations to edit (disabled)
	 *
	 * @since 0.3
	 *
	 * @param array $item
	 * @return string
	 */
	function column_translations($item) {
		$out = '';
		foreach($item['translations'] as $key => $translation) {
			$input_type = $item['multiline'] ?
				'<textarea name="translation[%1$s][%2$s]" id="%1$s-%2$s" disabled="disabled">%4$s</textarea>' :
				'<input type="text" name="translation[%1$s][%2$s]" id="%1$s-%2$s" value="%4$s" disabled="disabled" />';
			$out .= sprintf('<div class="translation"><label for="%1$s-%2$s">%3$s</label>'.$input_type.'</div>'."\n",
				esc_attr($key),
				esc_attr($item['row']),
				esc_html($this->languages[$key]),
				format_to_edit($translation)); // don't interpret special chars
		}
		return $out;
	}
}
