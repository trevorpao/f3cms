
(function(w, $){
    'use strict';

    $.fn.placeholder = function(options) {
        return this.each(function() {
            if (!('placeholder' in document.createElement(this.tagName.toLowerCase()))) {
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

    $.fn.inArray = function(ary, str) {
        var inArray = 0;

        for (var i in ary) {
            if (ary[i] == str) inArray++;
        }

        return (inArray > 0) ? true : false;
    };

})(window, jQuery);
