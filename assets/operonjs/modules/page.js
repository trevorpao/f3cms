;(function(app, gee, $){
    "use strict";

    // register a module name
    app.page = {
        cuModal: null,
        menuBox: $("#mainMenu"),
        init: function () {
            app.page.handler();

            if (navigator.userAgent.match(/iPhone|iPad|iPod/i)) {
                $(window).bind("touchend touchcancel touchleave", function (e) {
                    app.page.handler();
                });
            } else {
                $(window).scroll(function () {
                    app.page.handler();
                });
            }

            if (app.screen === 'mobile') {
                $(".c-layout-show-filter").show();
            }

            $('#carousel1').carousel({
                interval: false
            });
            $('#carousel2').carousel({
                interval: false
            });

            var popupOpts = {
                maxWidth: 800,
                maxHeight: 600,
                fitToView: false,
                width: '70%',
                height: '70%',
                autoSize: false,
                closeClick: false,
                openEffect: 'none',
                closeEffect: 'none'
            };

            if (app.screen === 'mobile') {
                // popupOpts.fitToView = true;
                // popupOpts.width = '100%';
                // popupOpts.height = '100%';

                $("#various").attr('target', '_blank');
            }
            else {
                $("#various").fancybox(popupOpts);
            }



            if ($('.video-player').length > 0) {
                $('.video-player').mb_YTPlayer();
            }

            if (1 == 2 && $('#tab1').length > 0 && app.screen === 'tablet') {
                var collapseBox = $('#tab1');
                collapseBox.find('[data-toggle=collapse]').hover(
                    function() {
                        var target = $(this).attr('href');
                        collapseBox.find('.collapse').collapse('hide').end()
                            .find(target).collapse('show');
                    }, function() {
                        // $('.panel-collapse').collapse('hide');
                    }
                );
            }
        },
        handler: function () {
            var currentWindowPosition = $(window).scrollTop();
            // gee.clog('currentWindowPosition::'+ currentWindowPosition);

            if (app.screen === 'mobile') { // if (window.outerWidth < 992) {
                app.page.menuBox.removeClass('fixed');
            } else {
                if (currentWindowPosition >= 40) {
                    app.page.menuBox.addClass('fixed');
                } else {
                    app.page.menuBox.removeClass('fixed');
                }
            }

            if (currentWindowPosition > 300) {
                $(".goTop").show();
            } else {
                $(".goTop").hide();
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
                    app.page.cuModal = modal;
                })
                .on('hidden.bs.modal', function () {
                    gee.clog('hidden.bs.modal');
                    if (html !== '') {
                        modalBody.html('');
                    }
                    app.page.cuModal = null;
                })
                .modal();
        }
    };

    gee.hook('reXPos', function(me) {
        var left = me.data('left')*1;
        var x = me.data('x')*1;
        var w = app.body.width();

        // if (w > 1000) {
        //     left = 0;
        // }
        // else {
        //     left = (app.body.width() * x + left);
        // }
        left = (app.body.width() * x + left);

        me.css({
            left: left + 'px'
        });

    }, 'init');

    gee.hook('selOpt', function(me){
        var f = me.closest('form');
        var box = me.closest('.dropdown');
        var ta = me.data('ta');
        var txt = $(me.event.target).text();

        f.find('input[name="'+ ta +'"]').val(txt);
        box.find('.menu-txt').text(txt);
    });

    gee.hook('go2Top', function(me) {
        $('html, body').animate({
            scrollTop: 0
        }, 600);
    });

    // hook some handler
    gee.hook('showFilterModal', function(me){
        app.page.showModal('filterModal', 'html');

        setTimeout(function () {
            gee.clog('showFilterModal:setTimeout');
        }, 300);
    });

}(app, gee, jQuery));
