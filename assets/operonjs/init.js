/**
 * app init
 */

document.addEventListener('DOMContentLoaded', function(){
    var str = $('.typed-text').data('typed');
    var speed = $('.typed-text').data('speed')*1;
    if (str) {
        var ary = str.split(';');
        gee.clog(ary);
        // Typed.new('.typed-text', {
        //     strings: ary,
        //     startDelay: 1200,
        //     typeSpeed: 90
        // });

        var box = $('.banner-title2');
        var html = '';
        var loopIdx = 0;
        var loopFadeIn = function(){
            box.find('.item:eq('+ loopIdx%ary.length +')').animateCss('fadeInUp', loopFadeOut);
        };
        var loopFadeOut = function(){
            setTimeout(fadeout, (speed - 3) * 1000);
        };
        var fadeout = function () {
            box.find('.item:eq('+ loopIdx%ary.length +')').animateCss('fadeOutUp', resetCurrent);
        };
        var resetCurrent = function() {
            box.find('.item').removeClass('current');
        };
        $.each(ary, function(idx){
            html += '<span class="item">'+ this +'</span>';
        });

        box.append(html).find('.item:eq('+ loopIdx%ary.length +')').addClass('current').animateCss('fadeInUp', loopFadeOut);

        setInterval(function () {
            loopIdx++;
            box.find('.item:eq('+ loopIdx%ary.length +')').addClass('current').animateCss('fadeInUp', loopFadeOut);
        }, speed * 1000)
    }
});

$(function () {
    "use strict";

    app.init();
    app.page.init();
    app.lang.init();
});
