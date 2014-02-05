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
			jQuery('#check-update').prop('checked', true);
		} else {
			jQuery('#update-status').text('Oops! There was a problem!').css('color', 'red');
		}	
	});
}

function smrt_storyline_alerts_check_update(e) {
	jQuery.ajax({
		url: ajaxurl,
		type: 'POST',
		data: {
			action: 'smrt_alert_check_update',
			nonce: smrt_alerts.nonce,
			postID: smrt_alerts.postID
		}
	}).success(function(json) {
		console.log(json);
		response = JSON.parse(json);
		smrt_alerts.nonce = response['nonce'];
		
		if(response['result'] === 'true') {
			jQuery('#check-update').prop('checked', true);
		} else {
			jQuery('#check-update').prop('checked', false);
		}
	});
}