(function ($) {
	var devices = [
			{
				name: 'iPhone',
				id: 'iphone',
				width: 320,
				height: 480,
				flexHeight: '93%',
				flexImage: 2
			},
			{
				name: 'iPhone 4/4s',
				width: 320,
				height: 480,
				flexHeight: '93%',
				id: 'iphone4',
				flexImage: 2
			},
			{
				name: 'iPhone 5/5s',
				width: 320,
				height: 564,
				flexHeight: '98%',
				id: 'iphone5',
				flexImage: 3
			},
			{
				name: 'Nexus 4',
				width: 384,
				height: 640,
				flexHeight: '100%',
				id: 'nexus4',
				flexImage: 4
			}
	];
	populateDevicesList = function () {
		var selector = $("#phoneselector");
		$.each(devices, function (index, value) {
			selector.append("<option value='" + value.id + "'>" + value.name + "</option>");
		});
	}
	adjustFooterPosition = function (height) {
		$(".sub").css({ top: height - 90, width: '100%' });
	}

	$(".sub").css({ top: 413 });
	$("#phoneselector").change(function () {
		var selectedVal = $(this).val();
		device = $.grep(devices, function (n, i) { return n.id == selectedVal })[0];
		if (device != null) {
			$(".device-wrapper").animate({ width: device.width + 56 + 'px', height: device.height + 148 + 'px' }, 500);
			adjustFooterPosition(device.height);
			$('.swiper-container').height(device.flexHeight);
			$(".swiper-wrapper, .swiper-slider").width("100%").height("100%");
			$('.flex-container').width('100%').height('100%');
			$('.swiper-slide').width('100%').height('100%');
			$('.story_image').css('flex', device.flexImage + ' 1 auto');
		}
	});
	InitSwiper = function () {
		return new Swiper('.swiper-container', {
			pagination: '.pagination',
			paginationClickable: true,
			mode: 'vertical',
			width: '100%',
			mousewheelControl: true,
			keyboardControl: true,
			touchRatio: 1.0,
			resistance: false,
			speed: 1000,

		});
	}
	populateDevicesList();
	adjustFooterPosition(480);
	var slider = InitSwiper();
})(jQuery); 
