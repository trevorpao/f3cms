
(function($) {
    "use strict";

    if (typeof $.fn.gene == "undefined") {
        $.fn.gene = {
            debug: 0,
            tags: [],
            evts: {},
            scriptName: 'jquery.gene',
            taClass: 'gee',
            apiUri: '/',

            goBack: function(me) {
                history.go(-1);
            },

            //表單驗証
            formValidate: function(me) {
                var g = $.fn.gene,
                    chk = 1;

                me.find("input[require], select[require], textarea[require]").each(function() {

                    if (chk === 0) {
                        return chk;
                    }

                    var $ci = $(this),
                        label = $ci.attr("title") || $ci.attr("name"),
                        txt = "";

                    if ($ci.is(":visible")) {

                        if ($ci.ifEmpty()) {
                            txt = '請输入' + label;
                            chk = 0;
                        }

                        g.clog('required:' + $ci.attr("require"));

                        if ($ci.attr("require") == "password") {
                            if ($ci.ifErrPasswd()) {
                                txt = label + '：請確認是否符合 4~12 字英文及數字';
                                chk = 0;
                            }
                        }

                        if ($ci.attr("require") == "sameWith") {
                            var $ta = $('#' + $ci.data('ta'));
                            if ($ci.val() != $ta.val()) {
                                txt = label + '不符合' + $ta.attr("title");
                                chk = 0;
                            }
                        }

                        if ($ci.attr("require") == "email") {
                            if ($ci.ifErrEmail()) {
                                txt = label + '：請確認是否符合 Email 格式';
                                chk = 0;
                            }
                        }

                        if ($ci.attr("require") == "chinese") {
                            if ($ci.ifErrChinese()) {
                                txt = label + '：請確認是否為全中文';
                                chk = 0;
                            }
                        }

                        if ($ci.attr("require") == "number") {
                            if ($ci.ifErrNumber()) {
                                txt = label + '：請確認是否符合數字格式';
                                chk = 0;
                            }

                            if ($ci.data("min") && chk == 1) {
                                if ($ci.data("min") > $ci.val()) {
                                    txt = '' + label + '：應大於 ' + $ci.data("min");
                                    chk = 0;
                                }
                            }

                            if ($ci.data("max") && chk == 1) {
                                if ($ci.data("max") > $ci.val()) {
                                    txt = '' + label + '：應小於 ' + $ci.data("max");
                                    chk = 0;
                                }
                            }
                        }

                    }

                    if (chk === 0) {
                        g.alert({
                            title: 'Error!',
                            txt: txt
                        });
                    }
                });

                return chk;
            },

            stdSubmit: function(me) {

                var g = $.fn.gene,
                    f = me.data("ta") ? $("#" + me.data("ta")) : me.closest("form"),
                    dAction = function() {

                        me.removeAttr('disabled').find('i').remove();

                        if (this.code == "1") {

                            if (gee.isset(this.data.msg)) {
                                gee.alert({
                                    title: 'Alert!',
                                    txt: this.data.msg
                                });
                            }

                            if (gee.isset(this.data.uri)) {
                                location.href = (this.data.uri === '') ? g.apiUri : this.data.uri;
                            }

                            if (gee.isset(this.data.goback)) {
                                history.go(-1);
                            }

                            if (gee.isset(this.data.reset)) {
                                f[0].reset();
                            }

                            if (gee.check(this.data.func)) {
                                gee.clog(this.data.func);
                                gee.exe(this.data.func, me);
                            }
                        } else {
                            if (gee.isset(this.data) && gee.isset(this.data.msg)) {
                                gee.alert({
                                    title: 'Alert!',
                                    txt: this.data.msg
                                });
                            } else {
                                g.alert({
                                    title: 'Error!',
                                    txt: "Server Error, Plaese Try Later(" + this.code + ")"
                                });
                            }
                        }
                    };

                if (!g.formValidate(f)) {
                    return false;
                }
                else {
                    me.attr('disabled', 'disabled').append('<i class="fa fa-spinner"></i>');

                    f.find("input").each(function() {
                        if (me.val() == me.attr("placeholder")) me.val("");
                    });

                    g.yell(me.data('uri'), f.serialize(), dAction, dAction);

                    if (me.attr("reset") === "1") {
                        f[0].reset();
                    }
                }

            },

            yell: function(uri, postData, successCB, errorCB, hideLoadAnim) {

                var g = $.fn.gene;

                if (!hideLoadAnim) {
                    g.loadAnim('show');
                }

                $.ajax({
                        "url": g.apiUri + uri,
                        "type": "POST",
                        "data": postData,
                        "cache": false
                    })
                    .done(function(j) {
                        if (j) {
                            g.clog(j);
                            if (g.isset(j.code)) {
                                if (j.code == "1") {
                                    if (typeof successCB == 'function') {
                                        successCB.call(j);
                                    }
                                } else {
                                    if (typeof errorCB == 'function') {
                                        errorCB.call(j);
                                    } else {
                                        if (g.isset(j.data)) {
                                            if (g.isset(j.data.uri)) {
                                                location.href = j.data.uri;
                                            }
                                        } else {
                                            g.alert({
                                                title: 'Error!',
                                                txt: "Server Error, Plaese Try Later(" + j.code + ")"
                                            });
                                        }
                                    }
                                }
                            } else {
                                g.alert({
                                    title: 'Error!',
                                    txt: "Server Error, Plaese Try Later(1)"
                                });
                            }
                        } else {
                            g.alert({
                                title: 'Error!',
                                txt: "Server Error, Plaese Try Later(2)"
                            });
                        }
                    })
                    .fail(function(o, s) {

                        g.err('ajax fail(' + o.status + ')!!');
                    })
                    .always(function() {

                        g.clog("ajax complete");
                        if (!hideLoadAnim) {
                            g.loadAnim('hide');
                        }
                    });
            },

            resetForm: function(me) {
                var f = me.data("ta") ? $("#" + me.data("ta")) : me.closest("form");
                f[0].reset();
            },

            getChainOption: function(me) {
                var g = $.fn.gene,
                    ta = me.data('ta'),
                    uri = me.data('uri'),
                    v = me.val();

                $.get(
                    uri + '&pid=' + v,
                    function(data) {
                        $('#' + ta).html(data);
                    }
                );
            },

            loadAnim: function(command) {
                var obj = $('body'),
                    anim = (command == 'show' || obj.hasClass("loadAnim")) ? 'show' : 'hide';

                if (anim == 'hide' || command == 'hide') {
                    obj.removeClass("loadAnim");
                } else {
                    obj.addClass("loadAnim");
                }
            },

            alert: function(me) {
                var title = me.title || me.data("title");
                var content = me.txt || me.data("txt");

                // $('#errorModal')
                // .find('.title').html(title).end()
                // .find('.content').html(content).end()
                // .modal('show');
                alert(content);
            },

            test: function(me) {
                var g = $.fn.gene;

                g.clog(me);
            },

            exe: function(func, args) {
                var g = $.fn.gene,
                    fun = (!g.check(func)) ? g['404'] : g[func];

                g.clog("exe::" + func);

                return fun.call(this, args);
            },

            pageController: function(me) {
                var g = $.fn.gene,
                    func = 'load' + me.attr('id').capitalize();
                if (!g.check(func)) {
                    g.clog("load:::" + func);
                    g.load(func, 'controller');
                }

                if (!g.check(func)) {
                    g.clog("load fail:::" + func);
                } else {
                    g.clog("start::" + me.attr('id').capitalize());
                    g.exe(func, me);
                }
            },

            load: function(functionName, sf) {
                var g = $.fn.gene,
                    loc = window.location.pathname,
                    dir = g.apiUri + '/js/',
                    subfloder = sf || 'plugins';

                g.clog("script::" + dir + subfloder + '/' + functionName + '.js');

                if (!g.check(functionName)) {
                    if (typeof importScripts == "function") {
                        g.clog("start importScripts::");
                        importScripts(dir + subfloder + '/' + functionName + '.js');
                    } else {
                        g.clog("start scripttag::");
                        $("#body").append("<script src='" + dir + subfloder + '/' + functionName + ".js'>\x3C/script>");
                    }
                }
            },

            '404': function(me) {
                var g = $.fn.gene;
                g.err('command not found!!');
            },

            err: function(txt) {
                if (txt !== "")
                    this.clog("Error::" + txt);
                else
                    this.clog('Error::unknown error!!');
            },

            check: function(functionName) {
                var g = $.fn.gene;
                return g.isset(g[functionName]);
            },

            clog: function(txt) {
                var g = $.fn.gene;
                if (typeof console != "undefined" && g.debug == 1) {
                    if (typeof txt == "string" || typeof txt == "number") {
                        console.log("gene::" + txt);
                    } else {
                        console.log("gene::" + typeof(txt));
                        console.log(txt);
                    }
                }
                return g;
            },

            parseUrlQuery: function(txt) {
                var a = document.createElement('a');
                a.href = txt;
                return (function() {
                    var ret = {},
                        seg = a.search.replace(/^\?/, '').split('&'),
                        len = seg.length,
                        i = 0,
                        s;
                    for (; i < len; i++) {
                        if (!seg[i]) {
                            continue;
                        }
                        s = seg[i].split('=');
                        ret[s[0]] = s[1];
                    }
                    return ret;
                })();
            },

            hookTag: function(newTagName, func) {
                var g = $.fn.gene;
                if (!g.check(newTagName)) {
                    g.tags.push(newTagName);
                    g.hook(newTagName, func);
                } else {
                    g.clog(newTagName + " overwrite?");
                }
            },

            hook: function(functionName, fun, evt) {
                var g = $.fn.gene;
                if (!g.check(functionName)) {
                    g[functionName] = fun;
                    g.evts[functionName] = (evt != "undefined") ? evt : "click";
                } else {
                    g.clog(functionName + " overwrite?");
                }
            },

            unhook: function(functionName, fun) {
                var g = $.fn.gene;
                if (g.check(functionName)) {
                    delete g[functionName];
                } else {
                    g.clog(functionName + " exist?");
                }
            },

            isset: function(obj) {
                if (typeof obj == "undefined" || obj === null) {
                    return false;
                } else {
                    return true;
                }
            },

            bindToEvt: function(evt) {
                var me = $(this);

                if (me.data('nopde') != 1) {
                    evt.preventDefault();
                    gee.clog("nopde");
                }

                me.event = evt;
                gee.clog("start");
                gee.exe(me.data("meb")[evt.type], me);
            },

            init: function() {
                var g = $.fn.gene;

                for (var tk in g.tags) {
                    g.exe(g.tags[tk], $(g.tags[tk]));
                }

                $('.' + g.taClass).each(function() {
                    var me = $(this),
                        e = me.data('event'),
                        b = me.data('behavior'),
                        ebi = me.data('gene'),
                        meb = {};

                    if (!g.isset(b)) {
                        b = "404";
                    }

                    if (!g.isset(e)) {
                        e = (!g.isset(g.evts[b])) ? "click" : g.evts[b];
                    }

                    if (!g.isset(ebi)) {
                        meb[e] = b;
                    } else {
                        var eba = ebi.replace(" ", "").split(",");
                        for (var ai = 0; ai < eba.length; ai++) {
                            var ebo = eba[ai].split(":");
                            meb[ebo[0]] = ebo[1];
                        }
                    }

                    g.clog(meb);

                    me.data("meb", meb).unbind();

                    for (var ei in meb) {
                        if (!g.check(meb[ei])) {
                            g.clog("load:::" + meb[ei]);
                            g.load(meb[ei]);
                        }

                        if (!g.check(meb[ei])) {
                            g.clog("load fail:::" + meb[ei]);
                        }

                        if (ei == "init") {
                            g.exe(meb[ei], me);
                        } else {
                            me.on(ei, g.bindToEvt);
                        }
                    }
                }).removeClass(g.taClass);
            }
        };

        $.fn.applyGEE = function(ta, obj) {
            var g = $.fn.gene;
            if (g.isset(ta)) {
                g.exe(ta, obj);
            } else {
                var b = this.data("behavior");
                g.exe(b, this);
            }

            return this;
        };
    }

})(jQuery);

if (typeof importScripts != "function") {
    var importScripts = (function(globalEval) {
        var xhr = new XMLHttpRequest();
        return function importScripts() {
            var args = Array.prototype.slice.call(arguments),
                len = args.length,
                i = 0,
                meta, data, content;
            for (; i < len; i++) {
                if (args[i].substr(0, 5).toLowerCase() === "data:") {
                    data = args[i];
                    content = data.indexOf(",");
                    meta = data.substr(5, content).toLowerCase();
                    data = decodeURIComponent(data.substr(content + 1));
                    if (/;\s*base64\s*[;,]/.test(meta)) {
                        data = atob(data);
                    }
                    if (/;\s*charset=[uU][tT][fF]-?8\s*[;,]/.test(meta)) {
                        data = decodeURIComponent(escape(data));
                    }
                } else {
                    xhr.open("GET", args[i], false);
                    xhr.send(null);
                    data = xhr.responseText;
                }
                globalEval(data);
            }
        };
    }(eval));
}
