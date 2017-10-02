;(function(app, gee, $){
    'use strict';

    // register a module name
    app.kano = {
        issues: [],

        init: function () {
            app.loadHtml('kano/edit-project', 'main-box');

            app.waitFor(function () {
                return $('#sortable').length;
            }).then(function () {
                $('#sortable').sortable();
                $('#sortable').disableSelection();

                app.kano.render();
            });
        },

        render: function () {
            app.renderBox($('#sortable') , {'data': app.kano.issues}, 1);
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
