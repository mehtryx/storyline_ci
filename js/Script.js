
(function ($) {
    var devices = [
            {
                name: 'iPhone',
                id: 'iphone',
                width: 320,
                height: 480
            },
            {
                name: 'iPhone 4/4s',
                width: 320,
                height: 480,
                id: 'iphone4'
            },
            {
                name: 'iPhone 5/5s',
                width: 320,
                height: 564,
                id: 'iphone5'
            },
            {
                name: 'Nexus 4',
                width: 384,
                height: 640,
                id: 'nexus4'
            }
    ];
    var storyItemHeight = 480;
    var storyItemWidth = 320;
    function PopulateDevicesList() {
        var selector = $("#phoneselector");
        $.each(devices, function (index, value) {
            selector.append("<option value='" + value.id + "'>" + value.name + "</option>");
        });
        
    }
    function adjustFooterPosition (height) {
        $(".adBanner").css({top:height-495, width: '100%'});
        $(".statusbar").css({top:height-135, width: '100%'});
        $(".sub").css({top:height-90, width: '100%'});
    }
    new Swiper('.swiper-container', {
        pagination: '.pagination',
        paginationClickable: true,
        mode: 'vertical',
        width: '100%',
    });
    $(".sub").css({ top:  413});
    $("#phoneselector").change(function () {
        var selectedVal = $(this).val();
        device = $.grep(devices, function (n,i) { return n.id ==  selectedVal})[0];
        if (device != null) {
            $(".device-wrapper").animate({ width: device.width + 56 +'px', height: device.height + 148 + 'px' }, 500);
            adjustFooterPosition(device.height);
            new Swiper('.swiper-container', {
                pagination: '.pagination',
                paginationClickable: true,
                mode: 'vertical'
            });
        }
    });



    
    PopulateDevicesList();
    adjustFooterPosition(480);
})(jQuery);