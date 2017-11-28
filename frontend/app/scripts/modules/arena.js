;(function(app, gee, $){
    'use strict';

    // register a module name
    app.arena = {
        cuModal: null,
        feed: null,
        init: function () {
            // app.arena.initSlider($('#slider-range1'));
            app.arena.handler();

            if (navigator.userAgent.match(/iPhone|iPad|iPod/i)) {
                $(window).bind('touchend touchcancel touchleave', function (e) {
                    app.arena.handler();
                });
            } else {
                $(window).scroll(function () {
                    app.arena.handler();
                });
            }

            localforage.ready().then(function() {
                app.arena.feed = localforage.createInstance({
                    name: 'arenaBase',
                    version: 1
                });
                gee.clog('-------------------------- localforage start -----------------------------');
                app.lang.init();
                app.arena.initFontSize();
            }).catch(function (e) {
                gee.clog(e);
                app.track.send('failure', 'init_localforage', JSON.stringify(e));
            });
        },
        initFontSize: function () {
            app.arena.feed.getItem('fontSize', function(err, val){
                if (err) {
                    gee.clog('---------------------- localforage err -------------------------');
                    gee.clog(err);
                    app.track.send('failure', 'load_localforage', JSON.stringify(err));
                }

                var cufontSize = app.fontSize;

                if (val) {
                    cufontSize = val*1;
                }

                if (app.fontSize !== cufontSize) {
                    app.fontSize = cufontSize;
                    $('#article .text p, #article .text li').css('fontSize', app.fontSize+'rem');
                }
            });
        },
        handler: function () {
            var currentWindowPosition = $(window).scrollTop();

            if (currentWindowPosition > 300) {
                $('.goTop').show();
            } else {
                $('.goTop').hide();
            }
        },
        showModal: function (ta, html) {
            html = (html) ? html : '';
            var modal = $('#'+ ta), modalBody = $('#'+ ta +' .modal-body');

            modal.unbind()
                .on('show.bs.modal', function () {
                    gee.clog('show.bs.modal');
                    if (html !== '') {
                        modalBody.html(html);
                    }
                    app.arena.cuModal = modal;
                })
                .on('hidden.bs.modal', function () {
                    gee.clog('hidden.bs.modal');
                    if (html !== '') {
                        modalBody.html('');
                    }
                    app.arena.cuModal = null;
                })
                .modal();
        }
    };

    gee.hook('reXPos', function(me) {
        var left = me.data('left')*1;
        var x = me.data('x')*1;
        var w = app.body.width();

        if (w > 1000) {
            left = 0;
        }
        else {
            left = (app.body.width() * x + left);
        }

        me.css({
            left: left + 'px'
        });

    }, 'init');

    // hook some handler
    gee.hook('arena.modal.show', function(me){
    });

    gee.hook('loadMain', function(me) {
        var src = me.data('src');

        app.loadHtml(src, 'main-box', 1);
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

    gee.hook('largerFont', function(me) {
        var taStr = me.data('ta') || '#article .text p, #article .text li';
        app.fontSize = app.fontSize * 1 + 0.1;
        app.arena.feed.setItem('fontSize', app.fontSize).catch( gee.clog );
        $(taStr).css('fontSize', app.fontSize + 'rem');
    });

    gee.hook('smallerFont', function(me) {
        var taStr = me.data('ta') || '#article .text p, #article .text li';
        app.fontSize = app.fontSize * 1 - 0.1;
        app.arena.feed.setItem('fontSize', app.fontSize).catch( gee.clog );
        $(taStr).css('fontSize', app.fontSize + 'rem');
    });

    gee.hook('initAutolink', function(me) {
        var html = Autolinker.link(me.html(), {
            stripPrefix: false,
            truncate: { length: 32, location: 'middle' }
        });

        me.html(html);
    }, 'init');

    gee.hook('initSwitchery', function(me) {
        new Switchery(me[0], me.data());
    }, 'init');

    gee.hook('initPagination', function(me) {
        var params = me.data();
        me.twbsPagination({
            totalPages: Math.ceil(params.total / params.length),
            visiblePages: 7,
            href: $('link[rel="canonical"]').attr('href') + '?page={{number}}'
        });
    }, 'init');

    gee.hook('initTmpl', function (me) {
        app.loadTmpl(me.data('tmpl'), me);
    });

}(app, gee, jQuery));
