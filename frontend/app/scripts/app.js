/**
 * app
 */

var gee = gee || $.fn.gene;

var app = function() {
    'use strict';

    var that = this;

    that.config = {
        baseUrl: window.apiUrl
    };

    var app = {
        pageCounter: 1,
        pageLimit: 8,

        fontSize: 1.25,
        detectWidth: 600,

        redo: null,

        tmplStores: {},
        htmlStores: {},
        tmplPath: 'tmpls',

        errMsg: {
            'e9100': '資料庫發生錯誤',
            'e9101': '資料庫發生錯誤',
            'e9102': '資料庫發生錯誤',
            'e9103': '資料庫發生錯誤',
            'e8100': '請輸入必填欄位'
        },

        init: function(modules) {

            app.win = $(window);
            app.docu = $(document);
            app.body = (app.win.opera) ? (app.docu.compatMode == 'CSS1Compat' ? $('html') : $('body')) : $('body');

            app.screen = ($('body').width() < that.config.detectWidth) ? 'mobile' : 'tablet';

            app.body.addClass(app.screen);

            gee.apiUri = that.config.baseUrl +'';
            gee.mainUri = window.mainUrl;
            gee.debug = 1;

            gee.init();

            var pathArray = window.location.pathname.split('/');

            if (pathArray && pathArray[1]) {
                $('#sidebar-menu').find('[data-type="'+ pathArray[1] +'"]').trigger('click');
            }

            if (modules && modules.length > 0) {
                modules.map(function (module) {
                    if (gee.isset(app[module]) && gee.isset(app[module].init)) {
                        app[module].init();
                    }
                });
            }
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
            var newPath = '/'+ src;
            var success = function(html, status, xhr) {
                if ( status === 'error' ) {
                    gee.alert({
                        title: 'Alert!',
                        txt: 'Sorry but there was an error: '+ xhr.status + ' ' + xhr.statusText
                    });
                }
                else {
                    app.htmlStores['file-'+ src] = html;
                    if (redirect === 1) {
                        app.redirect({path: newPath, ta: ta});
                    }
                    gee.init();
                }
            };
            ta = (typeof ta === 'string') ? $('#'+ ta) : ta;
            redirect = (redirect) ? redirect : '';

            if (typeof app.htmlStores['file-'+ src] === 'undefined') {
                gee.clog('load: ' + app.tmplPath + newPath + '.html');
                ta.load(gee.mainUri + app.tmplPath + newPath +'.html', success);
            }
            else {
                ta.html(app.htmlStores['file-'+ src]);
                if (redirect === 1) {
                    app.redirect({path: newPath, ta: ta});
                }
                gee.init();
            }
        },

        loadTmpl: function (tmplName, box) {
            if (typeof app.tmplStores[tmplName] === 'undefined') {
                var htmlCode = box.html() || '';
                htmlCode = htmlCode.replace(/&lt;\%/g, '<%').replace(/\%&gt;/g, '%>').replace(/\&amp;/g, '&');

                if (box.is('tbody') || box.hasClass('loop')) { // fix tbody>tr bug
                    htmlCode = '<%props data%>' + htmlCode + '<%/props%>';
                }
                if (box.is('form')) {
                    app.backend.initForm(box);
                    htmlCode = box.html();
                    htmlCode = htmlCode.replace(/&lt;\%/g, '<%').replace(/\%&gt;/g, '%>');
                }

                htmlCode = htmlCode.replace(/pre-gee/g, 'gee')
                    // .replace(/pre-gene/g, 'data-gene')
                    .replace(/pre-src/g, 'src'); // img src

                // gee.clog(htmlCode);
                app.tmplStores[tmplName] = $.templates(htmlCode);
            }

            box.html('');
        },

        setForm: function (ta, row) {
            ta.find(':input:not(:button)').each(function() {
                var col = $(this);
                var idx = col.attr('name');
                if (row.hasOwnProperty(idx)) {
                    var val = row[idx];
                    if (col.is(':checkbox')) {
                        if (col.attr('value') === val) {
                            col.prop('checked', true);
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
            if (!app.route) {
                window.location.hash = state.path;
            }
            else {
                window.history.pushState(state, '', state.path);
            }
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

        /**
         * a object of promise
         * @param  function condition return bool
         * @param  int limit max test times
         * @return promise
         */
        waitFor: function (condition, limit) {
            var dfr = $.Deferred();
            var times = 0;
            var during = 70;
            limit = limit || 9; // Longest duration :  during * (limit+1)


            var timer = setInterval(function () {
                times++;
                if (condition()) {
                    clearInterval(timer);
                    dfr.resolve();
                }

                if (times > limit) {
                    clearInterval(timer);
                    dfr.reject();
                }
            }, during);

            return dfr.promise();
        },

        stdErr: function (e, redo) {
            e.data = e.data || {};

            if (gee.isset(e.data.msg)) {
                gee.alert({ title: 'Alert!', txt: e.data.msg });
            } else {
                var code = 'e' + e.code;
                if (gee.isset(app.errMsg[code])) {
                    gee.alert({ title: 'Alert!', txt: app.errMsg[code] });
                } else {
                    gee.alert({
                        title: 'Error!',
                        txt: 'Server Error, Plaese Try Later(' + e.code + ')'
                    });
                }
            }
        },

        stdSuccess: function (rtn) {
            rtn.data = rtn.data || {};

            if (gee.isset(rtn.data.msg)) {
                gee.alert({ title: 'Alert!', txt: rtn.data.msg });
            }

            if (gee.isset(rtn.data.redirect)) {
                location.href = (rtn.data.redirect === '') ? gee.apiUri : rtn.data.redirect;
            }

            if (gee.isset(rtn.data.goback)) {
                history.go(-1);
            }
        },

        showErrMsg: function (col, cond, msg) {
            var box = col.closest('.form-group');
            box.removeClass('has-error has-pass has-feedback');

            if (cond) {
                box.addClass('has-error').find('.error-msg').text(msg);
                col.one('keyup', app.clearMsg);
            } else {
                box.addClass('has-pass has-feedback');
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
            currency: function(val) { return '$' + ($.fn.formatMoney((val+''), 0)); },
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

        extractAttr: function(obj) {
            var attr = {};
            obj.each(function() {
                $.each(this.attributes, function() {
                    attr[this.name] = this.value;
                });
            });
            return attr;
        }
    };

    return app;
};

var app = new app();
$.views.helpers(app.formatHelper);
