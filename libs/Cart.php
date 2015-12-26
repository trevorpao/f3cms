<?php
namespace F3CMS;

class Cart extends Helper
{
    const ST_INSERTED = 'Inserted';
    const ST_REMOVED  = 'Removed';
    const ST_ADDED    = 'Added';
    const ST_EMPTY    = 'Empty';

    public function __construct()
    {
        parent::__construct();
        $this->init_cart();
    }

    function do_get_items($f3, $args)
    {
        return Reaction::_return(1, array('cart' => self::get_cart()));
    }

    function do_add_item($f3, $args)
    {
        // need check depot
        $state = "";
        $pid = intval($f3->get('POST.item_id'));

        if (array_key_exists($pid, $_SESSION['cart'])) {
            if ($f3->get('POST.type') == 'force') {
                $_SESSION['cart'][$pid]['qty'] = $f3->get('POST.qty');
            }
            else {
                $_SESSION['cart'][$pid]['qty'] += $f3->get('POST.qty');
            }
            $state = self::ST_ADDED;
        }
        else {

            $tmp = fProduct::get_row($pid);

            if (!$tmp) {
                $state = self::ST_EMPTY;
            }
            else {
                $_SESSION['cart'][$pid] = array(
                    'id'    => $pid,
                    'title' => $tmp['title'],
                    'pic'   => $tmp['pic'],
                    'price' => $tmp['price'],
                    'qty'   => 1,
                    'slug'  => $tmp['slug']
                );
                $state = self::ST_INSERTED;
            }
        }

        return Reaction::_return(
            self::get_msgs()[$state]['code'],
            array('msg' => self::get_msgs()[$state]['msg'], 'cart' => self::get_cart())
        );
    }

    function do_remove_item($f3, $args)
    {
        $state = self::ST_REMOVED;
        $pid = intval($f3->get('POST.item_id'));

        if (array_key_exists($pid, $_SESSION['cart'])) {
            unset($_SESSION['cart'][$pid]);
        }

        return Reaction::_return(
            self::get_msgs()[$state]['code'],
            array('msg' => self::get_msgs()[$state]['msg'], 'cart' => self::get_cart())
        );
    }

    function do_get_count($f3, $args)
    {
        return Reaction::_return(1, array('count'=>count($_SESSION['cart'])));
    }

    static function get_cart()
    {
        return array_values($_SESSION['cart']);
    }

    public static function init_cart($force = false)
    {
        if ($force || !isset($_SESSION['cart'])) {
            $_SESSION['cart'] = array();
        }
    }

    static function get_msgs()
    {
        return array(
            'Inserted' => array('code' => '1', 'msg' => '品項新增至購物車'),
            'Removed'  => array('code' => '1', 'msg' => '品項已移除'),
            'Added'    => array('code' => '1', 'msg' => '品項數量已更新'),
            'Empty'    => array('code' => '2001', 'msg' => '品項已售完'),
        );
    }
}
