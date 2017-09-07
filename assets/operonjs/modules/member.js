;(function(app, gee, $){
    "use strict";

    app.member = {
        current: {},
        chkLogin: function() {
            var callback = function() {
                if (!this.code || this.code !== '0') {
                    gee.clog('Logined');
                    app.body.removeClass('logout').addClass('login');
                    app.member.current = this.data;
                }
                else {
                    gee.clog('Did not login');
                    app.body.removeClass('login').addClass('logout');
                }
            };

            gee.yell('/member/chk_login', {}, callback, callback);
        },

        login: function(data) {
            var callback = function() {
                if (!this.code || this.code !== '1') {
                    app.stdErr(this);
                }
                else {
                    // app.body.removeClass('logout').addClass('login');

                    if (gee.isset(this.data.msg)) {
                        gee.alert({
                            title: 'Alert!',
                            txt: this.data.msg
                        });
                    }

                    if (gee.isset(this.data.uri)) {
                        location.href = (this.data.uri === '') ? gee.apiUri : this.data.uri;
                    }
                }
            };

            gee.yell('/member/login', data, callback, callback);
        },

        register: function(data, btn) {
            var callback = function() {
                btn.removeAttr('disabled').find('i').remove();

                if (this.code == '1') {

                    if (gee.isset(this.data.msg)) {
                        gee.alert({
                            title: 'Alert!',
                            txt: this.data.msg
                        });
                    }

                    if (gee.isset(this.data.uri)) {
                        location.href = (this.data.uri === '') ? gee.apiUri : this.data.uri;
                    }

                    if (gee.isset(this.data.goback)) {
                        history.go(-1);
                    }
                } else {
                    if (gee.isset(this.data) && gee.isset(this.data.msg)) {
                        gee.alert({
                            title: 'Alert!',
                            txt: this.data.msg
                        });
                    } else {
                        gee.alert({
                            title: 'Error!',
                            txt: 'Server Error, Plaese Try Later(' + this.code + ')'
                        });
                    }
                }
            };

            gee.yell('/member/add_new', data, callback, callback);
        },

        update: function(data, btn) {
            var callback = function() {
                btn.removeAttr('disabled').find('i').remove();

                if (this.code == '1') {

                    if (gee.isset(this.data.msg)) {
                        gee.alert({
                            title: 'Alert!',
                            txt: this.data.msg
                        });
                    }

                    if (gee.isset(this.data.uri)) {
                        location.href = (this.data.uri === '') ? gee.apiUri : this.data.uri;
                    }

                    if (gee.isset(this.data.goback)) {
                        history.go(-1);
                    }
                } else {
                    if (gee.isset(this.data) && gee.isset(this.data.msg)) {
                        gee.alert({
                            title: 'Alert!',
                            txt: this.data.msg
                        });
                    } else {
                        gee.alert({
                            title: 'Error!',
                            txt: 'Server Error, Plaese Try Later(' + this.code + ')'
                        });
                    }
                }
            };

            gee.yell('/member/update', data, callback, callback);
        },

        logout: function() {
            var callback = function() {
                if (!this.code || this.code !== '1') {
                    app.stdErr(this);
                }
                else {
                    location.href = '/';
                }
            };

            gee.yell('/member/logout', {}, callback, callback, 'GET');
        },

        getMinsFromNow: function (mins) {
            return new Date(new Date().valueOf() + mins * 60 * 1000);
        }
    };

    gee.hook('login', function(me){
        var f = me.data('ta') ? $('#' + me.data('ta')) : me.closest('form');

        if (!gee.formValidate(f)) {
            return false;
        }
        else {
            return app.member.login(f.serialize());
        }
    });

    gee.hook('sendPinCode', function(me){
        var f = me.data('ta') ? $('#' + me.data('ta')) : me.closest('form');

        var $clock = $('#clock');

        $clock.countdown(app.member.getMinsFromNow(5), function(event) {
            $(this).html(event.strftime('%M:%S'));
        });
    });

    gee.hook('register', function(me){
        var form = me.data('ta') ? $('#' + me.data('ta')) : me.closest('form');

        form.find('input').each(function() {
            if ($(this).val() == $(this).attr('placeholder')) $(this).val('');
        });

        if (form.find('input[name="agree"]:checked').size() || confirm('是否同意會員條款?')) {
            if (!form.find('input[name="agree"]:checked').size()) {
                $('label[for="agree_cb"]').click();
            }

            if (!gee.formValidate(form)) {
                return false;
            }
            else {
                me.attr('disabled', 'disabled').append('<i class="fa fa-spinner fa-pulse fa-fw"></i>');
                return app.member.register(form.serialize(), me);
            }
        }
        else {
            gee.alert({
                title: 'Error!',
                txt: '您尚未同意會員條款'
            });
        }
    });

    gee.hook('refund', function(me){
        var form = me.data('ta') ? $('#' + me.data('ta')) : me.closest('form');

        form.find('input').each(function() {
            if ($(this).val() == $(this).attr('placeholder')) $(this).val('');
        });

        if (form.find('input[name="agree"]:checked').size() || confirm('是否同意使用電子折讓單?')) {
            if (!form.find('input[name="agree"]:checked').size()) {
                $('label[for="agree_cb"]').click();
            }

            if (!gee.formValidate(form)) {
                return false;
            }
            else {
                me.attr('disabled', 'disabled').append('<i class="fa fa-spinner fa-pulse fa-fw"></i>');
            }
        }
        else {
            gee.alert({
                title: 'Error!',
                txt: '您尚未同意使用電子折讓單'
            });
        }
    });

    gee.hook('modify', function(me){
        var form = me.data('ta') ? $('#' + me.data('ta')) : me.closest('form');

        form.find('input').each(function() {
            if ($(this).val() == $(this).attr('placeholder')) $(this).val('');
        });

        if (!gee.formValidate(form)) {
            return false;
        }
        else {
            var chk = 1;
            var txt = [];

            if ($('#pwd').val()) {
                if ($('#pwd').isPasswdErr()) {
                    txt.push('密碼：請確認是否符合 6~12 字英文及數字');
                    chk = 0;
                }

                if ($('#cpwd').val() !== $('#pwd').val()) {
                    txt.push('密碼與確認密碼不相同');
                    chk = 0;
                }
            }

            if (chk === 1) {
                me.attr('disabled', 'disabled').append('<i class="fa fa-spinner"></i>');
                return app.member.update(form.serialize(), me);
            }
            else {
                gee.alert({
                    title: 'Error!',
                    txt: txt.join("\r\n")
                });
            }
        }
    });

    gee.hook('logout', function(me){
        app.member.logout();
    });

}(app, gee, jQuery));
