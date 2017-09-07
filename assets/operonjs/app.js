/**
 * app
 */

var gee = gee || $.fn.gene;

var app = function() {
    "use strict";

    var that = this;

    that.config = {
        detectWidth: 991,
        baseUrl: $('base').attr('href')
    };

    return {
        pageCounter: 1,
        pageLimit: 8,

        fontSize: 1.25,

        redo: null,

        tmplStores: {},
        htmlStores: {},

        init: function() {

            app.win = $(window);
            app.docu = $(document);
            app.body = (app.win.opera) ? (app.docu.compatMode == "CSS1Compat" ? $('html') : $('body')) : $('body');

            app.screen = (app.body.width() < that.config.detectWidth) ? 'mobile' : 'tablet';

            app.body.addClass(app.screen);

            gee.apiUri = that.config.baseUrl +'api';
            gee.mainUri = '/';
            gee.debug = 1;

            gee.init();
        },

        resetCurrent: function (box) {
            var tmpl = box.data('tmpl');
            app.pageBox = box;

            if (typeof app.tmplStores[tmpl] === 'undefined') {
                app.tmplStores[tmpl] = $.templates(box.html());
            }

            app.pageCounter = 1;
            app.pageBox.html('');
            app.destroyPaginate();
        },

        setPaginate: function (total, callback) {
            $('#paginate').twbsPagination({
              totalPages: Math.ceil(total/app.pageLimit),
              visiblePages: 7,
              href: $('link[rel="canonical"]').attr('href') + '?page={{number}}',
              onPageClick: function (event, page) {
                app.pageCounter = page;
                callback.call(this);
              }
            });
        },

        destroyPaginate: function (total, callback) {
            $('#paginate').empty().removeData('twbs-pagination').off('page');
        },

        loadHtml: function(src, ta, redirect) {
            var path = '/'+ src;
            var success = function(html, status, xhr) {
                if ( status == "error" ) {
                    gee.alert({
                        title: 'Alert!',
                        txt: "Sorry but there was an error: "+ xhr.status + " " + xhr.statusText
                    });
                }
                else {
                    app.htmlStores[src] = html;
                    if (redirect === 1) {
                        app.redirect({path: path, ta: ta});
                    }
                    gee.init();
                }
            };
            ta = (typeof ta === 'string') ? $('#'+ ta) : ta;
            redirect = (redirect) ? redirect : '';

            if (typeof app.htmlStores[src] === 'undefined') {
                ta.load('./tmpl'+ path +'.html', success);
            }
            else {
                $('#'+ ta).html(app.htmlStores[src]);
                if (redirect !== '') {
                    app.redirect({path: path, ta: redirect});
                }
                gee.init();
            }
        },

        setForm: function (ta, row) {
            ta.find(':input:not(:button)').each(function() {
                var col = $(this);
                var idx = col.attr('name');
                if (row.hasOwnProperty(idx)) {
                    var val = row[idx];
                    if (col.is(':checkbox')) {
                        if (col.attr('value') === val) {
                            col.prop("checked", true);
                            col.next('.switchery').remove();
                            new Switchery(col[0], col.data());
                        }
                    }
                    else {
                        col.val(val);
                    }
                }
            });
        },

        redirect: function(state){
            window.history.pushState(state, '', state.path);
        },

        renderBox: function (box, dataList, clearBox, orientation) {
            orientation = (orientation) ? orientation : 'down';
            if (box && dataList) {
                var tmpl = box.data('tmpl');

                if (clearBox) {
                    box.html('');
                }

                if (orientation === 'down') {
                    box.append(app.tmplStores[tmpl].render(dataList));

                    if (app.pageCounter === 1) {
                        app.toTop();
                    }
                }
                else {
                    app.toTop();

                    box.prepend(app.tmplStores[tmpl].render(dataList));
                }
            }
        },

        toTop: function () {
            gee.clog('nowTop::'+ $('body').offset().top);
            app.body.animate({
                scrollTop: app.body.offset().top
            }, 700, 'easeOutBounce');
        },

        defaultPic: function(element) {
            element.src = '/images/member.jpg';
        },

        stdErr: function(e, redo) {
            if (e.code === '100') {
                app.redo = redo || null;
                app.body.removeClass('login').addClass('logout');

                gee.alert({
                    title: 'Alert!',
                    txt: '請重新登入'
                });
            }
            else {
                if (gee.isset(e.data.msg)) {
                    gee.alert({
                        title: 'Alert!',
                        txt: e.data.msg
                    });
                } else {
                    gee.alert({
                        title: 'Error!',
                        txt: 'Server Error, Plaese Try Later(' + e.code + ')'
                    });
                }
            }
        },

        cleanArray: function (actual) {
          var newArray = [];
          for (var i = 0; i < actual.length; i++) {
                if (actual[i]) {
                    newArray.push(actual[i]);
                }
          }
          return newArray;
        },

        formatHelper: {
            currency: function(val) { return '$' + ($.fn.formatMoney((val+""), 0)); },
            sum: function(price, qty) { return tmplHelpers.currency(qty*price); },
            loadPic: function(path) { return that.config.baseUrl + path; },
            average: function(sum, divide) { return (divide!='0') ? Math.round(sum*10/divide)/10 : 0; },
            beforeDate: function(ts, target) {
                var cu = moment(ts);
                app[target].max_ts = moment.max(app[target].max_ts, cu);
                app[target].min_ts = moment.min(app[target].min_ts, cu);
                return $.timeago(ts);
            },
            showDate: function(status, flow, schedule, createDate, publishDate) {
                var ts = publishDate || createDate;

                return status +' 於 ' + moment(ts).format('MM/DD HH:mm');
            },
            iso8601: function(ts) {
                return moment(ts).toISOString();
            },
            getYear: function(ts) {
                return moment(ts).format('YYYY');
            },
            getMon: function(ts) {
                return moment(ts).format('MMMM');
            },
            getWeek: function(ts) {
                return moment(ts).format('ddd');
            },
            getDay: function(ts) {
                return moment(ts).format('DD');
            },
            getTime: function(ts) {
                return moment(ts).format('HH:mm');
            },
            genderedHonorific: function(gender) {
                return (gender === 'f') ? '女士' : '先生';
            },
            linkAPI: function(str) {
                return that.config.uri + str;
            },
            nl2br: function(str) {
                var breakTag = '<br />';
                return (str + '')
                    .replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
            }
        },

        extract: function(obj) {
            var attr = {};
            obj.each(function() {
                $.each(this.attributes, function() {
                    attr[this.name] = this.value;
                });
            });
            return attr;
        }
    };
};

var app = new app();
$.views.helpers(app.formatHelper);
