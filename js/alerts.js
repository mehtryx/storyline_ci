function smrt_storyline_alerts_send_update(e) {
	e.preventDefault();
	jQuery.ajax({
		url: ajaxurl,
		type: 'POST',
		data: {
			action: 'smrt_push_ua_update',
			nonce: smrt_alerts.nonce,
			postID: smrt_alerts.postID
		}
	}).success(function(json) {
		console.log(json);
		response = JSON.parse(json);
		smrt_alerts.nonce = response['nonce'];
		
		if(response['result'] === 202) {
			jQuery('#update-status').text('Sent!').css('color', 'green');
		} else {
			jQuery('#update-status').text('Oops! There was a problem!').css('color', 'red');
		}	
	});
}