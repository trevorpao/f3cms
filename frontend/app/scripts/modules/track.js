;(function(app, gee, $){
    "use strict";

    // how to use
    // <a class="track" href="linkhere" data-cate="my_cate" data-act="my_act">link txt</a>

    app.track = {
        init: function(box) {
            box = box || app.body;

            box.find('.track').on('click', function() {
                var me = $(this);
                // var uri = me.attr('href') || me.data('url') || null;
                // var host = (gee.isset(uri)) ? uri.replace('http://','').replace('https://','').replace('//','').split(/[/?#]/)[0] : null;

                var cate = me.data('cate') || 'normal';
                var act = me.data('act') || 'jump';
                var label = me.data('label') || app.docu.find('title').text();

                // if (host && window.location.host != host) {
                //     cate = 'outbound';
                //     label = uri;
                // }

                if (gee.isset(window.ga)) {
                    window.ga('send', 'event', cate, act, label);
                }
            }).removeClass('track').addClass('tracked');
        }
    };

}(app, gee, jQuery));
