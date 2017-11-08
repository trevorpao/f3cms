;(function(app, gee, $){
    'use strict';

    // only for this site
    app.site = {
        init: function () {

            if (app.screen === 'mobile') {
                $('.c-layout-show-filter').show();
                $('.main-nav').addClass('mobile-on');
            }
            else {
                $('.main-nav').removeClass('mobile-on');
                $('.desktop-nav').show();
            }

            $('#article .text img.fr-fin, #article .text img.fr-dib').each(function() {
                var $me = $(this);
                var imageCaption = $me.attr('alt');
                if (imageCaption != '' && imageCaption != 'Image title') {
                    var img = $me.prop('outerHTML');
                    var imgWidth = $me.width();

                    var cap = $('<span class="img-caption ellipsis"><em>' + imageCaption +
                        '</em></span>').css({
                        'width': imgWidth + 'px'
                    }).prop('outerHTML');

                    $me.replaceWith('<div class="img-with-caption">'+ img +''+ cap +'</div>');
                }
            });

            $('#article .text .f-video-editor iframe').each(function() {
                var $me = $(this);
                var imgWidth = $me.width();
                $me.css({
                    'height': Math.round(imgWidth*0.5625) + 'px'
                });
            });

        }
    };

}(app, gee, jQuery));
