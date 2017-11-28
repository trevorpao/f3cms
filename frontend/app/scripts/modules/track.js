;(function(app, gee, $){
    'use strict';

    // how to use
    // <a class="track" href="linkhere" data-cate="my_cate" data-act="my_act">link txt</a>

    app.track = {
        init: function () {
            app.track.bind(app.body);
        },

        bind: function (box) {
            box.find('.track').on('click', function () {
                var me = $(this);

                var cate = me.data('cate') || 'normal';
                var act = me.data('act') || 'jump';
                var label = me.data('label') || me.attr('title') || app.docu.find('title').text();
                var which = me.data('which');

                app.track.send(cate, act, label, which);
            }).removeClass('track').addClass('tracked');
        },

        send: function (cate, act, label, which) {
            which = (which) ? which : 'ga';

            gee.clog({
                cate: cate,
                act: act,
                label: label,
                which: which,
            });

            switch (which) {
                case 'ga':
                    // Google analytics
                    if (gee.isset(window.ga)) {
                        if (label) {
                            window.ga('event', cate, act, label);
                        } else {
                            window.ga('event', cate, act);
                        }
                    }
                    break;

                case 'pixel':
                    // Facebook Pixel
                    if (gee.isset(window.fbq)) {
                        window.fbq('track', cate, act, label);
                    }
                    break;

                default:
                    return;
            }
        }
    };

}(app, gee, jQuery));
