function smrt_storyline_alerts_send_update(e) {
	e.preventDefault();
	jQuery.ajax({
		url: ajaxurl,
		type: 'POST',
		data: {
			action: 'smrt_push_ua_update',
			postID: smrt_alerts.postID
		}
	}).success(function(data) {
		console.log(data);
		
		if(data === '202') {
			jQuery('#update-status').text('Sent!').css('color', 'green');
		} else {
			jQuery('#update-status').text('Oops! There was a problem!').css('color', 'red');
		}	
	});
}