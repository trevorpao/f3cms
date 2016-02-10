
(function(w, $){
    "use strict";

    $.fn.placeholder = function(options) {
        return this.each(function() {
            if (!("placeholder" in document.createElement(this.tagName.toLowerCase()))) {
                var $this = $(this);
                var placeholder = $this.attr('placeholder');
                $this.val(placeholder).data('color', $this.css('color')).css('color', '#aaa');
                $this
                    .focus(function() {
                        if ($.trim($this.val()) === placeholder) {
                            $this.val('').css('color', $this.data('color'));
                        }
                    })
                    .blur(function() {
                        if (!$.trim($this.val())) {
                            $this.val(placeholder).data('color', $this.css('color')).css('color', '#aaa');
                        }
                    });
            }
        });
    };

    /*
    base lib
    */
    $.fn.ifEmpty = function() {
        return (this.val() === "" || this.val() == this.attr("placeholder"));
    };

    $.fn.ifErrEmail = function() {
        var str = this.val(),
            erp = /[\w-]+@([\w-]+\.)+[\w-]+/;

        gee.clog('iee:'+ erp.test(str));

        if (erp.test(str) !== true)
            return true;
        else
            return false;
    };

    $.fn.ifErrPasswd = function() {
        var str = this.val(),
            erp = /^(?=.*\d)(?=.*[a-zA-Z]){2,}(?=.*[a-zA-Z])(?!.*\s).{4,12}$/;

        if (erp.test(str) !== true)
            return true;
        else
            return false;
    };

    $.fn.ifErrChinese = function() {
        var str = this.val(),
            erp = /[^\u4e00-\u9fa5]/;

        gee.clog(erp.test(str));

        if (erp.test(str) === true)
            return true;
        else
            return false;
    };

    $.fn.ifErrNumber = function() {
        var str = this.val(),
            erp = /^\d+$/;

        gee.clog("num::" + erp.test(str));

        if (erp.test(str) !== true)
            return true;
        else
            return false;
    };

    $.fn.inArray = function(ary, str) {
        var inArray = 0,
            i;
        for (i in ary) {
            if (ary[i] == str) inArray++;
        }
        return (inArray > 0) ? true : false;
    };

})(window, jQuery);

gee.init();
