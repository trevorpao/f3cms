/**
 * hook base events
 */

;(function(app, gee, $) {
    'use strict';

    gee.hook('loadMain', function(me) {
        var type = me.data('type');

        app.loadHtml(type, 'main-box', 1);
    });

    gee.hook('loadBox', function(me) {
        var src = me.data('src');

        app.loadHtml(src, me);
    });

    gee.hook('loadModal', function(me) {
        var type = me.data('type');
        var width = me.data('width') || 'std';

        app.loadHtml('modal/' + type, width + '-modal-box');

        $('#' + width + '-modalLabel').text(type);
        $('#' + width + '-modal').modal('show');
    });

    gee.hook('paddingZero', function(me){
        var num = me.val();
        var length = me.data('length') || 2; // defaults to 2 if no parameter is passed
        var newStr = (new Array(length).join('0') + num).slice(length * -1);
        me.val(newStr);
        return newStr;
    });

    gee.hook('jump2', function(me){
        var max = me.attr('maxlength')*1 || 2;
        var num = (me.val()).length;
        var ta = $(me.data('ta')) || me.next('input');
        if (num === max) {
            ta.focus();
        }
    });

    gee.hook('selAll', function (me) {
        var ta = $(me.data('ta') || 'checkbox');
        gee.clog('selAll');
        if(me.prop('checked')) {
            ta.prop('checked', true);
        }
        else {
            ta.prop('checked', false);
        }
    });

    gee.hook('reExe', function(me) {
        if (app.redo) {
            var f = app.redo.split('.');
            if (typeof app[f[0]][f[1]] === 'function') {
                app[f[0]][f[1]].call(this);
                app.redo = null;
            } else {
                location.reload();
            }
        }
    });

    gee.hook('initPagination', function(me) {
        var params = me.data();
        app.pageLimit = (params.length) ? params.length : app.pageLimit;

        app.setPaginate(params.total);
    }, 'init');

}(app, gee, jQuery));
