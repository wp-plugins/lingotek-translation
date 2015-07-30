jQuery(document).ready(function($) {
	function lingotek_progress(i) {
		if (i < lingotek_data.ids.length) {
			var data = {
				action: 'lingotek_progress_'+lingotek_data.action,
				taxonomy: lingotek_data.taxonomy, // empty for posts
				id: lingotek_data.ids[i],
				_lingotek_nonce: lingotek_data.nonce
			}

			$.post(ajaxurl, data , function(response) {
				$("#lingotek-progressbar").progressbar({
					value: ++i / lingotek_data.ids.length * 100
				});
				lingotek_progress(i);
			});
		}

		else
			jQuery(location).attr('href', lingotek_data.sendback);
	}

	if ('undefined' != typeof(lingotek_data)) {
		if ('' == lingotek_data.warning || confirm(lingotek_data.warning)) {
			var d = $("#lingotek-progressdialog");
			if (d.length) {
				d.dialog({
					dialogClass: "wp-dialog",
					width: 400
				});
				$("#lingotek-progressbar").progressbar();
				lingotek_progress(0);
			}
		}
		else
			jQuery(location).attr('href', lingotek_data.sendback);
	}
});
