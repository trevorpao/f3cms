/**
 * 發信
 */
;
(function (app, gee, $) {
    'use strict';
    app.edm = {
        box: null,
        slug: '',
        limit: 10,
        delay: 5,
        init: function () {
            app.edm.setCurrent();
        },

        setCurrent: function () {
            app.edm.slug = window.location.pathname.split('/')[3];
        },

        loadEmails: function ($elem) {
            app.edm.box = $elem;
            var callback = function () {
                if (!this.code || this.code !== '1') {
                    app.stdErr(this);
                } else {
                    app.edm.renderList(this.data);
                }
            };

            gee.yell('/subscription/load_all', {}, callback, callback);
        },

        renderList: function (data) {
            var tmpl = app.edm.box.data('tmpl');

            if (typeof app.tmplStores[tmpl] === 'undefined') {
                app.tmplStores[tmpl] = $.templates(app.edm.box.html());
            }

            app.renderBox(app.edm.box, data, 1);
            $('.pre-gee').addClass('gee');

            var tabs = $.templates($('.nav-tabs').html());
            $('.nav-tabs').html(tabs.render(data));



            gee.init();

            $('.nav-tabs li:eq(0)').addClass('active');
            $('.tab-content .tab-pane:eq(0)').addClass('active');
        },

        loopList: function() {
            var items = [];
            var counter = 0;

            app.edm.box.find('.tab-pane.active').find('.item.new').each(function(idx) {
                var row = $(this).find(':checkbox:checked');
                if (row.length > 0 && counter < app.edm.limit) {
                    items.push(row.val());
                    $(this).removeClass('new').addClass('progressing');
                    counter++;
                }
                else {
                    // $(this).removeClass('new').addClass('pass');
                }
            });

            gee.clog(items);
            app.edm.send(items);
        },

        send: function(rows) {
            var callback = function () {
                if (!this.code || this.code !== '1') {
                    app.stdErr(this);
                }
                else {
                    app.edm.box.find('.tab-pane.active').find('.item.progressing').removeClass('progressing').addClass('done');
                    setTimeout(function() {
                        app.edm.loopList();
                    }, app.edm.delay * 1000);
                }
            };

            if (rows.length > 0) {
                gee.yell('/edm/send', {edm: app.edm.slug, emails: rows}, callback, callback);
            }
            else {
                app.edm.sendBtn.removeAttr('disabled').find('i').remove().end().html('已完成發送');
            }
        }
    };

    app.edm.init();

    gee.hook('loadEmails', function (me) {
        app.edm.loadEmails(me);
    });

    gee.hook('sendEdm', function (me) {
        app.edm.sendBtn = me;
        me.attr('disabled', 'disabled').html('發送中').append('<i class="fa fa-spinner fa-pulse fa-fw"></i>');
        app.edm.box.find('.tab-pane.active').find('.item.pass').removeClass('pass').addClass('new');
        app.edm.loopList();
    });

}(app, gee, jQuery));
