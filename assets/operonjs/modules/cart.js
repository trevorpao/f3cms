;(function(app, gee, $){
    "use strict";

    // register a module name
    app.cart = {
        store: [],
        fee: 0,
        total: 0,

        add2Cart: function(item_id, qty, type)
        {
            var callback = function () {
                if (!this.code || this.code !== '1') {
                    app.stdErr(this);
                }
                else {
                    var msg = (gee.isset(this.data) && gee.isset(this.data.msg)) ? this.data.msg : "購物車已更新";
                    app.cart.store = this.data.cart;
                    app.cart.renewCount(this.data.cart.length).renewTotal().renewFee(app.cart.fee).renewSum();
                    if (type == 'force') {
                        app.cart.renderCart();
                    }
                    else {
                        gee.alert({
                            title: 'Success',
                            txt: msg
                        });
                    }
                }
            };

            gee.yell('/cart/add', {qty: qty, item_id: item_id, type: type}, callback, callback);
        },

        removeItem: function(item_id)
        {
            var callback = function () {
                if (!this.code || this.code !== '1') {
                    app.stdErr(this);
                }
                else {
                    var msg = (gee.isset(this.data) && gee.isset(this.data.msg)) ? this.data.msg : "購物車已更新";
                    if (this.data.cart.length > 0) {
                        app.cart.store = this.data.cart;
                        app.cart.renewCount(this.data.cart.length).renewTotal().renewFee(app.cart.fee).renewSum();
                        app.cart.renderCart();
                        gee.alert({
                            title: 'Success',
                            txt: msg
                        });
                    }
                    else {
                        gee.alert({
                            title: 'Success',
                            txt: '購物車內無品項'
                        });
                        location.href = baseUri;
                    }
                }
            };

            gee.yell('/cart/remove', {item_id: item_id}, callback, callback);
        },

        renewCount: function(num)
        {
            if (!$.isNumeric(num)) {
                var callback = function () {
                    if (this.code == "1") {
                        $('.cart_count').html(this.data.count);
                    } else {
                        app.cart._handleErr(this);
                    }
                };

                gee.yell('/cart/count', {}, callback, callback);
            }
            else {
                $('.cart_count').html(num);
            }

            return cart;
        },

        renewTotal: function ()
        {
            app.cart.total = 0;
            $(app.cart.store).each(function(){
                app.cart.total += this.price * this.qty;
            });

            $('.cart_showTotal').html('$'+ (app.cart.total + '').formatMoney(0));

            return cart;
        },

        renewFee: function (fee)
        {
            if (fee === 0) {
                fee = $("input[name='shipment']:checked").val()*1;
            }

            if (app.cart.total < 1500 || fee > 100) {
                app.cart.fee = fee;
            }
            else {
                app.cart.fee = 0;
            }

            $('.cart_shipfee').html('$'+ (app.cart.fee + '').formatMoney(0));

            return cart;
        },

        renewSum: function ()
        {
            $('.cart_showSum').html('$'+ ((app.cart.fee*1 + app.cart.total*1) + '').formatMoney(0));

            return cart;
        },

        loadCart: function($elem)
        {
            var callback = function () {
                if (!this.code || this.code !== '1') {
                    app.stdErr(this);
                }
                else {
                    app.cart.store = this.data.cart;
                    app.cart.renderCart();
                    if (this.data.cart.length < 1) {
                        gee.alert({
                            title: 'Success',
                            txt: '購物車內無品項'
                        });
                        location.href = baseUri;
                    }
                }
            };

            gee.yell('/cart', {}, callback, callback);

            return cart;
        },

        renderCart: function ()
        {
            app.cart.cartBox.html(
                $( "#itemTmpl" ).render( app.cart.store )
            );
            gee.init();
            app.cart.renewTotal().renewFee(app.cart.fee).renewSum();

            return cart;
        }
    };

    // hook some handler
    gee.hook('add2Cart', function(me){
        var item_id = me.data('item_id');
        var qty = ($.isNumeric($('#qty').val())) ? $('#qty').val() : 1;

        app.cart.add2Cart(item_id, qty, 'normal');
    });

    gee.hook('add2CartSample', function(me){
        var num = $('.c-cart-number:eq(0)').text()*1;
        $('.c-cart-number').html(num + 1);
        $('#myModal').modal('hide');
    });

    gee.hook('toggleATM', function(me){
        var pick = me.find('option:selected').val();
        if (pick=='ATM') {
            $('#realATM').show();
        }
        else {
            $('#realATM').hide();
        }
    });

    gee.hook('toggleInvoice', function(me){
        var pick = $("input[name='invoice[type]']:checked").val();
        if (pick=='e-receipt') {
            $('.e-receipt').show();
        }
        else {
            $('.e-receipt').hide();
        }
    });

    gee.hook('newShipfee', function(){
        app.cart.renewFee($("input[name='shipment']:checked").val()).renewSum();
        $('input[name="fee"]', '#checkout_form').val(app.cart.fee);
    });

    gee.hook('removeItem', function(me){
        var item_id = me.data('item_id');

        app.cart.removeItem(item_id);
    });

    gee.hook('changeQty', function(me){
        var item_id = me.data('item_id');
        var qty = me.val();
        var max = me.attr('max');
        var min = me.attr('min');

        if ($.isNumeric(max)) {
            if (Math.min(max, qty) == max) {
                qty = max;
                me.val(max);
            }
        }

        if ($.isNumeric(min)) {
            if (Math.max(min, qty) == min) {
                qty = min;
                me.val(min);
            }
        }

        app.cart.add2Cart(item_id, qty, 'force');
    });

    gee.hook("syncAll", function (me){
        var g = $.fn.geneEH,
            f = me.closest("form"),
            ta = me.data("ta"),
            so = me.data("so");

        f.find("input[name^='"+ ta +"']").each(function(){
            var n = $(this).attr("name").replace(ta, so),
                v = f.find("input[name='"+ n +"']").val();
            $(this).val(v);
        }).end().find('input[name="invoice[addr]"]').val(
            f.find('input[name="buyer[address]"]').val()
        );
    });

    gee.hook('showInvoice', function(me){
        $("input[name='invoice[type]']")[0].checked = true;
    }, 'init');

    gee.hook('showShipFee', function(me){
        me.addClass('cart_shipfee');
        $("input[name='shipment']")[0].checked = true;
        gee.newShipfee();
    }, 'init');

    gee.hook('showTotal', function(me){
        me.addClass('cart_showTotal');
    }, 'init');

    gee.hook('resetQty', function(me){
        var min = me.data('min') || 1;
        var max = me.data('max') || 10;
        var val = me.val();
        if (val > max) {
            me.val(max);
        }
        if (val < min) {
            me.val(min);
        }
    });

    gee.hook('showSum', function(me){
        me.addClass('cart_showSum');
    }, 'init');

    gee.hook('loadCart', function(me){
        app.cart.cartBox = me;
        app.cart.loadCart();
    }, 'init');

}(app, gee, jQuery));
