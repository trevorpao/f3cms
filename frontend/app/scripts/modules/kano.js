;(function(app, gee, $){
    'use strict';

    app.kano = {
        box: null,
        issues: [],

        init: function () {
        },

        render: function () {
            app.renderBox(app.kano.box , {'data': app.kano.issues}, 1);
        }
    };

    gee.hook('addIssue', function (me) {
        var form = $('#new-issue');
        var issue = form.find('input[name="issue"]').val();
        var info = form.find('textarea[name="info"]').val();

        if (issue && info) {
            $('#new-issue')[0].reset();
            app.kano.issues.push({issue: issue, info: info});
            app.kano.render();
        }
        else {
            alert('題目及描述為必填');
        }
    });

    gee.hook('initKano', function (me) {
        var type = me.data('type');
        switch(type){
            case 'editor':
            app.kano.box = $('#issue-list');
            app.kano.box.sortable();
            app.kano.box.disableSelection();
            // gee.clog(app.kano.box);
            // app.kano.render();
            break;
        }
    });

    /**
     * reaction of Calculator
     */
    gee.hook('reactKano', function(me) {
        var ta = $(me.event.target);
        var func = ta.attr('func');

        if (gee.check(func)) {
            ta.event = me.event;
            gee.exe(func, ta);
        }
    });

}(app, gee, jQuery));
