jQuery(document).ready(function($) {

	var checkboxes = jQuery('[name="topic_enabled"]');

	checkboxes.on('click', function(e) {
		var termId = parseInt($(this).attr('id')),
			data = {
				'action': 'toggle_topic',
				'term_id': termId,
				'enabled': ($(this).attr('checked'))? 1 : 0
			};

		$.post(ajaxurl, data, function(response) {
        	var term_group = $('#tag-' + termId + ' > .term_group');
        	$(term_group).text(response);
		});
	});
});