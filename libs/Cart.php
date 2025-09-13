<?php
namespace F3CMS;

/**
 * Cart 類別負責管理購物車的操作，包括新增、移除、取得品項等功能。
 * 它提供了與購物車相關的多種方法，並使用 Session 來儲存購物車的狀態。
 */
class Cart extends Helper
{
    /**
     * 購物車狀態常數
     */
    const ST_INSERTED = 'Inserted'; // 品項新增至購物車
    const ST_REMOVED = 'Removed';   // 品項已移除
    const ST_ADDED = 'Added';       // 品項數量已更新
    const ST_EMPTY = 'Empty';       // 品項已售完

    /**
     * 建構子，初始化購物車
     */
    public function __construct()
    {
        parent::__construct();
        $this->init_cart();
    }

    /**
     * 取得購物車中的所有品項
     *
     * @param $f3
     * @param $args
     * @return array 包含購物車品項的陣列
     */
    public function do_get_items($f3, $args)
    {
        return Reaction::_return(1, ['cart' => self::get_cart()]);
    }

    /**
     * 新增品項至購物車
     *
     * @param $f3
     * @param $args
     * @return array 包含操作結果與購物車內容的陣列
     */
    public function do_add_item($f3, $args)
    {
        // need check depot
        $state = '';
        $pid   = intval(f3()->get('POST.item_id'));

        if (array_key_exists($pid, $_SESSION['cart'])) {
            if ('force' == f3()->get('POST.type')) {
                $_SESSION['cart'][$pid]['qty'] = f3()->get('POST.qty');
            } else {
                $_SESSION['cart'][$pid]['qty'] += f3()->get('POST.qty');
            }
            $state = self::ST_ADDED;
        } else {
            $tmp = fProduct::get_row($pid);

            if (!$tmp) {
                $state = self::ST_EMPTY;
            } else {
                $_SESSION['cart'][$pid] = [
                    'id'    => $pid,
                    'title' => $tmp['title'],
                    'pic'   => $tmp['pic'],
                    'price' => $tmp['price'],
                    'qty'   => 1,
                    'slug'  => $tmp['slug'],
                ];
                $state = self::ST_INSERTED;
            }
        }

        return Reaction::_return(
            self::get_msgs()[$state]['code'],
            ['msg' => self::get_msgs()[$state]['msg'], 'cart' => self::get_cart()]
        );
    }

    /**
     * 從購物車中移除品項
     *
     * @param $f3
     * @param $args
     * @return array 包含操作結果與購物車內容的陣列
     */
    public function do_remove_item($f3, $args)
    {
        $state = self::ST_REMOVED;
        $pid   = intval(f3()->get('POST.item_id'));

        if (array_key_exists($pid, $_SESSION['cart'])) {
            unset($_SESSION['cart'][$pid]);
        }

        return Reaction::_return(
            self::get_msgs()[$state]['code'],
            ['msg' => self::get_msgs()[$state]['msg'], 'cart' => self::get_cart()]
        );
    }

    /**
     * 取得購物車中的品項數量
     *
     * @param $f3
     * @param $args
     * @return array 包含品項數量的陣列
     */
    public function do_get_count($f3, $args)
    {
        return Reaction::_return(1, ['count' => count($_SESSION['cart'])]);
    }

    /**
     * 取得購物車內容
     *
     * @return array 購物車中的所有品項
     */
    public static function get_cart()
    {
        return array_values($_SESSION['cart']);
    }

    /**
     * 初始化購物車
     *
     * @param bool $force 是否強制初始化
     */
    public static function init_cart($force = false)
    {
        if ($force || !isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
    }

    /**
     * 取得購物車操作的訊息對應表
     *
     * @return array 包含操作訊息的陣列
     */
    public static function get_msgs()
    {
        return [
            'Inserted' => ['code' => '1', 'msg' => '品項新增至購物車'],
            'Removed'  => ['code' => '1', 'msg' => '品項已移除'],
            'Added'    => ['code' => '1', 'msg' => '品項數量已更新'],
            'Empty'    => ['code' => '2001', 'msg' => '品項已售完'],
        ];
    }
}
