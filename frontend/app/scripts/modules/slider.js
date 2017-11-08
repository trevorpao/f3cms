;(function(app, gee, $){
    'use strict';

    app.slider = {
        target: null,
        setTarget: function (taStr) {
            app.slider.target = $(taStr) || $('#article');
            return app.slider;
        },
        render: function () {
            app.slider.target.find('img.fr-fin, img.fr-dib').each(function () {
                var cu = $(this)[0].outerHTML;
                var imgPath = $(this).attr('src');
                $(this).replaceWith('<a class="slbox" href="'+ imgPath +'">'+ cu +'</a>');
            });

            return app.slider;
        },
        bind: function () {
            app.slider.target.find('a.slbox')
            .on('shown.simplelightbox', function () {
                app.body.addClass('hidden-scroll');
            }).on('closed.simplelightbox', function () {
                app.body.removeClass('hidden-scroll');
            }).simpleLightbox({
                close: false,
                disableScroll: false,
                history: false,
                loop: false,
                showCounter: false
            });

            return app.slider;
        }
    };

    gee.hook('initSlider', function (me) {
        app.slider.setTarget(me.data('ta')).render().bind();
    });

}(app, gee, jQuery));
