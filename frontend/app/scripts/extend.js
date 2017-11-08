
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

    $.fn.formatNum = function (n, c, d, t, s) {
        n = n * 1;
        c = isNaN(c = Math.abs(c)) ? 2 : c;
        d = typeof d === 'undefined' ? '.' : d;
        t = typeof t === 'undefined' ? ',' : t;
        s = (s === 1) ? ('') : ((n < 0) ? '-' : '');
        var i = parseInt(n = Math.abs(+n || 0).toFixed(c)) + '';
        var j = (j = i.length) > 3 ? j % 3 : 0;

        return s + (j ? i.substr(0, j) + t : '') + i.substr(j).replace(/(\d{3})(?=\d)/g, '$1' + t) + (c ? d + Math.abs(n - i).toFixed(c).slice(2) : '');
    };

    $.fn.serializeFormJSON = function () {
        var o = {};
        var a = this.serializeArray();
        $.each(a, function () {
            if (o[this.name]) {
                if (!o[this.name].push) {
                    o[this.name] = [o[this.name]];
                }
                o[this.name].push(this.value || '');
            } else {
                o[this.name] = this.value || '';
            }
        });
        return o;
    };

    $.fn.extend({
        /**
         * hasMutilClass
         * @param  {String}  nameStr classA|classB for OR, classA&classB for AND
         * @return {Boolean} return true if those classes are assigned to this element
         */
        hasMutilClass: function (nameStr) {
            var split = (nameStr.indexOf('|') !== -1) ? '|' : '&';
            var ary = nameStr.split(split);
            var ta = $(this)[0];
            var check = (split === '|' || ary.length === 0) ? false : true;
            $.each(ary, function (idx, val) {
                var tmpChk = ta.classList.contains(val);
                if (tmpChk && split === '|') {
                    check = true;
                }
                if (!tmpChk && split === '&') {
                    check = false;
                }
            });
            return check;
        }
    });

})(window, jQuery);
