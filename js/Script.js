
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
            width: 640,
            height: 960,
            id: 'iphone4'
        },
        {
            name: 'iPhone 5/5s',
            width: 640,
            height: 1136,
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
    console.log(selectedVal);
    device = jQuery.grep(devices, function (n,i) { return n.id ==  selectedVal})[0];
    if (device != null) {
        console.log(device);
        jQuery(".content-slides").animate({ width: device.width +'px', height: device.height + 'px' }, 500);
    }
});



var mySwiper = new Swiper('.swiper-container', {
    pagination: '.pagination',
    paginationClickable: true,
    mode: 'vertical'
});
PopulateDevicesList();
//})(jQuery);