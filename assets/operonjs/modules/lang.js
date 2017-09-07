;(function(app, gee, $){
    "use strict";

   var cfg = {
        tw: {
        },
        en: {
        }
    };

    app.lang = {
        default: 'tw',
        cu: 'tw',
        opt: [
            {k: 'tw', v: '中'},
            {k: 'en', v: 'EN'}
        ],
        init: function() {
            app.lang.cu = ( ($('#lang').val()) ? $('#lang').val() : ( getCookie('cuLang') === null ? app.lang.default : getCookie('cuLang') ) );
            app.lang.set(app.lang.cu);
            app.body.addClass(app.lang.cu);

            var root = document.documentElement;
            root.setAttribute('class', app.lang.cu);
        },
        set: function(lang) {
            var p = 0;
            $.each(app.lang.opt, function(idx, row){
                if (row.k === lang) {
                    p = idx;
                }
            });
            app.lang.point(p);
        },
        next: function() {
            var p = 0;
            $.each(app.lang.opt, function(idx, row){
                if (row.k === app.lang.cu) {
                    p = idx + 1;
                    if (p >= app.lang.opt.length ) {
                        p = 0;
                    }
                }
            });
            app.lang.point(p);
        },
        point: function (idx) {
            app.lang.cu = app.lang.opt[idx].k;
            $('body').removeClass('tw en').addClass(app.lang.cu);

            var root = document.documentElement;
            root.setAttribute('class', app.lang.cu);

            setCookie('cuLang', app.lang.cu, 7);

            $('.lang-text').text(app.lang.opt[idx].v);
        },
        get: function (idx) {
            return (cfg[app.lang.cu][idx]) ? cfg[app.lang.cu][idx] : idx;
        },
        switch: function() {
            $('[lang-key]').each(function(){
                var ta = $(this);
                var key = ta.attr('lang-key');
                var gotit = 0;
                $.each(cfg[app.lang.cu], function(idx, val){
                    if (idx === key) {
                        ta.text(val);
                        gotit = 1;
                    }
                });
                if (gotit === 0) {
                    ta.text(key);
                }
            });
        }
    };

    // change lang
    gee.hook('nextLang', function(me){
        app.lang.next();
    });

    // change lang & redirect
    gee.hook('switchI18n', function (me) {
        var uUri = $('link[rel="canonical"]').attr('href');
        var nUri = '';
        var old = app.lang.cu;

        app.lang.cu = me.data('ta');
        setCookie('cuLang', app.lang.cu, 7);

        nUri = uUri.replace('/'+ old, '/'+ app.lang.cu);

        if (nUri === uUri) {
            if (nUri.indexOf('/'+ app.lang.cu) == -1) {
                if(nUri.substr(-1) === '/') {
                    nUri = nUri.substr(0, nUri.length - 1);
                }
                nUri = nUri + '/'+ app.lang.cu;
            }
        }

        location.href = nUri;
    });

}(app, gee, jQuery));
