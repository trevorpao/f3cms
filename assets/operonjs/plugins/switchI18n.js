/**
 * 多語系切換按鈕
 */
;(function(app, gee, $) {
    'use strict';
    gee.hook('switchI18n', function (me) {
        var uUri = $('link[rel="canonical"]').attr('href');
        var nUri = '';
        var lang = me.data('ta');
        var cu = $('#lang').val();

        nUri = uUri.replace('/'+ cu, '/'+ lang);

        if (nUri === uUri) {
            if (nUri.indexOf('/'+ lang) == -1) {
                if(nUri.substr(-1) === '/') {
                    nUri = nUri.substr(0, nUri.length - 1);
                }
                nUri = nUri + '/'+ lang;
            }
        }

        location.href = nUri;
    });
}(app, gee, jQuery));
