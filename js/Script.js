
//(function ($) {

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
        }
];

function PopulateDevicesList() {
    var selector = jQuery("#phoneselector");
    jQuery.each(devices, function (index, value) {
        selector.append("<option value='" + value.id + "'>" + value.name + "</option>");
    });
    
}

jQuery("#phoneselector").change(function () {
    var selectedVal = jQuery(this).val();
    device = jQuery.grep(devices, function (n,i) { return n.id ==  selectedVal})[0];
    if (device != null) {
        jQuery(".device-wrapper").animate({ width: device.width + 56 +'px', height: device.height + 148 + 'px' }, 500);
    }
});



var mySwiper = new Swiper('.swiper-container', {
    pagination: '.pagination',
    paginationClickable: true,
    mode: 'vertical'
});
PopulateDevicesList();
//})(jQuery);