
(function(w, $){
    "use strict";

    $.fn.placeholder = function(options) {
        return this.each(function() {
            if (!("placeholder" in document.createElement(this.tagName.toLowerCase()))) {
                var $this = $(this);
                var placeholder = $this.attr('placeholder');
                $this.val(placeholder).data('color', $this.css('color')).css('color', '#aaa');
                $this.focus(function() {
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
    $.fn.isEmpty = function() {
        return (this.val() === "" || this.val() == this.attr("placeholder"));
    };

    $.fn.isEmailErr = function() {
        var erp = /[\w-]+@([\w-]+\.)+[\w-]+/;

        return (erp.test(this.val()) !== true) ? true : false;
    };

    $.fn.isPasswdErr = function() {
        var erp = /^(?=.*\d)(?=.*[a-zA-Z]){2,}(?=.*[a-zA-Z])(?!.*\s).{4,12}$/;

        return (erp.test(this.val()) !== true) ? true : false;
    };

    $.fn.isChineseErr = function() {
        var erp = /[^\u4e00-\u9fa5]/;

        return (erp.test(this.val()) === true) ? true : false;
    };

    $.fn.isNumberErr = function() {
        var erp = /^\d+$/;

        return (erp.test(this.val()) !== true) ? true : false;
    };

    $.fn.inArray = function(ary, str) {
        var inArray = 0;

        for (var i in ary) {
            if (ary[i] == str) inArray++;
        }

        return (inArray > 0) ? true : false;
    };

})(window, jQuery);
